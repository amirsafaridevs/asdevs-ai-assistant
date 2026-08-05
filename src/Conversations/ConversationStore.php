<?php
/**
 * Conversation history.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Conversations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps conversations for the person who had them, and deletes them for real.
 *
 * Nothing here builds a profile of the user (section 17.3): it stores the
 * conversation so they can look back at it, and nothing else. History is
 * per-user, so two people working on the same site never see each other's
 * conversations.
 */
final class ConversationStore {

	/**
	 * User meta key.
	 */
	public const META_KEY = 'asdevs_ai_assistant_conversations';

	/**
	 * Conversations kept per user.
	 */
	private const MAX_CONVERSATIONS = 30;

	/**
	 * Messages kept per conversation.
	 */
	private const MAX_MESSAGES = 200;

	/**
	 * Every conversation for a user, newest first.
	 *
	 * @param int $user_id The user.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::META_KEY, true );

		return is_array( $stored ) ? array_values( $stored ) : array();
	}

	/**
	 * A summary of each conversation, without the messages.
	 *
	 * @param int $user_id The user.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function index( int $user_id ): array {
		return array_map(
			static fn( array $conversation ) => array(
				'id'         => $conversation['id'],
				'title'      => $conversation['title'],
				'updated_at' => $conversation['updated_at'],
				'unfinished' => ! empty( $conversation['unfinished'] ),
			),
			$this->all( $user_id )
		);
	}

	/**
	 * One conversation.
	 *
	 * @param int    $user_id The user.
	 * @param string $id      Conversation id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $user_id, string $id ): ?array {
		foreach ( $this->all( $user_id ) as $conversation ) {
			if ( $conversation['id'] === $id ) {
				return $conversation;
			}
		}

		return null;
	}

	/**
	 * Create or replace a conversation.
	 *
	 * @param int                              $user_id    The user.
	 * @param string                           $id         Conversation id.
	 * @param string                           $title      Short title, in the user's language.
	 * @param array<int, array<string, mixed>> $messages   The messages.
	 * @param bool                             $unfinished Whether work was left mid-flight.
	 */
	public function save( int $user_id, string $id, string $title, array $messages, bool $unfinished = false ): void {
		$conversations = $this->all( $user_id );
		$messages      = array_slice( $messages, -self::MAX_MESSAGES );

		$entry = array(
			'id'         => $id,
			'title'      => $title,
			'messages'   => $messages,
			'unfinished' => $unfinished,
			'updated_at' => time(),
		);

		$conversations = array_values(
			array_filter(
				$conversations,
				static fn( array $conversation ) => $conversation['id'] !== $id
			)
		);

		array_unshift( $conversations, $entry );

		update_user_meta( $user_id, self::META_KEY, array_slice( $conversations, 0, self::MAX_CONVERSATIONS ) );
	}

	/**
	 * Delete one conversation.
	 *
	 * @param int    $user_id The user.
	 * @param string $id      Conversation id.
	 */
	public function delete( int $user_id, string $id ): void {
		$remaining = array_values(
			array_filter(
				$this->all( $user_id ),
				static fn( array $conversation ) => $conversation['id'] !== $id
			)
		);

		if ( array() === $remaining ) {
			$this->clear( $user_id );

			return;
		}

		update_user_meta( $user_id, self::META_KEY, $remaining );
	}

	/**
	 * Delete everything for a user. This removes the row, it does not hide it.
	 *
	 * @param int $user_id The user.
	 */
	public function clear( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}
}
