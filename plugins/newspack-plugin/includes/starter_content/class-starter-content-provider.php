<?php
/**
 * Abstract class for a source of starter content.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract class for a starter content provider.
 * Extend this to add other starter content approaches (non-WP site import, file import, etc.).
 */
abstract class Starter_Content_Provider {

	/**
	 * Meta key to mark a post as starter content homepage.
	 *
	 * @var string
	 */
	protected static $starter_homepage_meta = '_newspack_starter_content_homepage';

	/**
	 * Meta key prefix to mark posts as starter content posts.
	 *
	 * @var string
	 */
	protected static $starter_post_meta_prefix = '_newspack_starter_content_post_';

	/**
	 * Initialize a starter content provider.
	 *
	 * @param array $args Optional or required arguments (depends on provider).
	 * @return bool|WP_Error True on success. WP_Error on failure.
	 */
	abstract public static function initialize( $args = [] );

	/**
	 * Create a starter post.
	 *
	 * @param int $post_index Number of post to create (1st, second, etc.).
	 * @return int Post ID of created post.
	 */
	abstract public static function create_post( $post_index );

	/**
	 * Create the starter homepage.
	 *
	 * @return int Post ID of created homepage.
	 */
	abstract public static function create_homepage();

	/**
	 * Determine whether starter content has been created.
	 *
	 * @return bool True if yes. False if no.
	 */
	public static function has_created_starter_content() {
		global $wpdb;
		$post_ids = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQueryWithPlaceholder
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key LIKE %s",
				self::$starter_post_meta_prefix . '%'
			),
			ARRAY_A
		);
		return ! empty( $post_ids );
	}

	/**
	 * Delete all starter content.
	 */
	public static function remove_starter_content() {
		global $wpdb;

		$post_ids = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQueryWithPlaceholder
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key LIKE %s",
				self::$starter_post_meta_prefix . '%'
			),
			ARRAY_A
		);

		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id['post_id'], true );
			}
		}
	}

	/**
	 * Mark a post as a starter post.
	 *
	 * @param int $post_id Post ID of post to mark as starter post.
	 * @param int $post_index Number of post to mark it as (1st, second, etc.).
	 */
	protected static function mark_starter_post( $post_id, $post_index ) {
		$meta_key = self::$starter_post_meta_prefix . (int) $post_index;
		update_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Mark a post as the starter homepage.
	 *
	 * @param int $post_id Post ID of post to mark as starter homepage.
	 */
	protected static function mark_starter_homepage( $post_id ) {
		$meta_key = self::$starter_homepage_meta;
		update_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Get post ID of Xth starter post.
	 *
	 * @param int $post_index Index of a starter post to get.
	 * @return bool|int False if not found. Post ID if found.
	 */
	protected static function get_starter_post( $post_index ) {
		global $wpdb;
		$meta_key         = self::$starter_post_meta_prefix . $post_index;
		$existing_post_id = $wpdb->get_row( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s;", $meta_key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $existing_post_id ) {
			return $existing_post_id['post_id'];
		}
		return false;
	}

	/**
	 * Get post ID of starter homepage.
	 *
	 * @return bool|int False if homepage hasn't been created. Post ID if it has.
	 */
	protected static function get_starter_homepage() {
		global $wpdb;
		$meta_key         = self::$starter_homepage_meta;
		$existing_post_id = $wpdb->get_row( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s;", $meta_key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $existing_post_id ) {
			return $existing_post_id;
		}
		return false;
	}
}
