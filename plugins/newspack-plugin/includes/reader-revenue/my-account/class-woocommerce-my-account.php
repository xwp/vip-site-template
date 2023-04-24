<?php
/**
 * Connection with WooCommerce's features.
 *
 * @package Newspack
 */

namespace Newspack;

use Newspack\Reader_Activation;
use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Connection with WooCommerce's "My Account" page.
 */
class WooCommerce_My_Account {
	const BILLING_ENDPOINT             = 'billing';
	const STRIPE_CUSTOMER_ID_USER_META = '_newspack_stripe_customer_id';
	const RESET_PASSWORD_URL_PARAM     = 'reset-password';
	const DELETE_ACCOUNT_URL_PARAM     = 'delete-account';
	const DELETE_ACCOUNT_FORM          = 'delete-account-form';
	const SEND_MAGIC_LINK_PARAM        = 'magic-link';
	const AFTER_ACCOUNT_DELETION_PARAM = 'account-deleted';

	/**
	 * Cached Stripe customer ID of the current user.
	 *
	 * @var string
	 */
	private static $stripe_customer_id = null;

	/**
	 * Initialize.
	 *
	 * @codeCoverageIgnore
	 */
	public static function init() {
		\add_action( 'init', [ __CLASS__, 'add_rewrite_endpoints' ] );
		\add_action( 'woocommerce_account_' . self::BILLING_ENDPOINT . '_endpoint', [ __CLASS__, 'render_billing_template' ] );
		\add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'my_account_menu_items' ], 1000 );

		// Reader Activation mods.
		if ( Reader_Activation::is_enabled() ) {
			\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
			\add_filter( 'wc_get_template', [ __CLASS__, 'wc_get_template' ], 10, 5 );
			\add_action( 'template_redirect', [ __CLASS__, 'handle_password_reset_request' ] );
			\add_action( 'template_redirect', [ __CLASS__, 'handle_delete_account_request' ] );
			\add_action( 'template_redirect', [ __CLASS__, 'handle_delete_account' ] );
			\add_action( 'template_redirect', [ __CLASS__, 'handle_magic_link_request' ] );
			\add_action( 'template_redirect', [ __CLASS__, 'redirect_to_account_details' ] );
			\add_action( 'template_redirect', [ __CLASS__, 'edit_account_prevent_email_update' ] );
			\add_action( 'init', [ __CLASS__, 'restrict_account_content' ], 100 );
			\add_filter( 'woocommerce_save_account_details_required_fields', [ __CLASS__, 'remove_required_fields' ] );
			\add_action( 'logout_redirect', [ __CLASS__, 'add_param_after_logout' ] );
			\add_action( 'template_redirect', [ __CLASS__, 'show_message_after_logout' ] );
		}
	}

	/**
	 * Enqueue front-end scripts.
	 */
	public static function enqueue_scripts() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			\wp_enqueue_style(
				'my-account',
				\Newspack\Newspack::plugin_url() . '/dist/my-account.css',
				[],
				NEWSPACK_PLUGIN_VERSION
			);
		}
	}

	/**
	 * Filter "My Account" items, if Stripe is the donations platform.
	 *
	 * @param array $items Items.
	 */
	public static function my_account_menu_items( $items ) {
		// If the user has a Stripe customer ID, they're a Stripe customer (regardless of the donations platform).
		// Add a nav item for Stripe's billing portal.
		$stripe_customer_id = self::get_current_user_stripe_id();
		if ( false !== $stripe_customer_id ) {
			$custom_endpoints = [ self::BILLING_ENDPOINT => __( 'Billing', 'newspack' ) ];
			$items            = array_slice( $items, 0, 1, true ) + $custom_endpoints + array_slice( $items, 1, null, true );
		}

		$default_disabled_items = [];

		// Rename 'Logout' action to 'Log out', for grammatical reasons.
		if ( isset( $items['customer-logout'] ) ) {
			$items['customer-logout'] = __( 'Log out', 'newspack' );
		}

		if ( Reader_Activation::is_enabled() ) {
			// If the reader hasn't verified their account, only show options to verify or log out.
			if ( ! self::is_user_verified() ) {
				$minimum_items = [ 'edit-account', 'customer-logout' ];
				foreach ( $items as $key => $label ) {
					if ( ! in_array( $key, $minimum_items, true ) ) {
						unset( $items[ $key ] );
					}
				}
				return $items;
			}

			$default_disabled_items = array_merge( $default_disabled_items, [ 'dashboard', 'members-area', 'edit-address' ] );
			$customer_id            = \get_current_user_id();
			if ( function_exists( 'wcs_user_has_subscription' ) && function_exists( 'wcs_get_subscriptions' ) ) {
				$user_subscriptions             = wcs_get_subscriptions( [ 'customer_id' => $customer_id ] );
				$has_non_newspack_subscriptions = false;
				foreach ( $user_subscriptions as $subscription ) {
					if ( ! $subscription->get_meta( WooCommerce_Connection::SUBSCRIPTION_STRIPE_ID_META_KEY ) ) {
						$has_non_newspack_subscriptions = true;
						break;
					}
				}
				// Unless user has any subscriptions that aren't tied to a Stripe subscription by Newspack, hide the subscriptions link.
				// The Stripe-tied subscriptions will be available for management in the "Billing" section.
				if ( ! $has_non_newspack_subscriptions ) {
					$default_disabled_items[] = 'subscriptions';
				}
			}
			if ( function_exists( 'wc_get_orders' ) ) {
				$wc_non_newspack_orders = array_filter(
					\wc_get_orders( [ 'customer' => $customer_id ] ),
					function ( $order ) {
						return WooCommerce_Connection::CREATED_VIA_NAME !== $order->get_created_via();
					}
				);
				if ( empty( $wc_non_newspack_orders ) ) {
					$default_disabled_items = array_merge( $default_disabled_items, [ 'orders', 'payment-methods' ] );
				}
			}
			if ( function_exists( 'wc_get_customer_available_downloads' ) ) {
				$wc_customer_downloads = \wc_get_customer_available_downloads( $customer_id );

				if ( empty( $wc_customer_downloads ) ) {
					$default_disabled_items[] = 'downloads';
				}
			}
			$disabled_wc_menu_items = \apply_filters( 'newspack_my_account_disabled_pages', $default_disabled_items );
			foreach ( $disabled_wc_menu_items as $key ) {
				if ( isset( $items[ $key ] ) ) {
					unset( $items[ $key ] );
				}
			}
		}

		return $items;
	}

	/**
	 * Handle password reset request.
	 */
	public static function handle_password_reset_request() {
		if ( ! \is_user_logged_in() ) {
			return;
		}

		$nonce = filter_input( INPUT_GET, self::RESET_PASSWORD_URL_PARAM, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! $nonce ) {
			return;
		}

		$is_error = false;
		if ( \wp_verify_nonce( $nonce, self::RESET_PASSWORD_URL_PARAM ) ) {
			$result  = \retrieve_password( \wp_get_current_user()->user_email );
			$message = __( 'Please check your email inbox for instructions on how to set a new password.', 'newspack' );
			if ( \is_wp_error( $result ) ) {
				Logger::error( 'Error resetting password: ' . $result->get_error_message() );
				$message  = $result->get_error_message();
				$is_error = true;
			}
		} else {
			$message  = __( 'Something went wrong.', 'newspack' );
			$is_error = true;
		}

		\wp_safe_redirect(
			\add_query_arg(
				[
					'message'  => $message,
					'is_error' => $is_error,
				],
				\remove_query_arg( self::RESET_PASSWORD_URL_PARAM )
			)
		);
		exit;
	}

	/**
	 * Handle delete account request.
	 */
	public static function handle_delete_account_request() {
		if ( ! \is_user_logged_in() ) {
			return;
		}

		$user_id = \get_current_user_id();
		$user    = \wp_get_current_user();
		if ( ! Reader_Activation::is_user_reader( $user ) ) {
			return;
		}

		$nonce = filter_input( INPUT_GET, self::DELETE_ACCOUNT_URL_PARAM, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! $nonce || ! \wp_verify_nonce( $nonce, self::DELETE_ACCOUNT_URL_PARAM ) ) {
			return;
		}

		$token      = \wp_generate_password( 43, false, false );
		$form_nonce = \wp_create_nonce( self::DELETE_ACCOUNT_FORM );

		$url = \add_query_arg(
			[
				self::DELETE_ACCOUNT_FORM => $form_nonce,
				'token'                   => $token,
			],
			\wc_get_account_endpoint_url( 'edit-account' )
		);
		\set_transient( 'np_reader_account_delete_' . $user_id, $token, DAY_IN_SECONDS );

		$sent = Emails::send_email(
			Reader_Activation_Emails::EMAIL_TYPES['DELETE_ACCOUNT'],
			$user->user_email,
			[
				[
					'template' => '*DELETION_LINK*',
					'value'    => $url,
				],
			]
		);

		\wp_safe_redirect(
			\add_query_arg(
				[
					'message'  => $sent ? __( 'Please check your email inbox for instructions on how to delete your account.', 'newspack' ) : __( 'Something went wrong.', 'newspack' ),
					'is_error' => ! $sent,
				],
				\remove_query_arg( self::DELETE_ACCOUNT_URL_PARAM )
			)
		);
		exit;
	}

	/**
	 * Handle delete account confirmation.
	 */
	public static function handle_delete_account() {

		/** Make sure `wp_delete_user()` is available. */
		require_once ABSPATH . 'wp-admin/includes/user.php';

		if ( ! isset( $_POST[ self::DELETE_ACCOUNT_FORM ] ) ) {
			return;
		}

		$form_nonce = \sanitize_text_field( $_POST[ self::DELETE_ACCOUNT_FORM ] );
		if ( ! $form_nonce || ! \wp_verify_nonce( $form_nonce, self::DELETE_ACCOUNT_FORM ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'newspack' ) );
		}

		if ( ! isset( $_POST['confirm_delete'] ) ) {
			return;
		}

		if ( ! \is_user_logged_in() ) {
			return;
		}

		$user_id = \get_current_user_id();
		$user    = \wp_get_current_user();
		if ( ! Reader_Activation::is_user_reader( $user ) ) {
			return;
		}

		$token           = isset( $_POST['token'] ) ? \sanitize_text_field( $_POST['token'] ) : '';
		$transient_token = \get_transient( 'np_reader_account_delete_' . $user_id );
		if ( ! $token || ! $transient_token || $token !== $transient_token ) {
			\wp_die( \esc_html__( 'Invalid request.', 'newspack' ) );
		}
		\delete_transient( 'np_reader_account_delete_' . $user_id );

		\wp_delete_user( $user_id );
		\wp_safe_redirect( add_query_arg( self::AFTER_ACCOUNT_DELETION_PARAM, 1, \wc_get_account_endpoint_url( 'edit-account' ) ) );
		exit;
	}

	/**
	 * Handle magic link request.
	 */
	public static function handle_magic_link_request() {
		if ( ! \is_user_logged_in() ) {
			return;
		}
		$nonce = filter_input( INPUT_GET, self::SEND_MAGIC_LINK_PARAM, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( $nonce ) {
			$is_error = false;
			if ( \wp_verify_nonce( $nonce, self::SEND_MAGIC_LINK_PARAM ) ) {
				$result  = Reader_Activation::send_verification_email( \wp_get_current_user() );
				$message = __( 'Please check your email inbox for a link to verify your account.', 'newspack' );
				if ( \is_wp_error( $result ) ) {
					Logger::error( 'Error sending verification email: ' . $result->get_error_message() );
					$message  = $result->get_error_message();
					$is_error = true;
				}
			} else {
				$message  = __( 'Something went wrong.', 'newspack' );
				$is_error = true;
			}
			wp_safe_redirect(
				\add_query_arg(
					[
						'message'  => $message,
						'is_error' => $is_error,
					],
					\remove_query_arg( self::SEND_MAGIC_LINK_PARAM )
				)
			);
			exit;
		}
	}

	/**
	 * Check if the user is logged in and verified.
	 */
	public static function is_user_verified() {
		// Don't lock access if Reader Activation features aren't enabled.
		if ( ! Reader_Activation::is_enabled() ) {
			return true;
		}
		// Don't lock access if the user is not a reader.
		if ( \is_user_logged_in() && ! Reader_Activation::is_user_reader( \wp_get_current_user(), true ) ) {
			return true;
		}

		return \is_user_logged_in() && Reader_Activation::is_reader_verified( \wp_get_current_user() );
	}

	/**
	 * Redirect to "Account details" if accessing "My Account" directly.
	 * Do not redirect if the request is a resubscribe request, as resubscribe
	 * requests do their own redirect to the cart/checkout page.
	 */
	public static function redirect_to_account_details() {
		$resubscribe_request = isset( $_REQUEST['resubscribe'] ) ? 'shop_subscription' === get_post_type( absint( $_REQUEST['resubscribe'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( \is_user_logged_in() && Reader_Activation::is_enabled() && function_exists( 'wc_get_page_permalink' ) && ! $resubscribe_request ) {
			global $wp;
			$current_url               = \home_url( $wp->request );
			$my_account_page_permalink = \wc_get_page_permalink( 'myaccount' );
			$logout_url                = \wc_get_account_endpoint_url( 'customer-logout' );
			if ( \trailingslashit( $current_url ) === \trailingslashit( $my_account_page_permalink ) ) {
				\wp_safe_redirect( \wc_get_account_endpoint_url( 'edit-account' ) );
				exit;
			}
		}
	}

	/**
	 * Get current user's Stripe customer ID.
	 */
	private static function get_current_user_stripe_id() {
		if ( self::$stripe_customer_id ) {
			return self::$stripe_customer_id;
		}
		$user_id               = \get_current_user_id();
		$user_meta_customer_id = \get_user_meta( $user_id, Stripe_Connection::STRIPE_CUSTOMER_ID_USER_META, true );
		if ( $user_meta_customer_id ) {
			self::$stripe_customer_id = $user_meta_customer_id;
			return $user_meta_customer_id;
		}
		$customer_orders     = \wc_get_orders(
			[
				'customer_id' => $user_id,
				'created_via' => WooCommerce_Connection::CREATED_VIA_NAME,
				'limit'       => -1,
			]
		);
		$stripe_customer_ids = [];
		foreach ( $customer_orders as $order ) {
			$stripe_customer_id = $order->get_meta( '_stripe_customer_id' );
			if ( $stripe_customer_id ) {
				$stripe_customer_ids[] = $stripe_customer_id;
			}
		}
		array_unique( $stripe_customer_ids );
		if ( empty( $stripe_customer_ids ) ) {
			self::$stripe_customer_id = false;
		} else {
			self::$stripe_customer_id = $stripe_customer_ids[0];
			\update_user_meta( $user_id, Stripe_Connection::STRIPE_CUSTOMER_ID_USER_META, self::$stripe_customer_id );
		}
		return self::$stripe_customer_id;
	}

	/**
	 * Render custom "My Account" page.
	 */
	public static function render_billing_template() {
		$stripe_customer_id        = self::get_current_user_stripe_id();
		$stripe_billing_portal_url = Stripe_Connection::get_billing_portal_url( $stripe_customer_id );
		$error_message             = false;
		if ( \is_wp_error( $stripe_billing_portal_url ) ) {
			$error_message = $stripe_billing_portal_url->get_error_message();
			Logger::error( 'Error getting Stripe billing portal URL: ' . \wp_json_encode( $stripe_billing_portal_url ) );
		}

		include dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/reader-revenue/templates/myaccount-billing.php';
	}

	/**
	 * Add the necessary endpoints to rewrite rules.
	 */
	public static function add_rewrite_endpoints() {
		\add_rewrite_endpoint( self::BILLING_ENDPOINT, EP_PAGES );
		if ( ! \get_option( '_newspack_has_set_up_custom_billing_endpoint' ) ) {
			\flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
			Logger::log( 'Flushed rewrite rules to add billing endpoint' );
			\update_option( '_newspack_has_set_up_custom_billing_endpoint', true );
		}
	}

	/**
	 * Remove WC's required fields.
	 *
	 * @param array $required_fields Required fields.
	 */
	public static function remove_required_fields( $required_fields ) {
		if ( ! Donations::is_platform_stripe() ) {
			return $required_fields;
		}
		return [];
	}

	/**
	 * WC's page templates hijacking.
	 *
	 * @param string $template      Template path.
	 * @param string $template_name Template name.
	 */
	public static function wc_get_template( $template, $template_name ) {
		switch ( $template_name ) {
			case 'myaccount/form-login.php':
				if ( isset( $_GET[ self::AFTER_ACCOUNT_DELETION_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/reader-revenue/templates/myaccount-after-delete-account.php';
				}
				return $template;
			case 'myaccount/form-edit-account.php':
				if ( isset( $_GET[ self::DELETE_ACCOUNT_FORM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/reader-revenue/templates/myaccount-delete-account.php';
				}
				return dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/reader-revenue/templates/myaccount-edit-account.php';
			default:
				return $template;
		}
	}

	/**
	 * Restrict account content for unverified readers.
	 */
	public static function restrict_account_content() {
		if ( \is_user_logged_in() && ! self::is_user_verified() ) {
			\remove_all_actions( 'woocommerce_account_content' );
			\add_action(
				'woocommerce_account_content',
				function() {
					include dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/reader-revenue/templates/myaccount-verify.php';
				}
			);
		}
	}

	/**
	 * Prevent updating email via Edit Account page.
	 */
	public static function edit_account_prevent_email_update() {
		if (
			! Donations::is_platform_stripe()
			|| empty( $_POST['account_email'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			|| ! \is_user_logged_in()
			|| ! Reader_Activation::is_enabled()
		) {
			return;
		}
		$_POST['account_email'] = \wp_get_current_user()->user_email;
	}

	/**
	 * Append a logout param after a reader logs out from My Account.
	 *
	 * @param string $redirect_to The redirect destination URL.
	 *
	 * @return string The filtered destination URL.
	 */
	public static function add_param_after_logout( $redirect_to ) {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return;
		}

		if ( \wc_get_page_permalink( 'myaccount' ) === $redirect_to ) {
			$redirect_to = \add_query_arg(
				[ 'logged_out' => 1 ],
				$redirect_to
			);
		}

		return $redirect_to;
	}

	/**
	 * Show a logout success message to readers after logging out via My Account.
	 */
	public static function show_message_after_logout() {
		if ( isset( $_GET['logged_out'] ) && function_exists( 'wc_add_notice' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			\wc_add_notice( __( 'You have successfully logged out.', 'newspack' ), 'success' );
		}
	}
}

WooCommerce_My_Account::init();
