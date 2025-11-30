<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_Mailer {

	private static $instance = null;
	private $settings;
	private $is_queued = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = get_option( 'im_settings', [] );
		add_action( 'phpmailer_init', [ $this, 'intercept_phpmailer' ], 999 );
		add_filter( 'pre_wp_mail', [ $this, 'maybe_queue_mail' ], 10, 2 );
	}

	public function maybe_queue_mail( $null, $atts ) {
		$send_mode = $this->settings['send_mode'] ?? 'queue';

		// If direct mode, let wp_mail proceed normally
		if ( 'direct' === $send_mode || $this->should_bypass_queue() ) {
			return null;
		}

		// Queue mode: queue the email and return true to short-circuit wp_mail
		$this->queue_from_atts( $atts );
		return true;
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

		$table_name = $wpdb->prefix . 'im_emails';

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
				list( $key, $value ) = array_pad( explode( ':', $header, 2 ), 2, '' );
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
		$priority = apply_filters( 'im_priority', $priority, $to_email, $atts );

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

		$inserted = $wpdb->insert( $table_name, $data );

		if ( $inserted ) {
			$email_id = $wpdb->insert_id;

			if ( ! empty( $attachments ) ) {
				$this->handle_attachments( $email_id, (array) $attachments );
			}

			do_action( 'im_email_queued', $email_id, $data );
		}
	}

	private function send_direct( $phpmailer ) {
		if ( ! empty( $this->settings['force_from'] ) ) {
			$phpmailer->From     = $this->settings['from_email'] ?? $phpmailer->From;
			$phpmailer->FromName = $this->settings['from_name'] ?? $phpmailer->FromName;
		}

		$provider = $this->settings['provider'] ?? 'default';

		// Default provider uses WordPress's native wp_mail without modification
		if ( 'default' === $provider || 'smtp' === $provider ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = 'localhost';
		$phpmailer->SMTPAuth   = false;
		$phpmailer->SMTPSecure = '';
		$phpmailer->Port       = 25;

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
			IM_Logger::error( 'Provider class not found', [ 'provider' => $provider_name ] );
			return;
		}

		try {
			$provider = new $provider_class( $this->settings['credentials'] ?? [] );
			$result   = $provider->send( $phpmailer );

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] ?? 'Unknown error' );
			}

			do_action( 'im_email_sent', null, $result );
		} catch ( Exception $e ) {
			IM_Logger::error( 'Direct send failed', [
				'provider' => $provider_name,
				'error'    => $e->getMessage(),
			] );

			do_action( 'im_email_failed', null, $e->getMessage() );
		}
	}

	private function get_provider_class( $provider_name ) {
		$providers = [
			'ses'          => 'IM_Provider_SES',
			'mailgun'      => 'IM_Provider_Mailgun',
			'sendgrid'     => 'IM_Provider_SendGrid',
			'brevo'        => 'IM_Provider_Brevo',
			'sparkpost'    => 'IM_Provider_SparkPost',
			'netcore'      => 'IM_Provider_Netcore',
			'postmark'     => 'IM_Provider_Postmark',
			'elasticemail' => 'IM_Provider_ElasticEmail',
			'smtpcom'      => 'IM_Provider_SmtpCom',
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

	private function handle_attachments( $email_id, $attachments ) {
		$upload_dir = wp_upload_dir();
		$im_dir    = $upload_dir['basedir'] . '/im-attachments/' . $email_id;

		if ( ! file_exists( $im_dir ) ) {
			wp_mkdir_p( $im_dir );
		}

		$stored_paths = [];

		foreach ( $attachments as $attachment_path ) {
			if ( file_exists( $attachment_path ) ) {
				$filename   = basename( $attachment_path );
				$new_path   = $im_dir . '/' . $filename;
				$copied     = copy( $attachment_path, $new_path );

				if ( $copied ) {
					$stored_paths[] = $new_path;
				}
			}
		}

		if ( ! empty( $stored_paths ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'im_emails';

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
		$text = strip_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}
}
