<?php
/**
 * Service provider contract.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each product area owns one provider and registers itself.
 *
 * Adding a capability means adding a provider, not editing existing code.
 */
abstract class ServiceProvider {

	/**
	 * The container.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Bind services into the container.
	 *
	 * Must not touch WordPress hooks or resolve other services.
	 */
	public function register(): void {}

	/**
	 * Wire the services to WordPress.
	 *
	 * Runs after every provider has been registered.
	 */
	public function boot(): void {}
}
