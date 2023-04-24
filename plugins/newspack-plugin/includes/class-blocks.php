<?php
/**
 * Newspack Blocks.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Blocks Class.
 */
final class Blocks {
	/**
	 * Initialize Hooks.
	 */
	public static function init() {
		require_once NEWSPACK_ABSPATH . 'assets/blocks/reader-registration/index.php';
		\add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
	}

	/**
	 * Enqueue blocks scripts and styles for editor.
	 */
	public static function enqueue_block_editor_assets() {
		Newspack::load_common_assets();

		\wp_enqueue_script(
			'newspack-blocks',
			Newspack::plugin_url() . '/dist/blocks.js',
			[],
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		\wp_localize_script(
			'newspack-blocks',
			'newspack_blocks',
			[
				'has_newsletters'         => method_exists( 'Newspack_Newsletters_Subscription', 'add_contact' ),
				'has_reader_activation'   => Reader_Activation::is_enabled( false ),
				'newsletters_url'         => Wizards::get_wizard( 'engagement' )->newsletters_settings_url(),
				'has_google_oauth'        => Google_OAuth::is_oauth_configured(),
				'google_logo_svg'         => file_get_contents( dirname( NEWSPACK_PLUGIN_FILE ) . '/assets/blocks/reader-registration/icons/google.svg' ),
				'reader_activation_terms' => Reader_Activation::get_setting( 'terms_text' ),
				'reader_activation_url'   => Reader_Activation::get_setting( 'terms_url' ),
			]
		);
		\wp_enqueue_style(
			'newspack-blocks',
			Newspack::plugin_url() . '/dist/blocks.css',
			[],
			NEWSPACK_PLUGIN_VERSION
		);
	}
}
Blocks::init();
