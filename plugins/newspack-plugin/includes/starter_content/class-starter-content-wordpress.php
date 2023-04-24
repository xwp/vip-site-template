<?php
/**
 * Generate starter content from an existing WP site using its REST API.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Generate starter content from an existing WP site using its REST API.
 */
class Starter_Content_WordPress extends Starter_Content_Provider {

	/**
	 * Option key to store raw scraped data from site for generating starter content.
	 *
	 * @var string
	 */
	private static $existing_content_raw_data_option = '_newspack_existing_content_raw_data';

	/**
	 * Initialize starter content data.
	 *
	 * @param array $args Array of info to initialize provider. Needs to contain 'site' with site URL with this one.
	 * @return bool|WP_Error True on success. WP_Error on failure.
	 */
	public static function initialize( $args = [] ) {
		if ( empty( $args['site'] ) || ! wp_http_validate_url( $args['site'] ) ) {
			return new WP_Error(
				'newspack_starter_content',
				sprintf(
					/* translators: %s - Site URL */
					__( 'Invalid site URL: "%s".', 'newspack' ),
					sanitize_text_field( $request_params['site'] )
				)
			);
		}

		$site_url   = trailingslashit( esc_url_raw( $args['site'] ) );
		$wp_api_url = $site_url . 'wp-json/wp/v2/posts?per_page=50';

		$response = wp_remote_get( $wp_api_url, [ 'timeout' => 60 ] ); //phpcs:ignore
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			// Check if the site is a WP site that doesn't have REST API enabled.
			if ( 404 === $response_code && false !== stripos( $response_body, '/wp-content' ) ) {
				return new WP_Error(
					'newspack_starter_content',
					sprintf(
						/* translators: %s - Site URL */
						__( '"%s" is a WordPress site but does not have the REST API enabled.', 'newspack' ),
						sanitize_text_field( $site_url )
					)
				);
			}

			return new WP_Error(
				'newspack_starter_content',
				sprintf(
					/* translators: %s - Site URL */
					__( '"%s" does not appear to be a WordPress site.', 'newspack' ),
					sanitize_text_field( $site_url )
				)
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_json = json_decode( $response_body, true );
		if ( ! $response_json ) {
			return new WP_Error(
				'newspack_starter_content',
				sprintf(
					/* translators: %s - Site URL */
					__( 'Unable to parse response from "%s".', 'newspack' ),
					sanitize_text_field( $site_url )
				)
			);
		}

		$relevant_info = array_map( 
			function( $post_data ) use ( $site_url ) {
				return [
					'title'          => $post_data['title']['rendered'],
					'date'           => $post_data['date'],
					'content'        => $post_data['content']['rendered'],
					'featured_image' => $post_data['featured_media'],
					'site_url'       => $site_url,
				];
			},
			$response_json 
		);

		update_option( self::$existing_content_raw_data_option, $relevant_info );
		return true;
	}

	/**
	 * Create the Xth starter post.
	 *
	 * @param int $post_index Index of post to create.
	 * @return int ID of existing post if already created or ID of created post.
	 */
	public static function create_post( $post_index ) {
		$existing_post_id = self::get_starter_post( $post_index );
		if ( $existing_post_id ) {
			return $existing_post_id;
		}

		if ( ! function_exists( 'wp_insert_post' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$existing_content_raw_data = get_option( self::$existing_content_raw_data_option, [] );
		if ( ! $existing_content_raw_data ) {
			return false;
		}

		if ( empty( $existing_content_raw_data[ $post_index ] ) ) {
			return false;
		}

		$raw_post_data = $existing_content_raw_data[ $post_index ];
		$post_data     = [
			'post_title'   => sanitize_text_field( $raw_post_data['title'] ),
			'post_status'  => 'publish',
			'post_content' => wp_kses_post( $raw_post_data['content'] ),
			'post_date'    => sanitize_text_field( $raw_post_data['date'] ),
		];
		$post_id       = wp_insert_post( $post_data );
		if ( ! $post_id ) {
			return false;
		}

		if ( ! empty( $raw_post_data['featured_image'] ) && ! empty( $raw_post_data['site_url'] ) ) {
			$featured_image_id = (int) $raw_post_data['featured_image'];
			if ( $featured_image_id ) {
				$media_endpoint = trailingslashit( esc_url_raw( $raw_post_data['site_url'] ) ) . 'wp-json/wp/v2/media/' . $featured_image_id;
				$media_response = wp_remote_get( $media_endpoint, [ 'timeout' => 60 ] ); //phpcs:ignore
				if ( ! is_wp_error( $media_response ) && 200 === wp_remote_retrieve_response_code( $media_response ) ) {
					$media_response_body = json_decode( wp_remote_retrieve_body( $media_response ), true );
					$media_src           = $media_response_body['source_url'];

					if ( ! function_exists( 'media_sideload_image' ) ) {
						require_once ABSPATH . 'wp-admin/includes/media.php';
						require_once ABSPATH . 'wp-admin/includes/file.php';
						require_once ABSPATH . 'wp-admin/includes/image.php';
					}

					$image_id = media_sideload_image( $media_src, $post_id, null, 'id' );
					if ( is_numeric( $image_id ) ) {
						set_post_thumbnail( $post_id, $image_id );
					}
				}
			}
		}

		self::mark_starter_post( $post_id, $post_index );

		return $post_id;
	}

	/**
	 * Create the homepage.
	 *
	 * @return post_id ID of existing homepage or ID of created homepage.
	 */
	public static function create_homepage() {
		$existing_homepage_id = self::get_starter_homepage();
		if ( $existing_homepage_id ) {
			return $existing_homepage_id;
		}

		if ( ! function_exists( 'wp_insert_post' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}
		ob_start();
		?>
		<!-- wp:columns {"className":"is-style-borders"} --><div class="wp-block-columns is-style-borders">

			<!-- wp:column {"width":66.66} --><div class="wp-block-column" style="flex-basis:66.66%">

				<!-- wp:newspack-blocks/homepage-articles {"postsToShow":1,"sectionHeader":"<?php echo esc_html__( 'Featured', 'newspack' ); ?>"} /-->

			</div><!-- /wp:column -->
			<!-- wp:column {"width":33.33} --><div class="wp-block-column" style="flex-basis:33.33%">

				<!-- wp:newspack-blocks/homepage-articles {"showExcerpt":false,"imageShape":"square","showAvatar":false,"postsToShow":6,"mediaPosition":"left","typeScale":2,"imageScale":1,"sectionHeader":"<?php echo esc_html__( 'Latest', 'newspack' ); ?>"} /-->

			</div><!-- /wp:column -->

		</div><!-- /wp:columns -->

		<!-- wp:separator {"className":"is-style-wide"} --><hr class="wp-block-separator is-style-wide"/><!-- /wp:separator -->

		<!-- wp:columns {"className":"is-style-borders"} --><div class="wp-block-columns is-style-borders">

			<!-- wp:column --><div class="wp-block-column">

				<!-- wp:newspack-blocks/homepage-articles {"className":"is-style-borders","showExcerpt":false,"showAuthor":false,"postsToShow":1,"typeScale":3,"imageScale":1} /-->

				<!-- wp:newspack-blocks/homepage-articles {"className":"is-style-borders","showExcerpt":false,"showAuthor":false,"showAvatar":false,"postsToShow":3,"mediaPosition":"left","typeScale":2,"imageScale":1} /-->

			</div><!-- /wp:column -->

			<!-- wp:column --><div class="wp-block-column">

				<!-- wp:newspack-blocks/homepage-articles {"className":"is-style-borders","showExcerpt":false,"showAuthor":false,"postsToShow":1,"typeScale":3,"imageScale":1} /-->

				<!-- wp:newspack-blocks/homepage-articles {"className":"is-style-borders","showExcerpt":false,"showAuthor":false,"showAvatar":false,"postsToShow":3,"mediaPosition":"left","typeScale":2,"imageScale":1} /-->

			</div><!-- /wp:column -->

			<!-- wp:column --><div class="wp-block-column">

				<!-- wp:newspack-blocks/homepage-articles {"className":"is-style-borders","showExcerpt":false,"showImage":false,"showAuthor":false,"showAvatar":false,"postsToShow":1,"mediaPosition":"left","imageScale":1} /-->

				<!-- wp:newspack-blocks/homepage-articles {"className":"is-style-borders","showExcerpt":false,"showImage":false,"showAuthor":false,"showAvatar":false,"postsToShow":4,"mediaPosition":"left","typeScale":3,"imageScale":1} /-->

			</div><!-- /wp:column -->

		</div><!-- /wp:columns -->
		<?php
		$content = ob_get_clean();

		$post_data = [
			'post_title'   => 'Homepage',
			'post_type'    => 'page',
			'post_content' => $content,
		];
		$page_id   = wp_insert_post( $post_data );
		wp_publish_post( $page_id );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
		set_theme_mod( 'hide_front_page_title', true );
		self::mark_starter_homepage( $page_id );

		return $page_id;
	}
}
