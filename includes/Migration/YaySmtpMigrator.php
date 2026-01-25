<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_YaySmtpMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'yaysmtp_settings';

	protected array $provider_map = [
		'mail'       => 'default',
		'smtp'       => 'smtp',
		'sendgrid'   => 'sendgrid',
		'sendinblue' => 'brevo',
		'gmail'      => 'gmail',
		'zoho'       => 'smtp',
		'mailgun'    => 'mailgun',
		'smtpcom'    => 'smtpcom',
		'amazonses'  => 'ses',
		'postmark'   => 'postmark',
		'sparkpost'  => 'sparkpost',
		'mailjet'    => 'mailjet',
		'pepipost'   => 'pepipost',
		'sendpulse'  => 'smtp',
		'outlookms'  => 'outlook',
		'mandrill'   => 'mandrill',
	];

	public function get_name(): string {
		return 'YaySMTP';
	}

	public function get_slug(): string {
		return 'yaysmtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source ) ) {
			return [];
		}

		$mailer = $source['currentMailer'] ?? 'mail';
		$provider = $this->map_provider( $mailer );

		$settings = [
			'provider'   => $provider,
			'from_email' => $source['fromEmail'] ?? '',
			'from_name'  => $source['fromName'] ?? '',
			'force_from' => $this->normalize_bool( $source['forceFromEmail'] ?? false ),
		];

		$mailer_settings = $source[ $mailer ] ?? [];
		$settings['credentials'] = $this->map_credentials( $mailer_settings, $provider );

		return $settings;
	}

	private function map_credentials( array $mailer, string $provider ): array {
		$credentials = [];

		switch ( $provider ) {
			case 'smtp':
				$credentials = [
					'host'       => $mailer['host'] ?? '',
					'port'       => $this->normalize_port( $mailer['port'] ?? 587 ),
					'encryption' => $this->normalize_encryption( $mailer['encryption'] ?? 'tls' ),
					'username'   => $mailer['user'] ?? '',
					'password'   => $this->decrypt_yay_password( $mailer['pass'] ?? '' ),
					'auth'       => $this->normalize_bool( $mailer['auth'] ?? true ),
				];
				break;

			case 'sendgrid':
			case 'brevo':
			case 'sparkpost':
			case 'pepipost':
			case 'mandrill':
				$credentials = [
					'api_key' => $mailer['api_key'] ?? '',
				];
				break;

			case 'mailgun':
				$credentials = [
					'api_key' => $mailer['api_key'] ?? '',
					'domain'  => $mailer['domain'] ?? '',
					'region'  => ( $mailer['region'] ?? 'us' ) === 'eu' ? 'eu' : 'us',
				];
				break;

			case 'smtpcom':
				$credentials = [
					'api_key' => $mailer['api_key'] ?? '',
					'channel' => $mailer['sender'] ?? '',
				];
				break;

			case 'ses':
				$credentials = [
					'access_key' => $mailer['access_key_id'] ?? '',
					'secret_key' => $mailer['secret_access_key'] ?? '',
					'region'     => $mailer['region'] ?? 'us-east-1',
				];
				break;

			case 'postmark':
				$credentials = [
					'server_token' => $mailer['api_key'] ?? '',
				];
				break;

			case 'mailjet':
				$credentials = [
					'api_key'    => $mailer['api_key'] ?? '',
					'secret_key' => $mailer['secret_key'] ?? '',
				];
				break;

			case 'gmail':
			case 'outlook':
				$credentials = [
					'client_id'     => $mailer['client_id'] ?? '',
					'client_secret' => $mailer['client_secret'] ?? '',
				];
				break;
		}

		return $credentials;
	}

	private function decrypt_yay_password( string $encrypted ): string {
		if ( empty( $encrypted ) ) {
			return '';
		}

		$key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'yay_smtp123098';
		$decrypted = openssl_decrypt( $encrypted, 'AES-128-ECB', $key );

		return $decrypted ? $decrypted : $encrypted;
	}
}
