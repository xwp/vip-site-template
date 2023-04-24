<?php
/**
 * Reader Activation login form.
 * This is an empty file in order to trick WooCommerce's My Account login template
 * into rendering nothing so that we can replace it with our own login form.
 *
 * @package Newspack
 * @version 7.0.1
 */

namespace Newspack;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

\do_action( 'woocommerce_before_customer_login_form' );

Reader_Activation::render_auth_form( true );

\do_action( 'woocommerce_after_customer_login_form' );
