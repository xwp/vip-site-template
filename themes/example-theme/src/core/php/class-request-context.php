<?php
/**
 * Request context helpers.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

/**
 * Request context detection helpers.
 */
class Request_Context {

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
}
