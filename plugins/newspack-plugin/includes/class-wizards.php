<?php
/**
 * Newspack Wizards manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the wizards.
 */
class Wizards {

	/**
	 * Information about all of the wizards.
	 * See `init` for structure of the data.
	 *
	 * @var array
	 */
	protected static $wizards = [];

	/**
	 * Initialize and register all of the wizards.
	 */
	public static function init() {
		self::$wizards = [
			'setup'           => new Setup_Wizard(),
			'dashboard'       => new Dashboard(),
			'site-design'     => new Site_Design_Wizard(),
			'reader-revenue'  => new Reader_Revenue_Wizard(),
			'advertising'     => new Advertising_Wizard(),
			'syndication'     => new Syndication_Wizard(),
			'analytics'       => new Analytics_Wizard(),
			'components-demo' => new Components_Demo(),
			'seo'             => new SEO_Wizard(),
			'health-check'    => new Health_Check_Wizard(),
			'engagement'      => new Engagement_Wizard(),
			'popups'          => new Popups_Wizard(),
			'connections'     => new Connections_Wizard(),
			'settings'        => new Settings(),
		];
	}

	/**
	 * Get a wizard's object.
	 *
	 * @param string $wizard_slug The wizard to get. Use slug from self::$wizards.
	 * @return Wizard | bool The wizard on success, false on failure.
	 */
	public static function get_wizard( $wizard_slug ) {
		if ( isset( self::$wizards[ $wizard_slug ] ) ) {
			return self::$wizards[ $wizard_slug ];
		}

		return false;
	}

	/**
	 * Get a wizard's URL.
	 *
	 * @param string $wizard_slug The wizard to get URL for. Use slug from self::$wizards.
	 * @return string | bool The URL on success, false on failure.
	 */
	public static function get_url( $wizard_slug ) {
		$wizard = self::get_wizard( $wizard_slug );
		if ( $wizard ) {
			return $wizard->get_url();
		}

		return false;
	}

	/**
	 * Get all the URLs for all the wizards.
	 *
	 * @return array of slug => URL pairs.
	 */
	public static function get_urls() {
		$urls = [];
		foreach ( self::$wizards as $slug => $wizard ) {
			$urls[ $slug ] = $wizard->get_url();
		}

		return $urls;
	}

	/**
	 * Get a wizard's name.
	 *
	 * @param string $wizard_slug The wizard to get name for. Use slug from self::$wizards.
	 * @return string | bool The name on success, false on failure.
	 */
	public static function get_name( $wizard_slug ) {
		$wizard = self::get_wizard( $wizard_slug );
		if ( $wizard ) {
			return $wizard->get_name();
		}

		return false;
	}

	/**
	 * Get whether a wizard is completed.
	 *
	 * @param string $wizard_slug The wizard to get completion for. Use slug from self::$wizards.
	 * @return bool True if completed. False otherwise.
	 */
	public static function is_completed( $wizard_slug ) {
		$wizard = self::get_wizard( $wizard_slug );
		if ( $wizard ) {
			return $wizard->is_completed();
		}

		return false;
	}
}
Wizards::init();
