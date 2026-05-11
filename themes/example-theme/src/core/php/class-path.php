<?php
/**
 * Path on the filesystem.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

/**
 * Represent a path in the app.
 */
class Path {

	/**
	 * Absolute path to the reference base directory.
	 *
	 * @var array
	 */
	private $base;

	/**
	 * Setup path.
	 *
	 * @param string $base_path Absolute path to the base directory.
	 *
	 * @return void
	 */
	public function __construct( string $base_path ) {
		$this->base = explode( DIRECTORY_SEPARATOR, $this->normalize( $base_path ) );
	}

	/**
	 * Get the canonical base path.
	 *
	 * @return string Absolute path to the base directory.
	 */
	public function base(): string {
		return implode( DIRECTORY_SEPARATOR, $this->base );
	}

	/**
	 * Get path with normalized directory separators.
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	protected function normalize( string $path ): string {
		return preg_replace( '/[\\/]+/', DIRECTORY_SEPARATOR, $path );
	}

	/**
	 * Get absolute path for a path relative to the root.
	 *
	 * @param string $relative_path Path relative to the base.
	 *
	 * @return string
	 */
	public function to( string $relative_path ): string {
		$parts = $this->base;

		if ( is_string( $relative_path ) && strlen( $relative_path ) ) {
			$parts[] = ltrim( $this->normalize( $relative_path ), '\\/' );
		}

		return implode( DIRECTORY_SEPARATOR, $parts );
	}

	/**
	 * Get path relative to the base directory.
	 *
	 * @param string $absolute_path Absolute path to resolve relative to the base.
	 *
	 * @return string
	 */
	public function from( string $absolute_path ): string {
		$parts = explode( DIRECTORY_SEPARATOR, $this->normalize( $absolute_path ) );

		// Get the shared path elements from the root.
		$common_base = array_intersect_assoc( $this->base, $parts );

		return implode(
			DIRECTORY_SEPARATOR,
			array_merge(
				array_fill( 0, count( $this->base ) - count( $common_base ), '..' ),
				array_slice( $parts, count( $common_base ) )
			)
		);
	}
}
