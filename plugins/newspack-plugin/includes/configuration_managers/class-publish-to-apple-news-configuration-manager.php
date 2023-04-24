<?php
/**
 * Publish to Apple News plugin configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of Publish to Apple News.
 */
class Publish_To_Apple_News_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'publish-to-apple-news';

	/**
	 * Configure Publish to Apple News for Newspack use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		$active = $this->is_active();
		if ( ! $active || is_wp_error( $active ) ) {
			return $active;
		}
		if ( class_exists( 'Admin_Apple_Settings' ) ) {
			$defaults = new \Apple_Exporter\Settings();
			$settings = new \Admin_Apple_Settings();
			$settings->save_settings( $defaults->all() );
		}

		$this->set_newspack_has_configured( true );
		return true;
	}
}
