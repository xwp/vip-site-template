<?php
/**
 * Newspack API setup.
 *
 * @package Newspack
 */

namespace Newspack;

use Newspack\Api\Plugins_Controller;
use Newspack\Api\Wizards_Controller;
use Newspack\Api\Popups_Analytics_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the API as a whole.
 */
class API {

	/**
	 * Load up and register the endpoints.
	 */
	public function __construct() {
		include_once 'api/class-plugins-controller.php';
		include_once 'api/class-wizards-controller.php';

		$plugins_api = new Plugins_Controller();
		add_action( 'rest_api_init', [ $plugins_api, 'register_routes' ] );

		$wizards_api = new Wizards_Controller();
		add_action( 'rest_api_init', [ $wizards_api, 'register_routes' ] );
	}
}
new API();
