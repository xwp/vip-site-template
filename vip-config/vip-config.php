<?php
/**
 * VIP-Go specific wp-config.php.
 *
 * Mapped to vip-config/vip-config.php in the root of the WordPress installation on the VIP servers.
 *
 * Consider using environment variables for sensitive data:
 *
 * @see https://docs.wpvip.com/how-tos/manage-environment-variables/
 *
 * WARNING: This file is loaded very early (immediately after `wp-config.php`), which means that most WordPress APIs,
 *   classes, and functions are not available. The code below should be limited to pure PHP.
 *
 * @see https://vip.wordpress.com/documentation/vip-go/understanding-your-vip-go-codebase/
 *
 * @package XWP\VIP_Site_Template
 */

/**
 * Domain redirect configuration for a VIP multisite.
 *
 * Must run before WordPress loads to handle redirects efficiently.
 */
if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
	$http_host   = $_SERVER['HTTP_HOST']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$request_uri = $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/**
	 * Configure domain redirects for www/non-www variants.
	 *
	 * Structure: 'canonical-domain.com' => [ 'redirect-from-domains' ]
	 *
	 * Domain configuration:
	 * - Main site: www.client-x.com (canonical), client-x.com (redirects to www).
	 * - Network site: network.client-x.com (no www variant needed).
	 */
	$redirect_domains = [
		// Main site - redirect non-www to www.
		'www.client-x.com' => [
			'client-x.com',  // Redirect client-x.com → www.client-x.com.
		],
		// Network site doesn't need redirects (no www variant exists).
		// 'network.client-x.com' has no variants to redirect.
	];

	/**
	 * Safety checks for redirection:
	 * 1. Don't redirect for '/cache-healthcheck?' or monitoring will break.
	 * 2. Don't redirect in WP CLI context.
	 * 3. Only redirect if we're not already on the target domain.
	 * 4. Don't redirect go-vip.net domains (they're for non-production environments).
	 */
	foreach ( $redirect_domains as $redirect_to => $redirect_from_domains ) {
		if (
			'/cache-healthcheck?' !== $request_uri && // Do not redirect VIP's monitoring.
			! ( defined( 'WP_CLI' ) && WP_CLI ) && // Do not redirect WP-CLI commands.
			$redirect_to !== $http_host &&
			in_array( $http_host, $redirect_from_domains, true )
		) {
			header( 'Location: https://' . $redirect_to . $request_uri, true, 301 );
			exit;
		}
	}
}

/**
 * Enable Composer autoloader.
 *
 * Note that on VIP servers the vip-config directory gets mounted
 * in the public root directory instead of inside the wp-content directory
 * like it is in the source repository.
 *
 * This is also the reason why we can't place the vendor directory under
 * vip-config as its path changes on the server which breaks all the
 * resolved paths in the autoload.php file.
 */
if ( file_exists( dirname( __DIR__ ) . '/plugins/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/plugins/vendor/autoload.php';
} elseif ( file_exists( dirname( __DIR__ ) . '/wp-content/plugins/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/wp-content/plugins/vendor/autoload.php';
}

/**
 * Enable VIP Search. Use a conditional
 * since it might be disabled for tests or local development.
 *
 * @see https://docs.wpvip.com/technical-references/enterprise-search/
 */
if ( ! defined( 'VIP_ENABLE_VIP_SEARCH' ) ) {
	define( 'VIP_ENABLE_VIP_SEARCH', true );
}
if ( ! defined( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION' ) ) {
	define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );
}

/**
 * Set a high default limit to avoid too many revisions from polluting the database.
 *
 * Posts with extremely high revisions can result in fatal errors or have performance issues.
 *
 * Feel free to adjust this depending on your use cases.
 */
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
	define( 'WP_POST_REVISIONS', 100 );
}

/**
 * Disable New Relic Browser instrumentation.
 *
 * By default, the New Relic extension automatically enables Browser instrumentation.
 *
 * This injects some New Relic specific javascript onto all pages on the VIP Platform.
 *
 * This isn't always desireable (e.g. impacts performance) so let's turn it off.
 *
 * If you would like to enable Browser instrumentation, please remove the lines below.
 *
 * @see https://docs.newrelic.com/docs/agents/php-agent/features/browser-monitoring-php-agent/#disable
 * @see https://docs.wpvip.com/technical-references/tools-for-site-management/new-relic/
 */
if ( function_exists( 'newrelic_disable_autorum' ) ) {
	newrelic_disable_autorum();
}

/**
 * Set WP_DEBUG to true for all local or non-production VIP environments to ensure
 * _doing_it_wrong() notices display in Query Monitor. This also changes the error_reporting level to E_ALL.
 *
 * @see https://wordpress.org/support/article/debugging-in-wordpress/#wp_debug
 */
if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' !== VIP_GO_APP_ENVIRONMENT && ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

/**
 * Set WP_MEMORY_LIMIT to the current memory limit.
 * Fall back to 1024M if the current memory limit is not set.
 *
 * Some GraphQL/SQL queries, relying on Elasticsearch, can take up to 1GB of memory.
 */
define( 'WP_MEMORY_LIMIT', ini_get( 'memory_limit' ) ?: '1024M' );

/**
 * Set site domain for NewRelic in order to have separate logs for each site.
 *
 * phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
 */
if ( ! empty( $_SERVER['HTTP_HOST'] ) && function_exists( 'newrelic_set_appname' ) ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- it is ok, the code is copied from docs: https://docs.wpvip.com/technical-references/new-relic-for-wordpress/#h-separate-apps-out-on-a-per-site-basis-for-multisite
	$app_name = $_SERVER['HTTP_HOST'];

	// Append the environment name, if present.
	if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
		$app_name .= '-' . VIP_GO_APP_ENVIRONMENT;
	}

	newrelic_set_appname( $app_name );
}
