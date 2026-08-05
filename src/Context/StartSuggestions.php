<?php
/**
 * Opening suggestions, built from the real state of the site.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The first thing a user sees tells them what this product is.
 *
 * Section 7.3: a generic list makes it look like a chatbot; a list that
 * matches their site tells them, in the first second, that the assistant
 * knows their site.
 */
final class StartSuggestions {

	/**
	 * Site snapshot.
	 *
	 * @var SiteSnapshot
	 */
	private SiteSnapshot $snapshot;

	/**
	 * Constructor.
	 *
	 * @param SiteSnapshot $snapshot Site snapshot.
	 */
	public function __construct( SiteSnapshot $snapshot ) {
		$this->snapshot = $snapshot;
	}

	/**
	 * Up to four suggestions for the current user and site.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get(): array {
		$snapshot    = $this->snapshot->get();
		$suggestions = array();

		foreach ( $this->from_content( $snapshot ) as $suggestion ) {
			$suggestions[] = $suggestion;
		}

		foreach ( $this->from_plugins( $snapshot ) as $suggestion ) {
			$suggestions[] = $suggestion;
		}

		if ( $this->site_is_empty( $snapshot ) ) {
			$suggestions[] = array(
				'label'  => __( 'Create the first page of the site', 'asdevs-ai-assistant' ),
				'prompt' => __( 'Create the first page of the site', 'asdevs-ai-assistant' ),
			);
		}

		foreach ( $this->baseline( $snapshot ) as $suggestion ) {
			if ( count( $suggestions ) >= 4 ) {
				break;
			}

			$suggestions[] = $suggestion;
		}

		return array_slice( $suggestions, 0, 4 );
	}

	/**
	 * Suggestions that come from unfinished content.
	 *
	 * @param array<string, mixed> $snapshot Site snapshot.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function from_content( array $snapshot ): array {
		$suggestions = array();
		$types       = isset( $snapshot['content'] ) && is_array( $snapshot['content'] ) ? $snapshot['content'] : array();

		foreach ( $types as $type ) {
			$waiting = (int) $type['draft'] + (int) $type['pending'];

			if ( $waiting > 0 ) {
				$suggestions[] = array(
					'label'  => sprintf(
						/* translators: 1: number of items, 2: content type label, e.g. Posts. */
						__( 'Show the %1$s waiting in %2$s', 'asdevs-ai-assistant' ),
						number_format_i18n( $waiting ),
						$type['label']
					),
					'prompt' => sprintf(
						/* translators: %s: content type label. */
						__( 'Show the drafts waiting in %s', 'asdevs-ai-assistant' ),
						$type['label']
					),
				);
			}

			if ( count( $suggestions ) >= 2 ) {
				break;
			}
		}

		return $suggestions;
	}

	/**
	 * Suggestions that come from the plugin situation.
	 *
	 * @param array<string, mixed> $snapshot Site snapshot.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function from_plugins( array $snapshot ): array {
		$plugins = isset( $snapshot['plugins'] ) && is_array( $snapshot['plugins'] ) ? $snapshot['plugins'] : array();

		if ( empty( $plugins ) ) {
			return array();
		}

		$suggestions = array();

		if ( ( $plugins['updates'] ?? 0 ) > 0 ) {
			$suggestions[] = array(
				'label'  => sprintf(
					/* translators: %s: number of plugins. */
					__( 'Review the %s plugins with updates', 'asdevs-ai-assistant' ),
					number_format_i18n( (int) $plugins['updates'] )
				),
				'prompt' => __( 'Which plugins have updates?', 'asdevs-ai-assistant' ),
			);
		}

		if ( ( $plugins['inactive'] ?? 0 ) >= 3 ) {
			$suggestions[] = array(
				'label'  => sprintf(
					/* translators: %s: number of plugins. */
					__( 'Check the %s inactive plugins', 'asdevs-ai-assistant' ),
					number_format_i18n( (int) $plugins['inactive'] )
				),
				'prompt' => __( 'Check the inactive plugins', 'asdevs-ai-assistant' ),
			);
		}

		return $suggestions;
	}

	/**
	 * The fallback set, used when the site has nothing notable going on.
	 *
	 * @param array<string, mixed> $snapshot Site snapshot.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function baseline( array $snapshot ): array {
		$can         = isset( $snapshot['user']['can'] ) && is_array( $snapshot['user']['can'] ) ? $snapshot['user']['can'] : array();
		$suggestions = array();

		if ( ! empty( $can['edit_posts'] ) ) {
			$suggestions[] = array(
				'label'  => __( 'Write a new post', 'asdevs-ai-assistant' ),
				'prompt' => __( 'Create a new post', 'asdevs-ai-assistant' ),
			);
		}

		if ( ! empty( $can['list_users'] ) && null !== ( $snapshot['users'] ?? null ) ) {
			$suggestions[] = array(
				'label'  => sprintf(
					/* translators: %s: number of users. */
					__( 'Show the %s people with access', 'asdevs-ai-assistant' ),
					number_format_i18n( (int) $snapshot['users'] )
				),
				'prompt' => __( 'Show the site users', 'asdevs-ai-assistant' ),
			);
		}

		if ( ! empty( $can['manage_options'] ) ) {
			$suggestions[] = array(
				'label'  => __( 'Change a site setting', 'asdevs-ai-assistant' ),
				'prompt' => __( 'Which site settings can you change?', 'asdevs-ai-assistant' ),
			);
		}

		return $suggestions;
	}

	/**
	 * Whether the site has no content yet.
	 *
	 * @param array<string, mixed> $snapshot Site snapshot.
	 */
	private function site_is_empty( array $snapshot ): bool {
		$types = isset( $snapshot['content'] ) && is_array( $snapshot['content'] ) ? $snapshot['content'] : array();

		foreach ( $types as $type ) {
			$total = (int) $type['publish'] + (int) $type['draft'] + (int) $type['pending'] + (int) $type['future'];

			if ( $total > 0 ) {
				return false;
			}
		}

		return ! empty( $types );
	}
}
