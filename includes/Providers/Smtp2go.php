<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_Smtp2go extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'SMTP2GO';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'smtp2go_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SMTP2GO API key',
			];
		}

		$url = 'https://api.smtp2go.com/v3/email/send';

		$sender = $mail_data['from']['name']
			? $mail_data['from']['name'] . ' <' . $mail_data['from']['email'] . '>'
			: $mail_data['from']['email'];

		$recipient = $mail_data['to']['name']
			? $mail_data['to']['name'] . ' <' . $mail_data['to']['email'] . '>'
			: $mail_data['to']['email'];

		$payload = [
			'api_key' => $api_key,
			'sender'  => $sender,
			'to'      => [ $recipient ],
			'subject' => $mail_data['subject'],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['text_body'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['html_body'] = $mail_data['body_html'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['custom_headers'] = [
				[
					'header' => 'Reply-To',
					'value'  => $mail_data['reply_to'],
				],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['attachments'][] = [
						'filename' => basename( $attachment ),
						'fileblob' => base64_encode( file_get_contents( $attachment ) ),
						'mimetype' => mime_content_type( $attachment ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['data']['email_id'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential( 'smtp2go_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SMTP2GO API key',
			];
		}

		$url = 'https://api.smtp2go.com/v3/stats/email_summary';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( [ 'api_key' => $api_key ] ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'SMTP2GO connection successful',
		];
	}
}
