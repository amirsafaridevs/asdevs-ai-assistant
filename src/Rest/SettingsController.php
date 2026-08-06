<?php
/**
 * AI service settings endpoints.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Ai\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one setup step there is.
 *
 * The stored key is never sent back to the browser — responses say only
 * whether a key is present.
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
						'model'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => static fn( $value ) => sanitize_text_field( (string) $value ),
						),
						'key'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => static fn( $value ) => trim( sanitize_text_field( (string) $value ) ),
						),
						'thinking' => array(
							'type'    => 'boolean',
							'default' => null,
						),
					),
				),
			)
		);
	}

	/**
	 * Only a site administrator may see or change the service settings.
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
				__( 'Only a site administrator can set up the AI service.', 'asdevs-ai-assistant' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Current settings, without the key.
	 */
	public function show(): WP_REST_Response {
		$providers = array();

		foreach ( $this->providers->all() as $provider ) {
			$providers[] = array(
				'id'               => $provider->id(),
				'label'            => $provider->label(),
				'models'           => $provider->models(),
				'default_model'    => $provider->default_model(),
				'reasoning_models' => $provider->reasoning_models(),
				'configured'       => $provider->is_configured(),
			);
		}

		return new WP_REST_Response(
			array(
				'provider'  => $this->settings->provider(),
				'model'     => $this->settings->model(),
				'thinking'  => $this->settings->thinking(),
				'has_key'   => '' !== $this->settings->key_for( $this->settings->provider() ),
				'ready'     => $this->providers->is_ready(),
				'providers' => $providers,
			)
		);
	}

	/**
	 * Save settings.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$thinking = $request->get_param( 'thinking' );

		$this->settings->save(
			(string) $request->get_param( 'provider' ),
			(string) $request->get_param( 'model' ),
			(string) $request->get_param( 'key' ),
			null === $thinking ? null : (bool) $thinking
		);

		return $this->show();
	}
}
