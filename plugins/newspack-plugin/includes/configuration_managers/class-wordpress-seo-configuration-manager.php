<?php
/**
 * Yoast (WordPress SEO) plugin configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of Jetpack.
 */
class WordPress_SEO_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'wordpress_seo';

	/**
	 * Configure Yoast for Newspack use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		return true;
	}

	/**
	 * Is Yoast installed and connected?
	 *
	 * @return bool Plugin ready state.
	 */
	public function is_configured() {
		if ( $this->is_active() && class_exists( 'WPSEO_Options' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get Yoast option.
	 *
	 * @param string $key Key of the option to return.
	 * @param string $default Default value of the option.
	 * @return string|object Returns value of option.
	 */
	public function get_option( $key, $default = '' ) {
		return $this->is_configured() ?
			\WPSEO_Options::get( $key, $default ) :
			$this->unconfigured_error();
	}

	/**
	 * Set Yoast option.
	 *
	 * @param string $key Key of the option to return.
	 * @param string $value Value of the option.
	 */
	public function set_option( $key, $value ) {
		return $this->is_configured() ?
			\WPSEO_Options::set( $key, $value ) :
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
			esc_html__( 'Yoast plugin is not installed and activated. Install and/or activate it to access this feature.', 'newspack' ),
			[
				'status' => 400,
				'level'  => 'fatal',
			]
		);
	}
}
