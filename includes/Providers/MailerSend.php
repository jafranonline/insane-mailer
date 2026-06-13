<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_MailerSend extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'MailerSend';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'mailersend_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing MailerSend API key',
			];
		}

		$url = 'https://api.mailersend.com/v1/email';

		$payload = [
			'from' => [
				'email' => $mail_data['from']['email'],
				'name'  => $mail_data['from']['name'] ?? '',
			],
			'to' => [
				[
					'email' => $mail_data['to']['email'],
					'name'  => $mail_data['to']['name'] ?? '',
				],
			],
			'subject' => $mail_data['subject'],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['text'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['html'] = $mail_data['body_html'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['reply_to'] = [
				[
					'email' => $mail_data['reply_to'],
				],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['attachments'][] = [
						'filename' => basename( $attachment ),
						'content'  => base64_encode( file_get_contents( $attachment ) ),
					];
				}
			}
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

		$message_id = $result['response']['x_message_id'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential( 'mailersend_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing MailerSend API key',
			];
		}

		$url = 'https://api.mailersend.com/v1/domains';

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
			'message' => 'MailerSend connection successful',
		];
	}
}
