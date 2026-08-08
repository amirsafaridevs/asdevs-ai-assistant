<?php
/**
 * Removes everything this plugin stored.
 *
 * Deleting the plugin deletes the data: the service settings, every stored
 * conversation, and the discovery caches. Nothing is left behind.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's data for one site.
 */
function asdevs_ai_assistant_uninstall_site(): void {
	global $wpdb;

	delete_option( 'asdevs_ai_assistant_service' );
	delete_option( 'asdevs_ai_assistant_memory' );

	// Core API deletes the key for every user (object_id is ignored when delete_all is true).
	delete_metadata( 'user', 0, 'asdevs_ai_assistant_conversations', '', true );

	// Prefixed options (transients + timeouts) have no bulk-delete API in core.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( array( '_transient_asdevs_ai_', '_transient_timeout_asdevs_ai_' ) as $prefix ) {
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		foreach ( (array) $names as $name ) {
			delete_option( (string) $name );
		}
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

if ( is_multisite() ) {
	$asdevs_ai_assistant_sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $asdevs_ai_assistant_sites as $asdevs_ai_assistant_site_id ) {
		switch_to_blog( (int) $asdevs_ai_assistant_site_id );
		asdevs_ai_assistant_uninstall_site();
		restore_current_blog();
	}
} else {
	asdevs_ai_assistant_uninstall_site();
}
