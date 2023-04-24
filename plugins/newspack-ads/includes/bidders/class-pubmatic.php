<?php
/**
 * Newspack Ads Bidder - PubMatic
 *
 * The required Prebid.js modules for PubMatic are included in the Newspack
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
 * Newspack Ads Bidder PubMatic Class.
 */
final class PubMatic {

	/**
	 * Register bidder and its hooks.
	 */
	public static function init() {

		// Require environment variable due to its experimental nature.
		if ( ! defined( 'NEWSPACK_ADS_EXPERIMENTAL_BIDDERS' ) ) {
			return;
		}

		\Newspack_Ads\register_bidder(
			'pubmatic',
			[
				'name'       => 'PubMatic',
				'active_key' => 'pubmatic_pid',
				'settings'   => [
					[
						'description' => __( 'PubMatic Publisher ID', 'newspack-ads' ),
						'help'        => __( 'Publisher ID provided by PubMatic.', 'newspack-ads' ),
						'key'         => 'pubmatic_pid',
						'type'        => 'string',
					],
				],
			]
		);
		add_filter( 'newspack_ads_pubmatic_ad_unit_bid', [ __CLASS__, 'set_pubmatic_ad_unit_bid' ], 10, 4 );
	}

	/**
	 * Set PubMatic bid configuration for an ad unit.
	 *
	 * Assumes bidder configuration exists, e.g. `pubmatic_platform`, since a bid
	 * shouldn't be available otherwise.
	 *
	 * @param array|null $bid                 The bid configuration.
	 * @param array      $bidder              Bidder configuration.
	 * @param string     $bidder_placement_id The bidder placement ID for this ad unit.
	 * @param array      $data                Ad unit data.
	 *
	 * @return array The bid configuration.
	 */
	public static function set_pubmatic_ad_unit_bid( $bid, $bidder, $bidder_placement_id, $data ) {
		return [
			'bidder' => 'pubmatic',
			'params' => [
				'publisherId' => $bidder['data']['pubmatic_pid'],
				'adSlot'      => $bidder_placement_id,
			],
		];
	}
}
PubMatic::init();
