<?php
/**
 * Interface for classes that act as theme components.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

/**
 * Interface Component
 */
interface Component {
	/**
	 * Init the component. Hooks go in here.
	 *
	 * @return void
	 */
	public function init(): void;
}
