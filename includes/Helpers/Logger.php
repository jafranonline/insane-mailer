<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_Logger {

	public static function log( $message, $level = 'info', $context = [] ) {
		if ( ! WP_DEBUG || ! WP_DEBUG_LOG ) {
			return;
		}

		$log_entry = sprintf(
			'[Insane Mailer] [%s] %s',
			strtoupper( $level ),
			is_string( $message ) ? $message : print_r( $message, true )
		);

		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . print_r( $context, true );
		}

		error_log( $log_entry );
	}

	public static function debug( $message, $context = [] ) {
		self::log( $message, 'debug', $context );
	}

	public static function info( $message, $context = [] ) {
		self::log( $message, 'info', $context );
	}

	public static function warning( $message, $context = [] ) {
		self::log( $message, 'warning', $context );
	}

	public static function error( $message, $context = [] ) {
		self::log( $message, 'error', $context );
	}
}
