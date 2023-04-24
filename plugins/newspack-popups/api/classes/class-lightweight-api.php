<?php
/**
 * Newspack Campaigns lightweight API.
 *
 * @package Newspack
 */

/**
 * Create the base Lightweight_API class.
 */
require_once dirname( __FILE__ ) . '/../segmentation/class-segmentation-report.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use \DrewM\MailChimp\MailChimp;

/**
 * API endpoints.
 */
class Lightweight_API {
	/**
	 * Cache version so we can force a cache rebuild for reader data.
	 *
	 * @var reader_cache_version
	 */
	public $reader_cache_version = '1.0';

	/**
	 * Response object.
	 *
	 * @var response
	 */
	public $response = [];

	/**
	 * Debugging info.
	 *
	 * @var debug
	 */
	public $debug;

	/**
	 * If true, get all reader events from the DB instead of the object cache.
	 *
	 * @var ignore_cache
	 */
	public $ignore_cache = false;

	/**
	 * Default reader.
	 *
	 * @var reader_blueprint
	 */
	public $reader_blueprint = [
		'date_created'  => null,
		'date_modified' => null,
		'reader_data'   => [],
		'is_preview'    => false,
	];

	/**
	 * Default reader event item.
	 *
	 * @var reader_events_blueprint
	 */
	public $reader_events_blueprint = [
		'date_created' => null,
		'type'         => null,
		'context'      => null,
		'value'        => null,
	];

	/**
	 * Constructor.
	 *
	 * @param string|null $nonce If API is being instantiated directly by WP, it needs nonce verification.
	 * @param boolean     $ignore_referer_validation If API is being instantiated directly by WP, it does not need to check referer.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct( $nonce = null, $ignore_referer_validation = false ) {
		if ( $this->is_a_web_crawler() && ! $ignore_referer_validation ) {
			header( 'X-Robots-Tag: noindex' );
			exit;
		}
		if ( ! $this->verify_referer( $nonce ) && ! $ignore_referer_validation ) {
			$this->error( 'invalid_referer' );
		}
		if ( $this->is_debug_enabled() ) {
			$this->debug = [
				'read_query_count'   => 0,
				'write_query_count'  => 0,
				'delete_query_count' => 0,
				'cache_count'        => 0,
				'start_time'         => microtime( true ),
				'end_time'           => null,
				'duration'           => null,
				'suppression'        => [],
				'reader'             => null,
				'reader_events'      => [],
				'cache_is_valid'     => false,
			];
		}

		// If we don't have a persistent object cache, we can't rely on it across page views.
		if ( ! file_exists( WP_CONTENT_DIR . '/object-cache.php' ) || ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) ) {
			$this->ignore_cache = true;
		}

		if ( $this->is_cache_disabled() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->ignore_cache     = true;
			$this->debug['nocache'] = true;
		}
	}

	/**
	 * Whether debug mode is enabled via newspack-popups-config.php or URL param.
	 *
	 * @return boolean
	 */
	public function is_debug_enabled() {
		return isset( $_REQUEST['debug'] ) || ( defined( 'NEWSPACK_POPUPS_DEBUG' ) && NEWSPACK_POPUPS_DEBUG ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether caching is disabled via newspack-popups-config.php or URL param.
	 *
	 * @return boolean
	 */
	public function is_cache_disabled() {
		return isset( $_REQUEST['nocache'] ) || ( defined( 'NEWSPACK_POPUPS_NOCACHE' ) && NEWSPACK_POPUPS_NOCACHE ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Is the request coming from a common web crawler?
	 */
	public function is_a_web_crawler() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
			return false;
		}
		$user_agent = $_SERVER['HTTP_USER_AGENT']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		// https://www.keycdn.com/blog/web-crawlers.
		$common_web_crawlers_user_agents = [
			'Googlebot',
			'Bingbot',
			'Slurp',
			'DuckDuckBot',
			'Baiduspider',
			'YandexBot',
			'facebot',
			'ia_archiver',
		];
		foreach ( $common_web_crawlers_user_agents as $crawler_agent ) {
			if ( stristr( $user_agent, $crawler_agent ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Verify referer is valid.
	 *
	 * @param string|null $nonce If API is being instantiated directly by WP, it needs nonce verification.
	 *
	 * @return boolean|void True if the nonce or referer is valid; otherwise kill the request.
	 */
	public function verify_referer( $nonce = null ) {
		if ( $nonce ) {
			return \wp_verify_nonce( $nonce, 'newspack_campaigns_lightweight_api' );
		}

		$http_referer = ! empty( $_SERVER['HTTP_REFERER'] ) ? parse_url( $_SERVER['HTTP_REFERER'] , PHP_URL_HOST ) : null; // phpcs:ignore
		$valid_referers = [
			$http_referer,
		];
		$server_name = ! empty( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : null; // phpcs:ignore
		// Enable requests from AMP Cache.
		if ( preg_match( '/\.cdn\.ampproject\.org/', $http_referer ) ) {
			return true;
		}
		return ! empty( $http_referer ) && ! empty( $server_name ) && in_array( strtolower( $server_name ), $valid_referers, true );
	}

	/**
	 * Complete the API and print response.
	 *
	 * @codeCoverageIgnore
	 */
	public function respond() {
		if ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) {
			return;
		}
		if ( $this->is_debug_enabled() ) {
			$this->debug['end_time'] = microtime( true );
			$this->debug['duration'] = $this->debug['end_time'] - $this->debug['start_time'];
			$this->response['debug'] = $this->debug;
		}
		header( 'Access-Control-Allow-Origin: https://' . parse_url( $_SERVER['HTTP_REFERER'] )['host'], false ); // phpcs:ignore
		header( 'Access-Control-Allow-Credentials: true', false );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		http_response_code( 200 );
		print json_encode( $this->response ); // phpcs:ignore
		exit;
	}

	/**
	 * Return a 400 code error.
	 *
	 * @param string $code The error code.
	 */
	public function error( $code ) {
		// Exiting in test env would report the test as passed.
		if ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) {
			return;
		}

		http_response_code( 400 );
		print json_encode( [ 'error' => $code ] ); // phpcs:ignore
		exit;
	}

	/**
	 * Get data from transient.
	 *
	 * @param string $name The transient's name.
	 */
	public function get_transient_legacy( $name ) {
		global $wpdb;
		$name         = '_transient_' . $name;
		$table_name   = Segmentation::get_readers_table_name_legacy();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) == $table_name; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Bail if legacy table doesn't exist.
		if ( ! $table_exists ) {
			return null;
		}

		if ( $this->is_debug_enabled() ) {
			$this->debug['read_query_count'] += 1;
		}

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$table_name` WHERE option_name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $value ? maybe_unserialize( $value ) : null;
	}

	/**
	 * Delete transient.
	 *
	 * @param string $name The transient's name.
	 */
	public function delete_transient_legacy( $name ) {
		global $wpdb;
		$name       = '_transient_' . $name;
		$table_name = Segmentation::get_readers_table_name_legacy();

		wp_cache_delete( $name );
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table_name,
			[ 'option_name' => $name ]
		);

		if ( $this->is_debug_enabled() ) {
			$this->debug['delete_query_count'] += 1;
		}
	}

	/**
	 * Determine if the given client ID originated in a preview.
	 *
	 * @param string $client_id Client ID to check.
	 *
	 * @return boolean True if a preview.
	 */
	public function is_preview( $client_id ) {
		return 'preview' === substr( $client_id, 0, 7 );
	}

	/**
	 * Upsert multiple rows in one DB transaction.
	 *
	 * @param array $events Array of reader events to save.
	 *
	 * @return boolean Result of the write query: true if successful, otherwise false.
	 */
	public function bulk_db_insert( $events ) {
		$write_result = false;

		if ( 0 === count( $events ) ) {
			return $write_result;
		}

		global $wpdb;
		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$query                    = $this->get_sql( $reader_events_table_name, $events );

		// Write to the DB.
		$write_result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( $this->is_debug_enabled() ) {
			$this->debug['write_query_count'] += 1;

			if ( ! $write_result ) {
				$this->debug['write_error'] = "Error writing to $reader_events_table_name.";
			}
		}

		return $write_result;
	}

	/**
	 * Parse event logs to the DB.
	 */
	public function parse_event_logs() {

		global $wpdb;

		if ( ! file_exists( Segmentation::get_log_file_path() ) ) {
			return;
		}

		$log_file = fopen( Segmentation::get_log_file_path(), 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( flock( $log_file, LOCK_EX ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_flock
			$lines = [];

			while ( ! feof( $log_file ) ) {
				$line = trim( fgets( $log_file ) );
				if ( ! empty( $line ) ) {
					$lines[] = $line;
				}
			}

			$lines  = array_unique( $lines );
			$events = [];

			foreach ( $lines as $line ) {
				$result = explode( '|', $line );
				if ( isset( $result[1] ) ) {
					$client_id    = $result[0];
					$date_created = $result[1];
					$type         = $result[2];
					$context      = $result[3];
					$value        = $result[4];
				} else {
					// Handle legacy format.
					$result       = explode( ';', $line );
					$client_id    = $result[1];
					$date_created = $result[2];
					$type         = 'view';
					$context      = 'post';
					$value        = wp_json_encode(
						[
							'post_id'    => (int) $result[3],
							'categories' => $result[4],
						]
					);
				}

				$events[] = [
					'client_id'    => $client_id,
					'date_created' => $date_created,
					'type'         => $type,
					'context'      => $context,
					'value'        => $value,
				];
			}

			try {
				$this->bulk_db_insert( $events );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				error_log( $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			flock( $log_file, LOCK_UN ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_flock

			// Clear the log file.
			file_put_contents( Segmentation::get_log_file_path(), '' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			fclose( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		}
	}

	/**
	 * Given an event type and value, find other client IDs with matching type and value
	 * and reconcile all client IDs so they're considered a single reader.
	 *
	 * Limit the number of reconciled sessions to 10 for performance reasons.
	 *
	 * @param string $current_client_id The client ID of the current reader session.
	 * @param string $type The type of event to look up.
	 * @param mixed  $context The context of the event to look up.
	 *
	 * @return array Array of all client IDs associated with this reader.
	 */
	public function reconcile_readers( $current_client_id, $type, $context ) {
		global $wpdb;
		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$client_ids               = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT client_id FROM $reader_events_table_name WHERE type = %s AND context = %s ORDER BY date_created DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$type,
				$context
			)
		);

		if ( ! empty( $client_ids ) ) {
			$client_ids = array_map(
				function( $row ) {
					return $row->client_id;
				},
				$client_ids
			);

			if ( ! in_array( $current_client_id, $client_ids, true ) ) {
				$client_ids[] = $current_client_id;
			}

			// Save all client IDs to each reader record.
			foreach ( $client_ids as $unique_client_id ) {
				$reader              = $this->get_reader( $unique_client_id );
				$reader_data         = isset( $reader['reader_data'] ) ? $reader['reader_data'] : [];
				$original_client_ids = isset( $reader['reader_data']['client_ids'] ) ? $reader['reader_data']['client_ids'] : [];

				$this->debug['readers'][] = $reader;

				if ( isset( $reader_data['client_ids'] ) ) {
					$reader_data['client_ids'] = array_unique( array_merge( $reader_data['client_ids'], $client_ids ) );
				} else {
					$reader_data['client_ids'] = $client_ids;
				}

				// Limit number of reconciled sessions to 10.
				$reader_data['client_ids'] = array_slice( $reader_data['client_ids'], -10, 10 );

				// Only update if there are new client IDs to add.
				if ( ! empty( array_diff( $reader_data['client_ids'], $original_client_ids ) ) ) {
					$this->save_reader( $unique_client_id, $reader_data );
				}
			}

			return $client_ids;
		}

		return [ $current_client_id ];
	}

	/**
	 * Rebuild legacy reader data in the newer format.
	 *
	 * @param string $client_id Client ID of reader session.
	 * @param array  $reader Reader object to save.
	 */
	public function rebuild_legacy_reader( $client_id, $reader ) {
		// Check the legacy transients table for existing reader.
		$legacy_reader = $this->get_transient_legacy( $client_id );
		$reader_events = [];

		// If there's data for this reader in the legacy transients table, recreate it in the new table.
		if ( ! empty( $legacy_reader ) ) {
			$this->debug['legacy_reader'] = $legacy_reader;

			// Add posts_read data to views count.
			if ( isset( $legacy_reader['posts_read'] ) && 0 < count( $legacy_reader['posts_read'] ) ) {
				$reader['reader_data']['views'] = [ 'post' => count( $legacy_reader['posts_read'] ) ];

				// Add read categories data.
				$categories_read = ! empty( $reader['reader_data']['category'] ) ? $reader['reader_data']['category'] : [];
				foreach ( $legacy_reader['posts_read'] as $article_view ) {

					// Rebuild recent views as view events.
					if ( isset( $article_view['created_at'] ) ) {
						$hour_ago   = strtotime( '-1 hour', time() );
						$event_time = strtotime( $article_view['created_at'] );

						if ( $event_time > $hour_ago ) {
							$view_event = [
								'date_created' => gmdate( 'Y-m-d H:i:s', $event_time ),
								'type'         => 'view',
								'context'      => 'post',
								'value'        => [],
							];

							if ( isset( $article_view['post_id'] ) ) {
								$view_event['value']['post_id'] = $article_view['post_id'];
							}
							if ( isset( $article_view['categories'] ) ) {
								$view_event['value']['categories'] = $article_view['categories'];
							}

							$reader_events[] = $view_event;
						}
					}

					if ( ! empty( $article_view['category_ids'] ) ) {
						$category_ids = explode( ',', $article_view['category_ids'] );

						foreach ( $category_ids as $category_id ) {
							if ( ! isset( $categories_read[ $category_id ] ) ) {
								$categories_read[ $category_id ] = 0;
							}
							$categories_read[ $category_id ] ++;
						}
					}
				}

				if ( ! empty( $categories_read ) ) {
					$reader['reader_data']['category'] = $categories_read;
				}
			}

			// Add known user accounts.
			if ( ! empty( $legacy_reader['user_id'] ) ) {
				$reader_events[] = [
					'type'    => 'user_account',
					'context' => $legacy_reader['user_id'],
				];
			}

			// Add prior donations.
			if ( ! empty( $legacy_reader['donations'] ) ) {
				// Keep track of logged donations so we don't log duplicate events.
				$donation_ids = [];

				foreach ( $legacy_reader['donations'] as $donation ) {
					$create_event  = false;
					$donation      = (array) $donation;
					$donation_date = isset( $donation['date'] ) ? strtotime( $donation['date'] ) : time();
					$donation_data = [
						'date_created' => gmdate( 'Y-m-d H:i:s', $donation_date ),
						'type'         => 'donation',
						'value'        => $donation,
					];

					if ( isset( $donation['order_id'] ) && ! in_array( (int) $donation['order_id'], $donation_ids, true ) ) {
						$donation_ids[]           = $donation['order_id'];
						$donation_data['context'] = 'woocommerce';
						$create_event             = true;
					}
					if ( isset( $donation['stripe_id'] ) && ! in_array( (int) $donation['stripe_id'], $donation_ids, true ) ) {
						$donation_ids[]           = $donation['stripe_id'];
						$donation_data['context'] = 'stripe';
						$create_event             = true;
					}

					// Only log the event if it hasn't already been logged.
					if ( $create_event ) {
						$reader_events[] = $donation_data;
					}
				}
			}

			// Add prior newsletter subscriptions.
			if ( ! empty( $legacy_reader['email_subscriptions'] ) ) {
				foreach ( $legacy_reader['email_subscriptions'] as $subscription ) {
					$reader_events[] = [
						'type'    => 'subscription',
						'context' => $subscription['email'] ?? $subscription['address'],
					];
				}
			}

			// If we were able to save the legacy data, clean up old transients data.
			$this->delete_transient_legacy( $client_id );
		}

		$this->save_reader( $client_id, $reader['reader_data'] );

		if ( ! empty( $reader_events ) ) {
			$this->save_reader_events( $client_id, $reader_events );
		}
	}

	/**
	 * Get a specific reader.
	 *
	 * @param string $client_id Client ID.
	 *
	 * @return array Reader object.
	 */
	public function get_reader( $client_id ) {
		$reader              = $this->reader_blueprint;
		$reader['client_id'] = $client_id;

		// Check the cache first.
		if ( ! $this->ignore_cache || ! $this->validate_cache( $client_id ) ) {
			$cached_reader = wp_cache_get( 'reader', $client_id );
			if ( ! empty( $cached_reader ) ) {
				if ( $this->is_debug_enabled() ) {
					$this->debug['reader'] = $cached_reader;
				}
				return $cached_reader;
			}
		}

		// If ignoring cache or there's no cached reader, retrieve from the DB.
		global $wpdb;
		$readers_table_name = Segmentation::get_readers_table_name();
		$reader_from_db     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * from $readers_table_name WHERE client_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			)
		);

		if ( $this->is_debug_enabled() ) {
			$this->debug['read_query_count'] += 1;
		}

		// If there's no reader for this client ID in the DB, create it.
		if ( empty( $reader_from_db ) ) {
			$this->rebuild_legacy_reader( $client_id, $reader );
		} else {
			// Rebuild the cache.
			$reader_from_db = reset( $reader_from_db );
			$reader_from_db = (array) $reader_from_db;
			$reader         = wp_parse_args( $reader_from_db, $reader );
		}

		// Unserialize reader_data value.
		if ( isset( $reader['reader_data'] ) && is_string( $reader['reader_data'] ) ) {
			$reader['reader_data'] = json_decode( $reader['reader_data'], true );
		}

		// Rebuild cache.
		wp_cache_set( 'reader', $reader, $client_id );

		if ( $this->is_debug_enabled() ) {
			$this->debug['reader'] = $reader;
		}
		return $reader;
	}

	/**
	 * Get all known reader sessions associated with a client ID.
	 *
	 * @param string $current_client_id Client ID of the current session.
	 *
	 * @return array Array of all known readers associated with the client ID.
	 */
	public function get_readers( $current_client_id ) {
		$all_client_ids = $this->get_reconciled_client_ids( $current_client_id );
		$all_readers    = [];

		foreach ( $all_client_ids as $client_id ) {
			$all_readers[] = $this->get_reader( $client_id );
		}

		return $all_readers;
	}

	/**
	 * Given an associative array, format the keys and values into columns, values,
	 * and placeholders to be used in an INSERT INTO...ON DUPLICATE KEY SQL query.
	 * All data items in the array should have the same keys.
	 *
	 * @param string $table_name Name of the table to execute the query on.
	 * @param array  $data Associative array of data items to format. Array keys
	 *                     should correspond to table column names.
	 *
	 * @return array Object containing columns, values, placeholders, and duplicate
	 *               placeholders to be used in a SQL query.
	 */
	public function get_sql( $table_name, $data ) {
		global $wpdb;

		// Placeholders for the INSERT INTO statement.
		$placeholders           = [];
		$columns                = array_keys( $data[0] );
		$values                 = [];
		$duplicate_placeholders = [];
		$is_readers_table       = Segmentation::get_readers_table_name() === $table_name;

		// If updating the readers table, add date_modified timestamp.
		if ( $is_readers_table ) {
			$columns[] = 'date_modified';
		}

		foreach ( $data as $item ) {
			if ( $is_readers_table ) {
				$item['date_modified'] = gmdate( 'Y-m-d H:i:s' );
			}

			// Serialize value strings.
			if ( isset( $item['reader_data'] ) && is_array( $item['reader_data'] ) ) {
				$item['reader_data'] = wp_json_encode( $item['reader_data'] );
			}
			if ( isset( $item['value'] ) && is_array( $item['value'] ) ) {
				$item['value'] = wp_json_encode( $item['value'] );
			}

			$placeholder = [];
			$item_values = array_map(
				function( $value ) use ( &$placeholder ) {
					$placeholder[] = '%s';
					return $value;
				},
				array_values( $item )
			);

			$placeholder    = implode( ',', $placeholder );
			$placeholders[] = "($placeholder)";
			$values         = array_merge( $values, $item_values );
		}

		// Placeholders for the ON DUPLICATE KEY UPDATE statement.
		foreach ( $columns as $column ) {
			$duplicate_placeholders[] = "$column = values($column)";
		}

		$columns                = implode( ', ', $columns );
		$placeholders           = implode( ', ', $placeholders );
		$duplicate_placeholders = implode( ', ', $duplicate_placeholders );

		return $wpdb->prepare(
			"INSERT INTO $table_name ($columns) VALUES $placeholders ON DUPLICATE KEY UPDATE $duplicate_placeholders", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$values
		);
	}

	/**
	 * Upsert reader to DB and save to cache.
	 *
	 * @param string $client_id Client ID of the reader.
	 * @param array  $data Data for the reader to be updated.
	 *
	 * @return boolean True if updated., false if not.
	 */
	public function save_reader( $client_id, $data = [] ) {
		if ( empty( $client_id ) || ! is_string( $client_id ) ) {
			return false;
		}

		global $wpdb;
		$readers_table_name = Segmentation::get_readers_table_name();
		$reader             = [ 'client_id' => $client_id ];

		if ( ! empty( $data ) ) {
			$reader['reader_data'] = $data;
		}

		if ( $this->is_preview( $client_id ) ) {
			$reader['is_preview'] = true;
		}

		$query = $this->get_sql( $readers_table_name, [ $reader ] );

		// Write to the DB.
		$write_result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( $this->is_debug_enabled() ) {
			$this->debug['write_query_count'] += 1;
		}

		// If DB write was successful, rebuild cache.
		if ( $write_result ) {
			return wp_cache_set( 'reader', $reader, $client_id );
		}

		if ( $this->is_debug_enabled() ) {
			$this->debug['write_error'] = "Error writing to $readers_table_name.";
		}
		return false;
	}

	/**
	 * Given an array of reader events, only return those with matching $types and/or $contexts.
	 * If both $types and $contexts are null, simply return the array as-is.
	 *
	 * @param array             $events Array of reader events.
	 * @param string|array|null $types Data type or array of data types to filter by.
	 *                                 If not given, will only retrieve temporary data types.
	 * @param string|array|null $contexts Data context or array of data contexts to filter by.
	 *
	 * @return array Filtered array of data items.
	 */
	public function filter_events_by_type( $events = [], $types = null, $contexts = null ) {
		// Unserialize event values.
		if ( ! empty( $events ) ) {
			$events = array_map(
				function( $event ) {
					if ( ! empty( $event['value'] ) && is_string( $event['value'] ) ) {
						$event['value'] = json_decode( $event['value'], true );
					}
					return $event;
				},
				$events
			);
		}

		if ( null === $types && null === $contexts ) {
			return $events;
		}

		if ( ! is_array( $types ) && null !== $types ) {
			$types = [ $types ];
		}
		if ( ! is_array( $contexts ) && null !== $contexts ) {
			$contexts = [ $contexts ];
		}

		return array_values(
			array_filter(
				$events,
				function( $event ) use ( $types, $contexts ) {
					$matches = true;
					if ( null !== $types ) {
						$matches = in_array( $event['type'], $types, true );
					}

					if ( null !== $contexts ) {
						$matches = $matches && in_array( $event['context'], $contexts, true );
					}
					return $matches;
				}
			)
		);
	}

	/**
	 * Given an array of values, build a SQL statement to query on those values.
	 *
	 * @param string $column_name Name of the column to query.
	 * @param array  $values Possible values to query for.
	 *
	 * @return string Partial query string to use in a SQL query.
	 */
	public function build_partial_query_filter( $column_name, $values ) {
		global $wpdb;

		// If only one value to query on, use the = operator. Otherwise, use the IN operator.
		if ( 1 === count( $values ) ) {
			return $wpdb->prepare( "$column_name = %s", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		$placeholders = [];
		foreach ( $values as $value ) {
			$placeholders[] = '%s';
		}
		$placeholders = implode( ',', $placeholders );

		return $wpdb->prepare( "$column_name IN ( $placeholders )", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Get all client IDs known to belong to the same reader.
	 *
	 * @param string $current_client_id Client ID of the current reader session.
	 *
	 * @return array All client IDs known to belong to the same reader.
	 */
	public function get_reconciled_client_ids( $current_client_id ) {
		$reader = $this->get_reader( $current_client_id, true );
		return isset( $reader['reader_data']['client_ids'] ) ? $reader['reader_data']['client_ids'] : [ $current_client_id ];
	}

	/**
	 * Get all reader events from the DB.
	 *
	 * @param array             $client_ids Array of client IDs to fetch for.
	 * @param string|array|null $type Type or types of data to retrieve.
	 * @param string|array|null $context Context or contexts of data to retrieve.
	 *
	 * @return array Array of reader data for the given client ID.
	 */
	public function get_reader_events_from_db( $client_ids, $type = null, $context = null ) {
		global $wpdb;

		$client_filter  = $this->build_partial_query_filter( 'client_id', $client_ids );
		$type_filter    = '';
		$context_filter = '';

		if ( is_array( $type ) ) {
			$type_filter .= 'AND ' . $this->build_partial_query_filter( 'type', $type );
		}
		if ( is_array( $context ) ) {
			$context_filter .= 'AND ' . $this->build_partial_query_filter( 'context', $context );
		}

		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$events_sql               = "SELECT id, client_id, date_created, type, context, value from $reader_events_table_name WHERE $client_filter $type_filter $context_filter ORDER BY date_created DESC LIMIT 1000";
		$events                   = $wpdb->get_results( $events_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( $this->is_debug_enabled() ) {
			$this->debug['read_query_count'] += 1;
		}

		if ( ! empty( $events ) ) {
			$events = array_map(
				function( $event ) {
					$event = (array) $event;

					if ( ! empty( $event['value'] ) && is_string( $event['value'] ) ) {
						$event['value'] = json_decode( $event['value'], true );
					}

					return $event;
				},
				$events
			);
		}

		return $events;
	}

	/**
	 * Checks whether cached data has a version number that matches the current.
	 * If the version is missing or doesn't match the current version, the cache
	 * should be rebuilt from the DB instead.
	 *
	 * @param string $client_id Single client ID associated with the current reader.
	 *
	 * @return boolean True if cached data is valid, false if we should rebuild from DB.
	 */
	public function validate_cache( $client_id ) {
		$cache_version  = wp_cache_get( 'reader_cache_version', $client_id );
		$cached_events  = $this->get_reader_events_from_cache( $client_id );
		$cache_is_valid = false;

		if ( ! empty( $cache_version ) && $cache_version === $this->reader_cache_version && ! empty( $cached_events ) ) {
			$cache_is_valid = true;
		}

		// If the cache has been purged, let's parse the cached events to the DB.
		if ( ! $cache_is_valid ) {
			$this->parse_event_logs();
			return $cache_is_valid;
		}

		$this->debug['cache_version']  = $cache_version;
		$this->debug['cache_is_valid'] = $cache_is_valid;

		return $cache_is_valid;
	}

	/**
	 * Get reader events from the persistent cache, if available.
	 *
	 * @param string $client_id Single client ID associated with the current reader.
	 *
	 * @return array Array of reader events for the given client ID.
	 */
	public function get_reader_events_from_cache( $client_id ) {
		$events = wp_cache_get( 'reader_events', $client_id );

		// If there are no cached events.
		if ( empty( $events ) ) {
			$events = [];
		}

		return $events;
	}

	/**
	 * Updates events logged in debug mode.
	 *
	 * @param array $events New events to log.
	 */
	public function update_debug_events( $events ) {
		if ( $this->is_debug_enabled() ) {
			$debug_event_ids              = array_column( $this->debug['reader_events'], 'id' );
			$this->debug['reader_events'] = array_merge(
				$this->debug['reader_events'],
				array_values(
					array_filter(
						$events,
						function( $event ) use ( $debug_event_ids ) {
							return ! in_array( $event['id'], $debug_event_ids, true );
						}
					)
				)
			);
		}
	}

	/**
	 * Retrieve data for a specific reader from the cache or DB.
	 *
	 * @param string            $client_id Client ID of the current reader session.
	 * @param string|array|null $type Type or types of data to retrieve.
	 *                                If not given, only return temporary data types.
	 * @param string|array|null $context Context or contexts of data to retrieve.
	 * @param boolean           $ignore_cache If true, skip cache and get events directly from DB.
	 *
	 * @return array Array of reader data, optionally filtered by $type and $context.
	 */
	public function get_reader_events( $client_id, $type = null, $context = null, $ignore_cache = false ) {
		if ( ! is_array( $type ) && null !== $type ) {
			$type = [ $type ];
		}
		if ( ! is_array( $context ) && null !== $context ) {
			$context = [ $context ];
		}

		$client_id_or_ids = $this->get_reconciled_client_ids( $client_id );
		$cached_events    = [];

		// Check the cache first.
		if ( ! $this->ignore_cache && $this->validate_cache( $client_id ) ) {
			$cached_events   = $this->get_reader_events_from_cache( $client_id );
			$filtered_events = $this->filter_events_by_type( $cached_events, $type, $context );
			if ( ! empty( $filtered_events ) ) {
				$this->update_debug_events( $filtered_events );
				return $filtered_events;
			}
		}

		$events_from_db     = $this->get_reader_events_from_db( $client_id_or_ids, $type, $context );
		$existing_event_ids = array_column( $cached_events, 'id' );
		$unique_ids         = [];
		$filtered_events    = array_values(
			array_filter(
				$events_from_db,
				function( $event ) use ( $existing_event_ids ) {
					// Dedupe events from cache.
					if ( in_array( $event['id'], $existing_event_ids, true ) ) {
						return false;
					}

					return true;
				}
			)
		);

		// Rebuild cache.
		wp_cache_set( 'reader_events', array_merge( $cached_events, $filtered_events ), $client_id );

		$this->update_debug_events( $filtered_events );

		return $filtered_events;
	}

	/**
	 * Given a visit object, convert it to a view event.
	 *
	 * @param string $client_id Client ID.
	 * @param array  $visit Visit object passed to API.
	 *
	 * @return array|boolean View event for the visit, or false.
	 */
	public function convert_visit_to_event( $client_id, $visit ) {
		if ( ! $visit || ( ! isset( $visit['post_id'] ) && ! isset( $visit['request'] ) ) ) {
			return false;
		}

		$view_event = [
			'type'  => 'view',
			'value' => [],
		];
		if ( isset( $visit['post_id'] ) ) {
			$view_event['value']['post_id'] = $visit['post_id'];
		}
		if ( isset( $visit['categories'] ) ) {
			$view_event['value']['categories'] = $visit['categories'];
		}
		if ( isset( $visit['request'] ) ) {
			$view_event['value']['request'] = $visit['request'];
		}
		if ( isset( $visit['post_type'] ) || isset( $visit['request_type'] ) ) {
			$view_event['context'] = isset( $visit['post_type'] ) ? $visit['post_type'] : $visit['request_type'];
		}

		return $view_event;
	}

	/**
	 * Save reader data.
	 *
	 * @param string $client_id Client ID of the reader.
	 * @param array  $events Array of reader data rows to log.
	 *                       ['date_created'] Timestamp of the data insertion.
	 *                       ['date_modified'] Timestamp of the update.
	 *                       ['type'] Type of data. Required.
	 *                       ['context'] Context of data.
	 *                       ['value'] Value of the data to save. Required.
	 *
	 * @return boolean True if saved, false if not.
	 */
	public function save_reader_events( $client_id, $events ) {
		if ( empty( $client_id ) || empty( $events ) ) {
			return false;
		}

		// If the event contains data we can use to link this reader session with others, reconcile those sessions.
		$identifiable_events = $this->filter_events_by_type( $events, [ 'user_account', 'subscription' ] );
		if ( ! empty( $identifiable_events ) ) {
			foreach ( $identifiable_events as $identifiable_event ) {
				if ( ! empty( $identifiable_event['type'] ) && ! empty( $identifiable_event['context'] ) ) {
					$this->reconcile_readers( $client_id, $identifiable_event['type'], $identifiable_event['context'] );
				}
			}
		}

		// Rebuild reader_data if there were new views.
		$new_views = $this->filter_events_by_type( $events, 'view' );
		if ( ! empty( $new_views ) ) {
			$reader      = $this->get_reader( $client_id );
			$reader_data = isset( $reader['reader_data'] ) ? $reader['reader_data'] : [];

			if ( ! isset( $reader_data['views'] ) ) {
				$reader_data['views'] = [];
			}

			foreach ( $new_views as $view_event ) {
				if ( ! isset( $reader_data['views'][ $view_event['context'] ] ) ) {
					$reader_data['views'][ $view_event['context'] ] = 0;
				}
				$reader_data['views'][ $view_event['context'] ] ++;

				if ( isset( $view_event['value']['categories'] ) ) {
					$categories = explode( ',', $view_event['value']['categories'] );
					if ( ! isset( $reader_data['category'] ) ) {
						$reader_data['category'] = [];
					}
					foreach ( $categories as $term_id ) {
						if ( ! isset( $reader_data['category'][ $term_id ] ) ) {
							$reader_data['category'][ $term_id ] = 0;
						}
						$reader_data['category'][ $term_id ] ++;
					}
				}
			}

			$this->save_reader( $client_id, $reader_data );
		}

		// Ensure client ID exists and keys match across all new events.
		$reader_events_blueprint = $this->reader_events_blueprint;
		$events                  = array_map(
			function( $event ) use ( $client_id, $reader_events_blueprint ) {
				$event['client_id'] = $client_id;

				if ( empty( $event['date_created'] ) ) {
					$event['date_created'] = gmdate( 'Y-m-d H:i:s' );
				}

				return wp_parse_args( $event, $this->reader_events_blueprint );
			},
			$events
		);

		// If ignoring cache, write directly to DB.
		if ( $this->ignore_cache ) {
			$write_result = $this->bulk_db_insert( $events );

			if ( $this->is_debug_enabled() ) {
				$this->debug['write_query_count'] += 1;
			}

			return $write_result;
		} else {
			// Rebuild cache.
			$cached_events = $this->get_reader_events_from_cache( $client_id );
			$all_events    = array_merge( $cached_events, $events );
			$all_events    = array_map(
				function( $event ) {
					// If the event is coming from the persistent cache, fashion a faux-unique ID using the timestamp.
					if ( ! isset( $event['id'] ) ) {
						$event['id'] = $event['type'] . '_' . $event['context'] . '_' . $event['date_created'];
					}
					return $event;
				},
				$all_events
			);

			// Rebuild cache.
			wp_cache_set( 'reader_events', $all_events, $client_id );

			$this->update_debug_events( $all_events );

			// Write items to the flat file to be parsed to the DB at a later point.
			Segmentation_Report::log_reader_events( $events );
		}

		return true;
	}

	/**
	 * Retrieve a large sample of readers' data.
	 *
	 * @return array Readers' data.
	 */
	public function get_all_readers_data() {
		global $wpdb;
		$readers_table_name = Segmentation::get_readers_table_name();

		// Results are limited to the 1000 most recent rows for performance reasons. Also ignore clients from preview sessions.
		$all_client_ids_rows = $wpdb->get_results( "SELECT DISTINCT client_id,date_modified FROM $readers_table_name WHERE is_preview IS NULL ORDER BY date_modified DESC LIMIT 1000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map(
			function ( $row ) {
				return $row->client_id;
			},
			$all_client_ids_rows
		);
	}

	/**
	 * Get request param.
	 *
	 * @param string $param Param name.
	 * @param object $request A POST request.
	 */
	public function get_request_param( $param, $request ) {
		$value = isset( $request[ $param ] ) ? $request[ $param ] : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return $value;
	}

	/**
	 * Get URL query param.
	 *
	 * @param string $param Param name.
	 * @param string $url URL to parse.
	 *
	 * @return string|boolean Value of the param, or false if it's not in the URL.
	 */
	public function get_url_param( $param, $url ) {
		$parsed_url = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! empty( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $query );
			if ( ! empty( $query[ $param ] ) ) {
				return $query[ $param ];
			}
		}

		return false;
	}

	/**
	 * Get POST request payload.
	 */
	public function get_post_payload() {
		$payload = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null == $payload ) {
			// A POST request made by amp-analytics has to be parsed in this way.
			// $_POST contains the payload if the request has FormData.
			$payload = file_get_contents( 'php://input' ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
			if ( ! empty( $payload ) ) {
				$payload = json_decode( $payload, true );
			}
		}
		if ( null == $payload ) {
			// Of all else fails, look for payload in query string.
			return $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		}
		return $payload;
	}

	/**
	 * Get Mailchimp client data via API and sync to reader events.
	 *
	 * @param string $client_id Client ID.
	 * @param string $mailchimp_campaign_id Campaign ID extracted from mc_cid param.
	 * @param string $mailchimp_subscriber_id Campaign ID extracted from mc_eid param.
	 */
	public function get_mailchimp_client_data( $client_id, $mailchimp_campaign_id, $mailchimp_subscriber_id ) {
		$reader_events                        = [];
		$mailchimp_api_key_option_name        = 'newspack_mailchimp_api_key';
		$mailchimp_api_key_option_name_legacy = 'newspack_newsletters_mailchimp_api_key';
		global $wpdb;
		$mailchimp_api_key = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name IN (%s,%s) ORDER BY FIELD(%s,%s)", $mailchimp_api_key_option_name, $mailchimp_api_key_option_name_legacy, $mailchimp_api_key_option_name, $mailchimp_api_key_option_name_legacy )
		);
		if ( $mailchimp_api_key ) {
			$mc            = new Mailchimp( $mailchimp_api_key->option_value );
			$campaign_data = $mc->get( "campaigns/$mailchimp_campaign_id" );
			if ( isset( $campaign_data['recipients'], $campaign_data['recipients']['list_id'] ) ) {
				$list_id = $campaign_data['recipients']['list_id'];
				$members = $mc->get( "/lists/$list_id/members", [ 'unique_email_id' => $mailchimp_subscriber_id ] )['members'];

				if ( ! empty( $members ) ) {
					$subscriber      = $members[0];
					$reader_events[] = [
						'type'    => 'subscription',
						'context' => $subscriber['email_address'],
						'value'   => [
							'esp' => 'mailchimp',
						],
					];

					if ( isset( $subscriber['merge_fields'] ) ) {
						$donor_merge_field_option_name      = 'newspack_popups_mc_donor_merge_field';
						$donor_merge_fields                 = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s LIMIT 1", $donor_merge_field_option_name )
						);
						$donor_merge_fields                 = isset( $donor_merge_fields->option_value ) ? explode( ',', $donor_merge_fields->option_value ) : [ 'DONAT' ];
						$has_donated_according_to_mailchimp = array_reduce(
							// Get all merge fields whose name contains one of the Donor Merge Field option strings.
							array_filter(
								array_keys( $subscriber['merge_fields'] ),
								function ( $merge_field ) use ( $donor_merge_fields ) {
									$matches = false;
									foreach ( $donor_merge_fields as $donor_merge_field ) {
										if ( strpos( $merge_field, trim( $donor_merge_field ) ) !== false ) {
											$matches = true;
										}
									}
									return $matches;
								}
							),
							// If any of these fields is "true", the subscriber has donated.
							function ( $result, $donation_merge_field_name ) use ( $subscriber ) {
								if ( 'true' === $subscriber['merge_fields'][ $donation_merge_field_name ] ) {
									$result = true;
								}
								return $result;
							},
							false
						);

						if ( $has_donated_according_to_mailchimp ) {
							$reader_events[] = [
								'type'    => 'donation',
								'context' => 'mailchimp',
								'value'   => [
									'email' => $subscriber['email_address'],
									'mailchimp_has_donated' => true,
								],
							];
						}
					}
				}
			}
		}

		if ( ! empty( $reader_events ) ) {
			$this->save_reader_events( $client_id, $reader_events );
		}
	}

	/**
	 * Get option.
	 *
	 * @param string $name Option name.
	 */
	public function get_option( $name ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
