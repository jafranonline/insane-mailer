<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Deactivator {

	public static function deactivate() {
		self::clear_scheduled_crons();
		flush_rewrite_rules();
	}

	private static function clear_scheduled_crons() {
		wp_clear_scheduled_hook( 'insanemailer_cleanup_old_emails' );
		wp_clear_scheduled_hook( 'insanemailer_drain_legacy_queue' );

		// Left behind by queue mode.
		wp_clear_scheduled_hook( 'insanemailer_process_queue' );
	}
}
