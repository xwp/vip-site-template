<?php
/**
 * Utility functions
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

/**
 * Utility functions.
 */
class Utils {

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
