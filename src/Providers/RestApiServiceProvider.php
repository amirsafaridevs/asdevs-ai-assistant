<?php
/**
 * REST entry points.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Ai\Settings;
use ASDevs\AIAssistant\Ai\SystemPrompt;
use ASDevs\AIAssistant\Ai\ToolCatalog;
use ASDevs\AIAssistant\Context\SiteSnapshot;
use ASDevs\AIAssistant\Context\StartSuggestions;
use ASDevs\AIAssistant\Conversations\ConversationStore;
use ASDevs\AIAssistant\Core\Container;
use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Discovery\CapabilityMap;
use ASDevs\AIAssistant\Execution\ActionExecutor;
use ASDevs\AIAssistant\Rest\ActionController;
use ASDevs\AIAssistant\Rest\BootstrapController;
use ASDevs\AIAssistant\Rest\CapabilityController;
use ASDevs\AIAssistant\Rest\ChatController;
use ASDevs\AIAssistant\Rest\ConversationController;
use ASDevs\AIAssistant\Rest\Controller;
use ASDevs\AIAssistant\Rest\SettingsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's own endpoints.
 */
final class RestApiServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton(
			BootstrapController::class,
			static fn( Container $container ) => new BootstrapController(
				$container->get( SiteSnapshot::class ),
				$container->get( StartSuggestions::class ),
				$container->get( ConversationStore::class ),
				$container->get( ProviderRegistry::class )
			)
		);

		$this->container->singleton(
			CapabilityController::class,
			static fn( Container $container ) => new CapabilityController( $container->get( CapabilityMap::class ) )
		);

		$this->container->singleton(
			ActionController::class,
			static fn( Container $container ) => new ActionController( $container->get( ActionExecutor::class ) )
		);

		$this->container->singleton(
			ChatController::class,
			static fn( Container $container ) => new ChatController(
				$container->get( ProviderRegistry::class ),
				$container->get( SystemPrompt::class ),
				$container->get( ToolCatalog::class )
			)
		);

		$this->container->singleton(
			ConversationController::class,
			static fn( Container $container ) => new ConversationController( $container->get( ConversationStore::class ) )
		);

		$this->container->singleton(
			SettingsController::class,
			static fn( Container $container ) => new SettingsController(
				$container->get( Settings::class ),
				$container->get( ProviderRegistry::class )
			)
		);
	}

	/**
	 * Wire to WordPress.
	 */
	public function boot(): void {
		add_action(
			'rest_api_init',
			function (): void {
				foreach ( $this->controllers() as $controller_class ) {
					$controller = $this->container->get( $controller_class );

					if ( $controller instanceof Controller ) {
						$controller->register_routes();
					}
				}
			}
		);
	}

	/**
	 * The controller list.
	 *
	 * @return string[]
	 */
	private function controllers(): array {
		return array(
			BootstrapController::class,
			CapabilityController::class,
			ActionController::class,
			ChatController::class,
			ConversationController::class,
			SettingsController::class,
		);
	}
}
