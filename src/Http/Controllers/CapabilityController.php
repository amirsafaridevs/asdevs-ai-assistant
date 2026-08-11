<?php
/**
 * Capability discovery endpoints.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Discovery\CapabilityMap;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes what this site can do, scoped to the signed-in person.
 */
final class CapabilityController extends Controller {

	/**
	 * Capability map.
	 *
	 * @var CapabilityMap
	 */
	private CapabilityMap $map;

	/**
	 * Constructor.
	 *
	 * @param CapabilityMap $map Capability map.
	 */
	public function __construct( CapabilityMap $map ) {
		$this->map = $map;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/capabilities',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_all' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/capabilities/describe',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'describe' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
				'args'                => array(
					'route' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn( $value ) => sanitize_text_field( (string) $value ),
					),
				),
			)
		);
	}

	/**
	 * The whole map.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function list_all( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->map->all( (bool) $request->get_param( 'refresh' ) ) );
	}

	/**
	 * One capability in detail.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function describe( WP_REST_Request $request ): WP_REST_Response {
		$described = $this->map->describe( (string) $request->get_param( 'route' ) );

		if ( null === $described ) {
			return new WP_REST_Response(
				array(
					'found'   => false,
					'message' => __( 'I could not find that capability on this site.', 'asdevs-ai-assistant' ),
				),
				200
			);
		}

		return new WP_REST_Response( $described );
	}
}
