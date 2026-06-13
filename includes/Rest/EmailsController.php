<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Rest/Controller.php';

class INSANEMAILER_Rest_Emails_Controller extends INSANEMAILER_Rest_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/emails',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_emails' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/emails/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_email' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/emails/(?P<id>\d+)/retry',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'retry_email' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/emails/(?P<id>\d+)/pause',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'pause_email' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/emails/(?P<id>\d+)/resume',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'resume_email' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/emails/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_email' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/emails',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'clear_logs' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/export',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export_csv' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/stats',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_stats' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);
	}

	public function get_emails( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$page      = $request->get_param( 'page' ) ?? 1;
		$per_page  = $request->get_param( 'per_page' ) ?? 20;
		$status    = $request->get_param( 'status' ) ?? '';
		$search    = $request->get_param( 'search' ) ?? '';
		$send_mode = $request->get_param( 'send_mode' ) ?? '';

		$offset = ( $page - 1 ) * $per_page;

		$where = [ '1=1' ];
		$args  = [];

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}

		if ( ! empty( $send_mode ) ) {
			$where[] = 'send_mode = %s';
			$args[]  = $send_mode;
		}

		if ( ! empty( $search ) ) {
			$where[] = '(to_email LIKE %s OR subject LIKE %s)';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = implode( ' AND ', $where );

		// Add table name as first arg for %i placeholder.
		array_unshift( $args, $table_name );
		$args[] = $per_page;
		$args[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Where clause is safe, built from constants, args spread dynamically.
		$query = $wpdb->prepare( "SELECT * FROM %i WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d", ...$args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, prepared above.
		$emails = $wpdb->get_results( $query );

		$count_args = array_slice( $args, 0, -2 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Where clause is safe, built from constants, args spread dynamically.
		$count_query = $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE $where_clause", ...$count_args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, prepared above.
		$total = $wpdb->get_var( $count_query );

		return $this->success_response(
			[
				'emails'     => $emails,
				'total'      => (int) $total,
				'page'       => (int) $page,
				'per_page'   => (int) $per_page,
				'total_pages' => ceil( $total / $per_page ),
			]
		);
	}

	public function get_email( $request ) {
		global $wpdb;

		$email_id   = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single record.
		$email = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $email_id )
		);

		if ( ! $email ) {
			return $this->error_response( 'Email not found', 404 );
		}

		return $this->success_response( $email );
	}

	public function retry_email( $request ) {
		$email_id = $request->get_param( 'id' );

		$result = INSANEMAILER_Queue::instance()->retry_email( $email_id );

		if ( $result ) {
			return $this->success_response(
				[
					'message' => 'Email queued for retry',
					'id'      => $email_id,
				]
			);
		}

		return $this->error_response( 'Failed to retry email' );
	}

	public function pause_email( $request ) {
		global $wpdb;

		$email_id   = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single record.
		$email = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $email_id )
		);

		if ( ! $email ) {
			return $this->error_response( 'Email not found', 404 );
		}

		if ( 'pending' !== $email->status ) {
			return $this->error_response( 'Only pending emails can be paused' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$updated = $wpdb->update(
			$table_name,
			[ 'status' => 'paused' ],
			[ 'id' => $email_id ],
			[ '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		if ( false !== $updated ) {
			return $this->success_response(
				[
					'message' => 'Email paused',
					'id'      => $email_id,
				]
			);
		}

		return $this->error_response( 'Failed to pause email' );
	}

	public function resume_email( $request ) {
		global $wpdb;

		$email_id   = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single record.
		$email = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $email_id )
		);

		if ( ! $email ) {
			return $this->error_response( 'Email not found', 404 );
		}

		if ( 'paused' !== $email->status ) {
			return $this->error_response( 'Only paused emails can be resumed' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$updated = $wpdb->update(
			$table_name,
			[ 'status' => 'pending' ],
			[ 'id' => $email_id ],
			[ '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		if ( false !== $updated ) {
			return $this->success_response(
				[
					'message' => 'Email resumed',
					'id'      => $email_id,
				]
			);
		}

		return $this->error_response( 'Failed to resume email' );
	}

	public function delete_email( $request ) {
		global $wpdb;

		$email_id   = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single record.
		$email = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $email_id )
		);

		if ( ! $email ) {
			return $this->error_response( 'Email not found', 404 );
		}

		$this->delete_email_attachments( $email_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$deleted = $wpdb->delete(
			$table_name,
			[ 'id' => $email_id ],
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		if ( $deleted ) {
			return $this->success_response(
				[
					'message' => 'Email deleted successfully',
					'id'      => $email_id,
				]
			);
		}

		return $this->error_response( 'Failed to delete email' );
	}

	public function clear_logs( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$emails = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM %i', $table_name ) );

		foreach ( $emails as $email ) {
			$this->delete_email_attachments( $email->id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$deleted = $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		if ( false !== $deleted ) {
			return $this->success_response(
				[
					'message' => 'All logs cleared successfully',
				]
			);
		}

		return $this->error_response( 'Failed to clear logs' );
	}

	public function export_csv( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$status = $request->get_param( 'status' ) ?? '';

		$where = '1=1';
		$args  = [ $table_name ];

		if ( ! empty( $status ) ) {
			$where = 'status = %s';
			$args[] = $status;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Where clause is safe, args spread dynamically.
		$query = $wpdb->prepare( "SELECT * FROM %i WHERE $where ORDER BY created_at DESC LIMIT 1000", ...$args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, prepared above.
		$emails = $wpdb->get_results( $query, ARRAY_A );

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="insanemailer-emails-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		if ( ! empty( $emails ) ) {
			fputcsv( $output, array_keys( $emails[0] ) );

			foreach ( $emails as $email ) {
				fputcsv( $output, $email );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Required for output stream.
		fclose( $output );
		exit;
	}

	public function get_stats( $request ) {
		$stats = INSANEMAILER_Queue::instance()->get_stats();

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, recent emails not cacheable.
		$recent_emails = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC LIMIT 10', $table_name )
		);

		$cache_key   = 'insanemailer_daily_stats_30';
		$daily_stats = wp_cache_get( $cache_key, 'insane_mailer' );

		if ( false === $daily_stats ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$daily_stats = $wpdb->get_results(
				$wpdb->prepare( "SELECT DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed FROM %i WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date DESC", $table_name )
			);
			wp_cache_set( $cache_key, $daily_stats, 'insane_mailer', 300 );
		}

		return $this->success_response(
			[
				'stats'         => $stats,
				'recent_emails' => $recent_emails,
				'daily_stats'   => $daily_stats,
			]
		);
	}

	private function delete_email_attachments( $email_id ) {
		$upload_dir = wp_upload_dir();
		$insanemailer_dir    = $upload_dir['basedir'] . '/insanemailer-attachments/' . $email_id;

		if ( ! file_exists( $insanemailer_dir ) ) {
			return;
		}

		$files = glob( $insanemailer_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->rmdir( $insanemailer_dir );
	}
}
