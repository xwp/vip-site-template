<?php
/**
 * Setup Campaigns API.
 *
 * @package Newspack
 */

// @codeCoverageIgnoreStart
$wp_root_path = substr( $_SERVER['SCRIPT_FILENAME'], 0, strrpos( $_SERVER['SCRIPT_FILENAME'], 'wp-content/plugins/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated

if ( file_exists( $wp_root_path . '__wp__' ) ) {
	define( 'ABSPATH', $wp_root_path . '__wp__/' );
} else {
	define( 'ABSPATH', $wp_root_path );
}

define( 'WPINC', 'wp-includes/' );
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content/' );
}
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

$legacy_config_path = $wp_root_path . 'newspack-popups-config.php';
$config_path        = rtrim( WP_CONTENT_DIR, '/' ) . '/newspack-popups-config.php';
if ( file_exists( $legacy_config_path ) ) {
	require_once $legacy_config_path;
} elseif ( file_exists( $config_path ) ) {
	require_once $config_path;
} else {
	header( 'HTTP/1.0 503 Service Unavailable' );
	header( 'Content-Type: application/json' );
	die( "{ error: 'missing_config_file' }" );
}

// phpcs:disable

function __( $in ) { return $in; }
function wp_load_translations_early() {return null;}
function add_filter() {}
function do_action() {}
function add_action() {}
function has_filter() { return false;}
function apply_filters( $f, $in ) { return $in; }
function is_multisite() { return false; }
function is_wp_error( $thing ) { return ( $thing instanceof WP_Error ); }
function trailingslashit( $string ) {
	return rtrim( $string, '/\\' ) . '/';
}

if ( file_exists( ABSPATH . WPINC . '/wp-db.php' ) ) {
	require_once ABSPATH . WPINC . '/compat.php';
	require_once ABSPATH . WPINC . '/wp-db.php';
	require_once ABSPATH . WPINC . '/functions.php';
} else {
	header( 'HTTP/1.0 503 Service Unavailable' );
	header( 'Content-Type: application/json' );
	die( "{ error: 'no_wordpress' }" );
}

if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
	require WP_CONTENT_DIR . '/object-cache.php';
} else {
	require_once ABSPATH . WPINC . '/cache.php';
}

global $wpdb;
$db_prefix = defined( 'DB_PREFIX' ) ? DB_PREFIX : 'wp_';
$wpdb      = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$wpdb->set_prefix( $db_prefix );

global $table_prefix;
$table_prefix = $db_prefix;

if ( function_exists( 'wp_cache_init' ) ) {
	wp_cache_init();
} else {
	function wp_cache_init() {}
	function wp_cache_get() {}
	function wp_cache_set() {}
}

// phpcs:enable

require_once 'segmentation/class-segmentation.php';

// @codeCoverageIgnoreEnd
