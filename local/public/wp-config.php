<?php
/**
 * Config file used only during local development.
 *
 * phpcs:disable WordPress.WP.CapitalPDangit.Misspelled
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'db' );

define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_DEBUG', true );

// Use a custom wp-content directory to avoid including the default themes.
define( 'WP_CONTENT_DIR', __DIR__ . '/content' );

if ( defined( 'WP_HOME' ) && WP_HOME ) {
	define( 'WP_CONTENT_URL', WP_HOME . '/content' );
}

// Include VIP-specific config.
require __DIR__ . '/content/vip-config/vip-config.php';

$table_prefix = 'wp_';

require_once ABSPATH . 'wp-settings.php';
