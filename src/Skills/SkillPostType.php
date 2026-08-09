<?php
/**
 * Custom post type for reusable skill prompts.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Skills;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the skill post type (hidden from the admin menu UI).
 *
 * Skills are edited inside the assistant window; the CPT is the storage
 * layer so they survive in the database and can be exported with the site.
 */
final class SkillPostType {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'asdevs_ai_skill';

	/**
	 * Register with WordPress.
	 */
	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Assistant skills', 'asdevs-ai-assistant' ),
					'singular_name' => __( 'Assistant skill', 'asdevs-ai-assistant' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'excerpt', 'author' ),
				'delete_with_user'    => false,
			)
		);
	}
}
