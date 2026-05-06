<?php
/**
 * Styles.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

use RuntimeException;

use function XWP\VIP_Site_Template\Theme\theme;

/**
 * Styles.
 */
class Styles implements Component {

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 *
	 * @return void
	 */
	public function init(): void {
		// Enqueue frontend styles.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_styles' ] );

		// Enqueue editor styles.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_styles' ] );
	}

	/**
	 * Enqueue frontend styles.
	 *
	 * @throws RuntimeException Throws if assets aren't built.
	 *
	 * @return void
	 */
	public function enqueue_frontend_styles(): void {
		$this->enqueue_style( 'example-frontend', 'build/frontend.css' );
	}

	/**
	 * Enqueue editor styles.
	 *
	 * @throws RuntimeException Throws if assets aren't built.
	 *
	 * @return void
	 */
	public function enqueue_editor_styles(): void {
		$this->enqueue_style( 'example-editor', 'build/editor.css' );
	}

	/**
	 * Helper method to enqueue a style.
	 *
	 * @param string $handle Style handle.
	 * @param string $path   Path to the CSS file.
	 *
	 * @throws RuntimeException Throws if assets aren't built.
	 *
	 * @return void
	 */
	private function enqueue_style( string $handle, string $path ): void {
		$asset = theme()->asset( $path );

		if ( ! $asset->exists() ) {
			throw new RuntimeException( 'Built assets not found: ' . esc_html( $path ) . '. Please run `npm run build`' );
		}

		wp_enqueue_style(
			$handle,
			$asset->url(),
			[],
			$asset->version()
		);
	}
}
