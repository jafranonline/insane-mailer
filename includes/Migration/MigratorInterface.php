<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface IM_Migrator_Interface {

	/**
	 * Check if the source plugin settings exist and can be migrated.
	 *
	 * @return bool
	 */
	public function detect(): bool;

	/**
	 * Get the mapped settings in Insane Mailer format.
	 *
	 * @return array Settings array compatible with im_settings option.
	 */
	public function get_settings(): array;

	/**
	 * Get the human-readable name of the source plugin.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get the unique slug identifier for this migrator.
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Get a preview of what will be imported (with masked credentials).
	 *
	 * @return array
	 */
	public function get_preview(): array;
}
