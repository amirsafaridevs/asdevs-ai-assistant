<?php
/**
 * A quick read of the site's current state.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The handful of facts the assistant needs to open a conversation usefully.
 *
 * Everything here is scoped to what the current user is allowed to see, and
 * every number is real: suggestions built on invented numbers are what makes
 * an assistant feel like a generic chatbot.
 */
final class SiteSnapshot {

	/**
	 * Memoized snapshot.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $memo = null;

	/**
	 * The snapshot for the current user.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		if ( null !== $this->memo ) {
			return $this->memo;
		}

		$this->memo = array(
			'site'    => array(
				'name'         => get_bloginfo( 'name' ),
				'description'  => get_bloginfo( 'description' ),
				'locale'       => get_user_locale(),
				'admin_locale' => get_locale(),
				'timezone'     => wp_timezone_string(),
				'date_format'  => (string) get_option( 'date_format' ),
				'time_format'  => (string) get_option( 'time_format' ),
				'is_rtl'       => is_rtl(),
				'is_multisite' => is_multisite(),
				'admin_url'    => admin_url(),
			),
			'user'    => $this->current_user(),
			'content' => $this->content_counts(),
			'plugins' => $this->plugin_inventory(),
			'users'   => $this->user_count(),
		);

		return $this->memo;
	}

	/**
	 * Facts about the acting user.
	 *
	 * @return array<string, mixed>
	 */
	private function current_user(): array {
		$user = wp_get_current_user();

		return array(
			'id'           => (int) $user->ID,
			'display_name' => (string) $user->display_name,
			'roles'        => array_values( (array) $user->roles ),
			'can'          => array(
				'edit_posts'      => current_user_can( 'edit_posts' ),
				'publish_posts'   => current_user_can( 'publish_posts' ),
				'edit_others'     => current_user_can( 'edit_others_posts' ),
				'list_users'      => current_user_can( 'list_users' ),
				'manage_plugins'  => current_user_can( 'activate_plugins' ),
				'manage_options'  => current_user_can( 'manage_options' ),
				'upload_files'    => current_user_can( 'upload_files' ),
				'moderate_comments' => current_user_can( 'moderate_comments' ),
			),
		);
	}

	/**
	 * Content counts the user is allowed to see.
	 *
	 * @return array<string, mixed>
	 */
	private function content_counts(): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return array();
		}

		$types = array();

		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( 'attachment' === $post_type->name || ! current_user_can( $post_type->cap->edit_posts ) ) {
				continue;
			}

			$counts = wp_count_posts( $post_type->name );

			$types[ $post_type->name ] = array(
				'label'     => (string) $post_type->labels->name,
				'publish'   => (int) ( $counts->publish ?? 0 ),
				'draft'     => (int) ( $counts->draft ?? 0 ),
				'pending'   => (int) ( $counts->pending ?? 0 ),
				'future'    => (int) ( $counts->future ?? 0 ),
				'trash'     => (int) ( $counts->trash ?? 0 ),
				'rest_base' => (string) ( $post_type->rest_base ? $post_type->rest_base : $post_type->name ),
			);
		}

		return $types;
	}

	/**
	 * Installed plugins with active/inactive status, when allowed.
	 *
	 * @return array<string, mixed>
	 */
	private function plugin_inventory(): array {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return array();
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all     = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$counts  = get_site_transient( 'update_plugins' );
		$updates = isset( $counts->response ) && is_array( $counts->response ) ? count( $counts->response ) : 0;

		$active_names   = array();
		$inactive_names = array();

		foreach ( $all as $file => $plugin ) {
			$name = isset( $plugin['Name'] ) ? (string) $plugin['Name'] : (string) $file;

			if ( in_array( $file, $active, true ) ) {
				$active_names[] = $name;
			} else {
				$inactive_names[] = $name;
			}
		}

		sort( $active_names, SORT_NATURAL | SORT_FLAG_CASE );
		sort( $inactive_names, SORT_NATURAL | SORT_FLAG_CASE );

		return array(
			'total'    => count( $all ),
			'active'   => count( $active_names ),
			'inactive' => count( $inactive_names ),
			'updates'  => $updates,
			'list'     => array(
				'active'   => $active_names,
				'inactive' => $inactive_names,
			),
		);
	}

	/**
	 * User count, only when the user may list users.
	 */
	private function user_count(): ?int {
		if ( ! current_user_can( 'list_users' ) ) {
			return null;
		}

		$counts = count_users();

		return isset( $counts['total_users'] ) ? (int) $counts['total_users'] : null;
	}
}
