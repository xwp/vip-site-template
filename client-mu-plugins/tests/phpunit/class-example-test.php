<?php

class Test_Example extends \WP_UnitTestCase {

	public function test_actions_exist() {
		$this->assertTrue( function_exists( 'add_action' ) );
	}
}
