<?php
/**
 * PHPUnit config used only for tests.
 *
 * @package XWP\VipSiteTemplate
 */

// In case this is loaded before WP core.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp/' );
}

// Use the default config for the base.
require_once __DIR__ . '/wp-config.php';

// Use a dedicated prefix for the test tables.
$table_prefix = 'tests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
