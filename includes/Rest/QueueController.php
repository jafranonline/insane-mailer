<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Rest/Controller.php';

class IM_Rest_Queue_Controller extends IM_Rest_Controller {

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
		IM_Logger::info( 'Manual queue processing triggered' );

		IM_Queue::instance()->process();

		$stats = IM_Queue::instance()->get_stats();

		return $this->success_response(
			[
				'message' => 'Queue processed successfully',
				'stats'   => $stats,
			]
		);
	}

	public function get_queue_status( $request ) {
		$stats = IM_Queue::instance()->get_stats();

		global $wpdb;
		$table_name = $wpdb->prefix . 'im_emails';

		$processing = $wpdb->get_var(
			"SELECT COUNT(*) FROM $table_name WHERE status = 'processing'"
		);

		$pending_scheduled = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE status = 'pending' AND scheduled_at > %s",
				current_time( 'mysql' )
			)
		);

		$is_locked = get_transient( 'im_queue_lock' );

		return $this->success_response(
			[
				'stats'             => $stats,
				'processing'        => (int) $processing,
				'pending_scheduled' => (int) $pending_scheduled,
				'is_locked'         => (bool) $is_locked,
			]
		);
	}

	public function external_cron( $request ) {
		$token    = $request->get_param( 'token' );
		$settings = get_option( 'im_settings', [] );

		$valid_token = $settings['cron_token'] ?? '';

		if ( empty( $valid_token ) || $token !== $valid_token ) {
			return $this->error_response( 'Invalid token', 401 );
		}

		IM_Logger::info( 'External cron triggered' );

		IM_Queue::instance()->process();

		$stats = IM_Queue::instance()->get_stats();

		return $this->success_response(
			[
				'message' => 'Queue processed via external cron',
				'stats'   => $stats,
			]
		);
	}
}
