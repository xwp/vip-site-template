<?php
/**
 * Connection to Google services.
 *
 * @package Newspack
 */

namespace Newspack;

use Google\Site_Kit\Context;
use Google\Site_Kit\Core\Storage\Options;
use Google\Site_Kit\Core\Storage\User_Options;
use Google\Site_Kit\Core\Authentication\Authentication;
use Google\Site_Kit\Modules\Analytics as SiteKitAnalytics;
use Google\Auth\Credentials\UserRefreshCredentials;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class Google_Services_Connection {
	/**
	 * Get Site Kit's authentication.
	 *
	 * @codeCoverageIgnore
	 * @return Authentication Site Kit's authentication.
	 */
	public static function get_site_kit_authentication() {
		if ( defined( 'GOOGLESITEKIT_PLUGIN_MAIN_FILE' ) ) {
			$context        = new Context( GOOGLESITEKIT_PLUGIN_MAIN_FILE );
			$authentication = new Authentication( $context, new Options( $context ), new User_Options( $context ) );
			return $authentication;
		} else {
			return new WP_Error( 'newspack_sitekit_disconnected', __( 'Connect Site Kit plugin to view Campaign Analytics.', 'newspack' ) );
		}
	}

	/**
	 * Get OAuth2 client.
	 * More at https://googleapis.github.io/google-auth-library-php/master/Google/Auth/OAuth2.html.
	 *
	 * @codeCoverageIgnore
	 * @return object Google OAuth2 client.
	 */
	public static function get_site_kit_oauth_client() {
		return self::get_site_kit_authentication()->get_oauth_client();
	}

	/**
	 * Get Site Kit's Analytics module.
	 *
	 * @codeCoverageIgnore
	 * @return object Google OAuth2 client.
	 */
	public static function get_site_kit_analytics_module() {
		if ( defined( 'GOOGLESITEKIT_PLUGIN_MAIN_FILE' ) ) {
			$context = new Context( GOOGLESITEKIT_PLUGIN_MAIN_FILE );
			return new SiteKitAnalytics( $context );
		}
	}

	/**
	 * Get OAuth2 credentials.
	 *
	 * @return UserRefreshCredentials Credentials to make requests with.
	 */
	public static function get_oauth2_credentials() {
		return \Newspack\Google_OAuth::get_oauth2_credentials();
	}

	/**
	 * Send a custom event to GA.
	 *
	 * @param array $event_spec Event details.
	 */
	public static function send_custom_event( $event_spec ) {
		Logger::log( 'Sending custom event of category "' . $event_spec['category'] . '" to GA.' );
		try {
			$analytics = self::get_site_kit_analytics_module();
			if ( $analytics->is_connected() ) {
				$tracking_id        = $analytics->get_settings()->get()['propertyID'];
				$analytics_ping_url = 'https://www.google-analytics.com/collect';

				// Params docs: https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters.
				$analytics_ping_params = array(
					'v'   => 1,
					'tid' => $tracking_id, // Tracking ID/ Web Property ID.
					't'   => 'event', // Hit type.
					'an'  => 'Newspack', // Application Name.
					'ec'  => $event_spec['category'], // Event Category.
					'ea'  => $event_spec['action'], // Event Action.
				);

				// Client ID.
				if ( isset( $event_spec['cid'] ) ) {
					$analytics_ping_params['cid'] = $event_spec['cid'];
				} elseif ( isset( $_COOKIE['_ga'] ) ) {
					$cookie_pieces = explode( '.', $_COOKIE['_ga'], 3 ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					if ( 1 === count( $cookie_pieces ) ) {
						$cid = reset( $cookie_pieces );
					} else {
						list( $version, $domain_depth, $cid ) = $cookie_pieces;
					}
					$analytics_ping_params['cid'] = $cid;
				} else {
					$analytics_ping_params['cid'] = '555'; // Anonymous client.
				}

				if ( isset( $event_spec['label'] ) ) {
					$analytics_ping_params['el'] = $event_spec['label']; // Event label.
				}
				if ( isset( $event_spec['value'] ) ) {
					$analytics_ping_params['ev'] = $event_spec['value']; // Event value.
				}
				if ( isset( $event_spec['referer'] ) ) {
					$analytics_ping_params['dr'] = $event_spec['referer']; // Document Referrer.
				}
				if ( isset( $event_spec['host'] ) ) {
					$analytics_ping_params['dh'] = $event_spec['host']; // Document Host.
				}
				if ( isset( $event_spec['path'] ) ) {
					$analytics_ping_params['dp'] = $event_spec['path']; // Document Page.
				}

				$ga_url = $analytics_ping_url . '?' . http_build_query( $analytics_ping_params );
				if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
					return vip_safe_wp_remote_get( $ga_url );
				} else {
					return wp_remote_get( $ga_url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
				}
			}
		} catch ( \Throwable $th ) {
			Logger::error( 'Failed sending custom event to GA: ' . $th->getMessage() );
		}
	}
}
