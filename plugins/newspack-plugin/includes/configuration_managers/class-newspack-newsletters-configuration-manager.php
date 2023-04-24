<?php
/**
 * Newspack Newsletters plugin configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of Newspack Newsletters.
 */
class Newspack_Newsletters_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'newspack-newsletters';

	/**
	 * Configure Newspack Newsletters use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		return true;
	}

	/**
	 * Is Newspack Newsletters installed and connected?
	 *
	 * @return bool Plugin ready state.
	 */
	public function is_configured() {
		return class_exists( 'Newspack_Newsletters_Settings' );
	}

	/**
	 * Get Newspack Newsletters settings.
	 *
	 * @return object Newspack Newsletters settings.
	 */
	public function get_settings() {
		if ( ! $this->is_configured() ) {
			return [];
		}
		return \Newspack_Newsletters_Settings::get_settings_list();
	}

	/**
	 * Is Newspack Newsletters set up (any settings edited)?
	 *
	 * @return bool Plugin set up state.
	 */
	public function is_esp_set_up() {
		if ( $this->is_configured() ) {
			return \Newspack_Newsletters::is_service_provider_configured();
		}
	}

	/**
	 * Get configured ESP's lists.
	 *
	 * @return array Lists.
	 */
	public function get_lists() {
		if ( $this->is_configured() ) {
			return \Newspack_Newsletters::get_esp_lists();
		} else {
			return new \WP_Error(
				'newspack_missing_required_plugin',
				esc_html__( 'The Newspack Newsletters plugin is not installed and activated. Install and/or activate it to access this feature.', 'newspack' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}
	}

	/**
	 * Add a contact to ESP's list.
	 *
	 * @param array  $contact Contact info.
	 * @param string $list_id List ID.
	 * @return array Lists.
	 */
	public function add_contact( $contact, $list_id ) {
		if ( $this->is_configured() ) {
			return \Newspack_Newsletters_Subscription::add_contact( $contact, $list_id );
		}
	}

	/**
	 * Update Newspack Newsletters settings.
	 *
	 * @param object $update Settings update.
	 * @return object Newspack Newsletters settings.
	 */
	public function update_settings( $update ) {
		if ( $this->is_configured() ) {
			return \Newspack_Newsletters_Settings::update_settings( $update );
		}
	}
}
