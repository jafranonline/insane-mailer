<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_ElasticEmail extends IM_Abstract_Provider {

	public function get_name() {
		return 'Elastic Email';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Elastic Email API key',
			];
		}

		$url = 'https://api.elasticemail.com/v2/email/send';

		$from = ! empty( $mail_data['from']['name'] )
			? "{$mail_data['from']['name']} <{$mail_data['from']['email']}>"
			: $mail_data['from']['email'];

		$body = [
			'apikey'  => $api_key,
			'from'    => $from,
			'to'      => $mail_data['to']['email'],
			'subject' => $mail_data['subject'],
			'bodyHtml' => $mail_data['body_html'],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$body['bodyText'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['to']['name'] ) ) {
			$body['toName'] = $mail_data['to']['name'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$body['replyTo'] = $mail_data['reply_to'];
		}

		$result = $this->make_request(
			$url,
			[
				'method' => 'POST',
				'body'   => $body,
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['data']['messageid'] ?? null;

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
				'error'   => 'Missing Elastic Email API key',
			];
		}

		$url = 'https://api.elasticemail.com/v2/account/load';

		$result = $this->make_request(
			$url,
			[
				'method' => 'POST',
				'body'   => [
					'apikey' => $api_key,
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Elastic Email connection successful',
		];
	}
}
