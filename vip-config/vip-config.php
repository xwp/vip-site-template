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
 * @package XWP\Vip_Site_Template
 */

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
 * Set WP_MEMORY_LIMIT to 1024M as suggested by the VIP team in the past.
 *
 * Some GraphQL/SQL queries, relying on Elasticsearch, can take up to 1GB of memory.
 *
 * @see https://support.wpvip.com/hc/en-us/requests/205553
 */
define( 'WP_MEMORY_LIMIT', '1024M' );

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
