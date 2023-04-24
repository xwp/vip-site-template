<?php
/**
 * Front-end render functions for the Prompt block.
 *
 * @package Newspack_Popups
 */

namespace Newspack_Popups\Prompt_Block;

/**
 * Dynamic block registration.
 */
function register_block() {
	// Prompt block attributes.
	$block_json = json_decode(
		file_get_contents( __DIR__ . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		true
	);

	register_block_type(
		$block_json['name'],
		[
			'attributes'      => $block_json['attributes'],
			'render_callback' => __NAMESPACE__ . '\render_block',
		]
	);
}

/**
 * Block render callback.
 *
 * @param array $attributes Block attributes.
 */
function render_block( $attributes ) {
	$content     = '';
	$prompt_id   = $attributes['promptId'];
	$class_names = isset( $attributes['className'] ) ? ' class="' . $attributes['className'] . '"' : '';

	if ( empty( $prompt_id ) ) {
		return $content;
	}

	// Get the prompt by id.
	$prompt = \Newspack_Popups_Model::retrieve_popup_by_id( intval( $prompt_id ) );

	// Only show inline or manual-only prompts (should only be selectable in editor, but verify just in case).
	if ( ! empty( $prompt ) && ( \Newspack_Popups_Model::is_inline( $prompt ) || \Newspack_Popups_Model::is_manual_only( $prompt ) ) ) {
		if ( defined( 'WP_NEWSPACK_DEBUG' ) && WP_NEWSPACK_DEBUG ) {
			$content .= '<!-- Newspack Campaigns: Start Prompt ' . $prompt_id . '-->';
		}

		$content .= '<!-- wp:shortcode -->[newspack-popup id="' . $prompt_id . '"' . $class_names . ']<!-- /wp:shortcode -->';

		if ( defined( 'WP_NEWSPACK_DEBUG' ) && WP_NEWSPACK_DEBUG ) {
			$content .= '<!-- Newspack Campaigns: End Prompt ' . $prompt_id . '-->';
		}
	}

	return $content;
}

register_block();
