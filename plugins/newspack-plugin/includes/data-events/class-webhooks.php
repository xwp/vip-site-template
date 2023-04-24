<?php
/**
 * Newspack Data Events Webhooks.
 *
 * @package Newspack
 */

namespace Newspack\Data_Events;

use Newspack\Data_Events;
use Newspack\Logger;
use WP_Error;

/**
 * Main class.
 */
final class Webhooks {

	/**
	 * Webhook request post type.
	 */
	const REQUEST_POST_TYPE = 'np_webhook_request';

	/**
	 * Webhook endpoint taxonomy.
	 */
	const ENDPOINT_TAXONOMY = 'np_webhook_endpoint';

	/**
	 * Maximum number of request retries before disabling the endpoint.
	 */
	const MAX_RETRIES = 15;

	/**
	 * Time to retain finished requests.
	 */
	const DELETE_REQUESTS_BEFORE = '7 days ago';

	/**
	 * Header to be used while logging.
	 */
	const LOGGER_HEADER = 'NEWSPACK-WEBHOOKS';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'init', [ __CLASS__, 'register_request_post_type' ] );
		\add_action( 'init', [ __CLASS__, 'register_endpoint_taxonomy' ] );
		\add_action( 'init', [ __CLASS__, 'register_cron_events' ] );
		\add_action( 'newspack_deactivation', [ __CLASS__, 'clear_cron_events' ] );
		\add_action( 'newspack_data_event_dispatch', [ __CLASS__, 'handle_dispatch' ], 10, 4 );
		\add_action( 'transition_post_status', [ __CLASS__, 'transition_post_status' ], 10, 3 );
		\add_action( 'newspack_webhooks_as_process_request', [ __CLASS__, 'process_request' ] );
	}

	/**
	 * Register custom post type for webhook requests
	 */
	public static function register_request_post_type() {
		\register_post_type(
			self::REQUEST_POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Webhook Requests', 'newspack' ),
					'singular_name' => __( 'Webhook Request', 'newspack' ),
				],
				'public'       => false,
				'show_ui'      => false,
				'show_in_menu' => false,
				'show_in_rest' => false,
				'supports'     => [
					'title',
					'custom-fields',
				],
				'taxonomies'   => [
					self::ENDPOINT_TAXONOMY,
				],
			]
		);
	}

	/**
	 * Register taxonomy for webhook endpoints
	 */
	public static function register_endpoint_taxonomy() {
		\register_taxonomy(
			self::ENDPOINT_TAXONOMY,
			self::REQUEST_POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Webhook Endpoints', 'newspack' ),
					'singular_name' => __( 'Webhook Endpoint', 'newspack' ),
				],
				'public'       => false,
				'show_ui'      => false,
				'show_in_menu' => false,
				'show_in_rest' => false,
			]
		);
	}

	/**
	 * Get cron configuration.
	 *
	 * "clear_finished": Twice a day will permanently delete finished webhook
	 * requests older than 7 days.
	 *
	 * "send_late_requests": Hourly will send webhook requests that are late.
	 *
	 * @return array Schedules keyed by the class method that should be called.
	 */
	private static function get_cron_config() {
		return [
			'clear_finished'     => 'twicedaily',
			'send_late_requests' => 'hourly',
		];
	}

	/**
	 * Register webhook cron events.
	 */
	public static function register_cron_events() {
		$config = self::get_cron_config();
		foreach ( $config as $event => $schedule ) {
			$hook = "newspack_webhooks_cron_{$event}";
			\add_action( $hook, [ __CLASS__, $event ] );
			if ( ! \wp_next_scheduled( $hook ) ) {
				\wp_schedule_event( time(), $schedule, $hook );
			}
		}
	}

	/**
	 * Clear cron events.
	 */
	public static function clear_cron_events() {
		$config = self::get_cron_config();
		foreach ( $config as $event => $schedule ) {
			$hook = "newspack_webhooks_cron_{$event}";
			\wp_unschedule_event( \wp_next_scheduled( $hook ), $hook );
		}
	}

	/**
	 * Permanently delete finished webhook requests older than
	 * "DELETE_REQUESTS_BEFORE".
	 */
	public static function clear_finished() {
		$requests = \get_posts(
			[
				'fields'         => 'ids',
				'post_type'      => self::REQUEST_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'date_query'     => [
					[
						'column' => 'post_date_gmt',
						'before' => self::DELETE_REQUESTS_BEFORE,
					],
				],
			]
		);
		foreach ( $requests as $request_id ) {
			if ( 'finished' === \get_post_meta( $request_id, 'status', true ) ) {
				\wp_delete_post( $request_id, true );
			}
		}
	}

	/**
	 * Send webhook requests that should've been sent but are still pending at
	 * 'future' status.
	 */
	public static function send_late_requests() {
		if ( Data_Events::use_action_scheduler() ) {
			return;
		}
		$requests = \get_posts(
			[
				'post_type'      => self::REQUEST_POST_TYPE,
				'post_status'    => 'future',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'date_query'     => [
					[
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s' ),
					],
				],
			]
		);
		foreach ( $requests as $request_id ) {
			\wp_publish_post( $request_id );
		}
	}

	/**
	 * Create a webhook endpoint.
	 *
	 * @param string $url     Endpoint URL.
	 * @param array  $actions Array of action names.
	 * @param bool   $global  Whether the endpoint should be triggered for all actions.
	 *
	 * @return array|WP_Error Endpoint or error.
	 */
	public static function create_endpoint( $url, $actions = [], $global = false ) {
		$endpoint = \wp_insert_term(
			$url,
			self::ENDPOINT_TAXONOMY,
			[
				'description' => $url,
			]
		);
		if ( is_wp_error( $endpoint ) ) {
			return $endpoint;
		}
		\update_term_meta( $endpoint['term_id'], 'actions', $actions );
		\update_term_meta( $endpoint['term_id'], 'global', $global );
		return self::get_endpoint( $endpoint['term_id'] );
	}

	/**
	 * Update a webhook endpoint.
	 *
	 * @param int    $id       Endpoint ID.
	 * @param string $url      Endpoint URL.
	 * @param array  $actions  Array of action names.
	 * @param bool   $global   Whether the endpoint should be triggered for all actions.
	 * @param bool   $disabled Whether the endpoint is disabled.
	 *
	 * @return array|WP_Error Endpoint or error.
	 */
	public static function update_endpoint( $id, $url, $actions = [], $global = false, $disabled = false ) {
		$endpoint = \get_term( $id, self::ENDPOINT_TAXONOMY, ARRAY_A );
		if ( ! $endpoint ) {
			return new WP_Error( 'newspack_webhooks_endpoint_not_found', __( 'Webhook endpoint not found.', 'newspack' ) );
		}
		$endpoint = \wp_update_term(
			$id,
			self::ENDPOINT_TAXONOMY,
			[
				'name'        => $url,
				'description' => $url,
			]
		);
		\update_term_meta( $endpoint['term_id'], 'actions', $actions );
		\update_term_meta( $endpoint['term_id'], 'global', $global );
		\update_term_meta( $endpoint['term_id'], 'disabled', $disabled );
		return self::get_endpoint( $endpoint['term_id'] );
	}

	/**
	 * Delete a webhook endpoint.
	 *
	 * @param int $id Endpoint ID.
	 *
	 * @return void|WP_Error Error if endpoint not found.
	 */
	public static function delete_endpoint( $id ) {
		$endpoint = get_term( $id, self::ENDPOINT_TAXONOMY );
		if ( ! $endpoint ) {
			return new WP_Error( 'newspack_webhooks_endpoint_not_found', __( 'Webhook endpoint not found.', 'newspack' ) );
		}
		\wp_delete_term( $id, self::ENDPOINT_TAXONOMY );
	}

	/**
	 * Update a webhook endpoint label.
	 *
	 * @param int    $id    Endpoint ID.
	 * @param string $label Endpoint label.
	 *
	 * @return void|WP_Error Error if endpoint not found.
	 */
	public static function update_endpoint_label( $id, $label ) {
		if ( ! get_term( $id, self::ENDPOINT_TAXONOMY ) ) {
			return new \WP_Error( 'newspack_webhooks_endpoint_not_found', __( 'Webhook endpoint not found.', 'newspack' ) );
		}
		\update_term_meta( $id, 'label', $label );
	}

	/**
	 * Disable a webhook endpoint.
	 *
	 * @param int    $id    Endpoint ID.
	 * @param string $error Error message.
	 */
	public static function disable_endpoint( $id, $error = null ) {
		$endpoint = \get_term( $id, self::ENDPOINT_TAXONOMY );
		if ( ! $endpoint ) {
			return;
		}
		\update_term_meta( $id, 'disabled', true );
		if ( $error ) {
			\update_term_meta( $id, 'disabled_error', $error );
		}
	}

	/**
	 * Get a webhook endpoint array.
	 *
	 * @param int|WP_Term $endpoint Endpoint ID or term object.
	 *
	 * @return array|WP_Error
	 */
	public static function get_endpoint( $endpoint ) {
		if ( is_int( $endpoint ) ) {
			$endpoint = \get_term( $endpoint, self::ENDPOINT_TAXONOMY );
		}
		if ( ! $endpoint || \is_wp_error( $endpoint ) ) {
			return new WP_Error( 'newspack_webhooks_endpoint_not_found', __( 'Webhook endpoint not found.', 'newspack' ) );
		}
		$disabled = (bool) \get_term_meta( $endpoint->term_id, 'disabled', true );
		return [
			'id'             => $endpoint->term_id,
			'url'            => $endpoint->name,
			'actions'        => (array) \get_term_meta( $endpoint->term_id, 'actions', true ),
			'global'         => (bool) \get_term_meta( $endpoint->term_id, 'global', true ),
			'label'          => \get_term_meta( $endpoint->term_id, 'label', true ),
			'disabled'       => $disabled,
			'disabled_error' => $disabled ? \get_term_meta( $endpoint->term_id, 'disabled_error', true ) : null,
		];
	}

	/**
	 * Get all webhook endpoints.
	 *
	 * @return array Array of endpoints.
	 */
	public static function get_endpoints() {
		$endpoints = \get_terms( self::ENDPOINT_TAXONOMY, [ 'hide_empty' => false ] );
		return array_map( [ __CLASS__, 'get_endpoint' ], $endpoints );
	}

	/**
	 * Get a request data.
	 *
	 * @param int|WP_Post $request Request ID or post object.
	 *
	 * @return array|WP_Error
	 */
	public static function get_request( $request ) {
		if ( is_int( $request ) ) {
			$request = \get_post( $request );
		}
		if ( ! $request || \is_wp_error( $request ) ) {
			return new WP_Error( 'newspack_webhooks_request_not_found', __( 'Webhook request not found.', 'newspack' ) );
		}
		$endpoint  = self::get_request_endpoint( $request->ID );
		$scheduled = (int) \get_post_meta( $request->ID, 'scheduled', true );
		if ( ! $scheduled ) {
			$scheduled = \get_post_timestamp( $request );
		}
		return [
			'id'          => $request->ID,
			'endpoint'    => $endpoint,
			'body'        => \get_post_meta( $request->ID, 'body', true ),
			'action_name' => \get_post_meta( $request->ID, 'action_name', true ),
			'status'      => \get_post_meta( $request->ID, 'status', true ),
			'errors'      => \get_post_meta( $request->ID, 'errors', true ),
			'scheduled'   => $scheduled,
		];
	}

	/**
	 * Get all webhook requests for an endpoint.
	 *
	 * This executes a potentially slow query, use with caution.
	 *
	 * @param int $endpoint_id Endpoint ID.
	 * @param int $amount      Number of requests to return. Default -1 (all).
	 *
	 * @return array Array of requests.
	 */
	public static function get_endpoint_requests( $endpoint_id, $amount = -1 ) {
		$posts = \get_posts(
			[
				'post_type'      => self::REQUEST_POST_TYPE,
				'post_status'    => [ 'publish', 'draft', 'future', 'trash' ],
				'posts_per_page' => $amount,
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => self::ENDPOINT_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $endpoint_id,
					],
				],
			]
		);
		return array_map( [ __CLASS__, 'get_request' ], $posts );
	}

	/**
	 * Get endpoints for actions.
	 *
	 * @param string $action_name Action name.
	 *
	 * @return array Array of endpoints.
	 */
	private static function get_endpoints_for_action( $action_name ) {
		$endpoints = self::get_endpoints();
		return array_values(
			array_filter(
				$endpoints,
				function( $endpoint ) use ( $action_name ) {
					return ! $endpoint['disabled'] && ( $endpoint['global'] || in_array( $action_name, $endpoint['actions'], true ) );
				}
			)
		);
	}

	/**
	 * Create webhook request.
	 *
	 * @param int    $endpoint_id Endpoint ID.
	 * @param string $action_name Action name.
	 * @param int    $timestamp   Timestamp.
	 * @param array  $data        Data.
	 * @param string $client_id   Client ID.
	 *
	 * @return int|WP_Error.
	 */
	private static function create_request( $endpoint_id, $action_name, $timestamp, $data, $client_id ) {
		$request_id = \wp_insert_post(
			[
				'post_type'  => self::REQUEST_POST_TYPE,
				'post_title' => $action_name,
			],
			true
		);

		if ( \is_wp_error( $request_id ) ) {
			Logger::error( 'Error creating webhook request: ' . $request_id->get_error_message(), self::LOGGER_HEADER );
			return $request_id;
		}

		\wp_set_object_terms( $request_id, $endpoint_id, self::ENDPOINT_TAXONOMY );

		$body = [
			'request_id' => $request_id,
			'timestamp'  => $timestamp,
			'action'     => $action_name,
			'data'       => $data,
			'client_id'  => $client_id,
		];
		\update_post_meta( $request_id, 'body', \wp_json_encode( $body ) );
		\update_post_meta( $request_id, 'action_name', $action_name );
		\update_post_meta( $request_id, 'client_id', $action_name );

		\update_post_meta( $request_id, 'status', 'pending' );

		self::schedule_request( $request_id );

		return $request_id;
	}

	/**
	 * Get webhook request errors.
	 *
	 * @param int $request_id Request ID.
	 */
	private static function get_request_errors( $request_id ) {
		$errors = \get_post_meta( $request_id, 'errors', true );
		return $errors ? $errors : [];
	}

	/**
	 * Get webhook request endpoint.
	 *
	 * @param int $request_id Request ID.
	 *
	 * @return array|null
	 */
	private static function get_request_endpoint( $request_id ) {
		$endpoint_id = \wp_get_object_terms( $request_id, self::ENDPOINT_TAXONOMY, [ 'fields' => 'ids' ] );
		$endpoint_id = $endpoint_id ? $endpoint_id[0] : null;
		if ( ! $endpoint_id ) {
			return null;
		}
		return self::get_endpoint( $endpoint_id );
	}

	/**
	 * Add webhook request error.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $error      Error message.
	 */
	private static function add_request_error( $request_id, $error ) {
		Logger::error( $error, self::LOGGER_HEADER );
		$errors = self::get_request_errors( $request_id );
		$errors = array_merge( $errors, [ $error ] );
		\update_post_meta( $request_id, 'errors', $errors );
	}

	/**
	 * Handle webhook request status transition for processing scheduled requests.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function transition_post_status( $new_status, $old_status, $post ) {
		if (
			self::REQUEST_POST_TYPE === $post->post_type &&
			'publish' === $new_status &&
			'publish' !== $old_status &&
			! Data_Events::use_action_scheduler()
		) {
			self::process_request( $post->ID );
		}
	}

	/**
	 * Kill and trash a webhook request.
	 *
	 * @param int $request_id Request ID.
	 */
	private static function kill_request( $request_id ) {
		Logger::log( "Killing request {$request_id}.", self::LOGGER_HEADER );
		\update_post_meta( $request_id, 'status', 'killed' );
		\wp_update_post(
			[
				'ID'          => $request_id,
				'post_status' => 'trash',
			]
		);
	}

	/**
	 * Schedule a webhook request.
	 *
	 * @param int $request_id Request ID.
	 * @param int $delay      Delay in minutes. Default is 1 minute.
	 */
	private static function schedule_request( $request_id, $delay = 1 ) {
		$time     = strtotime( sprintf( '+%d minutes', \absint( $delay ) ) );
		$date     = date( 'Y-m-d H:i:s', $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$date_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
		\update_post_meta( $request_id, 'scheduled', $time );
		if ( Data_Events::use_action_scheduler() ) {
			Logger::log( "Scheduling request {$request_id} for {$date_gmt} via Action Scheduler.", self::LOGGER_HEADER );
			\as_schedule_single_action( $time, 'newspack_webhooks_as_process_request', [ $request_id ], 'newspack-data-events' );
		} else {
			Logger::log( "Scheduling request {$request_id} for {$date_gmt} via scheduled post publishing.", self::LOGGER_HEADER );
			\wp_update_post(
				[
					'ID'            => $request_id,
					'post_status'   => 'future',
					'post_date'     => $date,
					'post_date_gmt' => $date_gmt,
					'edit_date'     => true,
				]
			);
		}
	}

	/**
	 * Finish a webhook request.
	 *
	 * @param int $request_id Request ID.
	 */
	public static function finish_request( $request_id ) {
		Logger::log( "Finishing request {$request_id}.", self::LOGGER_HEADER );
		\update_post_meta( $request_id, 'status', 'finished' );
		// If using ActionScheduler, manually set to publish.
		if ( Data_Events::use_action_scheduler() ) {
			\wp_publish_post( $request_id );
		}
	}

	/**
	 * Process a webhook request
	 *
	 * @param int $request_id Request ID.
	 */
	public static function process_request( $request_id ) {
		$endpoint = self::get_request_endpoint( $request_id );
		if ( ! $endpoint ) {
			self::add_request_error( $request_id, __( 'Endpoint not found.', 'newspack' ) );
			self::kill_request( $request_id );
			return;
		}
		if ( $endpoint['disabled'] ) {
			self::add_request_error( $request_id, __( 'Endpoint is disabled.', 'newspack' ) );
			self::kill_request( $request_id );
			return;
		}

		$errors = self::get_request_errors( $request_id );

		$response = self::send_request( $request_id );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			if ( count( $errors ) >= self::MAX_RETRIES ) {
				self::kill_request( $request_id );
				self::disable_endpoint( $endpoint['id'], $error_message );
				return;
			}

			self::add_request_error( $request_id, $error_message );

			// Schedule a retry with exponential backoff maxed to 12 hours.
			$delay = min( 720, pow( 2, count( $errors ) ) );
			self::schedule_request( $request_id, $delay );
		} else {
			self::finish_request( $request_id );
		}
	}

	/**
	 * Send a webhook request.
	 *
	 * @param int $request_id Request ID.
	 *
	 * @return true|WP_Error True if successful, WP_Error if not.
	 */
	private static function send_request( $request_id ) {
		Logger::log( "Sending request {$request_id}", self::LOGGER_HEADER );
		$endpoint = self::get_request_endpoint( $request_id );
		if ( ! $endpoint ) {
			return new WP_Error( 'newspack_webhooks_endpoint_not_found', __( 'Endpoint not registered', 'newspack' ) );
		}

		$url  = $endpoint['url'];
		$body = \get_post_meta( $request_id, 'body', true );

		/**
		 * Filters the timeout for webhook requests.
		 *
		 * @param int   $request_timeout Timeout in seconds. Default is 10.
		 * @param int   $request_id      Request ID.
		 * @param array $endpoint        Endpoint data.
		 */
		$timeout = apply_filters( 'newspack_webhooks_request_timeout', 10, $request_id, $endpoint );

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => $timeout,
			'body'    => $body,
		];

		$response = \wp_safe_remote_request( $url, $args );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}
		$code    = \wp_remote_retrieve_response_code( $response );
		$message = \wp_remote_retrieve_response_message( $response );
		if ( ! $code || $code < 200 || $code >= 300 ) {
			return new WP_Error( 'newspack_webhooks_request_failed', $message ?? __( 'Request failed', 'newspack' ) );
		}
		return true;
	}

	/**
	 * Create a webhook request on data event dispatch.
	 *
	 * @param string $action_name Action name.
	 * @param int    $timestamp   Event timestamp.
	 * @param array  $data        Event data.
	 * @param string $client_id   Optional user session's client ID.
	 */
	public static function handle_dispatch( $action_name, $timestamp, $data, $client_id ) {
		$endpoints = self::get_endpoints_for_action( $action_name );
		if ( empty( $endpoints ) ) {
			return;
		}

		$requests = [];
		foreach ( $endpoints as $endpoint ) {
			$requests[] = self::create_request( $endpoint['id'], $action_name, $timestamp, $data, $client_id );
		}

		/**
		 * Fires after webhook requests are created for a data event dispatch.
		 *
		 * @param int|WP_Error[] $requests Array of request IDs.
		 */
		\do_action( 'newspack_webhooks_requests_created', $requests );
	}
}
Webhooks::init();
