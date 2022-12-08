<?php
/**
 * Plugin Name: Jetpack Config
 */

/**
 * Ensure that we always return a HTTP 404 response code when
 * the main query thinks this is a 404 page.
 *
 * Prevent Jetpack from shortcircuiting the WP core
 * 404 response setter.
 *
 * @see https://github.com/Automattic/jetpack/blob/741ac8af8cefec57a11d6844c4dcba05b277483c/projects/plugins/jetpack/modules/search/class.jetpack-search.php#L628-L634
 * @see https://wordpressvip.zendesk.com/hc/en-us/requests/125184
 */
add_action(
	'template_redirect',
	function () {
		if ( is_404() ) {
			status_header( 404 );
		}
	},
	100
);
