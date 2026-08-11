<?php
/**
 * Terms of use acceptance.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use ASDevs\AIAssistant\Legal\Terms;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets an administrator accept the current terms version.
 */
final class TermsController extends Controller {

	/**
	 * Terms service.
	 *
	 * @var Terms
	 */
	private Terms $terms;

	/**
	 * Constructor.
	 *
	 * @param Terms $terms Terms service.
	 */
	public function __construct( Terms $terms ) {
		$this->terms = $terms;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/terms/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'accept' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'version' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn( $value ) => sanitize_text_field( (string) $value ),
					),
				),
			)
		);
	}

	/**
	 * Persist acceptance of the version the person scrolled through.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept( WP_REST_Request $request ) {
		$version = (string) $request->get_param( 'version' );
		$user_id = get_current_user_id();

		if ( ! $this->terms->accept( $user_id, $version ) ) {
			return new WP_Error(
				'asdevs_ai_terms_stale',
				__( 'These terms have been updated. Reload the page to review the latest version.', 'asdevs-ai-assistant' ),
				array( 'status' => 409 )
			);
		}

		return new WP_REST_Response(
			array(
				'accepted' => true,
				'version'  => $this->terms->version(),
			)
		);
	}
}
