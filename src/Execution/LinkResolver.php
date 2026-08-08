<?php
/**
 * Links from results back into the admin panel.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Execution;

use ASDevs\AIAssistant\Security\ActionRequest;
use ASDevs\AIAssistant\Security\RouteInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds panel links for whatever an action touched.
 *
 * The assistant complements the panel; a user who cannot see the result in
 * the panel does not trust the assistant (section 9.5).
 */
final class LinkResolver {

	/**
	 * Route inspector.
	 *
	 * @var RouteInspector
	 */
	private RouteInspector $routes;

	/**
	 * Constructor.
	 *
	 * @param RouteInspector $routes Route inspector.
	 */
	public function __construct( RouteInspector $routes ) {
		$this->routes = $routes;
	}

	/**
	 * Links for a result payload.
	 *
	 * @param ActionRequest $action The action that produced the result.
	 * @param mixed         $data   The response data.
	 *
	 * @return array<string, string>
	 */
	public function for_result( ActionRequest $action, $data ): array {
		if ( ! is_array( $data ) || ! isset( $data['id'] ) || ! is_numeric( $data['id'] ) ) {
			return array();
		}

		$id    = (int) $data['id'];
		$links = array();

		// Media: edit in library + direct file URL. Attachment "preview" pages are useless here.
		if ( $this->routes->is_media_route( $action ) ) {
			$edit = get_edit_post_link( $id, 'raw' );

			if ( $edit ) {
				$links['edit'] = $edit;
			}

			if ( isset( $data['source_url'] ) && is_string( $data['source_url'] ) && '' !== $data['source_url'] ) {
				$links['file'] = $data['source_url'];
			}

			return $links;
		}

		$post_type = $this->routes->post_type_for( $action );

		if ( null !== $post_type ) {
			$edit = get_edit_post_link( $id, 'raw' );

			if ( $edit ) {
				$links['edit'] = $edit;
			}

			if ( isset( $data['link'] ) && is_string( $data['link'] ) && 'publish' === ( $data['status'] ?? '' ) ) {
				$links['view'] = $data['link'];
			} else {
				$preview = get_preview_post_link( $id );

				if ( $preview ) {
					$links['preview'] = $preview;
				}
			}

			return $links;
		}

		if ( $this->routes->is_user_route( $action ) ) {
			$edit = get_edit_user_link( $id );

			if ( $edit ) {
				$links['edit'] = $edit;
			}

			return $links;
		}

		$taxonomy = $this->routes->taxonomy_for( $action );

		if ( null !== $taxonomy ) {
			$edit = get_edit_term_link( $id, $taxonomy->name );

			if ( $edit ) {
				$links['edit'] = $edit;
			}
		}

		return $links;
	}
}
