<?php
/**
 * Asset.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

/**
 * Resolves a JS or CSS asset meta from *.asset.php file.
 */
class Asset {

	/**
	 * Absolute path to an asset file.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Path to the WP content root directory.
	 *
	 * Used for resolving the asset URL relative to the WP_CONTENT_URL.
	 *
	 * @var Path
	 */
	protected $base_path;

	/**
	 * URL to the asset.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * The *.asset.php contents.
	 *
	 * @var array
	 */
	protected $meta;

	/**
	 * Set the asset by absolute path.
	 *
	 * @param string $path Absolute path to the asset.
	 *
	 * @return void
	 */
	public function __construct( string $path ) {
		$this->path = $path;

		// Account for WP_CONTENT_DIR being a symbolic link (symlink).
		$this->base_path = new Path( WP_CONTENT_DIR );
	}

	/**
	 * Get the asset URL.
	 *
	 * @return string
	 */
	public function url(): string {
		return content_url( $this->base_path->from( $this->path ) );
	}

	/**
	 * Get the absolute path to the asset.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Does the asset path exist.
	 *
	 * @return bool
	 */
	public function exists(): bool {
		return is_readable( $this->path );
	}

	/**
	 * Get the *.asset.php path for the asset.
	 *
	 * @return string
	 */
	protected function asset_php_path(): string {
		return sprintf(
			'%s/%s',
			dirname( $this->path ),
			sprintf( '%s.asset.php', pathinfo( $this->path, PATHINFO_FILENAME ) )
		);
	}

	/**
	 * Get the asset version from the *.asset.php file.
	 *
	 * @return string|null Version or null if not specified.
	 */
	public function version(): string|null {
		return $this->meta( 'version' );
	}

	/**
	 * Get list of asset dependencies from the *.asset.php file.
	 *
	 * @return array
	 */
	public function dependencies(): array {
		return $this->meta( 'dependencies' );
	}

	/**
	 * Get asset meta.
	 *
	 * @param string $key Meta key to resolve.
	 *
	 * @return mixed|null Meta value or null if not found.
	 */
	protected function meta( string $key ): mixed {
		$default_meta = [
			'dependencies' => [],
			'version'      => null,
		];

		// Resolve meta only once, if not found.
		if ( ! isset( $this->meta ) ) {
			$asset_php_path = $this->asset_php_path();

			if ( is_readable( $asset_php_path ) ) {
				$asset_php = require $asset_php_path;

				if ( isset( $asset_php['version'] ) ) {
					$this->meta = array_merge( $default_meta, $asset_php );
				}
			}
		}

		if ( isset( $this->meta[ $key ] ) ) {
			return $this->meta[ $key ];
		}

		return null;
	}
}
