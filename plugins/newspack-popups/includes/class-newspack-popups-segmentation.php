<?php
/**
 * Newspack Segmentation Plugin
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/../api/campaigns/class-campaign-data-utils.php';
require_once dirname( __FILE__ ) . '/../api/segmentation/class-segmentation.php';

/**
 * Main Newspack Segmentation Plugin Class.
 */
final class Newspack_Popups_Segmentation {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Segmentation
	 */
	protected static $instance = null;

	/**
	 * Name of the Client ID, to be used by amp-analytics.
	 */
	const NEWSPACK_SEGMENTATION_CID_NAME = 'newspack-cid';

	/**
	 * Query param that will overwrite the cookie value.
	 */
	const NEWSPACK_SEGMENTATION_CID_LINKER_PARAM = 'ref_newspack_cid';

	/**
	 * Name of the option to store segments under.
	 */
	const SEGMENTS_OPTION_NAME = 'newspack_popups_segments';

	/**
	 * Installed version number of the custom table.
	 */
	const TABLE_VERSION = '1.0';

	/**
	 * Option name for the installed version number of the custom table.
	 */
	const TABLE_VERSION_OPTION = '_newspack_popups_table_versions';

	/**
	 * Main Newspack Segmentation Plugin Instance.
	 * Ensures only one instance of Newspack Segmentation Plugin Instance is loaded or can be loaded.
	 *
	 * @return Newspack Segmentation Plugin Instance - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ __CLASS__, 'check_update_version' ] );
		add_action( 'wp_footer', [ __CLASS__, 'insert_amp_analytics' ], 20 );

		add_filter( 'newspack_custom_dimensions', [ __CLASS__, 'register_custom_dimensions' ] );
		add_filter( 'newspack_custom_dimensions_values', [ __CLASS__, 'report_custom_dimensions' ] );

		// Data pruning CRON job.
		register_deactivation_hook( NEWSPACK_POPUPS_PLUGIN_FILE, [ __CLASS__, 'cron_deactivate' ] );
		add_action( 'newspack_popups_segmentation_data_prune', [ __CLASS__, 'prune_data' ] );
		$next = wp_next_scheduled( 'newspack_popups_segmentation_data_prune' );
		if ( ! $next || 3600 < $next - time() ) {
			self::cron_deactivate(); // To avoid duplicate execution when transitioning from daily to hourly schedule.
			wp_schedule_event( time(), 'hourly', 'newspack_popups_segmentation_data_prune' );
		}

		add_action( 'newspack_registered_reader', [ __CLASS__, 'handle_registered_reader' ], 10, 4 );
	}

	/**
	 * Clear the cron job when this plugin is deactivated.
	 */
	public static function cron_deactivate() {
		wp_clear_scheduled_hook( 'newspack_popups_segmentation_data_prune' );
	}

	/**
	 * Add custom custom dimensions to Newspack Plugin's Analytics Wizard.
	 *
	 * @param array $default_dimensions Default custom dimensions.
	 */
	public static function register_custom_dimensions( $default_dimensions ) {
		$default_dimensions = array_merge(
			$default_dimensions,
			[
				[
					'role'   => Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY,
					'option' => [
						'value' => Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY,
						'label' => __( 'Reader frequency', 'newspack' ),
					],
				],
				[
					'role'   => Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER,
					'option' => [
						'value' => Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER,
						'label' => __( 'Is a subcriber', 'newspack' ),
					],
				],
				[
					'role'   => Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR,
					'option' => [
						'value' => Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR,
						'label' => __( 'Is a donor', 'newspack' ),
					],
				],
			]
		);
		return $default_dimensions;
	}

	/**
	 * Add custom custom dimensions to Newspack Plugin's Analytics reporting.
	 *
	 * @param array $custom_dimensions_values Existing custom dimensions payload.
	 */
	public static function report_custom_dimensions( $custom_dimensions_values ) {
		$custom_dimensions = [];
		if ( class_exists( 'Newspack\Analytics_Wizard' ) ) {
			$custom_dimensions = Newspack\Analytics_Wizard::list_configured_custom_dimensions();
		}
		if ( empty( $custom_dimensions ) ) {
			return $custom_dimensions_values;
		}

		$campaigns_custom_dimensions = [
			Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY,
			Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER,
			Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR,
		];
		$all_campaign_dimensions     = array_values(
			array_map(
				function( $custom_dimension ) {
					return $custom_dimension['role'];
				},
				$custom_dimensions
			)
		);

		// No need to proceed if the configured custom dimensions do not include any Campaigns data.
		if ( 0 === count( array_intersect( $campaigns_custom_dimensions, $all_campaign_dimensions ) ) ) {
			return $custom_dimensions_values;
		}

		$client_id           = self::get_client_id();
		$api                 = self::load_lightweight_api();
		$subscription_events = $api->get_reader_events( $client_id, 'subscription' );
		$donation_events     = $api->get_reader_events( $client_id, 'donation' );

		foreach ( $custom_dimensions as $custom_dimension ) {
			// Strip the `ga:` prefix from gaID.
			$dimension_id = substr( $custom_dimension['gaID'], 3 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			switch ( $custom_dimension['role'] ) {
				case Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY:
					$read_count = Campaign_Data_Utils::get_post_view_count( [ $api->get_reader( $client_id ) ] );
					// Tiers mimick NCI's – https://news-consumer-insights.appspot.com.
					$read_count_tier = 'casual';
					if ( $read_count > 1 && $read_count <= 14 ) {
						$read_count_tier = 'loyal';
					} elseif ( $read_count > 14 ) {
						$read_count_tier = 'brand_lover';
					}
					$custom_dimensions_values[ $dimension_id ] = $read_count_tier;
					break;
				case Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER:
					$custom_dimensions_values[ $dimension_id ] = Campaign_Data_Utils::is_subscriber( $subscription_events ) ? 'true' : 'false';
					break;
				case Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR:
					$custom_dimensions_values[ $dimension_id ] = Campaign_Data_Utils::is_donor( $donation_events ) ? 'true' : 'false';
					break;
			}
		}

		return $custom_dimensions_values;
	}

	/**
	 * Get the light-weight API.
	 */
	private static function load_lightweight_api() {
		require_once dirname( __FILE__ ) . '/../api/campaigns/class-campaign-data-utils.php';
		require_once dirname( __FILE__ ) . '/../api/classes/class-lightweight-api.php';
		return new Lightweight_API( null, true );
	}

	/**
	 * Permission callback for the API calls.
	 */
	public static function is_admin_user() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Insert amp-analytics tracking code.
	 * Has to be included on every page to set the cookie.
	 *
	 * This amp-analytics tag will not report any analytics, it's only responsible for settings the cookie
	 * bearing the client ID, as well as handling the linker parameter when navigating from a proxy site.
	 *
	 * Because this tag doesn't report any analytics but is used to look up the reader's activity, it
	 * should be included in preview requests and logged-in admin/editor sessions.
	 *
	 * There is a known issue with amp-analytics & amp-access interoperation – more on that at
	 * https://github.com/Automattic/newspack-popups/pull/224#discussion_r496655085.
	 */
	public static function insert_amp_analytics() {
		$linker_id            = 'cid';
		$amp_analytics_config = [
			// Linker will append a query param to all internal links.
			// This will only be performed on a proxy site (like AMP cache) by default.
			// https://amp.dev/documentation/components/amp-analytics/?format=websites#linkers.
			'linkers' => [
				'enabled' => true,
				self::NEWSPACK_SEGMENTATION_CID_LINKER_PARAM => [
					'ids' => [
						$linker_id => self::get_cid_param(),
					],
				],
			],
			// If the linker parameter is found, the cookie value will be overwritten by it.
			// https://amp.dev/documentation/components/amp-analytics/?format=websites#cookies.
			'cookies' => [
				'enabled'                            => true,
				self::NEWSPACK_SEGMENTATION_CID_NAME => [
					'value' => 'LINKER_PARAM(' . self::NEWSPACK_SEGMENTATION_CID_LINKER_PARAM . ', ' . $linker_id . ')',
				],
			],
		];

		$initial_client_report_url_params = [];

		// Handle Mailchimp URL parameters.
		if ( isset( $_GET['mc_cid'], $_GET['mc_eid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$initial_client_report_url_params['mc_cid'] = sanitize_text_field( $_GET['mc_cid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$initial_client_report_url_params['mc_eid'] = sanitize_text_field( $_GET['mc_eid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// If visiting the donor landing page, mark the visitor as donor.
		$donor_landing_page = intval( Newspack_Popups_Settings::donor_landing_page() );
		if ( ! empty( $donor_landing_page ) && get_queried_object_id() === $donor_landing_page ) {
			$initial_client_report_url_params['donation'] = wp_json_encode(
				[
					'offsite_has_donated' => true,
				]
			);
		}

		if ( ! empty( $initial_client_report_url_params ) ) {
			$amp_analytics_config['requests']                            = [
				'initialClientDataReport' => esc_url( self::get_client_data_endpoint() ),
			];
			$amp_analytics_config['triggers']['initialClientDataReport'] = [
				'on'             => 'ini-load',
				'request'        => 'initialClientDataReport',
				'extraUrlParams' => array_merge(
					$initial_client_report_url_params,
					[
						'client_id' => self::get_cid_param(),
					]
				),
			];
		}

		?>
			<amp-analytics>
				<script type="application/json">
					<?php echo wp_json_encode( $amp_analytics_config ); ?>
				</script>
			</amp-analytics>
		<?php
	}

	/**
	 * Checks if the custom table has been created and is up-to-date.
	 * If not, run the create_database_tables method.
	 * See: https://codex.wordpress.org/Creating_Tables_with_Plugins
	 */
	public static function check_update_version() {
		$current_version = get_option( self::TABLE_VERSION_OPTION, false );

		if ( self::TABLE_VERSION !== $current_version ) {
			self::create_database_tables();
			update_option( self::TABLE_VERSION_OPTION, self::TABLE_VERSION );
		}
	}

	/**
	 * Create the clients and events tables.
	 */
	public static function create_database_tables() {
		global $wpdb;
		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$readers_table_name       = Segmentation::get_readers_table_name();
		$charset_collate          = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$readers_sql = "CREATE TABLE $readers_table_name (
			client_id varchar(100) NOT NULL,
			date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			date_modified datetime NOT NULL,
			reader_data longtext,
			is_preview bool,
			PRIMARY KEY  client_id (client_id),
			KEY is_preview (is_preview),
			KEY date_modified (date_modified)
		) $charset_collate;";

		dbDelta( $readers_sql ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbdelta

		$reader_events_sql = "CREATE TABLE $reader_events_table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			client_id varchar(100) NOT NULL,
			date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			type varchar(20) NOT NULL,
			context varchar(100) NOT NULL,
			value longtext,
			PRIMARY KEY  id (id),
			KEY client_id_type_context (client_id, type, context),
			KEY date_created (date_created)
		) $charset_collate;";

		dbDelta( $reader_events_sql ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbdelta
	}

	/**
	 * Get all configured segments.
	 *
	 * @return array Array of segments.
	 */
	public static function get_segments() {
		$segments                  = get_option( self::SEGMENTS_OPTION_NAME, [] );
		$segments_without_priority = array_filter(
			$segments,
			function( $segment ) {
				return ! isset( $segment['priority'] );
			}
		);

		// Failsafe to ensure that all segments have an assigned priority.
		if ( 0 < count( $segments_without_priority ) ) {
			$segments = self::reindex_segments( $segments );
		}

		// Filter out non-existing categories.
		$existing_categories_ids = get_categories(
			[
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);
		foreach ( $segments as &$segment ) {
			if ( ! isset( $segment['configuration']['favorite_categories'] ) ) {
				continue;
			}
			$fav_categories = $segment['configuration']['favorite_categories'];
			if ( ! empty( $fav_categories ) ) {
				$segment['configuration']['favorite_categories'] = array_values(
					array_intersect(
						$existing_categories_ids,
						$fav_categories
					)
				);
			}
		}
		return $segments;
	}

	/**
	 * Get a single segment by ID.
	 *
	 * @param string $id A segment id.
	 * @return object|null The single segment object with matching ID, or null.
	 */
	public static function get_segment( $id ) {
		$matching_segments = array_values(
			array_filter(
				self::get_segments(),
				function( $segment ) use ( $id ) {
					return $segment['id'] === $id;
				}
			)
		);

		if ( 0 < count( $matching_segments ) ) {
			return $matching_segments[0];
		}

		return null;
	}

	/**
	 * Get segment IDs.
	 */
	public static function get_segment_ids() {
		return array_map(
			function( $segment ) {
				return $segment['id'];
			},
			self::get_segments()
		);
	}

	/**
	 * Create a segment.
	 *
	 * @param object $segment A segment.
	 */
	public static function create_segment( $segment ) {
		$segments              = self::get_segments();
		$segment['id']         = uniqid();
		$segment['created_at'] = gmdate( 'Y-m-d' );
		$segment['updated_at'] = gmdate( 'Y-m-d' );
		$segments[]            = $segment;

		update_option( self::SEGMENTS_OPTION_NAME, $segments );
		return self::get_segments();
	}

	/**
	 * Delete a segment.
	 *
	 * @param string $id A segment id.
	 */
	public static function delete_segment( $id ) {
		$segments = array_values(
			array_filter(
				self::get_segments(),
				function( $segment ) use ( $id ) {
					return $segment['id'] !== $id;
				}
			)
		);
		update_option( self::SEGMENTS_OPTION_NAME, $segments );
		return self::get_segments();
	}

	/**
	 * Update a segment.
	 *
	 * @param object $segment A segment.
	 */
	public static function update_segment( $segment ) {
		$segments              = self::get_segments();
		$segment['updated_at'] = gmdate( 'Y-m-d' );
		foreach ( $segments as &$_segment ) {
			if ( $_segment['id'] === $segment['id'] ) {
				$_segment['name'] = $segment['name'];

				// Deprecate is_logged_in and is_not_logged_in option names in favor of has_user_account and no_user_account.
				if ( isset( $segment['is_logged_in'] ) ) {
					unset( $segment['is_logged_in'] );
				}
				if ( isset( $segment['is_not_logged_in'] ) ) {
					unset( $segment['is_not_logged_in'] );
				}

				$_segment['configuration'] = $segment['configuration'];
			}
		}

		update_option( self::SEGMENTS_OPTION_NAME, $segments );
		return self::get_segments();
	}

	/**
	 * Mock a preview CID for logged-in admin and editor users.
	 *
	 * @return string Preview client ID.
	 */
	private static function get_preview_user_cid() {
		return 'preview-user-' . \get_current_user_id();
	}

	/**
	 * Get the client ID placeholder used in AMP Access requests.
	 */
	public static function get_cid_param() {
		if ( Newspack_Popups::is_user_admin() ) {
			return self::get_preview_user_cid();
		}

		return 'CLIENT_ID(' . esc_attr( self::NEWSPACK_SEGMENTATION_CID_NAME ) . ')';
	}

	/**
	 * Get current client's id.
	 */
	public static function get_client_id() {
		if ( Newspack_Popups::is_user_admin() ) {
			return self::get_preview_user_cid();
		}

		return isset( $_COOKIE[ self::NEWSPACK_SEGMENTATION_CID_NAME ] ) ? esc_attr( $_COOKIE[ self::NEWSPACK_SEGMENTATION_CID_NAME ] ) : false; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Get API client data endpoint.
	 */
	public static function get_client_data_endpoint() {
		return plugins_url( '../api/segmentation/index.php', __FILE__ );
	}

	/**
	 * Update client data.
	 *
	 * @param string $client_id Client ID.
	 * @param string $payload Client data.
	 */
	public static function update_client_data( $client_id, $payload ) {
		if ( isset( $client_id ) ) {
			wp_safe_remote_post(
				self::get_client_data_endpoint(),
				[
					'body' => array_merge(
						[
							'client_id' => $client_id,
						],
						$payload
					),
				]
			);
		}
	}

	/**
	 * Get segment reach, based on recorded client data.
	 *
	 * @param object $segment_config Segment configuration.
	 * @return object Total clients amount and the amount covered by the segment.
	 */
	public static function get_segment_reach( $segment_config ) {
		$api             = self::load_lightweight_api();
		$all_client_data = wp_cache_get( 'newspack_popups_all_clients_data', 'newspack-popups' );
		if ( false === $all_client_data ) {
			$all_client_data = $api->get_all_readers_data();
			wp_cache_set( 'newspack_popups_all_clients_data', $all_client_data, 'newspack-popups' );
		}

		$client_in_segment = array_filter(
			$all_client_data,
			function ( $client_id ) use ( $api, $segment_config ) {
				return Campaign_Data_Utils::does_reader_match_segment(
					$segment_config,
					$api->get_readers( $client_id ),
					$api->get_reader_events( $client_id, Campaign_Data_Utils::get_reader_events_types() )
				);
			}
		);

		return [
			'total'      => count( $all_client_data ),
			'in_segment' => count( $client_in_segment ),
		];
	}

	/**
	 * Sort all segments by relative priority.
	 *
	 * @param array $segment_ids Array of segment IDs, in order of desired priority.
	 * @return array Array of sorted segments.
	 */
	public static function sort_segments( $segment_ids ) {
		$segments = get_option( self::SEGMENTS_OPTION_NAME, [] );
		$is_valid = self::validate_segment_ids( $segment_ids, $segments );

		if ( ! $is_valid ) {
			return new WP_Error(
				'invalid_segment_sort',
				__( 'Failed to sort due to outdated segment data. Please refresh and try again.', 'newspack-popups' )
			);
		}

		$sorted_segments = array_map(
			function( $segment_id ) use ( $segments ) {
				$segment = array_filter(
					$segments,
					function( $segment ) use ( $segment_id ) {
						return $segment['id'] === $segment_id;
					}
				);

				return reset( $segment );
			},
			$segment_ids
		);

		$sorted_segments = self::reindex_segments( $sorted_segments );
		update_option( self::SEGMENTS_OPTION_NAME, $sorted_segments );
		return self::get_segments();
	}

	/**
	 * Validate an array of segment IDs against the existing segment IDs in the options table.
	 * When re-sorting segments, the IDs passed should all exist, albeit in a different order,
	 * so if there are any differences, validation will fail.
	 *
	 * @param array $segment_ids Array of segment IDs to validate.
	 * @param array $segments    Array of existing segments to validate against.
	 * @return boolean Whether $segment_ids is valid.
	 */
	public static function validate_segment_ids( $segment_ids, $segments ) {
		$existing_ids = array_map(
			function( $segment ) {
				return $segment['id'];
			},
			$segments
		);

		return array_diff( $segment_ids, $existing_ids ) === array_diff( $existing_ids, $segment_ids );
	}

	/**
	 * Reindex segment priorities based on current position in array.
	 *
	 * @param object $segments Array of segments.
	 */
	public static function reindex_segments( $segments ) {
		$index = 0;

		return array_map(
			function( $segment ) use ( &$index ) {
				$segment['priority'] = $index;
				$index++;
				return $segment;
			},
			$segments
		);
	}

	/**
	 * Run the given query with chunks of up to 1000 rows, sleeping in between chunks.
	 * This helps avoid replication lag from deleting many records at once.
	 *
	 * @param string $query The query string to execute.
	 * @param array  $values If $query contains %s or %d placeholders, an array of values replace them.
	 */
	public static function query_with_sleep( $query, $values = [] ) {
		global $wpdb;
		$total     = 0;
		$processed = PHP_INT_MAX;
		$max_rows  = 1000; // Max number of records to process at once.

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare(
				"$query LIMIT $max_rows", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$values
			);
		} else {
			$sql = $wpdb->prepare( "$query LIMIT %d", $max_rows ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		while ( $processed > 0 ) {
			$start     = microtime( true );
			$processed = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$took      = microtime( true ) - $start;
			$total    += $processed;

			// Convert from float seconds to int microseconds.
			$sleep = round( $took * 1000000 );

			// Sleep 2x the time it took for the query to run.
			usleep( $sleep * 2 );
		}

		return $total;
	}

	/**
	 * Transform an array of strings into a part of an SQL query.
	 *
	 * @param string[] $items Array of strings to transform.
	 * @param string   $column_name Name of the column to include in the query.
	 */
	private static function array_to_in_query( $items, $column_name ) {
		$items = array_map(
			function( $item ) {
				return "'$item'";
			},
			$items
		);
		return $column_name . ' IN (' . implode( ',', $items ) . ')';
	}

	/**
	 * Remove unneeded data so the DB does not blow up.
	 * TODO: Ensure that client IDs on single-prompt previews are tagged as preview sessions.
	 */
	public static function prune_data() {
		global $wpdb;
		$readers_table_name       = Segmentation::get_readers_table_name();
		$reader_events_table_name = Segmentation::get_reader_events_table_name();

		// Remove all preview sessions data.
		$removed_preview_readers = 0;
		$removed_preview_events  = 0;
		$preview_client_ids      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			"SELECT client_id FROM $readers_table_name WHERE is_preview = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( 0 < count( $preview_client_ids ) ) {
			$preview_client_ids      = implode(
				',',
				array_map(
					function( $row ) {
						return "'$row->client_id'";
					},
					$preview_client_ids
				)
			);
			$removed_preview_readers = self::query_with_sleep( "DELETE FROM $readers_table_name WHERE is_preview = 1" );
			$removed_preview_events  = self::query_with_sleep(
				"DELETE FROM $reader_events_table_name WHERE client_id IN ( $preview_client_ids )"
			);
		}

		// Remove reader data if not containing donations nor subscriptions, and not updated in $days days.
		$days                     = 30;
		$removed_old_readers      = 0;
		$removed_old_events       = 0;
		$old_client_ids           = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS `client_id` FROM $readers_table_name WHERE date_modified < now() - interval %d DAY", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$days
			)
		);
		$old_client_ids_to_delete = [];
		foreach ( $old_client_ids as $row ) {
			$protected_events_types = Campaign_Data_Utils::get_protected_events_types();
			$sql_query              = $wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS `client_id` FROM $reader_events_table_name WHERE client_id = %s AND " . self::array_to_in_query( $protected_events_types, 'type' ) . ' ORDER BY date_created', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				$row->client_id
			);
			$protected_events       = $wpdb->get_results( $sql_query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			if ( 0 === count( $protected_events ) ) {
				$old_client_ids_to_delete[] = $row->client_id;
			}
		}
		if ( 0 < count( $old_client_ids_to_delete ) ) {
			$client_id_placeholders = array_map(
				function() {
					return '%s';
				},
				$old_client_ids_to_delete
			);
			$client_id_placeholders = implode( ', ', $client_id_placeholders );
			$removed_old_readers    = self::query_with_sleep(
				"DELETE FROM $readers_table_name WHERE client_id IN ( $client_id_placeholders )",
				$old_client_ids_to_delete
			);
			$removed_old_events     = self::query_with_sleep(
				"DELETE FROM $reader_events_table_name WHERE client_id IN ( $client_id_placeholders )",
				$old_client_ids_to_delete
			);
		}

		// Remove article_view and page_view events older than 1 hour.
		$removed_rows_count_page_view_events = self::query_with_sleep(
			"DELETE FROM $reader_events_table_name WHERE ( type = 'view' ) AND date_created < now() - interval 1 HOUR"
		);

		// Remove prompt interaction events older than $days days.
		$removed_row_counts_prompt_seen_events = self::query_with_sleep(
			"DELETE FROM $reader_events_table_name WHERE ( type = 'prompt_seen' OR type = 'prompt_dismissed' ) AND date_created < now() - interval $days DAY"
		);

		if ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) {
			return;
		}

		$logger_function = defined( 'WP_CLI' ) ? '\WP_CLI::log' : 'error_log';

		if ( $removed_preview_readers ) {
			$logger_function( 'Newspack Campaigns: Data pruning – removed ' . $removed_preview_readers . ' preview session rows from ' . $readers_table_name . ' table.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		if ( $removed_preview_events ) {
			$logger_function( 'Newspack Campaigns: Data pruning – removed ' . $removed_preview_events . ' preview session rows from ' . $reader_events_table_name . ' table.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		if ( $removed_old_readers ) {
			$logger_function( 'Newspack Campaigns: Data pruning – removed ' . $removed_old_readers . ' old rows from ' . $readers_table_name . ' table.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		if ( $removed_old_events ) {
			$logger_function( 'Newspack Campaigns: Data pruning – removed ' . $removed_old_events . ' old rows from ' . $reader_events_table_name . ' table.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		if ( $removed_rows_count_page_view_events ) {
			$logger_function( 'Newspack Campaigns: Data pruning – removed ' . $removed_rows_count_page_view_events . ' article/page view events from ' . $reader_events_table_name . ' table.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		if ( $removed_row_counts_prompt_seen_events ) {
			$logger_function( 'Newspack Campaigns: Data pruning – removed ' . $removed_row_counts_prompt_seen_events . ' prompt seen events from ' . $reader_events_table_name . ' table.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Handle reader registration.
	 *
	 * @param string       $email Email address.
	 * @param bool         $authenticate Whether the user was authenticated.
	 * @param int          $user_id New user ID.
	 * @param null|WP_User $existing_user If the reader already has an account, the user object.
	 */
	public static function handle_registered_reader( $email, $authenticate, $user_id, $existing_user ) {
		if ( empty( $user_id ) && $existing_user && isset( $existing_user->ID ) ) {
			$user_id = $existing_user->ID;
		}

		$action = $existing_user ? 'authenticate' : 'register';

		if ( ! empty( $user_id ) ) {
			try {
				$api = \Campaign_Data_Utils::get_api( \wp_create_nonce( 'newspack_campaigns_lightweight_api' ) );
				if ( ! $api ) {
					return;
				}
				$reader_events = [
					[
						'type'    => 'user_account',
						'context' => $user_id,
						'value'   => [
							'action' => $action,
						],
					],
				];
				$api->save_reader_events( self::get_client_id(), $reader_events );
			} catch ( \Throwable $th ) {
				\Newspack\Logger::log( 'Error when saving reader data on registration: ' . $th->getMessage() );
			}
		}
	}
}
Newspack_Popups_Segmentation::instance();
