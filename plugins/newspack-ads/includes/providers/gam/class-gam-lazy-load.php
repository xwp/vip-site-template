<?php
/**
 * Newspack Ads GAM Lazy Loading Settings.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers;

use Newspack_Ads\Core;
use Newspack_Ads\Settings;

/**
 * Newspack Ads GAM Lazy Loading Class.
 */
final class GAM_Lazy_Load {

	const SECTION = 'lazy_load';
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'newspack_ads_settings_list', [ __CLASS__, 'register_settings' ] );
		add_action( 'newspack_ads_gtag_before_script', [ __CLASS__, 'render_lazy_load_script' ] );
	}

	/**
	 * Register GAM Lazy Loading settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public static function register_settings( $settings_list ) {
		if ( Core::is_amp() ) {
			return $settings_list;
		}
		return array_merge(
			[
				[
					'description' => __( 'Lazy Loading', 'newspack-ads' ),
					'help'        => __( 'Enables pages to load faster, reduces resource consumption and contention, and improves viewability rate.', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => true,
					'public'      => true,
				],
				[
					'description' => __( 'Fetch margin percent', 'newspack-ads' ),
					'help'        => __( 'Minimum distance from the current viewport a slot must be before we fetch the ad as a percentage of viewport size.', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'fetch_margin_percent',
					'type'        => 'int',
					'default'     => 100,
					'public'      => true,
				],
				[
					'description' => __( 'Render margin percent', 'newspack-ads' ),
					'help'        => __( 'Minimum distance from the current viewport a slot must be before we render an ad.', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'render_margin_percent',
					'type'        => 'int',
					'default'     => 0,
					'public'      => true,
				],
				[
					'description' => __( 'Mobile scaling', 'newspack-ads' ),
					'help'        => __( 'A multiplier applied to margins on mobile devices. This allows varying margins on mobile vs. desktop.', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'mobile_scaling',
					'type'        => 'float',
					'default'     => 2,
					'public'      => true,
				],
			],
			$settings_list
		);
	}

	/**
	 * Render lazy loading script.
	 */
	public static function render_lazy_load_script() {
		if ( Core::is_amp() ) {
			return;
		}
		$settings = Settings::get_settings( 'lazy_load', true );
		if ( ! $settings['active'] ) {
			return;
		}

		ob_start();

		?>
		<script data-amp-plus-allowed>
			( function() {
				var lazy_load = <?php echo wp_json_encode( $settings, JSON_FORCE_OBJECT ); ?>;
				googletag.cmd.push( function() {
					googletag.pubads().enableLazyLoad( {
						fetchMarginPercent: lazy_load.fetch_margin_percent,
						renderMarginPercent: lazy_load.render_margin_percent,
						mobileScaling: lazy_load.mobile_scaling
					} );
				} );
			} )();
		</script>
		<?php

		echo apply_filters( 'newspack_ads_lazy_loading_js', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
GAM_Lazy_Load::init();
