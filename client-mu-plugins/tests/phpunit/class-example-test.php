<?php
/**
 * Test the WP setup.
 *
 * @package XWP\Vip_Site_Template
 */

class Test_Example extends \WP_UnitTestCase {

	public function test_actions_exist() {
		$this->assertTrue( function_exists( 'add_action' ) );
	}
}
