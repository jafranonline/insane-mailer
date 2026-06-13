<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Rest/Controller.php';

class INSANEMAILER_Rest_Queue_Controller extends INSANEMAILER_Rest_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/queue/process',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'process_queue' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/queue/status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_queue_status' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/cron',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'external_cron' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'token' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);
	}

	public function process_queue( $request ) {
		INSANEMAILER_Queue::instance()->process();

		$stats = INSANEMAILER_Queue::instance()->get_stats();

		return $this->success_response(
			[
				'message' => 'Queue processed successfully',
				'stats'   => $stats,
			]
		);
	}

	public function get_queue_status( $request ) {
		$stats = INSANEMAILER_Queue::instance()->get_stats();

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$cache_key   = 'insanemailer_queue_status';
		$cached_data = wp_cache_get( $cache_key, 'insane_mailer' );

		if ( false === $cached_data ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$processing = $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'processing'", $table_name )
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$pending_scheduled = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE status = 'pending' AND scheduled_at > %s",
					$table_name,
					current_time( 'mysql' )
				)
			);

			$cached_data = [
				'processing'        => (int) $processing,
				'pending_scheduled' => (int) $pending_scheduled,
			];
			wp_cache_set( $cache_key, $cached_data, 'insane_mailer', 30 );
		}

		$is_locked = get_transient( 'insanemailer_queue_lock' );

		return $this->success_response(
			[
				'stats'             => $stats,
				'processing'        => $cached_data['processing'],
				'pending_scheduled' => $cached_data['pending_scheduled'],
				'is_locked'         => (bool) $is_locked,
			]
		);
	}

	public function external_cron( $request ) {
		$token    = $request->get_param( 'token' );
		$settings = get_option( 'insanemailer_settings', [] );

		$valid_token = $settings['cron_token'] ?? '';

		if ( empty( $valid_token ) || $token !== $valid_token ) {
			return $this->error_response( 'Invalid token', 401 );
		}

		INSANEMAILER_Queue::instance()->process();

		$stats = INSANEMAILER_Queue::instance()->get_stats();

		return $this->success_response(
			[
				'message' => 'Queue processed via external cron',
				'stats'   => $stats,
			]
		);
	}
}
