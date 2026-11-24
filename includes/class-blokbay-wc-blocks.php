<?php
/**
 * Checkout block support (WooCommerce Blocks): registers the method; the payment itself happens on the Blokbay payment page.
 *
 * @package Blokbay_Crypto_Gateway
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Payment method type "blokbay" for the Cart and Checkout blocks.
 */
final class Blokbay_WC_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name (same id as the gateway).
	 *
	 * @var string
	 */
	protected $name = 'blokbay';

	/**
	 * Loads the gateway settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_blokbay_settings', array() );
	}

	/**
	 * Active when the gateway is available for the current cart.
	 *
	 * @return bool
	 */
	public function is_active() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		return isset( $gateways['blokbay'] ) && $gateways['blokbay']->is_available();
	}

	/**
	 * Script that registers the method in the checkout block.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'blokbay-blocks',
			plugins_url( 'assets/js/blocks.js', BLOKBAY_WC_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			BLOKBAY_WC_VERSION,
			true
		);
		return array( 'blokbay-blocks' );
	}

	/**
	 * Data passed to the script (title and description set by the merchant).
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => isset( $this->settings['title'] ) ? wp_strip_all_tags( (string) $this->settings['title'] ) : __( 'Pay with crypto', 'blokbay-crypto-gateway' ),
			'description' => isset( $this->settings['description'] ) ? wp_strip_all_tags( (string) $this->settings['description'] ) : '',
			'supports'    => array( 'products' ),
		);
	}
}
