<?php
/**
 * Conversation services.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Conversations\ConversationStore;
use ASDevs\AIAssistant\Core\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers conversation history.
 */
final class ConversationServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( ConversationStore::class, static fn() => new ConversationStore() );
	}

	/**
	 * Wire to WordPress.
	 */
	public function boot(): void {
		// When an account goes, its conversations go with it.
		add_action(
			'delete_user',
			function ( $user_id ): void {
				$this->container->get( ConversationStore::class )->clear( (int) $user_id );
			}
		);
	}
}
