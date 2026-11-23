<?php
/**
 * Blokbay API client and webhook signature check.
 *
 * @package Blokbay_Crypto_Gateway
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Blokbay payments API (https://blokbay.com/docs/api) with the merchant's secret key.
 */
class Blokbay_API {
	const DEFAULT_BASE = 'https://blokbay.com';
	const TOLERANCE    = 300;

	/**
	 * Secret key (sk_live_...).
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * API base address.
	 *
	 * @var string
	 */
	private $base;

	/**
	 * Optional transport for unit tests: function ( $method, $url, $headers, $body ) returning array( 'code' => int, 'body' => string ).
	 *
	 * @var callable|null
	 */
	private $transport;

	/**
	 * Constructor.
	 *
	 * @param string        $secret_key Secret key.
	 * @param string        $base       API base address.
	 * @param callable|null $transport  Optional transport (tests).
	 */
	public function __construct( $secret_key, $base = self::DEFAULT_BASE, $transport = null ) {
		$this->secret_key = trim( (string) $secret_key );
		$this->base       = rtrim( $base ? $base : self::DEFAULT_BASE, '/' );
		$this->transport  = $transport;
	}

	/**
	 * Currencies accepted by the Blokbay API, with their decimals (JPY has none). The buyer pays in crypto at the current
	 * exchange rate; amount and currency come back in the payment and in webhooks exactly as sent.
	 */
	const CURRENCIES = array(
		'USD' => 2,
		'EUR' => 2,
		'GBP' => 2,
		'CHF' => 2,
		'JPY' => 0,
		'CAD' => 2,
		'AUD' => 2,
		'SGD' => 2,
		'HKD' => 2,
		'AED' => 2,
	);

	/**
	 * True when the currency is accepted by Blokbay.
	 *
	 * @param string $currency ISO 4217 code.
	 * @return bool
	 */
	public static function supported( $currency ) {
		return isset( self::CURRENCIES[ strtoupper( (string) $currency ) ] );
	}

	/**
	 * Amount as a string with the decimals of the currency ("249.90", JPY "25000").
	 *
	 * @param float|string $value    Amount.
	 * @param string       $currency ISO 4217 code (default USD).
	 * @return string
	 */
	public static function amount( $value, $currency = 'USD' ) {
		$code = strtoupper( (string) $currency );
		$dec  = isset( self::CURRENCIES[ $code ] ) ? self::CURRENCIES[ $code ] : 2;
		return number_format( round( (float) $value, $dec ), $dec, '.', '' );
	}

	/**
	 * Creates a payment (POST /api/v1/payments).
	 *
	 * @param array  $data            Payment fields.
	 * @param string $idempotency_key Idempotency key.
	 * @return array
	 */
	public function create_payment( array $data, $idempotency_key = '' ) {
		return $this->request( 'POST', '/api/v1/payments', $data, $idempotency_key );
	}

	/**
	 * Reads a payment (GET /api/v1/payments/:id).
	 *
	 * @param string $id Payment id (gp_...).
	 * @return array
	 */
	public function get_payment( $id ) {
		if ( ! preg_match( '/^gp_[a-z0-9]+$/', (string) $id ) ) {
			return array( 'error' => 'bad_id' );
		}
		return $this->request( 'GET', '/api/v1/payments/' . $id );
	}

	/**
	 * Sends a request and decodes the JSON answer.
	 *
	 * @param string     $method          HTTP method.
	 * @param string     $path            Path.
	 * @param array|null $data            Body.
	 * @param string     $idempotency_key Idempotency key.
	 * @return array
	 */
	private function request( $method, $path, $data = null, $idempotency_key = '' ) {
		$headers = array(
			'Authorization' => 'Bearer ' . $this->secret_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);
		if ( $idempotency_key ) {
			$headers['Idempotency-Key'] = substr( (string) $idempotency_key, 0, 100 );
		}
		$body = null === $data ? null : wp_json_encode( $data );
		$res  = $this->transport
			? call_user_func( $this->transport, $method, $this->base . $path, $headers, $body )
			: self::wp_transport( $method, $this->base . $path, $headers, $body );
		$json = json_decode( isset( $res['body'] ) ? $res['body'] : '', true );
		if ( ! is_array( $json ) ) {
			return array(
				'error' => 'bad_response',
				'code'  => isset( $res['code'] ) ? $res['code'] : 0,
			);
		}
		$json['_code'] = isset( $res['code'] ) ? (int) $res['code'] : 0;
		return $json;
	}

	/**
	 * HTTP transport with the WordPress HTTP API.
	 *
	 * @param string      $method  HTTP method.
	 * @param string      $url     Address.
	 * @param array       $headers Headers.
	 * @param string|null $body    Body.
	 * @return array
	 */
	private static function wp_transport( $method, $url, $headers, $body ) {
		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => 20,
			'redirection' => 0,
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}
		$r = wp_remote_request( $url, $args );
		if ( is_wp_error( $r ) ) {
			return array(
				'code' => 0,
				'body' => '',
			);
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $r ),
			'body' => (string) wp_remote_retrieve_body( $r ),
		);
	}

	/**
	 * Checks the Blokbay-Signature header: t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<raw body>" with the signing secret>.
	 *
	 * @param string   $raw_body Raw request body.
	 * @param string   $header   Blokbay-Signature header.
	 * @param string   $secret   Webhook signing secret.
	 * @param int|null $now      Current time (tests).
	 * @return bool
	 */
	public static function verify_signature( $raw_body, $header, $secret, $now = null ) {
		if ( ! $secret || ! $header ) {
			return false;
		}
		$parts = array();
		foreach ( explode( ',', (string) $header ) as $kv ) {
			$p = explode( '=', trim( $kv ), 2 );
			if ( 2 === count( $p ) ) {
				$parts[ $p[0] ] = $p[1];
			}
		}
		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) || ! ctype_digit( $parts['t'] ) ) {
			return false;
		}
		$now = null === $now ? time() : (int) $now;
		if ( abs( $now - (int) $parts['t'] ) > self::TOLERANCE ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $parts['t'] . '.' . $raw_body, $secret );
		return hash_equals( $expected, $parts['v1'] );
	}
}
