<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Rest/Controller.php';

class INSANEMAILER_Rest_Settings_Controller extends INSANEMAILER_Rest_Controller {

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

		register_rest_route(
			$this->namespace,
			'/settings/export',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export_settings' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings/import',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'import_settings' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings/reset',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reset_settings' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);
	}

	public function get_connection_status( $request ) {
		$status = get_transient( 'insanemailer_connection_status' );

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
			'send_mode'          => 'direct',
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

		$settings = get_option( 'insanemailer_settings', $default_settings );
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
			'cloudflare_api_token',
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

		$current_settings = get_option( 'insanemailer_settings', [] );

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
			set_transient( 'insanemailer_connection_status', [
				'status'    => 'error',
				'message'   => $connection_error,
				'timestamp' => time(),
			], DAY_IN_SECONDS );
			return $this->error_response( $connection_error, 400, [ 'connection_failed' => true ] );
		}

		// Save successful connection status (24 hours)
		set_transient( 'insanemailer_connection_status', [
			'status'    => 'success',
			'message'   => 'Connected',
			'timestamp' => time(),
		], DAY_IN_SECONDS );

		update_option( 'insanemailer_settings', $new_settings );

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

		$timestamp = wp_next_scheduled( 'insanemailer_process_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'insanemailer_process_queue' );
		}

		if ( 'wp_cron' === $new_runner ) {
			wp_schedule_event( time(), $new_interval, 'insanemailer_process_queue' );
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
				set_transient( 'insanemailer_connection_status', [
					'status'    => 'success',
					'message'   => $result['message'] ?? 'Connected',
					'timestamp' => time(),
				], DAY_IN_SECONDS );
				return $this->success_response( $result );
			}

			set_transient( 'insanemailer_connection_status', [
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

		$to = $params['to'] ?? '';

		if ( empty( $to ) ) {
			return $this->error_response( 'Recipient email is required' );
		}

		if ( ! is_email( $to ) ) {
			return $this->error_response( 'Invalid email address' );
		}

		$settings  = get_option( 'insanemailer_settings', [] );
		$provider  = $settings['provider'] ?? 'default';
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$timestamp = wp_date( 'F j, Y \a\t g:i A' );

		$provider_names = [
			'default'      => 'PHP Mail',
			'smtp'         => 'Custom SMTP',
			'ses'          => 'Amazon SES',
			'sendgrid'     => 'SendGrid',
			'mailgun'      => 'Mailgun',
			'postmark'     => 'Postmark',
			'brevo'        => 'Brevo',
			'sparkpost'    => 'SparkPost',
			'mailjet'      => 'Mailjet',
			'elasticemail' => 'Elastic Email',
			'smtpcom'      => 'SMTP.com',
			'pepipost'     => 'Netcore',
			'resend'       => 'Resend',
			'cloudflare'   => 'Cloudflare Email Service',
			'mailersend'   => 'MailerSend',
			'mailtrap'     => 'Mailtrap',
			'loops'        => 'Loops',
			'mandrill'     => 'Mandrill',
			'smtp2go'      => 'SMTP2GO',
			'socketlabs'   => 'SocketLabs',
			'zeptomail'    => 'ZeptoMail',
			'gmail'        => 'Gmail',
			'outlook'      => 'Outlook',
		];

		$provider_name = $provider_names[ $provider ] ?? ucfirst( $provider );

		$subject = 'Test Email from Insane Mailer';

		$message = '<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f5;">
		<tr>
			<td align="center" style="padding: 40px 20px;">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 500px;">
					<!-- Main Card -->
					<tr>
						<td style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
							<!-- Header -->
							<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
								<tr>
									<td style="padding: 32px 32px 24px; text-align: center; border-bottom: 1px solid #e4e4e7;">
										<div style="display: inline-block; background-color: #18181b; color: #ffffff; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; padding: 6px 12px; border-radius: 20px; text-transform: uppercase;">Insane Mailer</div>
									</td>
								</tr>
							</table>
							<!-- Content -->
							<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
								<tr>
									<td style="padding: 32px;">
										<!-- Success Icon -->
										<div style="text-align: center; margin-bottom: 24px;">
											<div style="display: inline-block; width: 56px; height: 56px; background-color: #dcfce7; border-radius: 50%; line-height: 56px;">
												<span style="color: #16a34a; font-size: 28px;">&#10003;</span>
											</div>
										</div>
										<!-- Title -->
										<h1 style="margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #18181b; text-align: center;">Email Configured Successfully</h1>
										<p style="margin: 0 0 28px; font-size: 14px; color: #71717a; text-align: center; line-height: 1.5;">Your email configuration is working correctly.</p>
										<!-- Details -->
										<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #fafafa; border-radius: 8px;">
											<tr>
												<td style="padding: 16px;">
													<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
														<tr>
															<td style="padding: 8px 0; border-bottom: 1px solid #e4e4e7;">
																<span style="font-size: 12px; color: #71717a;">Provider</span><br>
																<span style="font-size: 14px; color: #18181b; font-weight: 500;">' . esc_html( $provider_name ) . '</span>
															</td>
														</tr>
														<tr>
															<td style="padding: 8px 0; border-bottom: 1px solid #e4e4e7;">
																<span style="font-size: 12px; color: #71717a;">Website</span><br>
																<span style="font-size: 14px; color: #18181b; font-weight: 500;">' . esc_html( $site_name ) . '</span>
															</td>
														</tr>
														<tr>
															<td style="padding: 8px 0;">
																<span style="font-size: 12px; color: #71717a;">Sent at</span><br>
																<span style="font-size: 14px; color: #18181b; font-weight: 500;">' . esc_html( $timestamp ) . '</span>
															</td>
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="padding: 24px; text-align: center;">
							<p style="margin: 0; font-size: 12px; color: #a1a1aa;">Sent from <a href="' . esc_url( $site_url ) . '" style="color: #71717a;">' . esc_html( $site_name ) . '</a></p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		add_action(
			'wp_mail_failed',
			function ( $error ) use ( &$mail_error ) {
				$mail_error = $error->get_error_message();
			}
		);

		$sent = wp_mail( $to, $subject, $message, $headers );

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
		$settings = get_option( 'insanemailer_settings', [] );

		$settings['cron_token'] = $token;
		update_option( 'insanemailer_settings', $settings );

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
			'ses_access_key'         => 'INSANEMAILER_SES_ACCESS_KEY',
			'ses_secret_key'         => 'INSANEMAILER_SES_SECRET_KEY',
			// SendGrid
			'sendgrid_api_key'       => 'INSANEMAILER_SENDGRID_API_KEY',
			// Mailgun
			'mailgun_api_key'        => 'INSANEMAILER_MAILGUN_API_KEY',
			// Postmark
			'postmark_server_token'  => 'INSANEMAILER_POSTMARK_TOKEN',
			// Brevo
			'brevo_api_key'          => 'INSANEMAILER_BREVO_API_KEY',
			// SparkPost
			'sparkpost_api_key'      => 'INSANEMAILER_SPARKPOST_API_KEY',
			// Mailjet
			'mailjet_api_key'        => 'INSANEMAILER_MAILJET_API_KEY',
			'mailjet_secret_key'     => 'INSANEMAILER_MAILJET_SECRET_KEY',
			// Elastic Email
			'elasticemail_api_key'   => 'INSANEMAILER_ELASTICEMAIL_API_KEY',
			// SMTP.com
			'smtpcom_api_key'        => 'INSANEMAILER_SMTPCOM_API_KEY',
			// Netcore/Pepipost
			'pepipost_api_key'       => 'INSANEMAILER_PEPIPOST_API_KEY',
			// Cloudflare
			'cloudflare_api_token'   => 'INSANEMAILER_CLOUDFLARE_API_TOKEN',
			'cloudflare_account_id'  => 'INSANEMAILER_CLOUDFLARE_ACCOUNT_ID',
			// Resend
			'resend_api_key'         => 'INSANEMAILER_RESEND_API_KEY',
			// MailerSend
			'mailersend_api_key'     => 'INSANEMAILER_MAILERSEND_API_KEY',
			// Mailtrap
			'mailtrap_api_key'       => 'INSANEMAILER_MAILTRAP_API_KEY',
			// Loops
			'loops_api_key'          => 'INSANEMAILER_LOOPS_API_KEY',
			// Mandrill
			'mandrill_api_key'       => 'INSANEMAILER_MANDRILL_API_KEY',
			// SMTP2GO
			'smtp2go_api_key'        => 'INSANEMAILER_SMTP2GO_API_KEY',
			// SocketLabs
			'socketlabs_server_id'   => 'INSANEMAILER_SOCKETLABS_SERVER_ID',
			'socketlabs_api_key'     => 'INSANEMAILER_SOCKETLABS_API_KEY',
			// ZeptoMail
			'zeptomail_api_key'      => 'INSANEMAILER_ZEPTOMAIL_TOKEN',
			// Gmail
			'gmail_client_id'        => 'INSANEMAILER_GMAIL_CLIENT_ID',
			'gmail_client_secret'    => 'INSANEMAILER_GMAIL_CLIENT_SECRET',
			// Outlook
			'outlook_client_id'      => 'INSANEMAILER_OUTLOOK_CLIENT_ID',
			'outlook_client_secret'  => 'INSANEMAILER_OUTLOOK_CLIENT_SECRET',
			// Custom SMTP
			'smtp_username'          => 'INSANEMAILER_SMTP_USERNAME',
			'smtp_password'          => 'INSANEMAILER_SMTP_PASSWORD',
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
			'ses'          => 'INSANEMAILER_Provider_SES',
			'mailgun'      => 'INSANEMAILER_Provider_Mailgun',
			'sendgrid'     => 'INSANEMAILER_Provider_SendGrid',
			'brevo'        => 'INSANEMAILER_Provider_Brevo',
			'sparkpost'    => 'INSANEMAILER_Provider_SparkPost',
			'pepipost'     => 'INSANEMAILER_Provider_Netcore',
			'postmark'     => 'INSANEMAILER_Provider_Postmark',
			'elasticemail' => 'INSANEMAILER_Provider_ElasticEmail',
			'smtpcom'      => 'INSANEMAILER_Provider_SmtpCom',
			'mailjet'      => 'INSANEMAILER_Provider_Mailjet',
			'resend'       => 'INSANEMAILER_Provider_Resend',
			'mailersend'   => 'INSANEMAILER_Provider_MailerSend',
			'mailtrap'     => 'INSANEMAILER_Provider_Mailtrap',
			'loops'        => 'INSANEMAILER_Provider_Loops',
			'mandrill'     => 'INSANEMAILER_Provider_Mandrill',
			'smtp2go'      => 'INSANEMAILER_Provider_Smtp2go',
			'socketlabs'   => 'INSANEMAILER_Provider_SocketLabs',
			'zeptomail'    => 'INSANEMAILER_Provider_ZeptoMail',
			'gmail'        => 'INSANEMAILER_Provider_Gmail',
			'outlook'      => 'INSANEMAILER_Provider_Outlook',
			'smtp'         => 'INSANEMAILER_Provider_SMTP',
			'cloudflare'   => 'INSANEMAILER_Provider_Cloudflare',
		];

		if ( ! isset( $providers[ $provider_name ] ) ) {
			return null;
		}

		$class_name = $providers[ $provider_name ];
		$file_path  = INSANEMAILER_PLUGIN_DIR . 'includes/Providers/' . str_replace( 'INSANEMAILER_Provider_', '', $class_name ) . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}

		return class_exists( $class_name ) ? $class_name : null;
	}

	public function export_settings( $request ) {
		$settings = get_option( 'insanemailer_settings', [] );

		return $this->success_response( [
			'settings' => $settings,
			'exported' => gmdate( 'Y-m-d H:i:s' ),
			'version'  => INSANEMAILER_VERSION,
		] );
	}

	public function import_settings( $request ) {
		$params = $request->get_json_params();

		if ( empty( $params['settings'] ) || ! is_array( $params['settings'] ) ) {
			return $this->error_response( 'Invalid settings data' );
		}

		$imported = $params['settings'];

		$allowed_keys = [
			'provider',
			'credentials',
			'from_email',
			'from_name',
			'force_from',
			'send_mode',
			'auto_plain_text',
			'queue_runner',
			'cron_token',
			'cron_interval',
			'bulk_limit',
			'rate_limit',
			'max_retries',
			'retry_delay',
			'priority_bypass',
			'auto_delete_days',
			'pause_sending',
			'priority_express',
		];

		$sanitized = [];
		foreach ( $allowed_keys as $key ) {
			if ( isset( $imported[ $key ] ) ) {
				$sanitized[ $key ] = $imported[ $key ];
			}
		}

		if ( empty( $sanitized ) ) {
			return $this->error_response( 'No valid settings found in import data' );
		}

		$current = get_option( 'insanemailer_settings', [] );
		$merged  = array_merge( $current, $sanitized );

		update_option( 'insanemailer_settings', $merged );
		delete_transient( 'insanemailer_connection_status' );

		return $this->success_response( [
			'message'  => 'Settings imported successfully',
			'settings' => $merged,
		] );
	}

	public function reset_settings( $request ) {
		$defaults = [
			'provider'           => 'smtp',
			'credentials'        => [],
			'from_email'         => get_option( 'admin_email' ),
			'from_name'          => get_option( 'blogname' ),
			'force_from'         => true,
			'send_mode'          => 'direct',
			'auto_plain_text'    => true,
			'queue_runner'       => 'wp_cron',
			'cron_token'         => '',
			'cron_interval'      => 'every_minute',
			'bulk_limit'         => 50,
			'rate_limit'         => 14,
			'max_retries'        => 3,
			'retry_delay'        => 300,
			'priority_bypass'    => [],
			'auto_delete_days'   => 14,
			'pause_sending'      => false,
			'priority_express'   => false,
		];

		update_option( 'insanemailer_settings', $defaults );
		delete_transient( 'insanemailer_connection_status' );

		return $this->success_response( [
			'message'  => 'Settings reset to defaults',
			'settings' => $defaults,
		] );
	}

	public function check_constants( $request ) {
		$constant_map = [
			// SES
			'ses_access_key'         => 'INSANEMAILER_SES_ACCESS_KEY',
			'ses_secret_key'         => 'INSANEMAILER_SES_SECRET_KEY',
			// SendGrid
			'sendgrid_api_key'       => 'INSANEMAILER_SENDGRID_API_KEY',
			// Mailgun
			'mailgun_api_key'        => 'INSANEMAILER_MAILGUN_API_KEY',
			// Postmark
			'postmark_server_token'  => 'INSANEMAILER_POSTMARK_TOKEN',
			// Brevo
			'brevo_api_key'          => 'INSANEMAILER_BREVO_API_KEY',
			// SparkPost
			'sparkpost_api_key'      => 'INSANEMAILER_SPARKPOST_API_KEY',
			// Mailjet
			'mailjet_api_key'        => 'INSANEMAILER_MAILJET_API_KEY',
			'mailjet_secret_key'     => 'INSANEMAILER_MAILJET_SECRET_KEY',
			// Elastic Email
			'elasticemail_api_key'   => 'INSANEMAILER_ELASTICEMAIL_API_KEY',
			// SMTP.com
			'smtpcom_api_key'        => 'INSANEMAILER_SMTPCOM_API_KEY',
			// Netcore/Pepipost
			'pepipost_api_key'       => 'INSANEMAILER_PEPIPOST_API_KEY',
			// Cloudflare
			'cloudflare_api_token'   => 'INSANEMAILER_CLOUDFLARE_API_TOKEN',
			'cloudflare_account_id'  => 'INSANEMAILER_CLOUDFLARE_ACCOUNT_ID',
			// Resend
			'resend_api_key'         => 'INSANEMAILER_RESEND_API_KEY',
			// MailerSend
			'mailersend_api_key'     => 'INSANEMAILER_MAILERSEND_API_KEY',
			// Mailtrap
			'mailtrap_api_key'       => 'INSANEMAILER_MAILTRAP_API_KEY',
			// Loops
			'loops_api_key'          => 'INSANEMAILER_LOOPS_API_KEY',
			// Mandrill
			'mandrill_api_key'       => 'INSANEMAILER_MANDRILL_API_KEY',
			// SMTP2GO
			'smtp2go_api_key'        => 'INSANEMAILER_SMTP2GO_API_KEY',
			// SocketLabs
			'socketlabs_server_id'   => 'INSANEMAILER_SOCKETLABS_SERVER_ID',
			'socketlabs_api_key'     => 'INSANEMAILER_SOCKETLABS_API_KEY',
			// ZeptoMail
			'zeptomail_api_key'      => 'INSANEMAILER_ZEPTOMAIL_TOKEN',
			// Gmail
			'gmail_client_id'        => 'INSANEMAILER_GMAIL_CLIENT_ID',
			'gmail_client_secret'    => 'INSANEMAILER_GMAIL_CLIENT_SECRET',
			// Outlook
			'outlook_client_id'      => 'INSANEMAILER_OUTLOOK_CLIENT_ID',
			'outlook_client_secret'  => 'INSANEMAILER_OUTLOOK_CLIENT_SECRET',
			// Custom SMTP
			'smtp_username'          => 'INSANEMAILER_SMTP_USERNAME',
			'smtp_password'          => 'INSANEMAILER_SMTP_PASSWORD',
		];

		$defined = [];
		foreach ( $constant_map as $field => $constant ) {
			$defined[ $field ] = defined( $constant ) && ! empty( constant( $constant ) );
		}

		return rest_ensure_response( [ 'constants' => $defined ] );
	}
}
