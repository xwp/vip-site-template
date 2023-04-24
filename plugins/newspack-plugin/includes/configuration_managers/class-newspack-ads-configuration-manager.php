<?php
/**
 * Newspack Ads Configuration Manager
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

use Newspack_Ads\Providers\GAM_Model;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of Newspack Ads.
 */
class Newspack_Ads_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'newspack-ads';

	/**
	 * Get whether the Site Kit plugin is active and set up.
	 *
	 * @return bool Whether Newspack Ads is installed and activated.
	 */
	public function is_configured() {
		return class_exists( '\Newspack_Ads\Providers\GAM_Model' );
	}

	/**
	 * Initial GAM setup
	 *
	 * @return object|WP_Error Setup results or error if it fails.
	 */
	public function setup_gam() {
		return $this->is_configured() ?
			GAM_Model::setup_gam() :
			$this->unconfigured_error();
	}

	/**
	 * Update GAM credentials.
	 *
	 * @param array $credentials Credentials to update.
	 *
	 * @return object|WP_Error Connection status or error if it fails.
	 */
	public function update_gam_credentials( $credentials ) {
		return $this->is_configured() ?
			GAM_Model::update_service_account_credentials( $credentials ) :
			$this->unconfigured_error();
	}

	/**
	 * Remove GAM credentials.
	 *
	 * @return object|WP_Error Connection status or error if it fails.
	 */
	public function remove_gam_credentials() {
		return $this->is_configured() ?
			GAM_Model::remove_service_account_credentials() :
			$this->unconfigured_error();
	}

	/**
	 * Get the ad units from our saved option.
	 *
	 * @return array | WP_Error Array of ad units or WP_Error if Newspack Ads isn't installed and activated.
	 */
	public function get_ad_units() {
		return $this->is_configured() ?
			GAM_Model::get_ad_units() :
			$this->unconfigured_error();
	}

	/**
	 * Get a single ad unit.
	 *
	 * @param number $id The id of the ad unit to retrieve.
	 * @param string $placement The id of the placement region.
	 * @return array | WP_Error Returns ad unit or error if the plugin is not active or the ad unit doesn't exist.
	 */
	public function get_ad_unit_for_display( $id, $placement = null ) {
		return $this->is_configured() ?
			GAM_Model::get_ad_unit_for_display( $id, $placement ) :
			$this->unconfigured_error();
	}

	/**
	 * Add a new ad unit.
	 *
	 * @param array $ad_unit The new ad unit info to add.
	 * @return array | WP_Error Returns new ad unit or error if the plugin is not active or the ad unit exists already.
	 */
	public function add_ad_unit( $ad_unit ) {
		return $this->is_configured() ?
			GAM_Model::add_ad_unit( $ad_unit ) :
			$this->unconfigured_error();
	}

	/**
	 * Update an ad unit
	 *
	 * @param array $ad_unit The updated ad unit.
	 * @return array | WP_Error Returns updated ad unit or error if the plugin is not active or the ad unit doesn't exist.
	 */
	public function update_ad_unit( $ad_unit ) {
		return $this->is_configured() ?
			GAM_Model::update_ad_unit( $ad_unit ) :
			$this->unconfigured_error();
	}

	/**
	 * Delete an ad unit
	 *
	 * @param integer $id The id of the ad unit to delete.
	 * @return bool | WP_Error Returns true if deletion is successful, or error if the plugin is not active or the ad unit doesn't exist.
	 */
	public function delete_ad_unit( $id ) {
		return $this->is_configured() ?
			GAM_Model::delete_ad_unit( $id ) :
			$this->unconfigured_error();
	}

	/**
	 * Is GAM connected?
	 *
	 * @return bool | WP_Error Returns true if GAM is not connected, or error if the plugin is not active.
	 */
	public function is_gam_connected() {
		return $this->is_configured() ?
			GAM_Model::is_api_connected() :
			$this->unconfigured_error();
	}

	/**
	 * Get GAM connection status.
	 *
	 * @return bool | WP_Error Returns object, or error if the plugin is not active.
	 */
	public function get_gam_connection_status() {
		return $this->is_configured() ?
			GAM_Model::get_connection_status() :
			$this->unconfigured_error();
	}

	/**
	 * Get GAM available networks.
	 *
	 * @return bool | WP_Error Returns object, or error if the plugin is not active.
	 */
	public function get_gam_available_networks() {
		return $this->is_configured() ?
			GAM_Model::get_available_networks() :
			$this->unconfigured_error();
	}

	/**
	 * Error to return if the plugin is not installed and activated.
	 *
	 * @return WP_Error
	 */
	private function unconfigured_error() {
		return new \WP_Error(
			'newspack_missing_required_plugin',
			esc_html__( 'The Newspack Ads plugin is not installed and activated. Install and/or activate it to access this feature.', 'newspack' ),
			[
				'status' => 400,
				'level'  => 'fatal',
			]
		);
	}

	/**
	 * Check if a service is enabled.
	 *
	 * @param string $service Service name.
	 * @return bool Is the service enabled.
	 */
	public function is_service_enabled( $service ) {
		return get_option( Advertising_Wizard::NEWSPACK_ADVERTISING_SERVICE_PREFIX . $service, false );
	}

	/**
	 * Configure Newspack Ads for Newspack use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		return true;
	}
}
