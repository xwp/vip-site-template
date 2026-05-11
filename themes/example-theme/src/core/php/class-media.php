<?php
/**
 * WP Media extension component.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

/**
 * WP Media extension component.
 */
final class Media implements Component {

	/**
	 * VIP get file timeout value. We need to increase the default timeout as certain files takes more to process.
	 *
	 * @var int
	 */
	const VIP_GET_FILE_TIMEOUT = 20;

	/**
	 * VIP Files API base URL.
	 *
	 * @var string
	 */
	private string $api_base;

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! defined( 'FILE_SERVICE_ENDPOINT' ) ) {
			return;
		}

		$this->api_base = 'https://' . FILE_SERVICE_ENDPOINT;

		// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args
		add_filter( 'http_request_args', [ $this, 'filter_override_media_timeout' ], 10, 2 );
	}

	/**
	 * Filter HTTP request arguments to override the timeout value for specific execution stacks.
	 *
	 * @see https://support.wpvip.com/hc/en-us/requests/190133
	 * - VIP filesystem is encountering timeouts with some media/images.
	 * - In the meantime, VIP support is recommending to increase the timeout value for some specific execution stacks.
	 *
	 * @param array  $args An array of HTTP request arguments.
	 * @param string $url The request URL.
	 *
	 * @return array Updated array of HTTP request arguments.
	 */
	public function filter_override_media_timeout( array $args, string $url ): array {
		// Skip if the request is not for the VIP files API.
		if ( false === str_starts_with( $url, $this->api_base ) ) {
			return $args;
		}

		$timeout = $args['timeout'] ?? 5;
		$stream  = $args['stream'] ?? false;

		// If timeout is already greater than the VIP_GET_FILE_TIMEOUT, then return the args as is.
		if ( false === $stream || self::VIP_GET_FILE_TIMEOUT < $timeout ) {
			return $args;
		}

		$args['timeout'] = self::VIP_GET_FILE_TIMEOUT;

		return $args;
	}
}
