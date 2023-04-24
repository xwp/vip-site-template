<?php
/**
 * Newspack Magic Links functionality.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Magic Links class.
 */
final class Magic_Link {

	const ADMIN_ACTIONS = [
		'send'    => 'np_magic_link_send',
		'clear'   => 'np_magic_link_clear',
		'disable' => 'np_magic_link_disable',
		'enable'  => 'np_magic_link_enable',
	];

	const TOKENS_META   = 'np_magic_link_tokens';
	const DISABLED_META = 'np_magic_link_disabled';

	const AUTH_ACTION        = 'np_auth_link';
	const AUTH_ACTION_RESULT = 'np_auth_link_result';
	const COOKIE             = 'np_auth_link';

	const OTP_LENGTH       = 6;
	const OTP_MAX_ATTEMPTS = 5;
	const OTP_AUTH_ACTION  = 'np_otp_auth';
	const OTP_HASH_COOKIE  = 'np_otp_hash';

	/**
	 * Current session secret.
	 *
	 * @var string
	 */
	private static $session_secret = '';

	/**
	 * Initialize hooks.
	 */
	public static function init() {

		/** Authentication hooks */
		\add_action( 'clear_auth_cookie', [ __CLASS__, 'clear_token_cookies' ] );
		\add_action( 'set_auth_cookie', [ __CLASS__, 'clear_token_cookies' ] );
		\add_action( 'template_redirect', [ __CLASS__, 'process_token_request' ] );
		\add_action( 'template_redirect', [ __CLASS__, 'process_otp_request' ] );

		/** Admin functionality */
		\add_action( 'init', [ __CLASS__, 'wp_cli' ] );
		\add_action( 'admin_init', [ __CLASS__, 'process_admin_action' ] );
		\add_filter( 'user_row_actions', [ __CLASS__, 'user_row_actions' ], 10, 2 );
		\add_action( 'edit_user_profile', [ __CLASS__, 'edit_user_profile' ] );

	}

	/**
	 * Whether a user can use magic links.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool Whether the user can use magic links.
	 */
	public static function can_magic_link( $user_id ) {
		if ( ! Reader_Activation::is_enabled() ) {
			return false;
		}

		$user = \get_user_by( 'id', $user_id );

		if ( ! $user || \is_wp_error( $user ) ) {
			return false;
		}

		if ( ! Reader_Activation::is_user_reader( $user ) ) {
			return false;
		}

		$can_magic_link = ! (bool) \get_user_meta( $user_id, self::DISABLED_META, true );

		/**
		 * Filters whether the user can use magic links.
		 *
		 * @param bool     $can_magic_link Whether the user can use magic links.
		 * @param \WP_User $user           User object.
		 */
		return \apply_filters( 'newspack_can_magic_link', $can_magic_link, $user );
	}

	/**
	 * Whether OTP is enabled given a user.
	 *
	 * @param \WP_User $user User object.
	 *
	 * @return bool Whether OTP is enabled.
	 */
	public static function is_otp_enabled( $user ) {
		/**
		 * Filters whether OTP is enabled.
		 *
		 * @param bool $is_enabled Whether OTP is enabled.
		 * @param \WP_User $user User object.
		 */
		$is_enabled = apply_filters( 'newspack_magic_link_otp_enabled', true, $user );

		/** Only use OTP if it's a self-served authentication request. */
		if ( true === $is_enabled && \is_user_logged_in() && \get_current_user_id() !== $user->ID ) {
			return false;
		}

		return $is_enabled;
	}

	/**
	 * Get magic link token expiration period.
	 *
	 * @return int Expiration in seconds.
	 */
	private static function get_token_expiration_period() {
		/**
		 * Filters the duration of the magic link token expiration period.
		 *
		 * @param int    $length Duration of the expiration period in seconds.
		 */
		return \apply_filters( 'newspack_magic_link_token_expiration', 30 * MINUTE_IN_SECONDS );
	}

	/**
	 * Clear token cookies.
	 */
	public static function clear_token_cookies() {
		/** This filter is documented in wp-includes/pluggable.php */
		if ( ! apply_filters( 'send_auth_cookies', true ) ) {
			return;
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
			setcookie( self::COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		}

		if ( isset( $_COOKIE[ self::OTP_HASH_COOKIE ] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
			setcookie( self::OTP_HASH_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		}

	}

	/**
	 * Get locally stored secret to be used as part of the client hash.
	 *
	 * @param bool $reset Whether to generate a new secret.
	 */
	private static function get_client_secret( $reset = false ) {
		$secret = self::$session_secret;

		/** Fetch cookie if available. */
		if ( empty( $secret ) && isset( $_COOKIE[ self::COOKIE ] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			$secret = \sanitize_text_field( \wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		}

		/** Regenerate if empty or resetting. */
		if ( empty( $secret ) || true === $reset ) {
			$secret = \wp_generate_password( 43, false, false );
		}

		self::$session_secret = $secret;

		/** This filter is documented in wp-includes/pluggable.php */
		if ( \apply_filters( 'send_auth_cookies', true ) ) {
			/** Add an extra 5 minutes to the client secret cookie expiration. */
			$expiration = time() + self::get_token_expiration_period() + ( 5 * MINUTE_IN_SECONDS );

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
			setcookie( self::COOKIE, $secret, $expiration, COOKIEPATH, COOKIE_DOMAIN, true );
		}

		return $secret;
	}

	/**
	 * Get client hash for the current session, if self-served.
	 *
	 * @param \WP_User $user         User the client hash is being generated for.
	 * @param bool     $reset_secret Whether to reset the stored client secret.
	 *
	 * @return string|null Client hash or null if unable to generate one.
	 */
	private static function get_client_hash( $user, $reset_secret = false ) {
		/** Don't return client hash from CLI command */
		if ( defined( 'WP_CLI' ) ) {
			return null;
		}

		/** Return client hash only if it's self-served. */
		if ( \is_user_logged_in() && \get_current_user_id() !== $user->ID ) {
			return null;
		}

		$hash_args = [];

		/**
		 * Filters whether to use IP as hash argument.
		 *
		 * @param bool $use_ip
		 */
		if ( true === \apply_filters( 'newspack_magic_link_hash_use_ip', true ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
			if ( isset( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
				// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
				$hash_args['ip'] = \wp_unslash( $_SERVER['REMOTE_ADDR'] );
			}
		}

		/**
		 * Filters whether to use user agent as hash argument.
		 *
		 * @param bool $use_user_agent
		 */
		if ( true === \apply_filters( 'newspack_magic_link_hash_use_user_agent', false ) ) {
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
				$hash_args['user_agent'] = \wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
			}
		}

		/**
		 * Filters whether to use a locally stored secret as a client hash argument.
		 *
		 * @param bool $use_secret
		 */
		if ( true === \apply_filters( 'newspack_magic_link_hash_use_secret', false ) ) {
			$hash_args['secret'] = self::get_client_secret( $reset_secret );
		}

		/**
		 * Filters the client hash arguments for the current session.
		 *
		 * @param string[] $hash_args Client hash arguments.
		 */
		$hash_args = \apply_filters( 'newspack_magic_link_client_hash_args', $hash_args );

		return ! empty( $hash_args ) ? \wp_hash( implode( '', $hash_args ) ) : null;
	}

	/**
	 * Clear all user tokens.
	 *
	 * @param \WP_User $user User to clear tokens for.
	 */
	public static function clear_user_tokens( $user ) {
		\delete_user_meta( $user->ID, self::TOKENS_META );

		/**
		 * Fires after all user tokens are cleared.
		 *
		 * @param \WP_User $user User for which tokens were cleared.
		 */
		do_action( 'newspack_magic_link_user_tokens_cleared', $user );
	}

	/**
	 * Get a random OTP code.
	 *
	 * @return string Random OTP code.
	 */
	private static function get_random_otp_code() {
		$length = self::OTP_LENGTH;
		$otp    = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$otp .= strval( \wp_rand( 0, 9 ) );
		}
		return $otp;
	}

	/**
	 * Generate an OTP to be used as part of a token.
	 *
	 * @param \WP_User $user User to generate OTP for.
	 *
	 * @return array|null {
	 *   OTP or null if unable to generate one.
	 *
	 *   @type string $code     OTP code.
	 *   @type string $hash     OTP Hash.
	 *   @type int    $attempts Initial value for OTP authentication attempts.
	 * }
	 */
	private static function generate_otp( $user ) {
		if ( ! self::is_otp_enabled( $user ) ) {
			return null;
		}
		$code = self::get_random_otp_code();
		$hash = \wp_generate_password( 60, false, false );

		/** This filter is documented in wp-includes/pluggable.php */
		if ( \apply_filters( 'send_auth_cookies', true ) ) {
			/** Subtract 1 minute from the OTP hash cookie expiration. */
			$expiration = time() + self::get_token_expiration_period() - MINUTE_IN_SECONDS;

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
			setcookie( self::OTP_HASH_COOKIE, $hash, $expiration, COOKIEPATH, COOKIE_DOMAIN, true );
		}

		return [
			'code'     => $code,
			'hash'     => $hash,
			'attempts' => 0,
		];
	}

	/**
	 * Generate magic link token.
	 *
	 * @param \WP_User $user User to generate the magic link token for.
	 *
	 * @return array|\WP_Error {
	 *   Magic link token data.
	 *
	 *   @type string     $token  The token.
	 *   @type string     $client Client hash.
	 *   @type string     $time   Token creation time.
	 *   @type array|null $otp    The OTP data or null if unavailable.
	 * }
	 */
	public static function generate_token( $user ) {
		if ( ! self::can_magic_link( $user->ID ) ) {
			return new \WP_Error( 'newspack_magic_link_invalid_user', __( 'Invalid user.', 'newspack' ) );
		}

		$now    = time();
		$tokens = \get_user_meta( $user->ID, self::TOKENS_META, true );
		if ( empty( $tokens ) ) {
			$tokens = [];
		}

		$expire = $now - self::get_token_expiration_period();
		if ( ! empty( $tokens ) ) {
			/** Limit maximum tokens to 5. */
			$tokens = array_slice( $tokens, -4, 4 );
			/** Clear expired tokens. */
			foreach ( $tokens as $index => $token_data ) {
				if ( $token_data['time'] < $expire ) {
					unset( $tokens[ $index ] );
				}
			}
			$tokens = array_values( $tokens );
		}

		/** Generate the new token. */
		$token       = \wp_generate_password( 60, false, false );
		$client_hash = self::get_client_hash( $user, true );
		$token_data  = [
			'token'  => $token,
			'client' => ! empty( $client_hash ) ? $client_hash : '',
			'time'   => $now,
			'otp'    => self::generate_otp( $user ),
		];
		$tokens[]    = $token_data;
		\update_user_meta( $user->ID, self::TOKENS_META, $tokens );
		return $token_data;
	}

	/**
	 * Generate a magic link.
	 *
	 * @param \WP_User $user User to generate the magic link for.
	 * @param string   $url  Destination url. Default is home_url().
	 *
	 * @return string|\WP_Error Magic link url or WP_Error if token generation failed.
	 */
	public static function generate_url( $user, $url = '' ) {
		$token_data = self::generate_token( $user );

		if ( \is_wp_error( $token_data ) ) {
			return $token_data;
		}

		return \add_query_arg(
			[
				'action' => self::AUTH_ACTION,
				'email'  => urlencode( $user->user_email ),
				'token'  => $token_data['token'],
			],
			! empty( $url ) ? $url : \home_url()
		);
	}

	/**
	 * Send magic link email to reader.
	 *
	 * @param \WP_User $user        User to send the magic link to.
	 * @param string   $redirect_to Which page to redirect the reader after authenticating.
	 * @param boolean  $use_otp     Whether to attempt the use of OTP.
	 *
	 * @return bool|\WP_Error Whether the email was sent or WP_Error if sending failed.
	 */
	public static function send_email( $user, $redirect_to = '', $use_otp = true ) {
		$token_data = self::generate_token( $user );
		if ( \is_wp_error( $token_data ) ) {
			return $token_data;
		}
		$url                = \add_query_arg(
			[
				'action' => self::AUTH_ACTION,
				'email'  => urlencode( $user->user_email ),
				'token'  => $token_data['token'],
			],
			! empty( $redirect_to ) ? $redirect_to : \home_url()
		);
		$email_type         = 'MAGIC_LINK';
		$email_placeholders = [
			[
				'template' => '*MAGIC_LINK_URL*',
				'value'    => $url,
			],
		];
		if ( $use_otp && ! empty( $token_data['otp'] ) ) {
			$email_type           = 'OTP_AUTH';
			$email_placeholders[] = [
				'template' => '*MAGIC_LINK_OTP*',
				'value'    => $token_data['otp']['code'],
			];
		}
		return Emails::send_email(
			Reader_Activation_Emails::EMAIL_TYPES[ $email_type ],
			$user->user_email,
			$email_placeholders
		);
	}

	/**
	 * Verify and returns the valid token given a user, token and client.
	 *
	 * This method cleans up expired tokens and returns the token data for
	 * immediate use.
	 *
	 * @param int    $user_id User ID.
	 * @param string $client  Client hash.
	 * @param string $token   Token to verify.
	 *
	 * @return array|\WP_Error {
	 *   Token data.
	 *
	 *   @type string     $token  The token.
	 *   @type string     $client Client hash.
	 *   @type string     $time   Token creation time.
	 *   @type array|null $otp    The OTP data or null if unavailable.
	 * }
	 */
	public static function validate_token( $user_id, $client, $token ) {
		$errors = new \WP_Error();
		$user   = \get_user_by( 'id', $user_id );

		if ( ! $user ) {
			$errors->add( 'invalid_user', __( 'User not found.', 'newspack' ) );
		} elseif ( ! self::can_magic_link( $user->ID ) ) {
			$errors->add( 'invalid_user_type', __( 'Not allowed for this user', 'newspack' ) );
		} else {
			$tokens = \get_user_meta( $user->ID, self::TOKENS_META, true );
			if ( empty( $tokens ) || empty( $token ) ) {
				$errors->add( 'invalid_token', __( 'Invalid token.', 'newspack' ) );
			}
		}

		$valid_token = false;

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$expire = time() - self::get_token_expiration_period();

		foreach ( $tokens as $index => $token_data ) {
			if ( $token_data['time'] < $expire ) {
				unset( $tokens[ $index ] );

			} elseif ( $token_data['token'] === $token ) {
				$valid_token = $token_data;

				/** If token data has a client hash, it must be equal to the user's. */
				if ( ! empty( $token_data['client'] ) && $token_data['client'] !== $client ) {
					$errors->add( 'invalid_client', __( 'Invalid client.', 'newspack' ) );
				}

				unset( $tokens[ $index ] );
				break;
			}
		}

		if ( empty( $valid_token ) ) {
			$errors->add( 'invalid_token', __( 'Invalid token.', 'newspack' ) );
		}
		self::clear_token_cookies();

		$tokens = array_values( $tokens );
		\update_user_meta( $user->ID, self::TOKENS_META, $tokens );

		return $errors->has_errors() ? $errors : $valid_token;
	}

	/**
	 * Verify and returns the valid token given a user and an OTP hash and code.
	 *
	 * This method cleans up expired tokens and returns the token data for
	 * immediate use.
	 *
	 * @param int    $user_id User ID.
	 * @param string $hash    OTP hash.
	 * @param string $code    OTP code.
	 *
	 * @return array|\WP_Error {
	 *   Token data.
	 *
	 *   @type string     $token  The token.
	 *   @type string     $client Client hash.
	 *   @type string     $time   Token creation time.
	 *   @type array|null $otp    The OTP data or null if unavailable.
	 * }
	 */
	public static function validate_otp( $user_id, $hash, $code ) {
		$errors = new \WP_Error();
		$user   = \get_user_by( 'id', $user_id );

		if ( ! $user ) {
			$errors->add( 'invalid_user', __( 'User not found.', 'newspack' ) );
		} elseif ( ! self::can_magic_link( $user->ID ) ) {
			$errors->add( 'invalid_user_type', __( 'Not allowed for this user', 'newspack' ) );
		} elseif ( ! self::is_otp_enabled( $user ) ) {
			$errors->add( 'invalid_otp', __( 'OTP is not enabled.', 'newspack' ) );
		} else {
			$tokens = \get_user_meta( $user->ID, self::TOKENS_META, true );
			if ( empty( $tokens ) || empty( $hash ) || empty( $code ) ) {
				$errors->add( 'invalid_otp', __( 'Invalid OTP.', 'newspack' ) );
			}
		}

		$valid_token = false;

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$expire = time() - self::get_token_expiration_period();

		foreach ( $tokens as $index => $token_data ) {
			if ( $token_data['time'] < $expire ) {
				unset( $tokens[ $index ] );

			} elseif ( ! empty( $token_data['otp'] ) && $token_data['otp']['hash'] === $hash ) {
				$valid_token = $token_data;

				if ( $token_data['otp']['code'] === $code ) {
					unset( $tokens[ $index ] );
					self::clear_token_cookies();
				} else {
					// Handle OTP attempts from given hash.
					$tokens[ $index ]['otp']['attempts']++;
					if ( $token_data['otp']['attempts'] >= self::OTP_MAX_ATTEMPTS ) {
						$errors->add( 'max_otp_attempts', __( 'Maximum OTP attempts reached.', 'newspack' ) );
						unset( $tokens[ $index ] );
						self::clear_token_cookies();
					}
					$errors->add( 'invalid_otp', __( 'Invalid OTP.', 'newspack' ) );
				}
				break;
			}
		}

		if ( empty( $valid_token ) ) {
			$errors->add( 'invalid_hash', __( 'Invalid hash.', 'newspack' ) );
		}

		$tokens = array_values( $tokens );
		\update_user_meta( $user->ID, self::TOKENS_META, $tokens );

		return $errors->has_errors() ? $errors : $valid_token;
	}

	/**
	 * Handle a reader authentication attempt using magic link token.
	 *
	 * @param int    $user_id           User ID.
	 * @param string $token_or_otp_hash Either token or OTP hash. OTP hash will
	 *                                  be used if $otp_code is provided.
	 * @param string $otp_code          OTP code to authenticate.
	 *
	 * @return bool|\WP_Error Whether the user was authenticated or WP_Error.
	 */
	private static function authenticate( $user_id, $token_or_otp_hash, $otp_code = null ) {
		$token    = '';
		$otp_hash = '';
		if ( ! empty( $otp_code ) ) {
			$otp_hash = $token_or_otp_hash;
		} else {
			$token = $token_or_otp_hash;
		}

		/** Refresh reader session if same reader is already authenticated. */
		if ( \is_user_logged_in() && \get_current_user_id() !== $user_id ) {
			return false;
		}

		$user = \get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'invalid_user', __( 'User not found.', 'newspack' ) );
		}

		if ( ! empty( $otp_hash ) ) {
			if ( ! self::is_otp_enabled( $user ) ) {
				return new \WP_Error( 'invalid_otp', __( 'OTP is not enabled.', 'newspack' ) );
			}
			$token_data = self::validate_otp( $user_id, $otp_hash, $otp_code );
		} else {
			$client_hash = self::get_client_hash( $user );
			$token_data  = self::validate_token( $user_id, $client_hash, $token );
		}

		if ( empty( $token_data ) ) {
			return false;
		}

		if ( \is_wp_error( $token_data ) ) {
			return $token_data;
		}

		// Authenticate the reader.
		Reader_Activation::set_current_reader( $user->ID );

		/**
		 * Fires after a reader has been authenticated via magic link.
		 *
		 * @param \WP_User $user User that has been authenticated.
		 */
		do_action( 'newspack_magic_link_authenticated', $user );

		return true;
	}

	/**
	 * Process magic link token from request.
	 */
	public static function process_token_request() {
		if ( ! Reader_Activation::is_enabled() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::AUTH_ACTION_RESULT ] ) && 0 === \absint( $_GET[ self::AUTH_ACTION_RESULT ] ) ) {
			\add_action(
				'before_header',
				function () {
					?>
					<style>
						.newspack-magic-link-error {
							text-align: center;
							padding: 1em;
							font-size: 0.7em;
							border-bottom: 1px solid var( --wc-red );
						}
					</style>
					<div class="newspack-magic-link-error">
						<?php \esc_html_e( 'We were not able to authenticate your account. Please try logging in again.', 'newspack' ); ?>
					</div>
					<?php
				},
				1
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['action'] ) || self::AUTH_ACTION !== $_GET['action'] ) {
			return;
		}

		$errored = false;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['token'] ) || ! isset( $_GET['email'] ) ) {
			$errored = true;
		}

		if ( ! $errored ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$email = \sanitize_email( $_GET['email'] );
			if ( $email ) {
				$user = \get_user_by( 'email', $email );
				if ( ! $user ) {
					$errored = true;
				}
			} else {
				$errored = true;
			}
		}

		$authenticated = false;

		if ( ! $errored ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$token         = \sanitize_text_field( \wp_unslash( $_GET['token'] ) );
			$authenticated = self::authenticate( $user->ID, $token );
		}

		\wp_safe_redirect(
			\add_query_arg(
				[ self::AUTH_ACTION_RESULT => true === $authenticated ? '1' : '0' ],
				\remove_query_arg( [ 'action', 'email', 'token' ] )
			)
		);
		exit;
	}

	/**
	 * Send JSON response to an OTP authentication request.
	 *
	 * @param string $message Message to display to the user.
	 * @param bool   $success Whether the request was successful.
	 * @param array  $data    Optional data to send.
	 */
	private static function send_otp_request_response( $message = '', $success = false, $data = [] ) {
		if ( ! \wp_is_json_request() ) {
			\wp_die( \esc_html__( 'Unsupported request method', 'newspack' ) );
		}
		\wp_send_json(
			[
				'message' => $message,
				'success' => (bool) $success,
				'data'    => $data,
			],
			$success ? 200 : 400
		);
	}

	/**
	 * Process OTP request.
	 */
	public static function process_otp_request() {
		if ( ! Reader_Activation::is_enabled() ) {
			return;
		}
		if ( \is_user_logged_in() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['action'] ) || self::OTP_AUTH_ACTION !== $_POST['action'] ) {
			return;
		}
		$hash  = isset( $_POST['hash'] ) ? \sanitize_text_field( \wp_unslash( $_POST['hash'] ) ) : '';
		$code  = isset( $_POST['code'] ) ? \sanitize_text_field( \wp_unslash( $_POST['code'] ) ) : '';
		$email = isset( $_POST['email'] ) ? \sanitize_email( $_POST['email'] ) : '';
		// phpcs:enable

		if ( ! \wp_is_json_request() ) {
			\wp_die( \esc_html__( 'Unsupported request method', 'newspack' ) );
		}

		if ( empty( $hash ) || empty( $code ) || empty( $email ) ) {
			return self::send_otp_request_response( __( 'Missing required parameters', 'newspack' ), false );
		}

		$user = \get_user_by( 'email', $email );
		if ( ! $user ) {
			return self::send_otp_request_response( __( 'Invalid user', 'newspack' ), false );
		}
		if ( ! self::is_otp_enabled( $user ) ) {
			return;
		}

		$authenticated = self::authenticate( $user->ID, $hash, $code );

		if ( is_wp_error( $authenticated ) ) {
			if ( 'max_otp_attempts' === $authenticated->get_error_code() ) {
				return self::send_otp_request_response( __( "You've reached the maximum attempts for this code. Please try again.", 'newspack' ), false, [ 'expired' => true ] );
			}
			if ( 'invalid_otp' === $authenticated->get_error_code() ) {
				return self::send_otp_request_response( __( 'The code does not match.', 'newspack' ), false );
			}
		}

		if ( true !== $authenticated ) {
			return self::send_otp_request_response( __( 'Unable to authenticated. Please try again.', 'newspack' ), false, [ 'expired' => true ] );
		}

		return self::send_otp_request_response( __( 'Login successful!', 'newspack' ), true );
	}

	/**
	 * WP CLI Commands.
	 */
	public static function wp_cli() {
		if ( ! defined( 'WP_CLI' ) ) {
			return;
		}
		if ( ! Reader_Activation::is_enabled() ) {
			return;
		}

		/**
		 * Sends a magic link to a reader.
		 *
		 * ## OPTIONS
		 *
		 * <email_or_id>
		 * : The email address or user ID of the reader.
		 *
		 * ## EXAMPLES
		 *
		 *     wp newspack magic-link send 12
		 *     wp newspack magic-link send john@doe.com
		 *
		 * @when after_wp_load
		 */
		$send = function( $args, $assoc_args ) {
			if ( ! isset( $args[0] ) ) {
				\WP_CLI::error( 'Please provide a user email or ID.' );
			}
			$id_or_email = $args[0];

			if ( \absint( $id_or_email ) ) {
				$user = \get_user_by( 'id', $id_or_email );
			} else {
				$user = \get_user_by( 'email', $id_or_email );
			}

			if ( ! $user || \is_wp_error( $user ) ) {
				\WP_CLI::error( __( 'User not found.', 'newspack' ) );
			}

			$result = self::send_email( $user );

			if ( \is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}

			// translators: %s is the email address of the user.
			\WP_CLI::success( sprintf( __( 'Email sent to %s.', 'newspack' ), $user->user_email ) );
		};
		\WP_CLI::add_command(
			'newspack magic-link send',
			$send,
			[
				'shortdesc' => __( 'Send a magic link to a reader.', 'newspack' ),
			]
		);
	}

	/**
	 * Get url for an admin action.
	 *
	 * @param string $action  Which admin action get the URL for.
	 * @param int    $user_id User to get the URL for.
	 *
	 * @return string Admin URL to perform an admin action.
	 */
	private static function get_admin_action_url( $action, $user_id ) {
		if ( ! \is_admin() ) {
			return '';
		}
		if ( ! isset( self::ADMIN_ACTIONS[ $action ] ) ) {
			return '';
		}
		$admin_action = self::ADMIN_ACTIONS[ $action ];
		return \add_query_arg(
			[
				'action'   => $admin_action,
				'uid'      => $user_id,
				'_wpnonce' => \wp_create_nonce( $admin_action ),
			]
		);
	}

	/**
	 * Adds magic link send action to user row actions.
	 *
	 * @param string[] $actions User row actions.
	 * @param \WP_User $user    User object.
	 *
	 * @return string[] User row actions.
	 */
	public static function user_row_actions( $actions, $user ) {
		if ( ! Reader_Activation::is_enabled() ) {
			return $actions;
		}
		if ( self::can_magic_link( $user->ID ) && \current_user_can( 'edit_user', $user->ID ) ) {
			$url                                 = self::get_admin_action_url( 'send', $user->ID );
			$actions['newspack-magic-link-send'] = '<a href="' . $url . '">' . \esc_html__( 'Send authentication link', 'newspack' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Process admin action request.
	 */
	public static function process_admin_action() {
		if ( ! Reader_Activation::is_enabled() ) {
			return;
		}

		$actions = self::ADMIN_ACTIONS;

		/** Add notice if admin action was successful. */
		if ( isset( $_GET['update'] ) && in_array( $_GET['update'], $actions, true ) ) {
			$update  = \sanitize_text_field( \wp_unslash( $_GET['update'] ) );
			$message = '';
			switch ( $update ) {
				case $actions['send']:
					$message = __( 'Authentication link sent.', 'newspack' );
					break;
				case $actions['clear']:
					$message = __( 'All authentication link tokens were removed.', 'newspack' );
					break;
				case $actions['disable']:
					$message = __( 'Authentication links are now disabled.', 'newspack' );
					break;
				case $actions['enable']:
					$message = __( 'Authentication links are now enabled.', 'newspack' );
					break;
			}
			if ( ! empty( $message ) ) {
				\add_action(
					'admin_notices',
					function() use ( $message ) {
						?>
						<div id="message" class="updated notice is-dismissible"><p><?php echo \esc_html( $message ); ?></p></div>
						<?php
					}
				);
			}
		}

		if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], $actions, true ) ) {
			return;
		}

		$action = \sanitize_text_field( \wp_unslash( $_GET['action'] ) );

		if ( ! isset( $_GET['uid'] ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'newspack' ) );
		}

		if ( ! \check_admin_referer( $action ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'newspack' ) );
		}

		$user_id = \absint( \wp_unslash( $_GET['uid'] ) );

		if ( ! \current_user_can( 'edit_user', $user_id ) ) {
			\wp_die( \esc_html__( 'You do not have permission to do that.', 'newspack' ) );
		}

		$user = \get_user_by( 'id', $user_id );

		if ( ! $user || \is_wp_error( $user ) ) {
			\wp_die( \esc_html__( 'User not found.', 'newspack' ) );
		}

		switch ( $action ) {
			case $actions['send']:
				$result = self::send_email( $user );
				if ( \is_wp_error( $result ) ) {
					\wp_die( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				break;
			case $actions['clear']:
				self::clear_user_tokens( $user );
				break;
			case $actions['disable']:
				self::clear_user_tokens( $user );
				\update_user_meta( $user_id, self::DISABLED_META, true );
				break;
			case $actions['enable']:
				\delete_user_meta( $user_id, self::DISABLED_META );
				break;
		}

		$redirect = \add_query_arg( [ 'update' => $action ], \remove_query_arg( [ 'action', 'uid', '_wpnonce' ] ) );
		\wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Magic link management for the user profile editor page.
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public static function edit_user_profile( $user ) {
		if ( ! Reader_Activation::is_enabled() ) {
			return;
		}

		if ( ! Reader_Activation::is_user_reader( $user ) ) {
			return;
		}

		$disabled = (bool) \get_user_meta( $user->ID, self::DISABLED_META, true );
		?>
		<div class="newspack-magic-link-management">
			<h2><?php _e( 'Passwordless Authentication Management', 'newspack' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr id="newspack-magic-link-support">
					<th><label><?php _e( 'Authentication Link Support', 'newspack' ); ?></label></th>
					<td>
						<?php if ( $disabled ) : ?>
							<a class="button" href="<?php echo \esc_url( self::get_admin_action_url( 'enable', $user->ID ) ); ?>"><?php _e( 'Enable Authentication Links' ); ?></a>
						<?php else : ?>
							<a class="button" href="<?php echo \esc_url( self::get_admin_action_url( 'disable', $user->ID ) ); ?>"><?php _e( 'Disable Authentication Links' ); ?></a>
						<?php endif; ?>
						<p class="description">
								<?php
								printf(
									/* translators: %1$s: Disabled or enabled. %2$s: User's display name. */
									\esc_html__( 'Authentication links support is currently %1$s for %2$s.', 'newspack' ),
									$disabled ? \esc_html__( 'disabled', 'newspack' ) : \esc_html__( 'enabled', 'newspack' ),
									\esc_html( $user->display_name )
								);
								?>
							</p>
					</td>
				</tr>
				<?php if ( ! $disabled ) : ?>
					<tr id="newspack-magic-link-send">
						<th><label><?php _e( 'Send Authentication Link', 'newspack' ); ?></label></th>
						<td>
							<a class="button" href="<?php echo \esc_url( self::get_admin_action_url( 'send', $user->ID ) ); ?>"><?php _e( 'Send Authentication Link' ); ?></a>
							<p class="description">
								<?php
								printf(
									/* translators: %1$s: User's display name. %2$d is the expiration period in minutes. */
									\esc_html__( 'Generate and send a new link to %1$s, which will authenticate them instantly. The link will be valid for %2$d minutes after its creation.', 'newspack' ),
									\esc_html( $user->display_name ),
									\esc_html( \absint( self::get_token_expiration_period() ) / MINUTE_IN_SECONDS )
								);
								?>
							</p>
						</td>
					</tr>
					<tr id="newspack-magic-link-clear">
						<th><label><?php _e( 'Clear All Tokens', 'newspack' ); ?></label></th>
						<td>
							<a class="button" href="<?php echo \esc_url( self::get_admin_action_url( 'clear', $user->ID ) ); ?>"><?php _e( 'Clear All Tokens' ); ?></a>
							<p class="description">
								<?php
								printf(
									/* translators: %s: User's display name. */
									\esc_html__( 'Clear all existing authentication link tokens for %s.', 'newspack' ),
									\esc_html( $user->display_name )
								);
								?>
							</p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
		</div>
		<?php
	}
}
Magic_Link::init();
