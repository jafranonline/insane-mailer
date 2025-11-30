<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_GoSmtpMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'gosmtp_options';

	protected array $provider_map = [
		'mail'       => 'default',
		'smtp'       => 'smtp',
		'sendgrid'   => 'sendgrid',
		'mailgun'    => 'mailgun',
		'sendinblue' => 'brevo',
		'postmark'   => 'postmark',
		'sparkpost'  => 'sparkpost',
		'smtpcom'    => 'smtpcom',
		'sendlayer'  => 'smtp',
		'maileroo'   => 'smtp',
	];

	public function get_name(): string {
		return 'GoSMTP';
	}

	public function get_slug(): string {
		return 'gosmtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source ) ) {
			return [];
		}

		$mailer = $source['mailer'][0] ?? [];
		$mail_type = $mailer['mail_type'] ?? 'mail';
		$provider = $this->map_provider( $mail_type );

		$settings = [
			'provider'   => $provider,
			'from_email' => $source['from_email'] ?? '',
			'from_name'  => $source['from_name'] ?? '',
			'force_from' => $this->normalize_bool( $source['force_from_email'] ?? false ),
		];

		$settings['credentials'] = $this->map_credentials( $mailer, $provider );

		return $settings;
	}

	private function map_credentials( array $mailer, string $provider ): array {
		$credentials = [];

		switch ( $provider ) {
			case 'smtp':
				$credentials = [
					'host'       => $mailer['smtp_host'] ?? '',
					'port'       => $this->normalize_port( $mailer['smtp_port'] ?? 587 ),
					'encryption' => $this->normalize_encryption( $mailer['encryption'] ?? 'tls' ),
					'username'   => $mailer['smtp_username'] ?? '',
					'password'   => $mailer['smtp_password'] ?? '',
					'auth'       => ( $mailer['smtp_auth'] ?? 'Yes' ) === 'Yes',
				];
				break;

			case 'sendgrid':
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

			case 'brevo':
				$credentials = [
					'api_key' => $mailer['api_key'] ?? '',
				];
				break;

			case 'postmark':
				$credentials = [
					'server_token' => $mailer['api_key'] ?? '',
				];
				break;

			case 'sparkpost':
				$credentials = [
					'api_key' => $mailer['api_key'] ?? '',
				];
				break;

			case 'smtpcom':
				$credentials = [
					'api_key' => $mailer['api_key'] ?? '',
					'channel' => $mailer['channel'] ?? '',
				];
				break;
		}

		return $credentials;
	}
}
