<?php
/**
 * What the widget needs when it opens.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Services\Context\SiteSnapshot;
use ASDevs\AIAssistant\Services\Context\StartSuggestions;
use ASDevs\AIAssistant\Services\Conversations\ConversationStore;
use ASDevs\AIAssistant\Services\Legal\Terms;
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
	 * Active conversation store.
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
	 * Terms of use.
	 *
	 * @var Terms
	 */
	private Terms $terms;

	/**
	 * Constructor.
	 *
	 * @param SiteSnapshot      $snapshot      Site snapshot.
	 * @param StartSuggestions  $suggestions   Opening suggestions.
	 * @param ConversationStore $conversations Active conversation store.
	 * @param ProviderRegistry  $providers     Providers.
	 * @param Terms             $terms         Terms of use.
	 */
	public function __construct(
		SiteSnapshot $snapshot,
		StartSuggestions $suggestions,
		ConversationStore $conversations,
		ProviderRegistry $providers,
		Terms $terms
	) {
		$this->snapshot      = $snapshot;
		$this->suggestions   = $suggestions;
		$this->conversations = $conversations;
		$this->providers     = $providers;
		$this->terms         = $terms;
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
		$user_id = get_current_user_id();
		$terms   = $this->terms->for_user( $user_id );

		// Until the current terms version is accepted, do not expose chat or site data.
		if ( ! $terms['accepted'] ) {
			return new WP_REST_Response(
				array(
					'ready'         => false,
					'ready_detail'  => '',
					'can_configure' => false,
					'settings_url'  => '',
					'provider'      => '',
					'models'        => array(),
					'site'          => array(),
					'user'          => array(
						'display_name' => '',
						'roles'        => array(),
					),
					'suggestions'   => array(),
					'conversation'  => null,
					'terms'         => $terms,
				)
			);
		}

		$snapshot = $this->snapshot->get();
		$ready    = $this->providers->is_ready();
		$selected = $this->providers->selected();
		$models   = array();

		if ( null !== $selected ) {
			foreach ( $selected->models() as $id => $label ) {
				$models[] = array(
					'id'    => (string) $id,
					'label' => (string) $label,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'ready'         => $ready,
				'ready_detail'  => $ready ? '' : $this->providers->not_ready_detail(),
				'can_configure' => current_user_can( 'manage_options' ),
				'settings_url'  => admin_url( 'admin.php?page=' . ASDEVS_AI_ASSISTANT_SLUG ),
				'provider'      => null !== $selected ? $selected->id() : '',
				'models'        => $models,
				'site'          => $snapshot['site'],
				'user'          => $snapshot['user'],
				'suggestions'   => $this->suggestions->get(),
				'conversation'  => $this->conversations->get( $user_id ),
				'terms'         => $terms,
			)
		);
	}
}
