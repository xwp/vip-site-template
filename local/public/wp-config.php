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

$table_prefix = 'wp_';

require_once ABSPATH . 'wp-settings.php';
