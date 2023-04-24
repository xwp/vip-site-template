<?php
/**
 * Starter content management class.
 *
 * @package Newspack
 */

namespace Newspack;

require_once NEWSPACK_COMPOSER_ABSPATH . 'autoload.php';

use \WP_Error, \WP_Query, Newspack\Theme_Manager, Newspack\Starter_Content_Generated, Newspack\Starter_Content_WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Starter Content generation and cleanup.
 */
class Starter_Content {

	/**
	 * Supported starter content approaches.
	 *
	 * @var array of strings
	 */
	private static $supported_starter_content_approaches = [ 'generated', 'import' ];

	/**
	 * Option key to store active starter content approach.
	 *
	 * @var string
	 */
	private static $starter_content_approach_option = '_newspack_starter_content_approach';

	/**
	 * Get the active starter content approach.
	 *
	 * @return bool|string False if no starter content provider initialized. One of $supported_starter_content_approaches if initialized.
	 */
	protected static function get_starter_content_approach() {
		$saved = get_option( self::$starter_content_approach_option, false );
		if ( $saved && in_array( $saved, self::$supported_starter_content_approaches ) ) {
			return $saved;
		}

		return false;
	}

	/**
	 * Get the active starter content provider.
	 *
	 * @return bool|Starter_Content_Provider False if no starter content provider initialized. Instance of provider if initialized.
	 */
	protected static function get_starter_content_provider() {
		$approach = self::get_starter_content_approach();
		if ( ! $approach || ! in_array( $approach, self::$supported_starter_content_approaches ) ) {
			return false;
		}

		$provider = 'generated' === $approach ? new Starter_Content_Generated() : new Starter_Content_WordPress();
		return $provider;
	}

	/**
	 * Initialize starter content infra.
	 *
	 * @param string $approach The approach to use when generating starter content ('generated' or 'import').
	 * @param string $existing_site_url URL of site to migrate (optional).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function initialize( $approach, $existing_site_url = '' ) {
		if ( ! in_array( $approach, self::$supported_starter_content_approaches ) ) {
			return new WP_Error( 'newspack_starter_content', __( 'Invalid starter content approach', 'newspack' ) );
		}

		$args     = [
			'site' => $existing_site_url,
		];
		$provider = 'generated' === $approach ? new Starter_Content_Generated() : new Starter_Content_WordPress();
		$result   = $provider::initialize( $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_option( self::$starter_content_approach_option, $approach );
		return true;
	}

	/**
	 * Create the Xth starter post.
	 *
	 * @param int $post_index The number of post to create (1st, second, etc.).
	 * @return int|WP_Error Post ID on success or if post already exists. WP_Error on failure.
	 */
	public static function create_post( $post_index ) {
		$provider = self::get_starter_content_provider();
		if ( ! $provider ) {
			return new WP_Error( 'newspack_starter_content', __( 'Starter content is not initialized correctly', 'newspack' ) );
		}

		return $provider::create_post( $post_index );
	}

	/**
	 * Create the starter homepage.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_homepage() {
		$provider = self::get_starter_content_provider();
		if ( ! $provider ) {
			return new WP_Error( 'newspack_starter_content', __( 'Starter content is not initialized correctly', 'newspack' ) );
		}

		return $provider::create_homepage();
	}

	/**
	 * Determine whether starter content has been created yet.
	 *
	 * @return bool True if yes, false if not.
	 */
	public static function has_created_starter_content() {
		$provider = self::get_starter_content_provider();
		if ( ! $provider ) {
			return false;
		}

		return $provider::has_created_starter_content();
	}

	/**
	 * Remove starter content.
	 */
	public static function remove_starter_content() {
		$provider = self::get_starter_content_provider();
		if ( ! $provider ) {
			return false;
		}

		return $provider::remove_starter_content();
	}

	/**
	 * Set up theme.
	 */
	public static function initialize_theme() {
		Theme_Manager::install_activate_theme();
		if ( false === get_theme_mod( 'custom_logo' ) ) {
			$logo_id = self::upload_logo();
			if ( $logo_id ) {
				set_theme_mod( 'custom_logo', $logo_id );
				set_theme_mod( 'logo_size', 0 );
			}
		}
		set_theme_mod( 'header_solid_background', false );
		set_theme_mod( 'header_simplified', false );
		return true;
	}

	/**
	 * Get theme style.
	 *
	 * @return string Style id.
	 */
	public static function get_theme() {
		$theme_object = wp_get_theme();
		return $theme_object->get_stylesheet();
	}

	/**
	 * Upload Newspack logo as placeholder.
	 */
	public static function upload_logo() {

		$attachment_title = esc_attr__( 'Newspack Placeholder Logo', 'newspack' );

		$args = [
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'name'           => $attachment_title,
		];

		$logo_query = new WP_Query( $args );
		if ( $logo_query && isset( $logo_query->posts, $logo_query->posts[0] ) ) {
			return null;
		}

		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_crop_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$file = wp_upload_bits(
			'newspack-logo.png',
			null,
			file_get_contents( NEWSPACK_ABSPATH . 'includes/raw_assets/images/newspack-logo.png' )
		);

		if ( ! $file || empty( $file['file'] ) ) {
			return null;
		}

		$wp_filetype = wp_check_filetype( $file['file'], null );

		$attachment = [
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id   = wp_insert_attachment( $attachment, $file['file'] );
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file['file'] );

		wp_update_attachment_metadata( $attachment_id, $attachment_data );
		return $attachment_id;
	}

	/**
	 * Is this an E2E testing environment?
	 *
	 * @return bool E2E testing environment?
	 */
	public static function is_e2e() {
		return defined( 'WP_NEWSPACK_IS_E2E' ) && WP_NEWSPACK_IS_E2E;
	}
}
