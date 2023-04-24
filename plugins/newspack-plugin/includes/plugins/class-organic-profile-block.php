<?php
/**
 * Organic Profile Block integration class.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class Organic_Profile_Block {
	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'wp_enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'wp_enqueue_scripts' ] );
	}

	/**
	 * If the post has the Organic Profile Block, but the plugin is not active,
	 * enqueue the styles to render the block.
	 */
	public static function wp_enqueue_scripts() {
		if (
			! is_plugin_active( 'organic-profile-block/organic-profile-block.php' )
			&& has_block( 'organic/profile-block' )
		) {
			\wp_enqueue_style(
				'organic-profile-block',
				Newspack::plugin_url() . '/dist/other-scripts/organic-profile-block.css',
				[],
				NEWSPACK_PLUGIN_VERSION
			);
		}
	}
}
Organic_Profile_Block::init();
