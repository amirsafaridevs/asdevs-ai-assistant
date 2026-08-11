<?php
/**
 * REST entry points.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Services\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Services\Ai\Settings;
use ASDevs\AIAssistant\Services\Ai\SystemPrompt;
use ASDevs\AIAssistant\Services\Ai\ToolCatalog;
use ASDevs\AIAssistant\Services\Context\SiteSnapshot;
use ASDevs\AIAssistant\Services\Context\StartSuggestions;
use ASDevs\AIAssistant\Services\Conversations\ConversationStore;
use ASDevs\AIAssistant\Core\Container;
use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Services\Discovery\CapabilityMap;
use ASDevs\AIAssistant\Services\Execution\ActionExecutor;
use ASDevs\AIAssistant\Services\Legal\Terms;
use ASDevs\AIAssistant\Services\Memory\MemoryStore;
use ASDevs\AIAssistant\Http\Controllers\ActionController;
use ASDevs\AIAssistant\Http\Controllers\BootstrapController;
use ASDevs\AIAssistant\Http\Controllers\CapabilityController;
use ASDevs\AIAssistant\Http\Controllers\ChatController;
use ASDevs\AIAssistant\Http\Controllers\ConversationController;
use ASDevs\AIAssistant\Http\Controllers\Controller;
use ASDevs\AIAssistant\Http\Controllers\MemoryController;
use ASDevs\AIAssistant\Http\Controllers\SettingsController;
use ASDevs\AIAssistant\Http\Controllers\SkillsController;
use ASDevs\AIAssistant\Http\Controllers\TermsController;
use ASDevs\AIAssistant\Services\Skills\SkillStore;

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
				$container->get( ProviderRegistry::class ),
				$container->get( Terms::class )
			)
		);

		$this->container->singleton(
			TermsController::class,
			static fn( Container $container ) => new TermsController( $container->get( Terms::class ) )
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
				$container->get( ToolCatalog::class ),
				$container->get( SkillStore::class )
			)
		);

		$this->container->singleton(
			SkillsController::class,
			static fn( Container $container ) => new SkillsController( $container->get( SkillStore::class ) )
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

		$this->container->singleton(
			MemoryController::class,
			static fn( Container $container ) => new MemoryController( $container->get( MemoryStore::class ) )
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
			TermsController::class,
			CapabilityController::class,
			ActionController::class,
			ChatController::class,
			ConversationController::class,
			SettingsController::class,
			MemoryController::class,
			SkillsController::class,
		);
	}
}
