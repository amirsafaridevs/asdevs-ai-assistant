<?php
/**
 * Guard against silent bulk changes.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a stream of small changes into a bulk change once it becomes one.
 *
 * A loop that changes fifty posts one call at a time is still a bulk change,
 * and section 16.1 requires confirmation for bulk changes.
 */
final class BulkGuard {

	/**
	 * Writes to the same collection allowed before confirmation kicks in.
	 */
	private const THRESHOLD = 3;

	/**
	 * Window in seconds over which writes are counted.
	 */
	private const WINDOW = 120;

	/**
	 * Whether this action continues a run of writes to the same collection.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	public function is_repeating( ActionRequest $action, int $user_id ): bool {
		if ( $action->is_read() ) {
			return false;
		}

		return $this->count( $action, $user_id ) >= self::THRESHOLD;
	}

	/**
	 * Record a write that ran without confirmation.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	public function record( ActionRequest $action, int $user_id ): void {
		if ( $action->is_read() ) {
			return;
		}

		set_transient( $this->key( $action, $user_id ), $this->count( $action, $user_id ) + 1, self::WINDOW );
	}

	/**
	 * Clear the counter for a collection.
	 *
	 * Called once the user has confirmed a bulk change, so the confirmed run
	 * does not immediately trip the guard again.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	public function reset( ActionRequest $action, int $user_id ): void {
		delete_transient( $this->key( $action, $user_id ) );
	}

	/**
	 * Current count for this collection.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function count( ActionRequest $action, int $user_id ): int {
		$stored = get_transient( $this->key( $action, $user_id ) );

		return is_numeric( $stored ) ? (int) $stored : 0;
	}

	/**
	 * Transient key for a user and collection.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function key( ActionRequest $action, int $user_id ): string {
		return 'asdevs_ai_bulk_' . md5( $user_id . '|' . $action->method() . '|' . $action->base_route() );
	}
}
