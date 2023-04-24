<?php
/**
 * Newspack Ads Public Functions.
 *
 * The functions in this file are not namespaced and should always be prefixed
 * with `newspack_ads_`.
 *
 * @package Newspack
 */

use Newspack_Ads\Suppression;

if ( ! function_exists( 'newspack_ads_should_show_ads' ) ) {
	/**
	 * Get whether ads should be displayed on a screen.
	 *
	 * @param int $post_id Post ID to check (optional, default: current post).
	 * @return bool
	 */
	function newspack_ads_should_show_ads( $post_id = null ) {
		return Suppression::should_show_ads( $post_id );
	}
}
