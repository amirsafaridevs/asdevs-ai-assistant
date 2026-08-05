<?php
/**
 * Shared REST controller behaviour.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for this plugin's endpoints.
 *
 * Every endpoint checks the signed-in user and the request nonce here, and
 * every endpoint sanitizes what it receives before it is used.
 */
abstract class Controller {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'asdevs-ai/v1';

	/**
	 * Register this controller's routes.
	 */
	abstract public function register_routes(): void;

	/**
	 * Whether the current request may use the assistant at all.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'asdevs_ai_not_allowed',
				__( 'You need to be signed in to use the assistant.', 'asdevs-ai-assistant' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! $this->has_valid_nonce() ) {
			return new WP_Error(
				'asdevs_ai_bad_nonce',
				__( 'This page has been open for a while. Reload it and try again.', 'asdevs-ai-assistant' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Whether the request carries a valid REST nonce.
	 *
	 * WordPress already enforces this for cookie-authenticated REST requests;
	 * it is repeated here so every entry point checks it in its own right.
	 */
	protected function has_valid_nonce(): bool {
		$nonce = '';

		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the nonce check.
		if ( '' === $nonce && isset( $_REQUEST['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the nonce check.
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}

		return '' !== $nonce && false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Recursively sanitize a value that came from the browser.
	 *
	 * @param mixed $value The value.
	 * @param int   $depth Current depth.
	 *
	 * @return mixed
	 */
	protected function sanitize_deep( $value, int $depth = 0 ) {
		if ( $depth > 8 ) {
			return null;
		}

		if ( is_array( $value ) ) {
			$clean = array();

			foreach ( array_slice( $value, 0, 200, true ) as $key => $item ) {
				$clean[ is_string( $key ) ? sanitize_text_field( $key ) : $key ] = $this->sanitize_deep( $item, $depth + 1 );
			}

			return $clean;
		}

		if ( is_string( $value ) ) {
			return sanitize_textarea_field( $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return null;
	}

	/**
	 * A sanitized array parameter.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $key     Parameter name.
	 *
	 * @return array<mixed>
	 */
	protected function array_param( WP_REST_Request $request, string $key ): array {
		$value = $request->get_param( $key );

		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = $this->sanitize_deep( $value );

		return is_array( $clean ) ? $clean : array();
	}
}
