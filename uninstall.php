<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'im_emails';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

delete_option( 'im_settings' );
delete_option( 'im_db_version' );

wp_clear_scheduled_hook( 'im_process_queue' );
wp_clear_scheduled_hook( 'im_cleanup_old_emails' );

$upload_dir = wp_upload_dir();
$im_dir    = $upload_dir['basedir'] . '/im-attachments';

if ( file_exists( $im_dir ) ) {
	$files = glob( $im_dir . '/*' );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		} elseif ( is_dir( $file ) ) {
			array_map( 'unlink', glob( "$file/*.*" ) );
			rmdir( $file );
		}
	}
	rmdir( $im_dir );
}

if ( is_multisite() ) {
	$sites = get_sites();
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );

		$table_name = $wpdb->prefix . 'im_emails';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		delete_option( 'im_settings' );
		delete_option( 'im_db_version' );

		restore_current_blog();
	}
}
