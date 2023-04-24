<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing
/**
 * Server-side rendering of the `newspack-blocks/donate` block.
 *
 * @package WordPress
 */

require_once NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/frontend/class-newspack-blocks-donate-renderer.php';

/**
 * Registers the `newspack-blocks/donate` block on server.
 */
function newspack_blocks_register_donate() {
	$block_json = json_decode(
		file_get_contents( __DIR__ . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		true
	);

	register_block_type(
		'newspack-blocks/' . $block_json['name'],
		[
			'attributes'      => $block_json['attributes'],
			'render_callback' => 'Newspack_Blocks_Donate_Renderer::render',
			'supports'        => $block_json['supports'],
		]
	);
}
add_action( 'init', 'newspack_blocks_register_donate' );
