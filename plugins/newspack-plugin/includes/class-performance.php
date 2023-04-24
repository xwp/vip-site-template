<?php
/**
 * Performance management.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Manages settings for Performance.
 */
class Performance {
	/**
	 * Whether page content is rendered now.
	 *
	 * @var bool
	 */
	public static $is_rendering_page_content = false;

	/**
	 * Index of currently rendered Homepage Post Block.
	 *
	 * @var int
	 */
	public static $current_homepage_posts_block = 0;

	/**
	 * Add hooks.
	 */
	public static function init() {
		add_filter( 'newspack_blocks_homepage_posts_block_attributes', [ __CLASS__, 'optimise_homepage_posts_block' ] );
		add_action( 'newspack_theme_before_page_content', [ __CLASS__, 'mark_page_content_rendering_start' ] );

		add_action( 'template_redirect', [ __CLASS__, 'process_output_html' ] );
		add_filter( 'newspack_output_html', [ __CLASS__, 'minify_inline_style_tags' ] );

		add_filter( 'newspack_analytics_event_js', [ __CLASS__, 'optimize_js' ] );
		add_filter( 'newspack_ads_frontend_js', [ __CLASS__, 'optimize_js' ] );
		add_filter( 'newspack_ads_lazy_loading_js', [ __CLASS__, 'optimize_js' ] );
	}

	/**
	 * Optimise JS code.
	 *
	 * @param string $js_code JS code.
	 */
	public static function optimize_js( $js_code ) {
		return preg_replace(
			[ '/(<script>|,|\(|\)|\{|\}|;|\&\&)\s+/', '/\s+(<script>|,|\(|\)|\{|\}|;|\&\&)/' ],
			'\1',
			$js_code
		);
	}

	/**
	 * Mark page content rendering start.
	 */
	public static function mark_page_content_rendering_start() {
		self::$is_rendering_page_content = true;
	}

	/**
	 * Turn of image lazy loading for the first two homepage posts blocks rendered on a page.
	 * These first blocks are likely to contain the Largest Contentful Paint image, which should
	 * not be deprioritised by lazy-loading, and should be prioritized with the fetchpriority hint.
	 *
	 * @param array $attributes Block attributes.
	 */
	public static function optimise_homepage_posts_block( $attributes ) {
		// Bail on AMP pages. These attributes may cause issues on AMP.
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return $attributes;
		}

		if ( self::$is_rendering_page_content ) {
			if ( 0 === self::$current_homepage_posts_block ) {
				$attributes['disableImageLazyLoad'] = true;
				$attributes['fetchPriority']        = 'high';
			}
			self::$current_homepage_posts_block++;
		}
		return $attributes;
	}

	/**
	 * Process the HTML output.
	 */
	public static function process_output_html() {
		if ( defined( 'NEWSPACK_DISABLE_HTML_PERF_PROCESSING' ) && NEWSPACK_DISABLE_HTML_PERF_PROCESSING ) {
			return;
		}
		if ( ! has_filter( 'newspack_output_html' ) ) {
			return;
		}
		if ( is_embed() || is_feed() || is_preview() || is_customize_preview() ) {
			return;
		}
		ob_start(
			function( $html ) {
				if ( stripos( $html, '<html' ) === false || stripos( $html, '</body>' ) === false || stripos( $html, '<xsl:stylesheet' ) !== false ) {
					return $html;
				}
				return (string) apply_filters( 'newspack_output_html', $html );
			}
		);
	}

	/**
	 * Minify a CSS string.
	 *
	 * @param string $css CSS string.
	 */
	private static function minify_css( $css ) {
		// Remove comments.
		$css = preg_replace( '/\/\*(?:(?!\*\/).)*\*\//is', '', $css );
		// Remove whitespace.
		$css = preg_replace(
			[ '/(,|\{|\}|;|\*\/)\s+/', '/\s+(,|\{|\}|;|\*\/)/' ],
			'\1',
			$css
		);
		return trim( $css );
	}

	/**
	 * Minify inline CSS.
	 *
	 * @param string $html HTML string.
	 */
	public static function minify_inline_style_tags( $html ) {
		return preg_replace_callback(
			'/(<style[^>]*>)(.*?)(<\/style>)/si',
			function( $matches ) {
				return $matches[1] . self::minify_css( $matches[2] ) . $matches[3];
			},
			$html
		);

	}
}
Performance::init();
