<?php
/**
 * Service container.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A minimal service container.
 *
 * No class builds another class directly; every dependency is resolved here.
 */
final class Container {

	/**
	 * Registered factories, keyed by service id.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = array();

	/**
	 * Resolved shared instances, keyed by service id.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Service ids currently being resolved, used to detect cycles.
	 *
	 * @var array<string, true>
	 */
	private array $resolving = array();

	/**
	 * Register a shared service factory.
	 *
	 * @param string   $id      Service id, normally a class-string.
	 * @param callable $factory Receives the container, returns the service.
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Register an already built instance.
	 *
	 * @param string $id       Service id.
	 * @param mixed  $instance The service.
	 */
	public function instance( string $id, $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Whether a service is known to the container.
	 *
	 * @param string $id Service id.
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] ) || isset( $this->factories[ $id ] );
	}

	/**
	 * Resolve a service.
	 *
	 * @param string $id Service id.
	 *
	 * @return mixed
	 * @throws \RuntimeException When the service is unknown or circular.
	 */
	public function get( string $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \RuntimeException(
				sprintf( 'Service "%s" is not registered in the container.', esc_html( $id ) )
			);
		}

		if ( isset( $this->resolving[ $id ] ) ) {
			throw new \RuntimeException(
				sprintf( 'Circular dependency while resolving "%s".', esc_html( $id ) )
			);
		}

		$this->resolving[ $id ] = true;

		try {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		} finally {
			unset( $this->resolving[ $id ] );
		}

		return $this->instances[ $id ];
	}
}
