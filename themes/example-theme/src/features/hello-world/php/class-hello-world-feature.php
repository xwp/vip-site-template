<?php
/**
 * Hello World feature.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Features;

/**
 * Hello World feature.
 *
 * A minimal example feature that adds a body class to demonstrate
 * the feature auto-discovery and initialization system.
 */
class Hello_World_Feature extends Feature {

	/**
	 * Feature-specific initialization.
	 *
	 * @return void
	 */
	protected function feature_init(): void {
		add_filter( 'body_class', [ Hello_World_Feature::class, 'add_body_class' ] );
	}

	/**
	 * Append the hello-world class to the body class list.
	 *
	 * @param string[] $classes Existing body classes.
	 *
	 * @return string[]
	 */
	public static function add_body_class( array $classes ): array {
		$classes[] = 'hello-world-feature';
		return $classes;
	}
}
