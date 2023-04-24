<?php
/**
 * Newspack tracking pixel functionality.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * (Tracking) Pixel class
 */
abstract class Pixel {
	/**
	 * The option name
	 *
	 * @var string
	 */
	public $option_name = '';

	/**
	 * Constructor
	 *
	 * @param string $option_name The option name.
	 */
	public function __construct( $option_name ) {
		$this->option_name = $option_name;
	}

	/**
	 * Validates the active argument
	 *
	 * @param mixed $value The value to be validated.
	 * @return boolean
	 */
	public static function validate_active( $value ) {
		return is_bool( $value );
	}

	/**
	 * Callback for the REST API GET method
	 *
	 * @return \WP_REST_Response
	 */
	public function api_get() {
		return rest_ensure_response( $this->get_option() );
	}

	/**
	 * Callback for the REST API POST method to save the settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function api_save( $request ) {
		$pixel_id = $request->get_param( 'pixel_id' );
		$active   = $request->get_param( 'active' );
		$value    = compact( 'pixel_id', 'active' );
		update_option( $this->option_name, $this->sanitize_option( $value ) );
		return rest_ensure_response( $this->get_option(), 200 );
	}

	/**
	 * Gets the current value of the option
	 *
	 * @return array
	 */
	public function get_option() {
		return get_option( $this->option_name, $this->get_default_values() );
	}

	/**
	 * Checks if option is active
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->get_option()['active'];
	}

	/**
	 * Gets the stored pixel ID. If option is not active, ignore the saved option
	 *
	 * @return string
	 */
	public function get_pixel_id() {
		if ( ! $this->is_active() ) {
			return '';
		}
		return $this->get_option()['pixel_id'];
	}

	/**
	 * Get settings default values
	 *
	 * @return array
	 */
	public function get_default_values() {
		return [
			'active'   => false,
			'pixel_id' => '',
		];
	}

	/**
	 * Sanitizes settings
	 *
	 * @param array $value The settings value.
	 * @return array
	 */
	public function sanitize_option( $value ) {
		$defaults  = $this->get_default_values();
		$sanitized = [];
		if ( ! is_array( $value ) ) {
			return $defaults;
		}
		if ( isset( $value['active'] ) ) {
			$sanitized['active'] = (bool) $value['active'];
		} else {
			$sanitized['active'] = $defaults['active'];
		}
		if ( isset( $value['pixel_id'] ) ) {
			$sanitized['pixel_id'] = (string) $value['pixel_id']; // store as string to avoid PHP_INT_MAX problems.
		} else {
			$sanitized['pixel_id'] = $defaults['pixel_id'];
		}
		return $sanitized;
	}

	/**
	 * Checks if AMP plugin is enabled and if the current response is being server as AMP
	 *
	 * @return boolean
	 */
	public function is_amp() {
		return function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
	}

	/**
	 * Processes the HTML to be included in the header.
	 *
	 * @param string $payload Snippet to print.
	 * @return void
	 */
	public function create_js_snippet( $payload ) {
		if ( $this->is_amp() ) {
			return;
		}
		$pixel_id = $this->get_pixel_id();
		if ( empty( $pixel_id ) ) {
			return;
		}
		echo str_replace( '__PIXEL_ID__', $pixel_id, $payload ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Processes the HTML to be included as a fallback (noscript).
	 *
	 * @param string $payload Snippet to print.
	 * @return void
	 */
	public function create_noscript_snippet( $payload ) {
		$pixel_id = $this->get_pixel_id();
		if ( empty( $pixel_id ) ) {
			return;
		}
		// If AMP plugin is enabled, it will convert the image into a <amp-pixel> tag.
		echo '<noscript>' . str_replace( '__PIXEL_ID__', $pixel_id, $payload ) . '</noscript>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Validates the pixel id
	 *
	 * @param mixed $value The value to be validated.
	 * @return boolean
	 */
	abstract protected function validate_pixel_id( $value );

	/**
	 * Gets the template for the img tag snippet
	 *
	 * @return string
	 */
	abstract protected function print_footer_snippet();

	/**
	 * Gets the template for the script tag snippet
	 *
	 * @return string
	 */
	abstract protected function print_head_snippet();
}
