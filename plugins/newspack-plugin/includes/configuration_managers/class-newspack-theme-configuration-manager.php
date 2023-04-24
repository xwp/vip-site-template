<?php
/**
 * Newspack Theme plugin configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of AMP.
 */
class Newspack_Theme_Configuration_Manager extends Configuration_Manager {

	/**
	 * Configure Newspack Theme for Newspack use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		set_theme_mod( 'theme_colors', 'default' );
		return true;
	}
}
