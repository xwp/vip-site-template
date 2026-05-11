<?php
/**
 * Theme.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

use XWP\VIP_Site_Template\Theme\Components\Component;

/**
 * Theme.
 */
class Theme {

	/**
	 * Path to the app root directory.
	 *
	 * @var Path
	 */
	private $path;

	/**
	 * Theme components.
	 *
	 * @var Components
	 */
	private $components;

	/**
	 * Constructor.
	 *
	 * @param string $path Absolute path to the theme root.
	 *
	 * @return void
	 */
	public function __construct( string $path ) {
		$this->path       = new Path( $path );
		$this->components = new Components();
	}

	/**
	 * Get the components.
	 *
	 * @return Components
	 */
	public function components(): Components {
		return $this->components;
	}

	/**
	 * Shorter component resolver.
	 *
	 * @template T of Component
	 *
	 * @param class-string<T> $name Component class name.
	 *
	 * @return T Component instance.
	 */
	public function component( string $name ): Component { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		// Returns the component instance of the given class name.
		return $this->components->component( $name );
	}

	/**
	 * Return the theme root path instance.
	 *
	 * @return Path
	 */
	public function path(): Path {
		return $this->path;
	}

	/**
	 * Get an asset by path relative to the theme root.
	 *
	 * @param string $relative_path Relative path.
	 *
	 * @return Asset
	 */
	public function asset( string $relative_path ): Asset {
		return new Asset( $this->path->to( $relative_path ) );
	}
}
