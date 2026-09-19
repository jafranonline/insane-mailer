<?php
/**
 * Removes everything Insane Mailer stored when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function insanemailer_uninstall_site() {
	global $wpdb;

	foreach ( [ 'insanemailer_settings', 'insanemailer_db_version', 'insanemailer_queue_drain_pending' ] as $option ) {
		delete_option( $option );
	}

	foreach ( [ 'insanemailer_connection_status', 'insanemailer_queue_lock', 'im_connection_status', 'im_queue_lock' ] as $transient ) {
		delete_transient( $transient );
	}

	foreach ( [ 'insanemailer_cleanup_old_emails', 'insanemailer_drain_legacy_queue', 'insanemailer_process_queue' ] as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping the plugin's own table on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'insanemailer_emails' ) );

	$upload_dir      = wp_upload_dir();
	$attachments_dir = trailingslashit( $upload_dir['basedir'] ) . 'insanemailer-attachments';

	if ( is_dir( $attachments_dir ) ) {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			$wp_filesystem->rmdir( $attachments_dir, true );
		}
	}
}

if ( is_multisite() ) {
	$insanemailer_site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $insanemailer_site_ids as $insanemailer_site_id ) {
		switch_to_blog( $insanemailer_site_id );
		insanemailer_uninstall_site();
		restore_current_blog();
	}
} else {
	insanemailer_uninstall_site();
}
