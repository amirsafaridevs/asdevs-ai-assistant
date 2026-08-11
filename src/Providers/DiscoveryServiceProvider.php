<?php
/**
 * Capability discovery services.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Services\Discovery\CapabilityMap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers discovery and keeps it honest when the site changes.
 */
final class DiscoveryServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( CapabilityMap::class, static fn() => new CapabilityMap() );
	}

	/**
	 * Wire to WordPress.
	 */
	public function boot(): void {
		$flush = function (): void {
			$this->container->get( CapabilityMap::class )->flush();
		};

		// A plugin installed today has to be usable today (section 13.2).
		foreach ( array( 'activated_plugin', 'deactivated_plugin', 'switch_theme', 'upgrader_process_complete' ) as $hook ) {
			add_action( $hook, $flush, 10, 0 );
		}
	}
}
