<?php
/**
 * Config file used only during local development.
 *
 * phpcs:disable WordPress.WP.CapitalPDangit.Misspelled, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'db' );

define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );

// Set the default theme for new sites.
define( 'WP_DEFAULT_THEME', 'twentytwentytwo' );

// Enable offline mode to ensure it doesn't connect to WP.com.
define( 'JETPACK_DEV_DEBUG', true ); // phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.DefiningRestrictedConstant

// Use Composer and Git to update plugins and themes.
define( 'DISALLOW_FILE_MODS', true );
define( 'DISALLOW_FILE_EDIT', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );

// Keep the wp-contents outside of WP core directory.
define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );

// Ensure object-cache.php knows where to load the assets from.
if ( ! defined( 'WPMU_PLUGIN_DIR' ) && defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );
}

// Define the local environment.
define( 'VIP_GO_APP_ENVIRONMENT', 'local' );

// Respond as if we were on HTTPS.
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

// Define our custom Memcached server for object caching.
$memcached_servers = [
	'default' => [
		'memcached:11211',
	],
];

// Include VIP-specific config.
require __DIR__ . '/vip-config/vip-config.php';

$table_prefix = 'wp_';

require_once ABSPATH . 'wp-settings.php';
