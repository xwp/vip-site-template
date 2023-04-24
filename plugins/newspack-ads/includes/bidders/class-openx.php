<?php
/**
 * Newspack Ads Bidder - OpenX
 *
 * The required Prebid.js modules for OpenX are included in the Newspack
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
 * Newspack Ads Bidder OpenX Class.
 */
final class OpenX {

	/**
	 * Register bidder and its hooks.
	 */
	public static function init() {

		// Require environment variable due to its experimental nature.
		if ( ! defined( 'NEWSPACK_ADS_EXPERIMENTAL_BIDDERS' ) ) {
			return;
		}

		\Newspack_Ads\register_bidder(
			'openx',
			[
				'name'       => 'OpenX',
				'active_key' => 'openx_platform',
				'settings'   => [
					[
						'description' => __( 'OpenX Platform ID', 'newspack-ads' ),
						'help'        => __( 'Platform id provided by your OpenX representative. E.g.: 555not5a-real-plat-form-id0123456789', 'newspack-ads' ),
						'key'         => 'openx_platform',
						'type'        => 'string',
					],
				],
			]
		);
		add_filter( 'newspack_ads_prebid_config', [ __CLASS__, 'add_user_sync_iframe' ] );
		add_filter( 'newspack_ads_openx_ad_unit_bid', [ __CLASS__, 'set_openx_ad_unit_bid' ], 10, 4 );
	}

	/**
	 * Enable user syncing through iframes.
	 *
	 * OpenX strongly recommends enabling user syncing through iframes. This
	 * functionality improves DSP user match rates and increases the OpenX bid
	 * rate and bid price.
	 *
	 * @link https://docs.prebid.org/dev-docs/bidders/openx.html
	 *
	 * @param array $config Prebid.js config.
	 *
	 * @return array Prebid.js config.
	 */
	public static function add_user_sync_iframe( $config ) {
		$bidder_config = \Newspack_Ads\get_bidder( 'openx' );
		if ( ! $bidder_config || ! isset( $bidder_config['data']['openx_platform'] ) || empty( $bidder_config['data']['openx_platform'] ) ) {
			return $config;
		}
		$config['userSync']['iframeEnabled'] = true;
		return $config;
	}

	/**
	 * Set OpenX bid configuration for an ad unit.
	 *
	 * Assumes bidder configuration exists, e.g. `openx_platform`, since a bid
	 * shouldn't be available otherwise.
	 *
	 * @param array|null $bid                 The bid configuration.
	 * @param array      $bidder              Bidder configuration.
	 * @param string     $bidder_placement_id The bidder placement ID for this ad unit.
	 * @param array      $data                Ad unit data.
	 *
	 * @return array The bid configuration.
	 */
	public static function set_openx_ad_unit_bid( $bid, $bidder, $bidder_placement_id, $data ) {
		return [
			'bidder' => 'openx',
			'params' => [
				'platform' => $bidder['data']['openx_platform'],
				'unit'     => $bidder_placement_id,
			],
		];
	}
}
OpenX::init();
