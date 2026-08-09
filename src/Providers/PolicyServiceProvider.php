<?php
/**
 * Boundary services.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Core\Container;
use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Execution\ActionExecutor;
use ASDevs\AIAssistant\Security\BulkGuard;
use ASDevs\AIAssistant\Security\ConfirmationTokens;
use ASDevs\AIAssistant\Security\NeverRules;
use ASDevs\AIAssistant\Security\RiskPolicy;
use ASDevs\AIAssistant\Security\RouteInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers everything that decides whether an action may run.
 */
final class PolicyServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( RouteInspector::class, static fn() => new RouteInspector() );
		$this->container->singleton( BulkGuard::class, static fn() => new BulkGuard() );
		$this->container->singleton( ConfirmationTokens::class, static fn() => new ConfirmationTokens() );

		$this->container->singleton(
			NeverRules::class,
			static fn( Container $container ) => new NeverRules( $container->get( RouteInspector::class ) )
		);

		$this->container->singleton(
			RiskPolicy::class,
			static fn( Container $container ) => new RiskPolicy(
				$container->get( RouteInspector::class ),
				$container->get( NeverRules::class ),
				$container->get( BulkGuard::class )
			)
		);

		$this->container->singleton(
			ActionExecutor::class,
			static fn( Container $container ) => new ActionExecutor(
				$container->get( RiskPolicy::class ),
				$container->get( ConfirmationTokens::class ),
				$container->get( BulkGuard::class )
			)
		);
	}
}
