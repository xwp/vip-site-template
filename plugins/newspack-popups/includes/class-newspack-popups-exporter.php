<?php
/**
 * Newspack Popups Exporter
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exporter
 */
class Newspack_Popups_Exporter {

	/**
	 * Stores the total number of items exported.
	 *
	 * @var int[]
	 */
	private $totals;

	/**
	 * Store the errors of items that could not be exported.
	 *
	 * @var int[]
	 */
	private $errors;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->reset_results();
	}

	/**
	 * Export prompts, segments and campaigns.
	 *
	 * @return array Exported data.
	 */
	public function export() {
		$this->reset_results();
		$exported_data = [
			'prompts'   => $this->get_prompts(),
			'segments'  => $this->get_segments(),
			'campaigns' => $this->get_campaigns(),
		];

		return [
			'totals' => $this->totals,
			'errors' => $this->errors,
			'data'   => $exported_data,
		];
	}

	/**
	 * Reset results for a new export
	 *
	 * @return void
	 */
	private function reset_results() {
		$this->totals = [
			'prompts'   => 0,
			'segments'  => 0,
			'campaigns' => 0,
		];
		$this->errors = [
			'prompts'   => [],
			'segments'  => [],
			'campaigns' => [],
			'terms'     => [],
		];
	}

	/**
	 * Get the prompts to be exported.
	 *
	 * Return only items that pass validation and add errors for the ones that don't.
	 *
	 * @return array
	 */
	private function get_prompts() {
		$prompts        = [];
		$stored_prompts = Newspack_Popups_Model::retrieve_popups( true );
		foreach ( $stored_prompts as $stored_prompt ) {
			$transformed = $this->prepare_prompt_for_export( $stored_prompt );
			$val         = new Newspack\Campaigns\Schemas\Prompts( $transformed );
			if ( $val->is_valid() ) {
				$prompts[] = $transformed;
				$this->totals['prompts']++;
			} else {
				$this->errors['prompts'][ $stored_prompt['id'] ] = $val->get_errors();
			}
		}

		return $prompts;
	}

	/**
	 * Get the Campaigns to be exported.
	 *
	 * Return only items that pass validation and add errors for the ones that don't.
	 *
	 * @return array
	 */
	private function get_campaigns() {
		$campaigns        = [];
		$stored_campaigns = $this->sanitize_campaign_groups( Newspack_Popups::get_groups() );
		foreach ( $stored_campaigns as $stored_campaign ) {
			$val = new Newspack\Campaigns\Schemas\Campaigns( $stored_campaign );
			if ( $val->is_valid() ) {
				$campaigns[] = $stored_campaign;
				$this->totals['campaigns']++;
			} else {
				$this->errors['campaigns'][ $stored_campaign->term_id ] = $val->get_errors();
			}
		}
		return $campaigns;
	}

	/**
	 * Get the Segments to be exported.
	 *
	 * Return only items that pass validation and add errors for the ones that don't.
	 *
	 * @return array
	 */
	private function get_segments() {
		$segments        = [];
		$stored_segments = Newspack_Popups_Segmentation::get_segments();
		foreach ( $stored_segments as $stored_segment ) {
			$stored_segment = $this->prepare_segment_for_export( $stored_segment );
			$val            = new Newspack\Campaigns\Schemas\Segments( $stored_segment );
			if ( $val->is_valid() ) {
				$segments[] = $stored_segment;
				$this->totals['segments']++;
			} else {
				$this->errors['segments'][ $stored_segment['id'] ] = $val->get_errors();
			}
		}
		return $segments;
	}

	/**
	 * Prepares the segment from the database for export.
	 *
	 * @param array $segment The segment as it is returned from Newspack_Popups_Segmentation::get_segments.
	 * @return array The segment in the format expected by the exporter.
	 */
	private function prepare_segment_for_export( $segment ) {
		if ( isset( $segment['configuration']['favorite_categories'] ) ) {
			$segment['configuration']['favorite_categories'] = $this->sanitize_categories( $segment['configuration']['favorite_categories'] );
		}
		if ( isset( $segment['created_at'] ) ) {
			unset( $segment['created_at'] );
		}
		if ( isset( $segment['updated_at'] ) ) {
			unset( $segment['updated_at'] );
		}
		return $segment;
	}

	/**
	 * Prepares the prompt from the database for export.
	 *
	 * @param array $prompt The prompt as it is returned from Newspack_Popups_Model::retrieve_popups.
	 * @return array The prompt in the format expected by the exporter.
	 */
	private function prepare_prompt_for_export( $prompt ) {
		unset( $prompt['id'] );

		if ( ! empty( $prompt['options']['excluded_categories'] ) ) {
			$prompt['options']['excluded_categories'] = $this->sanitize_categories( $prompt['options']['excluded_categories'] );
		}
		if ( ! empty( $prompt['options']['excluded_tags'] ) ) {
			$prompt['options']['excluded_tags'] = $this->sanitize_tags( $prompt['options']['excluded_tags'] );
		}
		if ( ! empty( $prompt['categories'] ) ) {
			$prompt['categories'] = $this->sanitize_categories( $prompt['categories'] );
		}
		if ( ! empty( $prompt['tags'] ) ) {
			$prompt['tags'] = $this->sanitize_tags( $prompt['tags'] );
		}
		if ( ! empty( $prompt['campaign_groups'] ) ) {
			$prompt['campaign_groups'] = $this->sanitize_campaign_groups( $prompt['campaign_groups'] );
		}

		// There was a bug that was saving some overlay_sizes as full instead of full-width. Let's take this into account and fix it.
		if ( isset( $prompt['options']['overlay_size'] ) && 'full' === $prompt['options']['overlay_size'] ) {
			$prompt['options']['overlay_size'] = 'full-width';
		}

		if ( empty( $prompt['options']['utm_suppression'] ) ) {
			unset( $prompt['options']['utm_suppression'] );
		}

		return $prompt;

	}

	/**
	 * Sanitizes an array of Categories
	 *
	 * @see self::sanitize_terms
	 *
	 * @param int[]|WP_Term[] $categories An array of categories IDs or WP_Term objects.
	 * @return array
	 */
	private function sanitize_categories( $categories ) {
		return $this->sanitize_terms( $categories, 'category' );
	}

	/**
	 * Sanitizes an array of Tags
	 *
	 * @see self::sanitize_terms
	 *
	 * @param int[]|WP_Term[] $tags An array of tags IDs or WP_Term objects.
	 * @return array
	 */
	private function sanitize_tags( $tags ) {
		return $this->sanitize_terms( $tags, 'post_tag' );
	}

	/**
	 * Sanitizes an array of Campaigns
	 *
	 * @see self::sanitize_terms
	 *
	 * @param int[]|WP_Term[] $campaign_groups An array of campaign_groups IDs or WP_Term objects.
	 * @return array
	 */
	private function sanitize_campaign_groups( $campaign_groups ) {
		return $this->sanitize_terms( $campaign_groups, Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY );
	}

	/**
	 * Sanitize terms.
	 *
	 * Transforms an array of terms in an array in the format expected by the Exporter, containing only the term ID and name.
	 *
	 * @param int[]|WP_Term[] $terms Array of terms. If term must be either an integer with the term ID, or a WP_Term object.
	 * @param string          $taxonomy Taxonomy slug. Used to fetch the term name if only an ID is informed.
	 * @return array
	 */
	private function sanitize_terms( $terms, $taxonomy ) {
		$sanitized_terms = [];
		foreach ( $terms as $term ) {
			if ( is_int( $term ) ) {
				$term = get_term( $term, $taxonomy );
			}
			if ( is_a( $term, 'WP_Term' ) ) {
				$sanitized_terms[] = [
					'id'   => $term->term_id,
					'name' => $term->name,
				];
			} else {
				if ( ! isset( $this->errors['terms'][ $taxonomy ] ) ) {
					$this->errors['terms'][ $taxonomy ] = [];
					if ( is_wp_error( $term ) ) {
						$this->errors['terms'][ $taxonomy ][] = $term->get_error_message();
					}
				}
			}
		}
		return $sanitized_terms;
	}
}
