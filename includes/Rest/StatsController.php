<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Rest/Controller.php';

class IM_Rest_Stats_Controller extends IM_Rest_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/stats/analytics',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_analytics' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'period' => [
						'type'              => 'integer',
						'default'           => 30,
						'sanitize_callback' => 'absint',
					],
					'start_date' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end_date' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	public function get_analytics( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'im_emails';
		$period     = $request->get_param( 'period' ) ?: 30;
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		if ( $start_date && $end_date ) {
			$end   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );
			$start = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
			$days  = (int) ( ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS ) + 1;
		} else {
			$end   = gmdate( 'Y-m-d 23:59:59' );
			$start = gmdate( 'Y-m-d 00:00:00', strtotime( "-{$period} days" ) );
			$days  = $period;
		}

		$prev_start = gmdate( 'Y-m-d 00:00:00', strtotime( $start ) - ( $days * DAY_IN_SECONDS ) );
		$prev_end   = gmdate( 'Y-m-d 23:59:59', strtotime( $start ) - DAY_IN_SECONDS );

		$summary    = $this->get_summary( $table_name, $start, $end );
		$prev_summary = $this->get_summary( $table_name, $prev_start, $prev_end );
		$daily      = $this->get_daily_stats( $table_name, $start, $end );
		$by_provider = $this->get_provider_stats( $table_name, $start, $end );
		$by_status  = $this->get_status_breakdown( $table_name, $start, $end );

		$delivery_rate = $this->calculate_delivery_rate( $summary );
		$prev_delivery_rate = $this->calculate_delivery_rate( $prev_summary );
		$delivery_rate_change = $prev_delivery_rate > 0
			? round( $delivery_rate - $prev_delivery_rate, 1 )
			: 0;

		$volume_change = $prev_summary['total'] > 0
			? round( ( ( $summary['total'] - $prev_summary['total'] ) / $prev_summary['total'] ) * 100, 1 )
			: 0;

		return $this->success_response( [
			'period'     => [
				'start' => gmdate( 'Y-m-d', strtotime( $start ) ),
				'end'   => gmdate( 'Y-m-d', strtotime( $end ) ),
				'days'  => $days,
			],
			'summary'    => [
				'total'          => (int) $summary['total'],
				'sent'           => (int) $summary['sent'],
				'pending'        => (int) $summary['pending'],
				'failed'         => (int) $summary['failed'],
				'bounced'        => (int) $summary['bounced'],
				'complained'     => (int) $summary['complained'],
				'delivery_rate'  => $delivery_rate,
				'bounce_rate'    => $this->calculate_bounce_rate( $summary ),
				'complaint_rate' => $this->calculate_complaint_rate( $summary ),
			],
			'comparison' => [
				'delivery_rate_change' => $delivery_rate_change,
				'volume_change'        => $volume_change,
			],
			'daily'      => $daily,
			'by_provider' => $by_provider,
			'by_status'  => $by_status,
		] );
	}

	private function get_summary( $table_name, $start, $end ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM $table_name
				WHERE created_at BETWEEN %s AND %s
				GROUP BY status",
				$start,
				$end
			),
			OBJECT_K
		);

		return [
			'total'      => array_sum( array_column( (array) $results, 'count' ) ),
			'sent'       => isset( $results['sent'] ) ? (int) $results['sent']->count : 0,
			'pending'    => isset( $results['pending'] ) ? (int) $results['pending']->count : 0,
			'processing' => isset( $results['processing'] ) ? (int) $results['processing']->count : 0,
			'failed'     => isset( $results['failed'] ) ? (int) $results['failed']->count : 0,
			'bounced'    => isset( $results['bounced'] ) ? (int) $results['bounced']->count : 0,
			'complained' => isset( $results['complained'] ) ? (int) $results['complained']->count : 0,
			'paused'     => isset( $results['paused'] ) ? (int) $results['paused']->count : 0,
		];
	}

	private function get_daily_stats( $table_name, $start, $end ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(created_at) as date,
					SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
					SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
					SUM(CASE WHEN status = 'complained' THEN 1 ELSE 0 END) as complained,
					COUNT(*) as total
				FROM $table_name
				WHERE created_at BETWEEN %s AND %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$start,
				$end
			)
		);

		return array_map( function ( $row ) {
			return [
				'date'       => $row->date,
				'sent'       => (int) $row->sent,
				'failed'     => (int) $row->failed,
				'bounced'    => (int) $row->bounced,
				'complained' => (int) $row->complained,
				'total'      => (int) $row->total,
			];
		}, $results );
	}

	private function get_provider_stats( $table_name, $start, $end ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(provider, 'default') as provider,
					SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
					SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
					COUNT(*) as total
				FROM $table_name
				WHERE created_at BETWEEN %s AND %s
				AND status IN ('sent', 'failed', 'bounced')
				GROUP BY provider
				ORDER BY total DESC",
				$start,
				$end
			)
		);

		return array_map( function ( $row ) {
			$delivered = (int) $row->sent;
			$total     = $delivered + (int) $row->failed + (int) $row->bounced;
			$success_rate = $total > 0 ? round( ( $delivered / $total ) * 100, 1 ) : 0;

			return [
				'provider'     => $row->provider,
				'sent'         => $delivered,
				'failed'       => (int) $row->failed,
				'bounced'      => (int) $row->bounced,
				'total'        => $total,
				'success_rate' => $success_rate,
			];
		}, $results );
	}

	private function get_status_breakdown( $table_name, $start, $end ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM $table_name
				WHERE created_at BETWEEN %s AND %s
				GROUP BY status",
				$start,
				$end
			),
			OBJECT_K
		);

		return [
			'sent'       => isset( $results['sent'] ) ? (int) $results['sent']->count : 0,
			'pending'    => isset( $results['pending'] ) ? (int) $results['pending']->count : 0,
			'processing' => isset( $results['processing'] ) ? (int) $results['processing']->count : 0,
			'failed'     => isset( $results['failed'] ) ? (int) $results['failed']->count : 0,
			'bounced'    => isset( $results['bounced'] ) ? (int) $results['bounced']->count : 0,
			'complained' => isset( $results['complained'] ) ? (int) $results['complained']->count : 0,
			'paused'     => isset( $results['paused'] ) ? (int) $results['paused']->count : 0,
		];
	}

	private function calculate_delivery_rate( $summary ) {
		$delivered = $summary['sent'];
		$total     = $delivered + $summary['failed'] + $summary['bounced'];

		if ( $total === 0 ) {
			return 0;
		}

		return round( ( $delivered / $total ) * 100, 1 );
	}

	private function calculate_bounce_rate( $summary ) {
		$bounced = $summary['bounced'];
		$total   = $summary['sent'] + $bounced;

		if ( $total === 0 ) {
			return 0;
		}

		return round( ( $bounced / $total ) * 100, 1 );
	}

	private function calculate_complaint_rate( $summary ) {
		$complained = $summary['complained'];
		$sent       = $summary['sent'];

		if ( $sent === 0 ) {
			return 0;
		}

		return round( ( $complained / $sent ) * 100, 2 );
	}
}
