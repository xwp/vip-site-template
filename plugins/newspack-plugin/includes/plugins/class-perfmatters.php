<?php
/**
 * Perfmatters integration class.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class Perfmatters {
	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		add_filter( 'option_perfmatters_options', [ __CLASS__, 'set_defaults' ] );
		add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ] );
	}

	/**
	 * Scripts to delay spec.
	 */
	private static function scripts_to_delay() {
		return [
			// Newspack.
			'newspack-plugin',
			'newspack-popups',
			'newspack-blocks',
			'newspack-newsletters',
			'newspack-sponsors',
			'newspack-listings',
			'newspack-theme',
			'window.newspack',
			// WordPress.
			'videopress',
			'related-posts',
			'jp-search.js',
			'jetpack-comment',
			'photon.min.js',
			'comment-reply',
			'stats.wp.com',
			// Google Analytics.
			"ga( '",
			"ga('",
			'google-analytics.com/analytics.js',
			// Google Tag Manager.
			'/gtm.js',
			'/gtag/js',
			'gtag(',
			'/gtm-',
			'/gtm.',
			// Facebook.
			'fbevents.js',
			'fbq(',
			'/busting/facebook-tracking/',
			// Twitter.
			'ads-twitter.com',
			// Plugins.
			'mailchimp-for-woocommerce',
			'mailchimp-for-wp',
			// Third-party services.
			'disqus',
			'recaptcha',
			'twitter.com',
			// Advertising.
			'googletag.pubads',
			'adsbygoogle.js',
			'ai_insert_code',
			'doubleclick.net',
		];
	}

	/**
	 * Stylesheets to exclude from the "Unused CSS" feature.
	 */
	private static function unused_css_excluded_stylesheets() {
		return [
			'plugins/newspack-blocks', // Newspack Blocks.
			'/themes/newspack-', // Any Newspack theme stylesheet.
			'wp-includes',
		];
	}

	/**
	 * Selectors to exclude from the "Unused CSS" feature.
	 */
	private static function unused_css_excluded_selectors() {
		return [
			'body',
		];
	}

	/**
	 * URLs to preconnect to.
	 *
	 * @param array $existing_urls Existing URLs to filter out.
	 */
	private static function preconnect_urls( $existing_urls = [] ) {
		return array_filter(
			[
				[
					'url'         => 'https://i0.wp.com',
					'crossorigin' => false,
				],
			],
			function( $url ) use ( $existing_urls ) {
				return ! in_array( $url['url'], $existing_urls );
			}
		);
	}

	/**
	 * Set default options for Perfmatters.
	 *
	 * @param array $options Perfmatters options.
	 */
	public static function set_defaults( $options = [] ) {
		if ( defined( 'NEWSPACK_IGNORE_PERFMATTERS_DEFAULTS' ) && NEWSPACK_IGNORE_PERFMATTERS_DEFAULTS ) {
			return $options;
		}

		// Basic options.
		$options['disable_emojis']              = true;
		$options['disable_dashicons']           = true;
		$options['disable_woocommerce_scripts'] = true;
		// "The cart fragments feature and or AJAX request in WooCommerce is used to update the cart
		// total without refreshing the page."
		// https://perfmatters.io/docs/disable-woocommerce-cart-fragments-ajax/
		$options['disable_woocommerce_cart_fragmentation'] = true;

		// JS deferral.
		if ( ! isset( $options['assets'] ) ) {
			$options['assets'] = [];
		}
		$defer_js_exclusions           = [
			'wp-includes',
			'jwplayer.com', // This platform won't work if the JS is deferred.
			'adsrvr.org', // This platform won't work if the JS is deferred.
		];
		$options['assets']['defer_js'] = true;
		if ( isset( $options['assets']['js_exclusions'] ) && is_array( $options['assets']['js_exclusions'] ) ) {
			$options['assets']['js_exclusions'] = array_unique(
				array_merge(
					$options['assets']['js_exclusions'],
					$defer_js_exclusions
				)
			);
		} else {
			$options['assets']['js_exclusions'] = $defer_js_exclusions;
		}
		$options['assets']['defer_jquery'] = true;

		// JS delay.
		$options['assets']['delay_js'] = true;
		if ( isset( $options['assets']['delay_js_inclusions'] ) && is_array( $options['assets']['delay_js_inclusions'] ) ) {
			$options['assets']['delay_js_inclusions'] = array_unique(
				array_merge(
					$options['assets']['delay_js_inclusions'],
					self::scripts_to_delay()
				)
			);
		} else {
			$options['assets']['delay_js_inclusions'] = self::scripts_to_delay();
		}
		$options['assets']['delay_timeout'] = true;

		// Unused CSS.
		$options['assets']['remove_unused_css'] = true;
		if ( isset( $options['assets']['rucss_excluded_stylesheets'] ) && is_array( $options['assets']['rucss_excluded_stylesheets'] ) ) {
			$options['assets']['rucss_excluded_stylesheets'] = array_unique(
				array_merge(
					$options['assets']['rucss_excluded_stylesheets'],
					self::unused_css_excluded_stylesheets()
				)
			);
		} else {
			$options['assets']['rucss_excluded_stylesheets'] = self::unused_css_excluded_stylesheets();
		}
		if ( isset( $options['assets']['rucss_excluded_selectors'] ) && is_array( $options['assets']['rucss_excluded_selectors'] ) ) {
			$options['assets']['rucss_excluded_selectors'] = array_unique(
				array_merge(
					$options['assets']['rucss_excluded_selectors'],
					self::unused_css_excluded_selectors()
				)
			);
		} else {
			$options['assets']['rucss_excluded_selectors'] = self::unused_css_excluded_selectors();
		}

		// Preload.
		if ( ! isset( $options['preload'] ) ) {
			$options['preload'] = [];
		}
		$options['preload']['critical_images'] = '2';
		if ( isset( $options['preload']['preconnect'] ) && is_array( $options['preload']['preconnect'] ) ) {
			$options['preload']['preconnect'] = array_merge(
				$options['preload']['preconnect'],
				self::preconnect_urls( array_column( $options['preload']['preconnect'], 'url' ) )
			);
		} else {
			$options['preload']['preconnect'] = self::preconnect_urls();
		}

		// Lazyload.
		if ( ! isset( $options['lazyload'] ) ) {
			$options['lazyload'] = [];
		}
		$options['lazyload']['lazy_loading_iframes']       = true;
		$options['lazyload']['youtube_preview_thumbnails'] = true;
		$options['lazyload']['image_dimensions']           = true;

		// Fonts.
		if ( ! isset( $options['fonts'] ) ) {
			$options['fonts'] = [];
		}
		$options['fonts']['disable_google_fonts'] = false;
		$options['fonts']['display_swap']         = true;
		$options['fonts']['local_google_fonts']   = true;

		return $options;
	}

	/**
	 * Add an admin notice.
	 */
	public static function admin_notice() {
		if ( 'settings_page_perfmatters' !== get_current_screen()->id ) {
			return;
		}
		if ( defined( 'NEWSPACK_IGNORE_PERFMATTERS_DEFAULTS' ) && NEWSPACK_IGNORE_PERFMATTERS_DEFAULTS ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>'
		. __( 'Newspack plugin is overriding Perfmatters settings. You can use the <code>NEWSPACK_IGNORE_PERFMATTERS_DEFAULTS</code> flag to disable that behavior.', 'newspack' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. '</p></div>';
	}
}
Perfmatters::init();
