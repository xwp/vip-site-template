<?php
/**
 * Newspack Popups View As
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Popups View As Class.
 */
final class Newspack_Popups_View_As {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_View_As
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Popups View As Instance.
	 * Ensures only one instance of Newspack Popups View As Instance is loaded or can be loaded.
	 *
	 * @return Newspack Popups View As Instance - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the "view as" feature specification.
	 *
	 * @return string "View as" specification.
	 */
	public static function viewing_as_spec() {
		if ( ! Newspack_Popups::is_user_admin() ) {
			return false;
		}
		if ( isset( $_GET['view_as'] ) && $_GET['view_as'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return sanitize_text_field( $_GET['view_as'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
}

Newspack_Popups_View_As::instance();
