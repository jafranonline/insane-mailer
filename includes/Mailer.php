<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Mailer {

	private static $instance = null;
	private $settings;
	private $current_email_id = null;

	/**
	 * Whether the outcome of the message in flight has already been written to
	 * its log row. Guards the wp_mail_succeeded / wp_mail_failed listeners,
	 * which would otherwise overwrite a provider result — and re-enter on the
	 * wp_mail_failed we fire ourselves.
	 *
	 * @var bool
	 */
	private $result_recorded = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = get_option( 'insanemailer_settings', [] );
		add_filter( 'pre_wp_mail', [ $this, 'handle_mail' ], 10, 2 );
		add_filter( 'wp_mail_from', [ $this, 'filter_from_email' ], 99 );
		add_filter( 'wp_mail_from_name', [ $this, 'filter_from_name' ], 99 );
		add_action( 'wp_mail_succeeded', [ $this, 'handle_wp_mail_succeeded' ] );
		add_action( 'wp_mail_failed', [ $this, 'handle_wp_mail_failed' ] );
	}

	/**
	 * Apply the configured sender before PHPMailer validates it.
	 *
	 * WordPress calls setFrom() before phpmailer_init fires, so overriding the
	 * sender any later is too late: the default wordpress@<host> is rejected
	 * outright on hosts without a dot (localhost), failing the send before any
	 * provider is reached.
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

	/**
	 * Log every wp_mail() message and deliver it through the configured provider.
	 *
	 * Only the "default" provider falls through to PHPMailer; everything else,
	 * custom SMTP included, is delivered by its own provider class and short
	 * circuits wp_mail(). Letting wp_mail() continue after a provider accepted
	 * the message would attempt a second delivery and report a false failure.
	 *
	 * @param null|bool $null Short-circuit value for pre_wp_mail.
	 * @param array     $atts wp_mail() arguments.
	 * @return null|bool Null to let PHPMailer run, or whether the send succeeded.
	 */
	public function handle_mail( $null, $atts ) {
		$this->current_email_id = null;
		$this->result_recorded  = false;

		$mail_data = $this->log_email( $atts );

		if ( ! empty( $this->settings['pause_sending'] ) ) {
			$this->update_email_status( $this->current_email_id, 'paused' );
			$this->result_recorded = true;

			return true;
		}

		$provider_name = $this->get_provider_slug();

		if ( 'default' === $provider_name ) {
			return null;
		}

		$result = $this->send_via_provider( $provider_name, $mail_data );

		if ( empty( $result['success'] ) ) {
			return $this->fail_email( $result['error'] ?? 'Unknown error', $result );
		}

		$this->mark_sent( $this->current_email_id, $result );
		$this->result_recorded = true;

		do_action( 'insanemailer_email_sent', $this->current_email_id, $result );

		return true;
	}

	/**
	 * Close out the log row once PHPMailer handled the message itself.
	 */
	public function handle_wp_mail_succeeded( $mail_data ) {
		if ( ! $this->current_email_id || $this->result_recorded ) {
			return;
		}

		$result = [ 'success' => true ];

		$this->mark_sent( $this->current_email_id, $result );
		$this->result_recorded = true;

		do_action( 'insanemailer_email_sent', $this->current_email_id, $result );
	}

	public function handle_wp_mail_failed( $error ) {
		if ( ! $this->current_email_id || $this->result_recorded ) {
			return;
		}

		$message = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;

		$this->mark_failed( $this->current_email_id, $message );
		$this->result_recorded = true;

		do_action( 'insanemailer_email_failed', $this->current_email_id, $message );
	}

	/**
	 * Record a provider failure and mirror it onto wp_mail_failed, which callers
	 * rely on for the reason a send did not go through.
	 */
	private function fail_email( $error, $result = [] ) {
		$this->mark_failed( $this->current_email_id, $error, $result );
		$this->result_recorded = true;

		do_action( 'insanemailer_email_failed', $this->current_email_id, $error );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, fired so wp_mail() listeners still see the failure.
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $error ) );

		return false;
	}

	/**
	 * Send an already logged message again and update its row in place.
	 *
	 * Shared by the resend endpoint and the one-time drain of emails left over
	 * from queue mode.
	 *
	 * @param object $email Row from the emails table.
	 * @return array Provider result, with at least a 'success' key.
	 */
	public function send_logged_email( $email ) {
		$headers     = json_decode( $email->headers, true );
		$attachments = json_decode( $email->attachments, true );

		$mail_data = [
			'to'          => [
				'email' => $email->to_email,
				'name'  => $email->to_name,
			],
			'from'        => [
				'email' => $email->from_email,
				'name'  => $email->from_name,
			],
			'reply_to'    => $email->reply_to,
			'subject'     => $email->subject,
			'body_html'   => $email->body_html,
			'body_plain'  => $email->body_plain,
			'headers'     => is_array( $headers ) ? $headers : [],
			'attachments' => is_array( $attachments ) ? $attachments : [],
		];

		$provider_name = $this->get_provider_slug();

		if ( 'default' === $provider_name ) {
			$result = $this->send_via_wp_mail( $mail_data );
		} else {
			$result = $this->send_via_provider( $provider_name, $mail_data );
		}

		// The row may predate the provider column, or have been sent by a
		// provider that has since been swapped out.
		$this->record_provider( $email->id, $provider_name );

		if ( ! empty( $result['success'] ) ) {
			$this->mark_sent( $email->id, $result );
			do_action( 'insanemailer_email_sent', $email->id, $result );
		} else {
			$error = $result['error'] ?? 'Unknown error';
			$this->mark_failed( $email->id, $error, $result );
			do_action( 'insanemailer_email_failed', $email->id, $error );
		}

		return $result;
	}

	private function get_provider_slug() {
		return $this->settings['provider'] ?? 'default';
	}

	/**
	 * Deliver a message through a provider class.
	 *
	 * @param string $provider_name Provider slug.
	 * @param array  $mail_data     Message, in the send_raw() shape.
	 * @return array Provider result.
	 */
	private function send_via_provider( $provider_name, $mail_data ) {
		$provider_class = $this->get_provider_class( $provider_name );

		if ( ! $provider_class ) {
			return [
				'success' => false,
				'error'   => 'Provider class not found',
			];
		}

		try {
			$provider = new $provider_class( $this->settings['credentials'] ?? [] );

			return $provider->send_raw( $mail_data );
		} catch ( Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Re-send a logged message through PHPMailer for the default provider.
	 *
	 * Our own pre_wp_mail filter is detached for the duration so the message is
	 * not logged a second time.
	 *
	 * @param array $mail_data Message, in the send_raw() shape.
	 * @return array Result, in the send_raw() shape.
	 */
	private function send_via_wp_mail( $mail_data ) {
		$to = $mail_data['to']['email'];

		if ( ! empty( $mail_data['to']['name'] ) ) {
			$to = sprintf( '%s <%s>', $mail_data['to']['name'], $mail_data['to']['email'] );
		}

		$headers = [];

		if ( ! empty( $mail_data['from']['email'] ) ) {
			$from = $mail_data['from']['email'];
			if ( ! empty( $mail_data['from']['name'] ) ) {
				$from = sprintf( '%s <%s>', $mail_data['from']['name'], $mail_data['from']['email'] );
			}
			$headers[] = 'From: ' . $from;
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . $mail_data['reply_to'];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		foreach ( $mail_data['headers'] as $key => $value ) {
			$headers[] = $key . ': ' . $value;
		}

		$body = ! empty( $mail_data['body_html'] ) ? $mail_data['body_html'] : $mail_data['body_plain'];

		remove_filter( 'pre_wp_mail', [ $this, 'handle_mail' ], 10 );

		$sent = wp_mail( $to, $mail_data['subject'], $body, $headers, $mail_data['attachments'] );

		add_filter( 'pre_wp_mail', [ $this, 'handle_mail' ], 10, 2 );

		if ( $sent ) {
			return [ 'success' => true ];
		}

		global $phpmailer;
		$error = 'Unknown error';
		if ( isset( $phpmailer ) && $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$error = $phpmailer->ErrorInfo;
		}

		return [
			'success' => false,
			'error'   => $error,
		];
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

	private function record_provider( $email_id, $provider_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated by the status write that follows.
		$wpdb->update(
			$wpdb->prefix . 'insanemailer_emails',
			[ 'provider' => $provider_name ],
			[ 'id' => $email_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	private function mark_sent( $email_id, $result ) {
		if ( ! $email_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'status'            => 'sent',
				'sent_at'           => $now,
				'message_id'        => $result['message_id'] ?? null,
				'provider_response' => wp_json_encode( $result ),
				'updated_at'        => $now,
			],
			[ 'id' => $email_id ],
			[ '%s', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );
	}

	private function mark_failed( $email_id, $error, $result = [] ) {
		if ( ! $email_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'status'            => 'failed',
				'error_message'     => $error,
				'provider_response' => wp_json_encode( $result ),
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $email_id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );
	}

	private function update_email_status( $email_id, $status ) {
		if ( ! $email_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $email_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );
	}

	/**
	 * Write the log row for an outgoing message and return its parsed contents.
	 *
	 * @param array $atts wp_mail() arguments.
	 * @return array Message, in the send_raw() shape.
	 */
	private function log_email( $atts ) {
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

		// A provider send bypasses wp_mail()'s own sender pipeline, so run the core
		// sender filters here to keep third-party overrides working.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hooks.
		$from_email = apply_filters( 'wp_mail_from', $from_email );
		$from_name  = apply_filters( 'wp_mail_from_name', $from_name );
		// phpcs:enable

		$is_html    = stripos( $content_type, 'text/html' ) !== false;
		$body_html  = $is_html ? $message : '';
		$body_plain = $is_html ? '' : $message;

		if ( $is_html && ! empty( $this->settings['auto_plain_text'] ) ) {
			$body_plain = $this->html_to_plain( $message );
		}

		$now = current_time( 'mysql' );

		$data = [
			'status'      => 'sending',
			'to_email'    => $to_email,
			'to_name'     => $to_name,
			'from_email'  => $from_email,
			'from_name'   => $from_name,
			'reply_to'    => $reply_to,
			'subject'     => $subject,
			'body_html'   => $body_html,
			'body_plain'  => $body_plain,
			'headers'     => wp_json_encode( $parsed_headers ),
			'attachments' => wp_json_encode( [] ),
			'provider'    => $this->get_provider_slug(),
			'created_at'  => $now,
			'updated_at'  => $now,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for email logging.
		$inserted = $wpdb->insert( $table_name, $data );

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		if ( $inserted ) {
			$email_id = $wpdb->insert_id;

			if ( ! empty( $attachments ) ) {
				$this->handle_attachments( $email_id, (array) $attachments );
			}

			$this->current_email_id = $email_id;
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
