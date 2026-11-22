<?php
/**
 * Plugin Name:          Blokbay Crypto Gateway
 * Plugin URI:           https://blokbay.com/crypto-payment-gateway
 * Description:          Accept BTC, USDC, USDT and ETH. Funds go from the buyer directly to your wallet in the same transaction. 1% per payment, no monthly fee.
 * Version:              1.0.0
 * Requires at least:    6.6
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Blokbay
 * Author URI:           https://blokbay.com
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          blokbay-crypto-gateway
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.3
 *
 * @package Blokbay_Crypto_Gateway
 */

defined( 'ABSPATH' ) || exit;

define( 'BLOKBAY_WC_VERSION', '1.0.0' );
define( 'BLOKBAY_WC_FILE', __FILE__ );

/**
 * Declares compatibility with High-Performance Order Storage and with the Cart and Checkout blocks.
 */
function blokbay_wc_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'blokbay_wc_declare_compatibility' );

/**
 * Loads the gateway once WooCommerce is available.
 */
function blokbay_wc_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	require_once __DIR__ . '/includes/class-blokbay-api.php';
	require_once __DIR__ . '/includes/class-blokbay-wc-gateway.php';
	add_filter( 'woocommerce_payment_gateways', 'blokbay_wc_add_gateway' );
}
add_action( 'plugins_loaded', 'blokbay_wc_init' );

/**
 * Adds the gateway to the WooCommerce list.
 *
 * @param array $methods Gateway class names.
 * @return array
 */
function blokbay_wc_add_gateway( $methods ) {
	$methods[] = 'Blokbay_WC_Gateway';
	return $methods;
}

/**
 * Registers the payment method for the checkout block.
 */
function blokbay_wc_blocks_init() {
	if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}
	require_once __DIR__ . '/includes/class-blokbay-wc-blocks.php';
	add_action( 'woocommerce_blocks_payment_method_type_registration', 'blokbay_wc_register_block_method' );
}
add_action( 'woocommerce_blocks_loaded', 'blokbay_wc_blocks_init' );

/**
 * Registers the block integration.
 *
 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry Payment method registry.
 */
function blokbay_wc_register_block_method( $registry ) {
	$registry->register( new Blokbay_WC_Blocks() );
}

/**
 * Adds a "Settings" link on the Plugins screen.
 *
 * @param array $links Action links.
 * @return array
 */
function blokbay_wc_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=blokbay' ) ) . '">' . esc_html__( 'Settings', 'blokbay-crypto-gateway' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'blokbay_wc_action_links' );
