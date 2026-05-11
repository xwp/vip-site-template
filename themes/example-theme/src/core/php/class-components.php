<?php
/**
 * Components.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

use RuntimeException;
use XWP\VIP_Site_Template\Theme\Components\Component;

/**
 * Components.
 */
class Components {

	/**
	 * List of components.
	 *
	 * @var Container
	 */
	private Container $components;

	/**
	 * Setup components.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->components = new Container();
	}

	/**
	 * Add a new component to the list.
	 *
	 * @param Components\Component $component The new Component to register.
	 *
	 * @throws RuntimeException Throws an exception if component is already registered.
	 *
	 * @return void
	 */
	public function add( Components\Component $component ): void {
		if ( $this->components->has( $component::class ) ) {
			throw new RuntimeException( 'Component ' . $component::class . ' has already been registered' );
		}

		$this->components->add( $component::class, $component );
	}

	/**
	 * Resolve theme component.
	 *
	 * @param string $name Component classname.
	 *
	 * @throws RuntimeException If the component is not found.
	 *
	 * @return Component
	 */
	public function component( string $name ): Component {
		if ( $this->components->has( $name ) ) {
			return $this->components->get( $name );
		}

		throw new RuntimeException( sprintf( 'Component %s not found!', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- expecting the caller to escape as needed.
	}
}
