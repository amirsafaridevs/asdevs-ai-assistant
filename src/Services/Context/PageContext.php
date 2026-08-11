<?php
/**
 * Where the user is standing in the admin.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the current admin screen into something the assistant can reason about.
 *
 * Section 13.5: on an edit screen, "publish this" means this post. Page
 * context is what keeps the number of questions down.
 */
final class PageContext {

	/**
	 * The current screen, described for the assistant.
	 *
	 * Read on the server from the real screen, never taken from the browser.
	 *
	 * @return array<string, mixed>
	 */
	public function current(): array {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return array();
		}

		$screen = get_current_screen();

		if ( null === $screen ) {
			return array();
		}

		$context = array(
			'screen'    => (string) $screen->id,
			'base'      => (string) $screen->base,
			'post_type' => (string) $screen->post_type,
			'taxonomy'  => (string) $screen->taxonomy,
			'title'     => function_exists( 'get_admin_page_title' ) ? wp_strip_all_tags( (string) get_admin_page_title() ) : '',
		);

		$object = $this->focused_object( $screen );

		if ( array() !== $object ) {
			$context['focus'] = $object;
		}

		$context['description'] = $this->describe( $screen, $object );

		return $context;
	}

	/**
	 * The single object the screen is about, when there is one.
	 *
	 * @param \WP_Screen $screen Current screen.
	 *
	 * @return array<string, mixed>
	 */
	private function focused_object( \WP_Screen $screen ): array {
		if ( 'post' === $screen->base ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the screen's own context, no action taken.
			$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
			$post    = $post_id > 0 ? get_post( $post_id ) : null;

			if ( $post instanceof \WP_Post && current_user_can( 'edit_post', $post->ID ) ) {
				$post_type = get_post_type_object( $post->post_type );

				return array(
					'type'      => 'post',
					'post_type' => $post->post_type,
					'id'        => (int) $post->ID,
					'title'     => (string) get_the_title( $post ),
					'status'    => (string) $post->post_status,
					'route'     => '/wp/v2/' . ( $post_type && $post_type->rest_base ? $post_type->rest_base : $post->post_type ) . '/' . (int) $post->ID,
				);
			}
		}

		if ( 'user-edit' === $screen->base ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the screen's own context, no action taken.
			$user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
			$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

			if ( $user && current_user_can( 'edit_user', $user->ID ) ) {
				return array(
					'type'  => 'user',
					'id'    => (int) $user->ID,
					'title' => (string) $user->display_name,
					'route' => '/wp/v2/users/' . (int) $user->ID,
				);
			}
		}

		return array();
	}

	/**
	 * One sentence describing where the user is, in their language.
	 *
	 * @param \WP_Screen           $screen Current screen.
	 * @param array<string, mixed> $object The focused object, if any.
	 */
	private function describe( \WP_Screen $screen, array $object ): string {
		if ( isset( $object['title'] ) && 'post' === ( $object['type'] ?? '' ) ) {
			$post_type = get_post_type_object( (string) $object['post_type'] );
			$label     = $post_type ? (string) $post_type->labels->singular_name : __( 'item', 'asdevs-ai-assistant' );

			return sprintf(
				/* translators: 1: content type, e.g. Post. 2: item title. */
				__( 'Editing the %1$s "%2$s"', 'asdevs-ai-assistant' ),
				$label,
				$object['title']
			);
		}

		if ( isset( $object['title'] ) && 'user' === ( $object['type'] ?? '' ) ) {
			return sprintf(
				/* translators: %s: person's name. */
				__( 'Editing the account of %s', 'asdevs-ai-assistant' ),
				$object['title']
			);
		}

		if ( 'edit' === $screen->base && '' !== (string) $screen->post_type ) {
			$post_type = get_post_type_object( (string) $screen->post_type );

			if ( $post_type ) {
				return sprintf(
					/* translators: %s: content type label, e.g. Posts. */
					__( 'Looking at the list of %s', 'asdevs-ai-assistant' ),
					(string) $post_type->labels->name
				);
			}
		}

		if ( 'users' === $screen->base ) {
			return __( 'Looking at the list of people with access', 'asdevs-ai-assistant' );
		}

		if ( 'plugins' === $screen->base ) {
			return __( 'Looking at the plugin list', 'asdevs-ai-assistant' );
		}

		if ( 'dashboard' === $screen->base ) {
			return __( 'On the dashboard', 'asdevs-ai-assistant' );
		}

		return '';
	}
}
