<?php
/**
 * Static class for managing all configuration managers.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;
defined( 'ABSPATH' ) || exit;

/**
 * List and instantiate configuration managers.
 */
class Configuration_Managers {
	/**
	 * A registry of all configuration managers, keyed to the plugin slug.
	 *
	 * @var array
	 */
	protected static $configuration_managers = [
		'jetpack'               => [
			'filename'   => 'class-jetpack-configuration-manager.php',
			'class_name' => 'Jetpack_Configuration_Manager',
		],
		'google-site-kit'       => [
			'filename'   => 'class-site-kit-configuration-manager.php',
			'class_name' => 'Site_Kit_Configuration_Manager',
		],
		'progressive-wp'        => [
			'filename'   => 'class-progressive-wp-configuration-manager.php',
			'class_name' => 'Progressive_WP_Configuration_Manager',
		],
		'newspack-ads'          => [
			'filename'   => 'class-newspack-ads-configuration-manager.php',
			'class_name' => 'Newspack_Ads_Configuration_Manager',
		],
		'newspack-popups'       => [
			'filename'   => 'class-newspack-popups-configuration-manager.php',
			'class_name' => 'Newspack_Popups_Configuration_Manager',
		],
		'newspack-newsletters'  => [
			'filename'   => 'class-newspack-newsletters-configuration-manager.php',
			'class_name' => 'Newspack_Newsletters_Configuration_Manager',
		],
		'woocommerce'           => [
			'filename'   => 'class-woocommerce-configuration-manager.php',
			'class_name' => 'WooCommerce_Configuration_Manager',
		],
		'newspack-theme'        => [
			'filename'   => 'class-newspack-theme-configuration-manager.php',
			'class_name' => 'Newspack_Theme_Configuration_Manager',
		],
		'publish-to-apple-news' => [
			'filename'   => 'class-publish-to-apple-news-configuration-manager.php',
			'class_name' => 'Publish_To_Apple_News_Configuration_Manager',
		],
		'wordpress_seo'         => [
			'filename'   => 'class-wordpress-seo-configuration-manager.php',
			'class_name' => 'WordPress_SEO_Configuration_Manager',
		],
		'wp-parsely'            => [
			'filename'   => 'class-parsely-configuration-manager.php',
			'class_name' => 'Parsely_Configuration_Manager',
		],
	];

	/**
	 * Convenience class to instantiate a plugin's configuration manager.
	 *
	 * @param string $slug The plugin slug.
	 * @var Configuration_Manager || WP_Error
	 */
	public static function configuration_manager_class_for_plugin_slug( $slug ) {
		if ( empty( self::$configuration_managers[ $slug ] ) ) {
			return new WP_Error(
				'newspack_configuration_manager_not_found',
				esc_html__( 'No configuration manager exists for this plugin.', 'newspack' )
			);
		}
		require_once NEWSPACK_ABSPATH . 'includes/configuration_managers/' . self::$configuration_managers[ $slug ]['filename'];

		$classname     = 'Newspack\\' . self::$configuration_managers[ $slug ]['class_name'];
		$instantiation = new $classname();

		return $instantiation;
	}

	/**
	 * Convenience class to configure a plugin.
	 *
	 * @param string $slug The plugin slug.
	 * @var bool || WP_Error
	 */
	public static function configure( $slug ) {
		$configuration_manager = self::configuration_manager_class_for_plugin_slug( $slug );
		if ( is_wp_error( $configuration_manager ) ) {
			return $configuration_manager;
		}
		return $configuration_manager->configure();
	}

	/**
	 * Convenience class to get configuration status of a plugin.
	 *
	 * @param string $slug The plugin slug.
	 * @var bool
	 */
	public static function is_configured( $slug ) {
		if ( Newspack::is_debug_mode() ) {
			return true;
		}
		$configuration_manager = self::configuration_manager_class_for_plugin_slug( $slug );
		if ( is_wp_error( $configuration_manager ) ) {
			return false;
		}
		return $configuration_manager->is_configured();
	}
}
