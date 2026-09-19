<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whitelisting, sanitizing and masking of the insanemailer_settings option.
 *
 * Every path that writes the option (REST update, import, migration) and every
 * path that returns it to the browser goes through here, so the accepted keys
 * and the definition of "secret" live in one place.
 */
class INSANEMAILER_Settings {

	const OPTION = 'insanemailer_settings';

	const MASK = '********';

	/**
	 * Credential keys are matched against this to decide what to mask.
	 */
	const SECRET_PATTERN = '/(password|secret|token|api_key|signing_key)/i';

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$clean = [];

		if ( isset( $input['provider'] ) ) {
			$clean['provider'] = sanitize_key( $input['provider'] );
		}

		if ( isset( $input['credentials'] ) ) {
			$clean['credentials'] = self::sanitize_secret_map( $input['credentials'] );
		}

		if ( isset( $input['webhook_secrets'] ) ) {
			$clean['webhook_secrets'] = self::sanitize_secret_map( $input['webhook_secrets'] );
		}

		if ( isset( $input['from_email'] ) ) {
			$clean['from_email'] = sanitize_email( $input['from_email'] );
		}

		if ( isset( $input['from_name'] ) ) {
			$clean['from_name'] = sanitize_text_field( $input['from_name'] );
		}

		if ( isset( $input['reply_to'] ) ) {
			$clean['reply_to'] = sanitize_email( $input['reply_to'] );
		}

		foreach ( [ 'force_from', 'auto_plain_text', 'pause_sending', 'setup_completed' ] as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$clean[ $flag ] = rest_sanitize_boolean( $input[ $flag ] );
			}
		}

		if ( isset( $input['auto_delete_days'] ) ) {
			$clean['auto_delete_days'] = max( 0, (int) $input['auto_delete_days'] );
		}

		return $clean;
	}

	/**
	 * Secrets may legitimately contain characters sanitize_text_field() would
	 * encode or strip, so only keys are normalised and values are limited to
	 * single-line strings without control characters.
	 */
	private static function sanitize_secret_map( $map ) {
		if ( ! is_array( $map ) ) {
			return [];
		}

		$clean = [];

		foreach ( $map as $key => $value ) {
			$key = sanitize_key( $key );

			if ( '' === $key || ! is_scalar( $value ) ) {
				continue;
			}

			$clean[ $key ] = trim( preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $value ) );
		}

		return $clean;
	}

	public static function mask( array $settings ) {
		if ( ! empty( $settings['credentials'] ) && is_array( $settings['credentials'] ) ) {
			foreach ( $settings['credentials'] as $key => $value ) {
				if ( '' !== (string) $value && preg_match( self::SECRET_PATTERN, $key ) ) {
					$settings['credentials'][ $key ] = self::MASK;
				}
			}
		}

		// Everything under webhook_secrets is a secret, whatever the provider is called.
		if ( ! empty( $settings['webhook_secrets'] ) && is_array( $settings['webhook_secrets'] ) ) {
			foreach ( $settings['webhook_secrets'] as $key => $value ) {
				if ( '' !== (string) $value ) {
					$settings['webhook_secrets'][ $key ] = self::MASK;
				}
			}
		}

		return $settings;
	}

	/**
	 * Put stored values back where the browser echoed the mask.
	 */
	public static function restore_masked( array $incoming, array $current ) {
		foreach ( [ 'credentials', 'webhook_secrets' ] as $group ) {
			if ( empty( $incoming[ $group ] ) || ! is_array( $incoming[ $group ] ) ) {
				continue;
			}

			foreach ( $incoming[ $group ] as $key => $value ) {
				if ( self::MASK === $value ) {
					$incoming[ $group ][ $key ] = $current[ $group ][ $key ] ?? '';
				}
			}
		}

		return $incoming;
	}
}
