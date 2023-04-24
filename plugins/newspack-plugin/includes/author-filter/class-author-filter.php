<?php
/**
 * Adds a filter by author to the Posts page in the Admin dashboard.
 *
 * @package Newspack
 */

namespace Newspack;

use WP_Post_Type;
use WP_User_Query;

/**
 * Author Filter class
 */
class Author_Filter {

	/**
	 * Flag to make sure hooks are initialized only once
	 *
	 * @var boolean
	 */
	private static $initiated = false;

	/**
	 * Initializes the hook
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::$initiated ) {
			add_action( 'restrict_manage_posts', array( __CLASS__, 'author_filters_admin' ) );
			self::$initiated = true;
		}
	}

	/**
	 * Checks if the Co Authors Plus plugin is enabled
	 *
	 * If $post_type is informed, will check if it is enabled for this specific post type
	 *
	 * @param string $post_type The post type you want to check if Co Authors plus is enabled to.
	 * @return boolean
	 */
	private static function is_coauthors_plus_enabled( $post_type = '' ) {
		global $coauthors_plus;
		if ( ! is_object( $coauthors_plus ) || ! method_exists( $coauthors_plus, 'search_authors' ) ) {
			return false;
		}
		if ( ! empty( $post_type ) ) {
			return $coauthors_plus->is_post_type_enabled( $post_type );
		}
		return true;
	}

	/**
	 * Echoes a User Dropdown to be added to the Filters
	 *
	 * @param string $post_type The post type the action is acting on.
	 * @return void
	 */
	public static function author_filters_admin( $post_type ) {

		// Disable this for sites with co authors plus enabled until we fix its issue with large sites.
		if ( self::is_coauthors_plus_enabled( $post_type ) ) {
			return;
		}

		$excluded_post_types = [ 'attachment', 'revision', 'nav_menu_item' ];

		if ( in_array( $post_type, $excluded_post_types, true ) ) {
			return;
		}

		$type_object = get_post_type_object( $post_type );

		if ( ! $type_object instanceof WP_Post_Type ) {
			return;
		}

		if ( self::is_coauthors_plus_enabled( $post_type ) ) {
			// When using Coauthors plugin, dont show the filter if user is browsing the "Mine" tab. It can lead to inconsistent UI.
			if ( ! empty( $_GET['author'] ) && get_current_user_id() === (int) $_GET['author'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			$value_field = 'user_login';
			$select_name = 'author_name';
			$users       = self::get_options_from_coauthors();
		} else {
			$value_field = 'ID';
			$select_name = 'author';
			$capability  = $type_object->cap->edit_posts;  // only users who can edit posts of this post type.
			$users       = self::get_options_from_users( $capability );
		}

		$selected = ! empty( $_GET[ $select_name ] ) ? sanitize_text_field( $_GET[ $select_name ] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$options  = array(
			sprintf(
				'<option value="" %s>%s</option>',
				selected( false, $selected, false ),
				__( 'All authors', 'newspack' )
			),
		);

		foreach ( $users as $user ) {
			$options[] = sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $user->$value_field ),
				selected( $user->$value_field, $selected, false ),
				esc_attr( $user->display_name )
			);
		}

		printf( '<select id="author_filter" name="%s">', esc_attr( $select_name ) );
		echo implode( "\n", $options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</select>';

	}

	/**
	 * Get the list of users
	 *
	 * @param string $capability The capability users must have to be listed.
	 * @return array
	 */
	private static function get_options_from_users( $capability ) {
		$args  = array(
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'capability' => [ $capability ],
			'fields'     => [ 'ID', 'display_name' ],
		);
		$query = new WP_User_Query( $args );
		return $query->get_results();
	}

	/**
	 * Retrieve the list of authors from the Co Authors Plus plugin
	 *
	 * This list includes regular and guest users
	 *
	 * @return array
	 */
	private static function get_options_from_coauthors() {
		global $coauthors_plus;
		return $coauthors_plus->search_authors();
	}

}

if ( ! defined( 'NEWSPACK_DISABLE_AUTHORS_FILTER' ) || ! NEWSPACK_DISABLE_AUTHORS_FILTER ) {
	Author_Filter::init();
}
