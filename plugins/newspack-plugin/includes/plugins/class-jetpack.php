<?php
/**
 * Jetpack integration class.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class Jetpack {

	/**
	 * Modules scripts handles.
	 *
	 * @var string[]
	 */
	private static $scripts_handles = [
		'jp-tracks',              // Tracks analytics library.
		'jetpack-instant-search', // Jetpack Instant Search.
		'jetpack-search-widget',  // Jetpack Search widget.
	];

	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'jetpack_async_scripts' ], 20 );
		add_filter( 'newspack_amp_plus_sanitized', [ __CLASS__, 'jetpack_modules_amp_plus' ], 10, 2 );
		add_action( 'wp_head', [ __CLASS__, 'fix_instant_search_sidebar_display' ], 10 );
		add_filter( 'jetpack_lazy_images_skip_image_with_attributes', [ __CLASS__, 'skip_lazy_loading_on_feeds' ], 10 );

		// Disables Google Analytics.
		add_filter( 'jetpack_active_modules', array( __CLASS__, 'remove_google_analytics_from_active' ), 10, 2 );
		add_filter( 'jetpack_get_available_modules', array( __CLASS__, 'remove_google_analytics_from_available' ) );
	}

	/**
	 * Skip image lazy-loading on RSS feeds.
	 *
	 * @param bool $skip_lazy_loading Whether to skip lazy-loading.
	 * @return @bool Whether to skip lazy-loading.
	 */
	public static function skip_lazy_loading_on_feeds( $skip_lazy_loading ) {
		if ( is_feed() ) {
			return true;
		}
		return $skip_lazy_loading;
	}

	/**
	 * Whether Jetpack modules scripts should be rendered in AMP Plus.
	 *
	 * @return @bool Whether to render scripts.
	 */
	private static function should_amp_plus_modules() {
		if ( defined( 'NEWSPACK_AMP_PLUS_JETPACK_MODULES' ) ) {
			return true === NEWSPACK_AMP_PLUS_JETPACK_MODULES;
		}
		return false;
	}

	/**
	 * Make Jetpack scripts async.
	 */
	public static function jetpack_async_scripts() {
		foreach ( self::$scripts_handles as $handle ) {
			wp_script_add_data( $handle, 'async', true );
		}
	}

	/**
	 * Allow Jetpack modules scripts to be loaded in AMP Plus mode.
	 *
	 * @param bool|null $is_sanitized If null, the error will be handled. If false, rejected.
	 * @param object    $error        The AMP sanitisation error.
	 *
	 * @return bool Whether the error should be rejected.
	 */
	public static function jetpack_modules_amp_plus( $is_sanitized, $error ) {
		if ( ! self::should_amp_plus_modules() ) {
			return $is_sanitized;
		}
		if ( AMP_Enhancements::is_script_attribute_matching_strings( self::$scripts_handles, $error ) ) {
			$is_sanitized = false;
		}

		// Match inline scripts by script text since they don't have IDs.
		if ( AMP_Enhancements::is_script_body_matching_strings(
			[
				'jetpackSearchModuleSorting',  // Jetpack Search module sorting.
				'JetpackInstantSearchOptions', // Jetpack Instant Search options.
			],
			$error
		) ) {
			$is_sanitized = false;
		}
		return $is_sanitized;
	}

	/**
	 * Fix Instant Search Sidebar Display for AMP Plus
	 */
	public static function fix_instant_search_sidebar_display() {
		if ( ! self::should_amp_plus_modules() ) {
			return;
		}
		?>
		<style>
			.jetpack-instant-search__widget-area {
				display: block !important;
			}
		</style>
		<?php
	}

	/**
	 * Disables Google Analytics module. Users will not be able to activate it.
	 *
	 * @param array $modules Array with modules slugs.
	 * @return array
	 */
	public static function remove_google_analytics_from_active( $modules ) {
		return array_diff( $modules, array( 'google-analytics' ) );
	}

	/**
	 * Remove Google Analytics from available modules
	 *
	 * @param array $modules The array of available modules.
	 * @return array
	 */
	public static function remove_google_analytics_from_available( $modules ) {
		if ( isset( $modules['google-analytics'] ) ) {
			unset( $modules['google-analytics'] );
		}
		return $modules;
	}
}
Jetpack::init();
