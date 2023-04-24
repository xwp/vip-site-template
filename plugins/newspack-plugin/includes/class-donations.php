<?php
/**
 * Newspack Donations features management.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error, \WC_Product_Simple, \WC_Product_Subscription, \WC_Name_Your_Price_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Handles donations functionality.
 */
class Donations {
	const NEWSPACK_READER_REVENUE_PLATFORM = 'newspack_reader_revenue_platform';
	const DONATION_PRODUCT_ID_OPTION       = 'newspack_donation_product_id';
	const DONATION_PAGE_ID_OPTION          = 'newspack_donation_page_id';
	const DONATION_SETTINGS_OPTION         = 'newspack_donations_settings';
	const DONATION_BILLING_FIELDS_OPTION   = 'newspack_donations_billing_fields';
	const DONATION_ORDER_META_KEYS         = [
		'referer_tags'       => [
			'label' => 'Post Tags',
		],
		'referer_categories' => [
			'label' => 'Post Categories',
		],
		'utm_source'         => [
			'label' => 'Campaign Source',
		],
		'utm_medium'         => [
			'label' => 'Campaign Medium',
		],
		'utm_campaign'       => [
			'label' => 'Campaign Name',
		],
		'utm_term'           => [
			'label' => 'Campaign Term',
		],
		'utm_content'        => [
			'label' => 'Campaign Content',
		],
	];

	/**
	 * Donation product WC name;
	 *
	 * @var string
	 */
	private static $donation_product_name = '';

	/**
	 * Cached status of the current request - is it a WC page.
	 *
	 * @var string
	 */
	private static $is_wc_page = null;

	/**
	 * Initialize hooks/filters/etc.
	 *
	 * @codeCoverageIgnore
	 */
	public static function init() {
		self::$donation_product_name = __( 'Donate', 'newspack' );
		if ( ! is_admin() ) {
			add_action( 'wp_loaded', [ __CLASS__, 'process_donation_form' ], 99 );
			add_action( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'woocommerce_checkout_update_order_meta' ] );
			add_filter( 'woocommerce_billing_fields', [ __CLASS__, 'woocommerce_billing_fields' ] );
			add_filter( 'pre_option_woocommerce_enable_guest_checkout', [ __CLASS__, 'disable_guest_checkout' ] );
			add_action( 'woocommerce_check_cart_items', [ __CLASS__, 'handle_cart' ] );
			add_filter( 'amp_skip_post', [ __CLASS__, 'should_skip_amp' ], 10, 2 );
			add_filter( 'newspack_blocks_donate_billing_fields_keys', [ __CLASS__, 'get_billing_fields' ] );
			add_filter( 'woocommerce_thankyou_order_received_text', [ __CLASS__, 'woocommerce_thankyou_order_received_text' ], 100, 2 );
			add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'checkout_create_order_line_item' ], 10, 4 );
		}
	}

	/**
	 * Should AMP be skipped?
	 * If it's a WooCommerce page (e.g. cart), then yes, cause AMP breaks interactivity on those.
	 *
	 * @param bool $skip True if AMP should be skipped.
	 * @param int  $post_id Post ID.
	 */
	public static function should_skip_amp( $skip, $post_id ) {
		if ( null === self::$is_wc_page ) {
			$wc_pages_ids     = [
				get_option( 'woocommerce_shop_page_id' ),
				get_option( 'woocommerce_cart_page_id' ),
				get_option( 'woocommerce_checkout_page_id' ),
				get_option( 'woocommerce_myaccount_page_id' ),
			];
			self::$is_wc_page = in_array( $post_id, $wc_pages_ids );
		}
		if ( self::$is_wc_page ) {
			return true;
		}
		return $skip;
	}

	/**
	 * Check whether WooCommerce and associated extensions required for donations are active.
	 *
	 * @return bool|WP_Error True if active. WP_Error if not.
	 */
	public static function is_woocommerce_suite_active() {
		if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Subscriptions_Product' ) || ! class_exists( 'WC_Name_Your_Price_Helpers' ) ) {
			return new WP_Error(
				'newspack_missing_required_plugin',
				esc_html__( 'The required plugins are not installed and activated. Install and/or activate them to access this feature.', 'newspack' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}

		return true;
	}

	/**
	 * Get the default donation settings.
	 *
	 * @return array Array of settings info.
	 */
	private static function get_donation_default_settings() {
		return [
			'amounts'             => [
				'once'  => [ 9, 20, 90, 20 ],
				'month' => [ 7, 15, 30, 15 ],
				'year'  => [ 84, 180, 360, 180 ],
			],
			'tiered'              => false,
			'disabledFrequencies' => [
				'once'  => false,
				'month' => false,
				'year'  => false,
			],
			'platform'            => self::get_platform_slug(),
			'minimumDonation'     => 5.0,
		];
	}

	/**
	 * Get the donation currency symbol.
	 */
	private static function get_currency_symbol() {
		switch ( self::get_platform_slug() ) {
			case 'wc':
				if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
					return \get_woocommerce_currency_symbol();
				}
				break;
			case 'stripe':
				$currency = Stripe_Connection::get_stripe_data()['currency'];
				return newspack_get_currency_symbol( $currency );
			default:
				return '$';
		}
	}

	/**
	 * Get donation product ID.
	 *
	 * @param string $frequency Donation frequency of the requested product.
	 */
	public static function get_donation_product( $frequency ) {
		if ( is_wp_error( self::is_woocommerce_suite_active() ) ) {
			return false;
		}
		$is_donation_product_valid = self::validate_donation_product();
		if ( is_wp_error( $is_donation_product_valid ) ) {
			self::update_donation_product();
		}
		$product_ids = self::get_donation_product_child_products_ids();
		return isset( $product_ids[ $frequency ] ) ? $product_ids[ $frequency ] : false;
	}

	/**
	 * Check if the donation product is valid.
	 */
	private static function validate_donation_product() {
		$product_id = get_option( self::DONATION_PRODUCT_ID_OPTION, 0 );
		if ( ! $product_id ) {
			return new WP_Error(
				'newspack_donations_missing_product',
				__( 'Missing donation product. Save the donation settings to create it.', 'newspack' )
			);
		}

		$product = self::get_parent_donation_product();
		if ( ! $product ) {
			return new WP_Error(
				'newspack_donations_missing_product',
				__( 'Misconfigured donation product. Save the donation settings to fix it.', 'newspack' )
			);
		}

		$child_products_ids = self::get_donation_product_child_products_ids();
		if (
			false === $child_products_ids['once'] ||
			false === $child_products_ids['month'] ||
			false === $child_products_ids['year']
		) {
			return new WP_Error(
				'newspack_donations_missing_child_products',
				__( 'Missing donation products. Save the donation settings to fix this.', 'newspack' )
			);
		}

		return true;
	}

	/**
	 * Get the parent donation product.
	 */
	private static function get_parent_donation_product() {
		$product = \wc_get_product( get_option( self::DONATION_PRODUCT_ID_OPTION, 0 ) );
		if ( ! $product || 'grouped' !== $product->get_type() || 'trash' === $product->get_status() ) {
			return false;
		}
		return $product;
	}

	/**
	 * Get the child products of the main donation product.
	 */
	private static function get_donation_product_child_products_ids() {
		$child_products_ids = [
			'once'  => false,
			'month' => false,
			'year'  => false,
		];
		$product            = self::get_parent_donation_product();

		if ( $product ) {
			// Add the product IDs for each frequency.
			foreach ( $product->get_children() as $child_id ) {
				$child_product = wc_get_product( $child_id );
				if ( ! $child_product || 'trash' === $child_product->get_status() || ! (bool) WC_Name_Your_Price_Helpers::is_nyp( $child_id ) ) {
					continue;
				}
				if ( 'subscription' === $child_product->get_type() ) {
					if ( 'year' === $child_product->get_meta( '_subscription_period', true ) ) {
						$child_products_ids['year'] = $child_id;
					} elseif ( 'month' == $child_product->get_meta( '_subscription_period', true ) ) {
						$child_products_ids['month'] = $child_id;
					}
				} elseif ( 'simple' === $child_product->get_type() ) {
					$child_products_ids['once'] = $child_id;
				}
			}
		}

		return $child_products_ids;
	}

	/**
	 * Get the donation product ID for the order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return int|false The donation product ID or false.
	 */
	public static function get_order_donation_product_id( $order_id ) {
		$donation_products = self::get_donation_product_child_products_ids();
		if ( empty( array_filter( $donation_products ) ) ) {
			return;
		}
		$order          = new \WC_Order( $order_id );
		$order_items    = $order->get_items();
		$donation_items = array_filter(
			$order_items,
			function ( $item ) use ( $donation_products ) {
				return in_array( $item->get_product_id(), $donation_products, true );
			}
		);
		return ! empty( $donation_items ) ? array_values( $donation_items )[0]->get_product_id() : false;
	}

	/**
	 * Get the donation settings.
	 *
	 * @return Array of donation settings or WP_Error if WooCommerce is not set up.
	 */
	public static function get_donation_settings() {
		$settings                   = self::get_donation_default_settings();
		$settings['currencySymbol'] = html_entity_decode( self::get_currency_symbol() );

		$saved_settings = get_option( self::DONATION_SETTINGS_OPTION, [] );

		$legacy_settings = [];

		// Migrate legacy settings, which stored only monthly amounts.
		if ( isset( $saved_settings['suggestedAmounts'] ) ) {
			$legacy_settings['suggestedAmounts'] = $saved_settings['suggestedAmounts'];
		}
		if ( isset( $saved_settings['suggestedAmountUntiered'] ) ) {
			$legacy_settings['suggestedAmountUntiered'] = $saved_settings['suggestedAmountUntiered'];
		}

		if ( self::is_platform_wc() ) {
			$ready = self::is_woocommerce_suite_active();
			if ( is_wp_error( $ready ) ) {
				return $ready;
			}

			$is_donation_product_valid = self::validate_donation_product();
			if ( is_wp_error( $is_donation_product_valid ) ) {
				return $is_donation_product_valid;
			}

			// Migrate legacy WC settings, stored as product meta.
			$parent_product = self::get_parent_donation_product();
			if ( $parent_product ) {
				$suggested_amounts         = $parent_product->get_meta( 'newspack_donation_suggested_amount', true );
				$untiered_suggested_amount = $parent_product->get_meta( 'newspack_donation_untiered_suggested_amount', true );
				if ( $suggested_amounts ) {
					$legacy_settings['suggestedAmounts'] = $suggested_amounts;
					$parent_product->delete_meta_data( 'newspack_donation_suggested_amount' );
				}
				if ( $untiered_suggested_amount ) {
					$legacy_settings['suggestedAmountUntiered'] = $untiered_suggested_amount;
					$parent_product->delete_meta_data( 'newspack_donation_untiered_suggested_amount' );
				}
				$tiered = $parent_product->get_meta( 'newspack_donation_is_tiered', true );

				if ( ! empty( $tiered ) && is_int( intval( $tiered ) ) ) {
					$legacy_settings['tiered'] = $tiered;
					$parent_product->delete_meta_data( 'newspack_donation_is_tiered' );
				}
				$parent_product->save();
			}
		}

		if ( ! empty( $legacy_settings ) ) {
			Logger::log( 'Migrating from legacy donation settings' );
			if ( ! isset( $saved_settings['amounts'] ) ) {
				if ( isset( $legacy_settings['suggestedAmounts'] ) && is_array( $legacy_settings['suggestedAmounts'] ) ) {
					$saved_settings['amounts']['once'][0]  = $legacy_settings['suggestedAmounts'][0] * 12;
					$saved_settings['amounts']['once'][1]  = $legacy_settings['suggestedAmounts'][1] * 12;
					$saved_settings['amounts']['once'][2]  = $legacy_settings['suggestedAmounts'][2] * 12;
					$saved_settings['amounts']['month'][0] = $legacy_settings['suggestedAmounts'][0];
					$saved_settings['amounts']['month'][1] = $legacy_settings['suggestedAmounts'][1];
					$saved_settings['amounts']['month'][2] = $legacy_settings['suggestedAmounts'][2];
					$saved_settings['amounts']['year'][0]  = $legacy_settings['suggestedAmounts'][0] * 12;
					$saved_settings['amounts']['year'][1]  = $legacy_settings['suggestedAmounts'][1] * 12;
					$saved_settings['amounts']['year'][2]  = $legacy_settings['suggestedAmounts'][2] * 12;
				}
				if ( isset( $legacy_settings['suggestedAmountUntiered'] ) ) {
					$saved_settings['amounts']['once'][3]  = $legacy_settings['suggestedAmountUntiered'] * 12;
					$saved_settings['amounts']['month'][3] = $legacy_settings['suggestedAmountUntiered'];
					$saved_settings['amounts']['year'][3]  = $legacy_settings['suggestedAmountUntiered'] * 12;
				}
			}
			if ( ! isset( $saved_settings['tiered'] ) && isset( $legacy_settings['tiered'] ) ) {
				$saved_settings['tiered'] = $legacy_settings['tiered'];
			}

			if ( ! self::is_platform_wc() ) {
				// Save the migrated settings.
				self::set_donation_settings( $saved_settings );
			}
		}

		// Get only the saved settings matching keys from default settings.
		$parsed_settings = wp_parse_args( array_intersect_key( $saved_settings, $settings ), $settings );
		// Ensure amounts are numbers.
		foreach ( $parsed_settings['amounts'] as $frequency => $amounts ) {
			$parsed_settings['amounts'][ $frequency ] = array_map( 'floatval', $amounts );
		}

		// Ensure a minimum donation amount is set.
		if ( ! isset( $saved_settings['minimumDonation'] ) && self::is_platform_wc() ) {
			self::update_donation_product( [ 'minimumDonation' => $settings['minimumDonation'] ] );
		}

		$parsed_settings['platform']      = self::get_platform_slug();
		$parsed_settings['billingFields'] = self::get_billing_fields();

		return $parsed_settings;
	}

	/**
	 * Set the donation settings.
	 *
	 * @param array $args Array of settings info.
	 * @return array Updated settings.
	 */
	public static function set_donation_settings( $args ) {
		$defaults = self::get_donation_default_settings();
		// Filter incoming object with array_intersect_key, so that is contains only valid keys.
		$configuration = wp_parse_args( array_intersect_key( $args, $defaults ), $defaults );

		if ( self::is_platform_wc() ) {
			$ready = self::is_woocommerce_suite_active();
			if ( is_wp_error( $ready ) ) {
				return $ready;
			}
			self::update_donation_product( $configuration );

			// Update the billing fields.
			$billing_fields = $args['billingFields'];
			if ( ! empty( $billing_fields ) ) {
				$billing_fields = array_map( 'sanitize_text_field', $billing_fields );
				self::update_billing_fields( $billing_fields );
			}
		}

		Logger::log( 'Save donation settings' );
		update_option( self::DONATION_SETTINGS_OPTION, $configuration );
		return self::get_donation_settings();
	}

	/**
	 * Is this frequency a recurring one?
	 *
	 * @param string $frequency Frequency.
	 */
	public static function is_recurring( $frequency ) {
		return 'once' !== $frequency;
	}

	/**
	 * Map donation frequency code to a human readable string.
	 *
	 * @param string $frequency Frequency code.
	 */
	public static function get_donation_name_by_frequency( $frequency ) {
		switch ( $frequency ) {
			case 'once':
				return __( 'One-Time Donation', 'newspack' );
			case 'month':
				return __( 'Monthly Donation', 'newspack' );
			case 'year':
				return __( 'Yearly Donation', 'newspack' );
			default:
				return __( 'Donation', 'newspack' );
		}
	}

	/**
	 * Create missing donations products.
	 *
	 * @param array $args Info that will be used to create the products.
	 */
	public static function update_donation_product( $args = [] ) {
		$defaults      = self::get_donation_default_settings();
		$configuration = wp_parse_args( $args, $defaults );

		$price_tier_index = $configuration['tiered'] ? 1 : 3;

		// Parent product.
		$parent_product = self::get_parent_donation_product();
		if ( ! $parent_product ) {
			$parent_product = new \WC_Product_Grouped();
		}
		$parent_product->set_name( self::$donation_product_name );
		$parent_product->set_catalog_visibility( 'hidden' );
		$parent_product->set_virtual( true );
		$parent_product->set_downloadable( true );
		$parent_product->set_sold_individually( true );

		$child_products_ids = self::get_donation_product_child_products_ids();

		// Child products.
		foreach ( $child_products_ids as $frequency => $maybe_product_id ) {
			$price        = $configuration['amounts'][ $frequency ][ $price_tier_index ];
			$is_recurring = self::is_recurring( $frequency );

			if ( false === $maybe_product_id ) {
				$child_product = $is_recurring ? new \WC_Product_Subscription() : new \WC_Product_Simple();
			} else {
				$child_product = \wc_get_product( $maybe_product_id );
			}

			/* translators: %s: Product name */
			$product_name = sprintf( __( '%s: One-Time', 'newspack' ), self::$donation_product_name );
			if ( 'month' === $frequency ) {
				/* translators: %s: Product name */
				$product_name = sprintf( __( '%s: Monthly', 'newspack' ), self::$donation_product_name );
			}
			if ( 'year' === $frequency ) {
				/* translators: %s: Product name */
				$product_name = sprintf( __( '%s: Yearly', 'newspack' ), self::$donation_product_name );
			}

			if ( $is_recurring ) {
				$child_product->update_meta_data( '_subscription_period', $frequency );
				$child_product->update_meta_data( '_subscription_period_interval', 1 );
				$child_product->update_meta_data( '_subscription_price', wc_format_decimal( $price ) );
			}

			$child_product->set_name( $product_name );
			$child_product->set_regular_price( $price );
			$child_product->update_meta_data( '_suggested_price', $price );
			$child_product->update_meta_data( '_min_price', wc_format_decimal( $configuration['minimumDonation'] ) );
			$child_product->update_meta_data( '_hide_nyp_minimum', 'yes' );
			$child_product->update_meta_data( '_nyp', 'yes' );
			$child_product->set_virtual( true );
			$child_product->set_downloadable( true );
			$child_product->set_catalog_visibility( 'hidden' );
			$child_product->set_sold_individually( true );
			$child_product->save();

			$child_products_ids[ $frequency ] = $child_product->get_id();
		}

		$parent_product->set_children(
			[
				$child_products_ids['month'],
				$child_products_ids['year'],
				$child_products_ids['once'],
			]
		);
		$parent_product->set_status( 'publish' );
		$parent_product->save();
		update_option( self::DONATION_PRODUCT_ID_OPTION, $parent_product->get_id() );
	}

	/**
	 * Remove all donation products from the cart.
	 */
	public static function remove_donations_from_cart() {
		$donation_settings = self::get_donation_settings();
		if ( ! self::is_platform_wc() || is_wp_error( self::is_woocommerce_suite_active() ) ) {
			return;
		}

		$products = self::get_donation_product_child_products_ids();
		foreach ( \WC()->cart->get_cart() as $cart_key => $cart_item ) {
			if ( ! empty( $cart_item['product_id'] ) && in_array( $cart_item['product_id'], $products ) ) {
				\WC()->cart->remove_cart_item( $cart_key );
			}
		}
	}

	/**
	 * Get donation platform slug.
	 */
	public static function get_platform_slug() {
		return get_option( self::NEWSPACK_READER_REVENUE_PLATFORM, 'wc' );
	}

	/**
	 * Set donation platform slug.
	 *
	 * @param string $platform Platform slug.
	 */
	public static function set_platform_slug( $platform ) {
		delete_option( self::NEWSPACK_READER_REVENUE_PLATFORM );
		update_option( self::NEWSPACK_READER_REVENUE_PLATFORM, $platform, true );
	}

	/**
	 * Is NRH the donation platform?
	 */
	public static function is_platform_nrh() {
		return 'nrh' === self::get_platform_slug();
	}

	/**
	 * Is WooCommerce the donation platform?
	 */
	public static function is_platform_wc() {
		return 'wc' === self::get_platform_slug();
	}

	/**
	 * Is Stripe the donation platform?
	 */
	public static function is_platform_stripe() {
		return 'stripe' === self::get_platform_slug();
	}

	/**
	 * Is the donation platform set to 'other'?
	 */
	public static function is_platform_other() {
		return 'other' === self::get_platform_slug();
	}

	/**
	 * Handle submission of the donation form.
	 */
	public static function process_donation_form() {
		$is_wc = self::is_platform_wc();

		$donation_form_submitted = filter_input( INPUT_GET, 'newspack_donate', FILTER_SANITIZE_NUMBER_INT );
		if ( ! $donation_form_submitted || ( $is_wc && is_wp_error( self::is_woocommerce_suite_active() ) ) ) {
			return;
		}

		$is_modal_checkout = filter_input( INPUT_GET, 'modal_checkout', FILTER_SANITIZE_NUMBER_INT );

		// Parse values from the form.
		$donation_frequency = filter_input( INPUT_GET, 'donation_frequency', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! $donation_frequency ) {
			return;
		}
		$donation_value = filter_input( INPUT_GET, 'donation_value_' . $donation_frequency, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! $donation_value ) {
			$donation_value = filter_input( INPUT_GET, 'donation_value_' . $donation_frequency . '_untiered', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( ! $donation_value ) {
				return;
			}
		}
		if ( 'other' === $donation_value ) {
			$donation_value = filter_input( INPUT_GET, 'donation_value_' . $donation_frequency . '_other', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! $donation_value ) {
				return;
			}
		}

		$referer    = wp_get_referer();
		$params     = [];
		$parsed_url = wp_parse_url( $referer );

		// Get URL params appended to the referer URL.
		if ( ! empty( $parsed_url['query'] ) ) {
			wp_parse_str( $parsed_url['query'], $params );
		}

		if ( function_exists( 'wpcom_vip_url_to_postid' ) ) {
			$referer_post_id = wpcom_vip_url_to_postid( $referer );
		} else {
			$referer_post_id = url_to_postid( $referer ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
		}
		$referer_tags       = [];
		$referer_categories = [];
		$tags               = get_the_tags( $referer_post_id );
		if ( $tags && ! empty( $tags ) ) {
			$referer_tags = array_map(
				function ( $item ) {
					return $item->slug;
				},
				$tags
			);
		}
		$categories = get_the_category( $referer_post_id );
		if ( $categories && ! empty( $categories ) ) {
			$referer_categories = array_map(
				function ( $item ) {
					return $item->slug;
				},
				$categories
			);
		}

		if ( $is_wc ) {
			$products = self::get_donation_product_child_products_ids();
			// Add product to cart.
			$product_id = $products[ $donation_frequency ];
			if ( ! $product_id ) {
				return;
			}

			self::remove_donations_from_cart();

			\WC()->cart->add_to_cart(
				$product_id,
				1,
				0,
				[],
				[
					'nyp'               => (float) \WC_Name_Your_Price_Helpers::standardize_number( $donation_value ),
					'referer'           => $referer,
					'newspack_popup_id' => filter_input( INPUT_GET, 'newspack_popup_id', FILTER_SANITIZE_NUMBER_INT ),
				]
			);
		}

		$query_args = [];

		if ( ! empty( $referer_tags ) ) {
			$query_args['referer_tags'] = implode( ',', $referer_tags );
		}
		if ( ! empty( $referer_categories ) ) {
			$query_args['referer_categories'] = implode( ',', $referer_categories );
		}
		if ( $is_modal_checkout ) {
			$query_args['modal_checkout'] = 1;
		}

		// Pass through UTM params so they can be forwarded to the WooCommerce checkout flow.
		foreach ( $params as $param => $value ) {
			if ( 'utm' === substr( $param, 0, 3 ) ) {
				$param                = sanitize_text_field( $param );
				$query_args[ $param ] = sanitize_text_field( $value );
			}
		}

		$checkout_url = add_query_arg(
			$query_args,
			$is_wc ? \wc_get_page_permalink( 'checkout' ) : ''
		);

		// Redirect to checkout.
		\wp_safe_redirect( apply_filters( 'newspack_donation_checkout_url', $checkout_url, $donation_value, $donation_frequency ) );
		exit;
	}

	/**
	 * Add cart metadata to the order line item.
	 *
	 * @param \WC_Order_Item_Product $item The cart item.
	 * @param string                 $cart_item_key The cart item key.
	 * @param array                  $values The cart item values.
	 * @param \WC_Order              $order The order.
	 * @return void
	 */
	public static function checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['newspack_popup_id'] ) ) {
			$order->add_meta_data( '_newspack_popup_id', $values['newspack_popup_id'] );
		}
		if ( ! empty( $values['referer'] ) ) {
			$order->add_meta_data( '_newspack_referer', $values['referer'] );
		}
	}

	/**
	 * Create the donation page prepopulated with CTAs for the subscriptions.
	 *
	 * @return int Post ID of page.
	 */
	public static function create_donation_page() {
		$revenue_model = 'donations';

		$intro           = esc_html__( 'With the support of readers like you, we provide thoughtfully researched articles for a more informed and connected community. This is your chance to support credible, community-based, public-service journalism. Please join us!', 'newspack' );
		$content_heading = esc_html__( 'Donation', 'newspack' );
		$content         = esc_html__( "Edit and add to this content to tell your publication's story and explain the benefits of becoming a member. This is a good place to mention any special member privileges, let people know that donations are tax-deductible, or provide any legal information.", 'newspack' );

		$intro_block           = '
			<!-- wp:paragraph -->
				<p>%s</p>
			<!-- /wp:paragraph -->';
		$content_heading_block = '
			<!-- wp:heading -->
				<h2>%s</h2>
			<!-- /wp:heading -->';
		$content_block         = '
			<!-- wp:paragraph -->
				<p>%s</p>
			<!-- /wp:paragraph -->';

		$page_content = sprintf( $intro_block, $intro );
		if ( 'donations' === $revenue_model ) {
			$page_content .= self::get_donations_block();
		} elseif ( 'subscriptions' === $revenue_model ) {
			$page_content .= self::get_subscriptions_block();
		}
		$page_content .= sprintf( $content_heading_block, $content_heading );
		$page_content .= sprintf( $content_block, $content );

		$page_args = [
			'post_type'      => 'page',
			'post_title'     => __( 'Support our publication', 'newspack' ),
			'post_content'   => $page_content,
			'post_excerpt'   => __( 'Support quality journalism by joining us today!', 'newspack' ),
			'post_status'    => 'draft',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		];

		$page_id = wp_insert_post( $page_args );
		if ( is_numeric( $page_id ) ) {
			self::set_donation_page( $page_id );
			update_post_meta( $page_id, '_wp_page_template', 'single-feature.php' );
		}

		return $page_id;
	}

	/**
	 * Get raw content for a pre-populated WC featured product block featuring subscriptions.
	 *
	 * @return string Raw block content.
	 */
	protected static function get_subscriptions_block() {
		$button_text   = __( 'Join', 'newspack' );
		$subscriptions = wc_get_products(
			[
				'limit'                           => -1,
				'only_get_newspack_subscriptions' => true,
				'return'                          => 'ids',
			]
		);
		$num_products  = count( $subscriptions );
		if ( ! $num_products ) {
			return '';
		}

		$id_list = esc_attr( implode( ',', $subscriptions ) );

		$block_format = '
		<!-- wp:woocommerce/handpicked-products {"columns":%d,"editMode":false,"contentVisibility":{"title":true,"price":true,"rating":false,"button":true},"orderby":"price_asc","products":[%s]} -->
			<div class="wp-block-woocommerce-handpicked-products is-hidden-rating">[products limit="%d" columns="%d" orderby="price" order="ASC" ids="%s"]</div>
		<!-- /wp:woocommerce/handpicked-products -->';

		$block = sprintf( $block_format, $num_products, $id_list, $num_products, $num_products, $id_list );
		return $block;
	}

	/**
	 * Get raw content for a pre-populated Newspack Donations block.
	 *
	 * @return string Raw block content.
	 */
	protected static function get_donations_block() {
		$block = '<!-- wp:newspack-blocks/donate /-->';
		return $block;
	}

	/**
	 * Set the donation page.
	 *
	 * @param int $page_id The post ID of the donation page.
	 */
	protected static function set_donation_page( $page_id ) {
		update_option( self::DONATION_PAGE_ID_OPTION, $page_id );
	}

	/**
	 * Get info about the donation page.
	 *
	 * @param int $page_id Optional ID of page to get info for. Default: saved donation page.
	 * @return array|bool Array of info, or false if page is not created.
	 */
	public static function get_donation_page_info( $page_id = 0 ) {
		if ( ! $page_id ) {
			$page_id = get_option( self::DONATION_PAGE_ID_OPTION, 0 );
		}
		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			$page_id = self::create_donation_page();
		}

		return [
			'id'      => $page_id,
			'url'     => get_permalink( $page_id ),
			'editUrl' => html_entity_decode( get_edit_post_link( $page_id ) ),
			'status'  => get_post_status( $page_id ),
		];
	}

	/**
	 * Add a hidden billing fields with tags and categories.
	 *
	 * @param Array $form_fields WC form fields.
	 */
	public static function woocommerce_billing_fields( $form_fields ) {
		$params = filter_input_array( INPUT_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( is_array( $params ) ) {
			foreach ( $params as $param => $value ) {
				if ( $value && in_array( $param, array_keys( self::DONATION_ORDER_META_KEYS ) ) ) {
					$form_fields[ sanitize_text_field( $param ) ] = [
						'type'    => 'text',
						'default' => sanitize_text_field( $value ),
						'class'   => [ 'hide' ],
					];
				}
			}
		}

		return $form_fields;
	}

	/**
	 * If Reader Activation is enabled, the reader will be registered upon donation.
	 * Disable the guest checkout option in the checkout form.
	 *
	 * @param string $value Value of the guest checkout option from WC settings. Can be 'yes' or 'no'.
	 *
	 * @return string Filtered value.
	 */
	public static function disable_guest_checkout( $value ) {
		if ( Reader_Activation::is_enabled() ) {
			$value = 'no';
		}

		return $value;
	}

	/**
	 * Update WC order with the client id from hidden form field.
	 *
	 * @param String $order_id WC order id.
	 */
	public static function woocommerce_checkout_update_order_meta( $order_id ) {
		$params = filter_input_array( INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( is_array( $params ) ) {
			foreach ( $params as $param => $value ) {
				if ( in_array( $param, array_keys( self::DONATION_ORDER_META_KEYS ) ) ) {
					update_post_meta( $order_id, sanitize_text_field( $param ), sanitize_text_field( $value ) );
				}
			}
		}
	}

	/**
	 * Can Stripe platform be used?
	 *
	 * @return bool True if it can.
	 */
	public static function can_use_stripe_platform() {
		$is_amp_plugin_active = is_plugin_active( 'amp/amp.php' );
		$is_using_amp_plus    = AMP_Enhancements::is_amp_plus_configured();
		// Only if AMP plugin is not active, or site is using AMP Plus.
		return ! $is_amp_plugin_active || $is_using_amp_plus;
	}

	/**
	 * Can the streamlined donate block be used?
	 *
	 * @return bool True if it can.
	 */
	public static function can_use_streamlined_donate_block() {
		if ( self::can_use_stripe_platform() ) {
			$payment_data    = Stripe_Connection::get_stripe_data();
			$has_stripe_keys = isset( $payment_data['usedPublishableKey'], $payment_data['usedSecretKey'] ) && $payment_data['usedPublishableKey'] && $payment_data['usedSecretKey'];
			return $has_stripe_keys;
		}
		return false;
	}

	/**
	 * Whether the WC cart contains a donation product.
	 *
	 * @return bool
	 */
	public static function is_donation_cart() {
		if ( ! self::is_platform_wc() ) {
			return false;
		}
		$donation_products_ids = array_values( self::get_donation_product_child_products_ids() );
		if ( empty( $donation_products_ids ) ) {
			return false;
		}
		if ( ! WC()->cart || ! WC()->cart->cart_contents || ! is_array( WC()->cart->cart_contents ) ) {
			return false;
		}
		foreach ( WC()->cart->cart_contents as $prod_in_cart ) {
			if ( isset( $prod_in_cart['product_id'] ) && in_array( $prod_in_cart['product_id'], $donation_products_ids ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Manipulate WC's cart, if needed.
	 * If WC is not the donations platform, the donation products should not be buyable.
	 */
	public static function handle_cart() {
		if ( self::is_platform_wc() ) {
			return;
		}
		$donation_products_ids = array_values( self::get_donation_product_child_products_ids() );
		foreach ( WC()->cart->cart_contents as $prod_in_cart ) {
			if ( isset( $prod_in_cart['product_id'] ) && in_array( $prod_in_cart['product_id'], $donation_products_ids ) ) {
				WC()->cart->remove_cart_item( $prod_in_cart['key'] );
			}
		}
	}

	/**
	 * Get the checkout billing fields keys for the donation form.
	 *
	 * @return string[]
	 */
	public static function get_billing_fields() {
		$billing_fields = get_option( self::DONATION_BILLING_FIELDS_OPTION, [] );

		// If empty, return empty array so it uses the default.
		if ( empty( $billing_fields ) ) {
			return $billing_fields;
		}

		// Email is required, so it should always be in the list.
		if ( ! in_array( 'billing_email', $billing_fields, true ) ) {
			$billing_fields[] = 'billing_email';
		}

		return $billing_fields;
	}

	/**
	 * Update the checkout billing fields keys for the donation form.
	 *
	 * @param string[] $billing_fields Checkout fields keys.
	 *
	 * @return string[] Updated checkout fields keys.
	 */
	public static function update_billing_fields( $billing_fields ) {
		update_option( self::DONATION_BILLING_FIELDS_OPTION, $billing_fields );
		return $billing_fields;
	}

	/**
	 * Get the donation "thank you, order received" text.
	 *
	 * @param string $text  The text to display.
	 * @param object $order The order object.
	 *
	 * @return string
	 */
	public static function woocommerce_thankyou_order_received_text( $text, $order ) {
		if ( ! $order ) {
			return $text;
		}
		$product_id = self::get_order_donation_product_id( $order->get_id() );
		if ( ! $product_id ) {
			return $text;
		}
		return __( 'Thank you for your donation!', 'newspack-blocks' );
	}
}
Donations::init();
