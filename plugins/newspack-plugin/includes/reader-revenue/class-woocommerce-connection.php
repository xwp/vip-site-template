<?php
/**
 * Connection with WooCommerce's features.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Connection with WooCommerce's features.
 */
class WooCommerce_Connection {
	const CREATED_VIA_NAME                = 'newspack-stripe';
	const SUBSCRIPTION_STRIPE_ID_META_KEY = 'newspack-stripe-subscription-id';

	const DISABLED_SUBSCRIPTION_STATUSES = [
		'active',
		'pending',
		'on-hold',
		'pending-cancel',
		'cancelled',
		'expired',
		'trash',
		'deleted',
		'new-payment-method',
		'switched',
	];

	private static $created_membership_id; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	/**
	 * Initialize.
	 *
	 * @codeCoverageIgnore
	 */
	public static function init() {
		\add_action( 'admin_init', [ __CLASS__, 'disable_woocommerce_setup' ] );
		\add_filter( 'option_woocommerce_subscriptions_allow_switching_nyp_price', [ __CLASS__, 'force_allow_switching_subscription_amount' ] );

		// WooCommerce Subscriptions.
		\add_action( 'add_meta_boxes', [ __CLASS__, 'remove_subscriptions_schedule_meta_box' ], 45 );
		\add_filter( 'wc_order_is_editable', [ __CLASS__, 'make_syncd_subscriptions_uneditable' ], 10, 2 );
		\add_filter( 'woocommerce_order_actions', [ __CLASS__, 'remove_syncd_subscriptions_order_actions' ], 11, 1 );
		foreach ( self::DISABLED_SUBSCRIPTION_STATUSES as $status_name ) {
			\add_filter( 'woocommerce_can_subscription_be_updated_to_' . $status_name, [ __CLASS__, 'disable_subscription_status_updates' ], 11, 2 );
		}
		\add_filter( 'woocommerce_subscriptions_can_user_renew_early', [ __CLASS__, 'prevent_subscription_early_renewal' ], 11, 2 );
		\add_filter( 'woocommerce_subscription_is_manual', [ __CLASS__, 'set_syncd_subscriptions_as_manual' ], 11, 2 );
		\add_filter( 'wc_stripe_generate_payment_request', [ __CLASS__, 'stripe_gateway_payment_request_data' ], 10, 2 );
		\add_action( 'woocommerce_subscription_status_updated', [ __CLASS__, 'sync_reader_on_subscription_update' ], 10, 3 );

		// WooCommerce Memberships.
		\add_action( 'wc_memberships_user_membership_created', [ __CLASS__, 'wc_membership_created' ], 10, 2 );

		// WC Subscriptions hooks in and creates subscription at priority 100, so use priority 101.
		\add_action( 'woocommerce_checkout_order_processed', [ __CLASS__, 'order_processed' ], 101 );
		\add_action( 'option_woocommerce_subscriptions_failed_scheduled_actions', [ __CLASS__, 'filter_subscription_scheduled_actions_errors' ] );

		\add_action( 'wp_login', [ __CLASS__, 'sync_reader_on_customer_login' ], 10, 2 );
	}

	/**
	 * Check whether everything is set up to enable customer syncing to ESP.
	 *
	 * @return bool True if enabled. False if not.
	 */
	protected static function can_sync_customers() {
		return Reader_Activation::is_enabled() && class_exists( 'WC_Customer' ) && function_exists( 'wcs_get_users_subscriptions' );
	}

	/**
	 * If a membership is created during the request, save its ID.
	 *
	 * @param \WC_Memberships_Membership_Plan $membership_plan the plan that user was granted access to.
	 * @param array                           $args array of User Membership arguments.
	 */
	public static function wc_membership_created( $membership_plan, $args ) {
		self::$created_membership_id = $args['user_membership_id'];
	}

	/**
	 * Hide WooCommerce's setup task list. Newspack does the setup behind the scenes.
	 */
	public static function disable_woocommerce_setup() {
		if ( class_exists( '\Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists' ) ) {
			$task_list = \Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists::get_list( 'setup' );
			if ( $task_list ) {
				$task_list->hide();
			}
		}
	}

	/**
	 * Get WC's Order Item related to a donation frequency.
	 *
	 * @param string $frequency Donation frequency.
	 * @param number $amount Donation amount.
	 */
	private static function get_donation_order_item( $frequency, $amount = 0 ) {
		$product_id = Donations::get_donation_product( $frequency );
		if ( false === $product_id ) {
			return false;
		}

		$item = new \WC_Order_Item_Product();
		$item->set_product( \wc_get_product( $product_id ) );
		$item->set_total( $amount );
		$item->set_subtotal( $amount );
		$item->save();
		return $item;
	}

	/**
	 * Donations actions when order is processed.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function order_processed( $order_id ) {
		$product_id = Donations::get_order_donation_product_id( $order_id );

		/** Bail if not a donation order. */
		if ( false === $product_id ) {
			return;
		}

		if ( self::can_sync_customers() ) {
			$order = new \WC_Order( $order_id );
			if ( ! $order->get_customer_id() ) {
				return;
			}
			self::sync_reader_from_order( $order );
		}

		/**
		 * Fires when a donation order is processed.
		 *
		 * @param int $order_id   Order post ID.
		 * @param int $product_id Donation product post ID.
		 */
		\do_action( 'newspack_donation_order_processed', $order_id, $product_id );
	}

	/**
	 * Sync a reader's info to the ESP when they log in.
	 *
	 * @param string  $user_login User's login name.
	 * @param WP_User $user User object.
	 */
	public static function sync_reader_on_customer_login( $user_login, $user ) {
		if ( ! self::can_sync_customers() ) {
			return;
		}

		$customer = new \WC_Customer( $user->ID );

		// If user is not a Woo customer, don't need to sync them.
		if ( ! $customer->get_order_count() ) {
			return;
		}

		self::sync_reader_from_order( $customer->get_last_order() );
	}

	/**
	 * Get the contact data from a WooCommerce order.
	 *
	 * @param \WC_Order|int $order WooCommerce order or order ID.
	 * @param bool|string   $payment_page_url Payment page URL. If not provided, checkout URL will be used.
	 *
	 * @return array|false Contact data or false.
	 */
	public static function get_contact_from_order( $order, $payment_page_url = false ) {
		if ( is_integer( $order ) ) {
			$order = new \WC_Order( $order );
		}

		$user_id = $order->get_customer_id();
		if ( ! $user_id ) {
			return false;
		}

		$customer = new \WC_Customer( $user_id );
		$metadata = [
			'registration_method' => 'woocommerce-order',
		];

		$metadata[ Newspack_Newsletters::get_metadata_key( 'account' ) ]           = $order->get_customer_id();
		$metadata[ Newspack_Newsletters::get_metadata_key( 'registration_date' ) ] = $customer->get_date_created()->date( Newspack_Newsletters::METADATA_DATE_FORMAT );

		if ( false === $payment_page_url ) {
			$referer_from_order = $order->get_meta( '_newspack_referer' );
			if ( empty( $referer_from_order ) ) {
				$payment_page_url = \wc_get_checkout_url();
			} else {
				$payment_page_url = $referer_from_order;
			}
		}
		$metadata['current_page_url'] = $payment_page_url;

		$order_subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );

		// One-time donation.
		if ( empty( $order_subscriptions ) ) {
			$metadata[ Newspack_Newsletters::get_metadata_key( 'membership_status' ) ] = 'Donor';
			$metadata[ Newspack_Newsletters::get_metadata_key( 'total_paid' ) ]        = (float) $customer->get_total_spent();
			$metadata[ Newspack_Newsletters::get_metadata_key( 'total_paid' ) ]       += (float) $order->get_total();
			$metadata[ Newspack_Newsletters::get_metadata_key( 'product_name' ) ]      = '';
			$order_items = $order->get_items();
			if ( $order_items ) {
				$metadata[ Newspack_Newsletters::get_metadata_key( 'product_name' ) ] = reset( $order_items )->get_name();
			}
			$metadata[ Newspack_Newsletters::get_metadata_key( 'last_payment_amount' ) ] = $order->get_total();
			$order_date_paid = $order->get_date_paid();
			if ( null !== $order_date_paid ) {
				$metadata[ Newspack_Newsletters::get_metadata_key( 'last_payment_date' ) ] = $order_date_paid->date( Newspack_Newsletters::METADATA_DATE_FORMAT );
			}

			// Subscription donation.
		} else {
			$current_subscription = reset( $order_subscriptions );

			$metadata[ Newspack_Newsletters::get_metadata_key( 'membership_status' ) ] = 'Donor';
			if ( 'active' === $current_subscription->get_status() || 'pending' === $current_subscription->get_status() ) {
				if ( 'month' === $current_subscription->get_billing_period() ) {
					$metadata[ Newspack_Newsletters::get_metadata_key( 'membership_status' ) ] = 'Monthly Donor';
				}

				if ( 'year' === $current_subscription->get_billing_period() ) {
					$metadata[ Newspack_Newsletters::get_metadata_key( 'membership_status' ) ] = 'Yearly Donor';
				}
			} else {
				if ( 'month' === $current_subscription->get_billing_period() ) {
					$metadata[ Newspack_Newsletters::get_metadata_key( 'membership_status' ) ] = 'Ex-Monthly Donor';
				}

				if ( 'year' === $current_subscription->get_billing_period() ) {
					$metadata[ Newspack_Newsletters::get_metadata_key( 'membership_status' ) ] = 'Ex-Yearly Donor';
				}
			}

			$metadata[ Newspack_Newsletters::get_metadata_key( 'sub_start_date' ) ]      = $current_subscription->get_date( 'start' );
			$metadata[ Newspack_Newsletters::get_metadata_key( 'sub_end_date' ) ]        = $current_subscription->get_date( 'end' ) ? $current_subscription->get_date( 'end' ) : '';
			$metadata[ Newspack_Newsletters::get_metadata_key( 'billing_cycle' ) ]       = $current_subscription->get_billing_period();
			$metadata[ Newspack_Newsletters::get_metadata_key( 'recurring_payment' ) ]   = $current_subscription->get_total();
			$metadata[ Newspack_Newsletters::get_metadata_key( 'last_payment_date' ) ]   = $current_subscription->get_date( 'last_order_date_paid' ) ? $current_subscription->get_date( 'last_order_date_paid' ) : gmdate( Newspack_Newsletters::METADATA_DATE_FORMAT );
			$metadata[ Newspack_Newsletters::get_metadata_key( 'last_payment_amount' ) ] = $current_subscription->get_total();

			// When a WC Subscription is terminated, the next payment date is set to 0. We don't want to sync that – the next payment date should remain as it was
			// in the event of cancellation.
			$next_payment_date = $current_subscription->get_date( 'next_payment' );
			if ( $next_payment_date ) {
				$metadata[ Newspack_Newsletters::get_metadata_key( 'next_payment_date' ) ] = $next_payment_date;
			}

			$metadata[ Newspack_Newsletters::get_metadata_key( 'total_paid' ) ]  = (float) $customer->get_total_spent();
			$metadata[ Newspack_Newsletters::get_metadata_key( 'total_paid' ) ] += (float) $current_subscription->get_total();

			$metadata[ Newspack_Newsletters::get_metadata_key( 'product_name' ) ] = '';
			if ( $current_subscription ) {
				$subscription_order_items = $current_subscription->get_items();
				if ( $subscription_order_items ) {
					$metadata[ Newspack_Newsletters::get_metadata_key( 'product_name' ) ] = reset( $subscription_order_items )->get_name();
				}
			}
		}

		$first_name = $order->get_billing_first_name();
		$last_name  = $order->get_billing_last_name();
		return [
			'email'    => $order->get_billing_email(),
			'name'     => "$first_name $last_name",
			'metadata' => $metadata,
		];
	}

	/**
	 * Sync a customer to the ESP from an order.
	 *
	 * @param WC_Order    $order Order object.
	 * @param bool        $verify_created_via Whether to verify that the order was not created via the Stripe integration.
	 * @param bool|string $payment_page_url Payment page URL. If not provided, checkout URL will be used.
	 */
	public static function sync_reader_from_order( $order, $verify_created_via = true, $payment_page_url = false ) {
		if ( ! self::can_sync_customers() ) {
			return;
		}

		if ( $verify_created_via && self::CREATED_VIA_NAME === $order->get_created_via() ) {
			// Only sync orders not created via the Stripe integration.
			return;
		}

		$contact = self::get_contact_from_order( $order, $payment_page_url );
		if ( ! $contact ) {
			return;
		}

		\Newspack_Newsletters_Subscription::add_contact( $contact );
	}

	/**
	 * If the site is using woocommerce-memberships, create a new user and a
	 * membership, if the donation type calls for it.
	 *
	 * @param string $email_address Email address.
	 * @param string $full_name Full name.
	 * @param string $frequency Donation frequency.
	 * @param array  $metadata Donor metadata.
	 */
	public static function set_up_membership( $email_address, $full_name, $frequency, $metadata = [] ) {
		if ( ! class_exists( 'WC_Memberships_Membership_Plans' ) ) {
			return;
		}
		$wc_memberships_membership_plans = new \WC_Memberships_Membership_Plans();
		$should_create_account           = false;
		$membership_plans                = $wc_memberships_membership_plans->get_membership_plans();
		$order_items                     = [ self::get_donation_order_item( $frequency ) ];
		foreach ( $membership_plans as $plan ) {
			$access_granting_product_ids = \wc_memberships_get_order_access_granting_product_ids( $plan, '', $order_items );
			if ( ! empty( $access_granting_product_ids ) ) {
				$should_create_account = true;
				break;
			}
		}
		if ( $should_create_account ) {
			if ( Reader_Activation::is_enabled() ) {
				$metadata = array_merge( $metadata, [ 'registration_method' => 'woocommerce-memberships' ] );
				$user_id  = Reader_Activation::register_reader( $email_address, $full_name, true, $metadata );
				return $user_id;
			}

			Logger::log( 'This order will result in a membership, creating account for user.' );
			$user_login = \sanitize_title( $full_name );
			$user_id    = \wc_create_new_customer( $email_address, $user_login, '', [ 'display_name' => $full_name ] );

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			// Log the new user in.
			\wp_set_current_user( $user_id, $user_login );
			\wp_set_auth_cookie( $user_id );

			return $user_id;
		}
	}

	/**
	 * Find order by Stripe transaction ID.
	 *
	 * @param string $transaction_id Transaction ID.
	 */
	private static function find_order_by_transaction_id( $transaction_id ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s AND meta_value=%s;", '_transaction_id', $transaction_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Get a WC Subscription object by Stripe Subscription ID.
	 *
	 * @param string $stripe_subscription_id Stripe Subscription ID.
	 * @return WC_Subscription|false Subscription object or false.
	 */
	public static function get_subscription_by_stripe_subscription_id( $stripe_subscription_id ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return false;
		}
		global $wpdb;
		$query           = $wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta WHERE `meta_key` = %s AND `meta_value` = %s",
			self::SUBSCRIPTION_STRIPE_ID_META_KEY,
			$stripe_subscription_id
		);
		$subscription_id           = $wpdb->get_var( $query ); // phpcs:ignore
		if ( $subscription_id ) {
			return \wcs_get_subscription( $subscription_id );
		} else {
			Logger::error( 'Error: could not find WC subscription by Stripe id: ' . $stripe_subscription_id );
			return false;
		}
	}

	/**
	 * Add data to an order or subscription.
	 *
	 * @param WC_Order $order Order object. Can be a subscription or an order.
	 * @param array    $order_data Order data.
	 */
	private static function add_universal_order_data( $order, $order_data ) {
		$order->set_currency( $order_data['currency'] );
		$order->set_date_created( $order_data['date'] );
		$order->set_billing_email( $order_data['email'] );
		$order->set_billing_first_name( $order_data['name'] );

		if ( isset( $order_data['subscribed'] ) && $order_data['subscribed'] ) {
			$order->add_order_note( __( 'Donor has opted-in to your newsletter.', 'newspack' ) );
		}

		if ( ! empty( $order_data['client_id'] ) ) {
			$order->add_meta_data( NEWSPACK_CLIENT_ID_COOKIE_NAME, $order_data['client_id'] );
		}

		if ( ! empty( $order_data['referer'] ) ) {
			$order->add_meta_data( '_newspack_referer', $order_data['referer'] );
		}

		// Add all newspack_* meta data.
		foreach ( $order_data as $key => $value ) {
			if ( 0 === strpos( $key, 'newspack_' ) ) {
				$order->add_meta_data( '_' . $key, $value );
			}
		}

		if ( ! empty( $order_data['user_id'] ) ) {
			$order->set_customer_id( $order_data['user_id'] );
		}

		$order->set_created_via( self::CREATED_VIA_NAME );
	}

	/**
	 * Update an order.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $update Update data.
	 */
	public static function update_order( $order_id, $update ) {
		$order = \wc_get_order( $order_id );
		if ( false === $order ) {
			Logger::error( 'Could not find WC order with id: ' . $order_id );
		} else {
			if ( isset( $update['status'] ) ) {
				$order->set_status( $update['status'] );
			}
			self::add_wc_stripe_gateway_metadata( $order, $update );
			$order->save();
			Logger::log( 'Updated WC order with id: ' . $order->get_id() );
		}
	}

	/**
	 * Update WC Stripe Gateway related metadata to an order or subscription.
	 * The order has to be saved afterwards.
	 *
	 * @param WC_Order $order Order object. Can be a subscription or an order.
	 * @param array    $metadata Metadata.
	 */
	public static function add_wc_stripe_gateway_metadata( $order, $metadata ) {
		$order->set_payment_method( 'stripe' );

		if ( isset( $metadata['stripe_id'] ) ) {
			$order->set_transaction_id( $metadata['stripe_id'] );
		}
		if ( isset( $metadata['stripe_fee'] ) ) {
			$order->add_meta_data( '_stripe_fee', $metadata['stripe_fee'] );
		}
		if ( isset( $metadata['stripe_net'] ) ) {
			$order->add_meta_data( '_stripe_net', $metadata['stripe_net'] );
		}
		if ( isset( $metadata['payment_method_title'] ) ) {
			$order->set_payment_method_title( $metadata['payment_method_title'] );
		} else {
			$order->set_payment_method_title( __( 'Stripe via Newspack', 'newspack' ) );
		}
		if ( isset( $metadata['stripe_customer_id'] ) ) {
			$order->add_meta_data( '_stripe_customer_id', $metadata['stripe_customer_id'] );
		}
		if ( isset( $metadata['currency'] ) ) {
			$order->add_meta_data( '_stripe_currency', $metadata['currency'] );
		}
		if ( isset( $metadata['stripe_source_id'] ) ) {
			$order->add_meta_data( '_stripe_source_id', $metadata['stripe_source_id'] );
		}
		if ( isset( $metadata['stripe_intent_id'] ) ) {
			$order->add_meta_data( '_stripe_intent_id', $metadata['stripe_intent_id'] );
		}
		if ( 'completed' === $order->get_status() ) {
			$order->add_meta_data( '_stripe_charge_captured', 'yes' );
		}
	}

	/**
	 * Create a WooCommerce order.
	 *
	 * @param array         $order_data Order data.
	 * @param WC_Order_Item $item Order item.
	 */
	private static function create_order( $order_data, $item ) {
		$frequency = $order_data['frequency'];
		$order     = \wc_create_order();

		self::add_universal_order_data( $order, $order_data );

		// Add notes to the order.
		if ( 'once' === $frequency ) {
			$order->add_order_note( __( 'One-time Newspack donation.', 'newspack' ) );
		} else {
			/* translators: %s - donation frequency */
			$order->add_order_note( sprintf( __( 'Newspack donation with frequency: %s.', 'newspack' ), $frequency ) );
		}

		$order->add_item( $item );
		$order->calculate_totals();

		$status = 'completed';
		if ( isset( $order_data['status'] ) && is_string( $order_data['status'] ) ) {
			$status = $order_data['status'];
		}
		$order->set_status( $status );

		// Metadata for woocommerce-gateway-stripe plugin.
		self::add_wc_stripe_gateway_metadata( $order, $order_data );

		if ( ! empty( $order_data['client_id'] ) ) {
			/**
			 * When a new order is created that can be associated with a client ID,
			 * fire an action with the client ID and the relevant order info.
			 *
			 * @param WC_Order    $order Donation order.
			 * @param string      $client_id Client ID.
			 */
			do_action( 'newspack_new_donation_woocommerce', $order, $order_data['client_id'] );
		}

		$order->save();

		Logger::log( 'Created WC order with id: ' . $order->get_id() );

		return $order;
	}

	/**
	 * Convert timestamp to a date string.
	 *
	 * @param int $timestamp Timestamp.
	 */
	private static function convert_timestamp_to_date( $timestamp ) {
		if ( 0 === $timestamp ) {
			return 0;
		}
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Add a donation transaction to WooCommerce.
	 *
	 * @param object $order_data Order data.
	 * @return array Data of created order and subscription, if applicable.
	 */
	public static function create_transaction( $order_data ) {
		if ( isset( $order_data['stripe_id'] ) ) {
			$found_order = self::find_order_by_transaction_id( $order_data['stripe_id'] );
			if ( ! empty( $found_order ) ) {
				Logger::log( 'NOT creating an order, it was already synced.' );
				return;
			}
		}

		$frequency              = $order_data['frequency'];
		$stripe_subscription_id = false;
		if ( isset( $order_data['stripe_subscription_id'] ) ) {
			$stripe_subscription_id = $order_data['stripe_subscription_id'];
		}

		// Match the Stripe product to WC product.
		$item = self::get_donation_order_item( $frequency, $order_data['amount'] );
		if ( false === $item ) {
			return new \WP_Error( 'newspack_woocommerce', __( 'Missing donation product.', 'newspack' ) );
		}

		$subscription_status = 'none';
		if ( isset( $order_data['subscription_status'] ) ) {
			$subscription_status = $order_data['subscription_status'];
		}

		if (
			Donations::is_recurring( $frequency )
			&& function_exists( 'wcs_create_subscription' ) // WC Subscriptions plugin is active.
			&& isset( $order_data['stripe_invoice_billing_reason'] )
			&& isset( $order_data['stripe_subscription_id'] ) && is_string( $order_data['stripe_subscription_id'] )
			&& isset( $order_data['user_id'] ) // Subscription can't be created without a user.
		) {
			if ( 'subscription_create' === $order_data['stripe_invoice_billing_reason'] ) {
				$subscription_status = 'created';
			} elseif ( 'subscription_cycle' === $order_data['stripe_invoice_billing_reason'] ) {
				$subscription_status = 'renewed';
			}
		}

		$order        = false;
		$subscription = false;

		/**
		 * Disable the emails sent to admin & customer after a subscription renewal order is completed.
		 */
		\add_filter( 'woocommerce_email_enabled_customer_completed_renewal_order', '__return_false' );
		\add_filter( 'woocommerce_email_enabled_new_renewal_order', '__return_false' );

		if ( 'renewed' === $subscription_status ) {
			/**
			 * Handle WooCommerce Subscriptions - subscription renewal. This will create a new order
			 * and associate it with a subscription.
			 */
			$subscription = self::get_subscription_by_stripe_subscription_id( $stripe_subscription_id );
			if ( $subscription ) {
				$order = \wcs_create_renewal_order( $subscription );
				self::add_universal_order_data( $order, $order_data );
				$order->set_status( 'completed' );
				$order->save();
				Logger::log( 'Updated WC subscription with id: ' . $subscription->get_id() . ' with a new order of id: ' . $order->get_id() );
			} else {
				// Linked subscription not found, just create an order.
				// Temporarily disable the "New Order" email, since this is a renewal.
				\add_filter( 'woocommerce_email_enabled_new_order', '__return_false' );
				$order = self::create_order( $order_data, $item );
				\remove_filter( 'woocommerce_email_enabled_new_order', '__return_false' );
			}
		} else {
			/**
			 * Create an order for a one-time or subscription-creation donation.
			 */
			$order = self::create_order( $order_data, $item );

			/**
			 * Handle WooCommerce Subscriptions - new subscription.
			 */
			if ( 'created' === $subscription_status ) {
				// A subscription needs a valid user. If no user ID is provided, attribute the
				// subscription to the user with the supplied email address.
				if ( empty( $order_data['user_id'] ) ) {
					$user = \get_user_by( 'email', $order_data['email'] );
					if ( ! $user || \is_wp_error( $user ) ) {
						Logger::log( 'Could not find user by email, creating a user.' );
						$display_name = explode( '@', $order_data['email'], 2 )[0];
						$user_id      = \wc_create_new_customer(
							$order_data['email'],
							\sanitize_user( $order_data['email'], true ),
							\wp_generate_password(),
							[ 'display_name' => $display_name ]
						);
						$order->set_customer_id( $user_id );
					} else {
						$order->set_customer_id( $user->ID );
					}
					$order->save();
				}
				$subscription_creation_payload = [
					'start_date'       => self::convert_timestamp_to_date( $order_data['date'] ),
					'order_id'         => $order->get_id(),
					'billing_period'   => $frequency,
					'billing_interval' => 1, // Every billing period (not e.g. every *second* month).
				];
				if ( isset( $order_data['wc_subscription_status'] ) ) {
					$subscription_creation_payload['status'] = $order_data['wc_subscription_status'];
				}
				$subscription = \wcs_create_subscription( $subscription_creation_payload );

				if ( is_wp_error( $subscription ) ) {
					Logger::error( 'Error creating WC subscription: ' . $subscription->get_error_message() );
				} else {
					$subscription_id = $subscription->get_id();

					$wc_subscription_payload = [
						'stripe_customer_id' => $order_data['stripe_customer_id'],
					];
					if ( isset( $order_data['stripe_source_id'] ) ) {
						$wc_subscription_payload['stripe_source_id'] = $order_data['stripe_source_id'];
					}
					self::add_wc_stripe_gateway_metadata( $subscription, $wc_subscription_payload );
					self::add_universal_order_data( $subscription, $order_data );

					// Mint a new item – subscription is a new WC order.
					$item = self::get_donation_order_item( $frequency, $order_data['amount'] );
					$subscription->add_item( $item );

					if ( false === $stripe_subscription_id ) {
						$subscription->add_order_note( __( 'This subscription was created via Newspack.', 'newspack' ) );
					} else {
						/* translators: %s - donation frequency */
						$subscription->add_order_note( sprintf( __( 'Newspack subscription with frequency: %s. The recurring payment and the subscription will be handled in Stripe, so you\'ll see "Manual renewal" as the payment method in WooCommerce.', 'newspack' ), $frequency ) );
						$subscription->update_status( 'active' ); // Settings status via method (not in wcs_create_subscription), to make WCS recalculate dates.
					}
					$subscription->calculate_totals();

					if ( false === $stripe_subscription_id ) {
						$subscription->set_payment_method( 'stripe' );
						if ( isset( $order_data['payment_method_title'] ) ) {
							$subscription->set_payment_method_title( $order_data['payment_method_title'] );
						} else {
							$subscription->set_payment_method_title( __( 'Stripe', 'newspack' ) );
						}
					} else {
						update_post_meta( $subscription_id, self::SUBSCRIPTION_STRIPE_ID_META_KEY, $stripe_subscription_id );
					}

					$subscription->save();

					Logger::log( 'Created WC subscription with id: ' . $subscription_id );

					if ( class_exists( 'WC_Memberships_Integration_Subscriptions_User_Membership' ) && self::$created_membership_id ) {
						Logger::log( 'Linking membership ' . self::$created_membership_id . ' to subscription ' . $subscription_id );
						$subscription_membership = new \WC_Memberships_Integration_Subscriptions_User_Membership( self::$created_membership_id );
						$subscription_membership->set_subscription_id( $subscription_id );
					}
				}
			}
		}

		\remove_filter( 'woocommerce_email_enabled_customer_completed_renewal_order', '__return_false' );
		\remove_filter( 'woocommerce_email_enabled_new_renewal_order', '__return_false' );

		/**
		 * Handle WooCommerce Memberships.
		 */
		if ( class_exists( 'WC_Memberships_Membership_Plans' ) ) {
			$wc_memberships_membership_plans = new \WC_Memberships_Membership_Plans();
			// If the order items are tied to a membership plan, a membership will be created.
			$wc_memberships_membership_plans->grant_access_to_membership_from_order( $order );
		}

		return [
			'order_id'        => ( ! \is_wp_error( $order ) && $order ) ? $order->get_id() : false,
			'subscription_id' => ( ! \is_wp_error( $subscription ) && $subscription ) ? $subscription->get_id() : false,
		];
	}

	/**
	 * Cancel a subscription in WooCommerce.
	 *
	 * @param string $stripe_subscription_id Stripe subscription ID.
	 * @param int    $end_date Timestamp of when to cancel the subscription.
	 *
	 * @return int Number of remaining active subscriptions for the user.
	 */
	public static function end_subscription( $stripe_subscription_id, $end_date ) {
		$subscription = self::get_subscription_by_stripe_subscription_id( $stripe_subscription_id );
		$active_subs  = 0;
		if ( $subscription ) {
			$wc_user_id = $subscription->get_user_id();
			$subscription->delete_date( 'next_payment' );
			$subscription->update_dates(
				[
					'end' => self::convert_timestamp_to_date( $end_date ),
				]
			);
			$subscription->set_status( 'cancelled' );
			$subscription->save();
			Logger::log( 'Cancelled WC subscription with id: ' . $subscription->get_id() );

			if ( $wc_user_id && function_exists( 'wcs_get_users_subscriptions' ) ) {
				$all_subs = \wcs_get_users_subscriptions( $wc_user_id );

				foreach ( $all_subs as $sub ) {
					if ( 'active' === $sub->get_status() ) {
						$active_subs ++;
					}
				}
			}
		}

		return $active_subs;
	}

	/**
	 * Set status to pending cancellation.
	 *
	 * @param string $stripe_subscription_id Stripe subscription ID.
	 * @param int    $cancelled_at Timestamp of when the subscription cancellation happened.
	 * @param int    $end Timestamp of when to cancel the subscription.
	 */
	public static function set_pending_cancellation_subscription( $stripe_subscription_id, $cancelled_at, $end ) {
		$subscription = self::get_subscription_by_stripe_subscription_id( $stripe_subscription_id );
		if ( $subscription ) {
			$subscription->delete_date( 'next_payment' );
			self::update_subscription_dates(
				$stripe_subscription_id,
				[
					'cancelled' => $cancelled_at,
					'end'       => $end,
				]
			);
			Logger::log( 'Set to pending cancallation: WC subscription with id ' . $subscription->get_id() . ' by Stripe id: ' . $stripe_subscription_id );
		}
	}

	/**
	 * Re-activate a subscription.
	 *
	 * @param string $stripe_subscription_id Stripe subscription ID.
	 */
	public static function reactivate_subscription( $stripe_subscription_id ) {
		$subscription = self::get_subscription_by_stripe_subscription_id( $stripe_subscription_id );
		if ( $subscription ) {
			$subscription->set_status( 'active' );
			self::update_subscription_dates(
				$stripe_subscription_id,
				[ 'end' => 0 ]
			);
			$subscription->save();
			Logger::log( 'Reactivated WC subscription with id ' . $subscription->get_id() . ' by Stripe id: ' . $stripe_subscription_id );
		}
	}

	/**
	 * Schedule a subscriptions for cancellation.
	 *
	 * @param string $stripe_subscription_id Stripe subscription ID.
	 * @param array  $dates Dates to update.
	 */
	private static function update_subscription_dates( $stripe_subscription_id, $dates ) {
		$subscription = self::get_subscription_by_stripe_subscription_id( $stripe_subscription_id );
		if ( $subscription ) {
			foreach ( $dates as $key => $value ) {
				$dates[ $key ] = self::convert_timestamp_to_date( $value );
			}
			$subscription->update_dates( $dates );
			Logger::log( 'Updated dates of WC subscription with id ' . $subscription->get_id() . ' by Stripe id: ' . $stripe_subscription_id );
		}
	}

	/**
	 * Put a WC subscription on hold.
	 *
	 * The $resumes_at parameter is not used, as WC Subscriptions seems to have a different concept
	 * of subscription pausing – there's no re-activation date prop and instead a "suspension count" is used.
	 *
	 * @param string $stripe_subscription_id Stripe subscription ID.
	 * @param int    $resumes_at Dates at which to resume the subscription. Currently unused.
	 */
	public static function put_subscription_on_hold( $stripe_subscription_id, $resumes_at ) {
		$subscription = self::get_subscription_by_stripe_subscription_id( $stripe_subscription_id );
		if ( $subscription ) {
			$subscription->set_status( 'on-hold' );
			$subscription->save();
			Logger::log( 'Putting on hold WC subscription with id: ' . $subscription->get_id() . ' by Stripe id: ' . $stripe_subscription_id );
		}
	}

	/**
	 * Is this subscription sync'd with Stripe?
	 *
	 * @param \WC_Subscription|int $subscription The subscription object or post ID.
	 */
	private static function is_synchronised_with_stripe( $subscription ) {
		if ( is_numeric( $subscription ) ) {
			$stripe_subscription_id = get_post_meta( $subscription, self::SUBSCRIPTION_STRIPE_ID_META_KEY, true );
		} elseif ( $subscription ) {
			$stripe_subscription_id = $subscription->get_meta( self::SUBSCRIPTION_STRIPE_ID_META_KEY );
		} else {
			$stripe_subscription_id = false;
		}
		return boolval( $stripe_subscription_id );
	}

	/**
	 * Remove subscription-related meta boxes if the subscription is sync'd with Stripe.
	 * This is because some subscription variables should not be editable here.
	 *
	 * @param string $post_type Post type of current screen.
	 */
	public static function remove_subscriptions_schedule_meta_box( $post_type ) {
		global $post_ID;
		if ( 'shop_subscription' === $post_type && self::is_synchronised_with_stripe( $post_ID ) ) {
			remove_meta_box( 'woocommerce-subscription-schedule', 'shop_subscription', 'side' );
		}
	}

	/**
	 * Disable editing of subscriptions which are sync'd with Stripe.
	 *
	 * @param bool             $is_editable Whether the subscription is editable.
	 * @param \WC_Subscription $subscription The subscription object.
	 */
	public static function make_syncd_subscriptions_uneditable( $is_editable, $subscription ) {
		if ( self::is_synchronised_with_stripe( $subscription ) ) {
			return false;
		}
		return $is_editable;
	}

	/**
	 * Disable early renewal of subscriptions which are sync'd with Stripe.
	 *
	 * @param bool             $can_renew_early Whether the subscription can be renewed early.
	 * @param \WC_Subscription $subscription The subscription object.
	 */
	public static function prevent_subscription_early_renewal( $can_renew_early, $subscription ) {
		if ( self::is_synchronised_with_stripe( $subscription ) ) {
			return false;
		}
		return $can_renew_early;
	}

	/**
	 * Force sync'd subscriptions to manual-renewal state.
	 *
	 * @param bool             $is_manual Whether the subscription is manually-renewed.
	 * @param \WC_Subscription $subscription The subscription object.
	 */
	public static function set_syncd_subscriptions_as_manual( $is_manual, $subscription ) {
		if ( self::is_synchronised_with_stripe( $subscription ) ) {
			return true;
		}
		return $is_manual;
	}

	/**
	 * Remove WC Subscriptions order actions for the sync'd subscription.
	 *
	 * @param array $actions An array of available actions.
	 */
	public static function remove_syncd_subscriptions_order_actions( $actions ) {
		global $theorder;
		if ( self::is_synchronised_with_stripe( $theorder ) ) {
			foreach ( $actions as $key => $value ) {
				if ( strpos( $key, 'wcs_' ) === 0 ) {
					unset( $actions[ $key ] );
				}
			}
		}
		return $actions;
	}

	/**
	 * Disable an extra status update (e.g. new-payment-method) for sync'd subscriptions.
	 *
	 * @param bool             $can_update Whether the status can be updated.
	 * @param \WC_Subscription $subscription The subscription object.
	 */
	public static function disable_subscription_status_updates( $can_update, $subscription ) {
		if ( self::is_synchronised_with_stripe( $subscription ) ) {
			return false;
		}
		return $can_update;
	}

	/**
	 * Filter WC Subscriptions' renewal errors. If a subscription is sync'd,
	 * it won't be handled by WC Subscriptions, so error can be ignored.
	 *
	 * @param array $renewal_errors An associative array of errors.
	 */
	public static function filter_subscription_scheduled_actions_errors( $renewal_errors ) {
		if ( is_array( $renewal_errors ) ) {
			foreach ( $renewal_errors as $key => $error ) {
				if ( isset( $error['args'], $error['args']['subscription_id'], $error['type'] ) ) {
					$subscription_id = $error['args']['subscription_id'];
					if ( self::is_synchronised_with_stripe( $subscription_id ) ) {
						unset( $renewal_errors[ $key ] );
					}
				}
			}
		}
		return $renewal_errors;
	}

	/**
	 * Create a payment description for Stripe Gateway.
	 *
	 * @param array  $order_data An array of order data, containing the order ID and subscription ID, if applicable.
	 * @param string $frequency The frequency of the donation.
	 */
	public static function create_payment_description( $order_data, $frequency ) {

		if ( $order_data['subscription_id'] ) {
			return sprintf(
				/* translators: %s: Product name */
				__( 'Newspack %1$s (Order #%2$d, Subscription #%3$d)', 'newspack' ),
				Donations::get_donation_name_by_frequency( $frequency ),
				$order_data['order_id'],
				$order_data['subscription_id']
			);
		} else {
			return sprintf(
				/* translators: %s: Product name */
				__( 'Newspack %1$s (Order #%2$d)', 'newspack' ),
				Donations::get_donation_name_by_frequency( $frequency ),
				$order_data['order_id']
			);
		}
	}

	/**
	 * Filter post request made by the Stripe Gateway for Stripe payments.
	 *
	 * @param array     $post_data An array of metadata.
	 * @param \WC_Order $order The order object.
	 */
	public static function stripe_gateway_payment_request_data( $post_data, $order ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			return $post_data;
		}
		$related_subscriptions = \wcs_get_subscriptions_for_renewal_order( $order );
		if ( ! empty( $related_subscriptions ) ) {
			// In theory, there should be just one subscription per renewal.
			$subscription    = reset( $related_subscriptions );
			$subscription_id = $subscription->get_id();
			// Add subscription ID to any renewal.
			$post_data['metadata']['subscription_id'] = $subscription_id;
			if ( \wcs_order_contains_renewal( $order ) ) {
				$post_data['metadata']['subscription_status'] = 'renewed';
			} else {
				$post_data['metadata']['subscription_status'] = 'created';
			}
			// Add the description only for Newspack-created subscriptions.
			if ( self::CREATED_VIA_NAME === $subscription->get_created_via() ) {
				$post_data['metadata']['origin'] = 'newspack';
				$post_data['description']        = self::create_payment_description(
					[
						'order_id'        => $order->get_id(),
						'subscription_id' => $subscription_id,
					],
					$subscription->get_billing_period()
				);
			}
		}
		return $post_data;
	}

	/**
	 * When a subscription's status is updated, re-sync the reader.
	 *
	 * @param object $subscription An instance of a WC_Subscription object.
	 * @param string $new_status A valid subscription status.
	 * @param string $old_status A valid subscription status.
	 */
	public static function sync_reader_on_subscription_update( $subscription, $new_status, $old_status ) {
		$order = $subscription->get_last_order( 'all' );

		if ( $order && self::can_sync_customers() ) {
			if ( ! $order->get_customer_id() ) {
				return;
			}
			self::sync_reader_from_order( $order );
		}
	}

	/**
	 * Force allow switching the subscription amount unless the NEWSPACK_PREVENT_WC_SUBS_ALLOW_SWITCHING_OVERRIDE constant is set
	 *
	 * @param bool $can_switch Whether the subscription amount can be switched.
	 */
	public static function force_allow_switching_subscription_amount( $can_switch ) {
		if ( defined( 'NEWSPACK_PREVENT_WC_SUBS_ALLOW_SWITCHING_OVERRIDE' ) && NEWSPACK_PREVENT_WC_SUBS_ALLOW_SWITCHING_OVERRIDE ) {
			return $can_switch;
		}
		return 'yes';
	}
}

WooCommerce_Connection::init();
