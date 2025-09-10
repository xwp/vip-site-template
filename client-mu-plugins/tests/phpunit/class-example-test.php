<?php
/**
 * Test the WP setup.
 *
 * @package XWP\Vip_Site_Template
 */

/**
 * Test the WP setup.
 */
class Example_Test extends \WP_UnitTestCase {

	/**
	 * Test that the add_action function exists.
	 *
	 * @return void
	 */
	public function test_actions_exist() {
		$this->assertTrue( function_exists( 'add_action' ) );
	}
}
