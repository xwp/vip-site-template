<?php
/**
 * Newspack Popups Model
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * API endpoints
 */
final class Newspack_Popups_Model {
	/**
	 * Possible placements of overlay popups.
	 *
	 * @var array
	 */
	protected static $overlay_placements = [ 'top', 'bottom', 'center', 'bottom_right', 'bottom_left', 'top_right', 'top_left', 'center_right', 'center_left' ];

	/**
	 * Possible placements of inline popups.
	 *
	 * @var array
	 */
	protected static $inline_placements = [ 'inline', 'above_header', 'archives' ];

	/**
	 * List of hooks that can be used to insert hidden inputs in forms that will be rendered inside a popup.
	 *
	 * @var array
	 */
	protected static $form_hooks = [
		'newspack_registration_before_form_fields',
		'newspack_newsletters_subscribe_block_before_form_fields',
		'newspack_blocks_donate_before_form_fields',
	];

	/**
	 * Attribute to temporarily hold the current popup ID and use it in the form_hooks.
	 *
	 * @var ?int
	 */
	protected static $form_hooks_popup_id;

	/**
	 * Retrieve all Popups (first 100).
	 *
	 * @param  boolean $include_unpublished Whether to include unpublished posts.
	 * @param  boolean $include_trash Whether to include trashed posts.
	 * @return array Array of Popup objects.
	 */
	public static function retrieve_popups( $include_unpublished = false, $include_trash = false ) {
		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			'post_status'    => $include_unpublished ? [ 'draft', 'pending', 'future', 'publish' ] : [ 'publish' ],
			'posts_per_page' => 100,
		];
		if ( $include_trash ) {
			$args['post_status'][] = 'trash';
		}

		$popups = self::retrieve_popups_with_query( new WP_Query( $args ), true );
		return $popups;
	}

	/**
	 * Retrieve all active popups.
	 */
	public static function retrieve_active_popups() {
		return self::retrieve_popups_with_query(
			new WP_Query(
				[
					'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				]
			),
			true
		);
	}

	/**
	 * Set terms for a Popup.
	 *
	 * @param integer $id ID of Popup.
	 * @param array   $terms Array of terms to be set.
	 * @param string  $taxonomy Taxonomy slug.
	 */
	public static function set_popup_terms( $id, $terms, $taxonomy ) {
		$popup = self::retrieve_popup_by_id( $id, false, true );
		if ( ! $popup ) {
			return new \WP_Error(
				'newspack_popups_popup_doesnt_exist',
				esc_html__( 'The prompt specified does not exist.', 'newspack-popups' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}
		if ( ! in_array( $taxonomy, [ 'category', 'post_tag', Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY ] ) ) {
			return new \WP_Error(
				'newspack_popups_invalid_taxonomy',
				esc_html__( 'Invalid taxonomy.', 'newspack-popups' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}
		$term_ids = array_map(
			function( $term ) {
				return $term['id'];
			},
			$terms
		);
		return wp_set_post_terms( $id, $term_ids, $taxonomy );
	}

	/**
	 * Set options for a Popup. Can be used by other plugins to set popup's options.
	 *
	 * @param integer $id ID of Popup.
	 * @param array   $options Array of options to update.
	 */
	public static function set_popup_options( $id, $options ) {
		$popup = self::retrieve_popup_by_id( $id, false, true );
		if ( ! $popup ) {
			return new \WP_Error(
				'newspack_popups_popup_doesnt_exist',
				esc_html__( 'The prompt specified does not exist.', 'newspack-popups' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}
		foreach ( $options as $key => $value ) {
			switch ( $key ) {
				case 'frequency':
					if ( ! in_array( $value, [ 'once', 'daily', 'always', 'custom' ] ) ) {
						return new \WP_Error(
							'newspack_popups_invalid_option_value',
							esc_html__( 'Invalid frequency value.', 'newspack-popups' ),
							[
								'status' => 400,
								'level'  => 'fatal',
							]
						);
					}
					update_post_meta( $id, $key, $value );
					break;
				case 'placement':
					$valid_placements = array_merge(
						self::$overlay_placements,
						self::$inline_placements,
						Newspack_Popups_Custom_Placements::get_custom_placement_values(),
						[ 'manual' ]
					);
					if ( ! in_array( $value, $valid_placements ) ) {
						return new \WP_Error(
							'newspack_popups_invalid_option_value',
							esc_html__( 'Invalid placement value.', 'newspack-popups' ),
							[
								'status' => 400,
								'level'  => 'fatal',
							]
						);
					}
					update_post_meta( $id, $key, $value );
					break;
				case 'post_types':
				case 'archive_page_types':
				case 'excluded_categories':
				case 'excluded_tags':
					update_post_meta( $id, $key, $value );
					break;
				default:
					update_post_meta( $id, $key, esc_attr( $value ) );
			}
		}
	}

	/**
	 * Retrieve prompts by placement. If $placement is not given, get only prompts eligible to be
	 * programmatically inserted (not shortcoded, custom placement, or manual-only).
	 *
	 * @param  boolean     $include_unpublished Whether to include unpublished prompts.
	 * @param  int|boolean $campaign_id Campaign term ID, or false to ignore campaign.
	 * @param  array|null  $placements If given, find prompts matching these exact placements.
	 * @return array Eligible popup objects.
	 */
	public static function retrieve_eligible_popups( $include_unpublished = false, $campaign_id = false, $placements = null ) {
		$valid_placements = is_array( $placements ) ? $placements : array_merge(
			self::$overlay_placements,
			self::$inline_placements
		);
		$args             = [
			'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			'post_status'    => $include_unpublished ? [ 'draft', 'pending', 'future', 'publish' ] : 'publish',
			'posts_per_page' => 100,
			'meta_key'       => 'placement',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value'     => $valid_placements,
			'meta_compare'   => 'IN',
		];

		// If previewing specific campaign.
		if ( ! empty( $campaign_id ) ) {
			$tax_query = [ 'taxonomy' => Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY ];

			if ( -1 === (int) $campaign_id ) {
				$tax_query['operator'] = 'NOT EXISTS';
			} else {
				$tax_query['field'] = 'term_id';
				$tax_query['terms'] = [ $campaign_id ];
			}

			$args['tax_query'] = [ $tax_query ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		return self::retrieve_popups_with_query( new WP_Query( $args ) );
	}

	/**
	 * Retrieve popup preview CPT post.
	 *
	 * @param string $post_id Post id.
	 * @return object Popup object.
	 */
	public static function retrieve_preview_popup( $post_id ) {
		// Up-to-date post data is stored in an autosave.
		$autosave    = wp_get_post_autosave( $post_id );
		$post_object = $autosave ? $autosave : get_post( $post_id );
		// Setting proper id for correct API calls.
		$post_object->ID = $post_id;

		return self::create_popup_object(
			$post_object,
			false,
			[
				'background_color'               => filter_input( INPUT_GET, 'n_bc', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'display_title'                  => filter_input( INPUT_GET, 'n_ti', FILTER_VALIDATE_BOOLEAN ),
				'hide_border'                    => filter_input( INPUT_GET, 'n_hb', FILTER_VALIDATE_BOOLEAN ),
				'large_border'                   => filter_input( INPUT_GET, 'n_lb', FILTER_VALIDATE_BOOLEAN ),
				'frequency'                      => filter_input( INPUT_GET, 'n_fr', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'frequency_max'                  => filter_input( INPUT_GET, 'n_fm', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'frequency_start'                => filter_input( INPUT_GET, 'n_fs', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'frequency_between'              => filter_input( INPUT_GET, 'n_fb', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'frequency_reset'                => filter_input( INPUT_GET, 'n_ft', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'overlay_color'                  => filter_input( INPUT_GET, 'n_oc', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'overlay_opacity'                => filter_input( INPUT_GET, 'n_oo', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'overlay_size'                   => filter_input( INPUT_GET, 'n_os', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'no_overlay_background'          => filter_input( INPUT_GET, 'n_bg', FILTER_VALIDATE_BOOLEAN ),
				'placement'                      => filter_input( INPUT_GET, 'n_pl', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'trigger_type'                   => filter_input( INPUT_GET, 'n_tt', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'trigger_delay'                  => filter_input( INPUT_GET, 'n_td', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'trigger_scroll_progress'        => filter_input( INPUT_GET, 'n_ts', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'trigger_blocks_count'           => filter_input( INPUT_GET, 'n_tb', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'archive_insertion_posts_count'  => filter_input( INPUT_GET, 'n_ac', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
				'archive_insertion_is_repeating' => filter_input( INPUT_GET, 'n_ar', FILTER_VALIDATE_BOOLEAN ),
				'utm_suppression'                => filter_input( INPUT_GET, 'n_ut', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
			]
		);
	}

	/**
	 * Retrieve popup CPT post by ID.
	 *
	 * The query for prompts relies on the dynamic default value of the post_status parameter.
	 * In admin context, it will include the drafts, and in non-admin context it will only return published posts.
	 *
	 * @param string $post_id Post id.
	 * @param bool   $use_default_status_query Whether to rely on the default behavior of the post_status parameter. If false, only published posts will be returned.
	 * @param bool   $include_unpublished Whether to include unpublished prompts. $use_default_status_query must be false.
	 * @return object Popup object.
	 */
	public static function retrieve_popup_by_id( $post_id, $use_default_status_query = false, $include_unpublished = false ) {
		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			'posts_per_page' => 1,
			'p'              => $post_id,
		];

		if ( false === $use_default_status_query ) {
			$args['post_status'] = $include_unpublished ? [ 'draft', 'pending', 'future', 'publish' ] : 'publish';
		}

		$popups = self::retrieve_popups_with_query( new WP_Query( $args ) );
		return count( $popups ) > 0 ? $popups[0] : null;
	}

	/**
	 * Retrieve popup CPT posts.
	 *
	 * @param WP_Query $query The query to use.
	 * @param boolean  $include_taxonomies If true, returned objects will include assigned categories and tags.
	 * @return array Popup objects array
	 */
	protected static function retrieve_popups_with_query( WP_Query $query, $include_taxonomies = false ) {
		$popups = [];
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$popup_post = get_post( get_the_ID() );
				if ( Newspack_Popups::NEWSPACK_POPUPS_CPT === $popup_post->post_type ) {
					$popup = self::create_popup_object(
						$popup_post,
						$include_taxonomies
					);
					$popup = self::deprecate_test_never_manual( $popup, 'publish' === $query->get( 'post_status', null ) );

					if ( $popup ) {
						$popups[] = $popup;
					}
				}
			}
			wp_reset_postdata();
		}
		return $popups;
	}

	/**
	 * Deprecate Test Mode and Never/Manual frequencies.
	 *
	 * @param object $popup The popup.
	 * @param bool   $published_only Whether the result must be a published post.
	 * @return object|null Popup object or null.
	 */
	protected static function deprecate_test_never_manual( $popup, $published_only ) {
		$frequency = $popup['options']['frequency'];
		$placement = $popup['options']['placement'];
		if ( in_array( $frequency, [ 'never', 'test', 'manual' ] ) ) {
			if ( in_array( $placement, [ 'inline', 'above_header' ] ) ) {
				$popup['options']['frequency'] = 'always';
			} else {
				$popup['options']['frequency'] = 'daily';
			}
			update_post_meta( $popup['id'], 'frequency', $popup['options']['frequency'] );

			$post = get_post( $popup['id'] );

			// Update 'manual' prompts to a default custom placement.
			if ( 'manual' === $frequency ) {
				$popup['options']['placement'] = 'custom1';
				update_post_meta( $popup['id'], 'placement', $popup['options']['placement'] );
			}

			// Set 'never' and 'test' prompts to draft status.
			if ( in_array( $frequency, [ 'never', 'test' ] ) ) {
				$popup['status']   = 'draft';
				$post->post_status = 'draft';
			}

			wp_update_post( $post );

			if ( $published_only && 'publish' !== $popup['status'] ) {
				$popup = null;
			}
		}
		return $popup;
	}

	/**
	 * Get options for the given prompt, with defaults.
	 *
	 * @param int         $id ID of the prompt.
	 * @param object|null $options Popup options to use instead of the options retrieved from the post. Used for popup previews.
	 * @return object Array of prompt options.
	 */
	public static function get_popup_options( $id, $options = null ) {
		$post_options = isset( $options ) ? $options : [
			'background_color'               => get_post_meta( $id, 'background_color', true ),
			'display_title'                  => get_post_meta( $id, 'display_title', true ),
			'hide_border'                    => get_post_meta( $id, 'hide_border', true ),
			'large_border'                   => get_post_meta( $id, 'large_border', true ),
			'frequency'                      => get_post_meta( $id, 'frequency', true ),
			'frequency_max'                  => get_post_meta( $id, 'frequency_max', true ),
			'frequency_start'                => get_post_meta( $id, 'frequency_start', true ),
			'frequency_between'              => get_post_meta( $id, 'frequency_between', true ),
			'frequency_reset'                => get_post_meta( $id, 'frequency_reset', true ),
			'overlay_color'                  => get_post_meta( $id, 'overlay_color', true ),
			'overlay_opacity'                => get_post_meta( $id, 'overlay_opacity', true ),
			'overlay_size'                   => get_post_meta( $id, 'overlay_size', true ),
			'no_overlay_background'          => get_post_meta( $id, 'no_overlay_background', true ),
			'placement'                      => get_post_meta( $id, 'placement', true ),
			'trigger_type'                   => get_post_meta( $id, 'trigger_type', true ),
			'trigger_delay'                  => get_post_meta( $id, 'trigger_delay', true ),
			'trigger_scroll_progress'        => get_post_meta( $id, 'trigger_scroll_progress', true ),
			'trigger_blocks_count'           => get_post_meta( $id, 'trigger_blocks_count', true ),
			'archive_insertion_posts_count'  => get_post_meta( $id, 'archive_insertion_posts_count', true ),
			'archive_insertion_is_repeating' => get_post_meta( $id, 'archive_insertion_is_repeating', true ),
			'utm_suppression'                => get_post_meta( $id, 'utm_suppression', true ),
			'selected_segment_id'            => get_post_meta( $id, 'selected_segment_id', true ),
			'post_types'                     => get_post_meta( $id, 'post_types', true ),
			'archive_page_types'             => get_post_meta( $id, 'archive_page_types', true ),
			'additional_classes'             => get_post_meta( $id, 'additional_classes', true ),
			'excluded_categories'            => get_post_meta( $id, 'excluded_categories', true ),
			'excluded_tags'                  => get_post_meta( $id, 'excluded_tags', true ),
		];

		// Remove empty options, except for those whose value might actually be 0.
		$filtered_options = array_filter(
			$post_options,
			function( $value, $key ) {
				if ( 'overlay_opacity' === $key ) {
					return true;
				}

				return ! empty( $value );
			},
			ARRAY_FILTER_USE_BOTH
		);

		return wp_parse_args(
			$filtered_options,
			[
				'background_color'               => '#FFFFFF',
				'display_title'                  => false,
				'hide_border'                    => false,
				'large_border'                   => false,
				'frequency'                      => 'always',
				'frequency_max'                  => 0,
				'frequency_start'                => 0,
				'frequency_between'              => 0,
				'frequency_reset'                => 'month',
				'overlay_color'                  => '#000000',
				'overlay_opacity'                => 30,
				'overlay_size'                   => 'medium',
				'no_overlay_background'          => false,
				'placement'                      => 'inline',
				'trigger_type'                   => 'time',
				'trigger_delay'                  => 0,
				'trigger_scroll_progress'        => 0,
				'trigger_blocks_count'           => 0,
				'archive_insertion_posts_count'  => 1,
				'archive_insertion_is_repeating' => false,
				'utm_suppression'                => null,
				'selected_segment_id'            => '',
				'post_types'                     => self::get_default_popup_post_types(),
				'archive_page_types'             => self::get_supported_archive_page_types(),
				'additional_classes'             => '',
				'excluded_categories'            => [],
				'excluded_tags'                  => [],
			]
		);
	}

	/**
	 * Get popups placements.
	 *
	 * @return array Array of popup placements.
	 */
	public static function get_overlay_placements() {
		return self::$overlay_placements;
	}

	/**
	 * Generates the possible sizes for a popup.
	 *
	 * @return array popup possible sizes
	 */
	public static function get_popup_size_options() {
		/**
		 * Filters the list of possible popup sizes.
		 *
		 * @param array Array of possible popup sizes.
		 *     $params = [
		 *          'value' => (string) size value.
		 *          'label' => (string) size label to be displayed.
		 *     ]
		 */
		return apply_filters(
			'newspack_popups_size_options',
			[
				[
					'value' => 'x-small',
					'label' => __( 'Extra Small', 'newspack-popups' ),
				],
				[
					'value' => 'small',
					'label' => __( 'Small', 'newspack-popups' ),
				],
				[
					'value' => 'medium',
					'label' => __( 'Medium', 'newspack-popups' ),
				],
				[
					'value' => 'large',
					'label' => __( 'Large', 'newspack-popups' ),
				],
				[
					'value' => 'full-width',
					'label' => __( 'Full-Width', 'newspack-popups' ),
				],
			]
		);
	}

	/**
	 * Get available archive page types where to display prompts
	 */
	public static function get_available_archive_page_types() {
		return [
			[
				'name'  => 'category',
				/* translators: archive page */
				'label' => __( 'Categories' ),
			],
			[
				'name'  => 'tag',
				/* translators: archive page */
				'label' => __( 'Tags' ),
			],
			[
				'name'  => 'author',
				/* translators: archive page */
				'label' => __( 'Authors' ),
			],
			[
				'name'  => 'date',
				/* translators: archive page */
				'label' => __( 'Date' ),
			],
			[
				'name'  => 'post-type',
				/* translators: archive page */
				'label' => __( 'Custom Post Types' ),
			],
			[
				'name'  => 'taxonomy',
				/* translators: archive page */
				'label' => __( 'Taxonomies' ),
			],
		];
	}

	/**
	 * Get the globally supported post types.
	 */
	public static function get_globally_supported_post_types() {
		return apply_filters(
			'newspack_campaigns_post_types_for_campaigns',
			self::get_default_popup_post_types()
		);
	}

	/**
	 * Get the supported archive page types.
	 */
	public static function get_supported_archive_page_types() {
		return apply_filters(
			'newspack_campaigns_archive_page_types_for_campaigns',
			self::get_default_popup_archive_page_types()
		);
	}

	/**
	 * Get the default supported post types.
	 */
	public static function get_default_popup_post_types() {
		// Any custom post type that is both public and has a post type archive.
		$public_post_types = array_values(
			get_post_types(
				[
					'has_archive' => true,
					'public'      => true,
				]
			)
		);

		// Default 'post' and 'page' post types actually have 'is_archive' => false, but we still want them.
		$public_post_types = array_merge( [ 'post', 'page' ], $public_post_types );

		return apply_filters(
			'newspack_campaigns_default_supported_post_types',
			$public_post_types
		);
	}

	/**
	 * Get the default supported archive page types.
	 */
	public static function get_default_popup_archive_page_types() {
		return [ 'category', 'tag', 'author', 'date', 'post-type', 'taxonomy' ];
	}

	/**
	 * Create the popup object.
	 *
	 * @param WP_Post $campaign_post The prompt post object.
	 * @param boolean $include_taxonomies If true, returned objects will include assigned categories and tags.
	 * @param object  $options Popup options to use instead of the options retrieved from the post. Used for popup previews.
	 * @return object Popup object
	 */
	public static function create_popup_object( $campaign_post, $include_taxonomies = false, $options = null ) {
		$id                    = $campaign_post->ID;
		$campaign_post_options = self::get_popup_options( $id, $options );
		$popup                 = [
			'id'      => $id,
			'status'  => 'inherit' === $campaign_post->post_status ? 'preview' : $campaign_post->post_status,
			'title'   => $campaign_post->post_title,
			'content' => $campaign_post->post_content,
			'options' => $campaign_post_options,
		];

		$assigned_segments = explode( ',', $popup['options']['selected_segment_id'] );
		if ( $popup['options']['selected_segment_id'] && 0 === count( array_intersect( $assigned_segments, Newspack_Popups_Segmentation::get_segment_ids() ) ) ) {
			$popup['options']['selected_segment_id'] = null;
		}
		if ( $include_taxonomies ) {
			$popup['categories']      = get_the_category( $id );
			$popup['tags']            = get_the_tags( $id );
			$popup['campaign_groups'] = get_the_terms( $id, Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY );
		}

		$duplicate_of = get_post_meta( $id, 'duplicate_of', true );

		if ( $duplicate_of ) {
			$popup['duplicate_of'] = $duplicate_of;
		}

		if ( self::is_inline( $popup ) ) {
			switch ( $popup['options']['trigger_type'] ) {
				case 'blocks_count':
					$popup['options']['trigger_scroll_progress'] = 0;
					break;
				case 'scroll':
				default:
					$popup['options']['trigger_blocks_count'] = 0;
					break;
			};

			return $popup;
		}

		if ( self::is_overlay( $popup ) ) {
			switch ( $popup['options']['trigger_type'] ) {
				case 'scroll':
					$popup['options']['trigger_delay'] = 0;
					break;
				case 'blocks_count':
				case 'time':
				default:
					$popup['options']['trigger_scroll_progress'] = 0;
					break;
			};
			if ( ! in_array( $popup['options']['placement'], self::$overlay_placements, true ) ) {
				$popup['options']['placement'] = 'center';
			}
		}

		return $popup;
	}

	/**
	 * Get the popup delay in milliseconds.
	 *
	 * @param object $popup The popup object.
	 * @return number Delay in milliseconds.
	 */
	protected static function get_delay( $popup ) {
		return intval( $popup['options']['trigger_delay'] ) * 1000 + 500;
	}

	/**
	 * Is it an inline popup or not.
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if it is an inline popup.
	 */
	public static function is_inline( $popup ) {
		if ( ! isset( $popup['options'], $popup['options']['placement'] ) ) {
			return false;
		}
		return in_array(
			$popup['options']['placement'],
			array_merge( self::$inline_placements, Newspack_Popups_Custom_Placements::get_custom_placement_values() )
		);
	}

	/**
	 * Is it a manual-only popup or not.
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if it is a manual-only popup.
	 */
	public static function is_manual_only( $popup ) {
		if ( ! isset( $popup['options'], $popup['options']['placement'] ) ) {
			return false;
		}

		return 'manual' === $popup['options']['placement'];
	}

	/**
	 * Get popups which should be inserted above page header.
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if the popup should be inserted above page header.
	 */
	public static function should_be_inserted_above_page_header( $popup ) {
		if ( self::is_inline( $popup ) ) {
			return 'above_header' === $popup['options']['placement'];
		} else {
			// Insert time-triggered overlay popups above the header, this way they will be
			// visible before scrolling below the fold.
			return 'time' === $popup['options']['trigger_type'];
		}
	}

	/**
	 * Get popups which should be inserted above page header.
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if the popup should be inserted above page header.
	 */
	public static function should_be_inserted_in_archive_pages( $popup ) {
		return self::is_inline( $popup ) && 'archives' === $popup['options']['placement'];
	}

	/**
	 * Get popups which should be inserted in page content.
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if the popup should be inserted in page content.
	 */
	public static function should_be_inserted_in_page_content( $popup ) {
		return self::should_be_inserted_above_page_header( $popup ) === false
			&& self::should_be_inserted_in_archive_pages( $popup ) === false;
	}

	/**
	 * Is it an overlay popup or not.
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if it is an overlay popup.
	 */
	public static function is_overlay( $popup ) {
		if ( ! $popup || ! isset( $popup['options'], $popup['options']['placement'] ) ) {
			return false;
		}
		return in_array( $popup['options']['placement'], self::$overlay_placements, true );
	}

	/**
	 * Is it an above-header popup?
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if it is an above-header popup.
	 */
	public static function is_above_header( $popup ) {
		if ( ! isset( $popup['options'], $popup['options']['placement'] ) ) {
			return false;
		}
		return 'above_header' === $popup['options']['placement'];
	}

	/**
	 * Does the popup have newsletter prompt?
	 *
	 * @param object $popup The popup object.
	 * @return boolean True if popup has a newsletter prompt.
	 */
	public static function has_newsletter_prompt( $popup ) {
		return (
			false !== strpos( $popup['content'], 'wp:jetpack/mailchimp' ) ||
			false !== strpos( $popup['content'], 'wp:mailchimp-for-wp/form' )
		);
	}

	/**
	 * Insert amp-analytics tracking code.
	 *
	 * @param object $popup The popup object.
	 * @param string $body Post body.
	 * @param string $element_id The id of the popup element.
	 * @return string Prints the generated amp-analytics element.
	 */
	protected static function insert_event_tracking( $popup, $body, $element_id ) {
		if (
			Newspack_Popups::is_preview_request() ||
			Newspack_Popups_Settings::is_non_interactive()
		) {
			return '';
		}
		global $wp;

		$endpoint = self::get_reader_endpoint();

		// Newsletter subscription forms.
		$subscribe_form_selector = '';
		$email_form_field_name   = '';

		/**
		 * A CSS class name that can be added to a form element to indicate that it should be treated as a subscribe form.
		 * This will allow forms built with platforms other than Jetpack's Mailchimp block or MC4WP to be tracked.
		 * Jetpack Mailchimp blocks and MC4WP forms will be tracked whether or not they have the class.
		 *
		 * @param string $class_name The class name to look for.
		 */
		$newspack_form_class = apply_filters( 'newspack_campaigns_form_class', 'newspack-subscribe-form' );
		$esp                 = null;

		if ( preg_match( '/wp-block-jetpack-mailchimp/', $body ) !== 0 ) {
			// Jetpack Mailchimp block.
			$subscribe_form_selector = apply_filters( 'newspack_campaigns_form_selector', '.wp-block-jetpack-mailchimp form' );
			$email_form_field_name   = apply_filters( 'newspack_campaigns_email_form_field_name', 'email', $subscribe_form_selector );
			$esp                     = 'mailchimp';
		} elseif ( preg_match( '/mc4wp-form/', $body ) !== 0 ) {
			// MC4WP form.
			$subscribe_form_selector = apply_filters( 'newspack_campaigns_form_selector', '.mc4wp-form' );
			$email_form_field_name   = apply_filters( 'newspack_campaigns_email_form_field_name', 'EMAIL', $subscribe_form_selector );
			$esp                     = 'mailchimp';
		} elseif ( preg_match( '/\[gravityforms\s(.*)\]/', $body, $gravity_form_attributes ) !== 0 && class_exists( '\GFAPI' ) ) {
			// Gravity Forms block produces a shortcode. Check for a form ID attribute on the shortcode.
			$has_id = preg_match( '/id="(\d*)"/', $gravity_form_attributes[1], $id_matches );

			// If it has an ID we can use to look up the form.
			if ( $has_id ) {
				$form_id = $id_matches[1];
				$form    = \GFAPI::get_form( $form_id );

				// If the form ID matches an existing GF form.
				if ( $form ) {
					$form_classes      = explode( ' ', $form['cssClass'] );
					$is_subscribe_form = in_array( $newspack_form_class, $form_classes, true );
					$email_field_id    = array_reduce(
						$form['fields'],
						function( $acc, $field ) {
							if ( 'email' === $field['type'] ) {
								$acc = $field['id'];
							}
							return $acc;
						},
						null
					);

					// If the form has the required CSS class and an email input field.
					if ( $is_subscribe_form && $email_field_id ) {
						$subscribe_form_selector = apply_filters( 'newspack_campaigns_form_selector', "form.$newspack_form_class" ); // Gravity Forms applies CSS classes directly to the form element.
						$email_form_field_name   = apply_filters( 'newspack_campaigns_email_form_field_name', "input_$email_field_id", $subscribe_form_selector );
					}
				}
			}
		} else {
			// Custom forms.
			if ( preg_match( '/' . $newspack_form_class . '/', $body ) !== 0 ) {
				$subscribe_form_selector = apply_filters( 'newspack_campaigns_form_selector', '.' . $newspack_form_class );
				$email_form_field_name   = apply_filters( 'newspack_campaigns_email_form_field_name', 'email', $subscribe_form_selector );
			}
		}

		$amp_analytics_config = [
			'requests' => [
				'event' => esc_url( $endpoint ),
			],
			'triggers' => [
				'trackPageview' => [
					'on'             => 'visible',
					'request'        => 'event',
					'visibilitySpec' => [
						'selector'             => '#' . esc_attr( $element_id ),
						'visiblePercentageMin' => 50,
						'totalTimeMin'         => 250,
						'continuousTimeMin'    => 100,
					],
					'extraUrlParams' => [
						'popup_id' => esc_attr( self::canonize_popup_id( $popup['id'] ) ),
						'cid'      => Newspack_Popups_Segmentation::get_cid_param(),
					],
				],
			],
		];
		if ( $subscribe_form_selector && $email_form_field_name ) {
			$extra_params = [
				'popup_id'            => esc_attr( self::canonize_popup_id( $popup['id'] ) ),
				'cid'                 => Newspack_Popups_Segmentation::get_cid_param(),
				'mailing_list_status' => 'subscribed',
				'email'               => '${formFields[' . esc_attr( $email_form_field_name ) . ']}',
			];

			if ( ! empty( $esp ) ) {
				$extra_params['esp'] = $esp;
			}

			$amp_analytics_config['triggers']['formSubmitSuccess'] = [
				'on'             => 'amp-form-submit-success',
				'request'        => 'event',
				'selector'       => '#' . esc_attr( $element_id ) . ' ' . esc_attr( $subscribe_form_selector ),
				'extraUrlParams' => $extra_params,
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
	 * Get a unique id.
	 *
	 * @return string Unique id.
	 */
	private static function get_uniqid() {
		return 'c' . substr( uniqid(), 10 );
	}

	/**
	 * Get a shortest possible CSS class name for a form element.
	 *
	 * @param string $type Type of the form.
	 * @param string $element_id The ID of the enclosing element.
	 */
	private static function get_form_class( $type, $element_id ) {
		if ( 'action' === $type ) {
			return $element_id; // Just use the id, since this class will be shared.
		}
		$types      = [ 'dismiss' ];
		$type_index = array_search( $type, $types );
		return $element_id . $type_index; // Use a unique class name.
	}

	/**
	 * Add tracked analytics events to use in Newspack Plugin's newspack_analytics_events filter.
	 *
	 * @param object $popup The popup object.
	 * @param string $body Post body.
	 * @param string $element_id The id of the popup element.
	 */
	private static function get_analytics_events( $popup, $body, $element_id ) {
		if ( Newspack_Popups::is_preview_request() || ! Newspack_Popups::is_tracking() ) {
			return [];
		}

		$popup_id            = $popup['id'];
		$segment_ids         = isset( $popup['options'] ) && ! empty( $popup['options']['selected_segment_id'] ) ?
			explode( ',', $popup['options']['selected_segment_id'] ) :
			[];
		$segments            = array_reduce(
			$segment_ids,
			function( $acc, $segment_id ) {
				$segment = Newspack_Popups_Segmentation::get_segment( $segment_id );
				if ( $segment && isset( $segment['name'] ) ) {
					$acc[] = $segment['name'];
				}
				return $acc;
			},
			[]
		);
		$event_category      = __( 'Newspack Announcement', 'newspack-popups' );
		$formatted_placement = ucwords( str_replace( '_', ' ', $popup['options']['placement'] ) );
		$default_event_label = sprintf(
			// Translators: Analytics label with prompt details (placement, title, ID, targeted segments).
			__( '%1$s: %2$s (%3$s) - %4$s', 'newspack-popups' ),
			$formatted_placement,
			$popup['title'],
			$popup_id,
			0 < count( $segments ) ? implode( '; ', $segments ) : __( 'Everyone', 'newspack-popups' )
		);
		$has_link            = preg_match( '/<a\s/', $body ) !== 0;
		$newspack_form_class = apply_filters( 'newspack_campaigns_form_class', '.newspack-subscribe-form' );
		$newspack_form_class = '.' === substr( $newspack_form_class, 0, 1 ) ? substr( $newspack_form_class, 1 ) : $newspack_form_class; // Strip the "." class selector.
		$has_register_form   = preg_match( '/id="newspack-(register|subscribe)-(.+)"/', $body ) !== 0;
		$has_lists_field     = preg_match( '/name="lists\[\]"/', $body ) !== 0;
		$has_form            = preg_match( '/<form\s|mc4wp-form|\[gravityforms\s|' . $newspack_form_class . '/', $body ) !== 0;
		$has_dismiss_form    = self::is_overlay( $popup );

		$analytics_events = [
			[
				'on'              => 'ini-load',
				'element'         => '#' . esc_attr( $element_id ),
				'event_name'      => __( 'Load', 'newspack-popups' ),
				'non_interaction' => true,
			],
			[
				'on'              => 'visible',
				'element'         => '#' . esc_attr( $element_id ),
				'event_name'      => __( 'Seen', 'newspack-popups' ),
				'non_interaction' => true,
				'visibilitySpec'  => [
					'totalTimeMin' => 500,
				],
			],
		];

		if ( $has_link ) {
			$analytics_events[] = [
				'on'          => 'click',
				'element'     => '#' . esc_attr( $element_id ) . ' a',
				'amp_element' => '#' . esc_attr( $element_id ) . ' a',
				'event_name'  => __( 'Link Click', 'newspack-popups' ),
			];
		}

		if ( $has_form ) {
			$analytics_events[] = [
				'amp_on'     => 'amp-form-submit',
				'on'         => 'submit',
				'element'    => '#' . esc_attr( $element_id ) . ' form:not(.popup-dismiss-form)', // Not a dismissal form.
				'event_name' => __( 'Form Submission', 'newspack-popups' ),
			];
		}
		if ( $has_dismiss_form ) {
			$analytics_events[] = [
				'amp_on'          => 'amp-form-submit-success',
				'on'              => 'submit',
				'element'         => '#' . esc_attr( $element_id ) . ' .popup-dismiss-form',
				'event_name'      => __( 'Dismissal', 'newspack-popups' ),
				'non_interaction' => true,
			];
		}

		foreach ( $analytics_events as &$event ) {
			$event_label = $default_event_label;

			// If a form submission and the form contains registration + list info, append that to the event label.
			if ( isset( $event['amp_on'] ) && 'amp-form-submit' === $event['amp_on'] && $has_register_form ) {
				$event_label .= ' | ${formId}';

				// If the reg form has a lists[] field, append the value to the event label.
				if ( $has_lists_field ) {
					$event_label .= ' - ${formFields[lists[]]}';
				}
			}

			$event['id']             = self::get_uniqid();
			$event['event_category'] = esc_attr( $event_category );
			$event['event_label']    = esc_attr( $event_label );
		}

		return $analytics_events;
	}

	/**
	 * Canonise popups id. The id from WP will be an integer, but AMP does not play well with that and needs a string.
	 *
	 * @param int $popup_id Popup id.
	 */
	public static function canonize_popup_id( $popup_id ) {
		return 'id_' . $popup_id;
	}

	/**
	 * Get amp-access attributes for a popup-enclosing amp-layout tag.
	 *
	 * @param object $popup Popup.
	 */
	public static function get_access_attrs( $popup ) {
		if ( Newspack_Popups_Settings::is_non_interactive() ) {
			return '';
		}
		if ( Newspack_Popups::previewed_popup_id() ) {
			return '';
		}
		// The amp-access endpoint is queried only once (on page load), but after changing block settings,
		// the block will be re-rendered. It has to be initially visible to be seen in the Customizer preview.
		$is_hidden_initially = ! is_customize_preview();
		return 'amp-access="popups.' . esc_attr( self::canonize_popup_id( $popup['id'] ) ) . '"' . ( $is_hidden_initially ? ' amp-access-hide ' : ' ' );
	}

	/**
	 * Get data-popup-status attribute for use in previews, if viewing as an admin.
	 *
	 * @param object $popup Popup.
	 */
	public static function get_data_status_preview_attrs( $popup ) {
		if ( ! Newspack_Popups::is_user_admin() ) {
			return '';
		}
		$status = 'future' === $popup['status'] ? __( 'scheduled', 'newspack-popups' ) : $popup['status'];
		$status = 'inherit' === $popup['status'] ? __( 'draft', 'newspack-popups' ) : $popup['status']; // Avoid "inherit" status when previewing a single prompt.
		return 'data-popup-status="' . esc_attr( $status ) . '" ';
	}

	/**
	 * Adds a hook to print a hidden field with the current popup ID in forms redendered inside the popup.
	 *
	 * @param array $popup The popup data.
	 * @return void
	 */
	protected static function add_form_hooks( $popup ) {
		self::$form_hooks_popup_id = $popup['id'];
		foreach ( self::$form_hooks as $hook ) {
			add_action( $hook, [ __CLASS__, 'print_form_hidden_fields' ] );
		}
	}

	/**
	 * Removes the hook to print a hidden field with the current popup ID in forms redendered inside the popup.
	 *
	 * @param array $popup The popup data.
	 * @return void
	 */
	protected static function remove_form_hooks( $popup ) {
		self::$form_hooks_popup_id = null;
		foreach ( self::$form_hooks as $hook ) {
			remove_action( $hook, [ __CLASS__, 'print_form_hidden_fields' ] );
		}
	}

	/**
	 * Prints a hidden field with the current popup ID.
	 *
	 * @return void
	 */
	public static function print_form_hidden_fields() {
		?>
			<input
				name="newspack_popup_id"
				type="hidden"
				value="<?php echo esc_attr( self::$form_hooks_popup_id ); ?>"
			/>
		<?php
	}

	/**
	 * Generate markup for an inline popup.
	 *
	 * @param string $popup The popup object.
	 * @return string The generated markup.
	 */
	public static function generate_inline_popup( $popup ) {
		global $wp;

		do_action( 'newspack_campaigns_before_campaign_render', $popup );
		$blocks = parse_blocks( $popup['content'] );
		$body   = '';
		self::add_form_hooks( $popup );
		foreach ( $blocks as $block ) {
			$body .= render_block( $block );
		}
		self::remove_form_hooks( $popup );
		do_action( 'newspack_campaigns_after_campaign_render', $popup );

		$element_id           = self::get_uniqid();
		$endpoint             = self::get_reader_endpoint();
		$display_title        = $popup['options']['display_title'];
		$hide_border          = $popup['options']['hide_border'];
		$large_border         = $popup['options']['large_border'];
		$is_newsletter_prompt = self::has_newsletter_prompt( $popup );
		$classes              = [ 'newspack-popup' ];
		$classes[]            = 'above_header' === $popup['options']['placement'] ? 'newspack-above-header-popup' : null;
		$classes[]            = ! self::is_above_header( $popup ) ? 'newspack-inline-popup' : null;
		$classes[]            = 'publish' !== $popup['status'] ? 'newspack-inactive-popup-status' : null;
		$classes[]            = ( ! empty( $popup['title'] ) && $display_title ) ? 'newspack-lightbox-has-title' : null;
		$classes[]            = $hide_border ? 'newspack-lightbox-no-border' : null;
		$classes[]            = $large_border ? 'newspack-lightbox-large-border' : null;
		$classes[]            = $is_newsletter_prompt ? 'newspack-newsletter-prompt-inline' : null;
		$classes              = array_merge( $classes, explode( ' ', $popup['options']['additional_classes'] ) );

		$analytics_events = self::get_analytics_events( $popup, $body, $element_id );
		if ( ! empty( $analytics_events ) ) {
			add_filter(
				'newspack_analytics_events',
				function ( $evts ) use ( $analytics_events ) {
					return array_merge( $evts, $analytics_events );
				}
			);
		}

		ob_start();
		?>
			<?php self::insert_event_tracking( $popup, $body, $element_id ); ?>
			<amp-layout
				<?php echo self::get_access_attrs( $popup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo self::get_data_status_preview_attrs( $popup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
				role="button"
				tabindex="0"
				style="<?php echo esc_attr( self::container_style( $popup ) ); ?>"
				id="<?php echo esc_attr( $element_id ); ?>"
			>
				<?php if ( ! empty( $popup['title'] ) && $display_title ) : ?>
					<h1 class="newspack-popup-title"><?php echo esc_html( $popup['title'] ); ?></h1>
				<?php endif; ?>
				<?php echo do_shortcode( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</amp-layout>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate markup and styles for an overlay popup.
	 *
	 * @param string $popup The popup object.
	 * @return string The generated markup.
	 */
	public static function generate_popup( $popup ) {
		// If previewing a single prompt, override saved settings with preview settings.
		if ( Newspack_Popups::previewed_popup_id() ) {
			$popup = self::retrieve_preview_popup( Newspack_Popups::previewed_popup_id() );
		}

		if ( self::is_overlay( $popup ) && self::is_overlay( $popup ) && has_block( 'newspack-blocks/homepage-articles', $popup['content'] ) ) {
			add_filter( 'newspack_blocks_homepage_enable_duplication', '__return_true' );
			add_filter( 'newspack_blocks_homepage_shown_rendered_posts', '__return_true' );
		}

		if ( ! self::is_overlay( $popup ) ) {
			return self::generate_inline_popup( $popup );
		}

		do_action( 'newspack_campaigns_before_campaign_render', $popup );
		$blocks = parse_blocks( $popup['content'] );
		$body   = '';
		self::add_form_hooks( $popup );
		foreach ( $blocks as $block ) {
			$body .= render_block( $block );
		}
		self::remove_form_hooks( $popup );
		do_action( 'newspack_campaigns_after_campaign_render', $popup );

		$element_id            = self::get_uniqid();
		$endpoint              = self::get_reader_endpoint();
		$display_title         = $popup['options']['display_title'];
		$hide_border           = $popup['options']['hide_border'];
		$large_border          = $popup['options']['large_border'];
		$overlay_opacity       = absint( $popup['options']['overlay_opacity'] ) / 100;
		$overlay_color         = $popup['options']['overlay_color'];
		$overlay_size          = 'full' === $popup['options']['overlay_size'] ? 'full-width' : $popup['options']['overlay_size'];
		$no_overlay_background = $popup['options']['no_overlay_background'];
		$hidden_fields         = self::get_hidden_fields( $popup );
		$is_newsletter_prompt  = self::has_newsletter_prompt( $popup );
		$has_featured_image    = has_post_thumbnail( $popup['id'] );
		$classes               = array( 'newspack-lightbox', 'newspack-popup', 'newspack-lightbox-placement-' . $popup['options']['placement'], 'newspack-lightbox-size-' . $overlay_size );
		$classes[]             = ( ! empty( $popup['title'] ) && $display_title ) ? 'newspack-lightbox-has-title' : null;
		$classes[]             = $hide_border ? 'newspack-lightbox-no-border' : null;
		$classes[]             = $large_border ? 'newspack-lightbox-large-border' : null;
		$classes[]             = $is_newsletter_prompt ? 'newspack-newsletter-prompt-overlay' : null;
		$classes[]             = $no_overlay_background ? 'newspack-lightbox-no-overlay' : null;
		$classes[]             = $has_featured_image ? 'newspack-lightbox-featured-image' : null;
		$classes               = array_merge( $classes, explode( ' ', $popup['options']['additional_classes'] ) );
		$wrapper_classes       = [ 'newspack-popup-wrapper' ];
		$wrapper_classes[]     = 'publish' !== $popup['status'] ? 'newspack-inactive-popup-status' : null;
		$is_scroll_triggered   = 'scroll' === $popup['options']['trigger_type'];

		add_filter(
			'newspack_analytics_events',
			function ( $evts ) use ( $popup, $body, $element_id ) {
				return array_merge( $evts, self::get_analytics_events( $popup, $body, $element_id ) );
			}
		);

		$animation_id = 'a_' . $element_id;

		ob_start();
		?>
		<amp-layout
			<?php echo self::get_access_attrs( $popup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::get_data_status_preview_attrs( $popup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			role="button"
			tabindex="0"
			id="<?php echo esc_attr( $element_id ); ?>"
		>
			<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" data-popup-status="<?php echo esc_attr( $popup['status'] ); ?>" style="<?php echo ! $hide_border ? esc_attr( self::container_style( $popup ) ) : ''; ?>">
				<div class="newspack-popup__content-wrapper" style="<?php echo $hide_border ? esc_attr( self::container_style( $popup ) ) : ''; ?>">
					<?php if ( $has_featured_image ) : ?>
						<div class="newspack-popup__featured-image">
							<?php echo get_the_post_thumbnail( $popup['id'], 'large' ); ?>
						</div>
					<?php endif; ?>
					<div class="newspack-popup__content">
						<?php if ( ! empty( $popup['title'] ) && $display_title ) : ?>
							<h1 class="newspack-popup-title"><?php echo esc_html( $popup['title'] ); ?></h1>
						<?php endif; ?>
						<?php echo do_shortcode( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<form class="popup-dismiss-form <?php echo esc_attr( self::get_form_class( 'dismiss', $element_id ) ); ?> popup-action-form <?php echo esc_attr( self::get_form_class( 'action', $element_id ) ); ?>"
						method="POST"
						action-xhr="<?php echo esc_url( $endpoint ); ?>"
						target="_top">
						<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button on="tap:<?php echo esc_attr( $element_id ); ?>.hide" class="newspack-lightbox__close" aria-label="<?php esc_html_e( 'Close Pop-up', 'newspack-popups' ); // phpcs:ignore WordPressVIPMinimum.Security.ProperEscapingFunction.htmlAttrNotByEscHTML ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>
						</button>
					</form>
				</div>
			</div>
			<?php if ( ! $no_overlay_background ) : ?>
				<?php if ( Newspack_Popups_Settings::enable_dismiss_overlays_on_background_tap() ) : ?>
					<form class="popup-dismiss-form <?php echo esc_attr( self::get_form_class( 'dismiss', $element_id ) ); ?> popup-action-form <?php echo esc_attr( self::get_form_class( 'action', $element_id ) ); ?>"
					method="POST"
					action-xhr="<?php echo esc_url( $endpoint ); ?>"
					target="_top">
						<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button style="opacity: <?php echo floatval( $overlay_opacity ); ?>;background-color:<?php echo esc_attr( $overlay_color ); ?>;" class="newspack-lightbox-shim" on="tap:<?php echo esc_attr( $element_id ); ?>.hide"></button>
					</form>
				<?php else : ?>
				<div style="opacity: <?php echo floatval( $overlay_opacity ); ?>;background-color:<?php echo esc_attr( $overlay_color ); ?>;" class="newspack-lightbox-shim"></div>
				<?php endif; ?>
			<?php endif; ?>
		</amp-layout>
		<?php if ( $is_scroll_triggered ) : ?>
			<div id="page-position-marker_<?php echo esc_attr( $animation_id ); ?>" style="position: absolute; top: <?php echo esc_attr( $popup['options']['trigger_scroll_progress'] ); ?>%"></div>
			<amp-position-observer
				target="page-position-marker_<?php echo esc_attr( $animation_id ); ?>"
				on="enter:<?php echo esc_attr( $animation_id ); ?>.start;"
				once
				layout="nodisplay"
			></amp-position-observer>
		<?php endif; ?>
		<amp-animation id="<?php echo esc_attr( $animation_id ); ?>" layout="nodisplay" <?php echo $is_scroll_triggered ? '' : 'trigger="visibility"'; ?>>
			<script type="application/json">
				{
					"duration": "125ms",
					"fill": "both",
					"iterations": "1",
					"direction": "alternate",
					"animations": [
						{
							"selector": "#<?php echo esc_attr( $element_id ); ?>",
							"delay": "<?php echo esc_html( self::get_delay( $popup ) ); ?>",
							"keyframes": {
								"opacity": ["0", "1"],
								"visibility": ["hidden", "visible"]
							}
						},
						{
							"selector": "#<?php echo esc_attr( $element_id ); ?>",
							"delay": "<?php echo esc_html( self::get_delay( $popup ) - 500 ); ?>",
							"keyframes": {
								"transform": ["translateY(90vh)", "translateY(0vh)"]
							}
						},
						{
								"selector": "#<?php echo esc_attr( $element_id ); ?> .newspack-popup-wrapper",
								"delay": "<?php echo intval( $popup['options']['trigger_delay'] ) * 1000 + 625; ?>",
								"keyframes": {
									<?php if ( in_array( $popup['options']['placement'], [ 'top', 'top_left', 'top_right' ] ) ) : ?>
										"transform": ["translateY(-100%)", "translateY(0)"]
									<?php elseif ( in_array( $popup['options']['placement'], [ 'bottom', 'bottom_left', 'bottom_right' ] ) ) : ?>
										"transform": ["translateY(100%)", "translateY(0)"]
									<?php else : ?>
										"opacity": ["0", "1"]
									<?php endif; ?>
								}
						}
					]
				}
			</script>
		</amp-animation>
		<?php
		self::insert_event_tracking( $popup, $body, $element_id );
		if ( self::is_overlay( $popup ) && has_block( 'newspack-blocks/homepage-articles', $popup['content'] ) ) {
			add_filter( 'newspack_blocks_homepage_enable_duplication', '__return_false' );
			add_filter( 'newspack_blocks_homepage_shown_rendered_posts', '__return_false' );
		}
		?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Pick either white or black, whatever has sufficient contrast with the color being passed to it.
	 * Copied from https://github.com/Automattic/newspack-theme/blob/master/newspack-theme/inc/template-functions.php#L401-L431
	 *
	 * @param  string $background_color Hexidecimal value of the background color.
	 * @return string Either black or white hexidecimal values.
	 *
	 * @ref https://stackoverflow.com/questions/1331591/given-a-background-color-black-or-white-text
	 */
	public static function foreground_color_for_background( $background_color ) {
		// hex RGB.
		$r1 = hexdec( substr( $background_color, 1, 2 ) );
		$g1 = hexdec( substr( $background_color, 3, 2 ) );
		$b1 = hexdec( substr( $background_color, 5, 2 ) );
		// Black RGB.
		$black_color    = '#000';
		$r2_black_color = hexdec( substr( $black_color, 1, 2 ) );
		$g2_black_color = hexdec( substr( $black_color, 3, 2 ) );
		$b2_black_color = hexdec( substr( $black_color, 5, 2 ) );
		// Calc contrast ratio.
		$l1             = 0.2126 * pow( $r1 / 255, 2.2 ) +
			0.7152 * pow( $g1 / 255, 2.2 ) +
			0.0722 * pow( $b1 / 255, 2.2 );
		$l2             = 0.2126 * pow( $r2_black_color / 255, 2.2 ) +
			0.7152 * pow( $g2_black_color / 255, 2.2 ) +
			0.0722 * pow( $b2_black_color / 255, 2.2 );
		$contrast_ratio = 0;
		if ( $l1 > $l2 ) {
			$contrast_ratio = (int) ( ( $l1 + 0.05 ) / ( $l2 + 0.05 ) );
		} else {
			$contrast_ratio = (int) ( ( $l2 + 0.05 ) / ( $l1 + 0.05 ) );
		}
		if ( $contrast_ratio > 5 ) {
			// If contrast is more than 5, return black color.
			return '#000';
		} else {
			// if not, return white color.
			return '#fff';
		}
	}

	/**
	 * Generate inline styles for Popup element.
	 *
	 * @param  object $popup A Pop-up object.
	 * @return string Inline styles attribute.
	 */
	public static function container_style( $popup ) {
		$hide_border      = $popup['options']['hide_border'];
		$background_color = $popup['options']['background_color'];
		$foreground_color = self::foreground_color_for_background( $background_color );
		return 'background-color:' . $background_color . ';color:' . $foreground_color;
	}

	/**
	 * Endpoint to handle Pop-up data.
	 *
	 * @return string Endpoint URL.
	 */
	public static function get_reader_endpoint() {
		return plugins_url( '../api/campaigns/index.php', __FILE__ );
	}

	/**
	 * Generate hidden fields to be used in all forms.
	 *
	 * @param  object $popup A Pop-up object.
	 * @return string Hidden fields markup.
	 */
	public static function get_hidden_fields( $popup ) {
		ob_start();
		?>
		<input
			name="popup_id"
			type="hidden"
			value="<?php echo esc_attr( self::canonize_popup_id( $popup['id'] ) ); ?>"
		/>
		<input
			name="cid"
			type="hidden"
			value="<?php echo esc_attr( Newspack_Popups_Segmentation::get_cid_param() ); ?>"
			data-amp-replace="CLIENT_ID"
		/>
		<input
			name="mailing_list_status"
			type="hidden"
			[value]="mailing_list_status"
		/>
		<input
			name="is_newsletter_popup"
			type="hidden"
			value="<?php echo esc_attr( self::has_newsletter_prompt( $popup ) ); ?>"
		/>
		<input
			name="dismiss"
			type="hidden"
			value="1"
		/>
		<?php
		return ob_get_clean();
	}
}
