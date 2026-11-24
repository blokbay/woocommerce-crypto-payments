<?php
/**
 * Removes the plugin settings when the plugin is deleted. Orders and their notes stay untouched.
 *
 * @package Blokbay_Crypto_Gateway
 * @license GPL-2.0-or-later
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {
	$blokbay_wc_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $blokbay_wc_site_ids as $blokbay_wc_site_id ) {
		delete_blog_option( (int) $blokbay_wc_site_id, 'woocommerce_blokbay_settings' );
	}
} else {
	delete_option( 'woocommerce_blokbay_settings' );
}
