<?php
/**
 * Newspack Campaigns API utils.
 *
 * @package Newspack
 */

/**
 * Utils.
 */
class Campaign_Data_Utils {
	/**
	 * Array of non-singular view contexts.
	 */
	const NON_SINGULAR_VIEW_CONTEXTS = [
		'unknown',
		'archive',
		'search',
		'feed',
		'posts_page',
		'404',
		'page',
	];

	/**
	 * Get an instance of the lightweight API that can be called directly by other plugin classes.
	 * Requires nonce verfication to prevent CSRF attacks.
	 *
	 * @param string $nonce Nonce string to authenticate the request. If not valid, the request will fail.
	 *
	 * @return object|boolean If the nonce is valid, an instance of the API. Otherwise, false.
	 */
	public static function get_api( $nonce ) {
		if ( \wp_verify_nonce( $nonce, 'newspack_campaigns_lightweight_api' ) ) {
			return new \Lightweight_API( $nonce );
		}

		return false;
	}

	/**
	 * Is the URL from a newsletter?
	 *
	 * @param string $url A URL.
	 */
	public static function is_url_from_email( $url ) {
		return stripos( $url, 'utm_medium=email' ) !== false;
	}

	/**
	 * Is reader a newsletter subscriber?
	 *
	 * @param object $reader_events Reader data.
	 * @param string $url Referrer URL.
	 */
	public static function is_subscriber( $reader_events, $url = '' ) {
		return self::is_url_from_email( $url ) || 0 < count(
			array_filter(
				$reader_events,
				function( $event ) {
					return 'subscription' === $event['type'];
				}
			)
		);
	}

	/**
	 * Is reader a donor?
	 *
	 * @param object $reader_events Reader data.
	 */
	public static function is_donor( $reader_events ) {
		return 0 < count(
			array_filter(
				$reader_events,
				function( $event ) {
					return 'donation' === $event['type'];
				}
			)
		);
	}

	/**
	 * Is reader a former donor?
	 *
	 * @param object $reader_events Reader data.
	 */
	public static function is_former_donor( $reader_events ) {
		$donation_related_events = array_values(
			array_filter(
				$reader_events,
				function( $event ) {
					return stripos( $event['type'], 'donation' ) !== false;
				}
			)
		);
		if ( 0 < count( $donation_related_events ) ) {
			// The donation cancellation event must be the latest one.
			// If they've donated again, they're not a former donor.
			$latest_donation_related_event = $donation_related_events[0];
			return 'donation_cancelled' === $latest_donation_related_event['type'];
		}
		return false;
	}

	/**
	 * Does reader have a WP user account?
	 *
	 * @param object $reader_events Reader data.
	 */
	public static function has_user_account( $reader_events ) {
		return 0 < count(
			array_filter(
				$reader_events,
				function( $event ) {
					return 'user_account' === $event['type'];
				}
			)
		);
	}

	/**
	 * Compare page referrer to a list of referrers.
	 *
	 * @param string $page_referrer_url Referrer to compare to.
	 * @param string $referrers_list_string Comma-separated referrer domains list.
	 */
	public static function does_referrer_match( $page_referrer_url, $referrers_list_string ) {
		$referer_domain      = parse_url( $page_referrer_url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$referer_domain_root = implode( '.', array_slice( explode( '.', $referer_domain ), -2 ) );
		$referrer_matches    = in_array(
			$referer_domain_root,
			array_map( 'trim', explode( ',', $referrers_list_string ) )
		);
		return $referrer_matches;
	}

	/**
	 * Given a reader's data, get the total view count of singular posts.
	 * Articles are any singular post type in Newspack sites.
	 *
	 * @param array $readers Reader data for all known sessions.
	 *
	 * @return int Total view count of singular posts.
	 */
	public static function get_post_view_count( $readers ) {
		$total_view_count      = 0;
		$non_singular_contexts = self::NON_SINGULAR_VIEW_CONTEXTS;

		foreach ( $readers as $reader ) {
			if ( isset( $reader['reader_data']['views'] ) ) {
				foreach ( $reader['reader_data']['views'] as $post_type => $count ) {
					if ( ! in_array( $post_type, $non_singular_contexts, true ) ) {
						$total_view_count += (int) $count;
					}
				}
			}
		}

		return $total_view_count;
	}

	/**
	 * Given a reader's recent events, get the view count of singular posts in the current session.
	 * A session is considered one hour.
	 *
	 * @param array $reader_events Array of reader data as returned by Lightweight_Api::get_reader_events.
	 *
	 * @return int View count of singular posts in current session.
	 */
	public static function get_post_view_count_session( $reader_events ) {
		$non_singular_contexts = self::NON_SINGULAR_VIEW_CONTEXTS;
		return count(
			array_filter(
				$reader_events,
				function ( $event ) use ( $non_singular_contexts ) {
					$hour_ago   = strtotime( '-1 hour', time() );
					$event_time = strtotime( $event['date_created'] );
					return 'view' === $event['type'] && ! in_array( $event['context'], $non_singular_contexts, true ) && $event_time > $hour_ago;
				}
			)
		);
	}

	/**
	 * Given a reader's data, get the view counts of each term of the given taxonomy.
	 *
	 * @param array  $readers Reader data for all known sessions.
	 * @param string $taxonomy Taxonomy to look for. Defaults to 'category'.
	 *
	 * @return int View counts of the given taxonomy.
	 */
	public static function get_term_view_counts( $readers, $taxonomy = 'category' ) {
		$totals = [];
		foreach ( $readers as $reader ) {
			if ( isset( $reader['reader_data'][ $taxonomy ] ) ) {
				foreach ( $reader['reader_data'][ $taxonomy ] as $term => $count ) {
					if ( ! isset( $totals[ $term ] ) ) {
						$totals[ $term ] = 0;
					}

					$totals[ $term ] += (int) $count;
				}
			}
		}

		if ( empty( $totals ) ) {
			return false;
		}

		return $totals;
	}

	/**
	 * Add default values to a segment.
	 *
	 * @param object $segment Segment configuration.
	 * @return object Segment configuration with default values.
	 */
	public static function canonize_segment( $segment ) {
		return (object) array_merge(
			[
				'min_posts'           => 0,
				'max_posts'           => 0,
				'min_session_posts'   => 0,
				'max_session_posts'   => 0,
				'is_subscribed'       => false,
				'is_not_subscribed'   => false,
				'is_donor'            => false,
				'is_not_donor'        => false,
				'is_former_donor'     => false,
				'has_user_account'    => false,
				'no_user_account'     => false,
				'referrers'           => '',
				'favorite_categories' => [],
				'priority'            => PHP_INT_MAX,
				'is_disabled'         => false,
			],
			(array) $segment
		);
	}

	/**
	 * Given a segment and client data, decide if the prompt should be shown.
	 *
	 * @param object $campaign_segment Segment data.
	 * @param string $readers Reader data for all known readers matching the given client ID.
	 * @param string $reader_events Reader data for the given client ID.
	 * @param string $referer_url URL of the page performing the API request.
	 * @param string $page_referrer_url URL of the referrer of the frontend page that is making the API request.
	 * @return bool Whether the prompt should be shown.
	 */
	public static function does_reader_match_segment( $campaign_segment, $readers, $reader_events, $referer_url = '', $page_referrer_url = '' ) {
		$should_display              = true;
		$is_subscriber               = self::is_subscriber( $reader_events, $referer_url );
		$is_donor                    = self::is_donor( $reader_events );
		$is_former_donor             = self::is_former_donor( $reader_events );
		$has_user_account            = self::has_user_account( $reader_events );
		$campaign_segment            = self::canonize_segment( $campaign_segment );
		$article_views_count         = self::get_post_view_count( $readers );
		$article_views_count_session = self::get_post_view_count_session( $reader_events );

		// Read counts for categories.
		$favorite_category_matches_segment = false;
		$category_view_counts              = self::get_term_view_counts( $readers );
		if ( $category_view_counts ) {
			arsort( $category_view_counts );
			$favorite_category_matches_segment = in_array( key( $category_view_counts ), $campaign_segment->favorite_categories );
		}

		/**
		* By article view count.
		*/
		if ( $campaign_segment->min_posts > 0 && $campaign_segment->min_posts > $article_views_count ) {
			$should_display = false;
		}
		if ( $campaign_segment->max_posts > 0 && $campaign_segment->max_posts < $article_views_count ) {
			$should_display = false;
		}

		/**
		* By article views in past hour.
		*/
		if ( $campaign_segment->min_session_posts > 0 && $campaign_segment->min_session_posts > $article_views_count_session ) {
			$should_display = false;
		}
		if ( $campaign_segment->max_session_posts > 0 && $campaign_segment->max_session_posts < $article_views_count_session ) {
			$should_display = false;
		}

		/**
		* By subscription status.
		*/
		if ( $campaign_segment->is_subscribed && ! $is_subscriber ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_not_subscribed && $is_subscriber ) {
			$should_display = false;
		}

		/**
		* By donation status.
		*/
		if ( $campaign_segment->is_donor && ! $is_donor ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_not_donor && $is_donor ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_former_donor && ! $is_former_donor ) {
			$should_display = false;
		}

		/**
		* By login status. is_logged_in and is_not_logged_in are legacy option names
		* that have been renamed has_user_account and no_user_account, for clarity.
		*/
		$segment_has_user_account = ( isset( $campaign_segment->has_user_account ) && $campaign_segment->has_user_account ) ||
		( isset( $campaign_segment->is_logged_in ) && $campaign_segment->is_logged_in );
		$segment_no_user_account  = ( isset( $campaign_segment->no_user_account ) && $campaign_segment->no_user_account ) ||
		( isset( $campaign_segment->is_not_logged_in ) && $campaign_segment->is_not_logged_in );

		if ( $segment_has_user_account && ! $has_user_account ) {
			$should_display = false;
		}
		if ( $segment_no_user_account && $has_user_account ) {
			$should_display = false;
		}

		/**
		* By referrer domain.
		*/
		if ( ! empty( $campaign_segment->referrers ) ) {
			if ( empty( $page_referrer_url ) ) {
				$should_display = false;
			}
			if ( empty( self::does_referrer_match( $page_referrer_url, $campaign_segment->referrers ) ) ) {
				$should_display = false;
			}
		}

		/**
		* By referrer domain - negative.
		*/
		if ( ! empty( $campaign_segment->referrers_not ) && ! empty( self::does_referrer_match( $page_referrer_url, $campaign_segment->referrers_not ) ) ) {
			$should_display = false;
		}

		/**
		* By most read category.
		*/
		if ( count( $campaign_segment->favorite_categories ) > 0 && ! $favorite_category_matches_segment ) {
			$should_display = false;
		}

		return $should_display;
	}

	/**
	 * If the prompt is an overlay.
	 *
	 * @param array $popup Popup object.
	 *
	 * @return boolean
	 */
	public static function is_overlay( $popup ) {
		return isset( $popup->t ) && 'o' === $popup->t;
	}

	/**
	 * If the prompt is an inline prompt.
	 *
	 * @param array $popup Popup object.
	 *
	 * @return boolean
	 */
	public static function is_inline( $popup ) {
		return isset( $popup->t ) && 'i' === $popup->t;
	}

	/**
	 * If the prompt is an above-header prompt.
	 *
	 * @param array $popup Popup object.
	 *
	 * @return boolean
	 */
	public static function is_above_header( $popup ) {
		return isset( $popup->t ) && 'a' === $popup->t;
	}

	/**
	 * Get all events types.
	 */
	public static function get_reader_events_types() {
		return array_merge( self::get_protected_events_types(), [ 'user_account', 'view' ] );
	}

	/**
	 * Get protected events types. These events are not allowed to be deleted when pruning data.
	 */
	public static function get_protected_events_types() {
		return [ 'donation', 'donation_cancelled', 'subscription' ];
	}
}
