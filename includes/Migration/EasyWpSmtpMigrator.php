<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class INSANEMAILER_EasyWpSmtpMigrator extends INSANEMAILER_Abstract_Migrator {

	protected string $option_name = 'easy_wp_smtp';

	protected array $provider_map = [
		'mail'      => 'default',
		'smtp'      => 'smtp',
		'sendgrid'  => 'sendgrid',
		'mailgun'   => 'mailgun',
		'sendinblue' => 'brevo',
		'mailjet'   => 'mailjet',
		'gmail'     => 'gmail',
		'outlook'   => 'outlook',
	];

	public function get_name(): string {
		return 'Easy WP SMTP';
	}

	public function get_slug(): string {
		return 'easy-wp-smtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source ) ) {
			return [];
		}

		$mailer = $source['mail']['mailer'] ?? 'smtp';
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
	 * Map Easy WP SMTP credentials to Insane Mailer format.
	 *
	 * @param array  $source   Full Easy WP SMTP settings.
	 * @param string $mailer   Easy WP SMTP mailer key.
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

			case 'mailjet':
				$credentials = [
					'api_key'    => $mailer_settings['api_key'] ?? '',
					'secret_key' => $mailer_settings['secret_key'] ?? '',
				];
				break;

			case 'gmail':
			case 'outlook':
				$credentials = [
					'client_id'     => $mailer_settings['client_id'] ?? '',
					'client_secret' => $mailer_settings['client_secret'] ?? '',
				];
				break;
		}

		return $credentials;
	}
}
