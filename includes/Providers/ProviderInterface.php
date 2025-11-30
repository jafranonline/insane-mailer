<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface IM_Provider_Interface {

	public function __construct( $credentials );

	public function send_raw( $mail_data );

	public function test_connection();

	public function get_name();
}
