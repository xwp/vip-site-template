<?php
/**
 * Bootstrap all must-use plugins.
 *
 * Named with a numeric prefix to be loaded before all other must-use plugins.
 *
 * @package XWP\Vip_Site_Template
 */

/**
 * Use wpcom_vip_load_plugin( 'plugin-name' ); to always
 * enable certain plugins from wp-content/plugins.
 */
wpcom_vip_load_plugin( 'safe-svg' );
wpcom_vip_load_plugin( 'wpcom-legacy-redirector' );
wpcom_vip_load_plugin( 'action-scheduler' );
