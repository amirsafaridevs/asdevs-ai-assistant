<?php
/**
 * Persistent memory endpoints.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Memory\MemoryStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets the assistant read and change its own memory rows.
 */
final class MemoryController extends Controller {

	/**
	 * Memory store.
	 *
	 * @var MemoryStore
	 */
	private MemoryStore $memory;

	/**
	 * Constructor.
	 *
	 * @param MemoryStore $memory Memory store.
	 */
	public function __construct( MemoryStore $memory ) {
		$this->memory = $memory;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/memory',
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
			'/memory/(?P<id>[a-z0-9_-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * List every memory row.
	 */
	public function index(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'items' => $this->memory->all(),
			)
		);
	}

	/**
	 * Create or update a row.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$content = (string) $request->get_param( 'content' );
		$id_raw  = $request->get_param( 'id' );
		$id      = is_string( $id_raw ) && '' !== $id_raw ? sanitize_key( $id_raw ) : null;

		$result = $this->memory->write( $content, $id );

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
	 * Delete one row.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( WP_REST_Request $request ) {
		$id     = sanitize_key( (string) $request->get_param( 'id' ) );
		$result = $this->memory->delete( $id );

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
}
