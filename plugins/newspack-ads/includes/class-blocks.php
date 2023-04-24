<?php
/**
 * Newspack Ads Block Management
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Core;
use Newspack_Ads\Placements;
use Newspack_Ads\Providers;

/**
 * Newspack Ads Blocks Management
 */
final class Blocks {

	/**
	 * Initialize blocks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_block' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
	}

	/**
	 * Register block
	 *
	 * @return void
	 */
	public static function register_block() {
		register_block_type(
			'newspack-ads/ad-unit',
			[
				'attributes'      => [
					'provider'     => [
						'type' => 'string',
					],
					'ad_unit'      => [
						'type' => 'string',
					],
					'bidders_ids'  => [
						'type'    => 'object',
						'default' => [],
					],
					'fixed_height' => [
						'type'    => 'boolean',
						'default' => false,
					],
					// Legacy attribute.
					'activeAd'     => [
						'type' => 'string',
					],
				],
				'render_callback' => [ __CLASS__, 'render_block' ],
				'supports'        => [],
			]
		);
	}

	/**
	 * Get block placement data.
	 *
	 * @param array[] $attrs Block attributes.
	 *
	 * @return array[] Placement data.
	 */
	private static function get_block_placement_data( $attrs ) {
		$data = wp_parse_args(
			$attrs,
			[
				'id'       => uniqid(),
				'enabled'  => true,
				'provider' => Providers::get_default_provider(),
				'ad_unit'  => isset( $attrs['activeAd'] ) ? $attrs['activeAd'] : '',
			]
		);
		return $data;
	}

	/**
	 * Register a placement given block attributes.
	 *
	 * @param array[] $attrs Block attributes.
	 *
	 * @return array[]|WP_Error Placement config or error if placement was not registered.
	 */
	private static function register_block_placement( $attrs ) {
		$data = self::get_block_placement_data( $attrs );
		if ( ! $data['ad_unit'] ) {
			return;
		}
		$placement_id     = sprintf( 'block_%s', $data['id'] );
		$hook_name        = sprintf( 'newspack_ads_%s_render', $placement_id );
		$placement_config = [
			'show_ui'   => false, // This is a dynamic placement and shouldn't be editable through the Ads wizard.
			'hook_name' => $hook_name,
			'data'      => $data,
		];
		$registered       = Placements::register_placement( $placement_id, $placement_config );
		if ( \is_wp_error( $registered ) ) {
			return $registered;
		}
		if ( ! $registered ) {
			return new \WP_Error(
				'newspack_ads_block_placement_not_registered',
				sprintf(
					/* translators: %s: Placement ID */
					__( 'Ad placement %s could not be registered.', 'newspack' ),
					$placement_id
				)
			);
		}
		return $placement_config;
	}

	/**
	 * Render block.
	 *
	 * @param array[] $attrs Block attributes.
	 *
	 * @return string Block HTML.
	 */
	public static function render_block( $attrs ) {
		$placement_config = self::register_block_placement( $attrs );
		if ( \is_wp_error( $placement_config ) ) {
			return '';
		}
		$classes = self::block_classes( 'wp-block-newspack-ads-blocks-ad-unit', $attrs );
		$align   = 'inherit';
		if ( strpos( $classes, 'aligncenter' ) == true ) {
			$align = 'center';
		}
		ob_start();
		do_action( $placement_config['hook_name'] );
		$content = ob_get_clean();
		if ( empty( $content ) ) {
			return '';
		}
		return sprintf( '<div class="%1$s" style="text-align:%2$s">%3$s</div>', $classes, $align, $content );
	}

	/**
	 * Enqueue block scripts and styles for editor.
	 */
	public static function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'newspack-ads-editor',
			Core::plugin_url( 'dist/editor.js' ),
			[],
			NEWSPACK_ADS_VERSION,
			true
		);
		wp_enqueue_style(
			'newspack-ads-editor',
			Core::plugin_url( 'dist/editor.css' ),
			[],
			NEWSPACK_ADS_VERSION
		);
	}

	/**
	 * Utility to assemble the class for a server-side rendered bloc
	 *
	 * @param string $type The block type.
	 * @param array  $attributes Block attributes.
	 *
	 * @return string Class list separated by spaces.
	 */
	public static function block_classes( $type, $attributes = array() ) {
		$align   = isset( $attributes['align'] ) ? $attributes['align'] : 'none';
		$classes = array(
			"wp-block-newspack-blocks-{$type}",
			"align{$align}",
		);
		if ( isset( $attributes['className'] ) ) {
			array_push( $classes, $attributes['className'] );
		}
		if ( isset( $attributes['backgroundColor'] ) ) {
			array_push( $classes, 'has-background' );
			array_push( $classes, sprintf( 'has-%s-background-color', $attributes['backgroundColor'] ) );
		}
		return implode( ' ', $classes );
	}
}
Blocks::init();
