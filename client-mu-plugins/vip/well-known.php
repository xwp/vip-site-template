<?php
/**
 * Add a .well-known services directory.
 *
 * @link https://tools.ietf.org/html/rfc8615
 */

add_action(
	'template_redirect',
	function() {
		$request = ! empty( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : false;
		$dir     = WP_CONTENT_DIR . '/vip-config';

		if (
			$request &&
			0 === strpos( $request, '/.well-known/' ) &&
			! is_dir( $dir . $request ) &&
			file_exists( $dir . $request ) &&
			0 === validate_file( $dir . $request )
		) {
			// Since this is serving public files from the .well-known directory, do not escape.
			echo file_get_contents( $dir . $request ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			status_header( 200 );
			exit;
		}
	},
	-100
);
