<?php
/**
 * Newspack admin notices.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

define( 'NEWSPACK_HANDOFF', 'newspack_handoff' );
define( 'NEWSPACK_HANDOFF_RETURN_URL', 'newspack_handoff_return_url' );
define( 'NEWSPACK_HANDOFF_SHOW_ON_BLOCK_EDITOR', 'newspack_handoff_show_on_block_editor' );

/**
 * Manages the API as a whole.
 */
class Handoff_Banner {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'current_screen', [ $this, 'clear_handoff_url' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ], 1 );
		add_action( 'enqueue_block_editor_assets', [ $this, 'insert_block_editor_handoff_banner' ] );
		add_action( 'admin_notices', [ $this, 'insert_handoff_banner' ], -9998 );
	}

	/**
	 * Render element into which Handoff Banner will be rendered.
	 *
	 * @return void.
	 */
	public function insert_handoff_banner() {
		if ( ! self::needs_handoff_return_ui() ) {
			return;
		}

		echo sprintf( "<div id='newspack-handoff-banner' data-primary_button_url='%s'></div>", esc_url( get_option( NEWSPACK_HANDOFF_RETURN_URL ) ) );
	}

	/**
	 * Render a handoff banner on the block editor if needed.
	 *
	 * @return void
	 */
	public function insert_block_editor_handoff_banner() {
		if ( ! self::needs_block_editor_handoff_return_ui() ) {
			return;
		}

		$handle = 'newspack-handoff-banner-block-editor';
		wp_register_script(
			$handle,
			Newspack::plugin_url() . '/assets/wizards/handoff-banner/block-editor.js',
			[ 'wp-element', 'wp-editor', 'wp-components' ],
			NEWSPACK_PLUGIN_VERSION,
			true
		);

		$script_info = [
			'text'       => __( 'Return to Newspack after completing configuration', 'newspack' ),
			'buttonText' => __( 'Back to Newspack', 'newspack' ),
			'returnURL'  => esc_url( get_option( NEWSPACK_HANDOFF_RETURN_URL, '' ) ),
		];
		wp_localize_script( $handle, 'newspack_handoff', $script_info );
		wp_enqueue_script( $handle );
	}

	/**
	 * Enqueue script and styles for Handoff Banner.
	 */
	public function enqueue_styles() {
		if ( ! self::needs_handoff_return_ui() ) {
			return;
		}
		$handle = 'newspack-handoff-banner';
		wp_register_style(
			$handle,
			Newspack::plugin_url() . '/dist/handoff-banner.css',
			[],
			NEWSPACK_PLUGIN_VERSION
		);
		wp_enqueue_style( $handle );

		Newspack::load_common_assets();

		wp_register_script(
			$handle,
			Newspack::plugin_url() . '/dist/handoff-banner.js',
			[ 'wp-element', 'wp-editor', 'wp-components' ],
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		wp_enqueue_script( $handle );

	}

	/**
	 * Register the slug of plugin that is about to be visited.
	 *
	 * @param  array   $plugin Slug of plugin to be visited.
	 * @param  boolean $show_on_block_editor Whether to show on block editor.
	 * @return void
	 */
	public static function register_handoff_for_plugin( $plugin, $show_on_block_editor = false ) {
		update_option( NEWSPACK_HANDOFF, $plugin );
		update_option( NEWSPACK_HANDOFF_SHOW_ON_BLOCK_EDITOR, (bool) $show_on_block_editor );
	}

	/**
	 * Should handoff return UI be shown?
	 *
	 * @return bool
	 */
	public static function needs_handoff_return_ui() {
		if ( get_option( NEWSPACK_SETUP_COMPLETE, true ) ) {
			return false;
		}

		return get_option( NEWSPACK_HANDOFF ) ? true : false;
	}

	/**
	 * Should handoff return UI be shown on the block editor?
	 *
	 * @return bool
	 */
	public static function needs_block_editor_handoff_return_ui() {
		return self::needs_handoff_return_ui() && (bool) get_option( NEWSPACK_HANDOFF_SHOW_ON_BLOCK_EDITOR, false );
	}

	/**
	 * If the current admin page is part of the Newspack dashboard, clear the handoff URL. This ensures the handoff banner won't be shown on Newspack admin pages.
	 *
	 * @param WP_Screen $current_screen The current screen object.
	 * @return void
	 */
	public function clear_handoff_url( $current_screen ) {
		if ( stristr( $current_screen->id, 'newspack' ) ) {
			update_option( NEWSPACK_HANDOFF, null );
			update_option( NEWSPACK_HANDOFF_SHOW_ON_BLOCK_EDITOR, false );
		}
	}
}
new Handoff_Banner();
