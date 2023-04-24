<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing
/**
 * Route Newspack Campaigns API requests based on method.
 *
 * @package Newspack
 */

require_once '../setup.php';

switch ( $_SERVER['REQUEST_METHOD'] ) { //phpcs:ignore
	case 'POST':
		include './class-segmentation-client-data.php';
		break;
	default:
		header( 'HTTP/1.0 404 Not Found' );
		header( 'Content-Type: application/json' );
		die( "{ error: 'unsupported_method' }" );
}
