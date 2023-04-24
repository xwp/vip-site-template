<?php
/**
 * Google Site Kit integration class.
 *
 * @package Newspack
 */

namespace Newspack;

use Google\Site_Kit\Context;
use Google\Site_Kit\Modules\Analytics_4;
use Google\Site_Kit\Modules\Analytics_4\Settings;
use Google\Site_Kit\Core\Modules\Module;
use Google\Site_Kit\Core\Authentication\Clients\Google_Site_Kit_Client;
use Google\Site_Kit_Dependencies\Google\Service\GoogleAnalyticsAdmin as Google_Service_GoogleAnalyticsAdmin;

defined( 'ABSPATH' ) || exit;

/**
 * Class extending Site Kit's Module, in order to easily access GA data via
 * Site Kit's Analytics Admin service.
 */
class GoogleSiteKitAnalytics extends Module {
	public function register() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		return true;
	}
	public function setup_info() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		return true;
	}

	/**
	 * Set up the Analytics Admin service, so the module can use it.
	 *
	 * @param Google_Site_Kit_Client $client Google client instance.
	 */
	protected function setup_services( Google_Site_Kit_Client $client ) {
		return array(
			'analyticsadmin' => new Google_Service_GoogleAnalyticsAdmin( $client ),
		);
	}

	/**
	 * Return data needed to set up Site Kit's GA4 settings.
	 *
	 * @param string $account_id Account ID.
	 */
	public function get_ga4_settings( $account_id ) {
		$analyticsadmin      = $this->get_service( 'analyticsadmin' );
		$properties_response = $analyticsadmin->properties->listProperties(
			array(
				'filter' => 'parent:accounts/' . $account_id,
			)
		);
		// Only proceed if there is exactly one GA4 property - otherwise we don't
		// know which to pick.
		if ( 1 !== count( $properties_response['properties'] ) ) {
			return false;
		}
		$property             = $properties_response['properties'][0];
		$datastreams_response = $analyticsadmin
			->properties_dataStreams // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			->listPropertiesDataStreams(
				$property['name']
			);
		// Only proceed if there is one GA4 data stream - otherwise we don't
		// know which to pick.
		if ( 1 !== count( $datastreams_response['dataStreams'] ) ) {
			return false;
		}
		$datastream = $datastreams_response['dataStreams'][0];
		preg_match( '/\d+$/', $property['name'], $property_id_matches );
		$property_id = $property_id_matches[0];
		preg_match( '/\d+$/', $datastream['name'], $webstreamdata_id_matches );
		$webstreamdata_id = $webstreamdata_id_matches[0];
		if ( ! $property_id || ! $webstreamdata_id ) {
			return false;
		}
		return [
			'propertyID'      => $property_id,
			'webDataStreamID' => $webstreamdata_id,
			'measurementID'   => $datastream['webStreamData']['measurementId'],
		];
	}
}
