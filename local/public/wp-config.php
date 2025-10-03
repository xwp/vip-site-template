<?php
/**
 * Config file used only during local development.
 *
 * phpcs:disable WordPress.WP.CapitalPDangit.Misspelled, WordPress.WP.GlobalVariablesOverride.Prohibited
 *
 * @package XWP\VIP_Site_Template
 */

// On VIP this is actually loaded from /vip-config/vip-config.php.
require_once __DIR__ . '/wp-content/plugins/vendor/autoload.php';

// Configured in docker-compose.yml.
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Multisite config.
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', true );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_DEBUG_LOG', true );
define( 'SCRIPT_DEBUG', true );

// Define the local environment.
define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
define( 'VIP_GO_ENV', 'local' );

// Set the default theme for new sites.
define( 'WP_DEFAULT_THEME', 'twentytwentyfive' );

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

/**
 * Enable ElasticSearch integration, passed to EP_HOST in VIP mu-plugins.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins/blob/508123df5b0f36cc0b33eff5b1c91d3d5204b71b/search/includes/classes/class-search.php#L466-L471
 */
define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'http://elasticsearch:9200' ] );
define( 'VIP_ELASTICSEARCH_USERNAME', 'elastic' );
define( 'VIP_ELASTICSEARCH_PASSWORD', 'changeme' );
define( 'FILES_CLIENT_SITE_ID', 123456 ); // Fake client site ID.

// Enable Xdebug during CLI requests.
if ( 'cli' === php_sapi_name() && function_exists( 'xdebug_connect_to_client' ) ) {
	xdebug_connect_to_client();
}

// Ensure the DB host is ready to accept connections.
$connection = new XWP\Wait_For\Tcp_Connection( DB_HOST, 3306 );

try {
	$connection->connect( 30 ); // DB container might take a while when starting from scratch.
} catch ( Exception $e ) {
	trigger_error( $e->getMessage(), E_USER_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Match the VIP production behaviour on local.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins/blob/ab1f0ac12b1690d27a175baac10729cc7a0060ff/000-pre-vip-config/requires.php
 */
if ( file_exists( __DIR__ . '/wp-content/mu-plugins/000-pre-vip-config/requires.php' ) ) {
	require_once __DIR__ . '/wp-content/mu-plugins/000-pre-vip-config/requires.php';
}

// Include VIP-specific config.
if ( file_exists( __DIR__ . '/vip-config/vip-config.php' ) ) {
	require __DIR__ . '/vip-config/vip-config.php';
}

$table_prefix = 'wp_';

require_once ABSPATH . 'wp-settings.php';
