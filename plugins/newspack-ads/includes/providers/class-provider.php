<?php
/**
 * Newspack Ads Provider Abstract Class.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers;

use Newspack_Ads\Providers\Provider_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Provider.
 */
abstract class Provider implements Provider_Interface {
	/**
	 * The provider ID.
	 *
	 * @var string
	 */
	protected $provider_id;

	/**
	 * The provider name.
	 *
	 * @var string
	 */
	protected $provider_name;

	/**
	 * The provider ID.
	 *
	 * @return string The provider ID.
	 */
	public function get_provider_id() {
		return $this->provider_id;
	}

	/**
	 * The provider display name.
	 *
	 * @return string The provider display name.
	 */
	public function get_provider_name() {
		return $this->provider_name;
	}

	/**
	 * Whether the provider is enabled and ready to be used. By default, a
	 * registered provider is active.
	 *
	 * Be aware that this method is called on an ad render, so it should be fast
	 * and not do any heavy processing or HTTP requests.
	 *
	 * @return bool Whether the provider is enabled and ready to be used.
	 */
	public function is_active() {
		return true;
	}

	/**
	 * Render the ad code for the given placement.
	 *
	 * @param string $unit_id        The unit ID.
	 * @param string $placement_key  Optional placement key.
	 * @param string $hook_key       Optional hook key, if the placement has multiple hooks.
	 * @param array  $placement_data Optional placement data.
	 */
	public function render_code( $unit_id, $placement_key = '', $hook_key = '', $placement_data = [] ) {
		if ( ! $this->is_active() ) {
			return;
		}
		do_action( 'newspack_ads_provider_before_render_code', self::get_provider_id(), $unit_id, $placement_key, $hook_key, $placement_data );
		echo $this->get_ad_code( $unit_id, $placement_key, $hook_key, $placement_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action( 'newspack_ads_provider_after_render_code', self::get_provider_id(), $unit_id, $placement_key, $hook_key, $placement_data );
	}
}
