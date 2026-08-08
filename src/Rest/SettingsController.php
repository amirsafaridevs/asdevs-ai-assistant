<?php
/**
 * AI connector settings endpoints.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Ai\Providers\WordPressConnectorProvider;
use ASDevs\AIAssistant\Ai\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Choose which WordPress connector the assistant uses, and test it.
 *
 * API keys are never handled here — they live under Settings → Connectors.
 */
final class SettingsController extends Controller {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Providers.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Constructor.
	 *
	 * @param Settings         $settings  Settings.
	 * @param ProviderRegistry $providers Providers.
	 */
	public function __construct( Settings $settings, ProviderRegistry $providers ) {
		$this->settings  = $settings;
		$this->providers = $providers;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'provider' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => static fn( $value ) => sanitize_key( (string) $value ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'provider' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => static fn( $value ) => sanitize_key( (string) $value ),
					),
				),
			)
		);
	}

	/**
	 * Only a site administrator may see or change the connector preference.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		$allowed = $this->check_permission();

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'asdevs_ai_not_allowed',
				__( 'Only a site administrator can choose the AI connector.', 'asdevs-ai-assistant' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Current connector preference.
	 */
	public function show(): WP_REST_Response {
		$providers = array();

		foreach ( $this->providers->all() as $provider ) {
			$providers[] = array(
				'id'         => $provider->id(),
				'label'      => $provider->label(),
				'configured' => $provider->is_configured(),
			);
		}

		$selected = $this->providers->selected();
		$provider = $this->settings->provider();
		$ready    = $this->providers->is_ready();

		if ( '' === $provider && null !== $selected ) {
			$provider = $selected->id();
		}

		return new WP_REST_Response(
			array(
				'provider'       => $provider,
				'ready'          => $ready,
				'ready_detail'   => $ready ? '' : $this->providers->not_ready_detail(),
				'providers'      => $providers,
				'connectors_url' => admin_url( 'options-connectors.php' ),
			)
		);
	}

	/**
	 * Save the preferred connector.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$provider = (string) $request->get_param( 'provider' );

		if ( '' !== $provider && ! isset( $this->providers->all()[ $provider ] ) ) {
			return new WP_Error(
				'asdevs_ai_unknown_connector',
				__( 'That connector is not available. Install it under Settings → Connectors first.', 'asdevs-ai-assistant' ),
				array( 'status' => 400 )
			);
		}

		$this->settings->save( $provider );

		return $this->show();
	}

	/**
	 * Send a short test prompt through the preferred (or requested) connector.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function test( WP_REST_Request $request ): WP_REST_Response {
		$id = (string) $request->get_param( 'provider' );

		if ( '' === $id ) {
			$id = $this->settings->provider();
		}

		$provider = '' !== $id
			? ( $this->providers->all()[ $id ] ?? null )
			: $this->providers->selected();

		if ( ! $provider instanceof WordPressConnectorProvider ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => __( 'No WordPress AI connector is available to test. Open Settings → Connectors and install one.', 'asdevs-ai-assistant' ),
				),
				409
			);
		}

		return new WP_REST_Response( $provider->test_connection() );
	}
}
