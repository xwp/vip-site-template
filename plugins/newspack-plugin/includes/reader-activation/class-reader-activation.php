<?php
/**
 * Reader Activation.
 *
 * @package Newspack
 */

namespace Newspack;

use Newspack\Recaptcha;

defined( 'ABSPATH' ) || exit;

/**
 * Reader Activation Class.
 */
final class Reader_Activation {

	const OPTIONS_PREFIX = 'newspack_reader_activation_';

	const AUTH_READER_COOKIE    = 'np_auth_reader';
	const AUTH_INTENTION_COOKIE = 'np_auth_intention';
	const SCRIPT_HANDLE         = 'newspack-reader-activation';
	const AUTH_SCRIPT_HANDLE    = 'newspack-reader-auth';

	/**
	 * Reader user meta keys.
	 */
	const READER              = 'np_reader';
	const EMAIL_VERIFIED      = 'np_reader_email_verified';
	const WITHOUT_PASSWORD    = 'np_reader_without_password';
	const REGISTRATION_METHOD = 'np_reader_registration_method';


	/**
	 * Unverified email rate limiting
	 */
	const LAST_EMAIL_DATE = 'np_reader_last_email_date';
	const EMAIL_INTERVAL  = 10; // 10 seconds

	/**
	 * Auth form.
	 */
	const AUTH_FORM_ACTION  = 'reader-activation-auth-form';
	const AUTH_FORM_OPTIONS = [
		'pwd',
		'link',
		'register',
	];

	/**
	 * Registration methods that don't require account verification.
	 */
	const SSO_REGISTRATION_METHODS = [ 'google' ];

	/**
	 * Whether the session is authenticating a newly registered reader
	 *
	 * @var bool
	 */
	private static $is_new_reader_auth = false;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		if ( self::is_enabled() ) {
			\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
			\add_action( 'clear_auth_cookie', [ __CLASS__, 'clear_auth_intention_cookie' ] );
			\add_action( 'clear_auth_cookie', [ __CLASS__, 'clear_auth_reader_cookie' ] );
			\add_action( 'set_auth_cookie', [ __CLASS__, 'clear_auth_intention_cookie' ] );
			\add_filter( 'login_form_defaults', [ __CLASS__, 'add_auth_intention_to_login_form' ], 20 );
			\add_action( 'resetpass_form', [ __CLASS__, 'set_reader_verified' ] );
			\add_action( 'password_reset', [ __CLASS__, 'set_reader_verified' ] );
			\add_action( 'password_reset', [ __CLASS__, 'set_reader_has_password' ] );
			\add_action( 'newspack_magic_link_authenticated', [ __CLASS__, 'set_reader_verified' ] );
			\add_action( 'auth_cookie_expiration', [ __CLASS__, 'auth_cookie_expiration' ], 10, 3 );
			\add_action( 'init', [ __CLASS__, 'setup_nav_menu' ] );
			\add_action( 'wp_footer', [ __CLASS__, 'render_auth_form' ] );
			\add_action( 'wc_get_template', [ __CLASS__, 'replace_woocommerce_auth_form' ], 10, 2 );
			\add_action( 'template_redirect', [ __CLASS__, 'process_auth_form' ] );
			\add_filter( 'amp_native_post_form_allowed', '__return_true' );
			\add_filter( 'woocommerce_email_actions', [ __CLASS__, 'disable_woocommerce_new_user_email' ] );
			\add_filter( 'retrieve_password_notification_email', [ __CLASS__, 'password_reset_configuration' ], 10, 4 );
			\add_action( 'lostpassword_post', [ __CLASS__, 'set_password_reset_mail_content_type' ] );
			\add_filter( 'lostpassword_errors', [ __CLASS__, 'rate_limit_lost_password' ], 10, 2 );
			\add_filter( 'woocommerce_checkout_customer_id', [ __CLASS__, 'associate_existing_woo_users_with_transactions_on_checkout' ] );
			\add_filter( 'woocommerce_checkout_posted_data', [ __CLASS__, 'dont_force_registration_for_existing_woo_users' ], 11 );
		}
	}

	/**
	 * Enqueue front-end scripts.
	 */
	public static function enqueue_scripts() {
		$authenticated_email = '';
		if ( \is_user_logged_in() && self::is_user_reader( \wp_get_current_user() ) ) {
			$authenticated_email = \wp_get_current_user()->user_email;
		}
		$script_dependencies = [];
		$script_data         = [
			'auth_intention_cookie' => self::AUTH_INTENTION_COOKIE,
			'cid_cookie'            => NEWSPACK_CLIENT_ID_COOKIE_NAME,
			'authenticated_email'   => $authenticated_email,
			'otp_auth_action'       => Magic_Link::OTP_AUTH_ACTION,
			'account_url'           => function_exists( 'wc_get_account_endpoint_url' ) ? \wc_get_account_endpoint_url( 'dashboard' ) : '',
		];

		if ( Recaptcha::can_use_captcha() ) {
			$script_dependencies[]           = Recaptcha::SCRIPT_HANDLE;
			$script_data['captcha_site_key'] = Recaptcha::get_setting( 'site_key' );
		}

		/**
		 * Reader Activation Frontend Library.
		 */
		\wp_register_script(
			self::SCRIPT_HANDLE,
			Newspack::plugin_url() . '/dist/reader-activation.js',
			$script_dependencies,
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		\wp_localize_script(
			self::SCRIPT_HANDLE,
			'newspack_reader_activation_data',
			$script_data
		);
		\wp_script_add_data( self::SCRIPT_HANDLE, 'async', true );
		\wp_script_add_data( self::SCRIPT_HANDLE, 'amp-plus', true );

		/**
		 * Reader Authentication
		 */
		\wp_enqueue_script(
			self::AUTH_SCRIPT_HANDLE,
			Newspack::plugin_url() . '/dist/reader-auth.js',
			[ self::SCRIPT_HANDLE ],
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		\wp_localize_script(
			self::AUTH_SCRIPT_HANDLE,
			'newspack_reader_auth_labels',
			[
				'invalid_email'    => __( 'Please enter a valid email address.', 'newspack' ),
				'invalid_password' => __( 'Please enter a password.', 'newspack' ),
				'blocked_popup'    => __( 'The popup has been blocked. Allow popups for the site and try again.', 'newspack' ),
			]
		);
		\wp_script_add_data( self::AUTH_SCRIPT_HANDLE, 'async', true );
		\wp_script_add_data( self::AUTH_SCRIPT_HANDLE, 'amp-plus', true );
		\wp_enqueue_style(
			self::AUTH_SCRIPT_HANDLE,
			Newspack::plugin_url() . '/dist/reader-auth.css',
			[],
			NEWSPACK_PLUGIN_VERSION
		);
	}

	/**
	 * Get settings config with default values.
	 *
	 * @return mixed[] Settings default values keyed by their name.
	 */
	private static function get_settings_config() {
		$settings_config = [
			'enabled'                     => true,
			'enabled_account_link'        => false,
			'account_link_menu_locations' => [ 'tertiary-menu' ],
			'newsletters_label'           => __( 'Subscribe to our newsletters:', 'newspack' ),
			'terms_text'                  => __( 'By signing up, you agree to our Terms and Conditions.', 'newspack' ),
			'terms_url'                   => '',
			'sync_esp'                    => true,
			'metadata_prefix'             => Newspack_Newsletters::get_metadata_prefix(),
			'sync_esp_delete'             => true,
			'active_campaign_master_list' => '',
			'mailchimp_audience_id'       => '',
			'emails'                      => Emails::get_emails( array_values( Reader_Activation_Emails::EMAIL_TYPES ), false ),
			'sender_name'                 => Emails::get_from_name(),
			'sender_email_address'        => Emails::get_from_email(),
			'contact_email_address'       => Emails::get_reply_to_email(),
		];

		/**
		 * Filters the global settings config for reader activation.
		 *
		 * @param mixed[] $settings_config Settings default values keyed by their name.
		 */
		return apply_filters( 'newspack_reader_activation_settings_config', $settings_config );
	}

	/**
	 * Get reader activation global settings.
	 *
	 * @return mixed[] Global settings keyed by their option name.
	 */
	public static function get_settings() {
		$config = self::get_settings_config();

		$settings = [];
		foreach ( $config as $key => $default_value ) {
			$settings[ $key ] = self::get_setting( $key );
		}

		return $settings;
	}

	/**
	 * Get a reader activation settings.
	 *
	 * @param string $name Setting name.
	 *
	 * @return mixed Setting value.
	 */
	public static function get_setting( $name ) {
		$config = self::get_settings_config();
		if ( ! isset( $config[ $name ] ) ) {
			return null;
		}
		$value = \get_option( self::OPTIONS_PREFIX . $name, $config[ $name ] );

		// If fetching terms URL, set the default here as \get_permalink and \get_post_status aren't available on the init hook.
		if ( 'terms_url' === $name && empty( $value ) ) {
			$privacy_policy_page_id = \get_option( 'wp_page_for_privacy_policy' );
			if ( ! empty( $privacy_policy_page_id ) && 'publish' === \get_post_status( $privacy_policy_page_id ) ) {
				$value = \get_permalink( $privacy_policy_page_id );
			}
		}

		// Use default value type for casting bool option value.
		if ( is_bool( $config[ $name ] ) ) {
			$value = (bool) $value;
		}
		return $value;
	}

	/**
	 * Update a reader activation setting.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Option value.
	 *
	 * @return bool True if the value was updated, false otherwise.
	 */
	public static function update_setting( $key, $value ) {
		$config = self::get_settings_config();
		if ( ! isset( $config[ $key ] ) ) {
			return false;
		}
		if ( is_bool( $value ) ) {
			$value = intval( $value );
		}
		if ( 'metadata_prefix' === $key ) {
			return Newspack_Newsletters::update_metadata_prefix( $value );
		}

		return \update_option( self::OPTIONS_PREFIX . $key, $value );
	}

	/**
	 * Check if the required Woo plugins are active.
	 *
	 * @return boolean True if all required plugins are active, otherwise false.
	 */
	public static function is_woocommerce_active() {
		$is_active = Donations::is_woocommerce_suite_active();

		if ( \is_wp_error( $is_active ) ) {
			return false;
		}

		return $is_active;
	}

	/**
	 * Whether reader activation is enabled.
	 *
	 * @param bool $strict If true, check both the environment constant and the setting.
	 *                     If false, only check for the constant.
	 *
	 * @return bool True if reader activation is enabled.
	 */
	public static function is_enabled( $strict = true ) {
		if ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) {
			return true;
		}

		$is_enabled = defined( 'NEWSPACK_EXPERIMENTAL_READER_ACTIVATION' ) && NEWSPACK_EXPERIMENTAL_READER_ACTIVATION;

		if ( ! $strict ) {
			return $is_enabled;
		}

		if ( $is_enabled ) {
			$is_enabled = (bool) \get_option( self::OPTIONS_PREFIX . 'enabled', true );
		}

		/**
		 * Filters whether reader activation is enabled.
		 *
		 * @param bool $is_enabled Whether reader activation is enabled.
		 */
		return \apply_filters( 'newspack_reader_activation_enabled', $is_enabled );
	}

	/**
	 * Add auth intention email to login form defaults.
	 *
	 * @param array $defaults Login form defaults.
	 *
	 * @return array
	 */
	public static function add_auth_intention_to_login_form( $defaults ) {
		$email = self::get_auth_intention_value();
		if ( ! empty( $email ) ) {
			$defaults['label_username'] = __( 'Email address', 'newspack' );
			$defaults['value_username'] = $email;
		}
		return $defaults;
	}

	/**
	 * Clear the auth intention cookie.
	 */
	public static function clear_auth_intention_cookie() {
		/** This filter is documented in wp-includes/pluggable.php */
		if ( ! apply_filters( 'send_auth_cookies', true ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( self::AUTH_INTENTION_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Set the auth intention cookie.
	 *
	 * @param string $email Email address.
	 */
	public static function set_auth_intention_cookie( $email ) {
		/** This filter is documented in wp-includes/pluggable.php */
		if ( ! apply_filters( 'send_auth_cookies', true ) ) {
			return;
		}

		/**
		 * Filters the duration of the auth intention cookie expiration period.
		 *
		 * @param int    $length Duration of the expiration period in seconds.
		 * @param string $email  Email address.
		 */
		$expire = time() + \apply_filters( 'newspack_auth_intention_expiration', 30 * DAY_IN_SECONDS, $email );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( self::AUTH_INTENTION_COOKIE, $email, $expire, COOKIEPATH, COOKIE_DOMAIN, true );
	}

	/**
	 * Clear cookie that indicates the reader is authenticated.
	 */
	public static function clear_auth_reader_cookie() {
		/** This filter is documented in wp-includes/pluggable.php */
		if ( ! apply_filters( 'send_auth_cookies', true ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( self::AUTH_READER_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Set cookie to indicate the reader has been authenticated.
	 *
	 * This cookie expiration doesn't matter, as it's intended to be read right
	 * after a frontend action that might have registered/authenticated a reader.
	 *
	 * Do not use this cookie for validation.
	 *
	 * @param \WP_User $user User object.
	 */
	public static function set_auth_reader_cookie( $user ) {
		/** This filter is documented in wp-includes/pluggable.php */
		if ( ! apply_filters( 'send_auth_cookies', true ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( self::AUTH_READER_COOKIE, $user->user_email, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, true );
	}

	/**
	 * Get the auth intention value.
	 *
	 * @return string|null Email address or null if not set.
	 */
	public static function get_auth_intention_value() {
		$email_address = null;
		if ( isset( $_COOKIE[ self::AUTH_INTENTION_COOKIE ] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			$email_address = \sanitize_email( $_COOKIE[ self::AUTH_INTENTION_COOKIE ] );
		}
		/**
		 * Filters the session auth intention email address.
		 *
		 * @param string|null $email_address Email address or null if not set.
		 */
		return \apply_filters( 'newspack_auth_intention', $email_address );
	}

	/**
	 * Whether the user is a reader.
	 *
	 * @param \WP_User $user   User object.
	 * @param bool     $strict Whether to check if the user was created through reader registration. Default false.
	 *
	 * @return bool Whether the user is a reader.
	 */
	public static function is_user_reader( $user, $strict = false ) {
		$is_reader = (bool) \get_user_meta( $user->ID, self::READER, true );
		$user_data = \get_userdata( $user->ID );

		if ( false === $is_reader && false === $strict ) {
			/**
			 * Filters the roles that can determine if a user is a reader.
			 *
			 * @param string[] $roles Array of user roles.
			 */
			$reader_roles = \apply_filters( 'newspack_reader_user_roles', [ 'subscriber', 'customer' ] );
			if ( ! empty( $reader_roles ) ) {
				$is_reader = ! empty( array_intersect( $reader_roles, $user_data->roles ) );
			}
		}

		/**
		 * Filters roles that restricts a user from being a reader.
		 *
		 * @param string[] $roles Array of user roles that restrict a user from being a reader.
		 */
		$restricted_roles = \apply_filters( 'newspack_reader_restricted_roles', [ 'administrator', 'editor' ] );
		if ( ! empty( $restricted_roles ) && $is_reader && ! empty( array_intersect( $restricted_roles, $user_data->roles ) ) ) {
			$is_reader = false;
		}

		/**
		 * Filters whether the user is a reader.
		 *
		 * @param bool     $is_reader Whether the user is a reader.
		 * @param \WP_User $user      User object.
		 */
		return (bool) \apply_filters( 'newspack_is_user_reader', $is_reader, $user );
	}

	/**
	 * Verify email address of a reader given the user.
	 *
	 * @param \WP_User|int $user_or_user_id User object.
	 *
	 * @return bool Whether the email address was verified.
	 */
	public static function set_reader_verified( $user_or_user_id ) {
		if ( $user_or_user_id instanceof \WP_User ) {
			$user = $user_or_user_id;
		} elseif ( absint( $user_or_user_id ) ) {
			$user = get_user_by( 'id', $user_or_user_id );
		}

		if ( ! isset( $user ) || ! $user || self::is_reader_verified( $user ) ) {
			return false;
		}

		/** Should not verify email if user is not a reader. */
		if ( ! self::is_user_reader( $user ) ) {
			return false;
		}

		\update_user_meta( $user->ID, self::EMAIL_VERIFIED, true );

		if ( function_exists( '\wc_add_notice' ) ) {
			\wc_add_notice( __( 'Thank you for verifying your account!', 'newspack' ), 'success' );
		}

		/**
		 * Fires after a reader's email address is verified.
		 *
		 * @param \WP_User $user User object.
		 */
		do_action( 'newspack_reader_verified', $user );

		return true;
	}

	/**
	 * Remove "without password" meta from user.
	 *
	 * @param \WP_User|int $user_or_user_id User object or user ID.
	 *
	 * @return bool Whether the meta was removed.
	 */
	public static function set_reader_has_password( $user_or_user_id ) {
		if ( $user_or_user_id instanceof \WP_User ) {
			$user = $user_or_user_id;
		} elseif ( absint( $user_or_user_id ) ) {
			$user = get_user_by( 'id', $user_or_user_id );
		}

		if ( ! isset( $user ) || ! $user ) {
			return false;
		}

		delete_user_meta( $user->ID, self::WITHOUT_PASSWORD );
		return true;
	}

	/**
	 * Whether the reader hasn't set its own password.
	 *
	 * @param \WP_User|int $user_or_user_id User object or user ID.
	 *
	 * @return bool|WP_Error Whether the reader hasn't set its password or error.
	 */
	public static function is_reader_without_password( $user_or_user_id ) {
		if ( $user_or_user_id instanceof \WP_User ) {
			$user = $user_or_user_id;
		} elseif ( absint( $user_or_user_id ) ) {
			$user = get_user_by( 'id', $user_or_user_id );
		}

		if ( ! isset( $user ) || ! $user || ! self::is_user_reader( $user ) ) {
			return new \WP_Error( 'newspack_is_reader_without_password', __( 'Invalid user.', 'newspack' ) );
		}

		return (bool) \get_user_meta( $user->ID, self::WITHOUT_PASSWORD, false );
	}

	/**
	 * Set custom auth cookie expiration for readers.
	 *
	 * @param int  $length   Duration of the expiration period in seconds.
	 * @param int  $user_id  User ID.
	 * @param bool $remember Whether to remember the user login. Default false.
	 *
	 * @return int Duration of the expiration period in seconds.
	 */
	public static function auth_cookie_expiration( $length, $user_id, $remember ) {
		if ( true === $remember ) {
			$user = \get_user_by( 'id', $user_id );
			if ( $user && self::is_user_reader( $user ) ) {
				$length = YEAR_IN_SECONDS;
			}
		}

		/**
		 * If the session is authenticating a newly registered reader we want the
		 * auth cookie to be short lived since the email ownership has not yet been
		 * verified.
		 */
		if ( true === self::$is_new_reader_auth ) {
			$length = 24 * HOUR_IN_SECONDS;
		}
		return $length;
	}

	/**
	 * Get a BEM formatted class name.
	 *
	 * @param string ...$parts The parts of the class name.
	 *
	 * @return string The BEM formatted class name.
	 */
	private static function get_element_class_name( ...$parts ) {
		if ( is_array( $parts[0] ) ) {
			$parts = $parts[0];
		}
		$parts = array_filter( $parts );
		array_unshift( $parts, 'newspack-reader' );
		return empty( $parts ) ? '' : implode( '__', $parts );
	}

	/**
	 * Setup nav menu hooks.
	 */
	public static function setup_nav_menu() {
		if ( ! self::get_setting( 'enabled_account_link' ) || ! self::is_woocommerce_active() ) {
			return;
		}

		$locations = self::get_setting( 'account_link_menu_locations' );
		$self      = new self();

		/** Always have location enabled for account link. */
		\add_filter(
			'has_nav_menu',
			function( $has_nav_menu, $location ) use ( $locations ) {
				if ( in_array( $location, $locations, true ) ) {
					$has_nav_menu = true;
				}
				return $has_nav_menu;
			},
			10,
			2
		);

		/** Fallback location to always print nav menu args */
		\add_filter(
			'wp_nav_menu_args',
			function( $args ) use ( $self, $locations ) {
				if ( in_array( $args['theme_location'], $locations, true ) ) {
					$args['fallback_cb'] = function( $args ) use ( $self ) {
						echo $self->nav_menu_items( '', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					};
				}
				return $args;
			}
		);

		/** Add as menu item */
		\add_filter( 'wp_nav_menu_items', [ __CLASS__, 'nav_menu_items' ], 20, 2 );

		/** Add mobile icon */
		\add_action(
			'newspack_header_after_mobile_toggle',
			function() use ( $self ) {
				?>
				<span class="<?php echo \esc_attr( self::get_element_class_name( 'account-link', 'mobile' ) ); ?>">
					<?php echo $self->get_account_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<?php
			}
		);
	}

	/**
	 * Setup nav menu items for reader account access.
	 *
	 * @param string   $output The HTML for the menu items.
	 * @param stdClass $args   An object containing wp_nav_menu() arguments.
	 *
	 * @return string The HTML list content for the menu items.
	 */
	public static function nav_menu_items( $output, $args = [] ) {
		$args      = (object) $args;
		$locations = self::get_setting( 'account_link_menu_locations' );

		/** Do not alter items for authenticated non-readers */
		if ( \is_user_logged_in() && ! self::is_user_reader( \wp_get_current_user() ) && ! \is_customize_preview() ) {
			return $output;
		}

		/**
		 * Menu locations to add the account menu items to.
		 */
		if ( ! in_array( $args->theme_location, $locations, true ) ) {
			return $output;
		}

		$link = self::get_account_link();
		if ( empty( $link ) ) {
			return $output;
		}

		$item  = '<li class="menu-item">';
		$item .= $link;
		$item .= '</li>';

		if ( empty( $output ) ) {
			$menu_class = sprintf( '%s %s', $args->menu_class, self::get_element_class_name( 'account-menu' ) );
			$output     = sprintf( $args->items_wrap ?? '<ul id="%1$s" class="%2$s">%3$s</ul>', $args->menu_id, $menu_class, $item );
		} else {
			$output = $output . $item;
		}
		return $output;
	}

	/**
	 * Get the account icon SVG markup.
	 *
	 * @return string The account icon SVG markup.
	 */
	private static function get_account_icon() {
		return '<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.25 16.4371C6.16445 15.2755 5.5 13.7153 5.5 12C5.5 8.41015 8.41015 5.5 12 5.5C15.5899 5.5 18.5 8.41015 18.5 12C18.5 13.7153 17.8356 15.2755 16.75 16.4371V16C16.75 14.4812 15.5188 13.25 14 13.25L10 13.25C8.48122 13.25 7.25 14.4812 7.25 16V16.4371ZM8.75 17.6304C9.70606 18.1835 10.8161 18.5 12 18.5C13.1839 18.5 14.2939 18.1835 15.25 17.6304V16C15.25 15.3096 14.6904 14.75 14 14.75L10 14.75C9.30964 14.75 8.75 15.3096 8.75 16V17.6304ZM4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12ZM14 10C14 11.1046 13.1046 12 12 12C10.8954 12 10 11.1046 10 10C10 8.89543 10.8954 8 12 8C13.1046 8 14 8.89543 14 10Z" /></svg>';
	}

	/**
	 * Get the error icon SVG markup.
	 *
	 * @return string The error icon SVG markup.
	 */
	private static function get_error_icon() {
		return '<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 20.8C16.8 20.8 20.8 16.9 20.8 12C20.8 7.2 16.9 3.2 12 3.2C7.2 3.2 3.2 7.1 3.2 12C3.2 16.8 7.2 20.8 12 20.8V20.8ZM12 4.8C16 4.8 19.2 8.1 19.2 12C19.2 16 16 19.2 12 19.2C8 19.2 4.8 15.9 4.8 12C4.8 8 8 4.8 12 4.8ZM13 7H11V13H13L13 7ZM13 15H11V17H13V15Z" /></svg>';
	}

	/**
	 * Get the check icon SVG markup.
	 *
	 * @return string The check icon SVG markup.
	 */
	private static function get_check_icon() {
		return '<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z" /></svg>';
	}

	/**
	 * Get account link.
	 *
	 * @return string Account link HTML or empty string.
	 */
	private static function get_account_link() {
		$account_url = '';
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			$account_url = \wc_get_account_endpoint_url( 'dashboard' );
		}

		/** Do not render link for authenticated readers if account page doesn't exist. */
		if ( empty( $account_url ) && \is_user_logged_in() ) {
			return '';
		}

		$class = function( ...$parts ) {
			array_unshift( $parts, 'account-link' );
			return self::get_element_class_name( $parts );
		};

		$labels = [
			'signedin'  => \__( 'My Account', 'newspack' ),
			'signedout' => \__( 'Sign In', 'newspack' ),
		];
		$label  = \is_user_logged_in() ? 'signedin' : 'signedout';

		$link  = '<a class="' . \esc_attr( $class() ) . '" data-labels="' . \esc_attr( htmlspecialchars( \wp_json_encode( $labels ), ENT_QUOTES, 'UTF-8' ) ) . '" href="' . \esc_url_raw( $account_url ?? '#' ) . '" data-newspack-reader-account-link>';
		$link .= '<span class="' . \esc_attr( $class( 'icon' ) ) . '">';
		$link .= self::get_account_icon();
		$link .= '</span>';
		$link .= '<span class="' . \esc_attr( $class( 'label' ) ) . '">' . \esc_html( $labels[ $label ] ) . '</span>';
		$link .= '</a>';

		/**
		 * Filters the HTML for the reader account link.
		 *
		 * @param string $link HTML for the reader account link.
		 */
		return apply_filters( 'newspack_reader_account_link', $link );
	}

	/**
	 * Render a honeypot field to guard against bot form submissions. Note that
	 * this field is named `email` to hopefully catch more bots who might be
	 * looking for such fields, where as the "real" field is named "npe".
	 *
	 * @param string $placeholder Placeholder text to render in the field.
	 */
	public static function render_honeypot_field( $placeholder = '' ) {
		if ( empty( $placeholder ) ) {
			$placeholder = __( 'Enter your email address', 'newspack' );
		}
		?>
		<input class="nphp" tabindex="-1" aria-hidden="true" name="email" type="email" autocomplete="off" placeholder="<?php echo \esc_attr( $placeholder ); ?>" />
		<?php
	}

	/**
	 * Renders reader authentication form.
	 *
	 * @param boolean $is_inline If true, render the form inline, otherwise render as a modal.
	 */
	public static function render_auth_form( $is_inline = false ) {
		// No need to render when logged in.
		if ( \is_user_logged_in() ) {
			return;
		}

		$class = function( ...$parts ) {
			array_unshift( $parts, 'auth-form' );
			return self::get_element_class_name( $parts );
		};

		$labels = [
			'signin'   => \__( 'Sign In', 'newspack' ),
			'register' => \__( 'Sign Up', 'newspack' ),
		];

		$message    = '';
		$classnames = [ 'newspack-reader-auth', $class() ];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['reader_authenticated'] ) && isset( $_GET['message'] ) ) {
			$message      = \sanitize_text_field( $_GET['message'] );
			$classnames[] = $class( 'visible' );
		}
		// phpcs:enable

		if ( $is_inline ) {
			$classnames[] = $class( 'inline' );
		}

		$newsletters_label = self::get_setting( 'newsletters_label' );
		if ( method_exists( 'Newspack_Newsletters_Subscription', 'get_lists_config' ) ) {
			$lists_config = \Newspack_Newsletters_Subscription::get_lists_config();
			if ( ! \is_wp_error( $lists_config ) ) {
				$lists = $lists_config;
			}
		}
		$terms_text      = self::get_setting( 'terms_text' );
		$terms_url       = self::get_setting( 'terms_url' );
		$is_account_page = function_exists( '\wc_get_page_id' ) ? \get_the_ID() === \wc_get_page_id( 'myaccount' ) : false;
		$redirect        = $is_account_page ? \wc_get_account_endpoint_url( 'dashboard' ) : '';
		?>
		<div class="<?php echo \esc_attr( implode( ' ', $classnames ) ); ?>" data-labels="<?php echo \esc_attr( htmlspecialchars( \wp_json_encode( $labels ), ENT_QUOTES, 'UTF-8' ) ); ?>">
			<div class="<?php echo \esc_attr( $class( 'wrapper' ) ); ?>">
				<?php if ( ! $is_inline ) : ?>
				<button class="<?php echo \esc_attr( $class( 'close' ) ); ?>" data-close aria-label="<?php \esc_attr_e( 'Close Authentication Form', 'newspack' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
						<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
					</svg>
				</button>
				<?php endif; ?>
				<div class="<?php echo \esc_attr( $class( 'content' ) ); ?>">
					<form method="post" target="_top">
						<input type="hidden" name="<?php echo \esc_attr( self::AUTH_FORM_ACTION ); ?>" value="1" />
						<input type="hidden" name="action" value="pwd" />
						<div class="<?php echo \esc_attr( $class( 'have-account' ) ); ?>">
							<a href="#" data-action="pwd link" data-set-action="register"><?php \esc_html_e( "I don't have an account", 'newspack' ); ?></a>
							<a href="#" data-action="register" data-set-action="pwd"><?php \esc_html_e( 'I already have an account', 'newspack' ); ?></a>
						</div>
						<div class="<?php echo \esc_attr( $class( 'header' ) ); ?>">
							<h2><?php _e( 'Sign In', 'newspack' ); ?></h2>
						</div>
						<p data-has-auth-link>
							<?php _e( "We've recently sent you an authentication link. Please, check your inbox!", 'newspack' ); ?>
						</p>
						<p data-action="pwd">
							<?php
								echo wp_kses_post(
									sprintf(
										// Translators: %s is the link to sign in via magic link instead.
										__( 'Sign in with a password below, or %s.', 'newspack' ),
										'<a href="#" data-set-action="link">' . __( 'sign in using your email', 'newspack' ) . '</a>'
									)
								);
							?>
						</p>
						<p data-action="link">
							<?php
								echo wp_kses_post(
									sprintf(
										// Translators: %s is the link to sign in via password instead.
										__( 'Get a code sent to your email to sign in, or %s.', 'newspack' ),
										'<a href="#" data-set-action="pwd">' . __( 'sign in using a password', 'newspack' ) . '</a>'
									)
								);
							?>
						</p>
						<p data-action="otp">
							<?php
								echo wp_kses_post(
									sprintf(
										// Translators: %s is the link to sign in via password instead.
										__( 'Enter the code you received via email to sign in, or %s.', 'newspack' ),
										'<a href="#" data-set-action="pwd">' . __( 'sign in using a password', 'newspack' ) . '</a>'
									)
								);
							?>
						</p>
						<input type="hidden" name="redirect" value="<?php echo \esc_attr( $redirect ); ?>" />
						<?php if ( isset( $lists ) && ! empty( $lists ) ) : ?>
							<div data-action="register">
								<?php if ( 1 < count( $lists ) ) : ?>
									<p><?php echo \esc_html( $newsletters_label ); ?></p>
								<?php endif; ?>
								<?php
								self::render_subscription_lists_inputs(
									$lists,
									array_keys( $lists ),
									[
										'single_label' => $newsletters_label,
									]
								);
								?>
							</div>
						<?php endif; ?>
						<div class="components-form__field" data-action="pwd link register">
							<input name="npe" type="email" placeholder="<?php \esc_attr_e( 'Enter your email address', 'newspack' ); ?>" />
							<?php self::render_honeypot_field(); ?>
						</div>
						<div class="components-form__field otp-field" data-action="otp">
							<input name="otp_code" type="text" maxlength="<?php echo \esc_attr( Magic_Link::OTP_LENGTH ); ?>" placeholder="<?php \esc_attr_e( '6-digit code', 'newspack' ); ?>" />
						</div>
						<div class="components-form__field" data-action="pwd">
							<input name="password" type="password" placeholder="<?php \esc_attr_e( 'Enter your password', 'newspack' ); ?>" />
						</div>
						<div class="<?php echo \esc_attr( $class( 'response' ) ); ?>">
							<span class="<?php echo \esc_attr( $class( 'response', 'icon' ) ); ?>" data-form-status="400">
								<?php echo self::get_error_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<span class="<?php echo \esc_attr( $class( 'response', 'icon' ) ); ?>" data-form-status="200">
								<?php echo self::get_check_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<div class="<?php echo \esc_attr( $class( 'response', 'content' ) ); ?>">
								<?php if ( ! empty( $message ) ) : ?>
									<p><?php echo \esc_html( $message ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<div class="<?php echo \esc_attr( $class( 'actions' ) ); ?>" data-action="pwd">
							<div class="components-form__submit">
								<button type="submit"><?php \esc_html_e( 'Sign in', 'newspack' ); ?></button>
							</div>
							<div class="components-form__help">
								<p class="small">
									<a href="#" data-set-action="link"><?php \esc_html_e( 'Sign in with your email', 'newspack' ); ?></a>
								</p>
								<p class="small">
									<a href="<?php echo \esc_url( \wp_lostpassword_url() ); ?>"><?php _e( 'Lost your password?', 'newspack' ); ?></a>
								</p>
							</div>
						</div>
						<div class="<?php echo \esc_attr( $class( 'actions' ) ); ?>" data-action="otp">
							<div class="components-form__submit">
								<button type="submit"><?php \esc_html_e( 'Sign in', 'newspack' ); ?></button>
							</div>
							<div class="components-form__help">
								<p class="small">
									<a href="#" data-set-action="link"><?php \esc_html_e( 'Try a different email', 'newspack' ); ?></a>
								</p>
								<p class="small">
									<a href="#" data-set-action="link"><?php _e( 'Send another code', 'newspack' ); ?></a>
								</p>
							</div>
						</div>
						<div class="<?php echo \esc_attr( $class( 'actions' ) ); ?>" data-action="link">
							<div class="components-form__submit">
								<button type="submit"><?php \esc_html_e( 'Send authorization code', 'newspack' ); ?></button>
							</div>
							<div class="components-form__help">
								<p class="small">
									<a href="#" data-set-action="pwd"><?php \esc_html_e( 'Sign in with a password', 'newspack' ); ?></a>
								</p>
							</div>
						</div>
						<div class="<?php echo \esc_attr( $class( 'actions' ) ); ?>" data-action="register">
							<div class="components-form__submit">
								<button type="submit"><?php \esc_html_e( 'Sign up', 'newspack' ); ?></button>
							</div>
						</div>
						<?php self::render_third_party_auth(); ?>
						<?php if ( ! empty( $terms_text ) ) : ?>
							<p class="<?php echo \esc_attr( $class( 'terms-text' ) ); ?>">
								<?php if ( ! empty( $terms_url ) ) : ?>
									<a href="<?php echo \esc_url( $terms_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php endif; ?>
								<?php echo \esc_html( $terms_text ); ?>
								<?php if ( ! empty( $terms_url ) ) : ?>
									</a>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Send the auth form response to the client, whether it's a JSON or POST request.
	 *
	 * @param array|WP_Error $data         The response to send to the client.
	 * @param string         $message      Optional custom message.
	 * @param string         $redirect_url Optional custom redirect URL.
	 */
	private static function send_auth_form_response( $data = [], $message = false, $redirect_url = false ) {
		$is_error = \is_wp_error( $data );
		if ( empty( $message ) ) {
			$message = $is_error ? $data->get_error_message() : __( 'Login successful!', 'newspack' );
		}
		if ( \wp_is_json_request() ) {
			\wp_send_json( compact( 'message', 'data' ), \is_wp_error( $data ) ? 400 : 200 );
			exit;
		} elseif ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			\wp_safe_redirect(
				\add_query_arg(
					[
						'reader_authenticated' => $is_error ? '0' : '1',
						'message'              => $message,
					],
					$redirect_url
				)
			);
			exit;
		}
	}

	/**
	 * Render newsletter subscription lists' form input.
	 *
	 * @param array[] $lists   {
	 *   List config keyed by their ID.
	 *
	 *   @type string $title       List title.
	 *   @type string $description List description.
	 * }
	 * @param array   $checked List IDs to pre-select.
	 * @param array   $config  {
	 *   Configuration options.
	 *
	 *   @type string  $title            Optional title to display above the list.
	 *   @type string  $name             Name of the input. Default is lists.
	 *   @type string  $single_label     Label for the input when only one list is present. Default is "Subscribe to our newsletter".
	 *   @type boolean $show_description Whether to display the list description. Default is true.
	 * }
	 */
	public static function render_subscription_lists_inputs( $lists = [], $checked = [], $config = [] ) {
		$config = \wp_parse_args(
			$config,
			[
				'title'            => '',
				'name'             => 'lists',
				'single_label'     => __( 'Subscribe to our newsletter', 'newspack' ),
				'show_description' => true,
			]
		);

		if ( empty( $lists ) && method_exists( 'Newspack_Newsletters_Subscription', 'get_lists_config' ) ) {
			$lists = \Newspack_Newsletters_Subscription::get_lists_config();
		}

		if ( empty( $lists ) || is_wp_error( $lists ) ) {
			return;
		}

		$id = \wp_rand( 0, 99999 );

		$class = function( ...$parts ) {
			array_unshift( $parts, 'lists' );
			return self::get_element_class_name( $parts );
		};

		$checked_map = array_flip( $checked );
		?>
		<div class="<?php echo \esc_attr( $class() ); ?>">
			<?php if ( 1 < count( $lists ) && ! empty( $config['title'] ) ) : ?>
				<h3><?php echo \esc_html( $config['title'] ); ?></h3>
			<?php endif; ?>
			<ul>
				<?php
				foreach ( $lists as $list_id => $list ) :
					$checkbox_id = sprintf( 'newspack-%s-list-checkbox-%s', $id, $list_id );
					?>
					<li>
						<span class="<?php echo \esc_attr( $class( 'checkbox' ) ); ?>">
							<input
								type="checkbox"
								name="<?php echo \esc_attr( $config['name'] ); ?>[]"
								value="<?php echo \esc_attr( $list_id ); ?>"
								id="<?php echo \esc_attr( $checkbox_id ); ?>"
								<?php if ( isset( $checked_map[ $list_id ] ) ) : ?>
									checked
								<?php endif; ?>
							/>
						</span>
						<span class="<?php echo \esc_attr( $class( 'details' ) ); ?>">
							<label class="<?php echo \esc_attr( $class( 'label' ) ); ?>" for="<?php echo \esc_attr( $checkbox_id ); ?>">
								<span class="<?php echo \esc_attr( $class( 'title' ) ); ?>">
									<?php
									if ( 1 === count( $lists ) ) {
										echo \wp_kses_post( $config['single_label'] );
									} else {
										echo \esc_html( $list['title'] );
									}
									?>
								</span>
								<?php if ( $config['show_description'] ) : ?>
									<span class="<?php echo \esc_attr( $class( 'description' ) ); ?>"><?php echo \esc_html( $list['description'] ); ?></span>
								<?php endif; ?>
							</label>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render third party auth buttons for an authentication form.
	 */
	public static function render_third_party_auth() {
		if ( ! Google_OAuth::is_oauth_configured() ) {
			return;
		}
		$class      = function( ...$parts ) {
			array_unshift( $parts, 'logins' );
			return self::get_element_class_name( $parts );
		};
		$classnames = implode( ' ', [ $class(), $class() . '--disabled' ] );
		?>
		<div class="<?php echo \esc_attr( $classnames ); ?>">
			<div class="<?php echo \esc_attr( $class( 'separator' ) ); ?>">
				<div></div>
				<div>
					<?php echo \esc_html__( 'OR', 'newspack' ); ?>
				</div>
				<div></div>
			</div>
			<button type="button" class="<?php echo \esc_attr( $class( 'google' ) ); ?>">
				<?php echo file_get_contents( dirname( NEWSPACK_PLUGIN_FILE ) . '/assets/blocks/reader-registration/icons/google.svg' ); // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span>
					<?php echo \esc_html__( 'Sign in with Google', 'newspack' ); ?>
				</span>
			</button>
		</div>
		<?php
	}

	/**
	 * If rendering the WooCommerce login form template, trick it into rendering nothing
	 * and replace it with our own login form.
	 *
	 * @param string $template Full template path.
	 * @param string $template_name Template name.
	 *
	 * @return string Filtered template path.
	 */
	public static function replace_woocommerce_auth_form( $template, $template_name ) {
		if ( 'myaccount/form-login.php' === $template_name ) {
			$template = dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/reader-activation/login-form.php';
		}

		return $template;
	}

	/**
	 * Process reader authentication form.
	 */
	public static function process_auth_form() {
		if ( \is_user_logged_in() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// Nonce not required for an authentication attempt.
		if ( ! isset( $_POST[ self::AUTH_FORM_ACTION ] ) ) {
			return;
		}
		$action        = isset( $_POST['action'] ) ? \sanitize_text_field( $_POST['action'] ) : '';
		$email         = isset( $_POST['npe'] ) ? \sanitize_email( $_POST['npe'] ) : '';
		$password      = isset( $_POST['password'] ) ? \sanitize_text_field( $_POST['password'] ) : '';
		$redirect      = isset( $_POST['redirect'] ) ? \esc_url_raw( $_POST['redirect'] ) : '';
		$lists         = isset( $_POST['lists'] ) ? array_map( 'sanitize_text_field', $_POST['lists'] ) : [];
		$honeypot      = isset( $_POST['email'] ) ? \sanitize_text_field( $_POST['email'] ) : '';
		$captcha_token = isset( $_POST['captcha_token'] ) ? \sanitize_text_field( $_POST['captcha_token'] ) : '';
		// phpcs:enable

		// Honeypot trap.
		if ( ! empty( $honeypot ) ) {
			return self::send_auth_form_response(
				[
					'email'         => $honeypot,
					'authenticated' => 1,
				]
			);
		}

		// reCAPTCHA test.
		if ( Recaptcha::can_use_captcha() ) {
			$captcha_result = Recaptcha::verify_captcha( $captcha_token );
			if ( \is_wp_error( $captcha_result ) ) {
				return self::send_auth_form_response( $captcha_result );
			}
		}

		if ( ! in_array( $action, self::AUTH_FORM_OPTIONS, true ) ) {
			return self::send_auth_form_response( new \WP_Error( 'invalid_request', __( 'Invalid request.', 'newspack' ) ) );
		}

		if ( $redirect && false === strpos( $redirect, home_url(), 0 ) ) {
			return self::send_auth_form_response( new \WP_Error( 'invalid_request', __( 'Invalid request.', 'newspack' ) ) );
		}

		if ( empty( $email ) ) {
			return self::send_auth_form_response( new \WP_Error( 'invalid_email', __( 'You must enter a valid email address.', 'newspack' ) ) );
		}

		self::set_auth_intention_cookie( $email );

		$user = \get_user_by( 'email', $email );
		if ( ( ! $user && 'register' !== $action ) || ( $user && ! self::is_user_reader( $user ) ) ) {
			return self::send_auth_form_response( new \WP_Error( 'unauthorized', __( "We couldn't find a reader account registered to this email address. Please confirm that you entered the correct email, or sign up for a new account.", 'newspack' ) ) );
		}

		$payload = [
			'email'         => $email,
			'authenticated' => 0,
		];

		switch ( $action ) {
			case 'pwd':
				if ( empty( $password ) ) {
					return self::send_auth_form_response( new \WP_Error( 'invalid_password', __( 'You must enter a valid password.', 'newspack' ) ) );
				}
				$user = \wp_authenticate( $user->user_login, $password );
				if ( \is_wp_error( $user ) ) {
					return self::send_auth_form_response( new \WP_Error( 'unauthorized', __( 'Invalid credentials.', 'newspack' ) ) );
				}
				$authenticated            = self::set_current_reader( $user->ID );
				$payload['authenticated'] = \is_wp_error( $authenticated ) ? 0 : 1;
				return self::send_auth_form_response( $payload, false, $redirect );
			case 'link':
				$sent = Magic_Link::send_email( $user );
				if ( true !== $sent ) {
					return self::send_auth_form_response( new \WP_Error( 'unauthorized', __( 'We encountered an error sending an authentication link. Please try again.', 'newspack' ) ) );
				}
				return self::send_auth_form_response( $payload, __( 'Please check your inbox for an authentication link.', 'newspack' ), $redirect );
			case 'register':
				$metadata = [];
				if ( ! empty( $lists ) ) {
					$metadata['lists'] = $lists;
				}
				$user_id = self::register_reader( $email, '', true, $metadata );
				if ( false === $user_id ) {
					return self::send_auth_form_response( $payload, __( 'An account was already registered with this email. Please check your inbox for an authentication link.', 'newspack' ), $redirect );
				}
				if ( \is_wp_error( $user_id ) ) {
					return self::send_auth_form_response(
						new \WP_Error( 'unauthorized', __( 'Unable to register your account. Try a different email.', 'newspack' ) )
					);
				}
				$payload['authenticated'] = \absint( $user_id ) ? 1 : 0;
				return self::send_auth_form_response( $payload, false, $redirect );
		}
	}


	/**
	 * Check if current reader has its email verified.
	 *
	 * @param \WP_User $user User object.
	 *
	 * @return bool|null Whether the email address is verified, null if invalid user.
	 */
	public static function is_reader_verified( $user ) {
		if ( ! $user ) {
			return null;
		}

		/** Should not verify email if user is not a reader. */
		if ( ! self::is_user_reader( $user ) ) {
			return null;
		}

		return (bool) \get_user_meta( $user->ID, self::EMAIL_VERIFIED, true );
	}

	/**
	 * Authenticate a reader session given its user ID.
	 *
	 * Warning: this method will only verify if the user is a reader in order to
	 * authenticate. It will not check for any credentials.
	 *
	 * @param \WP_User|int $user_or_user_id User object.
	 *
	 * @return \WP_User|\WP_Error The authenticated reader or WP_Error if authentication failed.
	 */
	public static function set_current_reader( $user_or_user_id ) {
		if ( $user_or_user_id instanceof \WP_User ) {
			$user = $user_or_user_id;
		} elseif ( absint( $user_or_user_id ) ) {
			$user = get_user_by( 'id', $user_or_user_id );
		}

		if ( ! $user || \is_wp_error( $user ) || ! self::is_user_reader( $user ) ) {
			return new \WP_Error( 'newspack_authenticate_invalid_user', __( 'Invalid user.', 'newspack' ) );
		}

		$user_id = \absint( $user->ID );

		\wp_clear_auth_cookie();
		\wp_set_current_user( $user->ID );
		\wp_set_auth_cookie( $user->ID, true );
		self::set_auth_reader_cookie( $user );
		\do_action( 'wp_login', $user->user_login, $user );
		Logger::log( 'Logged in user ' . $user->ID );

		return $user;
	}

	/**
	 * Register a reader given its email.
	 *
	 * Due to authentication or auth intention, this method should be used
	 * preferably on POST or API requests to avoid issues with caching.
	 *
	 * @param string $email        Email address.
	 * @param string $display_name Reader display name to be used on account creation.
	 * @param bool   $authenticate Whether to authenticate after registering. Default to true.
	 * @param array  $metadata     Any metadata to pass along to the action hook.
	 *
	 * @return int|false|\WP_Error The created user ID in case of registration, false if the user already exists, or a WP_Error object.
	 */
	public static function register_reader( $email, $display_name = '', $authenticate = true, $metadata = [] ) {
		if ( ! self::is_enabled() ) {
			return new \WP_Error( 'newspack_register_reader_disabled', __( 'Registration is disabled.', 'newspack' ) );
		}

		if ( \is_user_logged_in() ) {
			return new \WP_Error( 'newspack_register_reader_logged_in', __( 'Cannot register while logged in.', 'newspack' ) );
		}

		$email = \sanitize_email( $email );

		if ( empty( $email ) ) {
			return new \WP_Error( 'newspack_register_reader_empty_email', __( 'Please enter a valid email address.', 'newspack' ) );
		}

		self::set_auth_intention_cookie( $email );

		$existing_user = \get_user_by( 'email', $email );
		if ( \is_wp_error( $existing_user ) ) {
			return $existing_user;
		}

		$user_id = false;

		if ( $existing_user ) {
			Logger::log( "User with $email already exists. Sending magic link." );
			Magic_Link::send_email( $existing_user );
		} else {
			/**
			 * Create new reader.
			 */
			if ( empty( $display_name ) ) {
				$display_name = explode( '@', $email, 2 )[0];
			}

			$user_login = \sanitize_user( $email, true );

			$random_password = \wp_generate_password();

			if ( function_exists( '\wc_create_new_customer' ) ) {
				/**
				 * Create WooCommerce Customer if possible.
				 *
				 * Email notification for WooCommerce is handled by the plugin.
				 */
				$user_id = \wc_create_new_customer( $email, $user_login, $random_password, [ 'display_name' => $display_name ] );
			} else {
				$user_id = \wp_insert_user(
					[
						'user_login'   => $user_login,
						'user_email'   => $email,
						'user_pass'    => $random_password,
						'display_name' => $display_name,
					]
				);
				\wp_new_user_notification( $user_id, null, 'user' );
			}

			if ( \is_wp_error( $user_id ) ) {
				Logger::error( 'User registration failed: ' . $user_id->get_error_message() );
				return $user_id;
			}

			/**
			 * Add default reader related meta.
			 */
			\update_user_meta( $user_id, self::READER, true );
			/** Email is not yet verified. */
			\update_user_meta( $user_id, self::EMAIL_VERIFIED, false );
			/** User hasn't set their own password yet. */
			\update_user_meta( $user_id, self::WITHOUT_PASSWORD, true );

			Logger::log( 'Created new reader user with ID ' . $user_id );

			if ( $authenticate ) {
				self::$is_new_reader_auth = true;
				self::set_current_reader( $user_id );
			}
		}

		// Note the user's login method for later use.
		if ( isset( $metadata['registration_method'] ) ) {
			\update_user_meta( $user_id, self::REGISTRATION_METHOD, $metadata['registration_method'] );
			if ( in_array( $metadata['registration_method'], self::SSO_REGISTRATION_METHODS, true ) ) {
				self::set_reader_verified( $user_id );
			}
		}

		/**
		 * Action after registering and authenticating a reader.
		 *
		 * @param string         $email         Email address.
		 * @param bool           $authenticate  Whether to authenticate after registering.
		 * @param false|int      $user_id       The created user id.
		 * @param false|\WP_User $existing_user The existing user object.
		 * @param array          $metadata      Metadata.
		 */
		\do_action( 'newspack_registered_reader', $email, $authenticate, $user_id, $existing_user, $metadata );

		return $user_id;
	}

	/**
	 * Whether the current reader is rate limited.
	 *
	 * @param \WP_User $user WP_User object to be verified.
	 *
	 * @return bool
	 */
	public static function is_reader_email_rate_limited( $user ) {
		if ( self::is_reader_verified( $user ) ) {
			return false;
		}
		$last_email = get_user_meta( $user->ID, self::LAST_EMAIL_DATE, true );
		return $last_email && self::EMAIL_INTERVAL > time() - $last_email;
	}

	/**
	 * Send a magic link with special messaging to verify the user.
	 *
	 * @param WP_User $user WP_User object to be verified.
	 */
	public static function send_verification_email( $user ) {
		$redirect_to = function_exists( '\wc_get_account_endpoint_url' ) ? \wc_get_account_endpoint_url( 'dashboard' ) : '';

		/** Rate limit control */
		if ( self::is_reader_email_rate_limited( $user ) ) {
			return new \WP_Error( 'newspack_verification_email_interval', __( 'Please wait before requesting another verification email.', 'newspack' ) );
		}
		\update_user_meta( $user->ID, self::LAST_EMAIL_DATE, time() );

		return Emails::send_email(
			Reader_Activation_Emails::EMAIL_TYPES['VERIFICATION'],
			$user->user_email,
			[
				[
					'template' => '*VERIFICATION_URL*',
					'value'    => Magic_Link::generate_url( $user, $redirect_to ),
				],
			]
		);
	}

	/**
	 * Get value of the client ID bearing cookie.
	 */
	public static function get_client_id() {
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		return isset( $_COOKIE[ NEWSPACK_CLIENT_ID_COOKIE_NAME ] ) ? sanitize_text_field( $_COOKIE[ NEWSPACK_CLIENT_ID_COOKIE_NAME ] ) : false;
	}

	/**
	 * Disable the standard WooCommerce "new customer welcome" email.
	 *
	 * @param array $emails Types of transactional emails sent by WooCommerce.
	 *
	 * @return array Filtered array of transactional email types.
	 */
	public static function disable_woocommerce_new_user_email( $emails ) {
		$emails = array_values(
			array_filter(
				$emails,
				function( $type ) {
					return 'woocommerce_created_customer' !== $type;
				}
			)
		);

		return $emails;
	}


	/**
	 * Filters args sent to wp_mail when a password change email is sent.
	 *
	 * @param array   $defaults {
	 *       The default notification email arguments. Used to build wp_mail().
	 *
	 *     @type string $to      The intended recipient - user email address.
	 *     @type string $subject The subject of the email.
	 *     @type string $message The body of the email.
	 *     @type string $headers The headers of the email.
	 * }
	 * @param string  $key        The activation key.
	 * @param string  $user_login The username for the user.
	 * @param WP_User $user       WP_User object.
	 *
	 * @return array The filtered $defaults.
	 */
	public static function password_reset_configuration( $defaults, $key, $user_login, $user ) {
		$config_name  = Reader_Activation_Emails::EMAIL_TYPES['RESET_PASSWORD'];
		$email_config = Emails::get_email_config_by_type( $config_name );

		$defaults['headers'] = sprintf(
			'From: %1$s <%2$s>',
			$email_config['from_name'],
			$email_config['from_email']
		);
		$defaults['subject'] = $email_config['subject'];
		$defaults['message'] = Emails::get_email_payload(
			$config_name,
			[
				[
					'template' => '*PASSWORD_RESET_LINK*',
					'value'    => Emails::get_password_reset_url( $user, $key ),
				],
			]
		);

		return $defaults;
	}

	/**
	 * Set email content type when a password reset email is about to be sent.
	 */
	public static function set_password_reset_mail_content_type() {
		$email_content_type = function() {
			return 'text/html';
		};
		add_filter( 'wp_mail_content_type', $email_content_type );
	}

	/**
	 * Rate limit password reset.
	 *
	 * @param \WP_Error      $errors    A WP_Error object containing any errors generated
	 *                                  by using invalid credentials.
	 * @param \WP_User|false $user_data WP_User object if found, false if the user does not exist.
	 *
	 * @return \WP_Error
	 */
	public static function rate_limit_lost_password( $errors, $user_data ) {
		if ( $user_data && self::is_reader_email_rate_limited( $user_data ) ) {
			$errors->add( 'newspack_password_reset_interval', __( 'Please wait a moment before requesting another password reset email.', 'newspack' ) );
		} else {
			\update_user_meta( $user_data->ID, self::LAST_EMAIL_DATE, time() );
		}
		return $errors;
	}

	/**
	 * If a reader tries to make a recurring donation with an email address that
	 * has been previously registered, automatically associate the transaction with the user.
	 *
	 * @param int $customer_id Current customer ID.
	 * @return int Modified $customer_id
	 */
	public static function associate_existing_woo_users_with_transactions_on_checkout( $customer_id ) {
		$billing_email = filter_input( INPUT_POST, 'billing_email', FILTER_SANITIZE_EMAIL );
		if ( $billing_email ) {
			$customer = \get_user_by( 'email', $billing_email );
			if ( $customer ) {
				$customer_id = $customer->ID;
			}
		}
		return $customer_id;
	}

	/**
	 * Don't force account registration/login on Woo purchases for existing users.
	 *
	 * @param array $data Array of Woo checkout data.
	 * @return array Modified $data.
	 */
	public static function dont_force_registration_for_existing_woo_users( $data ) {
		$email    = $data['billing_email'];
		$customer = \get_user_by( 'email', $email );
		if ( $customer ) {
			$data['createaccount'] = 0;
			\add_filter( 'woocommerce_checkout_registration_required', '__return_false', 9999 );
		}

		return $data;
	}
}
Reader_Activation::init();
