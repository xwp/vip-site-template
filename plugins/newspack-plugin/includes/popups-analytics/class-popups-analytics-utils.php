<?php
/**
 * Popups Analytics utils.
 *
 * @package Newspack
 */

use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting\DateRange as Google_Service_AnalyticsReporting_DateRange;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting\Metric as Google_Service_AnalyticsReporting_Metric;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting\ReportRequest as Google_Service_AnalyticsReporting_ReportRequest;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting\Dimension as Google_Service_AnalyticsReporting_Dimension;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting\GetReportsRequest as Google_Service_AnalyticsReporting_GetReportsRequest;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting\DimensionFilter as Google_Service_AnalyticsReporting_DimensionFilter;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting as Google_Service_AnalyticsReporting;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsReporting\DimensionFilterClause as Google_Service_AnalyticsReporting_DimensionFilterClause;

/**
 * Popup Analytics Utilities.
 */
class Popups_Analytics_Utils {
	// Category is fixed, it identifies the GA custom event.
	const EVENT_CATEGORIES = [
		'Newspack Announcement', // Legacy category name.
		'NC',
	];

	/**
	 * Get dates in range.
	 *
	 * @param Date   $start start date.
	 * @param Date   $end end date.
	 * @param string $format date format.
	 * @return array array of dates in the given range.
	 */
	private static function get_dates_in_range( $start, $end, $format = 'Y-m-d' ) {
		return array_map(
			function( $timestamp ) use ( $format ) {
				return gmdate( $format, $timestamp );
			},
			range( strtotime( $start ) + ( $start < $end ? 4000 : 8000 ), strtotime( $end ) + ( $start < $end ? 8000 : 4000 ), 86400 )
		);
	}

	/**
	 * Fill in dates.
	 *
	 * @param array    $input array of days.
	 * @param DateTime $start_date start date.
	 * @param DateTime $end_date end date.
	 * @return array array of dates.
	 */
	private static function fill_in_dates( $input, $start_date, $end_date ) {
		$all_days = self::get_dates_in_range( $start_date, $end_date );

		$chart_axes  = [ [ __( 'Date', 'newspack' ), __( 'Views', 'newspack' ) ] ];
		$days_filled = array_map(
			function ( $day ) use ( $input ) {
				$date = new \DateTime( $day );
				return [ $date->format( 'M j' ), isset( $input[ $day ] ) ? intval( $input[ $day ] ) : 0 ];
			},
			$all_days
		);

		return array_merge( $chart_axes, $days_filled );
	}

	/**
	 * Get results of a GA report, taking page token into account.
	 *
	 * @param Analytics $analytics The Analytics.
	 * @param object    $options Options.
	 * @param string    $page_token Page token for the report.
	 * @return object GA report.
	 */
	private static function create_ga_report_result( $analytics, $options, $page_token = null ) {
		$date_range = $options['date_range'];
		// Create the Metrics object.
		$metrics = new Google_Service_AnalyticsReporting_Metric();
		$metrics->setExpression( 'ga:totalEvents' );
		$metrics->setAlias( 'events' );

		// Set up filters.
		$filters = [];

		// Filter just the popups custom event category.
		$dimension_category_filter = new Google_Service_AnalyticsReporting_DimensionFilter();
		$dimension_category_filter->setDimensionName( 'ga:eventCategory' );
		$dimension_category_filter->setOperator( 'IN_LIST' );
		$dimension_category_filter->setExpressions( self::EVENT_CATEGORIES );
		$filters[] = $dimension_category_filter;

		if ( ! empty( $options['event_action'] ) ) {
			$dimension_action_filter = new Google_Service_AnalyticsReporting_DimensionFilter();
			$dimension_action_filter->setDimensionName( 'ga:eventAction' );
			$dimension_action_filter->setOperator( 'IN_LIST' );
			$dimension_action_filter->setExpressions( [ $options['event_action'] ] );
			$filters[] = $dimension_action_filter;
		}
		if ( ! empty( $options['event_label_id'] ) ) {
			$dimension_label_id_filter = new Google_Service_AnalyticsReporting_DimensionFilter();
			$dimension_label_id_filter->setDimensionName( 'ga:eventLabel' );
			$dimension_label_id_filter->setOperator( 'PARTIAL' );
			$dimension_label_id_filter->setExpressions( $options['event_label_id'] );
			$filters[] = $dimension_label_id_filter;
		}

		// Create the DimensionFilterClauses.
		$dimension_filter_clause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
		$dimension_filter_clause->setFilters( $filters );
		$dimension_filter_clause->setOperator( 'AND' );

		// Create the Dimension objects.
		$date_dimension = new Google_Service_AnalyticsReporting_Dimension();
		$date_dimension->setName( 'ga:date' );
		$action_dimension = new Google_Service_AnalyticsReporting_Dimension();
		$action_dimension->setName( 'ga:eventAction' );
		$label_dimension = new Google_Service_AnalyticsReporting_Dimension();
		$label_dimension->setName( 'ga:eventLabel' );

		// Create the ReportRequest object.
		$profile_id = $analytics->get_settings()->get()['profileID'];
		$request    = new Google_Service_AnalyticsReporting_ReportRequest();
		$request->setViewId( $profile_id );
		$request->setDateRanges( $date_range );
		$request->setDimensions(
			array(
				$date_dimension,
				$action_dimension,
				$label_dimension,
			)
		);
		$request->setMetrics( array( $metrics ) );
		$request->setDimensionFilterClauses( array( $dimension_filter_clause ) );
		if ( null !== $page_token ) {
			$request->setPageToken( $page_token );
		}

		$body = new Google_Service_AnalyticsReporting_GetReportsRequest();
		$body->setReportRequests( array( $request ) );
		$client = \Newspack\Google_Services_Connection::get_site_kit_oauth_client()->get_client();

		$analyticsreporting = new Google_Service_AnalyticsReporting( $client );
		// https://developers.google.com/analytics/devguides/reporting/core/v4/rest/v4/reports/batchGet.
		$report_results = $analyticsreporting->reports->batchGet( $body );
		if ( isset( $report_results->reports[0] ) ) {
			return $report_results->reports[0];
		}
	}

	/**
	 * Get GA report.
	 *
	 * @param Object $options options.
	 * @return array array of rows with GA report data.
	 */
	private static function get_ga_report( $options ) {
		// Load and query analytics.
		$analytics = \Newspack\Google_Services_Connection::get_site_kit_analytics_module();

		if ( $analytics && $analytics->is_connected() ) {
			// Create the DateRange object.
			$date_range = new Google_Service_AnalyticsReporting_DateRange();
			$date_range->setStartDate( $options['start_date'] );
			$date_range->setEndDate( $options['end_date'] );
			$options['date_range'] = $date_range;

			try {
				$rows   = [];
				$report = self::create_ga_report_result( $analytics, $options );
				$rows   = array_merge( $rows, $report->data->rows );
				while ( $report->nextPageToken ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$report = self::create_ga_report_result( $analytics, $options, $report->nextPageToken ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$rows   = array_merge( $rows, $report->data->rows );
				}
				return $rows;
			} catch ( \Exception $error ) {
				$error_data = json_decode( $error->getMessage() );
				if ( isset( $error_data ) && 'UNAUTHENTICATED' === $error_data->error->status ) {
					return new WP_Error( 'newspack_campaign_analytics_sitekit_auth', __( 'Please authenticate with Google Analytics to view Campaign analytics.', 'newspack' ) );
				}
				return new WP_Error( 'newspack_campaign_analytics', __( 'Google Analytics data fetching error.', 'newspack' ), $error_data );
			}
		} else {
			return new WP_Error( 'newspack_campaign_analytics_sitekit_disconnected', __( 'Connect Site Kit plugin to view Campaign Analytics.', 'newspack' ) );
		}
	}

	/**
	 * GA custom event format handling - where:
	 * - label - `<type>: <title> (<id>) - <segment>` or `Newspack Announcement: <title> <id>` (old times)
	 * - action - plain text action (e.g. `Dismissal`, `Seen`)
	 *
	 * @param object $row Report result row.
	 * @return object Action & label objects.
	 */
	private static function process_ga_event_row( $row ) {
		$action_object = [
			'label' => $row['dimensions'][1],
			'value' => $row['dimensions'][1],
		];
		$label         = str_replace( 'Newspack Announcement: ', '', $row['dimensions'][2] );
		// Extract post id from the label.
		preg_match(
			'/\(([0-9]*)\)/',
			$label,
			$id_matches
		);
		$post_id      = array_pop( $id_matches ) ?? '';
		$label_object = [
			// Remove post id in parens.
			'label' => str_replace( " ($post_id)", '', $label ),
			'value' => $post_id,
		];
		return [
			'action' => $action_object,
			'label'  => $label_object,
		];
	}

	/**
	 * Decode event name, as it was reported to GA (`<popup-id><event-code>`).
	 * See Newspack_Popups_Model::encode_event_name.
	 *
	 * @param string $name Encoded event name.
	 */
	private static function decode_item( $name ) {
		preg_match( '/(\d*)(\d$)/', $name, $matches );
		if ( count( $matches ) === 3 ) {
			return [
				'post_id'    => $matches[1],
				'event_name' => self::get_event_name( $matches[2] ),
			];
		}
		return [];
	}

	/**
	 * Translate an event index to an event name.
	 * For external use, to decode an event name saved in GA.
	 *
	 * @param int $index Event Index.
	 * @return string Event name.
	 */
	private static function get_event_name( $index ) {
		$event_names = [
			esc_html__( 'Load', 'newspack-popups' ),
			esc_html__( 'Seen', 'newspack-popups' ),
			esc_html__( 'Link Click', 'newspack-popups' ),
			esc_html__( 'Form Submission', 'newspack-popups' ),
			esc_html__( 'Dismissal', 'newspack-popups' ),
			esc_html__( 'Permanent Dismissal', 'newspack-popups' ),
		];
		return $event_names[ $index ];
	}

	/**
	 * Get report.
	 *
	 * @param Object $options options.
	 * @return Object report data.
	 */
	public static function get_report( $options ) {
		return self::process_ga_report( self::get_ga_report( $options ), $options );
	}

	/**
	 * Process data for a report.
	 *
	 * @param array  $ga_data_rows Raw data from a GA report.
	 * @param Object $options Options.
	 * @return Object Report data.
	 */
	public static function process_ga_report( $ga_data_rows, $options ) {
		$event_label_id = $options['event_label_id'];

		if ( is_wp_error( $ga_data_rows ) ) {
			return $ga_data_rows;
		}

		$all_actions = [];
		$all_labels  = [];

		// Key metrics.
		$aggregate_seen_events            = 0;
		$aggregate_form_submission_events = -1;
		$aggregate_link_click_events      = -1;

		// Report by popup ID.
		$report_by_id = [];

		$post_edit_link = null;

		$ga_data_days = array_reduce(
			$ga_data_rows,
			function ( $days, $row ) use ( $event_label_id, $options, &$all_actions, &$all_labels, &$aggregate_seen_events, &$aggregate_form_submission_events, &$aggregate_link_click_events, &$post_edit_link, &$report_by_id ) {
				$label_dimension_value = $row['dimensions'][2];
				$has_label             = '(not set)' !== $label_dimension_value;

				if ( $has_label ) {
					$item          = self::process_ga_event_row( $row );
					$label_object  = $item['label'];
					$action_object = $item['action'];
				} else {
					$action_dimension_value = $row['dimensions'][1];
					$decoded_popup_data     = self::decode_item( $action_dimension_value );
					$label_object           = [
						'label' => get_the_title( $decoded_popup_data['post_id'] ), // Popup title.
						'value' => $decoded_popup_data['post_id'], // Popup post id.
					];
					$action_object          = [
						'label' => $decoded_popup_data['event_name'], // Event type.
						'value' => $decoded_popup_data['event_name'], // Event type.
					];
				}
				$post_id = $label_object['value'];

				if ( $post_id ) {
					$value = $row['metrics'][0]['values'][0];

					if ( isset( $options['with_report_by_id'] ) ) {
						$action = strtolower( preg_replace( '/\s/', '_', $action_object['value'] ) );
						if ( isset( $report_by_id[ $post_id ] ) ) {
							if ( isset( $report_by_id[ $post_id ][ $action ] ) ) {
								$report_by_id[ $post_id ][ $action ] += $value;
							} else {
								$report_by_id[ $post_id ][ $action ] = (int) $value;
							}
						} else {
							$report_by_id[ $post_id ] = [ $action => (int) $value ];
						}
					}

					$post = get_post( $post_id, ARRAY_A );
					if ( isset( $post['post_content'] ) ) {
						$post_content = $post['post_content'];
						if (
							-1 === $aggregate_link_click_events &&
							strpos( $post_content, '<a ' ) !== false
						) {
							$aggregate_link_click_events = 0;
						}
						if (
							-1 === $aggregate_form_submission_events &&
							strpos( $post_content, '<!-- wp:jetpack/mailchimp' ) !== false ||
							strpos( $post_content, '<!-- wp:mailchimp-for-wp/form' ) !== false ||
							strpos( $post_content, '<form' ) !== false
						) {
							$aggregate_form_submission_events = 0;
						}

						if ( '' != $event_label_id && get_post_status( $post['ID'] ) == 'publish' ) {
							$post_edit_link = get_edit_post_link( $post['ID'] );
						}
					}

					// Key metrics.
					if ( 'Seen' == $action_object['value'] ) {
						$aggregate_seen_events = $aggregate_seen_events + $value;
					}
					if ( 'Form Submission' == $action_object['value'] ) {
						$aggregate_form_submission_events = $aggregate_form_submission_events + $value;
					}
					if ( 'Link Click' == $action_object['value'] ) {
						$aggregate_link_click_events = $aggregate_link_click_events + $value;
					}

					if ( ! in_array( $action_object, $all_actions ) ) {
						$all_actions[] = $action_object;
					}

					if ( $post_id ) {
						// Last event with this label will be the final one.
						// If a popup was renamed, the last title will end up as the label.
						$all_labels[ $post_id ] = $label_object;
					}

					$parsed_date = date_create( $row['dimensions'][0] );
					$date        = date_format( $parsed_date, 'Y-m-d' );
					if ( isset( $days[ $date ] ) ) {
						$value = $days[ $date ] + $value;
					}
					$days[ $date ] = $value;
				}

				return $days;
			},
			array()
		);

		// Key metrics.
		$key_metrics = array(
			'seen'             => $aggregate_seen_events,
			'form_submissions' => $aggregate_form_submission_events,
			'link_clicks'      => $aggregate_link_click_events,
		);

		return array(
			'report'         => self::fill_in_dates( $ga_data_days, $options['start_date'], $options['end_date'] ),
			'report_by_id'   => $report_by_id,
			'actions'        => $all_actions,
			'labels'         => array_values( $all_labels ),
			'key_metrics'    => $key_metrics,
			'post_edit_link' => isset( $post_edit_link ) ? $post_edit_link : false,
		);
	}
}
