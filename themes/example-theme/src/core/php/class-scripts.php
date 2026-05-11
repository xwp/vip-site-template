<?php
/**
 * Scripts.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

use RuntimeException;
use XWP\VIP_Site_Template\Theme\Utils;

use function XWP\VIP_Site_Template\Theme\theme;

/**
 * Scripts.
 */
class Scripts implements Component {

	/**
	 * Asset handle for the Webpack runtime during development.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE_WEBPACK_RUNTIME = 'webpack-runtime';

	/**
	 * Handles for shared dependencies such as the webpack runtime.
	 *
	 * @var array
	 */
	private $dependencies = [];

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register shared runtime early.
		add_action( 'enqueue_block_editor_assets', [ $this, 'register_shared_runtime' ], 8 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_shared_runtime' ], 8 );

		// Enqueue core scripts.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Register the shared runtime script.
	 *
	 * @return void
	 */
	public function register_shared_runtime(): void {
		/**
		 * The webpack runtime is ejected into own file (per Webpack config)
		 * so all other scripts must register it as a dependency.
		 */
		$webpack_runtime_asset = theme()->asset( 'build/webpack/runtime.js' );

		if ( $webpack_runtime_asset->exists() ) {
			wp_register_script(
				self::SCRIPT_HANDLE_WEBPACK_RUNTIME,
				$webpack_runtime_asset->url(),
				$webpack_runtime_asset->dependencies(),
				$webpack_runtime_asset->version(),
				[
					'in_footer' => true,
					'strategy'  => 'defer',
				]
			);

			$this->dependencies[] = self::SCRIPT_HANDLE_WEBPACK_RUNTIME;
		}
	}

	/**
	 * Helper method to enqueue a script.
	 *
	 * @param string     $handle      Script handle.
	 * @param string     $path        Path to the JS file.
	 * @param array|null $data        Optional inline data to add to the script.
	 * @param bool       $is_frontend Whether this is a frontend script (uses defer strategy).
	 *
	 * @throws RuntimeException Throws if script asset is not found.
	 *
	 * @return void
	 */
	private function enqueue_script( string $handle, string $path, ?array $data = null, bool $is_frontend = false ): void {
		$script_asset = theme()->asset( $path );

		if ( ! $script_asset->exists() ) {
			throw new RuntimeException( 'Built assets not found: ' . esc_html( $path ) . '. Please run `npm run build`' );
		}

		$args = [ 'in_footer' => true ];

		if ( $is_frontend ) {
			$args['strategy'] = 'defer';
		}

		wp_enqueue_script(
			$handle,
			$script_asset->url(),
			array_merge( $script_asset->dependencies(), $this->dependencies ),
			$script_asset->version(),
			$args
		);

		// Add inline data if provided.
		if ( $data ) {
			// Convert handle to camelCase object name,
			// e.g., 'example-editor' -> 'exampleEditor'.
			$object_name = Utils::snake_to_camel( str_replace( '-', '_', $handle ) );

			wp_add_inline_script(
				$handle,
				"const {$object_name} = " . wp_json_encode( $data ),
				'before'
			);
		}
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @throws RuntimeException Throws if script asset is not found.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$this->enqueue_script( 'example-editor', 'build/editor.js', $this->get_editor_data() );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @throws RuntimeException Throws if script asset is not found.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		$this->enqueue_script( 'example-frontend', 'build/frontend.js', null, true );
	}

	/**
	 * Get editor data for inline scripts.
	 *
	 * @return array
	 */
	private function get_editor_data(): array {
		return apply_filters(
			'example_editor_data',
			[
				'home_url' => home_url(),
			]
		);
	}
}
