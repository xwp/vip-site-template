<?php
/**
 * Bootstrap the WP testing environment.
 */

// Ensure the DB host is ready to accept connections.
$connection = new XWP\Wait_For\Tcp_Connection( 'db', 3306 );

try {
	$connection->connect( 10 );
} catch ( Exception $e ) {
	trigger_error( $e->getMessage(), E_USER_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Load WP unit test helper library.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

// Start up the WP testing environment.
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
