<?php
/**
 * Mailchimp for WooCommerce integration class.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class Mailchimp_For_WooCommerce {
	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		// MC4WC fires it's hook at priority 10 so we need to move quicker.
		add_action( 'init', [ __CLASS__, 'remove_campaign_tracking_cookie' ], 9 );
	}

	/**
	 * Disable the cookie added by the Mailchimp for WooCommerce (MC4WC) plugin when on
	 * a Newspack site because the cookie causes performance issues when logged out.
	 */
	public static function remove_campaign_tracking_cookie() {
		// Don't do anything unless we know the MC4WC plugin is active.
		if ( ! \class_exists( '\MailChimp_Service' ) ) {
			return;
		}

		// Remove entirely the hook that sets the cookie.
		$service = \MailChimp_Service::instance();
		remove_action( 'init', [ $service, 'handleCampaignTracking' ] );
	}
}
Mailchimp_For_WooCommerce::init();
