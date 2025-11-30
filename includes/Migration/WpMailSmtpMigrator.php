<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_WpMailSmtpMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'wp_mail_smtp';

	protected array $provider_map = [
		'mail'      => 'default',
		'smtp'      => 'smtp',
		'sendgrid'  => 'sendgrid',
		'mailgun'   => 'mailgun',
		'sendinblue' => 'brevo',
		'postmark'  => 'postmark',
		'sparkpost' => 'sparkpost',
		'smtpcom'   => 'smtpcom',
		'sendlayer' => 'smtp', // No direct mapping, fallback to SMTP
		'gmail'     => 'gmail',
		'outlook'   => 'outlook',
		'zoho'      => 'zeptomail',
		'amazonses' => 'ses',
	];

	public function get_name(): string {
		return 'WP Mail SMTP';
	}

	public function get_slug(): string {
		return 'wp-mail-smtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source['mail'] ) ) {
			return [];
		}

		$mailer = $source['mail']['mailer'] ?? 'mail';
		$provider = $this->map_provider( $mailer );

		$settings = [
			'provider'   => $provider,
			'from_email' => $source['mail']['from_email'] ?? '',
			'from_name'  => $source['mail']['from_name'] ?? '',
			'force_from' => $this->normalize_bool( $source['mail']['from_email_force'] ?? false ),
		];

		$settings['credentials'] = $this->map_credentials( $source, $mailer, $provider );

		return $settings;
	}

	/**
	 * Map WP Mail SMTP credentials to Insane Mailer format.
	 *
	 * @param array  $source   Full WP Mail SMTP settings.
	 * @param string $mailer   WP Mail SMTP mailer key.
	 * @param string $provider Insane Mailer provider key.
	 * @return array
	 */
	private function map_credentials( array $source, string $mailer, string $provider ): array {
		$credentials = [];
		$mailer_settings = $source[ $mailer ] ?? [];

		switch ( $provider ) {
			case 'smtp':
				$credentials = [
					'host'       => $mailer_settings['host'] ?? '',
					'port'       => $this->normalize_port( $mailer_settings['port'] ?? 587 ),
					'encryption' => $this->normalize_encryption( $mailer_settings['encryption'] ?? 'tls' ),
					'username'   => $mailer_settings['user'] ?? '',
					'password'   => $mailer_settings['pass'] ?? '',
					'auth'       => $this->normalize_bool( $mailer_settings['auth'] ?? true ),
				];
				break;

			case 'sendgrid':
				$credentials = [
					'api_key' => $mailer_settings['api_key'] ?? '',
				];
				break;

			case 'mailgun':
				$credentials = [
					'api_key' => $mailer_settings['api_key'] ?? '',
					'domain'  => $mailer_settings['domain'] ?? '',
					'region'  => ( $mailer_settings['region'] ?? 'us' ) === 'eu' ? 'eu' : 'us',
				];
				break;

			case 'brevo':
				$credentials = [
					'api_key' => $mailer_settings['api_key'] ?? '',
				];
				break;

			case 'postmark':
				$credentials = [
					'server_token' => $mailer_settings['server_api_token'] ?? '',
				];
				break;

			case 'sparkpost':
				$credentials = [
					'api_key' => $mailer_settings['api_key'] ?? '',
				];
				break;

			case 'smtpcom':
				$credentials = [
					'api_key' => $mailer_settings['api_key'] ?? '',
					'channel' => $mailer_settings['channel'] ?? '',
				];
				break;

			case 'ses':
				$credentials = [
					'access_key' => $mailer_settings['access_key_id'] ?? '',
					'secret_key' => $mailer_settings['secret_access_key'] ?? '',
					'region'     => $mailer_settings['region'] ?? 'us-east-1',
				];
				break;

			case 'gmail':
			case 'outlook':
				$credentials = [
					'client_id'     => $mailer_settings['client_id'] ?? '',
					'client_secret' => $mailer_settings['client_secret'] ?? '',
				];
				break;

			case 'zeptomail':
				$credentials = [
					'api_key' => $mailer_settings['send_mail_token'] ?? '',
				];
				break;
		}

		return $credentials;
	}
}
