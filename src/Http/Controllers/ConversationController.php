<?php
/**
 * Active conversation endpoints.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Conversations\ConversationStore;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saves and restores the one active chat for the signed-in person.
 */
final class ConversationController extends Controller {

	/**
	 * Active conversation store.
	 *
	 * @var ConversationStore
	 */
	private ConversationStore $store;

	/**
	 * Constructor.
	 *
	 * @param ConversationStore $store Active conversation store.
	 */
	public function __construct( ConversationStore $store ) {
		$this->store = $store;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/conversation',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
					'args'                => array(
						'id'         => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => static fn( $value ) => sanitize_key( (string) $value ),
						),
						'title'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => static fn( $value ) => sanitize_text_field( (string) $value ),
						),
						'unfinished' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'clear' ),
					'permission_callback' => array( $this, 'check_terms_permission' ),
				),
			)
		);
	}

	/**
	 * Show the active conversation.
	 */
	public function show(): WP_REST_Response {
		$conversation = $this->store->get( get_current_user_id() );

		if ( null === $conversation ) {
			return new WP_REST_Response( array( 'found' => false ), 404 );
		}

		return new WP_REST_Response( $conversation );
	}

	/**
	 * Replace the active conversation.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$pending = $request->get_param( 'pending' );
		$choices = $request->get_param( 'choices' );

		$this->store->save(
			get_current_user_id(),
			(string) $request->get_param( 'id' ),
			(string) $request->get_param( 'title' ),
			$this->array_param( $request, 'messages' ),
			is_array( $pending ) ? $pending : null,
			is_array( $choices ) ? $choices : null,
			(bool) $request->get_param( 'unfinished' )
		);

		return new WP_REST_Response( array( 'saved' => true ) );
	}

	/**
	 * Delete the active conversation for this user.
	 */
	public function clear(): WP_REST_Response {
		$this->store->clear( get_current_user_id() );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}
}
