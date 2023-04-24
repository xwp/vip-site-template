<?php
/**
 * Newspack's Google Login handling.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Google Login flow.
 */
class Google_Login {
	const EMAIL_TRANSIENT_PREFIX = 'newspack_google_email_';
	const AUTH_CALLBACK          = 'newspack_google_login_callback';
	const CSRF_TOKEN_NAMESPACE   = 'google_login';

	const REQUIRED_SCOPES = [
		'https://www.googleapis.com/auth/userinfo.email', // User's email address.
	];

	/**
	 * Constructor.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
		add_action( 'template_redirect', [ __CLASS__, 'oauth_callback' ] );
	}

	/**
	 * Register the endpoints.
	 *
	 * @codeCoverageIgnore
	 */
	public static function register_api_endpoints() {
		// Get Google login status.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/login/google/register',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_google_login_register' ],
				'permission_callback' => [ __CLASS__, 'api_check_if_oauth_configured' ],
				'args'                => [
					'metadata' => [
						'required' => false,
					],
				],
			]
		);
		// Start Google login flow.
		\register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/login/google',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_google_auth_get_url' ],
				'permission_callback' => [ __CLASS__, 'api_check_if_oauth_configured' ],
			]
		);
	}

	/**
	 * Check capabilities for using API.
	 *
	 * @codeCoverageIgnore
	 * @return bool|WP_Error
	 */
	public static function api_check_if_oauth_configured() {
		if ( ! Google_OAuth::is_oauth_configured() ) {
			Logger::log( 'OAuth not configured.' );
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
	 * Start the Google OAuth2 flow by obtaining a URL to the Google consent screen.
	 *
	 * @return WP_REST_Response Response with the URL.
	 */
	public static function api_google_auth_get_url() {
		$url = Google_OAuth::google_auth_get_url(
			[
				'csrf_token'     => OAuth::generate_csrf_token( self::CSRF_TOKEN_NAMESPACE ),
				'scope'          => implode( ' ', self::REQUIRED_SCOPES ),
				'redirect_after' => add_query_arg( self::AUTH_CALLBACK, wp_create_nonce( self::AUTH_CALLBACK ), get_home_url() ),
			]
		);
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

		if ( ! isset( $_REQUEST['csrf_token'] ) || ! isset( $_REQUEST['access_token'] ) ) {
			wp_die( esc_html__( 'Invalid request', 'newspack' ) );
			return;
		}

		$saved_csrf_token = OAuth::retrieve_csrf_token( self::CSRF_TOKEN_NAMESPACE );

		if ( $_REQUEST['csrf_token'] !== $saved_csrf_token ) {
			Logger::error( 'Failed saving email - CSRF token mismatch.' );
			\wp_die( \esc_html__( 'Authentication failed.', 'newspack' ) );
		}

		$user_email = Google_OAuth::validate_token_and_get_email_address( sanitize_text_field( $_REQUEST['access_token'] ), self::REQUIRED_SCOPES );
		if ( is_wp_error( $user_email ) ) {
			Logger::error( 'Failed validating user: ' . $user_email->get_error_message() );
			\wp_die( \esc_html__( 'Authentication failed.', 'newspack' ) );
		}

		Logger::log( 'Got user email from Google: ' . $user_email );

		// Associate the email address with the a unique ID for later retrieval.
		set_transient( self::EMAIL_TRANSIENT_PREFIX . OAuth::get_unique_id(), $user_email, 20 );

		/** Close window if it's a popup. */
		?>
		<script type="text/javascript" data-amp-plus-allowed>
			if ( window.opener ) { window.close(); }
		</script>
		<?php
	}

	/**
	 * Get Google authentication status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public static function api_google_login_register( $request ) {
		$uid = OAuth::get_unique_id();
		// Retrieve the email address associated with the unique ID when the user was authenticated.
		$email = get_transient( self::EMAIL_TRANSIENT_PREFIX . $uid );
		delete_transient( self::EMAIL_TRANSIENT_PREFIX . $uid ); // Burn after reading.
		$metadata = [];
		if ( $request->get_param( 'metadata' ) ) {
			try {
				$metadata = json_decode( $request->get_param( 'metadata' ), true );
			} catch ( \Throwable $th ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fail silently.
			}
		}
		$metadata['registration_method'] = 'google';
		if ( $email ) {
			$existing_user = \get_user_by( 'email', $email );
			$message       = __( 'Thank you for registering!', 'newspack' );
			$data          = [
				'email'         => $email,
				'authenticated' => true,
				'sso'           => true,
				'existing_user' => $existing_user ? true : false,
			];

			if ( $existing_user ) {
				// Log the user in.
				$result  = Reader_Activation::set_current_reader( $existing_user->ID );
				$message = __( 'Thank you for signing in!', 'newspack' );
			} else {
				$result = Reader_Activation::register_reader( $email, '', true, $metadata );
				// At this point the user will be logged in.
			}
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return \rest_ensure_response(
				[
					'data'    => $data,
					'message' => $message,
				]
			);
		} else {
			Logger::error( 'Missing email for unique id ' . $uid );
			return new \WP_Error( 'newspack_google_login', __( 'Failed to retrieve email address. Please try again.', 'newspack' ) );
		}
	}
}
new Google_Login();
