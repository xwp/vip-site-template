<?php
/**
 * PHPUnit config used only for tests.
 *
 * @package XWP\Vip_Site_Template
 */

// In case this is loaded before WP core.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp/' );
}

// Configured in docker-compose.yml.
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Report all PHP errors.
error_reporting( -1 );

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );

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

// Account for VIP mu-plugins object-cache.php bootstrap logic not checking for the presence of the constant.
if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	define( 'VIP_GO_APP_ENVIRONMENT', false );
}

/**
 * Enable ElasticSearch integration, passed to EP_HOST in VIP mu-plugins.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins/blob/508123df5b0f36cc0b33eff5b1c91d3d5204b71b/search/includes/classes/class-search.php#L466-L471
 */
define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'http://elasticsearch:9200' ] );
define( 'VIP_ELASTICSEARCH_USERNAME', 'elastic' );
define( 'VIP_ELASTICSEARCH_PASSWORD', 'changeme' );
define( 'FILES_CLIENT_SITE_ID', 123456 ); // Fake client site ID.

// Include VIP-specific config.
if ( file_exists( __DIR__ . '/vip-config/vip-config.php' ) ) {
	require __DIR__ . '/vip-config/vip-config.php';
}

// Ensure the DB host is ready to accept connections.
$connection = new XWP\Wait_For\Tcp_Connection( DB_HOST, 3306 );

try {
	$connection->connect( 30 );
} catch ( Exception $e ) {
	trigger_error( $e->getMessage(), E_USER_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Use a dedicated prefix for the test tables.
$table_prefix = 'tests_';

define( 'WP_TESTS_MULTISITE', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
