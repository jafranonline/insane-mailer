<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_SES extends IM_Abstract_Provider {

	public function get_name() {
		return 'Amazon SES';
	}

	public function send_raw( $mail_data ) {
		$auth_type = $this->get_credential( 'ses_auth_type', 'api' );
		$region    = $this->get_credential( 'region', 'us-east-1' );

		if ( 'smtp' === $auth_type ) {
			return $this->send_via_smtp( $mail_data, $region );
		}

		return $this->send_via_api( $mail_data, $region );
	}

	private function send_via_api( $mail_data, $region ) {
		$access_key = $this->get_credential( 'ses_access_key' ) ?: $this->get_credential( 'access_key' );
		$secret_key = $this->get_credential( 'ses_secret_key' ) ?: $this->get_credential( 'secret_key' );

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing AWS credentials',
			];
		}

		$host = "email.{$region}.amazonaws.com";

		$raw_email = $this->build_raw_email( $mail_data );

		$params = [
			'Action'          => 'SendRawEmail',
			'RawMessage.Data' => base64_encode( $raw_email ),
		];

		$result = $this->make_ses_request( $host, $region, $access_key, $secret_key, $params );

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = null;
		if ( isset( $result['response']->SendRawEmailResult->MessageId ) ) {
			$message_id = (string) $result['response']->SendRawEmailResult->MessageId;
		}

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	private function send_via_smtp( $mail_data, $region ) {
		$username = $this->get_credential( 'ses_smtp_username' );
		$password = $this->get_credential( 'ses_smtp_password' );

		if ( empty( $username ) || empty( $password ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SMTP credentials',
			];
		}

		$host = "email-smtp.{$region}.amazonaws.com";
		$port = 587;

		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

		$phpmailer = new PHPMailer\PHPMailer\PHPMailer( true );

		try {
			$phpmailer->isSMTP();
			$phpmailer->Host       = $host;
			$phpmailer->SMTPAuth   = true;
			$phpmailer->Username   = $username;
			$phpmailer->Password   = $password;
			$phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			$phpmailer->Port       = $port;

			$phpmailer->setFrom( $mail_data['from']['email'], $mail_data['from']['name'] ?? '' );
			$phpmailer->addAddress( $mail_data['to']['email'], $mail_data['to']['name'] ?? '' );

			if ( ! empty( $mail_data['reply_to'] ) ) {
				$phpmailer->addReplyTo( $mail_data['reply_to'] );
			}

			$phpmailer->Subject = $mail_data['subject'];

			if ( ! empty( $mail_data['body_html'] ) ) {
				$phpmailer->isHTML( true );
				$phpmailer->Body    = $mail_data['body_html'];
				$phpmailer->AltBody = $mail_data['body_plain'] ?? '';
			} else {
				$phpmailer->Body = $mail_data['body_plain'] ?? '';
			}

			if ( ! empty( $mail_data['attachments'] ) ) {
				foreach ( $mail_data['attachments'] as $attachment ) {
					if ( file_exists( $attachment ) ) {
						$phpmailer->addAttachment( $attachment );
					}
				}
			}

			$phpmailer->send();

			return [
				'success'    => true,
				'message_id' => $phpmailer->getLastMessageID(),
			];
		} catch ( Exception $e ) {
			return [
				'success' => false,
				'error'   => 'SMTP send failed: ' . $e->getMessage(),
			];
		}
	}

	public function test_connection() {
		$auth_type = $this->get_credential( 'ses_auth_type', 'api' );
		$region    = $this->get_credential( 'region', 'us-east-1' );

		if ( 'smtp' === $auth_type ) {
			return $this->test_smtp_connection( $region );
		}

		return $this->test_api_connection( $region );
	}

	private function test_api_connection( $region ) {
		$access_key = $this->get_credential( 'ses_access_key' ) ?: $this->get_credential( 'access_key' );
		$secret_key = $this->get_credential( 'ses_secret_key' ) ?: $this->get_credential( 'secret_key' );

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing AWS IAM credentials',
			];
		}

		$host = "email.{$region}.amazonaws.com";

		$params = [
			'Action' => 'GetSendQuota',
		];

		$result = $this->make_ses_request( $host, $region, $access_key, $secret_key, $params );

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Amazon SES API connection successful',
		];
	}

	private function test_smtp_connection( $region ) {
		$username = $this->get_credential( 'ses_smtp_username' );
		$password = $this->get_credential( 'ses_smtp_password' );

		if ( empty( $username ) || empty( $password ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SMTP credentials',
			];
		}

		$host = "email-smtp.{$region}.amazonaws.com";
		$port = 587;

		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

		$phpmailer = new PHPMailer\PHPMailer\PHPMailer( true );

		try {
			$phpmailer->isSMTP();
			$phpmailer->Host       = $host;
			$phpmailer->SMTPAuth   = true;
			$phpmailer->Username   = $username;
			$phpmailer->Password   = $password;
			$phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			$phpmailer->Port       = $port;

			$phpmailer->smtpConnect();
			$phpmailer->smtpClose();

			return [
				'success' => true,
				'message' => 'Amazon SES SMTP connection successful',
			];
		} catch ( Exception $e ) {
			return [
				'success' => false,
				'error'   => 'SMTP connection failed: ' . $e->getMessage(),
			];
		}
	}

	private function make_ses_request( $host, $region, $access_key, $secret_key, $params, $debug = false ) {
		$url          = "https://{$host}/";
		$query        = $this->build_query( $params );
		$amz_datetime = gmdate( 'Ymd\THis\Z' );
		$amz_date     = gmdate( 'Ymd' );

		$debug_info   = [];
		$authorization = $this->get_auth_header_v4( $host, $region, $access_key, $secret_key, $query, $amz_datetime, $amz_date, $debug_info );

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'Host'         => $host,
					'X-Amz-Date'   => $amz_datetime,
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Authorization' => $authorization,
				],
				'body'    => $query,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
				'debug'   => $debug ? $debug_info : null,
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$xml         = simplexml_load_string( $body );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = $body;
			if ( $xml && isset( $xml->Error->Message ) ) {
				$error_message = (string) $xml->Error->Message;
			}

			return [
				'success'     => false,
				'error'       => $error_message,
				'status_code' => $status_code,
				'debug'       => $debug ? $debug_info : null,
			];
		}

		return [
			'success'     => true,
			'status_code' => $status_code,
			'response'    => $xml,
		];
	}

	private function build_query( $params ) {
		$encoded = [];
		foreach ( $params as $key => $value ) {
			$encoded[] = rawurlencode( $key ) . '=' . str_replace( '%7E', '~', rawurlencode( $value ) );
		}
		sort( $encoded, SORT_STRING );
		return implode( '&', $encoded );
	}

	private function get_auth_header_v4( $host, $region, $access_key, $secret_key, $query, $amz_datetime, $amz_date, &$debug_info = [] ) {
		$algo     = 'sha256';
		$aws_algo = 'AWS4-HMAC-SHA256';
		$service  = 'email';

		$canonical_uri         = '/';
		$canonical_querystring = '';
		$payload_data          = $query;

		// Canonical headers - only host and x-amz-date
		$canonical_headers = "host:{$host}\n" . "x-amz-date:{$amz_datetime}\n";
		$signed_headers    = 'host;x-amz-date';
		$payload_hash      = hash( $algo, $payload_data, false );

		$canonical_request = implode(
			"\n",
			[
				'POST',
				$canonical_uri,
				$canonical_querystring,
				$canonical_headers,
				$signed_headers,
				$payload_hash,
			]
		);

		$credential_scope = "{$amz_date}/{$region}/{$service}/aws4_request";
		$string_to_sign   = implode(
			"\n",
			[
				$aws_algo,
				$amz_datetime,
				$credential_scope,
				hash( $algo, $canonical_request, false ),
			]
		);

		$signing_key = $this->get_signing_key( $secret_key, $amz_date, $region, $service, $algo );
		$signature   = hash_hmac( $algo, $string_to_sign, $signing_key, false );

		$debug_info = [
			'host'              => $host,
			'region'            => $region,
			'service'           => $service,
			'amz_datetime'      => $amz_datetime,
			'amz_date'          => $amz_date,
			'query'             => $query,
			'payload_hash'      => $payload_hash,
			'canonical_request' => $canonical_request,
			'credential_scope'  => $credential_scope,
			'string_to_sign'    => $string_to_sign,
			'access_key_start'  => substr( $access_key, 0, 5 ),
			'secret_key_start'  => substr( $secret_key, 0, 5 ),
		];

		return $aws_algo . ' ' . implode(
			', ',
			[
				'Credential=' . $access_key . '/' . $credential_scope,
				'SignedHeaders=' . $signed_headers,
				'Signature=' . $signature,
			]
		);
	}

	private function get_signing_key( $key, $date_stamp, $region_name, $service_name, $algo ) {
		$k_date    = hash_hmac( $algo, $date_stamp, 'AWS4' . $key, true );
		$k_region  = hash_hmac( $algo, $region_name, $k_date, true );
		$k_service = hash_hmac( $algo, $service_name, $k_region, true );

		return hash_hmac( $algo, 'aws4_request', $k_service, true );
	}

	private function build_raw_email( $mail_data ) {
		$boundary = md5( uniqid( time() ) );

		$raw  = "From: {$mail_data['from']['name']} <{$mail_data['from']['email']}>\r\n";
		$raw .= "To: {$mail_data['to']['name']} <{$mail_data['to']['email']}>\r\n";

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$raw .= "Reply-To: {$mail_data['reply_to']}\r\n";
		}

		$raw .= "Subject: {$mail_data['subject']}\r\n";
		$raw .= "MIME-Version: 1.0\r\n";
		$raw .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$raw .= "--{$boundary}\r\n";
			$raw .= "Content-Type: text/plain; charset=UTF-8\r\n";
			$raw .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
			$raw .= $mail_data['body_plain'] . "\r\n\r\n";
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$raw .= "--{$boundary}\r\n";
			$raw .= "Content-Type: text/html; charset=UTF-8\r\n";
			$raw .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
			$raw .= $mail_data['body_html'] . "\r\n\r\n";
		}

		$raw .= "--{$boundary}--";

		return $raw;
	}
}
