<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Mailer {

	private static $instance = null;
	private $settings;
	private $is_queued = false;
	private $current_direct_email_id = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = get_option( 'insanemailer_settings', [] );
		add_action( 'phpmailer_init', [ $this, 'intercept_phpmailer' ], 999 );
		add_filter( 'pre_wp_mail', [ $this, 'maybe_queue_mail' ], 10, 2 );
		add_filter( 'wp_mail_from', [ $this, 'filter_from_email' ], 99 );
		add_filter( 'wp_mail_from_name', [ $this, 'filter_from_name' ], 99 );
	}

	/**
	 * Apply the configured sender before PHPMailer validates it.
	 *
	 * WordPress calls setFrom() before phpmailer_init fires, so overriding the
	 * sender only in send_direct() is too late: the default wordpress@<host> is
	 * rejected outright on hosts without a dot (localhost), failing the send
	 * before any provider is reached.
	 */
	public function filter_from_email( $from_email ) {
		$configured = $this->settings['from_email'] ?? '';

		if ( empty( $configured ) || ! is_email( $configured ) ) {
			return $from_email;
		}

		if ( ! empty( $this->settings['force_from'] ) || $this->is_default_from_email( $from_email ) ) {
			return $configured;
		}

		return $from_email;
	}

	public function filter_from_name( $from_name ) {
		$configured = $this->settings['from_name'] ?? '';

		if ( empty( $configured ) ) {
			return $from_name;
		}

		if ( ! empty( $this->settings['force_from'] ) || 'WordPress' === $from_name ) {
			return $configured;
		}

		return $from_name;
	}

	/**
	 * Whether the address is the wordpress@<host> fallback wp_mail() builds when
	 * no From header was supplied, rather than a caller-provided sender.
	 */
	private function is_default_from_email( $from_email ) {
		$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );
		$default  = 'wordpress@';

		if ( null !== $sitename ) {
			if ( 0 === strpos( $sitename, 'www.' ) ) {
				$sitename = substr( $sitename, 4 );
			}

			$default .= $sitename;
		}

		return $from_email === $default;
	}

	public function maybe_queue_mail( $null, $atts ) {
		$send_mode = $this->settings['send_mode'] ?? 'direct';

		// If direct mode, log the email before sending
		if ( 'direct' === $send_mode || $this->should_bypass_queue() ) {
			$mail_data = $this->log_direct_email( $atts );

			$provider_name = $this->settings['provider'] ?? 'default';

			/*
			 * API providers deliver over HTTP, so PHPMailer has no part to play.
			 * Letting wp_mail() continue would attempt a second delivery over the
			 * placeholder localhost:25 transport, and its failure would make
			 * wp_mail() return false for a message the provider already accepted.
			 */
			if ( 'default' !== $provider_name && 'smtp' !== $provider_name ) {
				return $this->send_direct_via_api( $mail_data );
			}

			return null;
		}

		// Queue mode: queue the email and return true to short-circuit wp_mail
		$this->queue_from_atts( $atts );
		return true;
	}

	/**
	 * Deliver a direct-mode email through the configured API provider.
	 *
	 * @param array $mail_data Parsed message, as returned by log_direct_email().
	 * @return bool Whether the provider accepted the message, for pre_wp_mail.
	 */
	private function send_direct_via_api( $mail_data ) {
		$provider_name  = $this->settings['provider'] ?? 'default';
		$provider_class = $this->get_provider_class( $provider_name );

		if ( ! $provider_class ) {
			return $this->fail_direct_email( 'Provider class not found' );
		}

		try {
			$provider = new $provider_class( $this->settings['credentials'] ?? [] );
			$result   = $provider->send_raw( $mail_data );

			if ( empty( $result['success'] ) ) {
				return $this->fail_direct_email( $result['error'] ?? 'Unknown error' );
			}

			$this->record_direct_email_result( $result );

			do_action( 'insanemailer_email_sent', $this->current_direct_email_id, $result );

			return true;
		} catch ( Exception $e ) {
			return $this->fail_direct_email( $e->getMessage() );
		}
	}

	/**
	 * Record a direct-send failure and mirror it onto wp_mail_failed, which
	 * callers rely on for the reason a send did not go through.
	 */
	private function fail_direct_email( $error ) {
		$this->update_direct_email_status( 'failed', $error );

		do_action( 'insanemailer_email_failed', $this->current_direct_email_id, $error );

		do_action(
			'wp_mail_failed',
			new WP_Error( 'wp_mail_failed', $error )
		);

		return false;
	}

	public function intercept_phpmailer( $phpmailer ) {
		// Only apply from settings in direct mode
		$this->send_direct( $phpmailer );
	}

	private function should_bypass_queue() {
		$priority_bypass = $this->settings['priority_bypass'] ?? [];

		if ( empty( $priority_bypass ) ) {
			return false;
		}

		global $wp_current_filter;

		foreach ( $priority_bypass as $hook ) {
			if ( in_array( $hook, (array) $wp_current_filter, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function queue_from_atts( $atts ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$to          = $atts['to'] ?? '';
		$subject     = $atts['subject'] ?? '';
		$message     = $atts['message'] ?? '';
		$headers     = $atts['headers'] ?? [];
		$attachments = $atts['attachments'] ?? [];

		// Parse to address
		$to_email = '';
		$to_name  = '';
		if ( is_array( $to ) ) {
			$to_email = $to[0] ?? '';
		} else {
			// Parse "Name <email>" format
			if ( preg_match( '/^(.+)\s*<(.+)>$/', $to, $matches ) ) {
				$to_name  = trim( $matches[1] );
				$to_email = trim( $matches[2] );
			} else {
				$to_email = $to;
			}
		}

		// Parse headers
		$parsed_headers = [];
		$from_email     = $this->settings['from_email'] ?? get_option( 'admin_email' );
		$from_name      = $this->settings['from_name'] ?? get_option( 'blogname' );
		$reply_to       = null;
		$content_type   = 'text/plain';

		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		foreach ( $headers as $header ) {
			if ( empty( $header ) ) {
				continue;
			}

			if ( is_array( $header ) ) {
				$key   = $header[0] ?? '';
				$value = $header[1] ?? '';
			} else {
				list( $key, $value ) = array_pad( explode( ':', $header, 2 ), 2, '' ); // phpcs:ignore Universal.Lists.DisallowLongListSyntax.Found
			}

			$key   = trim( $key );
			$value = trim( $value );

			if ( strcasecmp( $key, 'From' ) === 0 && empty( $this->settings['force_from'] ) ) {
				if ( preg_match( '/^(.+)\s*<(.+)>$/', $value, $matches ) ) {
					$from_name  = trim( $matches[1] );
					$from_email = trim( $matches[2] );
				} else {
					$from_email = $value;
				}
			} elseif ( strcasecmp( $key, 'Reply-To' ) === 0 ) {
				$reply_to = $value;
			} elseif ( strcasecmp( $key, 'Content-Type' ) === 0 ) {
				$content_type = $value;
			} else {
				$parsed_headers[ $key ] = $value;
			}
		}

		$is_html    = stripos( $content_type, 'text/html' ) !== false;
		$body_html  = $is_html ? $message : '';
		$body_plain = $is_html ? '' : $message;

		if ( $is_html && ! empty( $this->settings['auto_plain_text'] ) ) {
			$body_plain = $this->html_to_plain( $message );
		}

		$priority = 2;
		$priority = apply_filters( 'insanemailer_priority', $priority, $to_email, $atts );

		$status = ! empty( $this->settings['pause_sending'] ) ? 'paused' : 'pending';

		$data = [
			'status'       => $status,
			'send_mode'    => 'queue',
			'priority'     => $priority,
			'to_email'     => $to_email,
			'to_name'      => $to_name,
			'from_email'   => $from_email,
			'from_name'    => $from_name,
			'reply_to'     => $reply_to,
			'subject'      => $subject,
			'body_html'    => $body_html,
			'body_plain'   => $body_plain,
			'headers'      => wp_json_encode( $parsed_headers ),
			'attachments'  => wp_json_encode( [] ),
			'attempts'     => 0,
			'max_attempts' => $this->settings['max_retries'] ?? 3,
			'scheduled_at' => current_time( 'mysql' ),
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for email queue.
		$inserted = $wpdb->insert( $table_name, $data );

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		if ( $inserted ) {
			$email_id = $wpdb->insert_id;

			if ( ! empty( $attachments ) ) {
				$this->handle_attachments( $email_id, (array) $attachments );
			}

			do_action( 'insanemailer_email_queued', $email_id, $data );
		}
	}

	private function send_direct( $phpmailer ) {
		if ( ! empty( $this->settings['force_from'] ) ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer properties.
			$phpmailer->From     = $this->settings['from_email'] ?? $phpmailer->From;
			$phpmailer->FromName = $this->settings['from_name'] ?? $phpmailer->FromName;
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$provider = $this->settings['provider'] ?? 'default';

		// Default provider uses WordPress's native wp_mail without modification
		if ( 'default' === $provider || 'smtp' === $provider ) {
			return;
		}

		$phpmailer->isSMTP();
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer properties.
		$phpmailer->Host       = 'localhost';
		$phpmailer->SMTPAuth   = false;
		$phpmailer->SMTPSecure = '';
		$phpmailer->Port       = 25;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		add_action( 'phpmailer_init', [ $this, 'send_via_api' ], 1000 );
	}

	public function send_via_api( $phpmailer ) {
		$provider_name = $this->settings['provider'] ?? 'default';

		// Default and SMTP providers don't use API
		if ( 'default' === $provider_name || 'smtp' === $provider_name ) {
			return;
		}

		$provider_class = $this->get_provider_class( $provider_name );

		if ( ! $provider_class ) {
			$this->update_direct_email_status( 'failed', 'Provider class not found' );
			return;
		}

		try {
			$provider = new $provider_class( $this->settings['credentials'] ?? [] );

			// Prepare mail_data from PHPMailer object.
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer properties.
			$to_email = '';
			$to_name  = '';
			if ( ! empty( $phpmailer->getToAddresses() ) ) {
				$to_address = $phpmailer->getToAddresses()[0];
				$to_email   = $to_address[0] ?? '';
				$to_name    = $to_address[1] ?? '';
			}

			$mail_data = [
				'to'          => [
					'email' => $to_email,
					'name'  => $to_name,
				],
				'from'        => [
					'email' => $phpmailer->From,
					'name'  => $phpmailer->FromName,
				],
				'reply_to'    => ! empty( $phpmailer->getReplyToAddresses() ) ? array_key_first( $phpmailer->getReplyToAddresses() ) : '',
				'subject'     => $phpmailer->Subject,
				'body_html'   => $phpmailer->Body,
				'body_plain'  => $phpmailer->AltBody,
				'headers'     => [],
				'attachments' => $phpmailer->getAttachments(),
			];
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$result = $provider->send_raw( $mail_data );

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] ?? 'Unknown error' );
			}

			$this->record_direct_email_result( $result );

			do_action( 'insanemailer_email_sent', $this->current_direct_email_id, $result );
		} catch ( Exception $e ) {
			$this->update_direct_email_status( 'failed', $e->getMessage() );
			do_action( 'insanemailer_email_failed', $this->current_direct_email_id, $e->getMessage() );
		}
	}

	private function get_provider_class( $provider_name ) {
		$providers = [
			'ses'          => 'INSANEMAILER_Provider_SES',
			'mailgun'      => 'INSANEMAILER_Provider_Mailgun',
			'sendgrid'     => 'INSANEMAILER_Provider_SendGrid',
			'brevo'        => 'INSANEMAILER_Provider_Brevo',
			'sparkpost'    => 'INSANEMAILER_Provider_SparkPost',
			'netcore'      => 'INSANEMAILER_Provider_Netcore',
			'postmark'     => 'INSANEMAILER_Provider_Postmark',
			'elasticemail' => 'INSANEMAILER_Provider_ElasticEmail',
			'smtpcom'      => 'INSANEMAILER_Provider_SmtpCom',
			'smtp'         => 'INSANEMAILER_Provider_SMTP',
			'gmail'        => 'INSANEMAILER_Provider_Gmail',
			'outlook'      => 'INSANEMAILER_Provider_Outlook',
			'socketlabs'   => 'INSANEMAILER_Provider_SocketLabs',
			'mandrill'     => 'INSANEMAILER_Provider_Mandrill',
			'smtp2go'      => 'INSANEMAILER_Provider_Smtp2go',
			'mailtrap'     => 'INSANEMAILER_Provider_Mailtrap',
			'mailjet'      => 'INSANEMAILER_Provider_Mailjet',
			'zeptomail'    => 'INSANEMAILER_Provider_ZeptoMail',
			'mailersend'   => 'INSANEMAILER_Provider_MailerSend',
			'loops'        => 'INSANEMAILER_Provider_Loops',
			'resend'       => 'INSANEMAILER_Provider_Resend',
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

	private function handle_attachments( $email_id, $attachments ) {
		$upload_dir = wp_upload_dir();
		$insanemailer_dir    = $upload_dir['basedir'] . '/insanemailer-attachments/' . $email_id;

		if ( ! file_exists( $insanemailer_dir ) ) {
			wp_mkdir_p( $insanemailer_dir );
		}

		$stored_paths = [];

		foreach ( $attachments as $attachment_path ) {
			if ( file_exists( $attachment_path ) ) {
				$filename   = basename( $attachment_path );
				$new_path   = $insanemailer_dir . '/' . $filename;
				$copied     = copy( $attachment_path, $new_path );

				if ( $copied ) {
					$stored_paths[] = $new_path;
				}
			}
		}

		if ( ! empty( $stored_paths ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'insanemailer_emails';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, updating email record.
			$wpdb->update(
				$table_name,
				[ 'attachments' => wp_json_encode( $stored_paths ) ],
				[ 'id' => $email_id ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}

	private function html_to_plain( $html ) {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}

	private function record_direct_email_result( $result ) {
		if ( ! $this->current_direct_email_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'message_id'        => $result['message_id'] ?? null,
				'provider_response' => wp_json_encode( $result ),
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $this->current_direct_email_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );
	}

	private function update_direct_email_status( $status, $error = null ) {
		if ( ! $this->current_direct_email_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( $error ) {
			$data['error_message'] = $error;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			$data,
			[ 'id' => $this->current_direct_email_id ],
			array_fill( 0, count( $data ), '%s' ),
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );
	}

	private function log_direct_email( $atts ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$to          = $atts['to'] ?? '';
		$subject     = $atts['subject'] ?? '';
		$message     = $atts['message'] ?? '';
		$headers     = $atts['headers'] ?? [];
		$attachments = $atts['attachments'] ?? [];

		$to_email = '';
		$to_name  = '';
		if ( is_array( $to ) ) {
			$to_email = $to[0] ?? '';
		} else {
			if ( preg_match( '/^(.+)\s*<(.+)>$/', $to, $matches ) ) {
				$to_name  = trim( $matches[1] );
				$to_email = trim( $matches[2] );
			} else {
				$to_email = $to;
			}
		}

		$from_email   = $this->settings['from_email'] ?? get_option( 'admin_email' );
		$from_name    = $this->settings['from_name'] ?? get_option( 'blogname' );
		$reply_to     = null;
		$content_type = 'text/plain';

		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		$parsed_headers = [];
		foreach ( $headers as $header ) {
			if ( empty( $header ) ) {
				continue;
			}

			if ( is_array( $header ) ) {
				$key   = $header[0] ?? '';
				$value = $header[1] ?? '';
			} else {
				list( $key, $value ) = array_pad( explode( ':', $header, 2 ), 2, '' ); // phpcs:ignore Universal.Lists.DisallowLongListSyntax.Found
			}

			$key   = trim( $key );
			$value = trim( $value );

			if ( strcasecmp( $key, 'From' ) === 0 && empty( $this->settings['force_from'] ) ) {
				if ( preg_match( '/^(.+)\s*<(.+)>$/', $value, $matches ) ) {
					$from_name  = trim( $matches[1] );
					$from_email = trim( $matches[2] );
				} else {
					$from_email = $value;
				}
			} elseif ( strcasecmp( $key, 'Reply-To' ) === 0 ) {
				$reply_to = $value;
			} elseif ( strcasecmp( $key, 'Content-Type' ) === 0 ) {
				$content_type = $value;
			} else {
				$parsed_headers[ $key ] = $value;
			}
		}

		// An API send bypasses wp_mail()'s own sender pipeline, so run the core
		// sender filters here to keep third-party overrides working.
		$from_email = apply_filters( 'wp_mail_from', $from_email );
		$from_name  = apply_filters( 'wp_mail_from_name', $from_name );

		$is_html    = stripos( $content_type, 'text/html' ) !== false;
		$body_html  = $is_html ? $message : '';
		$body_plain = $is_html ? '' : $message;

		if ( $is_html && ! empty( $this->settings['auto_plain_text'] ) ) {
			$body_plain = $this->html_to_plain( $message );
		}

		$now = current_time( 'mysql' );

		$data = [
			'status'       => 'completed',
			'send_mode'    => 'direct',
			'priority'     => 2,
			'to_email'     => $to_email,
			'to_name'      => $to_name,
			'from_email'   => $from_email,
			'from_name'    => $from_name,
			'reply_to'     => $reply_to,
			'subject'      => $subject,
			'body_html'    => $body_html,
			'body_plain'   => $body_plain,
			'headers'      => wp_json_encode( $parsed_headers ),
			'attachments'  => wp_json_encode( [] ),
			'attempts'     => 1,
			'max_attempts' => 1,
			'scheduled_at' => $now,
			'sent_at'      => $now,
			'created_at'   => $now,
			'updated_at'   => $now,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for email logging.
		$inserted = $wpdb->insert( $table_name, $data );

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		if ( $inserted ) {
			$email_id = $wpdb->insert_id;

			if ( ! empty( $attachments ) ) {
				$this->handle_attachments( $email_id, (array) $attachments );
			}

			$this->current_direct_email_id = $email_id;
		}

		return [
			'to'          => [
				'email' => $to_email,
				'name'  => $to_name,
			],
			'from'        => [
				'email' => $from_email,
				'name'  => $from_name,
			],
			'reply_to'    => $reply_to,
			'subject'     => $subject,
			'body_html'   => $body_html,
			'body_plain'  => $body_plain,
			'headers'     => $parsed_headers,
			'attachments' => (array) $attachments,
		];
	}
}
