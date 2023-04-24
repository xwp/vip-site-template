<?php
/**
 * Handles the revisions control for Newspack, managing how many revisions should be kept in the database
 *
 * @package Newspack
 */

namespace Newspack;

use DateTime;
use WP_Post;

require_once 'class-major-revisions.php';

/**
 * Revisions Control class
 */
class Revisions_Control {

	/**
	 * Flag to make sure hooks are initialized only once
	 *
	 * @var boolean
	 */
	private static $initiated = false;

	/**
	 * Initializes the hook
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::$initiated ) {
			add_filter( 'wp_revisions_to_keep', [ __CLASS__, 'filter_revisions_to_keep' ] );
			add_filter( 'pre_delete_post', [ __CLASS__, 'pre_delete_revision' ], 10, 2 );
			add_action( 'admin_head-revision.php', [ __CLASS__, 'admin_head' ] );
			self::$initiated = true;
		}
	}

	/**
	 * Gets the option stored in the database with the feature configuration
	 *
	 * @return array
	 */
	private static function get_option() {
		$option = get_option( 'newspack_revisions_control' );
		// If option does not exist or is invalid, fallback to defaults.
		if ( empty( $option ) || ! is_array( $option ) || empty( $option['active'] ) || empty( $option['number'] ) || empty( $option['min_age'] ) ) {
			return [];
		}
		return $option;
	}

	/**
	 * Gets the current status of the feature configuration
	 *
	 * @return array
	 */
	private static function get_status() {
		$option = self::get_option();
		if ( ! empty( $option ) ) {
			return $option;
		}
		// TODO: When we allow users to modify the min_age via UI or hook, we need to make the Help text in self::admin_head dynamic.
		if ( defined( 'NEWSPACK_LIMIT_REVISIONS_NUMBER' ) && is_int( NEWSPACK_LIMIT_REVISIONS_NUMBER ) ) {
			return [
				'active'  => true,
				'number'  => NEWSPACK_LIMIT_REVISIONS_NUMBER,
				'min_age' => '-1 week',
			];
		}
		return [ 'active' => false ];
	}

	/**
	 * Checks if the feature is active
	 *
	 * @return boolean
	 */
	public static function is_active() {
		return self::get_status()['active'];
	}

	/**
	 * Checks the number of revisions that should be kept, if feature is active
	 *
	 * @return ?int
	 */
	private static function get_number() {
		if ( self::get_status()['active'] ) {
			return self::get_status()['number'];
		}
	}

	/**
	 * Gets the maximum date a revision must have to be deleted, if the feature is active
	 *
	 * @return ?string
	 */
	private static function get_min_age() {
		if ( self::get_status()['active'] ) {
			$min_age = self::get_status()['min_age'];
			$date    = ( new DateTime() )->modify( $min_age );
			return $date->format( 'Y-m-d H:i:s' );
		}
	}

	/**
	 * Filters the number of revisions to keep
	 *
	 * @param int $number Number of revisions to store.
	 * @return int
	 */
	public static function filter_revisions_to_keep( $number ) {
		if ( ! self::is_active() ) {
			return $number;
		}
		return (int) self::get_number();
	}

	/**
	 * Keep revisions that are not old enough from being deleted
	 *
	 * @param mixed    $check Whether to go forward with deletion.
	 * @param \WP_Post $post Post object.
	 * @return mixed
	 */
	public static function pre_delete_revision( $check, WP_Post $post ) {
		if ( ! is_null( $check ) || ! self::is_active() || 'revision' !== $post->post_type ) {
			return $check;
		}
		if ( $post->post_date > self::get_min_age() ) {
			return true; // do not delete.
		}
		$revision = new Major_Revision( $post->post_parent, $post->ID );
		if ( $revision->is_major() ) {
			return true; // do not delete.
		}
		return $check;
	}

	/**
	 * Admin head callback. Adds an additional section to the Help tab
	 *
	 * @return void
	 */
	public static function admin_head() {

		// translators: %d is the maximum number of revisions that will be kept.
		$help_text  = '<p>' . sprintf( __( '<strong>Newspack</strong> revisions control is active. This means that there is a limit of %d revisions that will be kept.', 'newspack' ), self::get_number() ) . '</p>';
		$help_text .= '<p>' . __( ' Older revisions that exceed this number will be deleted, except:', 'newspack' ) . '</p>';
		$help_text .= '<ul><li>' . __( 'Revisions less than one week old', 'newspack' ) . '</li>';
		$help_text .= '<li>' . __( 'Revisions marked as major. Use the button on the revision title bar to mark/unmark a revision as major', 'newspack' ) . '</li></ul>';



		$help_tab = get_current_screen()->get_help_tab( 'revisions-overview' );

		$help_tab['content'] .= $help_text;

		get_current_screen()->add_help_tab( $help_tab );

	}

}

Revisions_Control::init();
