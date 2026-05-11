<?php
/**
 * Block registry.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

use Exception;
use XWP\VIP_Site_Template\Theme\Path;

/**
 * Block registry.
 *
 * @link https://developer.wordpress.org/block-editor/reference-guides/block-api/block-patterns/
 */
class Block_Registry implements Component {

	/**
	 * Block directories with this prefixed are registered only during development.
	 *
	 * @var string
	 */
	const TEMPLATE_DIRECTORY_PREFIX = '_';

	/**
	 * Block.json asset path keys that we'll rewrite to point to build directory.
	 *
	 * @var array
	 */
	const ASSET_PATH_KEYS = [
		'script',
		'viewScript',
		'editorScript',
		'style',
		'editorStyle',
	];

	/**
	 * List of absolute paths to registered blocks.
	 *
	 * @var string[]
	 */
	protected $blocks = [];

	/**
	 * Block source directory relative to the theme root.
	 *
	 * We register the build directory with WP and rewrite any
	 * render template paths back to the source directory since
	 * wp-scripts doesn't move any other PHP files to the
	 * build directory.
	 *
	 * @var Path
	 */
	protected $source_directory;

	/**
	 * Block distribution directory relative to the theme root.
	 *
	 * Contains block.json and all the built JS and CSS. All PHP
	 * is loaded from the source directory instead.
	 *
	 * @var Path
	 */
	protected $build_directory;

	/**
	 * Setup the blocks registry.
	 *
	 * @param Path $source_directory Blocks source directory.
	 * @param Path $build_directory  Blocks build directory.
	 *
	 * @return void
	 */
	public function __construct( Path $source_directory, Path $build_directory ) {
		$this->source_directory = $source_directory;
		$this->build_directory  = $build_directory;
	}

	/**
	 * Register any needed hooks/filters.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'action_register_blocks' ], 1 );
		add_filter( 'block_type_metadata', [ $this, 'maybe_rewrite_block_asset_paths' ] );
		add_filter( 'block_categories_all', [ $this, 'action_add_block_category' ], 10 );
	}

	/**
	 * Register all blocks in the source directory to account
	 * for wp-scripts not moving all PHP scripts during the build.
	 *
	 * All block asset paths are rewritten to point to the build
	 * directory dynamically.
	 *
	 * @return void
	 *
	 * @throws Exception Error when no blocks are found.
	 */
	public function action_register_blocks(): void {
		// Lookup by block.json since we have other non-block directories in the same folder.
		$block_json_paths = glob( $this->source_directory->to( '*/block.json' ) );

		if ( ! is_array( $block_json_paths ) ) {
			throw new Exception( 'Failed to find any blocks.' );
		}

		$block_directories = array_map( 'dirname', $block_json_paths );

		foreach ( $block_directories as $directory ) {
			// Allow blocks to run any custom logic during registration.
			$block_functions_path = sprintf( '%s/functions.php', $directory );

			if ( is_readable( $block_functions_path ) ) {
				require_once $block_functions_path;
			}

			$this->blocks[] = $directory; // Simplify local block lookup.

			// Register the block.
			register_block_type( $directory );
		}
	}

	/**
	 * Rewrite PHP template path if it's specified.
	 *
	 * The wp-scripts doesn't move all PHP files to the build directory
	 * during the build like it does with CSS and JS files so we keep
	 * all PHP assets under the original block source directory.
	 *
	 * @param array $metadata Registered block metadata.
	 *
	 * @return array Modified metadata.
	 */
	public function maybe_rewrite_block_asset_paths( array $metadata ): array {
		if ( ! empty( $metadata['file'] ) && $this->is_registry_block( $metadata['file'] ) ) {
			$block_path = dirname( $metadata['file'] );

			foreach ( self::ASSET_PATH_KEYS as $asset_type ) {
				if ( ! empty( $metadata[ $asset_type ] ) ) {
					$metadata[ $asset_type ] = $this->rewrite_assets_paths( $metadata[ $asset_type ], $block_path );
				}
			}
		}

		return $metadata;
	}

	/**
	 * If the full path of a block asset belongs to a block in this registry.
	 *
	 * @param string $block_path Absolute path to any block asset.
	 *
	 * @return boolean
	 */
	public function is_registry_block( string $block_path ): bool {
		return in_array( dirname( $block_path ), $this->blocks, true );
	}

	/**
	 * Rewrite assets paths to point to the built assets.
	 *
	 * @param string|string[] $assets     Single or array of asset paths or handles.
	 * @param string          $block_path Path to block directory.
	 *
	 * @return string[] Rewritten assets paths.
	 */
	protected function rewrite_assets_paths( array|string $assets, string $block_path ): array|string {
		if ( is_string( $assets ) ) {
			$assets = [ $assets ];
		}

		$block_name = basename( $block_path );

		// Build relative path: ../../../build/blocks/hello-world.
		$relative_path = '../../../build/blocks/' . $block_name;

		foreach ( $assets as $key => $asset ) {
			$filename = remove_block_asset_path_prefix( $asset );

			// Rewrite only paths (not handle IDs).
			if ( $filename !== $asset ) {
				// Convert ./js/editor.js to editor.js, ./css/frontend.scss to frontend.css.
				$clean_filename = ltrim( $filename, './' );
				$clean_filename = str_replace( [ 'js/', 'css/' ], '', $clean_filename );

				// Handle SCSS to CSS conversion.
				if ( str_ends_with( $clean_filename, '.scss' ) ) {
					$clean_filename = str_replace( '.scss', '.css', $clean_filename );
				}

				$assets[ $key ] = sprintf(
					'file:%s/%s',
					$relative_path,
					$clean_filename
				);
			}
		}

		return $assets;
	}

	/**
	 * Adding a new (custom) block category.
	 *
	 * @param array $block_categories Array of categories for block types.
	 *
	 * @return array List of available block categories.
	 */
	public function action_add_block_category( array $block_categories ): array {
		return array_merge(
			$block_categories,
			[
				[
					'slug'  => 'example-blocks',
					'title' => __( 'Example Blocks', 'example-theme' ),
				],
			]
		);
	}
}
