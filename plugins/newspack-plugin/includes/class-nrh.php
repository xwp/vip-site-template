<?php
/**
 * Newspack News Revenue Hub feature management.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Salesforce functionality.
 */
class NRH {
	/**
	 * Add hooks.
	 */
	public static function init() {
		add_filter( 'newspack_donation_checkout_url', [ __CLASS__, 'redirect_to_nrh_checkout' ], 10, 3 );
		add_filter( 'newspack_blocks_donate_block_html', [ __CLASS__, 'handle_custom_campaign_id' ], 10, 2 );
		add_filter( 'allowed_redirect_hosts', [ __CLASS__, 'allow_redirect_to_nrh' ] );
	}

	/**
	 * Redirect to the NRH checkout page when the donation form is submitted if possible.
	 *
	 * @param string $checkout_url URL of checkout page.
	 * @param float  $donation_value Amount of donation.
	 * @param string $donation_frequency 'month', 'year', or 'once'.
	 * @return string Modified $checkout_url.
	 */
	public static function redirect_to_nrh_checkout( $checkout_url, $donation_value, $donation_frequency ) {
		// Mapping of Newspack -> NRH donation frequencies.
		$donation_frequencies = [
			'month' => 'monthly',
			'year'  => 'yearly',
			'once'  => 'once',
		];

		$nrh_config = get_option( NEWSPACK_NRH_CONFIG );

		$organization_id = wp_strip_all_tags( $nrh_config['nrh_organization_id'] );
		$salesforce_id   = false;
		if ( isset( $nrh_config['nrh_salesforce_campaign_id'] ) ) {
			$salesforce_id = wp_strip_all_tags( $nrh_config['nrh_salesforce_campaign_id'] );
		}
		$custom_campaign = filter_input( INPUT_GET, 'campaign', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( $custom_campaign ) {
			$salesforce_id = wp_strip_all_tags( $custom_campaign );
		}
		$donation_value     = floatval( $donation_value );
		$donation_frequency = isset( $donation_frequencies[ $donation_frequency ] ) ? $donation_frequencies[ $donation_frequency ] : false;

		if ( empty( $organization_id ) || empty( $donation_value ) || empty( $donation_frequency ) ) {
			return $checkout_url;
		}

		// Remove donations from the cart since the customer won't be checking out on site.
		Donations::remove_donations_from_cart();

		$url = sprintf(
			'https://checkout.fundjournalism.org/memberform?amount=%.1f&installmentPeriod=%s&org_id=%s',
			$donation_value,
			$donation_frequency,
			$organization_id
		);

		if ( ! empty( $salesforce_id ) ) {
			$url .= '&campaign=' . esc_attr( $salesforce_id );
		}

		return $url;
	}

	/**
	 * Add a hidden campaign input when a custom campaign is present in the GET request.
	 *
	 * @param string $html The donate form html.
	 * @param array  $attributes Block attributes.
	 * @return string modified $html.
	 */
	public static function handle_custom_campaign_id( $html, $attributes ) {
		// Don't add a global campaign ID if there is already a campaign ID.
		if ( stripos( $html, "name='campaign'" ) || stripos( $html, 'name="campaign"' ) ) {
			return $html;
		}

		$custom_campaign = filter_input( INPUT_GET, 'campaign', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! $custom_campaign ) {
			return $html;
		}

		$custom_campaign = '<input type="hidden" name="campaign" value="' . esc_attr( $custom_campaign ) . '"/>';
		$html            = str_replace( '</form>', $custom_campaign . '</form>', $html );
		return $html;
	}

	/**
	 * Add the NRH checkout URL as an allowed redirect target.
	 *
	 * @param array $hosts Array of allowed hosts.
	 * @return array Modified $hosts.
	 */
	public static function allow_redirect_to_nrh( $hosts ) {
		$hosts[] = 'checkout.fundjournalism.org';
		return $hosts;
	}

	/**
	 * Get NRH config.
	 */
	public static function get_nrh_config() {
		return get_option( NEWSPACK_NRH_CONFIG, [] );
	}
}
NRH::init();
