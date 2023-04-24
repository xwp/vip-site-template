<?php
/**
 * Newspack Campaigns maybe display prompt.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once dirname( __FILE__ ) . '/../classes/class-lightweight-api.php';
require_once dirname( __FILE__ ) . '/class-campaign-data-utils.php';

/**
 * GET endpoint to determine if prompt is shown or not.
 */
class Maybe_Show_Campaign extends Lightweight_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		if ( ! isset( $_REQUEST['popups'], $_REQUEST['settings'], $_REQUEST['cid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$popups    = json_decode( $_REQUEST['popups'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings  = json_decode( $_REQUEST['settings'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$visit     = json_decode( $_REQUEST['visit'], true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$response  = [];
		$client_id = $_REQUEST['cid']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_id   = isset( $_REQUEST['uid'] ) ? absint( $_REQUEST['uid'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$view_as_spec = [];
		if ( ! empty( $_REQUEST['view_as'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$view_as_spec = Segmentation::parse_view_as( json_decode( $_REQUEST['view_as'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		// Log an article or page view event.
		if ( ! defined( 'DISABLE_CAMPAIGN_EVENT_LOGGING' ) || true !== DISABLE_CAMPAIGN_EVENT_LOGGING ) {
			$reader_events = [];
			$view_event    = $this->convert_visit_to_event( $client_id, $visit );

			// Handle user accounts.
			if ( ! empty( $user_id ) ) {
				$existing_user_accounts = $this->get_reader_events( $client_id, 'user_account', $user_id );
				if ( 0 === count( $existing_user_accounts ) ) {
					$reader_events[] = [
						'type'    => 'user_account',
						'context' => $user_id,
					];
				}
			}

			// Filter out recently seen views.
			if ( ! empty( $view_event ) ) {
				$reader_events[] = $view_event;
			}

			$referer_url                   = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$page_referer_url              = isset( $_REQUEST['ref'] ) ? $_REQUEST['ref'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$all_segments                  = isset( $settings->all_segments ) ? $settings->all_segments : [];
			$overlay_to_maybe_display      = null;
			$above_header_to_maybe_display = null;
			$custom_placements_displayed   = [];

			// Get Mailchimp subscriber data.
			$mailchimp_campaign_id   = $this->get_url_param( 'mc_cid', $referer_url );
			$mailchimp_subscriber_id = $this->get_url_param( 'mc_eid', $referer_url );
			if ( $mailchimp_campaign_id && $mailchimp_subscriber_id ) {
				$this->get_mailchimp_client_data( $client_id, $mailchimp_campaign_id, $mailchimp_subscriber_id );
			} elseif ( Campaign_Data_Utils::is_url_from_email( $referer_url ) ) {
				// If reader is coming from a newsletter email, consider them a subscriber.
				$reader_events[] = [
					'type'    => 'subscription',
					'context' => 'mailchimp',
					'value'   => [ 'source' => 'utm_medium=email' ],
				];
			}

			$reader = $this->get_reader( $client_id );

			if ( isset( $reader['client_id'] ) && ! empty( $reader_events ) ) {
				$this->save_reader_events( $client_id, $reader_events );
			}
		}

		if ( $settings ) {
			$settings->best_priority_segment_id = $this->get_best_priority_segment_id( $all_segments, $client_id, $referer_url, $page_referer_url, $view_as_spec );

			if ( $this->is_debug_enabled() ) {
				$this->debug['matching_segment'] = $settings->best_priority_segment_id;
			}
		}

		// Check each matching popup against other global factors.
		foreach ( $popups as $popup ) {
			$popup_should_be_shown = $this->should_popup_be_shown(
				$client_id,
				$popup,
				$settings,
				$referer_url,
				$page_referer_url,
				$view_as_spec,
				false
			);

			// If an overlay is already able to be shown, pick the one that has the higher priority.
			if ( $popup_should_be_shown && Campaign_Data_Utils::is_overlay( $popup ) ) {
				if ( empty( $overlay_to_maybe_display ) ) {
					$overlay_to_maybe_display = $popup;
				} else {
					$higher_priority_item = self::get_higher_priority_item( $overlay_to_maybe_display, $popup, $all_segments );

					// If the previous overlay already has a higher priority, only show that one. Otherwise, show this one instead.
					$response[ $overlay_to_maybe_display->id ] = $overlay_to_maybe_display->id === $higher_priority_item->id;
					$popup_should_be_shown                     = $popup->id === $higher_priority_item->id;
					if ( false === $popup_should_be_shown ) {
						self::add_suppression_reason( $popup->id, __( 'Another overlay prompt already displayed.', 'newspack-popups' ) );
					}
					$overlay_to_maybe_display = $higher_priority_item;
				}
			}

			// TODO: the conditions below should not apply to manually-placed prompts.

			// If an above-header is already able to be shown, pick the one that has the higher priority.
			if ( $popup_should_be_shown && Campaign_Data_Utils::is_above_header( $popup ) ) {
				if ( empty( $above_header_to_maybe_display ) ) {
					$above_header_to_maybe_display = $popup;
				} else {
					$higher_priority_item = self::get_higher_priority_item( $above_header_to_maybe_display, $popup, $all_segments );

					// If the previous above-header already has a higher priority, only show that one. Otherwise, show this one instead.
					$response[ $above_header_to_maybe_display->id ] = $above_header_to_maybe_display->id === $higher_priority_item->id;
					$popup_should_be_shown                          = $popup->id === $higher_priority_item->id;
					if ( false === $popup_should_be_shown ) {
						self::add_suppression_reason( $popup->id, __( 'Another above-header prompt already displayed.', 'newspack-popups' ) );
					}
					$above_header_to_maybe_display = $higher_priority_item;
				}
			}

			// Handle custom placements: Only one prompt should be shown per placement block.
			// "Everyone" prompts should only be shown if the reader doesn't match any segments.
			if ( $popup_should_be_shown && ! empty( $popup->c ) ) {
				if ( ! isset( $custom_placements_displayed[ $popup->c ] ) ) {
					$custom_placements_displayed[ $popup->c ] = $popup;
				} else {
					$previous_item        = $custom_placements_displayed[ $popup->c ];
					$higher_priority_item = self::get_higher_priority_item( $previous_item, $popup, $all_segments );

					// If the previous prompt in this custom placement already has a higher priority, only show that one. Otherwise, show this one instead.
					$response[ $previous_item->id ] = $previous_item->id === $higher_priority_item->id;
					$popup_should_be_shown          = $popup->id === $higher_priority_item->id;
					if ( false === $popup_should_be_shown ) {
						self::add_suppression_reason( $popup->id, __( 'Prompt in this custom placement already displayed.', 'newspack-popups' ) );
					}
					$custom_placements_displayed[ $popup->c ] = $higher_priority_item;
				}
			}

			$response[ $popup->id ] = $popup_should_be_shown;
		}

		if ( ! $this->ignore_cache ) {
			wp_cache_set( 'reader_cache_version', $this->reader_cache_version, $client_id );
		}

		$this->response = $response;
		$this->respond();
	}

	/**
	 * Get the best-priority segment that matches the client.
	 *
	 * @param object      $all_segments Segments to check.
	 * @param string      $client_id Client ID.
	 * @param string      $referer_url URL of the page performing the API request.
	 * @param string      $page_referer_url URL of the referrer of the frontend page that is making the API request.
	 * @param object|bool $view_as_spec View as spec.
	 *
	 * @return string|null ID of the best matching segment, or null if the client matches no segment.
	 */
	public function get_best_priority_segment_id( $all_segments, $client_id, $referer_url = '', $page_referer_url = '', $view_as_spec = false ) {
		// If using "view as" feature, automatically make that the matching segment. Otherwise, find the matching segment with the best priority.
		if ( $view_as_spec && isset( $view_as_spec['segment'] ) ) {
			return $view_as_spec['segment'];
		}

		$readers                  = $this->get_readers( $client_id );
		$reader_events            = $this->get_reader_events( $client_id, Campaign_Data_Utils::get_reader_events_types() );
		$best_segment_priority    = PHP_INT_MAX;
		$best_priority_segment_id = null;

		foreach ( $all_segments as $segment_id => $segment ) {
			// Determine whether the client matches the segment criteria.
			$segment = Campaign_Data_Utils::canonize_segment( $segment );

			// Ignore disabled segments.
			if ( true === $segment->is_disabled ) {
				continue;
			}

			$client_matches_segment = Campaign_Data_Utils::does_reader_match_segment(
				$segment,
				$readers,
				$reader_events,
				$referer_url,
				$page_referer_url
			);

			// Find the matching segment with the best priority.
			if ( $client_matches_segment && $segment->priority < $best_segment_priority ) {
				$best_segment_priority    = $segment->priority;
				$best_priority_segment_id = $segment_id;
			}
		}

		return $best_priority_segment_id;
	}

	/**
	 * Add suppression reason to debug output.
	 *
	 * @param string $id Prompt ID.
	 * @param string $reason The reason.
	 */
	private function add_suppression_reason( $id, $reason ) {
		if ( $this->is_debug_enabled() ) {
			if ( isset( $this->debug['suppression'][ $id ] ) ) {
				$this->debug['suppression'][ $id ][] = $reason;
			}
			$this->debug['suppression'][ $id ] = [ $reason ];
		}
	}

	/**
	 * Primary prompt visibility logic.
	 *
	 * @param string $client_id Client ID.
	 * @param object $popup Prompt.
	 * @param object $settings Settings.
	 * @param string $referer_url URL of the page performing the API request.
	 * @param string $page_referer_url URL of the referrer of the frontend page that is making the API request.
	 * @param object $view_as_spec "View As" specification.
	 * @param string $now Current timestamp.
	 *
	 * @return bool Whether prompt should be shown.
	 */
	public function should_popup_be_shown( $client_id, $popup, $settings, $referer_url = '', $page_referer_url = '', $view_as_spec = false, $now = false ) {
		$should_display = true;

		// Handle referer-based conditions.
		if ( ! empty( $referer_url ) ) {
			// Suppressing based on UTM Source parameter in the URL.
			$utm_suppression = ! empty( $popup->utm ) ? urldecode( $popup->utm ) : null;
			if ( $utm_suppression && stripos( urldecode( $referer_url ), 'utm_source=' . $utm_suppression ) ) {
				$should_display = false;
				self::add_suppression_reason( $popup->id, __( 'utm_source from prompt settings matched.', 'newspack-popups' ) );
			}
		}

		// Handle segmentation.
		$popup_segment_ids = ! empty( $popup->s ) ? explode( ',', $popup->s ) : [];

		// Using "view as" feature.
		if ( $view_as_spec ) {
			$should_display = false;
			if ( isset( $view_as_spec['segment'] ) && $view_as_spec['segment'] ) {
				// Show prompts with matching segments, or "everyone". Don't show any prompts that don't match the previewed segment.
				if ( in_array( $view_as_spec['segment'], $popup_segment_ids ) || empty( $popup->s ) ) {
					$should_display = true;
				}

				// Show prompts if the view_as segment doesn't exist.
				if ( 'everyone' !== $view_as_spec['segment'] && ! property_exists( $settings->all_segments, $view_as_spec['segment'] ) ) {
					$should_display = true;
				}
			}
		} elseif ( $should_display && ! empty( $popup_segment_ids ) && ! empty( $settings ) ) {
			// $settings->best_priority_segment_id should always be present, but in case it's not (e.g. in a unit test), we can fetch it here.
			$best_priority_segment_id = isset( $settings->best_priority_segment_id ) ?
				$settings->best_priority_segment_id :
				$this->get_best_priority_segment_id( $settings->all_segments, $client_id, $referer_url, $page_referer_url, $view_as_spec );

			// Only factor in the best-priority segment.
			$is_best_priority = ! empty( $best_priority_segment_id ) ? in_array( $best_priority_segment_id, $popup_segment_ids ) : false;
			$popup_segment    = $is_best_priority ? $settings->all_segments->{$best_priority_segment_id} : false;
			$segment_matches  =
				$popup_segment ?
				Campaign_Data_Utils::does_reader_match_segment(
					Campaign_Data_Utils::canonize_segment( $popup_segment ),
					$this->get_readers( $client_id ),
					$this->get_reader_events( $client_id, Campaign_Data_Utils::get_reader_events_types() ),
					$referer_url,
					$page_referer_url
				) :
				false;
			$should_display   = $is_best_priority && $segment_matches;

			if ( false === $should_display ) {
				if ( $segment_matches ) {
					self::add_suppression_reason( $popup->id, __( 'Segment matches, but another segment has higher priority.', 'newspack-popups' ) );
				} else {
					self::add_suppression_reason( $popup->id, __( 'Segment does not match.', 'newspack-popups' ) );
				}
			}
		}

		// If the prompt is already suppressed, no need to proceed.
		if ( ! $should_display ) {
			return $should_display;
		}

		// Handle frequency.
		$frequency         = $popup->f;
		$frequency_max     = (int) $popup->fm;
		$frequency_start   = (int) $popup->fs;
		$frequency_between = (int) $popup->fb;
		$frequency_reset   = $popup->ft;

		// Override individual settings if a frequency preset is selected.
		if ( 'once' === $frequency ) {
			$frequency_max     = 1;
			$frequency_start   = 0;
			$frequency_between = 0;
			$frequency_reset   = 'month';
		}
		if ( 'daily' === $frequency ) {
			$frequency_max     = 1;
			$frequency_start   = 0;
			$frequency_between = 0;
			$frequency_reset   = 'day';
		}
		if ( 'always' === $frequency ) {
			$frequency_max     = 0;
			$frequency_start   = 0;
			$frequency_between = 0;
			$frequency_reset   = 'month';
		}

		if ( false === $now ) {
			$now = time();
		}

		$reader      = $this->get_reader( $client_id );
		$seen_events = $this->get_reader_events( $client_id, 'prompt_seen', $popup->id );
		$total_views = 0;

		// Tally up pageviews of any post type.
		if ( isset( $reader['reader_data']['views'] ) ) {
			foreach ( $reader['reader_data']['views'] as $post_type => $views ) {
				$total_views += (int) $views;
			}
		}

		// Guard against invalid or missing reset period values.
		if ( ! in_array( $frequency_reset, [ 'month', 'week', 'day' ], true ) ) {
			$frequency_reset = 'month';
		}

		// Filter seen events for the relevant period.
		$seen_events = array_filter(
			$seen_events,
			function( $event ) use ( $frequency_reset, $now ) {
				$seen = strtotime( $event['date_created'] );
				return $seen >= strtotime( '-1 ' . $frequency_reset, $now );
			}
		);

		// If not displaying every pageview.
		if ( 0 < $frequency_between ) {
			$views_after_start = max( 0, $total_views - ( $frequency_start + 1 ) );

			if ( 0 < $views_after_start % ( $frequency_between + 1 ) ) {
				$should_display = false;
				self::add_suppression_reason(
					$popup->id,
					sprintf(
						// Translators: Suppression debug message.
						__( 'Prompt should only be displayed once every %d pageviews.', 'newspack-popups' ),
						$frequency_between + 1
					)
				);
			}
		}

		// If reader hasn't viewed enough articles yet.
		if ( 0 < $total_views && $total_views <= $frequency_start ) {
			$should_display = false;
			self::add_suppression_reason( $popup->id, __( 'Minimum pageviews not yet met.', 'newspack-popups' ) );
		}

		// If there's a max frequency.
		if ( 0 < $frequency_max && count( $seen_events ) >= $frequency_max ) {
			$should_display = false;
			self::add_suppression_reason(
				$popup->id,
				sprintf(
					// Translators: Suppression debug message.
					__( 'Max displays met for the %s.', 'newspack-popups' ),
					$frequency_reset
				)
			);
		}

		return $should_display;
	}

	/**
	 * Compare two campaign objects and return the one with the higher segment priority (lower priority index).
	 * If both have equal priority, just return the first one.
	 *
	 * @param object $popup_a First campaign to compare.
	 * @param object $popup_b Second campaign to compare.
	 * @param array  $segments   Array of segments, to extract priority values from.
	 * @return integer The campaign with the higher priority.
	 */
	public function get_higher_priority_item( $popup_a, $popup_b, $segments ) {
		$priority_a = ! empty( $segments->{$popup_a->s}->priority ) ? $segments->{$popup_a->s}->priority : PHP_INT_MAX;
		$priority_b = ! empty( $segments->{$popup_b->s}->priority ) ? $segments->{$popup_b->s}->priority : PHP_INT_MAX;

		if ( $priority_a <= $priority_b ) {
			return $popup_a;
		}

		return $popup_b;
	}
}
new Maybe_Show_Campaign();
