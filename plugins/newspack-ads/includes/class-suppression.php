<?php
/**
 * Newspack Ads Ad Suppression
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Core;
use Newspack_Ads\Settings;
use Newspack_Ads\Placements;
use Newspack_Ads\Providers\GAM_Model;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Ad Suppression Class.
 */
final class Suppression {

	const OPTION_NAME = '_newspack_global_ad_suppression';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
		\add_action( 'init', [ __CLASS__, 'register_post_meta' ] );
		\add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
	}

	/**
	 * Register API endpoints.
	 */
	public static function register_api_endpoints() {
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/suppression',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'api_get_config' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/suppression',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'api_update_config' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'config' => [
						'required' => true,
						'type'     => [
							'type'       => 'object',
							'properties' => [
								'tags'                   => [
									'type'  => 'array',
									'items' => [
										'type' => 'string',
									],
								],
								'tag_archive_pages'      => [
									'type' => 'boolean',
								],
								'specific_tag_archive_pages' => [
									'type'  => 'array',
									'items' => [
										'type' => 'integer',
									],
								],
								'categories'             => [
									'type'  => 'array',
									'items' => [
										'type' => 'string',
									],
								],
								'category_archive_pages' => [
									'type' => 'boolean',
								],
								'specific_category_archive_pages' => [
									'type'  => 'array',
									'items' => [
										'type' => 'integer',
									],
								],
								'author_archive_pages'   => [
									'type' => 'boolean',
								],
								'post_types'             => [
									'type'  => 'array',
									'items' => [
										'type' => 'string',
									],
								],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Register suppression meta field for post.
	 */
	public static function register_post_meta() {
		\register_post_meta(
			'',
			'newspack_ads_suppress_ads',
			[
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'boolean',
			]
		);
		\register_post_meta(
			'',
			'newspack_ads_suppress_ads_placements',
			[
				'show_in_rest' => [
					'schema' => [
						'type'    => 'array',
						'context' => [ 'edit' ],
						'items'   => [
							'type' => 'string',
						],
					],
				],
				'single'       => true,
				'type'         => 'array',
				'default'      => [],
			]
		);
	}

	/**
	 * Get ad suppresion config through API request.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_get_config() {
		return \rest_ensure_response( self::get_config() );
	}

	/**
	 * Update ad suppresion config through API request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_update_config( $request ) {
		self::update_config( $request['config'] );
		return \rest_ensure_response( self::get_config() );
	}

	/**
	 * Get ad suppresion config.
	 */
	private static function get_config() {
		$options = \get_option(
			self::OPTION_NAME,
			[
				'tags'                            => [],
				'tag_archive_pages'               => false,
				'specific_tag_archive_pages'      => [],
				'categories'                      => [],
				'category_archive_pages'          => false,
				'specific_category_archive_pages' => [],
				'author_archive_pages'            => false,
				'post_types'                      => [],
			]
		);
		/** Migrate legacy archive options */
		if ( ! empty( $options['specific_tag_archive_pages'] ) ) {
			$options['tags'] = $options['specific_tag_archive_pages'];
			unset( $options['specific_tag_archive_pages'] );
		}
		if ( ! empty( $options['specific_category_archive_pages'] ) ) {
			$options['categories'] = $options['specific_category_archive_pages'];
			unset( $options['specific_category_archive_pages'] );
		}
		return $options;
	}

	/**
	 * Update config.
	 *
	 * @param array $config Updated config.
	 */
	private static function update_config( $config ) {
		\update_option( self::OPTION_NAME, $config );
	}

	/**
	 * Enqueue block editor ad suppression assets for any post type considered
	 * "viewable".
	 */
	public static function enqueue_block_editor_assets() {
		$post_type = \get_current_screen()->post_type;
		if ( ! empty( $post_type ) && \is_post_type_viewable( $post_type ) ) {
			\wp_enqueue_script( 'newspack-ads-suppress-ads', Core::plugin_url( 'dist/suppress-ads.js' ), [], NEWSPACK_ADS_VERSION, true );
			$placements = Placements::get_placements();
			\wp_localize_script(
				'newspack-ads-suppress-ads',
				'newspackAdsSuppressAds',
				[
					'placements' => array_filter(
						$placements,
						function( $placement ) {
							return true === $placement['show_ui'];
						}
					),
				]
			);
		}
	}

	/**
	 * Internal method for determining if the page should display ads.
	 *
	 * @param int $post_id Optional post ID to check for.
	 *
	 * @return bool
	 */
	private static function internal_should_show_ads( $post_id = null ) {
		$config = self::get_config();
		if ( \is_singular() || ! empty( $post_id ) ) {
			if ( ! $post_id ) {
				$post_id = \get_the_ID();
			}
			if ( \get_post_meta( $post_id, 'newspack_ads_suppress_ads', true ) ) {
				return false;
			}
			if ( ! empty( $config['post_types'] ) && in_array( \get_post_type( $post_id ), $config['post_types'] ) ) {
				return false;
			}
			if ( ! empty( $config['tags'] ) && \has_tag( $config['tags'], $post_id ) ) {
				return false;
			}
			if ( ! empty( $config['categories'] ) && \has_category( $config['categories'], $post_id ) ) {
				return false;
			}
		}
		if ( \is_archive() ) {
			if ( ! empty( $config['post_types'] ) && \is_post_type_archive( $config['post_types'] ) ) {
				return false;
			}
			if ( \is_tag() ) {
				if ( isset( $config['tag_archive_pages'] ) && true === $config['tag_archive_pages'] ) {
					return false;
				}
				if ( ! empty( $config['tags'] ) && \in_array( \get_queried_object_id(), $config['tags'] ) ) {
					return false;
				}
			}
			if ( \is_category() ) {
				if ( isset( $config['category_archive_pages'] ) && true === $config['category_archive_pages'] ) {
					return false;
				}
				if ( ! empty( $config['categories'] ) && \in_array( \get_queried_object_id(), $config['categories'] ) ) {
					return false;
				}
			}
			if ( \is_author() && isset( $config['author_archive_pages'] ) && true === $config['author_archive_pages'] ) {
				return false;
			}
		}
		if ( \is_404() ) {
			return false;
		}
		return true;
	}

	/**
	 * Get whether ads should be displayed on a screen.
	 *
	 * @param int $post_id Post ID to check (optional, default: current post).
	 *
	 * @return bool
	 */
	public static function should_show_ads( $post_id = null ) {
		if ( is_singular() && empty( $post_id ) ) {
			$post_id = get_the_ID();
		}
		return apply_filters( 'newspack_ads_should_show_ads', self::internal_should_show_ads( $post_id ), $post_id );
	}
}
Suppression::init();
