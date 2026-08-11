<?php
/**
 * Skill CRUD endpoints.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Skills\SkillStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets administrators manage reusable skill prompts.
 */
final class SkillsController extends Controller {

	/**
	 * Skill store.
	 *
	 * @var SkillStore
	 */
	private SkillStore $skills;

	/**
	 * Constructor.
	 *
	 * @param SkillStore $skills Skill store.
	 */
	public function __construct( SkillStore $skills ) {
		$this->skills = $skills;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/skills',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'write' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
				'args'                => array(
					'q'     => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/load',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'load' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * List every skill.
	 */
	public function index(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'items' => $this->skills->all(),
			)
		);
	}

	/**
	 * Rank skills against what the person is trying to do.
	 *
	 * Answers the assistant's own `find_skills` tool: metadata only, so
	 * browsing the catalogue never costs as much as loading from it.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function search( WP_REST_Request $request ): WP_REST_Response {
		$query = trim( (string) $request->get_param( 'q' ) );
		$limit = (int) $request->get_param( 'limit' );
		$limit = $limit > 0 ? $limit : 5;

		$matches = $this->skills->search( $query, $limit );

		return new WP_REST_Response(
			array(
				'query'   => $query,
				'matches' => $matches,
				'total'   => count( $matches ),
			)
		);
	}

	/**
	 * Full prompts for the skills the assistant chose.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function load( WP_REST_Request $request ): WP_REST_Response {
		$raw = $request->get_param( 'slugs' );

		if ( is_string( $raw ) ) {
			$raw = array( $raw );
		}

		$slugs = array();

		foreach ( array_slice( is_array( $raw ) ? $raw : array(), 0, 10 ) as $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$slugs[] = (string) $value;
			}
		}

		return new WP_REST_Response( $this->skills->load( $slugs ) );
	}

	/**
	 * One skill by id.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$item = $this->skills->get( (int) $request->get_param( 'id' ) );

		if ( null === $item ) {
			return new WP_Error(
				'asdevs_ai_skill_missing',
				__( 'That skill was not found.', 'asdevs-ai-assistant' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( array( 'item' => $item ) );
	}

	/**
	 * Create a skill.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$result = $this->skills->write( $this->payload_from( $request ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'item' => $result,
			),
			201
		);
	}

	/**
	 * Update a skill.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$result = $this->skills->write( $this->payload_from( $request ), (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'item' => $result,
			)
		);
	}

	/**
	 * Delete one skill.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->skills->delete( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array<string, mixed>
	 */
	private function payload_from( WP_REST_Request $request ): array {
		return array(
			'title'       => (string) $request->get_param( 'title' ),
			'slug'        => (string) $request->get_param( 'slug' ),
			'prompt'      => (string) $request->get_param( 'prompt' ),
			'description' => (string) $request->get_param( 'description' ),
			'when_to_use' => (string) $request->get_param( 'when_to_use' ),
			'keywords'    => $request->get_param( 'keywords' ) ?? '',
		);
	}
}
