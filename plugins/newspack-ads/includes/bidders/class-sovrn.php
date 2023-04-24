<?php
/**
 * Newspack Ads Bidder - Sovrn
 *
 * The required Prebid.js modules for Sovrn are included in the Newspack
 * Ads plugin. For additional partners you must recompile Prebid.js and replace
 * the `newspack-ads-prebid` enqueued script.
 *
 * See Newspack_Ads_Bidding::enqueue_scripts() and 
 * `newspack-ads/src/prebid/index.js` for additional reference.
 *
 * More information on Prebid.js: https://github.com/prebid/Prebid.js/
 *
 * @package Newspack
 */

namespace Newspack_Ads\Bidding;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Bidder Sovrn Class.
 */
final class Sovrn {

	/**
	 * Register bidder and its hooks.
	 */
	public static function init() {

		// Require environment variable due to its experimental nature.
		if ( ! defined( 'NEWSPACK_ADS_EXPERIMENTAL_BIDDERS' ) ) {
			return;
		}

		\Newspack_Ads\register_bidder(
			'sovrn',
			[ 'name' => 'Sovrn' ]
		);
		add_filter( 'newspack_ads_sovrn_ad_unit_bid', [ __CLASS__, 'set_sovrn_ad_unit_bid' ], 10, 4 );
	}

	/**
	 * Set Sovrn bid configuration for an ad unit.
	 *
	 * @param array|null $bid                 The bid configuration.
	 * @param array      $bidder              Bidder configuration.
	 * @param string     $bidder_placement_id The bidder placement ID for this ad unit.
	 * @param array      $data                Ad unit data.
	 *
	 * @return array The bid configuration.
	 */
	public static function set_sovrn_ad_unit_bid( $bid, $bidder, $bidder_placement_id, $data ) {
		return [
			'bidder' => 'sovrn',
			'params' => [
				'tagid' => $bidder_placement_id,
			],
		];
	}
}
Sovrn::init();
