<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_SMTP extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'Generic SMTP';
	}

	public function send_raw( $mail_data ) {
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';

		$mail = new PHPMailer\PHPMailer\PHPMailer( true );

		try {
			$mail->isSMTP();
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer properties.
			$mail->Host       = $this->get_credential( 'host', 'localhost' );
			$mail->Port       = $this->get_credential( 'port', 587 );
			$mail->SMTPAuth   = $this->get_credential( 'auth', false );
			$mail->Username   = $this->get_credential( 'username', '' );
			$mail->Password   = $this->get_credential( 'password', '' );
			$mail->SMTPSecure = $this->get_credential( 'encryption', 'tls' );
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$mail->setFrom( $mail_data['from']['email'], $mail_data['from']['name'] ?? '' );
			$mail->addAddress( $mail_data['to']['email'], $mail_data['to']['name'] ?? '' );

			if ( ! empty( $mail_data['reply_to'] ) ) {
				$mail->addReplyTo( $mail_data['reply_to'] );
			}

			$is_html = ! empty( $mail_data['body_html'] );

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer properties.
			$mail->Subject = $mail_data['subject'];

			// A plain-text message has no HTML body, and PHPMailer rejects an
			// empty Body outright with "Message body empty".
			if ( $is_html ) {
				$mail->isHTML( true );
				$mail->Body    = $mail_data['body_html'];
				$mail->AltBody = $mail_data['body_plain'] ?? '';
			} else {
				$mail->isHTML( false );
				$mail->Body = $mail_data['body_plain'] ?? '';
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			if ( ! empty( $mail_data['attachments'] ) ) {
				foreach ( $mail_data['attachments'] as $attachment ) {
					if ( file_exists( $attachment ) ) {
						$mail->addAttachment( $attachment );
					}
				}
			}

			$mail->send();

			return [
				'success'    => true,
				'message_id' => $mail->getLastMessageID(),
			];
		} catch ( Exception $e ) {
			return [
				'success' => false,
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				'error'   => $mail->ErrorInfo,
			];
		}
	}

	public function test_connection() {
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';

		$mail = new PHPMailer\PHPMailer\PHPMailer( true );

		try {
			$mail->isSMTP();
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer properties.
			$mail->Host       = $this->get_credential( 'host', 'localhost' );
			$mail->Port       = $this->get_credential( 'port', 587 );
			$mail->SMTPAuth   = $this->get_credential( 'auth', false );
			$mail->Username   = $this->get_credential( 'username', '' );
			$mail->Password   = $this->get_credential( 'password', '' );
			$mail->SMTPSecure = $this->get_credential( 'encryption', 'tls' );
			$mail->Timeout    = 10;
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$mail->smtpConnect();
			$mail->smtpClose();

			return [
				'success' => true,
				'message' => 'SMTP connection successful',
			];
		} catch ( Exception $e ) {
			return [
				'success' => false,
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				'error'   => $mail->ErrorInfo,
			];
		}
	}
}
