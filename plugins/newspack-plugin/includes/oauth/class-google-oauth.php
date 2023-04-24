<?php
/**
 * Newspack's Google OAuth2 handling.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;
use Google\Auth\OAuth2;
use Google\Auth\Credentials\UserRefreshCredentials;

defined( 'ABSPATH' ) || exit;

/**
 * Google OAuth2 flow.
 */
class Google_OAuth {
	const AUTH_DATA_META_NAME  = '_newspack_google_oauth';
	const AUTH_CALLBACK        = 'newspack_google_oauth_callback';
	const CSRF_TOKEN_NAMESPACE = 'google';

	const REQUIRED_SCOPES = [
		'https://www.googleapis.com/auth/userinfo.email', // User's email address.
		'https://www.googleapis.com/auth/admanager',
		'https://www.googleapis.com/auth/analytics',
		'https://www.googleapis.com/auth/analytics.edit',
	];

	/**
	 * Constructor.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
		add_action( 'admin_init', [ __CLASS__, 'oauth_callback' ] );
	}

	/**
	 * Register the endpoints.
	 *
	 * @codeCoverageIgnore
	 */
	public static function register_api_endpoints() {
		// Get Google OAuth2 auth status.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/google',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_google_auth_status' ],
				'permission_callback' => [ __CLASS__, 'permissions_check' ],
			]
		);
		// Start Google OAuth2 flow.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/google/start',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_google_auth_get_url' ],
				'permission_callback' => [ __CLASS__, 'permissions_check' ],
			]
		);
		// Revoke Google OAuth2 details.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/oauth/google/revoke',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_google_auth_revoke' ],
				'permission_callback' => [ __CLASS__, 'permissions_check' ],
			]
		);
	}

	/**
	 * Check capabilities for using API.
	 *
	 * @codeCoverageIgnore
	 * @return bool|WP_Error
	 */
	public static function permissions_check() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_oauth_configured() ) {
			Logger::error( 'Fail: user failed permissions check or OAuth is not configured.' );
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

	/**
	 * Save OAuth2 credentials for the current user.
	 *
	 * @param object $tokens Tokens.
	 * @return bool True if credentials were saved.
	 */
	private static function save_auth_credentials( $tokens ) {
		$tokens           = (array) $tokens;
		$saved_csrf_token = OAuth::retrieve_csrf_token( self::CSRF_TOKEN_NAMESPACE );

		if ( $tokens['csrf_token'] !== $saved_csrf_token ) {
			Logger::error( 'Failed saving credentials - CSRF token mismatch.' );
			return new \WP_Error(
				'newspack_google_oauth',
				__( 'Session token mismatch.', 'newspack' ),
				[
					'status' => 403,
				]
			);
		}
		if ( ! isset( $tokens['access_token'], $tokens['expires_at'] ) ) {
			Logger::error( 'Failed saving credentials - missing data.' );
			return new \WP_Error(
				'newspack_google_oauth',
				__( 'Missing data.', 'newspack' ),
				[
					'status' => 403,
				]
			);
		}

		$auth                 = self::get_google_auth_saved_data();
		$auth['access_token'] = $tokens['access_token'];
		$auth['expires_at']   = $tokens['expires_at'];
		if ( isset( $tokens['refresh_token'] ) ) {
			$auth['refresh_token'] = $tokens['refresh_token'];
		}
		self::remove_credentials();
		Logger::log( 'Saving credentials to WP option ' . self::AUTH_DATA_META_NAME );
		return add_option( self::AUTH_DATA_META_NAME, $auth );
	}

	/**
	 * Create params to obtain a URL for a redirection to Google consent page.
	 */
	public static function get_google_auth_url_params() {
		return [
			'csrf_token'     => OAuth::generate_csrf_token( self::CSRF_TOKEN_NAMESPACE ),
			'scope'          => implode( ' ', self::REQUIRED_SCOPES ),
			'redirect_after' => add_query_arg( self::AUTH_CALLBACK, wp_create_nonce( self::AUTH_CALLBACK ), admin_url( 'index.php' ) ),
		];
	}


	/**
	 * Get the URL for a redirection to Google consent page.
	 *
	 * @param array $auth_params OAuth proxy params.
	 *
	 * @return string|WP_Error URL or error.
	 */
	public static function google_auth_get_url( $auth_params ) {
		try {
			$url    = OAuth::authenticate_proxy_url(
				'google',
				'/wp-json/newspack-oauth-proxy/v1/start',
				$auth_params
			);
			$result = wp_safe_remote_get( $url );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( 200 !== $result['response']['code'] ) {
				$error_text  = __( 'Request failed.', 'newspack' );
				$parsed_data = json_decode( $result['body'] );
				if ( property_exists( $parsed_data, 'message' ) ) {
					$error_text = $parsed_data->message;
				}
				return new WP_Error(
					'newspack_google_oauth',
					$error_text
				);
			}
			$response_body = json_decode( $result['body'] );
			return $response_body->url;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'newspack_google_oauth',
				$e->getMessage()
			);
		}
	}

	/**
	 * Start the Google OAuth2 flow.
	 *
	 * @return WP_REST_Response Response with the URL.
	 */
	public static function api_google_auth_get_url() {
		$auth_params = self::get_google_auth_url_params();
		$url         = self::google_auth_get_url( $auth_params );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		return rest_ensure_response( $url );
	}

	/**
	 * OAuth callback.
	 */
	public static function oauth_callback() {
		if ( ! isset( $_GET[ self::AUTH_CALLBACK ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( $_GET[ self::AUTH_CALLBACK ] ), self::AUTH_CALLBACK ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'newspack' ) );
			return;
		}

		if ( ! isset( $_REQUEST['csrf_token'] ) || ! isset( $_REQUEST['access_token'] ) || ! isset( $_REQUEST['expires_at'] ) ) {
			wp_die( esc_html__( 'Invalid request', 'newspack' ) );
			return;
		}

		Logger::log( 'Attempting to save credentials.' );

		$auth_save_data = [
			'csrf_token'   => sanitize_text_field( $_REQUEST['csrf_token'] ),
			'access_token' => sanitize_text_field( $_REQUEST['access_token'] ),
			'expires_at'   => sanitize_text_field( $_REQUEST['expires_at'] ),
		];

		if ( isset( $_REQUEST['refresh_token'] ) ) {
			$auth_save_data['refresh_token'] = sanitize_text_field( $_REQUEST['refresh_token'] );
		}

		$auth_save_result = self::save_auth_credentials( $auth_save_data );

		if ( is_wp_error( $auth_save_result ) ) {
			Logger::error( 'Credentials saving resulted in an error: ' . $auth_save_result->get_error_message() );
			wp_die( esc_html( $auth_save_result->get_error_message() ) );
			return;
		}
		if ( ! $auth_save_result ) {
			Logger::error( 'Failed saving credentials.' );
			wp_die( esc_html__( 'Could not save auth data for user.', 'newspack' ) );
			return;
		}

		Logger::log( 'Credentials saved.' );

		/** Add success notice in case window is not closed automatically. */
		add_action(
			'admin_notices',
			function() {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Successfully connected to Google account.', 'newspack' ) . '</p></div>';
			}
		);

		/** Close window if it's a popup. */
		?>
		<script type="text/javascript">
			if ( window.opener ) { window.close(); }
		</script>
		<?php
	}

	/**
	 * Revoke credentials of current user.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function api_google_auth_revoke() {
		Logger::log( 'Revoking credentials…' );
		$auth_data = self::get_google_auth_saved_data();
		if ( ! isset( $auth_data['refresh_token'] ) ) {
			return new \WP_Error( 'newspack_google_oauth', __( 'Missing token for user.', 'newspack' ) );
		}
		$token  = $auth_data['refresh_token'];
		$result = \wp_safe_remote_post(
			add_query_arg( [ 'token' => $token ], 'https://oauth2.googleapis.com/revoke' )
		);
		if ( 200 === $result['response']['code'] ) {
			Logger::log( 'Revoking credentials success.' );
			self::remove_credentials();
			return \rest_ensure_response( [ 'status' => 'ok' ] );
		} else {
			Logger::error( 'Failed revoking credentials.' );
			return new \WP_Error( 'newspack_google_oauth', __( 'Could not revoke credentials.', 'newspack' ) );
		}
	}

	/**
	 * Get Google authentication status.
	 */
	public static function api_google_auth_status() {
		$response = [
			'user_basic_info' => false,
		];
		if ( false === self::is_oauth_configured() ) {
			return \rest_ensure_response( $response );
		}
		$user_info_data = self::authenticated_user_basic_information();
		if ( is_wp_error( $user_info_data ) ) {
			return $user_info_data;
		}
		$response['user_basic_info'] = $user_info_data;
		return \rest_ensure_response( $response );
	}

	/**
	 * Get Google authentication details.
	 */
	public static function get_google_auth_saved_data() {
		if ( ! self::permissions_check() ) {
			return [];
		}
		return get_option( self::AUTH_DATA_META_NAME, [] );
	}

	/**
	 * Get user's email address.
	 *
	 * @param array $access_token Authentication token.
	 * @param array $required_scopes Required scopes.
	 */
	public static function validate_token_and_get_email_address( $access_token, $required_scopes ) {
		// Validate access token.
		$token_info_response = wp_safe_remote_get(
			add_query_arg(
				'access_token',
				$access_token,
				'https://www.googleapis.com/oauth2/v1/tokeninfo'
			)
		);

		if ( 200 === wp_remote_retrieve_response_code( $token_info_response ) ) {
			$token_info     = json_decode( wp_remote_retrieve_body( $token_info_response ) );
			$granted_scopes = explode( ' ', $token_info->scope );
			/** If granted scope is 'dfp', interpret as 'admanager'.  */
			foreach ( $granted_scopes as &$scope ) {
				if ( 'https://www.googleapis.com/auth/dfp' === $scope ) {
					$scope = 'https://www.googleapis.com/auth/admanager';
				}
			}
			$missing_scopes = array_diff( $required_scopes, $granted_scopes );
			if ( 0 < count( $missing_scopes ) ) {
				Logger::error( 'OAuth token validation errored with missing scopes: ' . implode( ', ', $missing_scopes ) . '. Granted scopes: ' . $token_info->scope );
				return new \WP_Error( 'newspack_google_oauth', __( 'Newspack can’t access all necessary data because you haven’t granted all permissions requested during setup. Please reconnect your Google account.', 'newspack' ) );
			}

			$user_info_response = wp_safe_remote_get(
				add_query_arg(
					'access_token',
					$access_token,
					'https://www.googleapis.com/oauth2/v2/userinfo'
				)
			);
			if ( 200 === wp_remote_retrieve_response_code( $user_info_response ) ) {
				$user_info = json_decode( $user_info_response['body'] );
				return $user_info->email;
			}
		} else {
			Logger::error( 'Failed retrieving user info – invalid credentials.' );
			return new \WP_Error( 'newspack_google_oauth', __( 'Invalid Google credentials. Please reconnect.', 'newspack' ) );
		}
	}

	/**
	 * Authenticated user's basic information.
	 *
	 * @return array|WP_Error Basic information, or error.
	 */
	private static function authenticated_user_basic_information() {
		$oauth2_credentials = self::get_oauth2_credentials();
		if ( false === $oauth2_credentials ) {
			return new \WP_Error( 'newspack_google_oauth', __( 'Invalid or missing Google credentials.', 'newspack' ) );
		}

		$access_token = $oauth2_credentials->getAccessToken();
		$user_email   = self::validate_token_and_get_email_address( $access_token, self::REQUIRED_SCOPES );
		if ( is_wp_error( $user_email ) ) {
			return $user_email;
		}
		return [
			'email'             => $user_email,
			'has_refresh_token' => null !== $oauth2_credentials->getRefreshToken(),
		];
	}

	/**
	 * Get OAuth2 Credentials.
	 * If refresh token is available, refresh credentials.
	 *
	 * @return OAuth2|bool The credentials, or false of the user has not authenticated or credentials are not usable.
	 */
	public static function get_oauth2_credentials() {
		$auth_data = self::get_google_auth_saved_data();
		if ( empty( $auth_data ) ) {
			Logger::log( 'No credentials saved, OAuth credentials will not be returned.' );
			return false;
		}
		if ( ! isset( $auth_data['access_token'] ) ) {
			Logger::log( 'Access token is not set, OAuth credentials will not be returned.' );
			return false;
		}
		$is_expired = time() > $auth_data['expires_at'];

		if ( $is_expired && isset( $auth_data['refresh_token'] ) ) {
			Logger::log( 'Refreshing the token…' );
			// Refresh the access token.
			try {
				$url    = OAuth::authenticate_proxy_url(
					'google',
					'/wp-json/newspack-oauth-proxy/v1/refresh-token',
					[
						'refresh_token' => $auth_data['refresh_token'],
						'csrf_token'    => OAuth::generate_csrf_token( self::CSRF_TOKEN_NAMESPACE ),
					]
				);
				$result = wp_safe_remote_get( $url );
				if ( is_wp_error( $result ) ) {
					Logger::error( 'Token refresh resulted in error: ' . $result->get_error_message() );
					return false;
				}
				if ( 200 !== $result['response']['code'] ) {
					Logger::error( 'Token refresh response is not 200: ' . $result['response']['code'] );
					return false;
				}
				$response_body = json_decode( $result['body'] );

				if ( isset( $response_body->access_token ) ) {
					Logger::log( 'Refreshed the token.' );
					$auth_save_result = self::save_auth_credentials( $response_body );
					if ( is_wp_error( $auth_save_result ) ) {
						Logger::error( 'Credentials saving resulted in an error: ' . $auth_save_result->get_error_message() );
						return false;
					}
					$auth_data = self::get_google_auth_saved_data();
				} else {
					Logger::error( 'Access token missing from the response.' );
				}
			} catch ( \Exception $e ) {
				Logger::error( 'Token refreshing failed due to error: ' . $e->getMessage() );
				return false;
			}
		}

		$oauth_object = new OAuth2( [] );
		$oauth_object->setAccessToken( $auth_data['access_token'] );
		if ( isset( $auth_data['refresh_token'] ) ) {
			$oauth_object->setRefreshToken( $auth_data['refresh_token'] );
		} else {
			Logger::error( 'Refresh token missing in the credentials – the authorisation will have to be refreshed in an hour.' );
		}
		return $oauth_object;
	}

	/**
	 * Remove saved credentials.
	 */
	public static function remove_credentials() {
		Logger::log( 'Removing stored credentials.' );
		delete_option( self::AUTH_DATA_META_NAME );
	}

	/**
	 * Is OAuth configured?
	 */
	public static function is_oauth_configured() {
		return OAuth::is_proxy_configured( 'google' );
	}
}
new Google_OAuth();
