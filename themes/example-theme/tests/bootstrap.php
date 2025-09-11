<?php
/**
 * Bootstrap the WP test environment.
 *
 * @package XWP\VIP_Site_Template\Theme
 *
 * phpcs:disable WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
 */

// WP core test suite will make these the option values automatically.
global $wp_tests_options;
$test_theme_basename = basename( dirname( __DIR__ ) );
$wp_tests_options    = [
	'template'       => $test_theme_basename,
	'stylesheet'     => $test_theme_basename,
	'current_theme'  => $test_theme_basename,
	'active_plugins' => [
		// List of plugins required for unit tests.
		'action-scheduler/action-scheduler.php',
		'wpcom-legacy-redirector/wpcom-legacy-redirector.php',
	],
];

// Load WP unit test helper library.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

// Start up the WP testing environment.
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
