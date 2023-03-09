<?php
/**
 * Plugin Name: Environment Config
 *
 * @package XWP\Vip_Site_Template
 */

// Always enable Query Monitor on local.
if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'local' === VIP_GO_APP_ENVIRONMENT ) {
	add_filter( 'wpcom_vip_qm_enable', '__return_true' );
}
