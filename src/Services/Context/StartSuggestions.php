<?php
/**
 * Opening suggestions, built from the real state of the site.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Context;

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

		foreach ( $this->from_plugin_updates( $snapshot ) as $suggestion ) {
			if ( count( $suggestions ) >= 4 ) {
				break;
			}

			$suggestions[] = $suggestion;
		}

		if ( $this->site_is_empty( $snapshot ) && count( $suggestions ) < 4 ) {
			$suggestions[] = array(
				'label'  => __( 'Create the first page of the site', 'asdevs-ai-assistant' ),
				'prompt' => __( 'Help me create the first page of this site.', 'asdevs-ai-assistant' ),
			);
		}

		foreach ( $this->from_inactive_plugins( $snapshot ) as $suggestion ) {
			if ( count( $suggestions ) >= 4 ) {
				break;
			}

			$suggestions[] = $suggestion;
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
	 * Suggestions from unfinished or upcoming content, highest-signal first.
	 *
	 * @param array<string, mixed> $snapshot Site snapshot.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function from_content( array $snapshot ): array {
		$types      = isset( $snapshot['content'] ) && is_array( $snapshot['content'] ) ? $snapshot['content'] : array();
		$candidates = array();

		foreach ( $types as $type ) {
			if ( ! is_array( $type ) ) {
				continue;
			}

			$label   = (string) ( $type['label'] ?? '' );
			$pending = (int) ( $type['pending'] ?? 0 );
			$draft   = (int) ( $type['draft'] ?? 0 );
			$future  = (int) ( $type['future'] ?? 0 );

			if ( '' === $label ) {
				continue;
			}

			if ( $pending > 0 ) {
				$candidates[] = array(
					'weight' => 300 + min( $pending, 50 ),
					'label'  => sprintf(
						/* translators: 1: number of items, 2: content type label, e.g. Posts. */
						_n(
							'Review %1$s pending item in %2$s',
							'Review %1$s pending items in %2$s',
							$pending,
							'asdevs-ai-assistant'
						),
						number_format_i18n( $pending ),
						$label
					),
					'prompt' => sprintf(
						/* translators: %s: content type label. */
						__( 'Show pending items in %s and summarize what needs approval.', 'asdevs-ai-assistant' ),
						$label
					),
				);
			}

			if ( $draft > 0 ) {
				$candidates[] = array(
					'weight' => 200 + min( $draft, 50 ),
					'label'  => sprintf(
						/* translators: 1: number of items, 2: content type label, e.g. Posts. */
						_n(
							'Continue %1$s draft in %2$s',
							'Continue %1$s drafts in %2$s',
							$draft,
							'asdevs-ai-assistant'
						),
						number_format_i18n( $draft ),
						$label
					),
					'prompt' => sprintf(
						/* translators: %s: content type label. */
						__( 'List drafts in %s and help me decide what to finish or publish next.', 'asdevs-ai-assistant' ),
						$label
					),
				);
			}

			if ( $future > 0 ) {
				$candidates[] = array(
					'weight' => 100 + min( $future, 30 ),
					'label'  => sprintf(
						/* translators: 1: number of items, 2: content type label, e.g. Posts. */
						_n(
							'Check %1$s scheduled item in %2$s',
							'Check %1$s scheduled items in %2$s',
							$future,
							'asdevs-ai-assistant'
						),
						number_format_i18n( $future ),
						$label
					),
					'prompt' => sprintf(
						/* translators: %s: content type label. */
						__( 'Show scheduled items in %s and confirm timing looks right.', 'asdevs-ai-assistant' ),
						$label
					),
				);
			}
		}

		usort(
			$candidates,
			static function ( array $left, array $right ): int {
				return (int) $right['weight'] <=> (int) $left['weight'];
			}
		);

		$suggestions = array();

		foreach ( $candidates as $candidate ) {
			$suggestions[] = array(
				'label'  => (string) $candidate['label'],
				'prompt' => (string) $candidate['prompt'],
			);

			if ( count( $suggestions ) >= 2 ) {
				break;
			}
		}

		return $suggestions;
	}

	/**
	 * High-priority maintenance: available plugin updates.
	 *
	 * @param array<string, mixed> $snapshot Site snapshot.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function from_plugin_updates( array $snapshot ): array {
		$plugins = isset( $snapshot['plugins'] ) && is_array( $snapshot['plugins'] ) ? $snapshot['plugins'] : array();
		$updates = (int) ( $plugins['updates'] ?? 0 );

		if ( $updates < 1 ) {
			return array();
		}

		return array(
			array(
				'label'  => sprintf(
					/* translators: %s: number of plugins. */
					_n(
						'Apply %s plugin update',
						'Apply %s plugin updates',
						$updates,
						'asdevs-ai-assistant'
					),
					number_format_i18n( $updates )
				),
				'prompt' => __( 'Which plugins have updates available, and which should we apply first?', 'asdevs-ai-assistant' ),
			),
		);
	}

	/**
	 * Lower-priority housekeeping for unused plugins.
	 *
	 * @param array<string, mixed> $snapshot Site snapshot.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function from_inactive_plugins( array $snapshot ): array {
		$plugins  = isset( $snapshot['plugins'] ) && is_array( $snapshot['plugins'] ) ? $snapshot['plugins'] : array();
		$inactive = (int) ( $plugins['inactive'] ?? 0 );

		if ( $inactive < 3 ) {
			return array();
		}

		return array(
			array(
				'label'  => sprintf(
					/* translators: %s: number of plugins. */
					_n(
						'Clean up %s inactive plugin',
						'Clean up %s inactive plugins',
						$inactive,
						'asdevs-ai-assistant'
					),
					number_format_i18n( $inactive )
				),
				'prompt' => __( 'Review inactive plugins and suggest which ones are safe to remove.', 'asdevs-ai-assistant' ),
			),
		);
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
		$site_name   = isset( $snapshot['site']['name'] ) ? trim( (string) $snapshot['site']['name'] ) : '';

		if ( ! empty( $can['edit_posts'] ) ) {
			$suggestions[] = array(
				'label'  => '' !== $site_name
					? sprintf(
						/* translators: %s: site name. */
						__( 'Write a new post for %s', 'asdevs-ai-assistant' ),
						$site_name
					)
					: __( 'Write a new post', 'asdevs-ai-assistant' ),
				'prompt' => __( 'Help me create a new post.', 'asdevs-ai-assistant' ),
			);
		}

		if ( ! empty( $can['list_users'] ) && null !== ( $snapshot['users'] ?? null ) ) {
			$users = (int) $snapshot['users'];

			$suggestions[] = array(
				'label'  => sprintf(
					/* translators: %s: number of users. */
					_n(
						'Review who has access (%s person)',
						'Review who has access (%s people)',
						$users,
						'asdevs-ai-assistant'
					),
					number_format_i18n( $users )
				),
				'prompt' => __( 'Show who has access to this site and what they can do.', 'asdevs-ai-assistant' ),
			);
		}

		if ( ! empty( $can['manage_options'] ) ) {
			$suggestions[] = array(
				'label'  => __( 'Update a site setting', 'asdevs-ai-assistant' ),
				'prompt' => __( 'Which site settings can you help me change right now?', 'asdevs-ai-assistant' ),
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
			if ( ! is_array( $type ) ) {
				continue;
			}

			$total = (int) ( $type['publish'] ?? 0 )
				+ (int) ( $type['draft'] ?? 0 )
				+ (int) ( $type['pending'] ?? 0 )
				+ (int) ( $type['future'] ?? 0 );

			if ( $total > 0 ) {
				return false;
			}
		}

		return ! empty( $types );
	}
}
