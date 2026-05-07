<?php
/**
 * Environment helpers.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

use WPCOM_VIP_CLI_Command;

/**
 * Environment detection helpers.
 */
class Environment {

	/**
	 * If a request is part of PHP unit tests.
	 *
	 * @return boolean
	 */
	public static function is_unit_tests(): bool {
		return defined( 'PHPUNIT_COMPOSER_INSTALL' );
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
	 * Is WP debug mode enabled.
	 *
	 * @return boolean
	 */
	public static function is_debug(): bool {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG );
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
	 * Get the platform environment.
	 *
	 * Use the VIP environment name or fallback to the WP core environment type.
	 *
	 * @return string
	 */
	public static function get_platform_environment(): string {
		// Environment variable mainly used for testing.
		if ( ! empty( getenv( 'SITE_ENVIRONMENT' ) ) ) {
			return (string) getenv( 'SITE_ENVIRONMENT' );
		}

		// Environment constant defined by VIP.
		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && VIP_GO_APP_ENVIRONMENT ) {
			return (string) VIP_GO_APP_ENVIRONMENT;
		}

		// Fallback to the WP core environment type.
		return wp_get_environment_type();
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
}
