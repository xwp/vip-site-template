<?php
/**
 * Newspack's Mailchimp API handling.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Mailchimp API Settings.
 */
class Mailchimp_API {
	/** API Endpoint Placeholder, needs to replace <dc> by the right datacenter. */
	const API_ENDPOINT_PLACEHOLDER = 'https://<dc>.api.mailchimp.com/3.0';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
	}

	/**
	 * Register the endpoints.
	 */
	public static function register_api_endpoints() {
		// Get Mailchimp auth status.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/mailchimp',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_mailchimp_auth_status' ],
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			]
		);

		// Verify and save Mailchimp API Key.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/mailchimp',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_mailchimp_save_key' ],
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'api_key' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'required'          => true,
					],
				],
			]
		);

		// Delete Mailchimp API Key.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/mailchimp',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_mailchimp_delete_key' ],
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Check whether we have an API key and its validity.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_mailchimp_auth_status() {
		return self::is_valid_api_key();
	}

	/**
	 * Verify and save Mailchimp API key
	 *
	 * @param WP_REST_REQUEST $request call request.
	 * @return WP_REST_Response
	 */
	public static function api_mailchimp_save_key( $request ) {
		$endpoint = self::get_api_endpoint_from_key( $request['api_key'] );
		if ( ! $endpoint ) {
			return new \WP_Error( 'wrong_parameter', __( 'Invalid Mailchimp API Key.', 'newspack' ) );
		}

		$key_is_valid_response = self::is_valid_api_key( $request['api_key'] );

		if ( ! is_wp_error( $key_is_valid_response ) ) {
			update_option( 'newspack_mailchimp_api_key', $request['api_key'] );
		}

		return $key_is_valid_response;
	}

	/**
	 * Delete Mailchimp API Key option.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_mailchimp_delete_key() {
		delete_option( 'newspack_mailchimp_api_key' );
		return \rest_ensure_response( [] );
	}

	/**
	 * Get the right datacenter from the API key and set it in the endpoint.
	 *
	 * @param string $api_key Mailchimp API Key.
	 * @return string|boolean the API endpoint with the right datacenter.
	 */
	private static function get_api_endpoint_from_key( $api_key ) {
		if ( strpos( $api_key, '-' ) === false ) {
			return false;
		}
		list(, $data_center) = explode( '-', $api_key );
		// Mailchimp API key format: abc123-datacenter.
		return $data_center ? str_replace( '<dc>', $data_center, self::API_ENDPOINT_PLACEHOLDER ) : false;
	}

	/**
	 * Check API Key validity
	 *
	 * @param string $api_key Mailchimp API Key.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function is_valid_api_key( $api_key = '' ) {
		// Mailchimp API root endpoint returns details about the Mailchimp user account.
		$response = self::request( 'GET', '', [], $api_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return \rest_ensure_response( [ 'username' => $response['username'] ] );
	}

	/**
	 * Perform Mailchimp API Request
	 *
	 * @param string $method  HTTP method.
	 * @param string $path    API path.
	 * @param array  $data    Data to send.
	 * @param string $api_key Optional API key to override the default one.
	 *
	 * @return array|WP_Error response body or error.
	 */
	public static function request( $method, $path = '', $data = [], $api_key = '' ) {
		if ( empty( $api_key ) ) {
			// 'newspack_mailchimp_api_key' is a new option introduced to manage MC API key accross Newspack plugins.
			// Keeping the old option for backwards compatibility.
			$api_key = \get_option( 'newspack_mailchimp_api_key', get_option( 'newspack_newsletters_mailchimp_api_key' ) );
		}
		$endpoint = self::get_api_endpoint_from_key( $api_key );
		if ( ! $endpoint ) {
			return new \WP_Error( 'wrong_parameter', __( 'Invalid Mailchimp API Key.', 'newspack' ) );
		}
		$url    = $endpoint . '/' . $path;
		$config = [
			'method'  => $method,
			'headers' => [
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => "Basic $api_key",
			],
		];
		if ( ! empty( $data ) ) {
			$config['body'] = \wp_json_encode( $data );
		}
		$response = \wp_safe_remote_request( $url, $config );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}
		$parsed_response = json_decode( $response['body'], true );
		if ( 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'newspack_mailchimp_api',
				array_key_exists( 'title', $parsed_response ) ? $parsed_response['title'] : __( 'Request failed.', 'newspack' )
			);
		}
		return $parsed_response;
	}

	/**
	 * Perform a GET request to Mailchimp's API
	 *
	 * @param string $path API path.
	 *
	 * @return array|WP_Error API response or error.
	 */
	public static function get( $path = '' ) {
		return self::request( 'GET', $path );
	}

	/**
	 * Perform a PUT request to Mailchimp's API
	 *
	 * @param string $path API path.
	 * @param array  $data Data to send.
	 *
	 * @return array|WP_Error API response or error.
	 */
	public static function put( $path = '', $data = [] ) {
		return self::request( 'PUT', $path, $data );
	}

	/**
	 * Perform a POST request to Mailchimp's API
	 *
	 * @param string $path API path.
	 * @param array  $data Data to send.
	 *
	 * @return array|WP_Error API response or error.
	 */
	public static function post( $path = '', $data = [] ) {
		return self::request( 'POST', $path, $data );
	}
}

new Mailchimp_API();
