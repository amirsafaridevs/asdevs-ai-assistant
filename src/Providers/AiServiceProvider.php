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
use ASDevs\AIAssistant\Ai\Providers\AnthropicProvider;
use ASDevs\AIAssistant\Ai\Providers\OpenAiProvider;
use ASDevs\AIAssistant\Ai\Settings;
use ASDevs\AIAssistant\Ai\SystemPrompt;
use ASDevs\AIAssistant\Ai\ToolCatalog;
use ASDevs\AIAssistant\Context\SiteSnapshot;
use ASDevs\AIAssistant\Core\Container;
use ASDevs\AIAssistant\Core\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the AI layer.
 */
final class AiServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( ToolCatalog::class, static fn() => new ToolCatalog() );

		$this->container->singleton(
			SystemPrompt::class,
			static fn( Container $container ) => new SystemPrompt( $container->get( SiteSnapshot::class ) )
		);

		$this->container->singleton(
			ProviderRegistry::class,
			static function ( Container $container ) {
				$settings = $container->get( Settings::class );
				$registry = new ProviderRegistry( $settings );

				$registry->add( new AnthropicProvider( $settings ) );
				$registry->add( new OpenAiProvider( $settings ) );

				/**
				 * Filter the AI providers a site can choose from.
				 *
				 * A site can add its own service without touching this plugin.
				 *
				 * @param AiProvider[] $extra    Extra providers.
				 * @param Settings     $settings Stored settings.
				 */
				$extra = apply_filters( 'asdevs_ai_assistant_providers', array(), $settings );

				foreach ( (array) $extra as $provider ) {
					if ( $provider instanceof AiProvider ) {
						$registry->add( $provider );
					}
				}

				return $registry;
			}
		);
	}
}
