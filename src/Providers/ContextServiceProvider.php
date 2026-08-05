<?php
/**
 * Context services.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Context\PageContext;
use ASDevs\AIAssistant\Context\SiteSnapshot;
use ASDevs\AIAssistant\Context\StartSuggestions;
use ASDevs\AIAssistant\Core\Container;
use ASDevs\AIAssistant\Core\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers what the assistant knows about the site and the screen.
 */
final class ContextServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( SiteSnapshot::class, static fn() => new SiteSnapshot() );
		$this->container->singleton( PageContext::class, static fn() => new PageContext() );

		$this->container->singleton(
			StartSuggestions::class,
			static fn( Container $container ) => new StartSuggestions( $container->get( SiteSnapshot::class ) )
		);
	}
}
