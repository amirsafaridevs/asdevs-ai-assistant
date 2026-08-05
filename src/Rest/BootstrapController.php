<?php
/**
 * What the widget needs when it opens.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Context\SiteSnapshot;
use ASDevs\AIAssistant\Context\StartSuggestions;
use ASDevs\AIAssistant\Conversations\ConversationStore;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the opening state of the conversation window.
 */
final class BootstrapController extends Controller {

	/**
	 * Site snapshot.
	 *
	 * @var SiteSnapshot
	 */
	private SiteSnapshot $snapshot;

	/**
	 * Opening suggestions.
	 *
	 * @var StartSuggestions
	 */
	private StartSuggestions $suggestions;

	/**
	 * Conversation history.
	 *
	 * @var ConversationStore
	 */
	private ConversationStore $conversations;

	/**
	 * Providers.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Constructor.
	 *
	 * @param SiteSnapshot      $snapshot      Site snapshot.
	 * @param StartSuggestions  $suggestions   Opening suggestions.
	 * @param ConversationStore $conversations Conversation history.
	 * @param ProviderRegistry  $providers     Providers.
	 */
	public function __construct(
		SiteSnapshot $snapshot,
		StartSuggestions $suggestions,
		ConversationStore $conversations,
		ProviderRegistry $providers
	) {
		$this->snapshot      = $snapshot;
		$this->suggestions   = $suggestions;
		$this->conversations = $conversations;
		$this->providers     = $providers;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Handle the request.
	 */
	public function handle(): WP_REST_Response {
		$snapshot = $this->snapshot->get();

		return new WP_REST_Response(
			array(
				'ready'         => $this->providers->is_ready(),
				'can_configure' => current_user_can( 'manage_options' ),
				'settings_url'  => admin_url( 'options-general.php?page=' . ASDEVS_AI_ASSISTANT_SLUG ),
				'site'          => $snapshot['site'],
				'user'          => $snapshot['user'],
				'suggestions'   => $this->suggestions->get(),
				'conversations' => $this->conversations->index( get_current_user_id() ),
			)
		);
	}
}
