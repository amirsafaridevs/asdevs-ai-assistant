<?php
/**
 * Skill prompt storage.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Skills;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for assistant skills backed by the skill CPT.
 */
final class SkillStore {

	/**
	 * Hard cap on stored skills.
	 */
	private const MAX_ITEMS = 100;

	/**
	 * Hard cap on a single skill prompt.
	 */
	private const MAX_PROMPT = 8000;

	/**
	 * Hard cap on the short description.
	 */
	private const MAX_DESCRIPTION = 400;

	/**
	 * Hard cap on the "when to use this" line the model reads.
	 */
	private const MAX_WHEN = 600;

	/**
	 * Hard cap on the keyword list.
	 */
	private const MAX_KEYWORDS = 400;

	/**
	 * How many skills one turn may load at once.
	 */
	private const MAX_LOADED = 5;

	/**
	 * Total characters of skill prompt one turn may pull into context.
	 */
	private const MAX_LOADED_CHARS = 24000;

	/**
	 * Meta key: when the model should reach for this skill.
	 */
	private const META_WHEN = '_asdevs_ai_when_to_use';

	/**
	 * Meta key: extra search terms and synonyms.
	 */
	private const META_KEYWORDS = '_asdevs_ai_keywords';

	/**
	 * Ranker used to answer the assistant's own skill searches.
	 *
	 * @var SkillMatcher
	 */
	private SkillMatcher $matcher;

	/**
	 * Skills for the life of this request, or null before the first read.
	 *
	 * One turn can search the catalogue and then load several slugs out of it;
	 * that is one list, not four queries.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $cache = null;

	/**
	 * Constructor.
	 *
	 * @param SkillMatcher|null $matcher Ranker.
	 */
	public function __construct( ?SkillMatcher $matcher = null ) {
		$this->matcher = $matcher ?? new SkillMatcher();
	}

	/**
	 * Every published skill, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$posts = get_posts(
			array(
				'post_type'              => SkillPostType::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_ITEMS,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$items = array();

		foreach ( $posts as $post ) {
			$item = $this->to_array( $post );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		/**
		 * Filter the skills the assistant can see.
		 *
		 * The seam other plugins will register their own skills through. Rows
		 * must match the shape returned here: id, title, slug, prompt,
		 * description, when_to_use, keywords.
		 *
		 * @param array<int, array<string, mixed>> $items Stored skills.
		 */
		$items = (array) apply_filters( 'asdevs_ai_assistant_skills', $items );

		$this->cache = array_values( array_filter( $items, 'is_array' ) );

		return $this->cache;
	}

	/**
	 * Skills that match a natural-language query, ranked, without prompt bodies.
	 *
	 * This is what the assistant's own search tool answers with: enough for the
	 * model to choose, never so much that browsing costs as much as loading.
	 *
	 * @param string $query What the person is trying to do.
	 * @param int    $limit How many to return.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function search( string $query, int $limit = 5 ): array {
		$matches = $this->matcher->rank( $this->all(), $query, $limit );
		$rows    = array();

		foreach ( $matches as $match ) {
			$rows[] = array(
				'slug'        => (string) $match['slug'],
				'title'       => (string) $match['title'],
				'description' => (string) ( $match['description'] ?? '' ),
				'when_to_use' => (string) ( $match['when_to_use'] ?? '' ),
				'score'       => (float) ( $match['score'] ?? 0 ),
				'matched_on'  => array_values( (array) ( $match['matched_on'] ?? array() ) ),
			);
		}

		return $rows;
	}

	/**
	 * Full prompts for the skills the assistant chose to load.
	 *
	 * Capped in both count and characters: a model that asks for everything
	 * gets the first few, and is told plainly that the rest were left out.
	 *
	 * @param array<int, string> $slugs Skill slugs the model asked for.
	 *
	 * @return array{loaded: array<int, array<string, mixed>>, missing: array<int, string>, skipped: array<int, string>}
	 */
	public function load( array $slugs ): array {
		$loaded  = array();
		$missing = array();
		$skipped = array();
		$seen    = array();
		$budget  = self::MAX_LOADED_CHARS;

		foreach ( $slugs as $raw ) {
			$slug = $this->normalize_slug( (string) $raw );

			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$skill         = $this->find( $slug );

			if ( null === $skill ) {
				$missing[] = $slug;
				continue;
			}

			$prompt = (string) $skill['prompt'];

			if ( count( $loaded ) >= self::MAX_LOADED || strlen( $prompt ) > $budget ) {
				$skipped[] = $slug;
				continue;
			}

			$budget -= strlen( $prompt );

			$loaded[] = array(
				'slug'        => (string) $skill['slug'],
				'title'       => (string) $skill['title'],
				'description' => (string) ( $skill['description'] ?? '' ),
				'prompt'      => $prompt,
			);
		}

		return array(
			'loaded'  => $loaded,
			'missing' => $missing,
			'skipped' => $skipped,
		);
	}

	/**
	 * One skill by slug, including any registered by other plugins.
	 *
	 * @param string $slug Skill slug.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( string $slug ): ?array {
		$slug = $this->normalize_slug( $slug );

		if ( '' === $slug ) {
			return null;
		}

		foreach ( $this->all() as $skill ) {
			if ( (string) ( $skill['slug'] ?? '' ) === $slug ) {
				return $skill;
			}
		}

		return null;
	}

	/**
	 * One skill by numeric id.
	 *
	 * @param int $id Post id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || SkillPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		if ( 'publish' !== $post->post_status ) {
			return null;
		}

		return $this->to_array( $post );
	}

	/**
	 * One skill by slug (post_name).
	 *
	 * @param string $slug Skill slug.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_by_slug( string $slug ): ?array {
		$slug = $this->normalize_slug( $slug );

		if ( '' === $slug ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'              => SkillPostType::POST_TYPE,
				'post_status'            => 'publish',
				'name'                   => $slug,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( array() === $posts ) {
			return null;
		}

		return $this->to_array( $posts[0] );
	}

	/**
	 * Create or update a skill.
	 *
	 * @param array<string, mixed> $data Skill fields.
	 * @param int|null             $id   Existing id to update, or null to create.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function write( array $data, ?int $id = null ) {
		$title       = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '';
		$slug_raw    = isset( $data['slug'] ) ? (string) $data['slug'] : '';
		$prompt      = isset( $data['prompt'] ) ? trim( (string) $data['prompt'] ) : '';
		$description = isset( $data['description'] ) ? sanitize_text_field( (string) $data['description'] ) : '';
		$when        = isset( $data['when_to_use'] ) ? sanitize_textarea_field( (string) $data['when_to_use'] ) : '';
		$keywords    = $this->normalize_keywords( $data['keywords'] ?? '' );

		if ( '' === $title ) {
			return new \WP_Error(
				'asdevs_ai_skill_title',
				__( 'Give this skill a name.', 'asdevs-ai-assistant' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $prompt ) {
			return new \WP_Error(
				'asdevs_ai_skill_prompt',
				__( 'A skill needs a prompt.', 'asdevs-ai-assistant' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $prompt ) > self::MAX_PROMPT ) {
			$prompt = substr( $prompt, 0, self::MAX_PROMPT );
		}

		if ( strlen( $description ) > self::MAX_DESCRIPTION ) {
			$description = substr( $description, 0, self::MAX_DESCRIPTION );
		}

		if ( strlen( $when ) > self::MAX_WHEN ) {
			$when = substr( $when, 0, self::MAX_WHEN );
		}

		if ( strlen( $keywords ) > self::MAX_KEYWORDS ) {
			$keywords = substr( $keywords, 0, self::MAX_KEYWORDS );
		}

		$slug = $this->normalize_slug( '' !== $slug_raw ? $slug_raw : $title );

		if ( '' === $slug ) {
			return new \WP_Error(
				'asdevs_ai_skill_slug',
				__( 'Could not make a usable slug from that name.', 'asdevs-ai-assistant' ),
				array( 'status' => 400 )
			);
		}

		$slug_check = $this->ensure_unique_slug( $slug, $id );

		if ( is_wp_error( $slug_check ) ) {
			return $slug_check;
		}

		$slug = $slug_check;

		if ( null !== $id && $id > 0 ) {
			$existing = get_post( $id );

			if ( ! $existing instanceof \WP_Post || SkillPostType::POST_TYPE !== $existing->post_type ) {
				return new \WP_Error(
					'asdevs_ai_skill_missing',
					__( 'That skill was not found.', 'asdevs-ai-assistant' ),
					array( 'status' => 404 )
				);
			}

			$result = wp_update_post(
				array(
					'ID'           => $id,
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $prompt,
					'post_excerpt' => $description,
					'post_status'  => 'publish',
				),
				true
			);
		} else {
			$count = 0;
			$counts = wp_count_posts( SkillPostType::POST_TYPE );

			if ( is_object( $counts ) && isset( $counts->publish ) ) {
				$count = (int) $counts->publish;
			}

			if ( $count >= self::MAX_ITEMS ) {
				return new \WP_Error(
					'asdevs_ai_skill_limit',
					__( 'This site already has the maximum number of skills.', 'asdevs-ai-assistant' ),
					array( 'status' => 400 )
				);
			}

			$result = wp_insert_post(
				array(
					'post_type'    => SkillPostType::POST_TYPE,
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $prompt,
					'post_excerpt' => $description,
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
				),
				true
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( (int) $result, self::META_WHEN, $when );
		update_post_meta( (int) $result, self::META_KEYWORDS, $keywords );

		$this->cache = null;

		$saved = $this->get( (int) $result );

		if ( null === $saved ) {
			return new \WP_Error(
				'asdevs_ai_skill_save',
				__( 'The skill could not be saved.', 'asdevs-ai-assistant' ),
				array( 'status' => 500 )
			);
		}

		return $saved;
	}

	/**
	 * Delete a skill by id.
	 *
	 * @param int $id Post id.
	 *
	 * @return true|\WP_Error
	 */
	public function delete( int $id ) {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || SkillPostType::POST_TYPE !== $post->post_type ) {
			return new \WP_Error(
				'asdevs_ai_skill_missing',
				__( 'That skill was not found.', 'asdevs-ai-assistant' ),
				array( 'status' => 404 )
			);
		}

		$deleted     = wp_delete_post( $id, true );
		$this->cache = null;

		if ( ! $deleted instanceof \WP_Post ) {
			return new \WP_Error(
				'asdevs_ai_skill_delete',
				__( 'The skill could not be deleted.', 'asdevs-ai-assistant' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Resolve skill prompts for the system prompt from a list of slugs.
	 *
	 * @param array<int, string> $slugs Skill slugs.
	 *
	 * @return array<int, array{title: string, slug: string, prompt: string}>
	 */
	public function resolve_for_prompt( array $slugs ): array {
		$resolved = array();
		$seen     = array();

		foreach ( $slugs as $slug ) {
			$slug = $this->normalize_slug( (string) $slug );

			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$skill         = $this->find( $slug );

			if ( null === $skill ) {
				continue;
			}

			$resolved[] = array(
				'title'  => (string) $skill['title'],
				'slug'   => (string) $skill['slug'],
				'prompt' => (string) $skill['prompt'],
			);
		}

		return $resolved;
	}

	/**
	 * Compact listing for the system prompt catalogue.
	 *
	 * Names and trigger lines only — never prompt bodies. This is the index the
	 * model reads on every turn to decide whether searching is worth a call.
	 */
	public function catalogue_for_prompt(): string {
		$items = $this->all();

		if ( array() === $items ) {
			return '(none defined yet)';
		}

		$lines = array();

		foreach ( $items as $item ) {
			$hint = trim( (string) ( $item['when_to_use'] ?? '' ) );

			if ( '' === $hint ) {
				$hint = trim( (string) ( $item['description'] ?? '' ) );
			}

			$line = sprintf( '- /%s — %s', (string) $item['slug'], (string) $item['title'] );

			if ( '' !== $hint ) {
				$line .= ': ' . $hint;
			}

			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Wipe every skill post (uninstall).
	 */
	public static function delete_all(): void {
		$ids = get_posts(
			array(
				'post_type'              => SkillPostType::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * @param \WP_Post $post Skill post.
	 *
	 * @return array<string, mixed>|null
	 */
	private function to_array( \WP_Post $post ): ?array {
		$slug  = $this->normalize_slug( (string) $post->post_name );
		$title = trim( (string) $post->post_title );
		// Skill prompts are authored by admins; keep line breaks, strip tags.
		$prompt = trim( wp_strip_all_tags( (string) $post->post_content ) );

		if ( '' === $slug || '' === $title || '' === $prompt ) {
			return null;
		}

		return array(
			'id'          => (int) $post->ID,
			'title'       => $title,
			'slug'        => $slug,
			'prompt'      => $prompt,
			'description' => trim( wp_strip_all_tags( (string) $post->post_excerpt ) ),
			'when_to_use' => trim( (string) get_post_meta( $post->ID, self::META_WHEN, true ) ),
			'keywords'    => trim( (string) get_post_meta( $post->ID, self::META_KEYWORDS, true ) ),
			'created_at'  => strtotime( (string) $post->post_date_gmt ) ?: time(),
			'updated_at'  => strtotime( (string) $post->post_modified_gmt ) ?: time(),
		);
	}

	/**
	 * Flatten a keyword list from either a comma string or an array.
	 *
	 * @param mixed $value Raw keywords.
	 */
	private function normalize_keywords( $value ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}

		$parts = preg_split( '/[,\n]+/u', (string) $value );

		if ( ! is_array( $parts ) ) {
			return '';
		}

		$terms = array();

		foreach ( $parts as $part ) {
			$term = sanitize_text_field( trim( (string) $part ) );

			if ( '' !== $term ) {
				$terms[] = $term;
			}
		}

		return implode( ', ', array_slice( $terms, 0, 30 ) );
	}

	/**
	 * @param string $value Raw slug or title.
	 */
	private function normalize_slug( string $value ): string {
		$slug = sanitize_title( $value );
		$slug = preg_replace( '/[^a-z0-9\-]+/', '', strtolower( (string) $slug ) ) ?? '';
		$slug = trim( $slug, '-' );

		if ( strlen( $slug ) > 64 ) {
			$slug = substr( $slug, 0, 64 );
			$slug = rtrim( $slug, '-' );
		}

		return $slug;
	}

	/**
	 * Ensure the slug is unique among skills (and optionally reserve the current id).
	 *
	 * @param string   $slug Desired slug.
	 * @param int|null $id   Updating skill id, if any.
	 *
	 * @return string|\WP_Error
	 */
	private function ensure_unique_slug( string $slug, ?int $id ) {
		$existing = $this->get_by_slug( $slug );

		if ( null === $existing ) {
			return $slug;
		}

		if ( null !== $id && (int) $existing['id'] === $id ) {
			return $slug;
		}

		return new \WP_Error(
			'asdevs_ai_skill_slug_taken',
			__( 'Another skill already uses that slug.', 'asdevs-ai-assistant' ),
			array( 'status' => 409 )
		);
	}
}
