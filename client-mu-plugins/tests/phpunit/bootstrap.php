<?php
/**
 * Bootstrap the WP testing environment.
 *
 * @package XWP\VipSiteTemplate
 */

// Load WP unit test helper library.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

// Start up the WP testing environment.
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
