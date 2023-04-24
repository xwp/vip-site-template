<?php
/**
 * Newspack Placements Plugin
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Placements Plugin Class.
 */
final class Newspack_Popups_Custom_Placements {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Custom_Placements
	 */
	protected static $instance = null;

	/**
	 * Name of the option to store placements under.
	 */
	const PLACEMENTS_OPTION_NAME = 'newspack_popups_custom_placements';

	/**
	 * Main Newspack Placements Plugin Instance.
	 * Ensures only one instance of Newspack Placements Plugin Instance is loaded or can be loaded.
	 *
	 * @return Newspack Placements Plugin Instance - Main instance.
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
		add_action( 'init', [ __CLASS__, 'manage_view_assets' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'rest_api_init' ] );
	}

	/**
	 * Enqueue front-end assets.
	 */
	public static function manage_view_assets() {
		// Do nothing in editor environment.
		if ( is_admin() ) {
			return;
		}

		$src_directory  = dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/src/blocks/';
		$dist_directory = dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/';
		$iterator       = new \DirectoryIterator( $src_directory );

		foreach ( $iterator as $block_directory ) {
			if ( ! $block_directory->isDir() || $block_directory->isDot() ) {
				continue;
			}
			$type = $block_directory->getFilename();

			/* If view.php is found, include it and use for block rendering. */
			$view_php_path = $src_directory . $type . '/view.php';
			if ( file_exists( $view_php_path ) ) {
				include_once $view_php_path;
				continue;
			}
		}
	}

	/**
	 * Initialise REST API endpoints.
	 */
	public static function rest_api_init() {
		\register_rest_route(
			'newspack-popups/v1',
			'custom-placement',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_prompts_for_custom_placement' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Get prompts assigned to a given custom placement.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Prompt IDs and titles matching the request params.
	 */
	public static function api_get_prompts_for_custom_placement( $request ) {
		$custom_placement_id = self::validate_custom_placement_id( $request['custom_placement'] );

		if ( empty( $custom_placement_id ) ) {
			return new \WP_Error(
				'newspack_popups_custom_placement',
				esc_html__( 'Invalid custom placement ID.', 'newspack-popups' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}

		$prompts = self::get_prompts_for_custom_placement( [ $custom_placement_id ] );

		if ( ! empty( $prompts ) ) {
			$sorted_prompts = array_map(
				function( $post ) {
					$segment_ids = get_post_meta( $post->ID, 'selected_segment_id', true );
					$segments    = [
						[
							'name'     => 'Everyone',
							'priority' => PHP_INT_MAX,
						],
					];

					if ( $segment_ids ) {
						$segment_ids = explode( ',', $segment_ids );
						$segments    = array_reduce(
							$segment_ids,
							function( $acc, $segment_id ) {
								$segment = Newspack_Popups_Segmentation::get_segment( $segment_id );

								// Only return segments that exist (result can be null if a segment has been deleted).
								if ( $segment ) {
									$acc[] = $segment;
								}

								return $acc;
							},
							[]
						);
					}

					return [
						'id'       => $post->ID,
						'title'    => $post->post_title,
						'segments' => $segments,
					];
				},
				$prompts
			);

			// Sort by segment priority.
			usort(
				$sorted_prompts,
				function( $a, $b ) {
					$priority_b = intval( $b['segments'][0]['priority'] );
					$priority_a = intval( $a['segments'][0]['priority'] );

					return $priority_a - $priority_b;
				}
			);

			return new \WP_REST_Response( $sorted_prompts );
		}

		return [];
	}

	/**
	 * Query for prompts with the given custom placements.
	 *
	 * @param array         $custom_placement_ids Array of IDs for custom placements to look up.
	 * @param string        $fields Field values to return in response: 'all' or 'ids'.
	 * @param array|boolean $categories Array of category IDs to filter by, empty array if fetching only uncategorized prompts, or false to ignore categories.
	 *
	 * @return array Array of prompt posts matching the custom placement.
	 */
	public static function get_prompts_for_custom_placement( $custom_placement_ids, $fields = 'all', $categories = false ) {
		if ( empty( $custom_placement_ids ) ) {
			return [];
		}
		$args = [
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			'fields'         => $fields,
			'meta_key'       => 'placement',
			'meta_compare'   => 'IN',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value'     => $custom_placement_ids,
		];

		if ( ! empty( $categories ) ) {
			$args['category__in'] = $categories;
		} elseif ( is_array( $categories ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'] = [
				[
					'taxonomy' => 'category',
					'operator' => 'NOT EXISTS',
				],
			];
		}

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts;
		}

		return [];
	}

	/**
	 * Validate a custom placement ID.
	 *
	 * @param string $custom_placement_id The slug of the custom placement to check.
	 * @return string|boolean The ID if it's valid, false if not.
	 */
	public static function validate_custom_placement_id( $custom_placement_id ) {
		$custom_placement_id = sanitize_text_field( $custom_placement_id );
		return in_array( $custom_placement_id, self::get_custom_placement_values() ) ? $custom_placement_id : false;
	}

	/**
	 * Get default placements that exist for all sites.
	 */
	public static function get_default_custom_placements() {
		return [
			'custom1' => __( 'Custom Placement 1' ),
			'custom2' => __( 'Custom Placement 2' ),
			'custom3' => __( 'Custom Placement 3' ),
		];
	}

	/**
	 * Get all configured placements.
	 *
	 * @return array Array of placements.
	 */
	public static function get_custom_placements() {
		$placements = get_option( self::PLACEMENTS_OPTION_NAME, [] );
		return array_merge( self::get_default_custom_placements(), $placements );
	}

	/**
	 * Get a simple array of placement values.
	 *
	 * @return array Array of placement values.
	 */
	public static function get_custom_placement_values() {
		return array_keys( self::get_custom_placements() );
	}

	/**
	 * Determine whether the given prompt should be displayed via custom placement.
	 *
	 * @param object $prompt The prompt to assess.
	 * @return boolean Whether or not the prompt has a custom placement.
	 */
	public static function is_custom_placement_or_manual( $prompt ) {
		return (
			'manual' === $prompt['options']['frequency'] ||
			in_array( $prompt['options']['placement'], self::get_custom_placement_values() )
		);
	}
}
Newspack_Popups_Custom_Placements::instance();
