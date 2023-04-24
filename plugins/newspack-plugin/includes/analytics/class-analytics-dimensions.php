<?php
/**
 * Newspack Analytics dimensions features.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Analytics dimensions integrations.
 */
class Analytics_Dimensions {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'handle_custom_dimensions_reporting' ] );
	}

	/**
	 * Tell Site Kit to report the article's data as custom dimensions.
	 * More about custom dimensions: https://support.google.com/analytics/answer/2709828.
	 */
	public static function handle_custom_dimensions_reporting() {
		$custom_dimensions_values = apply_filters(
			'newspack_custom_dimensions_values',
			self::get_custom_dimensions_values( get_the_ID() )
		);
		foreach ( $custom_dimensions_values as $key => $value ) {
			self::add_custom_dimension_to_ga_config( $key, $value );
		}
	}

	/**
	 * Get values for custom dimensions to be sent to GA.
	 *
	 * @param string $post_id Post ID.
	 */
	public static function get_custom_dimensions_values( $post_id ) {
		$custom_dimensions        = Analytics_Wizard::list_configured_custom_dimensions();
		$custom_dimensions_values = [];
		foreach ( $custom_dimensions as $dimension ) {
			$dimension_role = $dimension['role'];
			// Remove `ga:` prefix.
			$dimension_id = substr( $dimension['gaID'], 3 );

			$post = get_post( $post_id );
			if ( $post ) {
				if ( 'category' === $dimension_role ) {
					$categories       = get_the_category( $post_id );
					$primary_category = get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
					if ( $primary_category ) {
						foreach ( $categories as $category ) {
							if ( $category->term_id === (int) $primary_category ) {
								$categories = [ $category ];
							}
						}
					}
					if ( ! empty( $categories ) ) {
						$categories_slugs                          = implode(
							',',
							array_map(
								function( $cat ) {
									return $cat->slug;
								},
								$categories
							)
						);
						$custom_dimensions_values[ $dimension_id ] = $categories_slugs;
					}
				}

				if ( 'author' === $dimension_role ) {
					$author_id                                 = $post->post_author;
					$custom_dimensions_values[ $dimension_id ] = get_the_author_meta( 'display_name', $author_id );
				}

				if ( 'word_count' === $dimension_role ) {
					$custom_dimensions_values[ $dimension_id ] = count( explode( ' ', wp_strip_all_tags( $post->post_content ) ) );
				}

				if ( 'publish_date' === $dimension_role ) {
					$custom_dimensions_values[ $dimension_id ] = get_the_time( 'Y-m-d H:i', $post->ID );
				}
			}
		}
		return $custom_dimensions_values;
	}

	/**
	 * Add custom dimension to GA config via Site Kit filters.
	 *
	 * @param string $dimension_id Dimension ID.
	 * @param string $payload Payload.
	 */
	public static function add_custom_dimension_to_ga_config( $dimension_id, $payload ) {
		// Non-AMP.
		add_filter(
			'googlesitekit_gtag_opt',
			function ( $gtag_opt ) use ( $payload, $dimension_id ) {
				$gtag_opt[ $dimension_id ] = $payload;
				return $gtag_opt;
			}
		);
		// AMP.
		add_filter(
			'googlesitekit_amp_gtag_opt',
			function ( $gtag_amp_opt ) use ( $payload, $dimension_id ) {
				$tracking_id = $gtag_amp_opt['vars']['gtag_id'];
				$gtag_amp_opt['vars']['config'][ $tracking_id ][ $dimension_id ] = $payload;
				return $gtag_amp_opt;
			}
		);
	}
}
new Analytics_Dimensions();
