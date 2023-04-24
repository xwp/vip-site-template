<?php
/**
 * Newspack Ads Google Ad Manager Provider.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers;

use Newspack_Ads\Core;
use Newspack_Ads\Providers\Provider;
use Newspack_Ads\Providers\GAM_Model;

defined( 'ABSPATH' ) || exit;

require_once 'api/class-api.php';
require_once 'class-gam-model.php';
require_once 'class-gam-lazy-load.php';
require_once 'class-gam-scripts.php';
require_once 'class-gam-ad-block-recovery.php';

/**
 * Google Ad Manager.
 */
final class GAM_Provider extends Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->provider_id   = 'gam';
		$this->provider_name = 'Google Ad Manager';
	}

	/**
	 * Whether the provider is enabled and ready to be used.
	 *
	 * Temporarily use the same option used for toggling Google Ad Manager on
	 * the Newspack "Ad Providers" settings page.
	 *
	 * @return bool Whether the provider is enabled and ready to be used.
	 */
	public function is_active() {
		return get_option( '_newspack_advertising_service_google_ad_manager', false );
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
		$ad_units = GAM_Model::get_ad_units( $refresh );
		return array_map(
			function( $ad_unit ) {
				return [
					'name'  => $ad_unit['name'],
					'value' => (string) $ad_unit['id'],
					'sizes' => $ad_unit['sizes'],
				];
			},
			array_values( $ad_units )
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
		$ad_unit = GAM_Model::get_ad_unit_for_display(
			$unit_id,
			array(
				'unique_id' => $placement_data['id'],
				'placement' => $placement_key,
			)
		);
		if ( \is_wp_error( $ad_unit ) ) {
			return '';
		}
		$is_amp = Core::is_amp();
		$code   = $is_amp ? $ad_unit['amp_ad_code'] : $ad_unit['ad_code'];
		if ( empty( $code ) ) {
			return '';
		}
		if ( 'sticky' === $placement_key && true === $is_amp ) {
			$sticky_attrs = [
				'class'  => 'newspack_amp_sticky_ad',
				'layout' => 'nodisplay',
			];
			$code         = sprintf(
				'<amp-sticky-ad %s>%s</amp-sticky-ad>',
				implode(
					' ',
					array_map(
						function( $key, $value ) {
							return sprintf( "%s='%s'", $key, $value );
						},
						array_keys( $sticky_attrs ),
						array_values( $sticky_attrs )
					)
				),
				$code
			);
		}
		return $code;
	}
}
