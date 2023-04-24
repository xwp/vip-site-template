<?php
/**
 * Newspack Ads Utility Functions.
 *
 * @package Newspack
 */

namespace Newspack_Ads;

/**
 * Return a string from a size array.
 *
 * @param array $size Size array.
 *
 * @return string Size string.
 */
function get_size_string( $size ) {
	return $size[0] . 'x' . $size[1];
}

/**
 * Return an array from a size string.
 *
 * @param string $size Size string.
 *
 * @return array Size array.
 */
function get_size_array( $size ) {
	return array_map( 'intval', explode( 'x', $size ) );
}

/**
 * Get an extended list of ad sizes from the Interactive Advertising Bureau (IAB).
 *
 * The sizes 320x100 and 300x600 have been deprecated by IAB but are included
 * here for backwards compatibility and legacy reasons.
 *
 * @link https://www.iab.com/wp-content/uploads/2019/04/IABNewAdPortfolio_LW_FixedSizeSpec.pdf.
 *
 * @return string|null[] Associative array with ad sizes names keyed by their size string.
 */
function get_iab_sizes() {
	$sizes = [
		'970x250'   => __( 'Billboard', 'newspack-ads' ),
		'300x50'    => __( 'Mobile banner', 'newspack-ads' ),
		'320x50'    => __( 'Mobile banner', 'newspack-ads' ),
		'320x100'   => __( 'Large mobile banner', 'newspack-ads' ),
		'728x90'    => __( 'Leaderboard', 'newspack-ads' ),
		'970x90'    => __( 'Super Leaderboard/Pushdown', 'newspack-ads' ),
		'300x1050'  => __( 'Portrait', 'newspack-ads' ),
		'160x600'   => __( 'Skyscraper', 'newspack-ads' ),
		'300x600'   => __( 'Half page', 'newspack-ads' ),
		'300x250'   => __( 'Medium Rectangle', 'newspack-ads' ),
		'120x60'    => null,
		'640x1136'  => __( 'Mobile Phone Interstitial', 'newspack-ads' ),
		'750x1334'  => __( 'Mobile Phone Interstitial', 'newspack-ads' ),
		'1080x1920' => __( 'Mobile Phone Interstitial', 'newspack-ads' ),
		'120x20'    => __( 'Feature Phone Small Banner', 'newspack-ads' ),
		'168x28'    => __( 'Feature Phone Medium Banner', 'newspack-ads' ),
		'216x36'    => __( 'Feature Phone Large Banner', 'newspack-ads' ),
	];
	return apply_filters( 'newspack_ads_iab_sizes', $sizes );
}

/**
 * Get sizes array from IAB standard sizes.
 *
 * @return array[] Array of ad sizes.
 */
function get_iab_size_array() {
	return array_map( '\Newspack_Ads\get_size_array', array_keys( get_iab_sizes() ) );
}
