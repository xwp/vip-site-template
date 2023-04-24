<?php
/**
 * WooCommerce plugin(s) configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

use \WC_Payment_Gateways, \WC_Install, \WP_Error;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of WooCommerce.
 */
class WooCommerce_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'woocommerce';

	/**
	 * Get whether the WooCommerce plugin is active and set up.
	 *
	 * @todo Actually implement this.
	 * @return bool Whether WooCommerce is active and set up.
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * Configure WooCommerce for Newspack use.
	 *
	 * @todo Actually implement this and set up Shop page, default settings, etc.
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		return true;
	}

	/**
	 * Retrieve WooCommerce country/state fields to populate Select
	 *
	 * @return Array Array of objects formatted for use in a SelectControl.
	 */
	public function country_state_fields() {
		if ( ! function_exists( 'WC' ) ) {
			return [];
		}
		$wc_countries = WC()->countries;
		if ( null == $wc_countries ) {
			return [];
		}
		$countries     = $wc_countries->get_countries();
		$states        = $wc_countries->get_states();
		$location_info = [];
		foreach ( $countries as $country_code => $country ) {
			if ( ! empty( $states[ $country_code ] ) ) {
				foreach ( $states[ $country_code ] as $state_code => $state ) {
					$location_info[] = [
						'value' => $country_code . ':' . $state_code,
						'label' => html_entity_decode( $country . ' – ' . $state ),
					];
				}
			} else {
				$location_info[] = [
					'value' => $country_code,
					'label' => html_entity_decode( $country ),
				];
			}
		}
		return $location_info;
	}

	/**
	 * Retrieve configured payment gateways.
	 *
	 * @param bool $only_enabled Whether to return only the enabled gateways.
	 * @return Array Array of payment gateways.
	 */
	public static function get_payment_gateways( $only_enabled = false ) {
		if ( ! class_exists( '\WC_Payment_Gateways' ) ) {
			return [];
		}
		$gateways = \WC_Payment_Gateways::instance()->payment_gateways();
		if ( $only_enabled ) {
			return array_filter(
				$gateways,
				function( $gateway ) {
					return 'yes' === $gateway->enabled;
				}
			);
		}
		return $gateways;
	}

	/**
	 * Retrieve Stripe data
	 *
	 * @return Array Array of Stripe data.
	 */
	public function stripe_data() {
		$gateways = self::get_payment_gateways();
		$defaults = Stripe_Connection::get_default_stripe_data();
		if ( ! isset( $gateways['stripe'] ) ) {
			return $defaults;
		}
		$stripe      = $gateways['stripe'];
		$stripe_data = [
			'enabled'            => 'yes' === $stripe->get_option( 'enabled', false ) ? true : false,
			'testMode'           => 'yes' === $stripe->get_option( 'testmode', false ) ? true : false,
			'publishableKey'     => $stripe->get_option( 'publishable_key', '' ),
			'secretKey'          => $stripe->get_option( 'secret_key', '' ),
			'testPublishableKey' => $stripe->get_option( 'test_publishable_key', '' ),
			'testSecretKey'      => $stripe->get_option( 'test_secret_key', '' ),
		];
		return \wp_parse_args( $stripe_data, $defaults );
	}

	/**
	 * Retrieve location data
	 *
	 * @return Array Array of data.
	 */
	public function location_data() {
		$default = [
			'countrystate' => '',
			'address1'     => '',
			'address2'     => '',
			'city'         => '',
			'postcode'     => '',
			'currency'     => '',
		];
		if ( ! function_exists( 'WC' ) ) {
			return $default;
		}
		$wc_countries = WC()->countries;
		if ( null == $wc_countries ) {
			return $default;
		}
		$countrystate_raw = wc_get_base_location();
		return [
			'countrystate' => ( empty( $countrystate_raw['state'] ) || '*' === $countrystate_raw['state'] ) ? $countrystate_raw['country'] : $countrystate_raw['country'] . ':' . $countrystate_raw['state'],
			'address1'     => $wc_countries->get_base_address(),
			'address2'     => $wc_countries->get_base_address_2(),
			'city'         => $wc_countries->get_base_city(),
			'postcode'     => $wc_countries->get_base_postcode(),
			'currency'     => get_woocommerce_currency(),
		];
	}

	/**
	 * Update WooCommerce Location Data
	 *
	 * @param Array $args Address data.
	 * @return Array The data that was updated.
	 */
	public function update_location( $args ) {
		isset( $args['address1'] ) ? update_option( 'woocommerce_store_address', $args['address1'] ) : null;
		isset( $args['address2'] ) ? update_option( 'woocommerce_store_address_2', $args['address2'] ) : null;
		isset( $args['city'] ) ? update_option( 'woocommerce_store_city', $args['city'] ) : null;
		isset( $args['countrystate'] ) ? update_option( 'woocommerce_default_country', $args['countrystate'] ) : null;
		isset( $args['postcode'] ) ? update_option( 'woocommerce_store_postcode', $args['postcode'] ) : null;
		isset( $args['currency'] ) ? update_option( 'woocommerce_currency', $args['currency'] ) : null;
		return true;
	}

	/**
	 * Update WooCommerce Stripe settings
	 *
	 * @param Array $args Address data.
	 * @return Array|WP_Error The data that was updated or an error.
	 */
	public function update_wc_stripe_settings( $args ) {
		$gateways = WC_Payment_Gateways::instance()->payment_gateways();
		if ( ! isset( $gateways['stripe'] ) ) {
			if ( $args['enabled'] ) {
				// Stripe is not installed and we want to use it. Install/Activate/Initialize it.
				Plugin_Manager::activate( 'woocommerce-gateway-stripe' );
				do_action( 'plugins_loaded' );
				WC_Payment_Gateways::instance()->init();
				$gateways = WC_Payment_Gateways::instance()->payment_gateways();
			} else {
				// Stripe is not installed and we don't want to use it. No settings needed.
				return true;
			}
		}

		$stripe = $gateways['stripe'];
		$stripe->update_option( 'enabled', $args['enabled'] ? 'yes' : 'no' );
		$stripe->update_option( 'testmode', $args['testMode'] ? 'yes' : 'no' );
		$stripe->update_option( 'publishable_key', $args['publishableKey'] );
		$stripe->update_option( 'secret_key', $args['secretKey'] );
		$stripe->update_option( 'test_publishable_key', $args['testPublishableKey'] );
		$stripe->update_option( 'test_secret_key', $args['testSecretKey'] );

		// @todo when is the best time to do this?
		$this->set_smart_defaults();

		return true;
	}

	/**
	 * Set general settings that our users will want (e.g. no reason for product reviews on a news membership).
	 */
	public function set_smart_defaults() {
		// Create Shop, My Account, etc. pages if not already created.
		WC_Install::create_pages();

		// Disable coupons and reviews.
		update_option( 'woocommerce_enable_coupons', 'no' );
		update_option( 'woocommerce_enable_reviews', 'no' );

		// Enables checkout without login.
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
	}
}
