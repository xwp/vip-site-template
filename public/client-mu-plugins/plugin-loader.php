<?php
/**
 * Bootstrap all must-use plugins.
 */

/**
 * List of relative paths to plugin bootstrap files.
 *
 * @var array
 */
$mu_plugin_files = [
	// 'example-plugin/plugin.php',
];

foreach ( $mu_plugin_files as $mu_plugin_file ) {
	$mu_plugin_file_path = sprintf( '%s/%s', __DIR__, $mu_plugin_file );

	if ( file_exists( $mu_plugin_file_path ) ) {
		require_once $mu_plugin_file_path;
	}
}
