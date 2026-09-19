<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_Loops extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'Loops';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential_any( [ 'api_key', 'loops_api_key' ] );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Loops API key',
			];
		}

		$url = 'https://app.loops.so/api/v1/transactional';

		$payload = [
			'email'            => $mail_data['to']['email'],
			'transactionalId'  => $mail_data['template_id'] ?? '',
			'dataVariables'    => [
				'subject' => $mail_data['subject'],
			],
		];

		// Loops uses transactional templates, but we can send custom content
		if ( ! empty( $mail_data['body_html'] ) || ! empty( $mail_data['body_plain'] ) ) {
			$payload['dataVariables']['body'] = $mail_data['body_html'] ?? $mail_data['body_plain'];
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['id'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential_any( [ 'api_key', 'loops_api_key' ] );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Loops API key',
			];
		}

		$url = 'https://app.loops.so/api/v1/api-key';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Loops connection successful',
		];
	}
}
