<?php
/**
 * Plugin Name:     Newspack Ads
 * Plugin URI:      https://newspack.blog
 * Description:     Ad services integration.
 * Author:          Automattic
 * License:         GPL2
 * Version:         1.42.5
 *
 * @package         Newspack
 */

namespace Newspack_Ads;

defined( 'ABSPATH' ) || exit;

// Define NEWSPACK_ADS_PLUGIN_FILE.
if ( ! defined( 'NEWSPACK_ADS_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_ADS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'NEWSPACK_ADS_COMPOSER_ABSPATH' ) ) {
	define( 'NEWSPACK_ADS_COMPOSER_ABSPATH', dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/vendor/' );
}

// Include the main Newspack Ads class.
if ( ! class_exists( 'Newspack_Ads\Core' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-core.php';
}
