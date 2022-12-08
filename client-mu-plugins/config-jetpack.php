<?php
/**
 * Plugin Name: Jetpack Config
 */

/**
 * The VIP_JETPACK_IS_PRIVATE constant is enabled by default in non-production environments.
 *
 * It disables programmatic access to content via the WordPress.com REST API and Jetpack Search;
 * subscriptions via the WordPress.com Reader; and syndication via the WordPress.com Firehose.
 *
 * You can disable "private" mode (e.g. for testing) in non-production environment by setting the constant to `false` below (or just by removing the lines).
 *
 * @see https://docs.wpvip.com/technical-references/restricting-site-access/controlling-content-distribution-via-jetpack/
 */
if ( ! defined( 'VIP_JETPACK_IS_PRIVATE' ) && defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' !== VIP_GO_APP_ENVIRONMENT ) {
	define( 'VIP_JETPACK_IS_PRIVATE', true );
}

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
