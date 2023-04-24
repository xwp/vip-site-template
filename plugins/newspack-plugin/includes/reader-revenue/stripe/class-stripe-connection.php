<?php
/**
 * All things Stripe.
 *
 * @package Newspack
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

namespace Newspack;

use Stripe\Stripe;

defined( 'ABSPATH' ) || exit;

/**
 * All things Stripe.
 */
class Stripe_Connection {
	const STRIPE_DATA_OPTION_NAME        = 'newspack_stripe_data';
	const STRIPE_DONATION_PRICE_METADATA = 'newspack_donation_price';
	const STRIPE_CUSTOMER_ID_USER_META   = '_newspack_stripe_customer_id';

	const ESP_METADATA_VALUES = [
		'once_donor'    => 'Donor',
		'monthly_donor' => 'Monthly Donor',
		'yearly_donor'  => 'Yearly Donor',
	];

	/**
	 * Ensures the customer ID lookup is run only once per request.
	 *
	 * @var bool
	 */
	private static $is_looking_up_customer_id = false;

	/**
	 * Cache.
	 *
	 * @var array
	 */
	private static $cache = [
		'invoices'      => [],
		'subscriptions' => [],
	];

	/**
	 * Initialize.
	 *
	 * @codeCoverageIgnore
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'handle_merchant_id_file_request' ] );
		add_action( 'init', [ __CLASS__, 'register_apple_pay_domain' ] );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', [ __CLASS__, 'is_wc_complete_order_email_enabled' ] );
		add_action( 'newspack_reader_verified', [ __CLASS__, 'newspack_reader_verified' ] );
		add_action( 'delete_user', [ __CLASS__, 'cancel_user_subscriptions' ], 90 ); // Priority 90 to run after Newsletters deletes the contact in ESP.
	}

	/**
	 * Get Stripe data blueprint.
	 */
	public static function get_default_stripe_data() {
		$location_code = 'US';
		$currency      = 'USD';

		// The instance might've used WooCommerce. Read WC's saved settings to
		// provide more sensible defaults.
		$wc_country = get_option( 'woocommerce_default_country', false );
		if ( $wc_country ) {
			$wc_country = explode( ':', $wc_country )[0];
		}
		$valid_country_codes = wp_list_pluck( newspack_get_countries(), 'value' );
		if ( $wc_country && in_array( $wc_country, $valid_country_codes ) ) {
			$location_code = $wc_country;
		}
		$wc_currency      = get_option( 'woocommerce_currency', false );
		$valid_currencies = wp_list_pluck( newspack_get_currencies_options(), 'value' );
		if ( $wc_currency && in_array( $wc_currency, $valid_currencies ) ) {
			$currency = $wc_currency;
		}

		return [
			'enabled'            => false,
			'testMode'           => false,
			'publishableKey'     => '',
			'secretKey'          => '',
			'testPublishableKey' => '',
			'testSecretKey'      => '',
			'currency'           => $currency,
			'location_code'      => $location_code,
			'newsletter_list_id' => '',
		];
	}

	/**
	 * Get Stripe data, either from WC, or saved in options table.
	 */
	public static function get_stripe_data() {
		$stripe_data = self::get_saved_stripe_data();
		return $stripe_data;
	}

	/**
	 * Update Stripe data. If WC is the donations platform, update is there too.
	 *
	 * @param object $updated_stripe_data Updated Stripe data to be saved.
	 */
	public static function update_stripe_data( $updated_stripe_data ) {
		if ( Donations::is_platform_wc() ) {
			// If WC is configured, set Stripe data in WC.
			$wc_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'woocommerce' );
			$wc_configuration_manager->update_wc_stripe_settings( $updated_stripe_data );
		}
		if ( isset( $updated_stripe_data['fee_multiplier'] ) ) {
			update_option( 'newspack_blocks_donate_fee_multiplier', $updated_stripe_data['fee_multiplier'] );
		}
		if ( isset( $updated_stripe_data['fee_static'] ) ) {
			update_option( 'newspack_blocks_donate_fee_static', $updated_stripe_data['fee_static'] );
		}

		$valid_keys     = array_keys( self::get_default_stripe_data() );
		$validated_data = array_intersect_key( $updated_stripe_data, array_flip( $valid_keys ) );

		// Save it in options table.
		return update_option( self::STRIPE_DATA_OPTION_NAME, $validated_data );
	}

	/**
	 * Get Stripe customer.
	 *
	 * @param string $customer_id Customer ID.
	 */
	public static function get_customer_by_id( $customer_id ) {
		$stripe = self::get_stripe_client();
		try {
			return $stripe->customers->retrieve( $customer_id, [] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not fetch customer.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get Stripe charge.
	 *
	 * @param string $charge_id Customer ID.
	 */
	public static function get_charge_by_id( $charge_id ) {
		$stripe = self::get_stripe_client();
		try {
			return $stripe->charges->retrieve( $charge_id, [] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not fetch charge.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get customer's charges.
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $page Page of results.
	 * @param int    $limit Limit of results.
	 * @param string $type 'charge' or 'invoice'. The latter contains information about a subcription, if applicable.
	 */
	public static function get_customer_transactions( $customer_id, $page = false, $limit = false, $type = 'charge' ) {
		$stripe = self::get_stripe_client();
		try {
			$all_transactions = [];
			$params           = [
				'query' => 'customer:"' . $customer_id . '"',
			];
			if ( $limit ) {
				$params['limit'] = $limit;
			}
			if ( $page ) {
				$params['page'] = $page;
			}
			if ( 'invoice' === $type ) {
				$response = $stripe->invoices->search( $params );
			} elseif ( 'charge' === $type ) {
				$response = $stripe->charges->search( $params );
			} else {
				return new \WP_Error( 'stripe_newspack', __( 'Invalid transaction type.', 'newspack' ) );
			}
			$all_transactions = $response['data'];
			if ( $limit && count( $all_transactions ) >= $limit ) {
				return $all_transactions;
			}
			if ( $response['has_more'] ) {
				$all_transactions = array_merge( $all_transactions, self::get_customer_transactions( $customer_id, $response['next_page'], $limit, $type ) );
			}
			return $all_transactions;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not fetch customer\'s charges.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get the sum of all customer's charges (Lifetime Value).
	 *
	 * @param string $customer_id Customer ID.
	 */
	public static function get_customer_ltv( $customer_id ) {
		$all_charges = self::get_customer_transactions( $customer_id );
		if ( \is_wp_error( $all_charges ) ) {
			return $all_charges;
		}
		return array_reduce(
			$all_charges,
			function( $total, $charge ) {
				return $total + self::normalise_amount( $charge['amount'], $charge['currency'] );
			},
			0
		);
	}

	/**
	 * Get Stripe billing portal configuration used for this integration.
	 * The configuration will disallow the customer to update their email,
	 * because it has to stay in sync with WP.
	 */
	private static function get_billing_portal_configuration_id() {
		$config_meta_key = 'newspack_config_v2';
		$stripe          = self::get_stripe_client();
		try {
			$all_configs = $stripe->billingPortal->configurations->all( [ 'active' => true ] );
			$config_id   = false;
			foreach ( $all_configs['data'] as $config ) {
				if ( $config['metadata'][ $config_meta_key ] ) {
					$config_id = $config['id'];
				}
			}
			if ( ! $config_id ) {
				$new_config = $stripe->billingPortal->configurations->create(
					[
						'features'         => [
							'customer_update'       => [
								'allowed_updates' => [ 'tax_id' ],
								'enabled'         => true,
							],
							'invoice_history'       => [
								'enabled' => true,
							],
							'payment_method_update' => [
								'enabled' => true,
							],
							'subscription_pause'    => [
								'enabled' => false,
							],
							'subscription_cancel'   => [
								'cancellation_reason' => [
									'enabled' => true,
									'options' => [ 'too_expensive', 'unused', 'other' ],
								],
								'enabled'             => true,
								'mode'                => 'at_period_end',
								'proration_behavior'  => 'none',
							],
						],
						'business_profile' => [ 'headline' => '' ],
						'metadata'         => [
							$config_meta_key => true,
						],
					]
				);
				$config_id  = $new_config['id'];
				Logger::log( 'Created a new Stripe billing portal configuration, id is: ' . $config_id );
			}
			return $config_id;
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed at creating Stripe billing portal configuration: ' . $e->getMessage() );
			return new \WP_Error( 'stripe_newspack', __( 'Could not retrieve or create billing portal configuration.', 'newspack' ), $e->getMessage() );
		}
	}
	/**
	 * Get Stripe customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @param string $return_url Return URL.
	 */
	public static function get_billing_portal_url( $customer_id, $return_url = false ) {
		$stripe = self::get_stripe_client();
		if ( false === $return_url ) {
			global $wp;
			$return_url = home_url( add_query_arg( array(), $wp->request ) );
		}
		try {
			$portal_data = $stripe->billingPortal->sessions->create(
				[
					'customer'      => $customer_id,
					'return_url'    => $return_url,
					'configuration' => self::get_billing_portal_configuration_id(),
				]
			);
			return $portal_data['url'];
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not create billing portal session.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Retrieve a customer by email address.
	 *
	 * @param string $email_address Email address.
	 */
	private static function get_customer_by_email( $email_address ) {
		try {
			$stripe          = self::get_stripe_client();
			$found_customers = $stripe->customers->all( [ 'email' => $email_address ] )['data'];
			return count( $found_customers ) ? $found_customers[0] : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Get Stripe invoice.
	 *
	 * @param string $invoice_id Invoice ID.
	 */
	public static function get_invoice( $invoice_id ) {
		if ( empty( $invoice_id ) ) {
			return new \WP_Error( 'stripe_newspack', __( 'Invoice ID is missing.', 'newspack' ) );
		}
		if ( isset( self::$cache['invoices'][ $invoice_id ] ) ) {
			return self::$cache['invoices'][ $invoice_id ];
		}
		$stripe = self::get_stripe_client();
		try {
			$result                                 = $stripe->invoices->retrieve( $invoice_id, [] );
			self::$cache['invoices'][ $invoice_id ] = $result;
			return $result;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not fetch invoice.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get Stripe subscription.
	 *
	 * @param string $subscription_id Invoice ID.
	 */
	public static function get_subscription( $subscription_id ) {
		if ( isset( self::$cache['subscriptions'][ $subscription_id ] ) ) {
			return self::$cache['subscriptions'][ $subscription_id ];
		}
		$stripe = self::get_stripe_client();
		try {
			$result = $stripe->subscriptions->retrieve( $subscription_id, [] );
			self::$cache['subscriptions'][ $subscription_id ] = $result;
			return $result;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not fetch subscription.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get Stripe customer's subscriptions.
	 *
	 * @param string $customer_id Customer ID.
	 */
	public static function get_subscriptions_of_customer( $customer_id ) {
		$stripe = self::get_stripe_client();
		try {
			return $stripe->subscriptions->all(
				[
					'customer' => $customer_id,
					'limit'    => 100,
				]
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not fetch subscriptions.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get Subscription from payment, if one exists.
	 *
	 * @param array $payment Payment object.
	 */
	public static function get_subscription_from_payment( $payment ) {
		if ( $payment['invoice'] ) {
			$invoice = self::get_invoice( $payment['invoice'] );
			if ( ! \is_wp_error( $invoice ) && $invoice['subscription'] ) {
				return self::get_subscription( $invoice['subscription'] );
			}
		}
	}

	/**
	 * Cancel a Stripe subscription.
	 *
	 * @param string $subscription_id Subscription ID.
	 */
	private static function cancel_subscription( $subscription_id ) {
		$stripe = self::get_stripe_client();
		Logger::log( 'Cancelling Stripe subscription with id: ' . $subscription_id );
		try {
			return $stripe->subscriptions->cancel( $subscription_id, [] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not cancel subscription.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get balance transaction.
	 *
	 * @param string $transaction_id Transaction ID.
	 */
	public static function get_balance_transaction( $transaction_id ) {
		$stripe = self::get_stripe_client();
		try {
			return $stripe->balanceTransactions->retrieve( $transaction_id, [] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'stripe_newspack', __( 'Could not fetch balance transaction.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Format an amount with currency symbol.
	 *
	 * @param number $amount Amount.
	 * @param string $currency Currency code.
	 */
	private static function format_amount( $amount, $currency ) {
		$symbol = newspack_get_currency_symbol( strtoupper( $currency ) );
		if ( 'USD' === strtoupper( $currency ) ) {
			return $symbol . $amount;
		} else {
			return $amount . $symbol;
		}
	}

	/**
	 * Send an email to customer.
	 *
	 * @param object $customer Stripe customer.
	 * @param object $payment Stripe payment.
	 */
	public static function send_email_to_customer( $customer, $payment ) {
		$amount_normalised = self::normalise_amount( $payment['amount'], $payment['currency'] );

		// Replace content placeholders.
		$placeholders = [
			[
				'template' => '*AMOUNT*',
				'value'    => self::format_amount( $amount_normalised, $payment['currency'] ),
			],
			[
				'template' => '*DATE*',
				'value'    => gmdate( 'Y-m-d', $payment['created'] ),
			],
			[
				'template' => '*PAYMENT_METHOD*',
				'value'    => __( 'Card', 'newspack' ) . ' – ' . $payment['payment_method_details']['card']['last4'],
			],
			[
				'template' => '*RECEIPT_URL*',
				'value'    => sprintf( '<a href="%s">%s</a>', $payment['receipt_url'], 'stripe.com' ),
			],
		];

		Emails::send_email(
			Reader_Revenue_Emails::EMAIL_TYPES['RECEIPT'],
			$customer['email'],
			$placeholders
		);
	}

	/**
	 * Determine the memberhip status metadata field value.
	 *
	 * @param string $frequency Frequency of payment.
	 */
	public static function get_membership_status_field_value( $frequency ) {
		switch ( $frequency ) {
			case 'once':
				return self::ESP_METADATA_VALUES['once_donor'];
			case 'year':
				return self::ESP_METADATA_VALUES['yearly_donor'];
			case 'month':
				return self::ESP_METADATA_VALUES['monthly_donor'];
		}
	}

	/**
	 * Create metadata for a recurring payment.
	 *
	 * @param string $frequency Frequency.
	 * @param string $amount Amount.
	 * @param string $currency Currency.
	 * @param int    $date Date.
	 */
	public static function create_recurring_payment_metadata( $frequency, $amount, $currency, $date ) {
		$metadata          = [];
		$amount_normalised = self::normalise_amount( $amount, $currency );
		$payment_date      = gmdate( Newspack_Newsletters::METADATA_DATE_FORMAT, $date );
		$next_payment_date = date_format( date_add( date_create( 'now' ), date_interval_create_from_date_string( '1 ' . $frequency ) ), Newspack_Newsletters::METADATA_DATE_FORMAT );

		$metadata[ Newspack_Newsletters::get_metadata_key( 'billing_cycle' ) ]     = $frequency;
		$metadata[ Newspack_Newsletters::get_metadata_key( 'recurring_payment' ) ] = $amount_normalised;
		$metadata[ Newspack_Newsletters::get_metadata_key( 'membership_status' ) ] = self::get_membership_status_field_value( $frequency );
		$metadata[ Newspack_Newsletters::get_metadata_key( 'next_payment_date' ) ] = $next_payment_date;
		$metadata[ Newspack_Newsletters::get_metadata_key( 'sub_start_date' ) ]    = $payment_date;
		// In case this was previously set after a previous cancelled subscription, clear it.
		$metadata[ Newspack_Newsletters::get_metadata_key( 'sub_end_date' ) ] = '';
		return $metadata;
	}

	/**
	 * Get saved Stripe data.
	 */
	private static function get_saved_stripe_data() {
		$stripe_data = self::get_default_stripe_data();
		if ( Donations::is_platform_wc() ) {
			// If WC is configured, get Stripe data from WC.
			$wc_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'woocommerce' );
			$stripe_data              = $wc_configuration_manager->stripe_data();
		} else {
			// Filter out empty values, which are not booleans.
			$saved_stripe_data = array_filter(
				get_option( self::STRIPE_DATA_OPTION_NAME, [] ),
				function ( $value ) {
					if ( 'boolean' === gettype( $value ) ) {
						return true;
					} else {
						return ! empty( $value );
					}
				}
			);
			$stripe_data       = array_merge( $stripe_data, $saved_stripe_data );
		}
		$stripe_data['usedPublishableKey'] = $stripe_data['testMode'] ? $stripe_data['testPublishableKey'] : $stripe_data['publishableKey'];
		$stripe_data['usedSecretKey']      = $stripe_data['testMode'] ? $stripe_data['testSecretKey'] : $stripe_data['secretKey'];
		$stripe_data['fee_multiplier']     = get_option( 'newspack_blocks_donate_fee_multiplier', '2.9' );
		$stripe_data['fee_static']         = get_option( 'newspack_blocks_donate_fee_static', '0.3' );

		return $stripe_data;
	}

	/**
	 * Is Stripe configured?
	 */
	public static function is_configured() {
		return (bool) self::get_stripe_secret_key();
	}

	/**
	 * Get Stripe secret key.
	 */
	private static function get_stripe_secret_key() {
		$stripe_data = self::get_saved_stripe_data();
		return $stripe_data['usedSecretKey'];
	}

	/**
	 * Create a recurring donation product in Stripe.
	 *
	 * @param string $name Name.
	 * @param string $interval Interval.
	 */
	private static function create_donation_product( $name, $interval ) {
		$payment_data = self::get_stripe_data();
		$currency     = $payment_data['currency'];

		$stripe = self::get_stripe_client();
		// A price has to be assigned to a product.
		$product = $stripe->products->create( [ 'name' => $name ] );
		// Tiered volume pricing allows the subscription prices to be arbitrary.
		$price = $stripe->prices->create(
			[
				'product'        => $product['id'],
				'currency'       => $currency,
				'billing_scheme' => 'tiered',
				'tiers'          => [
					[
						'up_to'               => 1,
						'unit_amount_decimal' => 1,
					],
					[
						'up_to'               => 'inf',
						'unit_amount_decimal' => 1,
					],
				],
				'tiers_mode'     => 'volume',
				'recurring'      => [
					'interval' => $interval,
				],
				'metadata'       => [
					self::STRIPE_DONATION_PRICE_METADATA => $interval,
				],
			]
		);
		return $price;
	}

	/**
	 * List Stripe donation-related products. Products will be created if not found.
	 */
	public static function get_donation_prices() {
		try {
			$stripe        = self::get_stripe_client();
			$all_prices    = $stripe->prices->all()['data'];
			$prices_mapped = [];
			$payment_data  = self::get_stripe_data();

			foreach ( $all_prices as $price ) {
				$has_metadata_field = isset( $price['metadata'][ self::STRIPE_DONATION_PRICE_METADATA ] );
				if ( $has_metadata_field ) {
					$metadata_field_value = $price['metadata'][ self::STRIPE_DONATION_PRICE_METADATA ];
					$currency_matches     = strtolower( $payment_data['currency'] ) === strtolower( $price['currency'] );
					if ( $currency_matches && 'month' === $metadata_field_value && 'month' === $price['recurring']['interval'] ) {
						$prices_mapped['month'] = $price;
					}
					if ( $currency_matches && 'year' === $metadata_field_value && 'year' === $price['recurring']['interval'] ) {
						$prices_mapped['year'] = $price;
					}
				}
			}
			if ( ! isset( $prices_mapped['month'] ) ) {
				$prices_mapped['month'] = self::create_donation_product( __( 'Newspack Monthly Donation', 'newspack' ), 'month' );
			}
			if ( ! isset( $prices_mapped['year'] ) ) {
				$prices_mapped['year'] = self::create_donation_product( __( 'Newspack Annual Donation', 'newspack' ), 'year' );
			}
			return $prices_mapped;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'newspack_plugin_stripe', __( 'Products retrieval failed.', 'newspack' ), $e->getMessage() );
		}
	}

	/**
	 * Get Stripe client.
	 */
	public static function get_stripe_client() {
		$secret_key = self::get_stripe_secret_key();
		if ( $secret_key ) {
			return new \Stripe\StripeClient(
				[
					'api_key'        => $secret_key,
					'stripe_version' => '2022-11-15',
				]
			);
		}
	}

	/**
	 * Check connection to Stripe.
	 */
	public static function get_connection_error() {
		if ( ! self::get_stripe_secret_key() ) {
			return __( 'Stripe secret key not provided.', 'newspack' );
		}
		$stripe = self::get_stripe_client();
		try {
			$products = $stripe->products->all( [ 'limit' => 1 ] );
			return false;
		} catch ( \Throwable $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Is the currency zero-decimal?
	 *
	 * @param strin $currency Currency code.
	 * @return number Amount.
	 */
	public static function is_currency_zero_decimal( $currency ) {
		$zero_decimal_currencies = [ 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ];
		return in_array( strtoupper( $currency ), $zero_decimal_currencies, true );
	}

	/**
	 * Normalise the amount.
	 *
	 * @see get_amount
	 *
	 * @param number $amount Amount to process.
	 * @param strin  $currency Currency code.
	 * @return number Amount.
	 */
	public static function normalise_amount( $amount, $currency ) {
		if ( self::is_currency_zero_decimal( $currency ) ) {
			return $amount;
		}
		return (float) $amount / 100;
	}

	/**
	 * Process the amount for Stripe. Some currencies are zero-decimal, but for others,
	 * the amount should be multiplied by 100 (100 USD is 100*100 currency units, aka cents).
	 * https://stripe.com/docs/currencies#zero-decimal
	 *
	 * @param number $amount Amount to process.
	 * @param strin  $currency Currency code.
	 * @return number Amount.
	 */
	private static function get_amount( $amount, $currency ) {
		if ( self::is_currency_zero_decimal( $currency ) ) {
			return $amount;
		}
		return $amount * 100;
	}

	/**
	 * Get (by email) or create customer.
	 *
	 * @param object $data Customer data.
	 */
	private static function upsert_customer( $data ) {
		try {
			$stripe   = self::get_stripe_client();
			$customer = self::get_customer_by_email( $data['email'] );

			$customer_data_payload = [
				'email'       => $data['email'],
				'name'        => $data['name'],
				'description' => sprintf(
					// Translators: %s is the customer's full name.
					__( 'Name: %s, Description: Newspack Donor', 'newspack-plugin' ),
					$data['name']
				),
			];
			if ( isset( $data['metadata'] ) ) {
				$customer_data_payload['metadata'] = $data['metadata'];
			}

			if ( null === $customer ) {
				$customer = $stripe->customers->create( $customer_data_payload );
			} else {
				$customer = $stripe->customers->update(
					$customer['id'],
					$customer_data_payload
				);
			}
			return $customer;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'newspack_plugin_stripe', $e->getMessage() ?? __( 'Customer creation failed.', 'newspack' ) );
		}
	}

	/**
	 * Handle a donation in Stripe.
	 * If it's a recurring donation, a subscription will be created. Otherwise,
	 * a single charge.
	 *
	 * @param object $config Data about the donation.
	 */
	public static function handle_donation( $config ) {
		Stripe_Webhooks::validate_or_create_webhooks( true );

		$stripe_data = self::get_stripe_data();

		/**
		 * Fires at the top of the handle_donation method on the Stripe Connection class, just before it handles the donation.
		 *
		 * @param array $config Data about the donation.
		 * @param array $stripe_data Data about the Stripe connection.
		 */
		do_action( 'newspack_stripe_handle_donation_before', $config, $stripe_data );

		$response = [
			'error'  => null,
			'status' => null,
		];
		try {
			$stripe = self::get_stripe_client();

			$amount_raw      = $config['amount'];
			$frequency       = $config['frequency'];
			$email_address   = $config['email_address'];
			$full_name       = $config['full_name'];
			$source_id       = $config['source_id'];
			$client_metadata = $config['client_metadata'];

			/**
			 * Filters the payment metadata that will be sent to Stripe when a donation is made.
			 *
			 * Use this filter to add additinoal metadata to the payment. Every metadata prefixed with "newspack_" will be later automatically added
			 * as a metadata to the order or subscription when we receive the webhook request from Stripe.
			 * (Note, in the order, the metadata prefix will be "_newspack_")
			 *
			 * @param array $payment_metadata The payment metadata.
			 * @param array $config The donation configuration.
			 */
			$payment_metadata = apply_filters( 'newspack_stripe_handle_donation_payment_metadata', $config['payment_metadata'], $config );

			if ( ! isset( $client_metadata['userId'] ) && Reader_Activation::is_enabled() ) {
				$reader_metadata                        = $client_metadata;
				$reader_metadata['registration_method'] = 'stripe-donation';
				$user_id                                = Reader_Activation::register_reader( $email_address, $full_name, true, $reader_metadata );
				if ( ! \is_wp_error( $user_id ) && false !== $user_id ) {
					$client_metadata['userId'] = $user_id;
				}
			}

			$customer = self::upsert_customer(
				[
					'email'    => $email_address,
					'name'     => $full_name,
					'metadata' => $client_metadata,
				]
			);
			if ( \is_wp_error( $customer ) ) {
				$response['error'] = $customer->get_error_message();
				/**
				 * Fires at handle_donation method on the Stripe Connection class, when there's an error in the donation.
				 *
				 * @param object $config Data about the donation.
				 * @param array $stripe_data Data about the Stripe connection.
				 * @param string $error_message Error message.
				 */
				do_action( 'newspack_stripe_handle_donation_error', $config, $stripe_data, $response['error'] );
				return $response;
			}

			$is_recurring = 'once' != $frequency;

			// In this mode, WC will create a subscription for the customer, instead of using
			// native Stripe subscriptions.
			$is_wc_first_enabled = defined( 'NEWSPACK_USE_WC_SUBSCRIPTIONS_WITH_STRIPE_PLATFORM' ) && NEWSPACK_USE_WC_SUBSCRIPTIONS_WITH_STRIPE_PLATFORM;
			$is_wc_first         = $is_wc_first_enabled && Donations::is_woocommerce_suite_active();

			$payment_intent_payload = [
				'amount'      => $amount_raw,
				'customer'    => $customer['id'],
				'description' => __( 'Newspack One-Time Donation', 'newspack-blocks' ),
			];

			// To create a WC Subscription, a source is needed to make future charges,
			// because WC Stripe Gateway is source-based.

			// Mark the payment as coming from Newspack.
			$payment_metadata['origin'] = 'newspack';

			// Data for WC Stripe Gateway.
			$payment_metadata['payment_type']        = $is_recurring ? 'recurring' : 'once';
			$payment_metadata['save_payment_method'] = false;

			// Attach the source to the customer.
			$stripe->customers->createSource(
				$customer['id'],
				[
					'source' => $source_id,
				]
			);

			if ( $is_wc_first || ! $is_recurring ) {
				// Create a Payment Intent on Stripe. The client secret of this PI has to be
				// sent back to the front-end to finish processing the transaction.
				$payment_intent_payload['source'] = $source_id;
				if ( $is_recurring ) {
					// Set up the payment intent for recurring payments via WooCommerce Subscriptions.
					$payment_intent_payload['setup_future_usage'] = 'off_session';
				}
				// Default description, to be updated with order ID once it's created.
				$payment_intent_payload['description'] = __( 'Newspack Donation', 'newspack' );
				$payment_intent_payload['metadata']    = $payment_metadata;
				$payment_intent                        = self::create_payment_intent( $payment_intent_payload );

				if ( ! Emails::can_send_email( Reader_Revenue_Emails::EMAIL_TYPES['RECEIPT'] ) ) {
					// If this instance can't send the receipt email, make Stripe send the email.
					$payment_intent['receipt_email'] = $email_address;
				}

				if ( $is_wc_first ) {
					$amount_normalised = self::normalise_amount( $payment_intent['amount'], $payment_intent['currency'] );
					switch ( $config['tokenization_method'] ) {
						case 'apple_pay':
							$payment_method_title = __( 'Apple Pay (Stripe)', 'newspack' );
							break;
						case 'android_pay':
							$payment_method_title = __( 'Google Pay (Stripe)', 'newspack' );
							break;
						default:
							$payment_method_title = __( 'Credit Card (Stripe)', 'newspack' );
							break;
					}
					$wc_order_payload = [
						'status'               => 'pending',
						'email'                => $customer['email'],
						'name'                 => $customer['name'],
						'stripe_customer_id'   => $customer['id'],
						'stripe_source_id'     => $source_id,
						'stripe_intent_id'     => $payment_intent['id'],
						'payment_method_title' => $payment_method_title,
						'date'                 => $payment_intent['created'],
						'amount'               => $amount_normalised,
						'frequency'            => $frequency,
						'currency'             => strtoupper( $payment_intent['currency'] ),
						'client_id'            => $customer['metadata']['clientId'],
						'user_id'              => $customer['metadata']['userId'],
						'subscribed'           => self::has_customer_opted_in_to_newsletters( $customer ),
					];
					if ( $is_recurring ) {
						$wc_order_payload['subscription_status'] = 'created';
					}

					$wc_transaction_creation_data = WooCommerce_Connection::create_transaction( $wc_order_payload );
					$wc_order_id                  = $wc_transaction_creation_data['order_id'];
					if ( ! \is_wp_error( $wc_order_id ) ) {
						// Trigger the ESP data sync, which would normally happen on checkout.
						$payment_page_url = isset( $client_metadata['current_page_url'] ) ? $client_metadata['current_page_url'] : false;
						WooCommerce_Connection::sync_reader_from_order( $wc_order_id, false, $payment_page_url );

						$payment_intent_meta = [
							'order_id'            => $wc_transaction_creation_data['order_id'],
							'payment_type'        => 'recurring',
							'subscription_status' => 'created',
						];
						if ( $wc_transaction_creation_data['subscription_id'] ) {
							$payment_intent_meta['subscription_id'] = $wc_transaction_creation_data['subscription_id'];
						}

						// Update the metadata on the payment intent with the order ID.
						$stripe->paymentIntents->update(
							$payment_intent['id'],
							[
								'description' => WooCommerce_Connection::create_payment_description( $wc_transaction_creation_data, $frequency ),
								'metadata'    => $payment_intent_meta,
							]
						);
					}
				}

				$response['client_secret'] = $payment_intent['client_secret'];
			} else {
				// Create a Subscription on Stripe.
				$prices = self::get_donation_prices();
				$price  = $prices[ $frequency ];
				$amount = self::get_amount( $amount_raw, $price['currency'] );

				$subscription = $stripe->subscriptions->create(
					[
						'customer'         => $customer['id'],
						'items'            => [
							[
								'price'    => $price['id'],
								'quantity' => $amount,
							],
						],
						'payment_behavior' => 'allow_incomplete',
						'metadata'         => $payment_metadata,
						'expand'           => [ 'latest_invoice.payment_intent' ],
					]
				);

				// Update invoice metadata.
				$stripe->invoices->update(
					$subscription->latest_invoice['id'],
					[
						'metadata' => $payment_metadata,
					]
				);

				if ( 'incomplete' === $subscription->status ) {
					// The card may require additional authentication.
					$response['client_secret'] = $subscription->latest_invoice->payment_intent->client_secret;
				} elseif ( 'active' === $subscription->status ) {
					$response['status'] = 'success';
				}
			}
		} catch ( \Throwable $e ) {
			$response['error'] = $e->getMessage();
			/**
			 * This hook is documented above
			 */
			do_action( 'newspack_stripe_handle_donation_error', $config, $stripe_data, $response['error'] );
		}
		return $response;
	}

	/**
	 * Create a Stripe payment intent.
	 *
	 * @param object $config Data about the payment intent.
	 */
	private static function create_payment_intent( $config ) {
		$stripe       = self::get_stripe_client();
		$payment_data = self::get_stripe_data();
		$amount       = self::get_amount( $config['amount'], $payment_data['currency'] );
		$intent_data  = array_merge(
			$config,
			[
				'amount'               => $amount,
				'currency'             => $payment_data['currency'],
				'payment_method_types' => [ 'card' ],
			]
		);
		return $stripe->paymentIntents->create( $intent_data );
	}

	/**
	 * Handle Stripe's Apple Pay verification.
	 */
	public static function handle_merchant_id_file_request() {
		if ( isset( $_SERVER['REQUEST_URI'] ) ) { // WPCS: Input var okay.
			$raw_uri = sanitize_text_field(
				wp_unslash( $_SERVER['REQUEST_URI'] ) // WPCS: Input var okay.
			);
			if ( '/.well-known/apple-developer-merchantid-domain-association' === $raw_uri ) {
				$path = dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/reader-revenue/apple-developer-merchantid-domain-association';
				header( 'content-type: application/octet-stream' );
				echo file_get_contents( $path ); // phpcs:ignore
				exit;
			}
		}
	}

	/**
	 * Get site's domain.
	 */
	private static function get_site_domain() {
		return wp_parse_url( site_url() )['host'];
	}

	/**
	 * Does the connected Stripe instance have the site's domain
	 * registerd with Apple Pay?
	 */
	private static function is_apple_pay_domain_registered() {
		try {
			$stripe       = self::get_stripe_client();
			$site_domain  = self::get_site_domain();
			$found_domain = array_filter(
				$stripe->applePayDomains->all()['data'],
				function( $item ) use ( $site_domain ) {
					return $site_domain === $item->domain_name && true === $item->livemode;
				}
			);
			return 0 !== count( $found_domain );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Ensure the site's domain is registered with Stripe for Apple Pay.
	 */
	public static function register_apple_pay_domain() {
		if ( get_transient( '_newspack_is_registering_apple_pay_domain' ) ) {
			return;
		}
		try {
			$secret_key = self::get_stripe_secret_key();
			if ( 0 === stripos( $secret_key, 'sk_test' ) ) {
				// Apple Pay domains can only be added using live keys.
				return;
			}
			set_transient( '_newspack_is_registering_apple_pay_domain', true, 10 );
			$stripe = self::get_stripe_client();
			if ( $stripe ) {
				$site_domain = self::get_site_domain();
				if ( ! self::is_apple_pay_domain_registered() ) {
					$stripe->applePayDomains->create( [ 'domain_name' => $site_domain ] );
				}
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Disable WC's order complete email, if the email will be sent by this integration.
	 *
	 * @param bool $is_enabled True if enabled.
	 */
	public static function is_wc_complete_order_email_enabled( $is_enabled ) {
		if ( Donations::is_platform_stripe() && Emails::can_send_email( Reader_Revenue_Emails::EMAIL_TYPES['RECEIPT'] ) ) {
			$is_enabled = false;
		}
		return $is_enabled;
	}

	/**
	 * Handle the newspack_reader_verified hook.
	 *
	 * @param \WP_User $user   The user object.
	 */
	public static function newspack_reader_verified( $user ) {
		self::sync_customer_id( $user->user_email );
	}

	/**
	 * Lookup the customer ID for a given email address.
	 *
	 * @param string $email_address   Email address.
	 */
	private static function sync_customer_id( $email_address ) {
		if ( self::$is_looking_up_customer_id ) {
			return;
		}
		self::$is_looking_up_customer_id = true;
		if ( ! $email_address ) {
			return;
		}
		$customer = self::get_customer_by_email( $email_address );
		if ( $customer ) {
			update_user_meta( get_current_user_id(), self::STRIPE_CUSTOMER_ID_USER_META, $customer['id'] );
		}
	}

	/**
	 * Get frequency of a payment.
	 *
	 * @param array $payment Stripe payment.
	 */
	public static function get_frequency_of_payment( $payment ) {
		$frequency = 'once';
		if ( $payment['invoice'] ) {
			// A subscription payment will have an invoice.
			$invoice = self::get_invoice( $payment['invoice'] );
			if ( \is_wp_error( $invoice ) ) {
				return $frequency;
			}
			$frequency = self::get_frequency_from_invoice( $invoice );
		}
		return $frequency;
	}

	/**
	 * Get payment frequency from invoice.
	 *
	 * @param array $invoice Stripe invoice.
	 */
	public static function get_frequency_from_invoice( $invoice ) {
		$frequency = 'once';
		$recurring = $invoice['lines']['data'][0]['price']['recurring'];
		if ( isset( $recurring['interval'] ) ) {
			$frequency = $recurring['interval'];
		}
		return $frequency;
	}

	/**
	 * Has this customer opted in to receiving the newsletter?
	 *
	 * @param array $customer Stripe customer.
	 */
	public static function has_customer_opted_in_to_newsletters( $customer ) {
		return isset( $customer['metadata']['newsletterOptIn'] ) && 'true' === $customer['metadata']['newsletterOptIn'];
	}

	/**
	 * Create WC transaction payload.
	 *
	 * @param array $customer Stripe customer.
	 * @param array $payment Stripe payment.
	 */
	public static function create_wc_transaction_payload( $customer, $payment ) {
		$balance_transaction = self::get_balance_transaction( $payment['balance_transaction'] );
		$amount_normalised   = self::normalise_amount( $payment['amount'], $payment['currency'] );
		$stripe_data         = self::get_stripe_data();

		if ( isset( $payment['billing_reason'], $payment['subscription'] ) ) {
			$subscription_id        = $payment['subscription'];
			$invoice_billing_reason = $payment['billing_reason'];
		} elseif ( $payment['invoice'] ) {
			$invoice = self::get_invoice( $payment['invoice'] );
			if ( $invoice && ! \is_wp_error( $invoice ) ) {
				$invoice_billing_reason = $invoice['billing_reason'];
				if ( isset( $invoice['subscription'] ) && is_string( $invoice['subscription'] ) ) {
					$subscription_id = $invoice['subscription'];
				}
			} elseif ( \is_wp_error( $invoice ) ) {
				Logger::log( 'Invoice error: ' . $invoice->get_error_message() );
			}
		} else {
			$subscription_id        = null;
			$invoice_billing_reason = null;
		}
		if ( isset( $payment['frequency'] ) ) {
			$frequency = $payment['frequency'];
		} else {
			$frequency = self::get_frequency_of_payment( $payment );
		}
		$payload = [
			'email'                         => $customer['email'],
			'name'                          => $customer['name'],
			'stripe_id'                     => $payment['id'],
			'stripe_customer_id'            => $customer['id'],
			'stripe_fee'                    => self::normalise_amount( $balance_transaction['fee'], $payment['currency'] ),
			'stripe_net'                    => self::normalise_amount( $balance_transaction['net'], $payment['currency'] ),
			'stripe_invoice_billing_reason' => $invoice_billing_reason,
			'stripe_subscription_id'        => $subscription_id,
			'date'                          => $payment['created'],
			'amount'                        => $amount_normalised,
			'frequency'                     => $frequency,
			'currency'                      => $stripe_data['currency'],
			'client_id'                     => $customer['metadata']['clientId'],
			'user_id'                       => $customer['metadata']['userId'],
			'subscribed'                    => self::has_customer_opted_in_to_newsletters( $customer ),
			'referer'                       => $payment['referer'] ?? null,
		];

		// Add any metadata prefixed with newspack_ to the payload.
		foreach ( $payment as $key => $value ) {
			if ( 0 === strpos( $key, 'newspack_' ) ) {
				$payload[ $key ] = $value;
			}
		}

		return $payload;
	}

	/**
	 * Cancel all Stripe subscriptions of a WP user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function cancel_user_subscriptions( $user_id ) {
		$customer_id = get_user_meta( $user_id, self::STRIPE_CUSTOMER_ID_USER_META, true );
		if ( $customer_id ) {
			$subscriptions = self::get_subscriptions_of_customer( $customer_id );
			if ( \is_wp_error( $subscriptions ) ) {
				return $subscriptions;
			}
			foreach ( $subscriptions as $subscription ) {
				self::cancel_subscription( $subscription['id'] );
			}
		}
	}
}

Stripe_Connection::init();
