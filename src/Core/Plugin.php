<?php
/**
 * Plugin lifecycle.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Core;

use ASDevs\AIAssistant\Providers\AdminServiceProvider;
use ASDevs\AIAssistant\Providers\AiServiceProvider;
use ASDevs\AIAssistant\Providers\AssetServiceProvider;
use ASDevs\AIAssistant\Providers\ContextServiceProvider;
use ASDevs\AIAssistant\Providers\DiscoveryServiceProvider;
use ASDevs\AIAssistant\Providers\ConversationServiceProvider;
use ASDevs\AIAssistant\Providers\LegalServiceProvider;
use ASDevs\AIAssistant\Providers\PolicyServiceProvider;
use ASDevs\AIAssistant\Providers\RestApiServiceProvider;
use ASDevs\AIAssistant\Providers\SettingsServiceProvider;
use ASDevs\AIAssistant\Providers\SkillsServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the container and runs the register/boot lifecycle.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * The container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Booted providers.
	 *
	 * @var ServiceProvider[]
	 */
	private array $providers = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->container->instance( Container::class, $this->container );
	}

	/**
	 * Get the plugin instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The service container.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Run the lifecycle: register every provider, then boot every provider.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		foreach ( $this->provider_classes() as $provider_class ) {
			$provider          = new $provider_class( $this->container );
			$this->providers[] = $provider;
			$provider->register();
		}

		foreach ( $this->providers as $provider ) {
			$provider->boot();
		}
	}

	/**
	 * The provider list.
	 *
	 * @return string[]
	 */
	private function provider_classes(): array {
		$providers = array(
			SettingsServiceProvider::class,
			PolicyServiceProvider::class,
			DiscoveryServiceProvider::class,
			ContextServiceProvider::class,
			ConversationServiceProvider::class,
			LegalServiceProvider::class,
			SkillsServiceProvider::class,
			AiServiceProvider::class,
			RestApiServiceProvider::class,
			AdminServiceProvider::class,
			AssetServiceProvider::class,
		);

		/**
		 * Filter the registered service providers.
		 *
		 * @param string[] $providers Fully qualified provider class names.
		 */
		$providers = apply_filters( 'asdevs_ai_assistant_service_providers', $providers );

		return array_values(
			array_filter(
				$providers,
				static fn( $class ) => is_string( $class ) && is_subclass_of( $class, ServiceProvider::class )
			)
		);
	}
}
