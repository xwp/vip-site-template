<?php
/**
 * Environment tests.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

use WP_UnitTestCase;

/**
 * Test Environment.
 */
class Environment extends WP_UnitTestCase {

	/**
	 * Ensure that WP is loaded.
	 */
	public function test_wordpress_and_plugin_are_loaded() {
		$this->assertTrue( function_exists( 'do_action' ) );
	}
}
