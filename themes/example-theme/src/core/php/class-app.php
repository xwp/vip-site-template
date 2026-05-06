<?php
/**
 * App.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

use RuntimeException;

/**
 * App.
 */
class App {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private static $container;

	/**
	 * Setup the app.
	 *
	 * @return void
	 */
	public function __construct() {
		self::$container = new Container();
	}

	/**
	 * Register a resolver for a class.
	 *
	 * @param string $name Class name.
	 * @param mixed  $resolver Resolver callback.
	 *
	 * @return void
	 */
	public static function bind( string $name, $resolver ): void {
		self::$container->add( $name, $resolver );
	}

	/**
	 * Retrieves an instance of the theme.
	 *
	 * @param string $name Class name.
	 *
	 * @throws RuntimeException If the class resolver not found.
	 *
	 * @return mixed
	 */
	public static function resolve( string $name ): mixed {
		try {
			return self::$container->get( $name );
		} catch ( RuntimeException $e ) {
			if ( Utils::is_debug() ) {
				throw new RuntimeException( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- happens only during dev.
			}
		}

		return null;
	}
}
