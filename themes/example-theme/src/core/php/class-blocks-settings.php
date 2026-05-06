<?php
/**
 * Blocks Settings Override.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

use WP_Theme_JSON_Data;
use XWP\VIP_Site_Template\Theme\Utils;

/**
 * Blocks Settings Override.
 *
 * Dynamically injects disallowed block settings into theme.json at runtime,
 * merging with existing settings while ensuring disallowed rules take precedence.
 *
 * Also deregisters block style variations from paragraph and heading blocks.
 */
class Blocks_Settings implements Component {

	/**
	 * List of blocks to apply disallowed settings to.
	 *
	 * @var array<string>
	 */
	const BLOCKS_TO_RESTRICT_SETTINGS = [
		// Text/Media blocks.
		'core/paragraph',
		'core/heading',
		'core/quote',
		'core/pullquote',
		'core/list',
		'core/list-item',
		'core/table',
		'core/image',
		'core/embed',
		// Design Blocks.
		'core/buttons',
		'core/button',
		'core/separator',
		'core/spacer',
		'core/columns',
		'core/group',
		'core/grid',
		'core/stack',
		'core/row',
	];

	/**
	 * Disallowed settings to apply to restricted blocks.
	 *
	 * @var array<string,mixed>
	 */
	const DISALLOWED_SETTINGS = [
		'background' => [
			'backgroundImage' => false,
			'backgroundSize'  => false,
		],
		'border'     => [
			'color'  => false,
			'radius' => false,
			'style'  => false,
			'width'  => false,
		],
		'color'      => [
			'background'       => false,
			'custom'           => false,
			'customDuotone'    => false,
			'customGradient'   => false,
			'defaultDuotone'   => false,
			'defaultGradients' => false,
			'defaultPalette'   => false,
			'duotone'          => [],
			'gradients'        => [],
			'link'             => false,
			'palette'          => [],
			'text'             => false,
			'heading'          => false,
			'button'           => false,
			'caption'          => false,
		],
		'dimensions' => [
			'aspectRatio'         => false,
			'aspectRatios'        => [],
			'defaultAspectRatios' => false,
			'minHeight'           => false,
		],
		'layout'     => [
			'wideSize'                      => null,
			'allowCustomContentAndWideSize' => false,
		],
		'lightbox'   => [
			'enabled'      => false,
			'allowEditing' => false,
		],
		'position'   => [
			'sticky' => false,
		],
		'shadow'     => [
			'defaultPresets' => false,
			'presets'        => [],
		],
		'spacing'    => [
			'blockGap'            => true,
			'margin'              => false,
			'padding'             => false,
			'units'               => [],
			'customSpacingSize'   => false,
			'defaultSpacingSizes' => false,
			'spacingSizes'        => [],
			'spacingScale'        => [],
		],
		'typography' => [
			'defaultFontSizes' => false,
			'customFontSize'   => false,
			'fontStyle'        => false,
			'fontWeight'       => false,
			'fluid'            => false,
			'letterSpacing'    => false,
			'lineHeight'       => false,
			'textAlign'        => false,
			'textColumns'      => false,
			'textDecoration'   => false,
			'writingMode'      => false,
			'textTransform'    => false,
			'dropCap'          => false,
			'fontSizes'        => [],
			'fontFamilies'     => [],
		],
	];

	/**
	 * Block styles to remove.
	 *
	 * @var array<string,array<string>>
	 */
	const BLOCK_STYLES_TO_REMOVE = [
		'core/quote'     => [ 'plain' ],
		'core/separator' => [ 'wide', 'dots' ],
		'core/button'    => [ 'outline' ],
		'core/image'     => [ 'rounded' ],
		'core/table'     => [ 'stripes' ],
	];

	/**
	 * Register any needed hooks/filters.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'wp_theme_json_data_theme', [ $this, 'disallow_blocks_settings' ] );
		add_filter( 'register_block_type_args', [ $this, 'disallow_blocks_advanced_settings' ], 10, 2 );
		add_filter( 'register_block_type_args', [ $this, 'remove_blocks_styles' ], 10, 2 );
	}

	/**
	 * Disallow blocks settings from theme.json at runtime.
	 *
	 * Merges disallowed settings with existing block settings, with disallowed
	 * settings taking precedence. Creates block entries if they don't exist.
	 *
	 * @param WP_Theme_JSON_Data $theme_json Theme JSON data object.
	 *
	 * @return WP_Theme_JSON_Data Modified theme JSON data object.
	 */
	public function disallow_blocks_settings( WP_Theme_JSON_Data $theme_json ): WP_Theme_JSON_Data {
		$theme_data = $theme_json->get_data();

		// Initialize blocks array if it doesn't exist.
		if ( ! isset( $theme_data['settings']['blocks'] ) ) {
			$theme_data['settings']['blocks'] = [];
		}

		// Iterate through each block that needs restrictions.
		foreach ( self::BLOCKS_TO_RESTRICT_SETTINGS as $block_name ) {
			// Initialize block settings if they don't exist.
			if ( ! isset( $theme_data['settings']['blocks'][ $block_name ] ) ) {
				$theme_data['settings']['blocks'][ $block_name ] = [];
			}

			// Merge each category of disallowed settings.
			foreach ( self::DISALLOWED_SETTINGS as $setting_key => $setting_values ) {
				// Initialize setting category if it doesn't exist.
				if ( ! isset( $theme_data['settings']['blocks'][ $block_name ][ $setting_key ] ) ) {
					$theme_data['settings']['blocks'][ $block_name ][ $setting_key ] = [];
				}

				// Merge with disallowed settings taking precedence.
				$theme_data['settings']['blocks'][ $block_name ][ $setting_key ] = array_merge(
					$theme_data['settings']['blocks'][ $block_name ][ $setting_key ],
					$setting_values
				);
			}
		}

		return new WP_Theme_JSON_Data( $theme_data, 'theme' );
	}

	/**
	 * Disallow advanced settings from all blocks.
	 *
	 * @param array  $args       Block type arguments.
	 * @param string $block_type Block type name.
	 *
	 * @return array Modified block type arguments.
	 */
	public function disallow_blocks_advanced_settings( array $args, string $block_type ): array {
		$args['supports'] = $args['supports'] ?? [];

		// Keep only anchor support for heading blocks to support TOC anchors.
		if ( 'core/heading' === $block_type ) {
			$args['supports']['anchor']          = true; // HTML Anchor field.
			$args['supports']['customClassName'] = false; // Additional CSS Class(es) field.
			return $args;
		}

		// Remove advanced settings from all non-heading blocks.
		$args['supports']['anchor']          = false; // HTML Anchor field.
		$args['supports']['customClassName'] = false; // Additional CSS Class(es) field.

		return $args;
	}

	/**
	 * Remove unwanted core block styles during block registration.
	 *
	 * Filters the block registration args to remove specific default styles
	 * from blocks before they're registered. The styles to remove are defined
	 * in the BLOCK_STYLES_TO_REMOVE constant.
	 *
	 * @param array  $args       Block type arguments.
	 * @param string $block_type Block type name.
	 *
	 * @return array Modified block type arguments.
	 */
	public function remove_blocks_styles( array $args, string $block_type ): array {
		// Check if this block has styles to remove.
		if ( ! isset( self::BLOCK_STYLES_TO_REMOVE[ $block_type ] ) ) {
			return $args;
		}

		// Check if block has styles registered.
		if ( ! isset( $args['styles'] ) || ! is_array( $args['styles'] ) ) {
			return $args;
		}

		// Get the list of style slugs to remove for this block.
		$styles_to_remove = self::BLOCK_STYLES_TO_REMOVE[ $block_type ];

		// Filter out unwanted styles.
		$filtered_styles = array_filter(
			$args['styles'],
			function ( $style ) use ( $styles_to_remove ) {
				return isset( $style['name'] ) && ! in_array( $style['name'], $styles_to_remove, true );
			}
		);

		// If all styles were removed, unset the styles property entirely.
		// WordPress expects either an array with items or the property not to exist.
		if ( empty( $filtered_styles ) ) {
			unset( $args['styles'] );
		} else {
			// Re-index the array to avoid gaps in keys.
			$args['styles'] = array_values( $filtered_styles );
		}

		return $args;
	}
}
