<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_Deactivator {

	public static function deactivate() {
		self::clear_scheduled_crons();
		flush_rewrite_rules();
	}

	private static function clear_scheduled_crons() {
		wp_clear_scheduled_hook( 'im_process_queue' );
		wp_clear_scheduled_hook( 'im_cleanup_old_emails' );
	}
}
