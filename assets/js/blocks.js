/* Blokbay: payment method for the WooCommerce checkout block. No build step needed. GPL-2.0-or-later */
( function () {
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var settings = ( window.wc && window.wc.wcSettings && window.wc.wcSettings.getSetting( 'blokbay_data', {} ) ) || {};
	if ( ! reg || ! window.wp || ! window.wp.element ) return;
	var el = window.wp.element.createElement;
	var decode = ( window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities ) || function ( s ) { return s; };
	var title = decode( settings.title || 'Pay with crypto' );
	var Content = function () { return el( 'div', null, decode( settings.description || '' ) ); };
	reg.registerPaymentMethod( {
		name: 'blokbay',
		label: el( 'span', null, title ),
		ariaLabel: title,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () { return true; },
		supports: { features: settings.supports || [ 'products' ] }
	} );
} )();
