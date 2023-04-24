<?php
/**
 * OneSignal integration class.
 * https://wordpress.org/plugins/onesignal-free-web-push-notifications
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class OneSignal {
	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		add_filter( 'newspack_amp_plus_sanitized', [ __CLASS__, 'onesignal_amp_plus' ], 10, 2 );
		add_filter( 'onesignal_is_amp', [ __CLASS__, 'onesignal_is_amp' ] );
	}

	/**
	 * Allow OneSignal modules scripts to be loaded in AMP Plus mode.
	 *
	 * @param bool|null $is_sanitized If null, the error will be handled. If false, rejected.
	 * @param object    $error        The AMP sanitisation error.
	 *
	 * @return bool Whether the error should be rejected.
	 */
	public static function onesignal_amp_plus( $is_sanitized, $error ) {
		if (
			AMP_Enhancements::is_script_attribute_matching_strings( [ 'onesignal.com' ], $error, 'src' )
			|| AMP_Enhancements::is_script_body_matching_strings( [ 'window.OneSignal' ], $error )
		) {
			$is_sanitized = false;
		}
		return $is_sanitized;
	}

	/**
	 * Override is-AMP check if AMP Plus is enabled.
	 *
	 * @param bool $is_amp Whether the page is AMP.
	 */
	public static function onesignal_is_amp( $is_amp ) {
		if ( AMP_Enhancements::should_use_amp_plus() ) {
			return false;
		}
		return $is_amp;
	}
}
OneSignal::init();
