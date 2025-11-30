<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class IM_Rest_Controller extends WP_REST_Controller {

	protected $namespace = 'insane-mailer/v1';

	public function permission_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	protected function success_response( $data, $status = 200 ) {
		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	protected function error_response( $message, $status = 400, $data = [] ) {
		return new WP_REST_Response(
			[
				'success' => false,
				'error'   => $message,
				'data'    => $data,
			],
			$status
		);
	}

	protected function validate_required_fields( $data, $required_fields ) {
		$missing = [];

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || '' === $data[ $field ] ) {
				$missing[] = $field;
			}
		}

		if ( ! empty( $missing ) ) {
			return sprintf(
				'Missing required fields: %s',
				implode( ', ', $missing )
			);
		}

		return null;
	}
}
