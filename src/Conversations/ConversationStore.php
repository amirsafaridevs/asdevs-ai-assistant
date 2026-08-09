<?php
/**
 * Active conversation state.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Conversations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the one active chat for each person, and deletes it for real.
 *
 * Nothing here builds a profile of the user (section 17.3): it stores the
 * conversation so they can continue it after closing the panel, and nothing
 * else. State is per-user, so two people on the same site never see each
 * other's chat. There is no history list — only the current chat.
 */
final class ConversationStore {

	/**
	 * User meta key for the active chat.
	 */
	public const META_KEY = 'asdevs_ai_assistant_active_chat';

	/**
	 * Legacy multi-conversation list (migrated once on read).
	 */
	public const LEGACY_META_KEY = 'asdevs_ai_assistant_conversations';

	/**
	 * Messages kept in the active chat.
	 */
	private const MAX_MESSAGES = 500;

	/**
	 * The active conversation for a user, or null when empty.
	 *
	 * @param int $user_id The user.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $user_id ): ?array {
		$stored = get_user_meta( $user_id, self::META_KEY, true );

		if ( is_array( $stored ) && isset( $stored['id'], $stored['messages'] ) && is_array( $stored['messages'] ) ) {
			return $this->normalize( $stored );
		}

		$legacy = $this->migrate_legacy( $user_id );

		return null === $legacy ? null : $this->normalize( $legacy );
	}

	/**
	 * Replace the active conversation entirely.
	 *
	 * @param int                              $user_id    The user.
	 * @param string                           $id         Conversation id.
	 * @param string                           $title      Short title, in the user's language.
	 * @param array<int, array<string, mixed>> $messages   Full message trail for the model.
	 * @param array<string, mixed>|null        $pending    Mid-turn confirmation, if any.
	 * @param array<string, mixed>|null        $choices    Open choice sheet, if any.
	 * @param bool                             $unfinished Whether work was left mid-flight.
	 */
	public function save(
		int $user_id,
		string $id,
		string $title,
		array $messages,
		?array $pending = null,
		?array $choices = null,
		bool $unfinished = false
	): void {
		$messages = array_values( array_slice( $messages, -self::MAX_MESSAGES ) );

		$entry = array(
			'id'         => $id,
			'title'      => $title,
			'messages'   => $messages,
			'pending'    => $pending,
			'choices'    => $choices,
			'unfinished' => $unfinished || null !== $pending,
			'updated_at' => time(),
		);

		update_user_meta( $user_id, self::META_KEY, $entry );

		// Drop the old multi-chat list so nothing lists past threads.
		delete_user_meta( $user_id, self::LEGACY_META_KEY );
	}

	/**
	 * Delete the active conversation. This removes the row, it does not hide it.
	 *
	 * @param int $user_id The user.
	 */
	public function clear( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
		delete_user_meta( $user_id, self::LEGACY_META_KEY );
	}

	/**
	 * Lift the newest legacy conversation into the single-chat key once.
	 *
	 * @param int $user_id The user.
	 *
	 * @return array<string, mixed>|null
	 */
	private function migrate_legacy( int $user_id ): ?array {
		$legacy = get_user_meta( $user_id, self::LEGACY_META_KEY, true );

		if ( ! is_array( $legacy ) || array() === $legacy ) {
			return null;
		}

		$first = $legacy[0] ?? null;

		if ( ! is_array( $first ) || ! isset( $first['id'], $first['messages'] ) || ! is_array( $first['messages'] ) ) {
			delete_user_meta( $user_id, self::LEGACY_META_KEY );

			return null;
		}

		$entry = array(
			'id'         => (string) $first['id'],
			'title'      => isset( $first['title'] ) ? (string) $first['title'] : '',
			'messages'   => array_values( $first['messages'] ),
			'pending'    => null,
			'choices'    => null,
			'unfinished' => ! empty( $first['unfinished'] ),
			'updated_at' => isset( $first['updated_at'] ) ? (int) $first['updated_at'] : time(),
		);

		update_user_meta( $user_id, self::META_KEY, $entry );
		delete_user_meta( $user_id, self::LEGACY_META_KEY );

		return $entry;
	}

	/**
	 * Guarantee the shape the browser expects.
	 *
	 * @param array<string, mixed> $conversation Raw entry.
	 *
	 * @return array<string, mixed>
	 */
	private function normalize( array $conversation ): array {
		return array(
			'id'         => (string) ( $conversation['id'] ?? '' ),
			'title'      => isset( $conversation['title'] ) ? (string) $conversation['title'] : '',
			'messages'   => isset( $conversation['messages'] ) && is_array( $conversation['messages'] )
				? array_values( $conversation['messages'] )
				: array(),
			'pending'    => isset( $conversation['pending'] ) && is_array( $conversation['pending'] )
				? $conversation['pending']
				: null,
			'choices'    => isset( $conversation['choices'] ) && is_array( $conversation['choices'] )
				? $conversation['choices']
				: null,
			'unfinished' => ! empty( $conversation['unfinished'] ),
			'updated_at' => isset( $conversation['updated_at'] ) ? (int) $conversation['updated_at'] : 0,
		);
	}
}
