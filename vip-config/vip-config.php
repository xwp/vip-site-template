<?php
/**
 * VIP-Go specific wp-config.php.
 *
 * Mapped to vip-config/vip-config.php in the root of the WordPress installation on the VIP servers.
 *
 * WARNING: This file is loaded very early (immediately after `wp-config.php`), which means that most WordPress APIs,
 *   classes, and functions are not available. The code below should be limited to pure PHP.
 *
 * @see https://vip.wordpress.com/documentation/vip-go/understanding-your-vip-go-codebase/
 */

// Enable Composer autoloader.
require dirname( __DIR__ ) . '/wp-content/plugins/vendor/autoload.php';
