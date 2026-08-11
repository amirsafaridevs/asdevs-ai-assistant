<?php
/**
 * Maps REST routes back to the WordPress objects they touch.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers "what does this route actually touch?" using WordPress registries.
 *
 * Nothing here is hard-coded per plugin: post types and taxonomies are read
 * from the live registry, so a custom post type behaves like a core one.
 */
final class RouteInspector {

	/**
	 * Post type objects keyed by their REST namespace + base.
	 *
	 * @var array<string, \WP_Post_Type>|null
	 */
	private ?array $post_type_map = null;

	/**
	 * Taxonomy objects keyed by their REST namespace + base.
	 *
	 * @var array<string, \WP_Taxonomy>|null
	 */
	private ?array $taxonomy_map = null;

	/**
	 * The post type a route belongs to, when it is a post type route.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function post_type_for( ActionRequest $action ): ?\WP_Post_Type {
		return $this->post_type_map()[ $this->collection_key( $action ) ] ?? null;
	}

	/**
	 * The taxonomy a route belongs to, when it is a taxonomy route.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function taxonomy_for( ActionRequest $action ): ?\WP_Taxonomy {
		return $this->taxonomy_map()[ $this->collection_key( $action ) ] ?? null;
	}

	/**
	 * Whether the route targets users.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function is_user_route( ActionRequest $action ): bool {
		return $this->matches( $action, '#^/wp/v2/users(/|$)#' );
	}

	/**
	 * Whether the route targets site settings.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function is_settings_route( ActionRequest $action ): bool {
		return $this->matches( $action, '#^/wp/v2/settings(/|$)#' );
	}

	/**
	 * Whether the route targets plugins.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function is_plugin_route( ActionRequest $action ): bool {
		return $this->matches( $action, '#^/wp/v2/plugins(/|$)#' );
	}

	/**
	 * Whether the route targets themes.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function is_theme_route( ActionRequest $action ): bool {
		return $this->matches( $action, '#^/wp/v2/(themes|global-styles)(/|$)#' );
	}

	/**
	 * Whether the route targets the media library.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function is_media_route( ActionRequest $action ): bool {
		return $this->matches( $action, '#^/wp/v2/media(/|$)#' );
	}

	/**
	 * Whether the route addresses a single object rather than a collection.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function is_single_object( ActionRequest $action ): bool {
		return (bool) preg_match( '#/[^/]*\d[^/]*$#', $action->route() );
	}

	/**
	 * The trailing id of a single object route, when numeric.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function object_id( ActionRequest $action ): ?int {
		if ( preg_match( '#/(\d+)$#', $action->route(), $matches ) ) {
			return (int) $matches[1];
		}

		return null;
	}

	/**
	 * Whether the object behind this route can be restored from the trash.
	 *
	 * @param ActionRequest $action The action.
	 */
	public function supports_trash( ActionRequest $action ): bool {
		$post_type = $this->post_type_for( $action );

		if ( null === $post_type ) {
			return false;
		}

		if ( 'attachment' === $post_type->name ) {
			return false;
		}

		return EMPTY_TRASH_DAYS > 0;
	}

	/**
	 * Compare a route against a pattern.
	 *
	 * @param ActionRequest $action  The action.
	 * @param string        $pattern Regular expression.
	 */
	private function matches( ActionRequest $action, string $pattern ): bool {
		return (bool) preg_match( $pattern, $action->route() );
	}

	/**
	 * The collection part of a route, e.g. /wp/v2/posts.
	 *
	 * @param ActionRequest $action The action.
	 */
	private function collection_key( ActionRequest $action ): string {
		$route = $action->base_route();

		// Drop sub-resources such as /wp/v2/posts/12/revisions.
		$route = (string) preg_replace( '#(/wp/v2/[^/]+)/.*$#', '$1', $route );

		return untrailingslashit( $route );
	}

	/**
	 * Build the post type route map.
	 *
	 * @return array<string, \WP_Post_Type>
	 */
	private function post_type_map(): array {
		if ( null !== $this->post_type_map ) {
			return $this->post_type_map;
		}

		$map = array();

		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			$namespace = $post_type->rest_namespace ? $post_type->rest_namespace : 'wp/v2';
			$base      = $post_type->rest_base ? $post_type->rest_base : $post_type->name;

			$map[ '/' . trim( $namespace, '/' ) . '/' . trim( (string) $base, '/' ) ] = $post_type;
		}

		$this->post_type_map = $map;

		return $map;
	}

	/**
	 * Build the taxonomy route map.
	 *
	 * @return array<string, \WP_Taxonomy>
	 */
	private function taxonomy_map(): array {
		if ( null !== $this->taxonomy_map ) {
			return $this->taxonomy_map;
		}

		$map = array();

		foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'objects' ) as $taxonomy ) {
			$namespace = $taxonomy->rest_namespace ? $taxonomy->rest_namespace : 'wp/v2';
			$base      = $taxonomy->rest_base ? $taxonomy->rest_base : $taxonomy->name;

			$map[ '/' . trim( $namespace, '/' ) . '/' . trim( (string) $base, '/' ) ] = $taxonomy;
		}

		$this->taxonomy_map = $map;

		return $map;
	}
}
