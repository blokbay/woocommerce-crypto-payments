<?php
/**
 * WooCommerce payment method "Crypto (Blokbay)": creates a Blokbay payment, sends the customer to the hosted payment page
 * and marks the order paid when the signed webhook payment.paid arrives (or when the customer returns and the API says paid).
 *
 * @package Blokbay_Crypto_Gateway
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payment gateway "blokbay".
 */
class Blokbay_WC_Gateway extends WC_Payment_Gateway {
	const META_ID = '_blokbay_payment_id';

	/**
	 * Sets up the gateway, its settings and its hooks.
	 */
	public function __construct() {
		$this->id                 = 'blokbay';
		$this->method_title       = __( 'Crypto (Blokbay)', 'blokbay-crypto-gateway' );
		$this->method_description = __( 'Accept BTC, USDC, USDT and ETH. Funds go directly to your wallet. 1% per payment, no fixed costs.', 'blokbay-crypto-gateway' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->icon               = plugins_url( 'assets/images/crypto.svg', BLOKBAY_WC_FILE );
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title', __( 'Pay with crypto', 'blokbay-crypto-gateway' ) );
		$this->description = $this->get_option( 'description', __( 'Pay with BTC, USDC, USDT or ETH from your wallet.', 'blokbay-crypto-gateway' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_blokbay', array( $this, 'webhook' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_check' ) );
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable', 'blokbay-crypto-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable crypto payments with Blokbay', 'blokbay-crypto-gateway' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'   => __( 'Title', 'blokbay-crypto-gateway' ),
				'type'    => 'text',
				'default' => __( 'Pay with crypto', 'blokbay-crypto-gateway' ),
			),
			'description'    => array(
				'title'   => __( 'Description', 'blokbay-crypto-gateway' ),
				'type'    => 'textarea',
				'default' => __( 'Pay with BTC, USDC, USDT or ETH from your wallet.', 'blokbay-crypto-gateway' ),
			),
			'secret_key'     => array(
				'title'       => __( 'Secret key', 'blokbay-crypto-gateway' ),
				'type'        => 'password',
				'description' => __( 'sk_live_... from Blokbay, Payments on your site.', 'blokbay-crypto-gateway' ),
			),
			'public_key'     => array(
				'title'       => __( 'Public key', 'blokbay-crypto-gateway' ),
				'type'        => 'text',
				'description' => __( 'pk_live_... (checked on every webhook).', 'blokbay-crypto-gateway' ),
			),
			'webhook_secret' => array(
				'title'       => __( 'Webhook signing secret', 'blokbay-crypto-gateway' ),
				'type'        => 'password',
				/* translators: %s: webhook address of this store, to paste in the Blokbay dashboard. */
				'description' => sprintf( __( 'whsec_... In Blokbay set the webhook URL to: %s', 'blokbay-crypto-gateway' ), esc_html( self::webhook_url() ) ),
			),
			'base_url'       => array(
				'title'       => __( 'API address', 'blokbay-crypto-gateway' ),
				'type'        => 'text',
				'default'     => Blokbay_API::DEFAULT_BASE,
				'description' => __( 'Leave as it is.', 'blokbay-crypto-gateway' ),
			),
		);
	}

	/**
	 * Keeps the API address on https (http only for a local test server).
	 *
	 * @param string $key   Field key.
	 * @param string $value Posted value.
	 * @return string
	 */
	public function validate_base_url_field( $key, $value ) {
		$url    = esc_url_raw( trim( (string) $value ), array( 'https', 'http' ) );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		$local  = in_array( $host, array( 'localhost', '127.0.0.1' ), true );
		if ( ! $url || ! $host || ( 'https' !== $scheme && ! $local ) ) {
			return Blokbay_API::DEFAULT_BASE;
		}
		return untrailingslashit( $url );
	}

	/**
	 * Webhook address of this store.
	 *
	 * @return string
	 */
	public static function webhook_url() {
		return add_query_arg( 'wc-api', 'blokbay', home_url( '/' ) );
	}

	/**
	 * Only for stores priced in a currency accepted by Blokbay (USD, EUR, GBP, CHF, JPY, CAD, AUD, SGD, HKD, AED).
	 *
	 * @return bool
	 */
	public function is_available() {
		return 'yes' === $this->enabled && $this->get_option( 'secret_key' ) && Blokbay_API::supported( get_woocommerce_currency() ) && parent::is_available();
	}

	/**
	 * Settings page, with a notice when the store currency is not accepted by Blokbay (the method stays hidden at checkout).
	 */
	public function admin_options() {
		$currency = get_woocommerce_currency();
		if ( ! Blokbay_API::supported( $currency ) ) {
			/* translators: 1: store currency, 2: list of accepted currencies. */
			echo '<div class="notice notice-warning inline" id="blokbay-currency-warning"><p>' . esc_html( sprintf( __( 'Blokbay accepts prices in %2$s. This store works in %1$s, so the crypto payment method stays hidden at checkout.', 'blokbay-crypto-gateway' ), $currency, implode( ', ', array_keys( Blokbay_API::CURRENCIES ) ) ) ) . '</p></div>';
		}
		parent::admin_options();
	}

	/**
	 * API client with the saved secret key.
	 *
	 * @return Blokbay_API
	 */
	public function api() {
		return new Blokbay_API( $this->get_option( 'secret_key' ), $this->get_option( 'base_url', Blokbay_API::DEFAULT_BASE ) );
	}

	/**
	 * Creates the Blokbay payment and sends the customer to the payment page.
	 *
	 * @param int $order_id Order id.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! Blokbay_API::supported( $order->get_currency() ) ) {
			wc_add_notice( __( 'Crypto payments are not available for orders in this currency.', 'blokbay-crypto-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}
		$currency = strtoupper( $order->get_currency() );
		$fields   = array(
			'amount'         => Blokbay_API::amount( $order->get_total(), $currency ),
			'currency'       => $currency,
			'reference'      => (string) $order->get_order_number(),
			/* translators: %s: order number. */
			'description'    => sprintf( __( 'Order %s', 'blokbay-crypto-gateway' ), $order->get_order_number() ),
			'customer_email' => (string) $order->get_billing_email(),
			'url_success'    => $this->get_return_url( $order ),
			'url_cancel'     => $order->get_cancel_order_url_raw(),
		);
		// Same order, same amount and currency: the same payment (safe retries). If that payment has expired, a new one.
		$key = 'wc-' . $order->get_id() . '-' . $order->get_order_key() . '-' . $fields['amount'] . ( 'USD' === $currency ? '' : '-' . $currency );
		$res = $this->api()->create_payment( $fields, $key );
		// The order key is random, so the key is unique per store; a payment of another order is still never used.
		$foreign = ! empty( $res['id'] ) && ! self::matches_order( $res, $fields );
		if ( $foreign || ( isset( $res['status'] ) && 'expired' === $res['status'] ) ) {
			$res = $this->api()->create_payment( $fields, $key . '-' . time() );
		}
		if ( empty( $res['id'] ) || empty( $res['url'] ) || ! self::matches_order( $res, $fields ) ) {
			wc_add_notice( __( 'The crypto payment could not be created. Please try again or choose another method.', 'blokbay-crypto-gateway' ), 'error' );
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning( 'Blokbay: ' . ( isset( $res['error'] ) ? $res['error'] : 'error' ), array( 'source' => 'blokbay' ) );
			}
			return array( 'result' => 'failure' );
		}
		$order->update_meta_data( self::META_ID, $res['id'] );
		$order->update_status( 'pending', __( 'Waiting for the crypto payment (Blokbay).', 'blokbay-crypto-gateway' ) );
		$order->save();
		return array(
			'result'   => 'success',
			'redirect' => $res['url'],
		);
	}

	/**
	 * True when the payment returned by the API belongs to this order (same reference, amount and currency).
	 *
	 * @param array $payment Blokbay payment.
	 * @param array $fields  Fields sent for this order.
	 * @return bool
	 */
	public static function matches_order( array $payment, array $fields ) {
		return isset( $payment['reference'], $payment['amount'], $payment['currency'] )
			&& (string) $payment['reference'] === (string) $fields['reference']
			&& (string) $payment['amount'] === (string) $fields['amount']
			&& (string) $payment['currency'] === (string) $fields['currency'];
	}

	/**
	 * Webhook endpoint: https://your-site/?wc-api=blokbay (authenticated by the HMAC signature, not by a nonce:
	 * the request comes from the Blokbay server, not from a logged-in user).
	 */
	public function webhook() {
		$raw                = (string) file_get_contents( 'php://input' );
		$header             = isset( $_SERVER['HTTP_BLOKBAY_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_BLOKBAY_SIGNATURE'] ) ) : '';
		list( $code, $msg ) = $this->handle_webhook( $raw, $header );
		status_header( $code );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $msg );
		exit;
	}

	/**
	 * Checks and applies a webhook event.
	 *
	 * @param string   $raw    Raw body.
	 * @param string   $header Blokbay-Signature header.
	 * @param int|null $now    Current time (tests).
	 * @return array{0: int, 1: string} HTTP code and message.
	 */
	public function handle_webhook( $raw, $header, $now = null ) {
		if ( ! Blokbay_API::verify_signature( $raw, $header, $this->get_option( 'webhook_secret' ), $now ) ) {
			return array( 400, 'bad signature' );
		}
		$ev = json_decode( $raw, true );
		if ( ! is_array( $ev ) || empty( $ev['type'] ) ) {
			return array( 400, 'bad payload' );
		}
		$pk = trim( (string) $this->get_option( 'public_key' ) );
		if ( $pk && ( ! isset( $ev['account'] ) || $ev['account'] !== $pk ) ) {
			return array( 400, 'other account' );
		}
		if ( 'ping' === $ev['type'] ) {
			return array( 200, 'pong' );
		}
		$p = isset( $ev['data'] ) && is_array( $ev['data'] ) ? $ev['data'] : array();
		if ( empty( $p['id'] ) ) {
			return array( 400, 'no payment' );
		}
		$order = $this->find_order( $p['id'] );
		// A previous payment of the same order (expired and then paid late): found by the order reference.
		if ( ! $order && ! empty( $p['reference'] ) && ctype_digit( (string) $p['reference'] ) ) {
			$o     = wc_get_order( (int) $p['reference'] );
			$order = ( $o && $o->get_payment_method() === $this->id && (string) $o->get_order_number() === (string) $p['reference'] ) ? $o : null;
			// Only a late "paid" counts here: the expiry of an old payment must not cancel the order while a newer payment is open.
			if ( $order && ( ! isset( $p['status'] ) || 'paid' !== $p['status'] ) ) {
				return array( 200, 'old payment' );
			}
		}
		if ( ! $order ) {
			return array( 200, 'unknown payment' ); // Not an order of this store (payment link, test): nothing to do.
		}
		return $this->apply_payment( $order, $p );
	}

	/**
	 * Same rules for the webhook and for the check on the thank-you page.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $p     Blokbay payment.
	 * @return array{0: int, 1: string}
	 */
	public function apply_payment( $order, array $p ) {
		$status = isset( $p['status'] ) ? $p['status'] : '';
		if ( 'paid' === $status ) {
			$currency = strtoupper( $order->get_currency() );
			if ( Blokbay_API::amount( $order->get_total(), $currency ) !== ( isset( $p['amount'] ) ? (string) $p['amount'] : '' ) || $currency !== ( isset( $p['currency'] ) ? $p['currency'] : '' ) ) {
				$order->add_order_note( __( 'Blokbay: amount does not match the order, check it in the Blokbay dashboard.', 'blokbay-crypto-gateway' ) );
				return array( 200, 'amount mismatch' );
			}
			if ( $order->is_paid() ) {
				return array( 200, 'already paid' );
			}
			$tx = isset( $p['tx'] ) ? (string) $p['tx'] : '';
			/* translators: 1: coin (USDC, USDT, ETH, BTC), 2: network, 3: transaction hash. */
			$order->add_order_note( sprintf( __( 'Blokbay: paid in %1$s on %2$s. Transaction %3$s', 'blokbay-crypto-gateway' ), isset( $p['coin'] ) ? $p['coin'] : '', isset( $p['network'] ) ? $p['network'] : '', $tx ) );
			$order->payment_complete( $tx );
			return array( 200, 'paid' );
		}
		if ( 'expired' === $status && $order->has_status( 'pending' ) ) {
			$order->update_status( 'cancelled', __( 'Blokbay: the crypto payment was not made in time.', 'blokbay-crypto-gateway' ) );
			return array( 200, 'expired' );
		}
		return array( 200, 'ok' );
	}

	/**
	 * Order that holds this Blokbay payment id. HPOS: meta_query of wc_get_orders. Posts storage: wc_get_orders ignores
	 * meta_query there (it would return the latest order), so a direct meta lookup. Always re-checked on the order itself.
	 * The lookup runs only when a signed webhook arrives, on one indexed meta key.
	 *
	 * @param string $payment_id Blokbay payment id.
	 * @return WC_Order|null
	 */
	public function find_order( $payment_id ) {
		$payment_id = (string) $payment_id;
		if ( '' === $payment_id ) {
			return null;
		}
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		if ( $hpos ) {
			$orders = wc_get_orders(
				array(
					'limit'          => 1,
					'payment_method' => $this->id,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only on a signed webhook.
						array(
							'key'   => self::META_ID,
							'value' => $payment_id,
						),
					),
				)
			);
		} else {
			$ids    = get_posts(
				array(
					'post_type'      => 'shop_order',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => self::META_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- only on a signed webhook.
					'meta_value'     => $payment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- only on a signed webhook.
				)
			);
			$orders = $ids ? array( wc_get_order( $ids[0] ) ) : array();
		}
		foreach ( $orders as $o ) {
			if ( $o && $o->get_payment_method() === $this->id && (string) $o->get_meta( self::META_ID ) === $payment_id ) {
				return $o;
			}
		}
		return null;
	}

	/**
	 * If the webhook is late, the thank-you page asks the API (only with the secret key, from the server).
	 *
	 * @param int $order_id Order id.
	 */
	public function thankyou_check( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->is_paid() ) {
			return;
		}
		$id = $order->get_meta( self::META_ID );
		if ( ! $id ) {
			return;
		}
		$p = $this->api()->get_payment( $id );
		if ( isset( $p['id'] ) && $p['id'] === $id ) {
			$this->apply_payment( $order, $p );
		}
	}
}
