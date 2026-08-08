<?php
/**
 * AI services.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Ai\AiProvider;
use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Ai\Settings;
use ASDevs\AIAssistant\Ai\SystemPrompt;
use ASDevs\AIAssistant\Ai\ToolCatalog;
use ASDevs\AIAssistant\Context\SiteSnapshot;
use ASDevs\AIAssistant\Core\Container;
use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Memory\MemoryStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the AI layer on top of WordPress core connectors.
 */
final class AiServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( ToolCatalog::class, static fn() => new ToolCatalog() );

		$this->container->singleton(
			SystemPrompt::class,
			static fn( Container $container ) => new SystemPrompt(
				$container->get( SiteSnapshot::class ),
				$container->get( MemoryStore::class )
			)
		);

		$this->container->singleton(
			ProviderRegistry::class,
			static function ( Container $container ) {
				$settings = $container->get( Settings::class );
				$registry = new ProviderRegistry( $settings );

				/**
				 * Filter the AI providers a site can choose from.
				 *
				 * Prefer WordPress connectors discovered via Settings → Connectors.
				 * Extra providers can still be added for advanced sites.
				 *
				 * @param AiProvider[]     $extra    Extra providers.
				 * @param Settings         $settings Stored settings.
				 * @param ProviderRegistry $registry Registry being built.
				 */
				$extra = apply_filters( 'asdevs_ai_assistant_providers', array(), $settings, $registry );

				foreach ( (array) $extra as $provider ) {
					if ( $provider instanceof AiProvider ) {
						$registry->add( $provider );
					}
				}

				return $registry;
			}
		);
	}

	/**
	 * Wire WordPress AI Client defaults.
	 */
	public function boot(): void {
		add_filter(
			'wp_ai_client_default_request_timeout',
			static function ( $timeout ) {
				return max( (float) $timeout, 120.0 );
			}
		);
	}
}
