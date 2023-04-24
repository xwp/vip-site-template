<?php
/**
 * Newspack Popups Importer
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Importer
 */
class Newspack_Popups_Importer {

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
	 * Store the mapping between the ID of the terms in the input data and the ID of the created terms.
	 *
	 * @var int[]
	 */
	private $terms_mapping = [];

	/**
	 * Store the mapping between the ID of the segments in the input data and the ID of the created segments.
	 *
	 * @var string[]
	 */
	private $segments_mapping = [];

	/**
	 * Constructor.
	 *
	 * @param array $input_data Data to import.
	 */
	public function __construct( $input_data ) {
		$this->reset_results();
		$this->input = $input_data;
	}

	/**
	 * Export prompts, segments and campaigns.
	 *
	 * @return array Exported data.
	 */
	public function import() {
		$this->reset_results();

		$validation = new Newspack\Campaigns\Schemas\Package( $this->input );
		if ( ! $validation->is_valid() ) {
			return [
				'errors' => [
					'validation' => $validation->get_errors(),
				],
			];
		}

		$this->process_campaigns();
		$this->process_segments();
		$this->process_prompts();

		return [
			'totals' => $this->totals,
			'errors' => $this->errors,
		];
	}

	/**
	 * Set a new term mapping.
	 *
	 * @param int $from The ID of the term in the input data.
	 * @param int $to The ID of the term in the database.
	 * @return void
	 */
	public function add_term_mapping( $from, $to ) {
		$this->terms_mapping[ $from ] = $to;
	}

	/**
	 * Reads the input and returns all references to tags and categories that are not present in the database.
	 *
	 * Use this before running the import so you can decide whether to map to other existing terms, create new terms or ignore these terms.
	 *
	 * @return array An array with two keys, post_tag and category, each of them containing an array of items with IDs and names.
	 */
	public function get_missing_terms_from_input() {
		$tags       = [];
		$categories = [];
		$prompts    = $this->input['prompts'];
		$segments   = $this->input['segments'];

		// get all terms from prompts and segments.
		foreach ( $prompts as $prompt ) {
			if ( ! empty( $prompt['tags'] ) ) {
				$tags = array_merge( $tags, $prompt['tags'] );
			}
			if ( ! empty( $prompt['categories'] ) ) {
				$categories = array_merge( $categories, $prompt['categories'] );
			}
			if ( ! empty( $prompt['options']['excluded_tags'] ) ) {
				$tags = array_merge( $tags, $prompt['options']['excluded_tags'] );
			}
			if ( ! empty( $prompt['options']['excluded_categories'] ) ) {
				$categories = array_merge( $categories, $prompt['options']['excluded_categories'] );
			}
		}
		foreach ( $segments as $segment ) {
			if ( ! empty( $segment['configuration']['favorite_categories'] ) ) {
				$categories = array_merge( $categories, $segment['configuration']['favorite_categories'] );
			}
		}

		// remove duplicates.
		$tags_names = array_unique( array_column( $tags, 'name' ) );
		$tags       = array_intersect_key( $tags, $tags_names );
		$cats_names = array_unique( array_column( $categories, 'name' ) );
		$categories = array_intersect_key( $categories, $cats_names );

		// remove existing terms.
		$tags       = array_filter(
			$tags,
			function( $tag ) {
				return ! get_term_by( 'name', $tag['name'], 'post_tag' );
			}
		);
		$categories = array_filter(
			$categories,
			function( $cat ) {
				return ! get_term_by( 'name', $cat['name'], 'category' );
			}
		);

		return [
			'post_tag' => $tags,
			'category' => $categories,
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
			'prompts'    => [],
			'segments'   => [],
			'campaigns'  => [],
			'terms'      => [],
			'validation' => [],
		];
	}

	/**
	 * Process campaigns
	 *
	 * Create the campaigns and store the mapping between the old and new IDs.
	 *
	 * @return void
	 */
	private function process_campaigns() {
		$campaigns = $this->input['campaigns'];
		foreach ( $campaigns as $campaign ) {
			$created                                = Newspack_Popups::create_campaign( $campaign['name'] );
			$this->terms_mapping[ $campaign['id'] ] = $created;
			$this->totals['campaigns']++;
		}
	}

	/**
	 * Process segments
	 *
	 * Create the segments and store the mapping between the old and new IDs.
	 *
	 * @return void
	 */
	private function process_segments() {
		$segments = $this->input['segments'];
		$segments = $this->pre_process_segments_terms( $segments );
		foreach ( $segments as $segment ) {
			$stored_segments                          = Newspack_Popups_Segmentation::create_segment( $segment );
			$created                                  = end( $stored_segments );
			$this->segments_mapping[ $segment['id'] ] = $created['id'];
			$this->totals['segments']++;
		}
	}

	/**
	 * Pre process terms in segments
	 *
	 * @param array $segments The segments from input.
	 * @return array The segments with the terms pre processed.
	 */
	private function pre_process_segments_terms( $segments ) {
		foreach ( $segments as $segment_index => $segment ) {
			if ( ! empty( $segment['configuration']['favorite_categories'] ) ) {
				$segments[ $segment_index ]['configuration']['favorite_categories'] = $this->pre_process_terms( $segment['configuration']['favorite_categories'], 'category', true );
			}
		}
		return $segments;
	}

	/**
	 * Process prompts
	 *
	 * @return void
	 */
	private function process_prompts() {
		$prompts = $this->input['prompts'];
		$prompts = $this->pre_process_prompt_segments( $prompts );
		$prompts = $this->pre_process_prompts_terms( $prompts );

		foreach ( $prompts as $prompt ) {
			$post_data = [
				'post_title'   => $prompt['title'],
				'post_content' => $prompt['content'],
				'post_status'  => $prompt['status'],
				'post_type'    => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			];
			$new_post  = wp_insert_post( $post_data );
			if ( is_wp_error( $new_post ) ) {
				$this->errors['prompts'][] = $new_post->get_error_message();
				continue;
			}

			$this->totals['prompts']++;

			Newspack_Popups_Model::set_popup_options( $new_post, $prompt['options'] );

			if ( ! empty( $prompt['campaign_groups'] ) ) {
				Newspack_Popups_Model::set_popup_terms( $new_post, $prompt['campaign_groups'], Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY );
			}
			if ( ! empty( $prompt['categories'] ) ) {
				Newspack_Popups_Model::set_popup_terms( $new_post, $prompt['categories'], 'category' );
			}
			if ( ! empty( $prompt['tags'] ) ) {
				Newspack_Popups_Model::set_popup_terms( $new_post, $prompt['tags'], 'post_tag' );
			}
		}
	}

	/**
	 * Pre-process prompt segments
	 *
	 * If there are segments set, replace the IDs with the IDs from the segment mapping
	 *
	 * @param array $prompts The prompts to process.
	 * @return array The processed prompts.
	 */
	private function pre_process_prompt_segments( $prompts ) {
		foreach ( $prompts as $prompt_index => $prompt ) {
			if ( ! empty( $prompt['options']['selected_segment_id'] ) ) {
				$segments = explode( ',', $prompt['options']['selected_segment_id'] );
				foreach ( $segments as $key => $segment ) {
					if ( isset( $this->segments_mapping[ $segment ] ) ) {
						$segments[ $key ] = $this->segments_mapping[ $segment ];
					} else {
						unset( $segments[ $key ] );
					}
				}
				$prompts[ $prompt_index ]['options']['selected_segment_id'] = implode( ',', $segments );
			}
		}
		return array_values( $prompts );
	}

	/**
	 * Pre process terms in prompts
	 *
	 * @param array $prompts The prompts from input.
	 * @return array The prompts with the terms pre processed.
	 */
	private function pre_process_prompts_terms( $prompts ) {
		foreach ( $prompts as $prompt_index => $prompt ) {

			if ( ! empty( $prompt['campaign_groups'] ) ) {
				$prompts[ $prompt_index ]['campaign_groups'] = $this->pre_process_terms( $prompt['campaign_groups'], 'campaign_groups' );
			}
			if ( ! empty( $prompt['categories'] ) ) {
				$prompts[ $prompt_index ]['categories'] = $this->pre_process_terms( $prompt['categories'], 'category' );
			}
			if ( ! empty( $prompt['tags'] ) ) {
				$prompts[ $prompt_index ]['tags'] = $this->pre_process_terms( $prompt['tags'], 'post_tag' );
			}
			if ( ! empty( $prompt['options']['excluded_categories'] ) ) {
				$prompts[ $prompt_index ]['options']['excluded_categories'] = $this->pre_process_terms( $prompt['options']['excluded_categories'], 'category', true );
			}
			if ( ! empty( $prompt['options']['excluded_tags'] ) ) {
				$prompts[ $prompt_index ]['options']['excluded_tags'] = $this->pre_process_terms( $prompt['options']['excluded_tags'], 'post_tag', true );
			}
		}
		return array_values( $prompts );
	}

	/**
	 * Pre process terms
	 *
	 * Check if terms mapping were defined and update their ids.
	 * Also checks if the term exists and update the mapping to the existing term, otherwise, drop the term.
	 *
	 * @param array  $terms The terms array in which each item is and array with id and name.
	 * @param string $taxonomy The taxonomy of the terms, used to look for existing terms with the same name.
	 * @param bool   $return_only_ids Whether to return only the ids of the terms, instead of a pair with id and name.
	 * @return array $terms The processed terms.
	 */
	private function pre_process_terms( $terms, $taxonomy, $return_only_ids = false ) {
		foreach ( $terms as $term_index => $term ) {
			if ( isset( $this->terms_mapping[ $term['id'] ] ) ) {
				$terms[ $term_index ]['id'] = $this->terms_mapping[ $term['id'] ];
			} else {
				$existing = get_term_by( 'name', $term['name'], $taxonomy );
				if ( $existing ) {
					$terms[ $term_index ]['id'] = $existing->term_id;
				} else {
					unset( $terms[ $term_index ] );
				}
			}
		}
		if ( $return_only_ids ) {
			$terms = array_map(
				function( $term ) {
					return $term['id'];
				},
				$terms
			);
		}
		return array_values( $terms );
	}


}
