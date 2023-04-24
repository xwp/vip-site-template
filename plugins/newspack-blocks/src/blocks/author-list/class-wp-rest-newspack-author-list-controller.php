<?php
/**
 * WP_REST_Newspack_Authors_Controller file.
 *
 * @package WordPress
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
/**
 * Class WP_REST_Newspack_Authors_Controller.
 */
class WP_REST_Newspack_Author_List_Controller extends WP_REST_Newspack_Authors_Controller {
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

	/**
	 * Constructs the controller.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'newspack-blocks/v1';
		$this->rest_base = 'author-list';
	}

	/**
	 * Registers the necessary REST API routes.
	 *
	 * @access public
	 */
	public function register_routes() {
		// Endpoint to get authors.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'api_get_all_authors' ],
					'args'                => [
						'author_id'     => [
							'sanitize_callback' => 'absint',
						],
						'author_roles'  => [
							'sanitize_callback' => 'WP_REST_Newspack_Author_List_Controller::sanitize_array',
						],
						'author_types'  => [
							'sanitize_callback' => 'WP_REST_Newspack_Author_List_Controller::sanitize_array',
						],
						'exclude'       => [
							'sanitize_callback' => 'WP_REST_Newspack_Author_List_Controller::sanitize_array',
						],
						'exclude_empty' => [
							'sanitize_callback' => 'absint',
						],
						'offset'        => [
							'sanitize_callback' => 'absint',
						],
						'per_page'      => [
							'sanitize_callback' => 'absint',
						],
						'search'        => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'fields'        => [
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Sanitize an array of text or number values.
	 *
	 * @param array $array Array of text or float values to be sanitized.
	 * @return array Sanitized array.
	 */
	public static function sanitize_array( $array ) {
		foreach ( $array as $value ) {
			if ( is_array( $value ) ) {
				$value = self::sanitize_array( $value );
			} else {
				if ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
				} else {
					$value = floatval( $value );
				}
			}
		}

		return $array;
	}

	/**
	 * Get a list of user roles on this site that have the edit_posts capability.
	 *
	 * @return array List of roles with edit_posts capability.
	 */
	public function get_editable_roles() {
		global $wp_roles;

		$editable_roles = array_reduce(
			$wp_roles->roles,
			function( $acc, $role ) {
				if ( isset( $role['capabilities'] ) && isset( $role['capabilities']['edit_posts'] ) && $role['capabilities']['edit_posts'] ) {
					$acc[] = $role['name'];
				}
				return $acc;
			},
			[]
		);

		/**
		 * Filter the array of editable roles so other plugins can add/remove as needed.
		 * The array should be a flat array of the name of each role as registered via add_role.
		 * https://developer.wordpress.org/reference/functions/add_role/
		 *
		 * @param array $editable_roles Array of editable role names as registered via add_role.
		 *
		 * @return array Filtered array of roles.
		 */
		return apply_filters( 'newspack_blocks_author_list_editable_roles', $editable_roles );
	}

	/**
	 * API request handler to fetch all authors matching the given request params.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_get_all_authors( $request ) {
		$options = [];

		if ( ! empty( $request->get_param( 'author_type' ) ) ) {
			$options['author_type'] = $request->get_param( 'author_type' );
		}

		if ( ! empty( $request->get_param( 'author_roles' ) ) ) {
			$options['author_roles'] = $request->get_param( 'author_roles' );
		}

		if ( ! empty( $request->get_param( 'exclude' ) ) && is_array( $request->get_param( 'exclude' ) ) ) {
			$options['exclude'] = $request->get_param( 'exclude' ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
		}

		if ( ! empty( $request->get_param( 'exclude_empty' ) ) ) {
			$options['exclude_empty'] = true;
		}

		if ( ! empty( $request->get_param( 'avatar_hide_default' ) ) ) {
			$options['avatar_hide_default'] = true;
		}

		if ( ! empty( $request->get_param( 'fields' ) ) ) {
			$options['fields'] = explode( ',', $request->get_param( 'fields' ) );
		}

		$combined_authors = $this->get_all_authors( $options );
		$response         = new \WP_REST_Response( $combined_authors );
		$response->header( 'x-wp-total', count( $combined_authors ) );

		return rest_ensure_response( $response );
	}

	/**
	 * Returns an array of combined authors and guest authors.
	 *
	 * @param array $options Associative array of options used to query authors.
	 *
	 * @return array Array of all matching authors and/or guest authors on the site.
	 */
	public function get_all_authors( $options = [] ) {
		$default_options = [
			'author_type'         => 'all',
			'author_roles'        => $this->get_editable_roles(),
			'avatar_hide_default' => false,
			'exclude'             => [], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			'exclude_empty'       => false,
			'fields'              => [ 'id', 'name', 'bio', 'email', 'social', 'avatar', 'url' ],
			'per_page'            => 10,
		];
		$options         = wp_parse_args( $options, $default_options );
		$fields          = $options['fields'];
		$current_page    = 1;

		// Array to store all authors.
		$all_guest_authors = [];
		$all_users         = [];

		// Get Co-authors guest authors, only if CAP plugin is active and the author type specified includes guest authors.
		if ( in_array( $options['author_type'], [ 'all', 'guest-authors' ], true ) && class_exists( 'CoAuthors_Guest_Authors' ) ) {
			$guest_author_args = [
				'post_type'      => 'guest-author',
				'posts_per_page' => $options['per_page'],
				'orderby'        => 'title',
				'order'          => 'ASC',
			];

			if ( ! empty( $options['exclude'] ) ) {
				$guest_author_args['post__not_in'] = array_values(
					array_map(
						function( $item ) {
							return isset( $item['value'] ) ? $item['value'] : $item;
						},
						array_filter(
							$options['exclude'],
							function( $item ) {
								return isset( $item['isGuest'] ) ? ! empty( $item['isGuest'] ) : true;
							}
						)
					)
				);
			}

			$results           = new \WP_Query( $guest_author_args );
			$number_of_pages   = $results->max_num_pages;
			$all_guest_authors = array_merge( $all_guest_authors, $results->posts );

			// Keep querying until we have all guest authors.
			if ( $current_page < $number_of_pages ) {
				while ( $current_page < $number_of_pages ) {
					$current_page               ++;
					$guest_author_args['paged'] = $current_page;
					$results                    = new \WP_Query( $guest_author_args );
					$all_guest_authors          = array_merge( $all_guest_authors, $results->posts );
				}
			}
		}

		if ( in_array( $options['author_type'], [ 'all', 'users' ], true ) ) {
			// Reset current page for new query.
			$current_page         = 1;
			$exclude_empty        = $options['exclude_empty'] ? true : false;
			$published_post_types = $exclude_empty ? [ 'post' ] : null;

			// Get WP users.
			$user_args = [
				'role__in'            => $options['author_roles'],
				'orderby'             => 'display_name',
				'order'               => 'ASC',
				'number'              => $options['per_page'],
				/**
				 * Filter the post types to check for user query. By default, only users with at least one
				 * published post will be included in the results. Filter this array to add/remove post types
				 * or set to `null` to include all users even if they have no published posts.
				 *
				 * @param array|null $post_types Array of post types to check, or null to bypass the check.
				 * @param boolean    $exclude_empty True if the block is set to exclude authors with 0 posts, otherwise false.
				 *
				 * @return array|null Filtered value.
				 */
				'has_published_posts' => apply_filters( 'newspack_blocks_author_list_post_types', $published_post_types, $exclude_empty ),
			];

			if ( ! empty( $options['exclude'] ) ) {
				$user_args['exclude'] = array_values( // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
					array_map(
						function( $item ) {
							return isset( $item['value'] ) ? $item['value'] : $item;
						},
						array_filter(
							$options['exclude'],
							function( $item ) {
								return isset( $item['isGuest'] ) ? empty( $item['isGuest'] ) : true;
							}
						)
					)
				);
			}

			$results         = new \WP_User_Query( $user_args );
			$user_total      = $results->get_total();
			$number_of_pages = ceil( $user_total / $options['per_page'] );
			$all_users       = array_merge( $all_users, $results->get_results() );

			// Keep querying until we have all users.
			if ( $current_page < $number_of_pages ) {
				while ( $current_page < $number_of_pages ) {
					$current_page       ++;
					$user_args['paged'] = $current_page;
					$results            = new \WP_User_Query( $user_args );
					$all_users          = array_merge( $all_users, $results->get_results() );
				}
			}
		}

		// Format and combine results.
		$linked_accounts  = []; // Keep track of linked accounts so we can exclude WP users in favor of the guest authors.
		$combined_authors = array_merge(
			array_reduce(
				$all_guest_authors,
				function( $acc, $guest_author ) use ( $fields, $options, &$linked_accounts ) {
					if ( $guest_author && class_exists( 'CoAuthors_Guest_Authors' ) ) {
						$last_name         = get_post_meta( $guest_author->ID, 'cap-last_name', true );
						$guest_author_data = [
							'id'         => intval( $guest_author->ID ),
							'registered' => $guest_author->post_date,
							'is_guest'   => true,
							'last_name'  => ! empty( $last_name ) && false !== strpos( $guest_author->post_title, $last_name ) ? $last_name : $guest_author->post_title,
						];

						$guest_author   = ( new CoAuthors_Guest_Authors() )->get_guest_author_by( 'id', $guest_author->ID );
						$author_term    = ( new CoAuthors_Plus() )->get_author_term( $guest_author );
						$post_count     = is_object( $author_term ) && isset( $author_term->count ) ? $author_term->count : 0;
						$linked_account = isset( $guest_author->linked_account ) ? $guest_author->linked_account : null;

						// Post count = guest author posts + linked WP user posts.
						if ( ! empty( $linked_account ) ) {
							$linked_accounts[] = $linked_account;
							$linked_user       = get_user_by( 'login', $linked_account );

							if ( $linked_user ) {
								$post_count += function_exists( 'wpcom_vip_count_user_posts' ) ?
									wpcom_vip_count_user_posts(
										$linked_user->ID,
										[ 'any' ], // Any post type.
										true // But public posts only.
									) :
									count_user_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.count_user_posts_count_user_posts
										$linked_user->ID,
										[ 'any' ], // Any post type.
										true // But public posts only.
									);
							}
						}

						// Only include users with at least one published post.
						if ( 0 === $post_count && $options['exclude_empty'] ) {
							return $acc;
						}

						if ( in_array( 'avatar', $fields, true ) && function_exists( 'coauthors_get_avatar' ) ) {
							$avatar     = coauthors_get_avatar( $guest_author, 256, $options['avatar_hide_default'] ? 'blank' : '' );
							$is_default = false !== strpos( $avatar, 'avatar-default' ) || false !== strpos( $avatar, 'd=blank' );

							if ( $avatar && ( ! $is_default || ! $options['avatar_hide_default'] ) ) {
								$guest_author_data['avatar'] = $avatar;
							}
						}
						$guest_author_data = self::fill_guest_author_data( $guest_author_data, $guest_author, $fields );

						$acc[] = $guest_author_data;
					}
					return $acc;
				},
				[]
			),
			array_reduce(
				$all_users,
				function( $acc, $user ) use ( $fields, $options, &$linked_accounts ) {
					if ( $user ) {
						$is_linked = in_array( $user->data->user_login, $linked_accounts, true );

						// If linked to a guest author, show only the guest author.
						if ( $is_linked ) {
							return $acc;
						}

						$last_name     = get_user_meta( intval( $user->data->ID ), 'last_name', true );
						$fallback_name = ! empty( $user->data->display_name ) ? $user->data->display_name : $user->data->user_login;
						$user_data     = [
							'id'         => intval( $user->data->ID ),
							'registered' => $user->data->user_registered,
							'is_guest'   => false,
							'last_name'  => ! empty( $last_name ) && false !== strpos( $fallback_name, $last_name ) ? $last_name : $fallback_name,
						];

						if ( in_array( 'avatar', $fields, true ) ) {
							$avatar     = get_avatar( $user->data->ID, 256, $options['avatar_hide_default'] ? 'blank' : '' );
							$is_default = false !== strpos( $avatar, 'avatar-default' ) || false !== strpos( $avatar, 'd=blank' );

							if ( $avatar && ( ! $is_default || ! $options['avatar_hide_default'] ) ) {
								$user_data['avatar'] = $avatar;
							}
						}

						$user_data = self::fill_user_data( $user_data, $user, $fields );

						$acc[] = $user_data;
					}
					return $acc;
				},
				[]
			)
		);

		// Sort combined authors array by last name (or display name if none).
		usort(
			$combined_authors,
			function( $a, $b ) {
				return strcmp(
					strtolower( $a['last_name'] ),
					strtolower( $b['last_name'] )
				);
			}
		);

		return $combined_authors;
	}
}
