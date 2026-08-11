<?php
/**
 * Confirmation tokens for risky actions.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issues and redeems one-time confirmations for level 3 actions.
 *
 * The token is bound to the exact action and to the user who was shown the
 * consequence, so a confirmation cannot be reused for a different action and a
 * blanket "just do everything from now on" cannot exist.
 */
final class ConfirmationTokens {

	/**
	 * How long a pending confirmation stays valid, in seconds.
	 */
	private const TTL = 600;

	/**
	 * Issue a token for an action awaiting confirmation.
	 *
	 * @param string $fingerprint Action fingerprint.
	 * @param int    $user_id     The acting user.
	 */
	public function issue( string $fingerprint, int $user_id ): string {
		$token = wp_generate_password( 32, false, false );

		set_transient( $this->key( $token ), $user_id . '|' . $fingerprint, self::TTL );

		return $token;
	}

	/**
	 * Redeem a token. A token is valid once.
	 *
	 * @param string $token       The token.
	 * @param string $fingerprint Action fingerprint it must match.
	 * @param int    $user_id     The acting user.
	 */
	public function redeem( string $token, string $fingerprint, int $user_id ): bool {
		if ( '' === $token ) {
			return false;
		}

		$stored = get_transient( $this->key( $token ) );

		if ( ! is_string( $stored ) ) {
			return false;
		}

		delete_transient( $this->key( $token ) );

		return hash_equals( $user_id . '|' . $fingerprint, $stored );
	}

	/**
	 * Transient key for a token.
	 *
	 * @param string $token The token.
	 */
	private function key( string $token ): string {
		return 'asdevs_ai_confirm_' . hash( 'sha256', $token );
	}
}
