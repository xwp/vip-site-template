<?php
/**
 * Newspack theme manager
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing installation/activation of Newspack theme and child themes.
 */
class Theme_Manager {

	/**
	 * Array of valid Newspack theme slugs.
	 *
	 * @var array
	 */
	public static $theme_slugs = [
		'newspack-theme', // Default.
		'newspack-scott', // Style Pack 1.
		'newspack-nelson', // Style Pack 2.
		'newspack-katharine', // Style Pack 3.
		'newspack-sacha', // Style Pack 4.
		'newspack-joseph', // Style Pack 5.
	];

	/**
	 * Install and activate the Newspack theme or a child theme.
	 *
	 * @param string $slug Slug of the theme to install.
	 */
	public static function install_activate_theme( $slug = 'newspack-theme' ) {
		if ( ! self::can_install_themes() ) {
			return new WP_Error( 'newspack_theme_failed_install', __( 'Themes cannot be installed.', 'newspack' ) );
		}

		if ( ! in_array( $slug, self::$theme_slugs ) ) {
			return new \WP_Error(
				'newspack_theme_invalid_slug',
				__( 'Invalid theme slug.', 'newspack' )
			);
		}

		$theme_url = "https://github.com/Automattic/newspack-theme/releases/latest/download/{$slug}.zip";

		$theme_object = wp_get_theme( $slug );
		if ( ! $theme_object->exists() ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/theme.php';
			WP_Filesystem();

			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \Theme_Upgrader( $skin );
			$success  = $upgrader->install( $theme_url );

			if ( is_wp_error( $success ) ) {
				return $success;
			} elseif ( $success ) {
				// Make sure `-master` or `-1.0.1` etc. are not in the theme folder name.
				// We just want the folder name to be the theme slug.
				$theme_object    = $upgrader->theme_info();
				$theme_folder    = $theme_object->get_stylesheet_directory();
				$expected_folder = $theme_object->get_theme_root() . '/' . $slug;
				if ( $theme_folder !== $expected_folder ) {
					rename( $theme_folder, $expected_folder ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_rename
				}
			} else {
				return new \WP_Error(
					'newspack_theme_failed_install',
					__( 'Newspack theme installation failed.', 'newspack' )
				);
			}
		}

		switch_theme( $slug );
		return true;
	}

	/**
	 * Determine whether theme installation is allowed in the current environment.
	 *
	 * @return bool
	 */
	public static function can_install_themes() {
		if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ||
			( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
			return false;
		}

		return true;
	}
}
