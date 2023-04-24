<?php
/**
 * Interface for a Newspack Ads Provider.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers;

/**
 * Provider Interface.
 */
interface Provider_Interface {
	/**
	 * The provider ID.
	 *
	 * @return string The provider ID.
	 */
	public function get_provider_id();

	/**
	 * The provider display name.
	 *
	 * @return string The provider display name.
	 */
	public function get_provider_name();

	/**
	 * Whether the provider is enabled and ready to be used.
	 *
	 * Be aware that this method is called on an ad render, so it should be fast
	 * and not do any heavy processing or HTTP requests.
	 *
	 * @return bool Whether the provider is enabled and ready to be used.
	 */
	public function is_active();

	/**
	 * The provider available units for placement.
	 *
	 * @param bool $refresh Whether refresh provider units data.
	 *
	 * @return array[
	 *  'name'  => string,
	 *  'value' => string
	 * ] The provider available units for placement.
	 */
	public function get_units( $refresh = false );

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
	public function get_ad_code( $unit_id, $placement_key = '', $hook_key = '', $placement_data = [] );

	/**
	 * Render the ad code for the given placement.
	 *
	 * @param string $unit_id        The unit ID.
	 * @param string $placement_key  Optional placement key.
	 * @param string $hook_key       Optional hook key, if the placement has multiple hooks.
	 * @param array  $placement_data Optional placement data.
	 *
	 * @return void
	 */
	public function render_code( $unit_id, $placement_key = '', $hook_key = '', $placement_data = [] );
}
