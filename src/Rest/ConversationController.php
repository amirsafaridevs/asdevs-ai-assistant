<?php
/**
 * Conversation history endpoints.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use ASDevs\AIAssistant\Conversations\ConversationStore;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets people look back at their conversations and delete them for good.
 */
final class ConversationController extends Controller {

	/**
	 * Conversation history.
	 *
	 * @var ConversationStore
	 */
	private ConversationStore $store;

	/**
	 * Constructor.
	 *
	 * @param ConversationStore $store Conversation history.
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
			'/conversations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'check_permission' ),
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
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-z0-9_\-]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * List conversations.
	 */
	public function index(): WP_REST_Response {
		return new WP_REST_Response( $this->store->index( get_current_user_id() ) );
	}

	/**
	 * Show one conversation.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$conversation = $this->store->get( get_current_user_id(), sanitize_key( (string) $request->get_param( 'id' ) ) );

		if ( null === $conversation ) {
			return new WP_REST_Response( array( 'found' => false ), 404 );
		}

		return new WP_REST_Response( $conversation );
	}

	/**
	 * Create or update a conversation.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$this->store->save(
			get_current_user_id(),
			(string) $request->get_param( 'id' ),
			(string) $request->get_param( 'title' ),
			$this->array_param( $request, 'messages' ),
			(bool) $request->get_param( 'unfinished' )
		);

		return new WP_REST_Response( array( 'saved' => true ) );
	}

	/**
	 * Delete one conversation.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$this->store->delete( get_current_user_id(), sanitize_key( (string) $request->get_param( 'id' ) ) );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Delete everything for this user.
	 */
	public function clear(): WP_REST_Response {
		$this->store->clear( get_current_user_id() );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}
}
