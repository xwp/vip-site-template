<?php
/**
 * Newspack Popups API
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * API endpoints
 */
final class Newspack_Popups_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_api_endpoints() {
		\register_rest_route(
			'newspack-popups/v1',
			'settings',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_settings_standalone' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'settingsToUpdate' => [
						'validate_callback' => [ __CLASS__, 'validate_settings' ],
						'sanitize_callback' => [ __CLASS__, 'sanitize_array' ],
					],
				],
			]
		);

		\register_rest_route(
			'newspack-popups/v1',
			'prompts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_inline_and_manual_prompts' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'search'   => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'_fields'  => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'include'  => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'per_page' => [
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		\register_rest_route(
			'newspack-popups/v1',
			'/(?P<original_id>\d+)/(?P<id>\d+)/duplicate',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_duplicate_title' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'original_id' => [
						'sanitize_callback' => 'absint',
					],
					'id'          => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		\register_rest_route(
			'newspack-popups/v1',
			'/(?P<id>\d+)/duplicate',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_duplicate_popup' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'id'    => [
						'sanitize_callback' => 'absint',
					],
					'title' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Recursively sanitize an array of arbitrary values.
	 *
	 * @param array $array Array to be sanitized.
	 * @return array Sanitized array.
	 */
	public static function sanitize_array( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::sanitize_array( $value );
			} elseif ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$value = intval( $value );
			} else {
				$value = boolval( $value );
			}

			$array[ $key ] = $value;
		}

		return $array;
	}

	/**
	 * Validate settings to be updated.
	 *
	 * @param String $settings_to_update Associative array of settings to be updated.
	 */
	public static function validate_settings( $settings_to_update ) {
		$valid = true;

		foreach ( $settings_to_update as $key => $value ) {
			if ( ! self::validate_settings_option_name( $key ) ) {
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Validate settings option key.
	 *
	 * @param String $key Meta key.
	 */
	public static function validate_settings_option_name( $key ) {
		return in_array(
			$key,
			array_map(
				function ( $setting ) {
					return $setting['key'];
				},
				\Newspack_Popups_Settings::get_settings()
			)
		);
	}

	/**
	 * Permission callback for authenticated requests.
	 *
	 * @return boolean if user can edit stuff.
	 */
	public static function permission_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => 403,
				]
			);
		}
		return true;
	}

	/**
	 * Handler for API settings update endpoint.
	 * This endpoint is used by the standlone Settings page, which
	 * is only used if the main Newspack plugin UI isn't available.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public static function update_settings_standalone( $request ) {
		$settings_to_update = $request['settingsToUpdate'];

		foreach ( $settings_to_update as $key => $value ) {
			$result = \Newspack_Popups_Settings::set_settings_standalone(
				[
					'option_value' => $value,
					'option_name'  => $key,
				]
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return \rest_ensure_response( \Newspack_Popups_Settings::get_settings() );
	}

	/**
	 * Get inline prompts with the given params.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public static function get_inline_and_manual_prompts( $request ) {
		$params   = $request->get_params();
		$search   = ! empty( $params['search'] ) ? $params['search'] : null;
		$include  = ! empty( $params['include'] ) ? explode( ',', $params['include'] ) : null;
		$per_page = ! empty( $params['per_page'] ) ? $params['per_page'] : 10;

		// Query args.
		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'meta_key'       => 'placement',
			'meta_compare'   => 'IN',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value'     => [ 'inline', 'manual' ],
		];

		// Look up by title only if provided with a search term and not post IDs.
		if ( ! empty( $search ) && empty( $include ) ) {
			$args['s'] = esc_sql( $search );
		}

		// If given post IDs to include, just get those.
		if ( ! empty( $include ) && count( $include ) && empty( $search ) ) {
			$args['post__in'] = $include;
			$args['orderby']  = 'post__in';
			$args['order']    = 'ASC';
		}

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			return new \WP_REST_Response(
				array_map(
					function( $post ) {
						$item = [
							'id'      => $post->ID,
							'title'   => $post->post_title,
							'content' => apply_filters( 'the_content', $post->post_content ),
						];

						return $item;
					},
					$query->posts
				),
				200
			);
		}

		return new \WP_REST_Response( [] );
	}

	/**
	 * Get default title for a duplicated prompt.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_get_duplicate_title( $request ) {
		$response = Newspack_Popups::get_duplicate_title( $request['original_id'], $request['id'] );
		return rest_ensure_response( $response );
	}

	/**
	 * Duplicate a prompt.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_duplicate_popup( $request ) {
		$response = Newspack_Popups::duplicate_popup( $request['id'], $request['title'] );
		return rest_ensure_response( $response );
	}
}
$newspack_popups_api = new Newspack_Popups_API();
