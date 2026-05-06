<?php
/**
 * Include the object cache from the VIP mu-plugin bundle.
 *
 * Used on local only.
 *
 * @package XWP\VIP_Site_Template
 */

// During PHPUnit tests, use WP's built-in in-memory object cache.
// The VIP Memcached drop-in's incr() has no local fallback, causing
// false returns when the Memcache server is unreachable from tests.
if ( defined( 'PHPUNIT_COMPOSER_INSTALL' ) ) {
	return;
}

if ( file_exists( __DIR__ . '/mu-plugins/drop-ins/object-cache.php' ) ) {
	require_once __DIR__ . '/mu-plugins/drop-ins/object-cache.php';
}
