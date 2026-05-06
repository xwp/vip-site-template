<?php
/**
 * Utility functions
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

use WPCOM_VIP_CLI_Command;

/**
 * Utility functions.
 */
class Utils {

	/**
	 * If this is a AJAX request.
	 *
	 * @return bool
	 */
	public static function is_ajax_request(): bool {
		return wp_doing_ajax();
	}

	/**
	 * If this is a REST API request.
	 *
	 * @return bool
	 */
	public static function is_rest_api_request(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Reliably detect if the request is an API or Ajax request.
	 *
	 * @return bool
	 */
	public static function is_api_request(): bool {
		return self::is_rest_api_request() || self::is_ajax_request();
	}

	/**
	 * If this is a WP CLI run.
	 *
	 * @return boolean
	 */
	public static function is_wp_cli_request(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * If this is a CRON job run.
	 *
	 * @return boolean
	 */
	public static function is_cron_request(): bool {
		return wp_doing_cron();
	}

	/**
	 * If a request is part of PHP unit tests.
	 *
	 * @return boolean
	 */
	public static function is_unit_tests(): bool {
		return defined( 'PHPUNIT_COMPOSER_INSTALL' );
	}

	/**
	 * Is WP debug mode enabled.
	 *
	 * @return boolean
	 */
	public static function is_debug(): bool {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	}

	/**
	 * Check if the current request is an autosave.
	 *
	 * @return bool True if the request is an autosave, false otherwise.
	 */
	public static function doing_autosave(): bool {
		return defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
	}

	/**
	 * Check if data import happens.
	 *
	 * @return bool
	 */
	public static function is_importing(): bool {
		return defined( 'WP_IMPORTING' ) && WP_IMPORTING;
	}

	/**
	 * If VIP WP CLI is available.
	 *
	 * @return boolean
	 */
	public static function is_vip_wp_cli_available(): bool {
		return class_exists( WPCOM_VIP_CLI_Command::class );
	}

	/**
	 * Is WP script debug mode enabled.
	 *
	 * @return boolean
	 */
	public static function is_script_debug(): bool {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
	}

	/**
	 * If the current environment is production.
	 *
	 * @return boolean
	 */
	public static function is_production_env(): bool {
		return ( 'production' === self::get_platform_environment() );
	}

	/**
	 * If the current environment is pre-prod (release).
	 *
	 * @return boolean
	 */
	public static function is_pre_prod_env(): bool {
		return ( 'release' === self::get_platform_environment() );
	}

	/**
	 * If the current environment is test (staging).
	 *
	 * @return boolean
	 */
	public static function is_test_env(): bool {
		return ( 'staging' === self::get_platform_environment() );
	}

	/**
	 * If the current environment is develop.
	 *
	 * @return boolean
	 */
	public static function is_dev_env(): bool {
		return ( 'develop' === self::get_platform_environment() );
	}

	/**
	 * If the current environment is local.
	 *
	 * @return boolean
	 */
	public static function is_local(): bool {
		return ( 'local' === self::get_platform_environment() );
	}

	/**
	 * Get the environment variable value
	 *
	 * Custom environment variables on the VIP environment should be prefixed
	 * with "VIP_ENV_VAR_". If a local environment variable name is "AWS_ACCESS_KEY_ID",
	 * then on a VIP environment it should use a "VIP_ENV_VAR_AWS_ACCESS_KEY_ID"
	 * name instead.
	 *
	 * @param string $key Environment variable key.
	 *
	 * @return ?string Environment variable value; null if not defined.
	 *
	 * @see https://docs.wpvip.com/how-tos/manage-environment-variables/
	 */
	public static function get_env_value( string $key ): ?string {
		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && ! in_array( VIP_GO_APP_ENVIRONMENT, [ 'local', false ], true ) ) {
			$value = method_exists( '\Automattic\VIP\Environment', 'get_var' )
				? \Automattic\VIP\Environment::get_var( $key )
				: null;
		} else {
			$value = getenv( $key );
		}

		return empty( $value ) || ! is_string( $value )
			? null
			: $value;
	}

	/**
	 * Get the platform environment.
	 *
	 * Use the VIP environment name or fallback to the WP core environment type.
	 *
	 * @return string
	 */
	public static function get_platform_environment(): string {
		// Environment variable mainly used for testing.
		if ( ! empty( getenv( 'DK_SITE_ENVIRONMENT' ) ) ) {
			return (string) getenv( 'DK_SITE_ENVIRONMENT' );
		}

		// Environment constant defined by VIP.
		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && VIP_GO_APP_ENVIRONMENT ) {
			return (string) VIP_GO_APP_ENVIRONMENT;
		}

		// Fallback to the WP core environment type.
		return wp_get_environment_type();
	}

	/**
	 * Get cached results.
	 *
	 * @param string          $key               Globally unique cache key.
	 * @param callable|string $group_or_callback Cache group or callback for a fresh response.
	 * @param callable|null   $callback          Callback for a fresh response.
	 * @param integer         $expire            Cache response expiry.
	 *
	 * @return mixed
	 */
	public static function with_cache( string $key, callable|string $group_or_callback, ?callable $callback = null, int $expire = 0 ): mixed {
		$group = '';

		if ( is_string( $group_or_callback ) ) {
			$group = $group_or_callback;
		} elseif ( is_callable( $group_or_callback ) ) {
			$callback = $group_or_callback;
		}

		// Skip getting cache value during migration.
		if ( self::is_importing() ) {
			return call_user_func( $callback );
		}

		$value = wp_cache_get( $key, $group );

		if ( false === $value ) {
			$value = call_user_func( $callback );
			wp_cache_set( $key, $value, $group, $expire ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		}

		return $value;
	}

	/**
	 * Clear cache item if supported.
	 *
	 * @param string $key   Globally unique cache key.
	 * @param string $group Cache group. Defaults to empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_cache_item( string $key, string $group = '' ): bool {
		// Skip clearing cache item during migration.
		if ( self::is_importing() ) {
			return false;
		}

		return wp_cache_delete( $key, $group );
	}

	/**
	 * Flush cache group if supported.
	 *
	 * @param string $group Group name.
	 */
	public static function flush_cache_group( string $group ): void {
		// Skip flushing cache group during migration.
		if ( self::is_importing() ) {
			return;
		}

		if ( wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( $group );
		}
	}

	/**
	 * Validate URL field
	 *
	 * @param string $url URL to validate.
	 *
	 * @return bool Boolean "true" if URL provided is valid, "false" otherwise.
	 */
	public static function is_valid_url( string $url ): bool {
		return false === ( empty( $url ) || false === filter_var( $url, \FILTER_VALIDATE_URL ) );
	}

	/**
	 * Convert camelCase string to snake_case.
	 *
	 * @param string $input "camelCase" string.
	 *
	 * @return string "snake_case" string.
	 */
	public static function camel_to_snake( string $input ): string {
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) );
	}

	/**
	 * Convert snake_case string to camelCase.
	 *
	 * @param string $input "snake_case" string.
	 *
	 * @return string "camelCase" string.
	 */
	public static function snake_to_camel( string $input ): string {
		return lcfirst( str_replace( '_', '', ucwords( $input, '_' ) ) );
	}

	/**
	 * Convert snake_case to PascalCase.
	 *
	 * @param string $input "snake_case" string.
	 *
	 * @return string "PascalCase" string.
	 */
	public static function snake_to_pascal_case( string $input ): string {
		return str_replace( '_', '', ucwords( $input, '_' ) );
	}

	/**
	 * Generate a URL for the WP API endpoint (cached by Fastly).
	 *
	 * Pass in the request path relative to the WP homeurl.
	 *
	 * @param string $path Path to the WP API endpoint.
	 *
	 * @return string Updated URL
	 */
	public static function get_wp_api_url( string $path ): string {
		return home_url( '/wp-api/' . ltrim( $path, '/' ) );
	}
}
