<?php
/**
 * Newspack Ads GAM Api Instance Object.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers\GAM\Api;

use Newspack_Ads\Providers\GAM\Api;
use Google\AdsApi\AdManager\AdManagerSession;

/**
 * Newspack Ads GAM Api Instance Object Abstract Class.
 */
abstract class Api_Object {
	/**
	 * Session object.
	 *
	 * @var AdManagerSession
	 */
	protected $session;

	/**
	 * Api Instance.
	 *
	 * @var Api
	 */
	protected $api;

	/**
	 * Contructor.
	 *
	 * @param AdManagerSession $session Session.
	 * @param Api              $api     API Instance.
	 */
	public function __construct( $session = null, $api = null ) {
		$this->session = $session;
		$this->api     = $api;
	}
}
