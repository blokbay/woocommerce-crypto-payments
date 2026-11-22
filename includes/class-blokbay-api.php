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
	 * USD amount with 2 decimals, as a string ("249.90").
	 *
	 * @param float|string $value Amount.
	 * @return string
	 */
	public static function amount( $value ) {
		return number_format( round( (float) $value, 2 ), 2, '.', '' );
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
