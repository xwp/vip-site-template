<?php
/**
 * Newspack Ads Broadstreet Provider.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers;

use Newspack_Ads\Providers\Provider;
use Newspack_Ads\Core;
use Newspack_Ads\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Broadstreet.
 */
final class Broadstreet_Provider extends Provider {

	const CACHE = 'newspack_ads_broadstreet_zones';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->provider_id   = 'broadstreet';
		$this->provider_name = 'Broadstreet';
	}

	/**
	 * Whether Broadstreet plugin is installed.
	 *
	 * @return bool Whether Broadstreet plugin is installed.
	 */
	private static function is_plugin_active() {
		return class_exists( '\Broadstreet' ) && class_exists( '\Broadstreet_Utility' );
	}

	/**
	 * Whether the provider is enabled and ready to be used.
	 *
	 * @return bool Whether the provider is enabled and ready to be used.
	 */
	public function is_active() {
		if ( ! self::is_plugin_active() ) {
			return false;
		}
		if ( method_exists( '\Broadstreet_Utility', 'getApiKey' ) && ! \Broadstreet_Utility::getApiKey() ) {
			return false;
		}
		if ( method_exists( '\Broadstreet_Utility', 'getNetworkId' ) && ! \Broadstreet_Utility::getNetworkId() ) {
			return false;
		}
		return true;
	}

	/**
	 * The provider available units for placement.
	 *
	 * @param bool $refresh Whether refresh provider units data.
	 *
	 * @return array[
	 *  'name'  => string,
	 *  'value' => string,
	 *  'sizes' => array[]
	 * ] The provider available units for placement.
	 */
	public function get_units( $refresh = false ) {
		if ( ! self::is_plugin_active() || ! method_exists( '\Broadstreet_Utility', 'getZoneCache' ) ) {
			return [];
		}
		$zones = \get_option( self::CACHE, [] );
		/**
		 * If getting through the dashboard, fetch from plugin utility and update
		 * local cache.
		 */
		if ( true === $refresh ) {
			$zones = \Broadstreet_Utility::getZoneCache();
			\update_option( self::CACHE, $zones );
		}
		return array_map(
			function( $zone ) {
				$unit = [
					'name'  => $zone->name,
					'value' => (string) $zone->id,
					'sizes' => [],
				];
				if ( isset( $zone->width ) && isset( $zone->height ) ) {
					$unit['sizes'][] = [
						$zone->width,
						$zone->height,
					];
				}
				return $unit;
			},
			array_values( $zones )
		);
	}

	/**
	 * The ad code for rendering.
	 *
	 * @param string $unit_id        The unit ID.
	 * @param string $placement_key  Optional placement key.
	 * @param string $hook_key       Optional hook key, if the placement has multiple hooks.
	 * @param array  $placement_data Optional placement data.
	 *
	 * @return string $ad_code The ad code for rendering.
	 */
	public function get_ad_code( $unit_id, $placement_key = '', $hook_key = '', $placement_data = [] ) {
		if ( ! self::is_plugin_active() || ! method_exists( '\Broadstreet_Utility', 'getZoneCode' ) ) {
			return '';
		}
		/**
		 * Temporarily disabling "fixed height" support for Broadstreet due to
		 * unexpected results of rendered creatives sizes and zone sizes.
		 *
		 * Creatives can be larger than its defined zone size.
		 *
		 * The original value of $fixed_height is:
		 * `Settings::get_setting( 'fixed_height', 'active' )`
		 */
		$fixed_height = false;
		$attrs        = [];
		$zones        = $this->get_units();
		$zone_idx     = array_search( $unit_id, array_column( $zones, 'value' ) );
		if ( false === $zone_idx ) {
			return;
		}
		$zone = $zones[ $zone_idx ];
		if ( ! empty( $zone['sizes'] ) ) {
			$width = $zone['sizes'][0][0];
			if ( $width && 0 < absint( $width ) ) {
				$attrs['style'] = sprintf( 'width: %dpx;', $width );
			}
			if ( $fixed_height ) {
				$height = $zone['sizes'][0][1];
				if ( $height && 0 < absint( $height ) ) {
					$attrs['style'] = sprintf( '%s height: %dpx;', $attrs['style'], $height );
				}
			}
		} else {
			$attrs['style'] = 'flex: 1 1 100%; width: 100%; height: auto;';
		}
		$code_attrs = [];
		return sprintf(
			'<div class="newspack-broadstreet-ad" %s>%s</div>',
			implode(
				' ',
				array_map(
					function( $key, $value ) {
						return sprintf( "%s='%s'", $key, $value );
					},
					array_keys( $attrs ),
					array_values( $attrs )
				)
			),
			\Broadstreet_Utility::getZoneCode( $unit_id, $code_attrs )
		);
	}
}
