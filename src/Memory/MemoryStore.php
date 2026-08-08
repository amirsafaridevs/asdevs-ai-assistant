<?php
/**
 * Persistent assistant memory.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Memory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores memory as JSON rows inside a WordPress option.
 *
 * Each row is a small note the assistant may keep across conversations. Only
 * administrators reach this store (enforced at the REST layer).
 */
final class MemoryStore {

	/**
	 * Option name.
	 */
	public const OPTION = 'asdevs_ai_assistant_memory';

	/**
	 * Hard cap on stored rows.
	 */
	private const MAX_ITEMS = 50;

	/**
	 * Hard cap on a single note.
	 */
	private const MAX_CONTENT = 2000;

	/**
	 * Every memory row, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$items = array();

		foreach ( $stored as $row ) {
			$item = $this->normalize( $row );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		usort(
			$items,
			static fn( array $a, array $b ): int => (int) $b['updated_at'] <=> (int) $a['updated_at']
		);

		return array_values( $items );
	}

	/**
	 * One row by id.
	 *
	 * @param string $id Memory id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		foreach ( $this->all() as $item ) {
			if ( (string) $item['id'] === $id ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Create a row, or update when an id is given.
	 *
	 * @param string      $content Note text.
	 * @param string|null $id      Existing id to update, or null to create.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function write( string $content, ?string $id = null ) {
		$content = trim( wp_strip_all_tags( $content ) );

		if ( '' === $content ) {
			return new \WP_Error(
				'asdevs_ai_memory_empty',
				__( 'Memory content cannot be empty.', 'asdevs-ai-assistant' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $content ) > self::MAX_CONTENT ) {
			$content = substr( $content, 0, self::MAX_CONTENT );
		}

		$items = $this->all();
		$now   = time();

		if ( null !== $id && '' !== $id ) {
			$found = false;

			foreach ( $items as $index => $item ) {
				if ( (string) $item['id'] !== $id ) {
					continue;
				}

				$items[ $index ]['content']    = $content;
				$items[ $index ]['updated_at'] = $now;
				$found                         = true;
				$saved                         = $items[ $index ];
				break;
			}

			if ( ! $found ) {
				return new \WP_Error(
					'asdevs_ai_memory_missing',
					__( 'That memory item was not found.', 'asdevs-ai-assistant' ),
					array( 'status' => 404 )
				);
			}
		} else {
			$saved = array(
				'id'         => $this->new_id(),
				'content'    => $content,
				'created_at' => $now,
				'updated_at' => $now,
			);

			array_unshift( $items, $saved );
		}

		$items = array_slice( $items, 0, self::MAX_ITEMS );
		$this->persist( $items );

		return $saved;
	}

	/**
	 * Delete a row by id.
	 *
	 * @param string $id Memory id.
	 *
	 * @return true|\WP_Error
	 */
	public function delete( string $id ) {
		$id    = sanitize_key( $id );
		$items = $this->all();
		$kept  = array();
		$found = false;

		foreach ( $items as $item ) {
			if ( (string) $item['id'] === $id ) {
				$found = true;
				continue;
			}

			$kept[] = $item;
		}

		if ( ! $found ) {
			return new \WP_Error(
				'asdevs_ai_memory_missing',
				__( 'That memory item was not found.', 'asdevs-ai-assistant' ),
				array( 'status' => 404 )
			);
		}

		$this->persist( $kept );

		return true;
	}

	/**
	 * Compact rows for the system prompt.
	 */
	public function for_prompt(): string {
		$items = $this->all();

		if ( array() === $items ) {
			return '(empty — nothing stored yet)';
		}

		$lines = array();

		foreach ( $items as $item ) {
			$lines[] = sprintf(
				'- [%s] %s',
				(string) $item['id'],
				(string) $item['content']
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * @param array<int, array<string, mixed>> $items Rows to store.
	 */
	private function persist( array $items ): void {
		update_option( self::OPTION, array_values( $items ), false );
	}

	/**
	 * @param mixed $row Raw option row.
	 *
	 * @return array<string, mixed>|null
	 */
	private function normalize( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$id      = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
		$content = isset( $row['content'] ) ? trim( wp_strip_all_tags( (string) $row['content'] ) ) : '';

		if ( '' === $id || '' === $content ) {
			return null;
		}

		$created = isset( $row['created_at'] ) ? (int) $row['created_at'] : time();
		$updated = isset( $row['updated_at'] ) ? (int) $row['updated_at'] : $created;

		return array(
			'id'         => $id,
			'content'    => $content,
			'created_at' => $created,
			'updated_at' => $updated,
		);
	}

	private function new_id(): string {
		return 'm' . strtolower( wp_generate_password( 10, false, false ) );
	}
}
