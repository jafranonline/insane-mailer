<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Rest/Controller.php';

class IM_Rest_Settings_Controller extends IM_Rest_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings/test-connection',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_connection' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/test-email',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_test_email' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings/regenerate-cron-token',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'regenerate_cron_token' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/server-info',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_server_info' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings/check-constants',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'check_constants' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings/connection-status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_connection_status' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);
	}

	public function get_connection_status( $request ) {
		$status = get_transient( 'im_connection_status' );

		if ( false === $status ) {
			return $this->success_response( [
				'status'    => null,
				'message'   => 'Not tested',
				'timestamp' => null,
			] );
		}

		return $this->success_response( $status );
	}

	public function get_settings( $request ) {
		$default_settings = [
			'provider'           => 'smtp',
			'credentials'        => [],
			'from_email'         => get_option( 'admin_email' ),
			'from_name'          => get_option( 'blogname' ),
			'force_from'         => true,
			'send_mode'          => 'queue',
			'auto_plain_text'    => true,
			'queue_runner'       => 'wp_cron',
			'cron_token'         => '',
			'bulk_limit'         => 50,
			'rate_limit'         => 14,
			'max_retries'        => 3,
			'retry_delay'        => 300,
			'priority_bypass'    => [],
			'auto_delete_days'   => 14,
		];

		$settings = get_option( 'im_settings', $default_settings );
		$settings = array_merge( $default_settings, $settings );


		// Mask sensitive credentials
		$sensitive_keys = [
			'password',
			'secret_key',
			'api_key',
			'server_token',
			'ses_smtp_password',
			'client_secret',
			'gmail_client_secret',
			'outlook_client_secret',
		];

		foreach ( $sensitive_keys as $key ) {
			if ( isset( $settings['credentials'][ $key ] ) && ! empty( $settings['credentials'][ $key ] ) ) {
				$settings['credentials'][ $key ] = str_repeat( '*', 8 );
			}
		}

		return $this->success_response( $settings );
	}

	public function update_settings( $request ) {
		$new_settings = $request->get_json_params();

		if ( empty( $new_settings ) ) {
			return $this->error_response( 'No settings provided' );
		}

		$current_settings = get_option( 'im_settings', [] );

		if ( isset( $new_settings['credentials'] ) ) {
			foreach ( $new_settings['credentials'] as $key => $value ) {
				if ( str_repeat( '*', 8 ) === $value ) {
					$new_settings['credentials'][ $key ] = $current_settings['credentials'][ $key ] ?? '';
				}
			}
		}

		$provider    = $new_settings['provider'] ?? 'default';
		$credentials = $new_settings['credentials'] ?? [];
		$connection_valid = true;
		$connection_error = '';

		if ( 'default' !== $provider ) {
			$credentials = $this->get_credentials_with_constants( $credentials );
			$provider_class = $this->get_provider_class( $provider );

			if ( $provider_class ) {
				try {
					$provider_instance = new $provider_class( $credentials );
					$result = $provider_instance->test_connection();

					if ( ! $result['success'] ) {
						$connection_valid = false;
						$connection_error = $result['error'] ?? 'Connection test failed';
					}
				} catch ( Exception $e ) {
					$connection_valid = false;
					$connection_error = $e->getMessage();
				}
			}
		}

		if ( ! $connection_valid ) {
			// Save failed connection status (24 hours)
			set_transient( 'im_connection_status', [
				'status'    => 'error',
				'message'   => $connection_error,
				'timestamp' => time(),
			], DAY_IN_SECONDS );
			return $this->error_response( $connection_error, 400, [ 'connection_failed' => true ] );
		}

		// Save successful connection status (24 hours)
		set_transient( 'im_connection_status', [
			'status'    => 'success',
			'message'   => 'Connected',
			'timestamp' => time(),
		], DAY_IN_SECONDS );

		update_option( 'im_settings', $new_settings );

		$this->maybe_reschedule_cron( $current_settings, $new_settings );

		return $this->success_response(
			[
				'message'          => 'Settings saved',
				'settings'         => $new_settings,
				'connection_valid' => true,
			]
		);
	}

	private function maybe_reschedule_cron( $old_settings, $new_settings ) {
		$old_interval = $old_settings['cron_interval'] ?? 'every_minute';
		$new_interval = $new_settings['cron_interval'] ?? 'every_minute';
		$old_runner   = $old_settings['queue_runner'] ?? 'wp_cron';
		$new_runner   = $new_settings['queue_runner'] ?? 'wp_cron';

		if ( $old_interval === $new_interval && $old_runner === $new_runner ) {
			return;
		}

		$timestamp = wp_next_scheduled( 'im_process_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'im_process_queue' );
		}

		if ( 'wp_cron' === $new_runner ) {
			wp_schedule_event( time(), $new_interval, 'im_process_queue' );
		}
	}

	public function test_connection( $request ) {
		$params = $request->get_json_params();

		$provider    = $params['provider'] ?? '';
		$credentials = $params['credentials'] ?? [];

		if ( empty( $provider ) ) {
			return $this->error_response( 'Provider is required' );
		}

		$provider_class = $this->get_provider_class( $provider );

		if ( ! $provider_class ) {
			return $this->error_response( 'Provider not found' );
		}

		$credentials = $this->get_credentials_with_constants( $credentials );

		try {
			$provider_instance = new $provider_class( $credentials );
			$result = $provider_instance->test_connection();

			if ( $result['success'] ) {
				set_transient( 'im_connection_status', [
					'status'    => 'success',
					'message'   => $result['message'] ?? 'Connected',
					'timestamp' => time(),
				], DAY_IN_SECONDS );
				return $this->success_response( $result );
			}

			set_transient( 'im_connection_status', [
				'status'    => 'error',
				'message'   => $result['error'] ?? 'Connection test failed',
				'timestamp' => time(),
			], DAY_IN_SECONDS );

			return $this->error_response(
				$result['error'] ?? 'Connection test failed',
				400,
				$result
			);
		} catch ( Exception $e ) {
			return $this->error_response( $e->getMessage() );
		}
	}

	public function send_test_email( $request ) {
		$params = $request->get_json_params();

		$to      = $params['to'] ?? '';
		$subject = $params['subject'] ?? 'Test Email from Easy SMTP Queue';
		$message = $params['message'] ?? 'This is a test email sent from Easy SMTP Queue plugin.';

		if ( empty( $to ) ) {
			return $this->error_response( 'Recipient email is required' );
		}

		if ( ! is_email( $to ) ) {
			return $this->error_response( 'Invalid email address' );
		}

		global $phpmailer;

		add_action(
			'wp_mail_failed',
			function ( $error ) use ( &$mail_error ) {
				$mail_error = $error->get_error_message();
			}
		);

		$sent = wp_mail( $to, $subject, $message );

		if ( $sent ) {
			return $this->success_response(
				[
					'message' => 'Test email sent successfully',
					'to'      => $to,
				]
			);
		}

		$error_msg = isset( $mail_error ) ? $mail_error : 'Failed to send test email';
		return $this->error_response( $error_msg );
	}

	public function regenerate_cron_token( $request ) {
		$token    = wp_generate_password( 32, false );
		$settings = get_option( 'im_settings', [] );

		$settings['cron_token'] = $token;
		update_option( 'im_settings', $settings );

		return $this->success_response( [ 'token' => $token ] );
	}

	public function get_server_info( $request ) {
		$disabled_functions = ini_get( 'disable_functions' );
		$mail_enabled       = function_exists( 'mail' ) && stripos( $disabled_functions, 'mail' ) === false;

		return $this->success_response( [
			'mail_enabled'   => $mail_enabled,
			'sendmail_path'  => ini_get( 'sendmail_path' ),
			'smtp_server'    => ini_get( 'SMTP' ),
			'smtp_port'      => ini_get( 'smtp_port' ),
			'php_version'    => PHP_VERSION,
		] );
	}

	private function get_credentials_with_constants( $credentials ) {
		if ( empty( $credentials['use_config'] ) ) {
			return $credentials;
		}

		$constant_map = [
			// SES
			'ses_access_key'         => 'IM_SES_ACCESS_KEY',
			'ses_secret_key'         => 'IM_SES_SECRET_KEY',
			// SendGrid
			'sendgrid_api_key'       => 'IM_SENDGRID_API_KEY',
			// Mailgun
			'mailgun_api_key'        => 'IM_MAILGUN_API_KEY',
			// Postmark
			'postmark_server_token'  => 'IM_POSTMARK_TOKEN',
			// Brevo
			'brevo_api_key'          => 'IM_BREVO_API_KEY',
			// SparkPost
			'sparkpost_api_key'      => 'IM_SPARKPOST_API_KEY',
			// Mailjet
			'mailjet_api_key'        => 'IM_MAILJET_API_KEY',
			'mailjet_secret_key'     => 'IM_MAILJET_SECRET_KEY',
			// Elastic Email
			'elasticemail_api_key'   => 'IM_ELASTICEMAIL_API_KEY',
			// SMTP.com
			'smtpcom_api_key'        => 'IM_SMTPCOM_API_KEY',
			// Netcore/Pepipost
			'pepipost_api_key'       => 'IM_PEPIPOST_API_KEY',
			// Resend
			'resend_api_key'         => 'IM_RESEND_API_KEY',
			// MailerSend
			'mailersend_api_key'     => 'IM_MAILERSEND_API_KEY',
			// Mailtrap
			'mailtrap_api_key'       => 'IM_MAILTRAP_API_KEY',
			// Loops
			'loops_api_key'          => 'IM_LOOPS_API_KEY',
			// Mandrill
			'mandrill_api_key'       => 'IM_MANDRILL_API_KEY',
			// SMTP2GO
			'smtp2go_api_key'        => 'IM_SMTP2GO_API_KEY',
			// SocketLabs
			'socketlabs_server_id'   => 'IM_SOCKETLABS_SERVER_ID',
			'socketlabs_api_key'     => 'IM_SOCKETLABS_API_KEY',
			// ZeptoMail
			'zeptomail_api_key'      => 'IM_ZEPTOMAIL_TOKEN',
			// Gmail
			'gmail_client_id'        => 'IM_GMAIL_CLIENT_ID',
			'gmail_client_secret'    => 'IM_GMAIL_CLIENT_SECRET',
			// Outlook
			'outlook_client_id'      => 'IM_OUTLOOK_CLIENT_ID',
			'outlook_client_secret'  => 'IM_OUTLOOK_CLIENT_SECRET',
			// Custom SMTP
			'smtp_username'          => 'IM_SMTP_USERNAME',
			'smtp_password'          => 'IM_SMTP_PASSWORD',
		];

		foreach ( $constant_map as $field => $constant ) {
			if ( defined( $constant ) ) {
				$credentials[ $field ] = constant( $constant );
			}
		}

		return $credentials;
	}

	private function get_provider_class( $provider_name ) {
		$providers = [
			'ses'          => 'IM_Provider_SES',
			'mailgun'      => 'IM_Provider_Mailgun',
			'sendgrid'     => 'IM_Provider_SendGrid',
			'brevo'        => 'IM_Provider_Brevo',
			'sparkpost'    => 'IM_Provider_SparkPost',
			'pepipost'     => 'IM_Provider_Netcore',
			'postmark'     => 'IM_Provider_Postmark',
			'elasticemail' => 'IM_Provider_ElasticEmail',
			'smtpcom'      => 'IM_Provider_SmtpCom',
			'mailjet'      => 'IM_Provider_Mailjet',
			'resend'       => 'IM_Provider_Resend',
			'mailersend'   => 'IM_Provider_MailerSend',
			'mailtrap'     => 'IM_Provider_Mailtrap',
			'loops'        => 'IM_Provider_Loops',
			'mandrill'     => 'IM_Provider_Mandrill',
			'smtp2go'      => 'IM_Provider_Smtp2go',
			'socketlabs'   => 'IM_Provider_SocketLabs',
			'zeptomail'    => 'IM_Provider_ZeptoMail',
			'gmail'        => 'IM_Provider_Gmail',
			'outlook'      => 'IM_Provider_Outlook',
			'smtp'         => 'IM_Provider_SMTP',
		];

		if ( ! isset( $providers[ $provider_name ] ) ) {
			return null;
		}

		$class_name = $providers[ $provider_name ];
		$file_path  = IM_PLUGIN_DIR . 'includes/Providers/' . str_replace( 'IM_Provider_', '', $class_name ) . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}

		return class_exists( $class_name ) ? $class_name : null;
	}

	public function check_constants( $request ) {
		$constant_map = [
			// SES
			'ses_access_key'         => 'IM_SES_ACCESS_KEY',
			'ses_secret_key'         => 'IM_SES_SECRET_KEY',
			// SendGrid
			'sendgrid_api_key'       => 'IM_SENDGRID_API_KEY',
			// Mailgun
			'mailgun_api_key'        => 'IM_MAILGUN_API_KEY',
			// Postmark
			'postmark_server_token'  => 'IM_POSTMARK_TOKEN',
			// Brevo
			'brevo_api_key'          => 'IM_BREVO_API_KEY',
			// SparkPost
			'sparkpost_api_key'      => 'IM_SPARKPOST_API_KEY',
			// Mailjet
			'mailjet_api_key'        => 'IM_MAILJET_API_KEY',
			'mailjet_secret_key'     => 'IM_MAILJET_SECRET_KEY',
			// Elastic Email
			'elasticemail_api_key'   => 'IM_ELASTICEMAIL_API_KEY',
			// SMTP.com
			'smtpcom_api_key'        => 'IM_SMTPCOM_API_KEY',
			// Netcore/Pepipost
			'pepipost_api_key'       => 'IM_PEPIPOST_API_KEY',
			// Resend
			'resend_api_key'         => 'IM_RESEND_API_KEY',
			// MailerSend
			'mailersend_api_key'     => 'IM_MAILERSEND_API_KEY',
			// Mailtrap
			'mailtrap_api_key'       => 'IM_MAILTRAP_API_KEY',
			// Loops
			'loops_api_key'          => 'IM_LOOPS_API_KEY',
			// Mandrill
			'mandrill_api_key'       => 'IM_MANDRILL_API_KEY',
			// SMTP2GO
			'smtp2go_api_key'        => 'IM_SMTP2GO_API_KEY',
			// SocketLabs
			'socketlabs_server_id'   => 'IM_SOCKETLABS_SERVER_ID',
			'socketlabs_api_key'     => 'IM_SOCKETLABS_API_KEY',
			// ZeptoMail
			'zeptomail_api_key'      => 'IM_ZEPTOMAIL_TOKEN',
			// Gmail
			'gmail_client_id'        => 'IM_GMAIL_CLIENT_ID',
			'gmail_client_secret'    => 'IM_GMAIL_CLIENT_SECRET',
			// Outlook
			'outlook_client_id'      => 'IM_OUTLOOK_CLIENT_ID',
			'outlook_client_secret'  => 'IM_OUTLOOK_CLIENT_SECRET',
			// Custom SMTP
			'smtp_username'          => 'IM_SMTP_USERNAME',
			'smtp_password'          => 'IM_SMTP_PASSWORD',
		];

		$defined = [];
		foreach ( $constant_map as $field => $constant ) {
			$defined[ $field ] = defined( $constant ) && ! empty( constant( $constant ) );
		}

		return rest_ensure_response( [ 'constants' => $defined ] );
	}
}
