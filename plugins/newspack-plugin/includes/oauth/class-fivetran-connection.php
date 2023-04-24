<?php
/**
 * Connection to Google services.
 *
 * @package Newspack
 */

namespace Newspack;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class Fivetran_Connection {
	const NEWSPACK_FIVETRAN_TOS_CONSENT_USER_META = '_newspack_fivetran_tos_consent';

	/**
	 * Constructor.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
	}

	/**
	 * Register the endpoints.
	 *
	 * @codeCoverageIgnore
	 */
	public function register_api_endpoints() {
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/fivetran',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_fivetran_connection_status' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/fivetran/(?P<provider>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_create_connection' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'service' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/fivetran-tos',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_post_fivetran_tos' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'has_accepted' => [
						'required'          => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);
	}

	/**
	 * Get Fivetran connections status.
	 */
	public static function api_get_fivetran_connection_status() {
		$url                  = OAuth::authenticate_proxy_url( 'fivetran', '/wp-json/newspack-fivetran/v1/connections-status' );
		$connections_statuses = self::process_proxy_response(
			\wp_safe_remote_get(
				$url,
				[
					'timeout' => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
				]
			)
		);
		if ( is_wp_error( $connections_statuses ) ) {
			return new WP_Error(
				'newspack_connections_fivetran',
				$connections_statuses->get_error_message()
			);
		}
		$response = [
			'connections_statuses' => $connections_statuses,
			'has_accepted_tos'     => (bool) get_user_meta( get_current_user_id(), self::NEWSPACK_FIVETRAN_TOS_CONSENT_USER_META, true ),
		];
		return $response;
	}

	/**
	 * Create a new connection.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function api_create_connection( $request ) {
		$service      = $request->get_param( 'service' );
		$service_data = [];

		// For Google Ad Manager (aka double_click_publishers) - if Newspack Ads knows the network code, let's use it.
		if (
			'double_click_publishers' === $service &&
			method_exists( 'Newspack_Ads\Providers\GAM_Model', 'get_active_network_code' )
		) {
			$network_code = \Newspack_Ads\Providers\GAM_Model::get_active_network_code();
			if ( ! empty( $network_code ) ) {
				$service_data['double_click_publishers'] = [
					'network_code' => $network_code,
				];
			}
		}

		$url      = OAuth::authenticate_proxy_url(
			'fivetran',
			'/wp-json/newspack-fivetran/v1/connect-card',
			[
				'service'        => $service,
				'service_data'   => $service_data,
				'redirect_after' => admin_url( 'admin.php?page=newspack-connections-wizard' ),
			]
		);
		$response = self::process_proxy_response( \wp_safe_remote_post( $url, [ 'timeout' => 30 ] ) ); // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return \rest_ensure_response( $response );
	}

	/**
	 * Update the user's consent for the TOS.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function api_post_fivetran_tos( $request ) {
		update_user_meta(
			get_current_user_id(),
			self::NEWSPACK_FIVETRAN_TOS_CONSENT_USER_META,
			sanitize_meta(
				self::NEWSPACK_FIVETRAN_TOS_CONSENT_USER_META,
				$request->get_param( 'has_accepted' ),
				'user'
			)
		);
		return rest_ensure_response( [] );
	}

	/**
	 * Process the proxy response.
	 *
	 * @param object $result Result of a request.
	 * @return WP_Error|object Error or response data.
	 */
	private static function process_proxy_response( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 400 <= $result['response']['code'] ) {
			$error_body   = json_decode( $result['body'] );
			$error_prefix = __( 'Fivetran proxy error', 'newspack' );
			if ( null !== $error_body && property_exists( $error_body, 'message' ) ) {
				$error_message = $error_prefix . ': ' . $error_body->message;
			} elseif ( null !== $error_body && property_exists( $error_body, 'data' ) ) {
				$error_message = $error_prefix . ': ' . wp_json_encode( $error_body->data );
			} else {
				$error_message = $error_prefix;
			}
			return new WP_Error(
				'newspack_connections_fivetran',
				$error_message
			);
		}
		return json_decode( $result['body'] );
	}

	/**
	 * Check capabilities for using API.
	 *
	 * @codeCoverageIgnore
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public static function api_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => 403,
				]
			);
		}
		return true;
	}
}
new Fivetran_Connection();
