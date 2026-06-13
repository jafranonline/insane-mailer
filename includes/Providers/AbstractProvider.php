<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/ProviderInterface.php';

abstract class INSANEMAILER_Abstract_Provider implements INSANEMAILER_Provider_Interface {

	protected $credentials = [];

	public function __construct( $credentials ) {
		$this->credentials = $credentials;
	}

	protected function make_request( $url, $args = [] ) {
		$defaults = [
			'timeout' => 30,
			'headers' => [],
		];

		$args = wp_parse_args( $args, $defaults );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = $this->extract_error_message( $data, $body );

			return [
				'success'     => false,
				'error'       => $error_message,
				'status_code' => $status_code,
				'response'    => $data,
			];
		}

		return [
			'success'     => true,
			'status_code' => $status_code,
			'response'    => $data,
		];
	}

	protected function extract_error_message( $data, $body ) {
		if ( is_array( $data ) ) {
			if ( isset( $data['message'] ) ) {
				return $data['message'];
			}
			if ( isset( $data['error'] ) ) {
				return is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
			}
			if ( isset( $data['errors'] ) ) {
				return is_string( $data['errors'] ) ? $data['errors'] : wp_json_encode( $data['errors'] );
			}
		}

		return $body ? $body : 'Unknown error';
	}

	protected function validate_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
	}

	protected function get_credential( $key, $default = null ) {
		return $this->credentials[ $key ] ?? $default;
	}
}
