<?php
/**
 * Newspack Ads Fixed Height Settings.
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Core;
use Newspack_Ads\Settings;

/**
 * Newspack Ads Fixed Height Class.
 */
final class Fixed_Height {

	const SECTION = 'fixed_height';
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'newspack_ads_settings_list', [ __CLASS__, 'register_settings' ] );
	}

	/**
	 * Register Fixed Height settings.
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
			$settings_list,
			[
				[
					'description' => __( 'Fixed height', 'newspack-ads' ),
					'help'        => __( 'If enabled, ad slots will be rendered with a fixed height determined by the unit\'s sizes configuration.', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => true,
					'public'      => true,
				],
				[
					'description' => __( 'Set a maximum fixed height', 'newspack-ads' ),
					'help'        => __( 'If enabled, rendered creatives larger than "maximum fixed height" will adjust the height accordingly and cause layout shift.', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'use_max_height',
					'type'        => 'boolean',
					'default'     => true,
					'public'      => true,
				],
				[
					'description' => __( 'Maximum fixed height', 'newspack-ads' ),
					'help'        => __( 'Maximum value for the fixed height applied to the ad slot, in pixels.', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'max_height',
					'type'        => 'int',
					'default'     => 100,
					'public'      => true,
				],
			]
		);
	}
}
Fixed_Height::init();
