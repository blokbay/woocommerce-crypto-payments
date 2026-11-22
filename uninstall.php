<?php
/**
 * Removes the plugin settings when the plugin is deleted. Orders and their notes stay untouched.
 *
 * @package Blokbay_Crypto_Gateway
 * @license GPL-2.0-or-later
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_blokbay_settings' );
