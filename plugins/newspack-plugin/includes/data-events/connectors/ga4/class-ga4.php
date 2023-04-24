<?php
/**
 * Newspack Data Events Ga4 Connector
 *
 * @package Newspack
 */

namespace Newspack\Data_Events\Connectors;

require_once 'class-event.php';

use Newspack\Data_Events;
use Newspack\Data_Events\Connectors\GA4\Event;
use Newspack\Data_Events\Popups as Popups_Events;
use Newspack\Logger;
use Newspack\Reader_Activation;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Main Class.
 */
class GA4 {

	/**
	 * The events being watched.
	 *
	 * @var array
	 */
	public static $watched_events = [
		'reader_logged_in',
		'reader_registered',
		'donation_new',
		'donation_subscription_cancelled',
		'newsletter_subscribed',
		'campaign_interaction',
	];

	/**
	 * Initialize the class and registers the handlers
	 *
	 * @return void
	 */
	public static function init() {
		Data_Events::register_handler( [ __CLASS__, 'global_handler' ] );
		add_filter( 'newspack_data_events_dispatch_body', [ __CLASS__, 'filter_event_body' ], 10, 2 );
		add_filter( 'newspack_stripe_handle_donation_payment_metadata', [ __CLASS__, 'filter_donation_metadata' ], 10, 2 );

		add_filter( 'newspack_data_events_dispatch_body', [ __CLASS__, 'filter_donation_new_event_body' ], 20, 2 );
	}

	/**
	 * Global handler for the Data Events API.
	 *
	 * @param string $event_name The event name.
	 * @param int    $timestamp Timestamp of the event.
	 * @param array  $data      Data associated with the event.
	 * @param int    $user_id   ID of the client that triggered the event. It's a RAS ID.
	 *
	 * @throws \Exception If the event is invalid.
	 * @return void
	 */
	public static function global_handler( $event_name, $timestamp, $data, $user_id ) {
		if ( ! in_array( $event_name, self::$watched_events, true ) ) {
			return;
		}

		$params    = $data['ga_params'];
		$client_id = $data['ga_client_id'];

		if ( empty( $client_id ) ) {
			throw new \Exception( 'Missing client ID' );
		}

		if ( method_exists( __CLASS__, 'handle_' . $event_name ) ) {
			$params = call_user_func( [ __CLASS__, 'handle_' . $event_name ], $params, $data );

			// prefix event name.
			$event_name = 'np_' . $event_name;

			if ( ! Event::validate_name( $event_name ) ) {
				throw new \Exception( 'Invalid event name' );
			}

			foreach ( $params as $param_name => $param_value ) {
				if ( ! Event::validate_name( $param_name ) ) {
					unset( $params[ $param_name ] );
					self::log( sprintf( 'Parameter %s has an invalid name. It was removed from the %s event.', $param_name, $event_name ) );
				}

				if ( ! Event::validate_param_value( $param_value ) ) {
					unset( $params[ $param_name ] );
					self::log( sprintf( 'Parameter %s has an invalid value. It was removed from the %s event.', $param_name, $event_name ) );
				}
			}

			if ( count( $params ) > Event::MAX_PARAMS ) {
				$discarded_params = array_slice( $params, Event::MAX_PARAMS );
				$params           = array_slice( $params, 0, Event::MAX_PARAMS );
				self::log( sprintf( 'Event %s has too many parameters. Only the first 25 were kept.', $event_name, Event::MAX_PARAMS ) );
				foreach ( array_keys( $discarded_params ) as $d_param_name ) {
					self::log( sprintf( 'Discarded parameter: %s', $d_param_name ) );
				}
			}

			$event = new Event( $event_name, $params );
			self::send_event( $event, $client_id, $timestamp, $user_id );

		} else {
			throw new \Exception( 'Event handler method not found' );
		}

	}

	/**
	 * Filters the event body before dispatching it.
	 *
	 * @param array  $body The event body.
	 * @param string $event_name The event name.
	 * @return array
	 */
	public static function filter_event_body( $body, $event_name ) {
		if ( ! in_array( $event_name, self::$watched_events, true ) ) {
			return $body;
		}

		$body['data']['ga_client_id'] = self::extract_cid_from_cookies();

		// Default params added to all events will go here.
		$body['data']['ga_params'] = [
			'logged_in' => is_user_logged_in() ? 'yes' : 'no',
		];

		$session_id = self::extract_sid_from_cookies();
		if ( $session_id ) {
			$body['data']['ga_params']['ga_session_id'] = $session_id;
		}

		$body['data']['ga_params']['is_reader'] = 'no';
		if ( is_user_logged_in() ) {
			$current_user                           = wp_get_current_user();
			$body['data']['ga_params']['is_reader'] = Reader_Activation::is_user_reader( $current_user ) ? 'yes' : 'no';
		}

		return $body;

	}

	/**
	 * When the donation_new event is dispatched from the Stripe webhook, it can't catch GA client ID from Cookies.
	 *
	 * In those cases, we rely on the metadata added to the order/subscription via the `newspack_stripe_handle_donation_payment_metadata` filter.
	 *
	 * This filter fixes both the donation_new event and the campaign_interaction with action form_submission_success that relies on this event.
	 *
	 * @param array  $body The event body.
	 * @param string $event_name The event name.
	 * @return array
	 */
	public static function filter_donation_new_event_body( $body, $event_name ) {
		if ( ! empty( $body['data']['ga_client_id'] || ( 'donation_new' !== $event_name && 'campaign_interaction' !== $event_name ) ) ) {
			return $body;
		}

		if ( 'donation_new' === $event_name ) {
			$order_id = $body['data']['platform_data']['order_id'];
		} else { // campaign_interaction.
			$order_id = $body['data']['interaction_data']['donation_order_id'] ?? false;
		}

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$ga_client_id = $order->get_meta( '_newspack_ga_client_id' );
			if ( $ga_client_id ) {
				$body['data']['ga_client_id'] = $ga_client_id;
			}
			$ga_session_id = $order->get_meta( '_newspack_ga_session_id' );
			if ( $ga_session_id ) {
				$body['data']['ga_params']['ga_session_id'] = $ga_session_id;
			}
			$logged_in = $order->get_meta( '_newspack_logged_in' );
			if ( $logged_in ) {
				$body['data']['ga_params']['logged_in'] = $logged_in;
			}
			$is_reader = $order->get_meta( '_newspack_is_reader' );
			if ( $is_reader ) {
				$body['data']['ga_params']['is_reader'] = $is_reader;
			}
		}

		return $body;

	}

	/**
	 * Handler for the reader_logged_in event.
	 *
	 * @param array $params The GA4 event parameters.
	 * @param array $data      Data associated with the Data Events api event.
	 *
	 * @return array $params The final version of the GA4 event params that will be sent to GA.
	 */
	public static function handle_reader_logged_in( $params, $data ) {
		return $params;
	}

	/**
	 * Handler for the reader_registered event.
	 *
	 * @param array $params The GA4 event parameters.
	 * @param array $data      Data associated with the Data Events api event.
	 *
	 * @return array $params The final version of the GA4 event params that will be sent to GA.
	 */
	public static function handle_reader_registered( $params, $data ) {
		$params['registration_method'] = $data['metadata']['registration_method'] ?? '';
		if ( ! empty( $data['metadata']['newspack_popup_id'] ) ) {
			$params = array_merge( $params, self::get_sanitized_popup_params( $data['metadata']['newspack_popup_id'] ) );
		}
		if ( ! empty( $data['metadata']['referer'] ) ) {
			$params['referer'] = substr( $data['metadata']['referer'], 0, 100 );
		}
		return $params;
	}

	/**
	 * Handler for the donation_new event.
	 *
	 * @param array $params The GA4 event parameters.
	 * @param array $data      Data associated with the Data Events api event.
	 *
	 * @return array $params The final version of the GA4 event params that will be sent to GA.
	 */
	public static function handle_donation_new( $params, $data ) {
		$params['amount']     = $data['amount'];
		$params['currency']   = $data['currency'];
		$params['recurrence'] = $data['recurrence'];
		$params['platform']   = $data['platform'];
		$params['referer']    = $data['referer'] ?? '';
		$params['popup_id']   = $data['popup_id'] ?? '';
		$params['range']      = self::get_donation_amount_range( $data['amount'] );
		return $params;
	}

	/**
	 * Handler for the donation_subscription_cancelled event.
	 *
	 * @param array $params The GA4 event parameters.
	 * @param array $data      Data associated with the Data Events api event.
	 *
	 * @return array $params The final version of the GA4 event params that will be sent to GA.
	 */
	public static function handle_donation_subscription_cancelled( $params, $data ) {
		$params['amount']     = $data['amount'];
		$params['currency']   = $data['currency'];
		$params['recurrence'] = $data['recurrence'];
		$params['platform']   = $data['platform'];
		$params['range']      = self::get_donation_amount_range( $data['amount'] );
		return $params;
	}

	/**
	 * Gets the value of the donation range metadata based on the donation amount.
	 *
	 * @param mixed $amount The donation amount.
	 * @return string
	 */
	public static function get_donation_amount_range( $amount ) {

		$amount = (float) $amount;

		if ( 0.0 === $amount ) {
			return '';
		} elseif ( $amount < 20 ) {
			return 'under-20';
		} elseif ( $amount < 51 ) {
			return '20-50';
		} elseif ( $amount < 101 ) {
			return '51-100';
		} elseif ( $amount < 201 ) {
			return '101-200';
		} elseif ( $amount < 501 ) {
			return '201-500';
		} else {
			return 'over-500';
		}
	}

	/**
	 * Handler for the newsletter_subscribed event.
	 *
	 * @param array $params The GA4 event parameters.
	 * @param array $data      Data associated with the Data Events api event.
	 *
	 * @return array $params The final version of the GA4 event params that will be sent to GA.
	 */
	public static function handle_newsletter_subscribed( $params, $data ) {
		$metadata = $data['contact']['metadata'] ?? [];
		if ( ! empty( $metadata['newspack_popup_id'] ) ) {
			$params = array_merge( $params, self::get_sanitized_popup_params( $metadata['newspack_popup_id'] ) );
		}
		$params['newsletters_subscription_method'] = $metadata['newsletters_subscription_method'] ?? '';
		$params['referer']                         = $metadata['current_page_url'] ?? '';

		// In case the subscription happened as part of the registration process, we should also have the registration method.
		$params['registration_method'] = $metadata['registration_method'] ?? '';

		$lists = $data['lists'];
		sort( $lists );
		$params['lists'] = implode( ',', $lists );

		return $params;
	}

	/**
	 * Handler for the campaign_interaction event.
	 *
	 * @param int   $params The GA4 event parameters.
	 * @param array $data      Data associated with the Data Events api event.
	 *
	 * @return array $params The final version of the GA4 event params that will be sent to GA.
	 */
	public static function handle_campaign_interaction( $params, $data ) {
		$transformed_data = $data;
		// remove data added in the body filter.
		unset( $transformed_data['ga_params'] );
		unset( $transformed_data['ga_client_id'] );

		$transformed_data = self::sanitize_popup_params( $transformed_data );

		return array_merge( $params, $transformed_data );
	}

	/**
	 * Sanitizes the popup params to be sent as params for GA events
	 *
	 * @param array $popup_params The popup params as they are returned by Newspack\Data_Events\Popups::get_popup_metadata and by the campaign_interaction data.
	 * @return array
	 */
	public static function sanitize_popup_params( $popup_params ) {
		// Invalid input.
		if ( ! is_array( $popup_params ) || ! isset( $popup_params['campaign_id'] ) ) {
			return [];
		}
		$santized = $popup_params;

		unset( $santized['interaction_data'] );
		$santized = array_merge( $santized, $popup_params['interaction_data'] );

		unset( $santized['campaign_blocks'] );
		foreach ( $popup_params['campaign_blocks'] as $block ) {
			$santized[ 'campaign_has_' . $block ] = 1;
		}
		return $santized;
	}

	/**
	 * Gets the santized popup params from a popup ID
	 *
	 * Ensures that the params are sanitized to be sent as GA params
	 *
	 * @param int $popup_id The popup ID.
	 * @return array
	 */
	public static function get_sanitized_popup_params( $popup_id ) {
		$popup_params = Popups_Events::get_popup_metadata( $popup_id );
		return self::sanitize_popup_params( $popup_params );
	}

	/**
	 * Gets the credentials for the GA4 API.
	 *
	 * @return array
	 */
	private static function get_credentials() {
		$api_secret     = get_option( 'ga4_api_secret' );
		$measurement_id = get_option( 'ga4_measurement_id' );
		return compact( 'api_secret', 'measurement_id' );
	}

	/**
	 * Gets the API URL for GA4
	 *
	 * @return WP_Error|string
	 */
	public static function get_api_url() {
		$credentials    = self::get_credentials();
		$api_secret     = $credentials['api_secret'];
		$measurement_id = $credentials['measurement_id'];
		if ( ! $api_secret || ! $measurement_id ) {
			return new WP_Error( 'missing_credentials', 'Missing GA4 API credentials.' );
		}
		return add_query_arg(
			[
				'api_secret'     => $api_secret,
				'measurement_id' => $measurement_id,
			],
			'https://www.google-analytics.com/mp/collect'
		);
	}

	/**
	 * Sends an event to GA4.
	 *
	 * @param Event  $event     The event object.
	 * @param string $client_id The GA client ID obtained from the cookie.
	 * @param int    $timestamp The timestamp of the event.
	 * @param string $user_id   User identifier. Use Reader_Activation::get_client_id().
	 *
	 * @throws \Exception If the credentials are missing.
	 * @return void
	 */
	private static function send_event( Event $event, $client_id, $timestamp, $user_id = null ) {

		$url = self::get_api_url();

		if ( is_wp_error( $url ) ) {
			throw new \Exception( $url->get_error_message() );
		}

		$timestamp_micros = (int) $timestamp * 1000000;

		$payload = [
			'client_id'        => $client_id,
			'timestamp_micros' => $timestamp_micros,
			'events'           => [
				$event->to_array(),
			],
		];

		if ( $user_id ) {
			$payload['user_id'] = $user_id;
		}

		wp_remote_post(
			$url,
			[
				'body' => wp_json_encode( $payload ),
			]
		);

		self::log( sprintf( 'Event sent - %s - Client ID: %s', $event->get_name(), $client_id ) );

	}

	/**
	 * Extracts the Client ID from the _ga cookie
	 *
	 * @return ?string
	 */
	private static function extract_cid_from_cookies() {
		if ( isset( $_COOKIE['_ga'] ) ) {
			$cookie_pieces = explode( '.', $_COOKIE['_ga'], 3 ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( 1 === count( $cookie_pieces ) ) {
				$cid = reset( $cookie_pieces );
			} else {
				list( $version, $domain_depth, $cid ) = $cookie_pieces;
			}
			return $cid;
		}
	}

	/**
	 * Extracts the Session ID from the _ga_{container} cookie
	 *
	 * If the cookie is not found, it will be created
	 *
	 * @return ?string
	 */
	private static function extract_sid_from_cookies() {
		foreach ( $_COOKIE as $key => $value ) { //phpcs:ignore
			if ( strpos( $key, '_ga_' ) === 0 && strpos( $value, 'GS1.' ) === 0 ) {
				$cookie_pieces = explode( '.', $value );
				if ( ! empty( $cookie_pieces[2] ) ) {
					return $cookie_pieces[2];
				}
			}
		}
	}

	/**
	 * Adds additional information about the current user and session to the payment metadata sent to Stripe
	 *
	 * @param array $payment_metadata The payment metadata.
	 * @param array $config The donation configuration.
	 * @return array
	 */
	public static function filter_donation_metadata( $payment_metadata, $config ) {
		$payment_metadata['newspack_ga_session_id'] = self::extract_sid_from_cookies();
		$payment_metadata['newspack_ga_client_id']  = self::extract_cid_from_cookies();
		$payment_metadata['newspack_logged_in']     = is_user_logged_in() ? 'yes' : 'no';
		$payment_metadata['newspack_is_reader']     = 'no';
		if ( is_user_logged_in() ) {
			$current_user                           = wp_get_current_user();
			$payment_metadata['newspack_is_reader'] = Reader_Activation::is_user_reader( $current_user ) ? 'yes' : 'no';
		}
		return $payment_metadata;
	}

	/**
	 * Log a message.
	 *
	 * @param string $message Message to log.
	 */
	private static function log( $message ) {
		Logger::log( $message, 'NEWSPACK-DATA-EVENTS-GA4' );
	}
}

if ( defined( 'NEWSPACK_EXPERIMENTAL_GA4_EVENTS' ) && NEWSPACK_EXPERIMENTAL_GA4_EVENTS ) {
	add_action( 'plugins_loaded', array( 'Newspack\Data_Events\Connectors\GA4', 'init' ) );
}
