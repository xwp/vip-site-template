<?php
/**
 * Container.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

use Container_Not_Registered_Exception;
use Psr\Container\ContainerInterface;

/**
 * Container.
 */
class Container implements ContainerInterface {

	/**
	 * List of all known bindings.
	 *
	 * @var array<string,callable>
	 */
	private $bindings = [];

	/**
	 * Attach a resolver.
	 *
	 * @param string $id Binding key.
	 * @param mixed  $resolver Callback resolver.
	 *
	 * @return void
	 */
	public function add( string $id, mixed $resolver ): void {
		$this->bindings[ $id ] = $resolver;
	}

	/**
	 * If container has a binding.
	 *
	 * @param string $id Binding key.
	 *
	 * @return boolean
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] );
	}

	/**
	 * Resolve a binding.
	 *
	 * @param string $id Binding key.
	 *
	 * @return mixed
	 *
	 * @throws Container_Not_Registered_Exception If the binding is not found.
	 */
	public function get( string $id ): mixed {
		if ( ! $this->has( $id ) ) {
			throw new Container_Not_Registered_Exception( sprintf( 'No resolver defined for `%s`', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- expected to be escaped by caller.
		}

		if ( is_callable( $this->bindings[ $id ] ) ) {
			return call_user_func( $this->bindings[ $id ], $this );
		}

		return $this->bindings[ $id ];
	}
}
