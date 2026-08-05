<?php
/**
 * Dynamic discovery of what this site can do.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Discovery;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the site's own REST map and turns it into capabilities.
 *
 * There is no hard-coded list of supported features anywhere in this plugin.
 * A plugin installed yesterday that exposes routes is discovered today, and a
 * plugin nobody here has ever seen works the same as a core one.
 */
final class CapabilityMap {

	/**
	 * Cache group key prefix.
	 */
	private const TRANSIENT_PREFIX = 'asdevs_ai_capmap_';

	/**
	 * How long a built map is cached, in seconds.
	 */
	private const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Routes that describe the API itself rather than a capability.
	 *
	 * @var string[]
	 */
	private const STRUCTURAL_ROUTES = array( '/', '/batch/v1', '/oembed/1.0/embed', '/oembed/1.0/proxy' );

	/**
	 * In-request memo of the built map.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $memo = null;

	/**
	 * The capability map for the current user.
	 *
	 * @param bool $refresh Skip the cache.
	 *
	 * @return array<string, mixed>
	 */
	public function all( bool $refresh = false ): array {
		if ( null !== $this->memo && ! $refresh ) {
			return $this->memo;
		}

		$key    = $this->cache_key();
		$cached = $refresh ? false : get_transient( $key );

		if ( is_array( $cached ) ) {
			$this->memo = $cached;

			return $cached;
		}

		$map = $this->build();

		set_transient( $key, $map, self::TTL );
		$this->memo = $map;

		return $map;
	}

	/**
	 * Full parameter detail for one capability, resolved on demand.
	 *
	 * Kept out of the compact map so the assistant can look closely at one
	 * capability without carrying every schema on the site around with it.
	 *
	 * @param string $route A route, with or without placeholders.
	 *
	 * @return array<string, mixed>|null
	 */
	public function describe( string $route ): ?array {
		$route  = '/' . ltrim( trim( $route ), '/' );
		$routes = rest_get_server()->get_routes();
		$match  = null;

		foreach ( array_keys( $routes ) as $registered ) {
			if ( $registered === $route || $this->normalize( $registered ) === $this->normalize( $route ) ) {
				$match = $registered;
				break;
			}
		}

		if ( null === $match ) {
			$match = $this->match_pattern( $route, array_keys( $routes ) );
		}

		if ( null === $match ) {
			return null;
		}

		$described = array(
			'route'   => $this->normalize( $match ),
			'methods' => array(),
		);

		foreach ( $routes[ $match ] as $handler ) {
			$methods = array_keys( array_filter( $handler['methods'] ) );
			$args    = array();

			foreach ( (array) ( $handler['args'] ?? array() ) as $name => $definition ) {
				$args[] = array_filter(
					array(
						'name'        => (string) $name,
						'type'        => $definition['type'] ?? null,
						'required'    => ! empty( $definition['required'] ),
						'description' => isset( $definition['description'] ) ? (string) $definition['description'] : null,
						'enum'        => isset( $definition['enum'] ) ? array_values( (array) $definition['enum'] ) : null,
						'default'     => $definition['default'] ?? null,
					),
					static fn( $value ) => null !== $value && false !== $value
				);
			}

			foreach ( $methods as $method ) {
				$described['methods'][ $method ] = array( 'args' => $args );
			}
		}

		return $described;
	}

	/**
	 * Forget the cached map for every user.
	 */
	public function flush(): void {
		$this->memo = null;

		global $wpdb;

		$like = $wpdb->esc_like( '_transient_' . self::TRANSIENT_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup has no core API for prefix deletion.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		foreach ( (array) $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}

	/**
	 * Build the map from the live REST server.
	 *
	 * @return array<string, mixed>
	 */
	private function build(): array {
		$server      = rest_get_server();
		$routes      = $server->get_routes();
		$collections = array();

		foreach ( $routes as $route => $handlers ) {
			if ( ! $this->is_capability_route( $route ) ) {
				continue;
			}

			$base = $this->collection_base( $route );

			if ( ! isset( $collections[ $base ] ) ) {
				$collections[ $base ] = array(
					'base'      => $base,
					'namespace' => $this->namespace_of( $base ),
					'label'     => $this->label_for( $base ),
					'paths'     => array(),
					'methods'   => array(),
					'reads'     => false,
					'writes'    => false,
				);
			}

			$methods = array();

			foreach ( $handlers as $handler ) {
				$methods = array_merge( $methods, array_keys( array_filter( $handler['methods'] ) ) );
			}

			$methods = array_values( array_unique( $methods ) );

			$collections[ $base ]['paths'][] = $this->normalize( $route );
			$collections[ $base ]['methods'] = array_values( array_unique( array_merge( $collections[ $base ]['methods'], $methods ) ) );
			$collections[ $base ]['reads']   = $collections[ $base ]['reads'] || in_array( 'GET', $methods, true );
			$collections[ $base ]['writes']  = $collections[ $base ]['writes'] || (bool) array_intersect( $methods, array( 'POST', 'PUT', 'PATCH', 'DELETE' ) );
		}

		foreach ( $collections as $base => $collection ) {
			$collections[ $base ]['paths']     = array_values( array_unique( $collection['paths'] ) );
			$collections[ $base ]['available'] = $this->user_may_read( $base, $routes );
		}

		$collections = array_values(
			array_filter(
				$collections,
				// Capabilities the current user cannot reach are not offered at all (section 16.4).
				static fn( array $collection ) => $collection['available']
			)
		);

		usort( $collections, static fn( $a, $b ) => strcmp( $a['base'], $b['base'] ) );

		return array(
			'generated_at' => time(),
			'site'         => array(
				'name'     => get_bloginfo( 'name' ),
				'url'      => home_url(),
				'language' => get_bloginfo( 'language' ),
				'timezone' => wp_timezone_string(),
			),
			'capabilities' => $collections,
		);
	}

	/**
	 * Whether a route describes something the assistant could act on.
	 *
	 * @param string $route The registered route.
	 */
	private function is_capability_route( string $route ): bool {
		if ( in_array( $route, self::STRUCTURAL_ROUTES, true ) ) {
			return false;
		}

		if ( str_starts_with( $route, '/asdevs-ai/' ) ) {
			return false;
		}

		// Namespace index routes such as /wp/v2 carry no capability of their own.
		if ( $this->is_namespace_index( $route ) ) {
			return false;
		}

		return (bool) preg_match( '#^/[^/]+/[^/]+#', $route );
	}

	/**
	 * Whether the route is a namespace index such as /wp/v2.
	 *
	 * @param string $route The registered route.
	 */
	private function is_namespace_index( string $route ): bool {
		return in_array( ltrim( $route, '/' ), rest_get_server()->get_namespaces(), true );
	}

	/**
	 * The collection a route belongs to.
	 *
	 * @param string $route The registered route.
	 */
	private function collection_base( string $route ): string {
		$normalized = $this->normalize( $route );
		$position   = strpos( $normalized, '/{' );

		if ( false !== $position ) {
			$normalized = substr( $normalized, 0, $position );
		}

		return untrailingslashit( $normalized );
	}

	/**
	 * Turn a registered route regex into a readable path.
	 *
	 * @param string $route The registered route.
	 */
	private function normalize( string $route ): string {
		$readable = preg_replace( '#\(\?P<([^>]+)>[^)]*\)(\?)?#', '{$1}', $route );

		return untrailingslashit( (string) $readable );
	}

	/**
	 * The namespace part of a base route.
	 *
	 * @param string $base Collection base.
	 */
	private function namespace_of( string $base ): string {
		$parts = explode( '/', trim( $base, '/' ) );

		return implode( '/', array_slice( $parts, 0, 2 ) );
	}

	/**
	 * A human label for a collection, taken from the registry when possible.
	 *
	 * @param string $base Collection base.
	 */
	private function label_for( string $base ): string {
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( $this->registry_base( $post_type->rest_namespace, $post_type->rest_base, $post_type->name ) === $base ) {
				return (string) $post_type->labels->name;
			}
		}

		foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'objects' ) as $taxonomy ) {
			if ( $this->registry_base( $taxonomy->rest_namespace, $taxonomy->rest_base, $taxonomy->name ) === $base ) {
				return (string) $taxonomy->labels->name;
			}
		}

		$slug = (string) preg_replace( '#^.*/#', '', $base );

		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Build the base route a registry entry is served from.
	 *
	 * @param string|null $namespace REST namespace.
	 * @param string|bool $rest_base REST base.
	 * @param string      $name      Object name.
	 */
	private function registry_base( ?string $namespace, $rest_base, string $name ): string {
		$namespace = $namespace ? $namespace : 'wp/v2';
		$base      = is_string( $rest_base ) && '' !== $rest_base ? $rest_base : $name;

		return '/' . trim( $namespace, '/' ) . '/' . trim( $base, '/' );
	}

	/**
	 * Whether the current user may read a collection.
	 *
	 * Asks the route's own permission callback, so the answer is exactly the
	 * one WordPress would give: the assistant can never see more than the user.
	 *
	 * @param string                     $base   Collection base.
	 * @param array<string, array<mixed>> $routes Registered routes.
	 */
	private function user_may_read( string $base, array $routes ): bool {
		$handlers = $routes[ $base ] ?? null;

		if ( null === $handlers ) {
			return true;
		}

		foreach ( $handlers as $handler ) {
			if ( empty( $handler['methods']['GET'] ) ) {
				continue;
			}

			if ( empty( $handler['permission_callback'] ) ) {
				return true;
			}

			$request = new WP_REST_Request( 'GET', $base );
			$result  = call_user_func( $handler['permission_callback'], $request );

			return true === $result;
		}

		return true;
	}

	/**
	 * Find a registered route whose pattern matches a concrete path.
	 *
	 * @param string   $route      Concrete path, e.g. /wp/v2/posts/12.
	 * @param string[] $registered Registered route patterns.
	 */
	private function match_pattern( string $route, array $registered ): ?string {
		foreach ( $registered as $pattern ) {
			if ( preg_match( '@^' . $pattern . '$@i', $route ) ) {
				return $pattern;
			}
		}

		return null;
	}

	/**
	 * Cache key for the current user and site state.
	 */
	private function cache_key(): string {
		$state = array(
			get_current_user_id(),
			wp_json_encode( wp_roles()->get_names() ),
			wp_json_encode( get_option( 'active_plugins', array() ) ),
			get_option( 'stylesheet' ),
			get_bloginfo( 'version' ),
			ASDEVS_AI_ASSISTANT_VERSION,
		);

		return self::TRANSIENT_PREFIX . md5( implode( '|', array_map( 'strval', $state ) ) );
	}
}
