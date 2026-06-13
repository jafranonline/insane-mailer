<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_SmtpCom extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'SMTP.com';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'api_key' );
		$channel = $this->get_credential( 'channel' );

		if ( empty( $api_key ) || empty( $channel ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SMTP.com API key or channel',
			];
		}

		$url = 'https://api.smtp.com/v4/messages';

		$payload = [
			'channel' => $channel,
			'recipients' => [
				[
					'address' => $mail_data['to']['email'],
					'name'    => $mail_data['to']['name'] ?? '',
				],
			],
			'originator' => [
				'from' => [
					'address' => $mail_data['from']['email'],
					'name'    => $mail_data['from']['name'] ?? '',
				],
			],
			'subject' => $mail_data['subject'],
			'body'    => [
				'parts' => [],
			],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['body']['parts'][] = [
				'type'    => 'text/plain',
				'content' => $mail_data['body_plain'],
			];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['body']['parts'][] = [
				'type'    => 'text/html',
				'content' => $mail_data['body_html'],
			];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['originator']['reply_to'] = [
				'address' => $mail_data['reply_to'],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['attachments'][] = [
						'name'         => basename( $attachment ),
						'content'      => base64_encode( file_get_contents( $attachment ) ),
						'content_type' => mime_content_type( $attachment ),
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

		$message_id = $result['response']['message_id'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential( 'api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SMTP.com API key',
			];
		}

		$url = 'https://api.smtp.com/v4/channels';

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
			'message' => 'SMTP.com connection successful',
		];
	}
}
