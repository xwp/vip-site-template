<?php
/**
 * Foundation.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

/**
 * Class for adding basic theme support,
 * most of which is mandatory to be implemented by all themes.
 */
class Foundation implements Component {

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 *
	 * @return void
	 */
	public function init(): void {
		// Add support for essential theme features.
		add_action( 'after_setup_theme', [ $this, 'action_essential_theme_support' ] );

		// Disable post formats.
		// Priority 20 to run after other theme supports that might set post formats.
		add_action( 'after_setup_theme', [ $this, 'disable_post_formats' ], 20 );

		// Disable legacy emoji support.
		add_action( 'init', [ $this, 'disable_legacy_emoji_support' ] );

		// Register block pattern categories.
		add_action( 'init', [ $this, 'register_pattern_categories' ], 5 );
	}

	/**
	 * Adds theme support for essential features.
	 *
	 * @return void
	 */
	public function action_essential_theme_support(): void {
		// Add support for post thumbnails.
		add_theme_support( 'post-thumbnails' );

		// Add default RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		// Ensure WordPress manages the document title.
		add_theme_support( 'title-tag' );

		// Ensure WordPress theme features render in HTML5 markup.
		add_theme_support(
			'html5',
			[
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
			]
		);

		// Add support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Add support for editor styles.
		add_theme_support( 'editor-styles' );

		// Add support for default block styles.
		add_theme_support( 'wp-block-styles' );

		// Add support for wide-aligned images.
		add_theme_support( 'align-wide' );
	}

	/**
	 * Disable post formats.
	 *
	 * @return void
	 */
	public function disable_post_formats(): void {
		remove_theme_support( 'post-formats' );
	}

	/**
	 * Removes legacy emoji support scripts & styles.
	 *
	 * @return void
	 */
	public function disable_legacy_emoji_support(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Register custom block pattern categories.
	 *
	 * @return void
	 */
	public function register_pattern_categories(): void {
		register_block_pattern_category(
			'example-patterns',
			[
				'label'       => __( 'Example', 'example-theme' ),
				'description' => __( 'Custom patterns.', 'example-theme' ),
			]
		);
	}
}
