<?php
/**
 * The endpoint that actually touches the site.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use ASDevs\AIAssistant\Execution\ActionExecutor;
use ASDevs\AIAssistant\Security\ActionRequest;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs one action, after the server has judged it.
 *
 * The browser decides what to propose; this endpoint decides what happens.
 * The two are separate on purpose: the conversation cannot talk its way past
 * a check that lives here (section 28.3).
 */
final class ActionController extends Controller {

	/**
	 * Action executor.
	 *
	 * @var ActionExecutor
	 */
	private ActionExecutor $executor;

	/**
	 * Constructor.
	 *
	 * @param ActionExecutor $executor Action executor.
	 */
	public function __construct( ActionExecutor $executor ) {
		$this->executor = $executor;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
				'args'                => array(
					'method'       => array(
						'type'              => 'string',
						'default'           => 'GET',
						'enum'              => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
						'sanitize_callback' => static fn( $value ) => strtoupper( sanitize_key( (string) $value ) ),
					),
					'route'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn( $value ) => sanitize_text_field( (string) $value ),
					),
					'confirmation' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn( $value ) => sanitize_text_field( (string) $value ),
					),
				),
			)
		);
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$action = new ActionRequest(
			(string) $request->get_param( 'method' ),
			(string) $request->get_param( 'route' ),
			$this->array_param( $request, 'params' )
		);

		$outcome = $this->executor->run( $action, (string) $request->get_param( 'confirmation' ) );

		return new WP_REST_Response( $outcome );
	}
}
