<?php
/**
 * Site Kit plugin configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of Google Site Kit.
 */
class Site_Kit_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'google-site-kit';

	/**
	 * The option name for Site Kit active modules.
	 *
	 * @var string
	 */
	public $active_modules_option = 'googlesitekit_active_modules';

	/**
	 * If Site Kit is active and set up, this will return info about all of the modules. Otherwise, this will return an empty array. See link below for array format.
	 *
	 * @see https://github.com/google/site-kit-wp/blob/9a262cd18c33995ce5ec81bc300ff055dff2a153/includes/Core/Modules/Modules.php#L123-L137
	 * @return array
	 */
	public function get_modules_info() {
		if ( empty( get_option( 'googlesitekit_credentials' ) ) ) {
			return [];
		}

		return apply_filters( 'googlesitekit_modules_data', [] );
	}

	/**
	 * Get info about one module. See `get_modules_info` docblock for link to info format.
	 * Recognized modules are: 'search-console', 'adsense', 'analytics', 'pagespeed-insights', 'optimize', 'tagmanager'.
	 *
	 * @param string $module The module slug.
	 * @return array Module info if module is found, otherwise empty array.
	 */
	public function get_module_info( $module ) {
		$modules = $this->get_modules_info();
		if ( empty( $modules[ $module ] ) ) {
			return [];
		}
		return $modules[ $module ];
	}

	/**
	 * Get whether a specific module is configured.
	 *
	 * @param string $module The module slug. See `get_module_info` for valid slugs.
	 * @return bool Whether the module is configured.
	 */
	public function is_module_configured( $module ) {
		$module_info = $this->get_module_info( $module );
		return ! empty( $module_info['setupComplete'] );
	}

	/**
	 * Check if module is active.
	 *
	 * @param string $module The module slug. See `get_module_info` for valid slugs.
	 * @return boolean True if module is active, otherwise false.
	 */
	public function is_module_active( $module ) {
		$sitekit_active_modules = get_option( $this->active_modules_option, [] );
		return in_array( $module, $sitekit_active_modules );
	}

	/**
	 * Activate a module if it isn't already active.
	 *
	 * @param string $module The module slug. See `get_module_info` for valid slugs.
	 */
	public function activate_module( $module ) {
		$sitekit_active_modules = get_option( $this->active_modules_option, [] );
		if ( ! in_array( $module, $sitekit_active_modules ) ) {
			$sitekit_active_modules[] = $module;
			update_option( $this->active_modules_option, $sitekit_active_modules );
		}
	}

	/**
	 * Deactivate a module if it possible.
	 *
	 * @param string $module The module slug. See `get_module_info` for valid slugs.
	 */
	public function deactivate_module( $module ) {
		$sitekit_active_modules = get_option( $this->active_modules_option, [] );
		$updated_modules        = [];

		foreach ( $sitekit_active_modules as $active_module ) {
			if ( $module !== $active_module ) {
				$updated_modules[] = $active_module;
			}
		}

		update_option( $this->active_modules_option, $updated_modules );
	}

	/**
	 * Get whether the Site Kit plugin is active and set up.
	 *
	 * @return bool Whether Site Kit is active and set up.
	 */
	public function is_configured() {
		global $wpdb;

		$user     = get_current_user_id();
		$meta_key = $wpdb->get_blog_prefix() . 'googlesitekit_access_token';
		$token    = get_user_meta( $user, $meta_key, true );

		return $this->is_active() && ! empty( $token );
	}

	/**
	 * Configure Site Kit for Newspack use.
	 * This can't actually do anything, since Site Kit is partially set up in Google.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		return true;
	}
}
