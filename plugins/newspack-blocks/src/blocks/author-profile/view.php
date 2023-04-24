<?php
/**
 * Server-side render functions for the Author Profile block.
 *
 * @package WordPress
 */

/**
 * Dynamic block registration.
 */
function newspack_blocks_register_author_profile() {
	$block_json = json_decode(
		file_get_contents( __DIR__ . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		true
	);

	if ( class_exists( '\Newspack\Authors_Custom_Fields' ) ) {
		$author_custom_fields = \Newspack\Authors_Custom_Fields::get_custom_fields();
		foreach ( $author_custom_fields as $field ) {
			$block_json['attributes'][ 'show' . $field['name'] ] = [
				'type'    => 'boolean',
				'default' => true,
			];
		}
	}

	register_block_type(
		'newspack-blocks/' . $block_json['name'],
		[
			'attributes'      => $block_json['attributes'],
			'render_callback' => 'newspack_blocks_render_block_author_profile',
		]
	);
}

/**
 * Given a numeric ID, get the corresponding WP user or Co-authors Plus guest author.
 *
 * @param int     $author_id Author ID to look up.
 * @param int     $avatar_size Size of the avatar image to fetch.
 * @param boolean $hide_default If true, don't show default avatars.
 * @param boolean $is_guest_author If true, search for guest authors. If false, only search for WP users.
 * @return object|boolean Author object in standardized format, or false if none exists.
 */
function newspack_blocks_get_author_or_guest_author( $author_id, $avatar_size = 128, $hide_default = false, $is_guest_author = true ) {
	$wp_user = get_user_by( 'id', $author_id );
	$author  = false;

	// First, see if the $author_id is a guest author.
	if ( class_exists( 'CoAuthors_Guest_Authors' ) && $is_guest_author ) {
		// Check if the ID given is a WP user with linked guest author.
		$linked_guest_author = false;

		if ( $wp_user ) {
			$linked_guest_author = WP_REST_Newspack_Authors_Controller::get_linked_guest_author( $wp_user->user_login );
		}

		if ( $linked_guest_author && isset( $linked_guest_author->ID ) ) {
			$author_id = $linked_guest_author->ID;
		}

		$author = ( new CoAuthors_Guest_Authors() )->get_guest_author_by( 'id', $author_id );
		$avatar = function_exists( 'coauthors_get_avatar' ) ? coauthors_get_avatar( $author, $avatar_size ) : false;

		// Format CAP guest author object to return to the render function.
		if ( $author && isset( $author->ID ) ) {
			$author = WP_REST_Newspack_Authors_Controller::fill_guest_author_data(
				[
					'id' => $author_id,
				],
				$author
			);

			if ( $avatar && ( false === strpos( $avatar, 'avatar-default' ) || ! $hide_default ) ) {
				$author['avatar'] = $avatar;
			}
		}
	}

	// If $author is still false, see if it's a standard WP User.
	if ( ! $author && $wp_user && isset( $wp_user->data ) ) {
		$author = WP_REST_Newspack_Authors_Controller::fill_user_data(
			[
				'id' => $author_id,
			],
			$wp_user
		);

		$avatar = get_avatar( $author_id, $avatar_size );
		if ( $avatar && ( false === strpos( $avatar, 'avatar-default' ) || ! $hide_default ) ) {
			$author['avatar'] = $avatar;
		}
	}

	return $author;
}

/**
 * Block render callback.
 *
 * @param array $attributes Block attributes.
 */
function newspack_blocks_render_block_author_profile( $attributes ) {
	// Bail if there's no author ID for this block.
	if ( empty( $attributes['authorId'] ) ) {
		return;
	}

	// Get the author by ID.
	$author = newspack_blocks_get_author_or_guest_author( intval( $attributes['authorId'] ), intval( $attributes['avatarSize'] ), $attributes['avatarHideDefault'], $attributes['isGuestAuthor'] );

	// Bail if there's no author or guest author with the saved ID.
	if ( empty( $author ) ) {
		return;
	}

	Newspack_Blocks::enqueue_view_assets( 'author-profile' );

	$content = Newspack_Blocks::template_include(
		'author-profile-card',
		[
			'attributes' => $attributes,
			'author'     => $author,
		]
	);

	return $content;
}

add_action( 'init', 'newspack_blocks_register_author_profile' );
