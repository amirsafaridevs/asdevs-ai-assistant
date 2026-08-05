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

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup at uninstall; there is no core API for prefixed meta or transient deletion.
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'asdevs_ai_assistant_conversations' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

	foreach ( array( '_transient_asdevs_ai_', '_transient_timeout_asdevs_ai_' ) as $prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
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
