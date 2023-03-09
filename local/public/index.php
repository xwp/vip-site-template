<?php
/**
 * Load WP from the managed sub-directory.
 *
 * Prevents the WP docker container from installing
 * WP core files in this directory.
 *
 * @see https://github.com/docker-library/wordpress/blob/e665dbf6044556ace612e09ce1e01d92bb8d6a34/latest/php8.0/fpm/docker-entrypoint.sh#L71-L96
 *
 * @package XWP\Vip_Site_Template
 */

require_once __DIR__ . '/wp/index.php';
