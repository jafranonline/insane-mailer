<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_RateLimiter {

	private $rate_limit;
	private $last_send_time = 0;

	public function __construct( $rate_limit = 14 ) {
		$this->rate_limit = $rate_limit;
	}

	public function throttle() {
		if ( $this->rate_limit <= 0 ) {
			return;
		}

		$delay_microseconds = (int) ( 1000000 / $this->rate_limit );

		$current_time = microtime( true );

		if ( $this->last_send_time > 0 ) {
			$time_since_last = ( $current_time - $this->last_send_time ) * 1000000;
			$sleep_time      = $delay_microseconds - $time_since_last;

			if ( $sleep_time > 0 ) {
				usleep( (int) $sleep_time );
			}
		}

		$this->last_send_time = microtime( true );
	}

	public function get_rate_limit() {
		return $this->rate_limit;
	}

	public function set_rate_limit( $rate_limit ) {
		$this->rate_limit = $rate_limit;
	}
}
