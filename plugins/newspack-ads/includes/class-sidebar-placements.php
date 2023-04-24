<?php
/**
 * Newspack Ads Sidebar Placements
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Placements;

/**
 * Newspack Ads Sidebar Placements
 */
final class Sidebar_Placements {

	// Hook names to be used for ad placement.
	const SIDEBAR_BEFORE_HOOK_NAME = 'newspack_ads_sidebar_before_placement_%s';
	const SIDEBAR_AFTER_HOOK_NAME  = 'newspack_ads_sidebar_after_placement_%s';

	// Sidebars that should have a single ad unit instead of "before" and "after" hooks.
	const SINGLE_UNIT_SIDEBARS = [
		'footer-2',
		'article-1',
		'article-2',
	];

	// Sidebars to not create ad placement.
	const DISALLOWED_SIDEBARS = [
		'header-2',
		'header-3',
		'footer-3',
	];

	// Sidebars that allow sticky positioning.
	const STICK_TO_TOP_SIDEBARS = [
		'sidebar-1',
	];

	// Sidebars to allow ads even when no widgets are configured.
	const ALLOWED_EMPTY_SIDEBARS = [
		'footer-2',
		'article-1',
		'article-2',
	];

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_placements' ] );
		add_action( 'dynamic_sidebar_before', [ __CLASS__, 'create_sidebar_before_action' ], 10, 2 );
		add_action( 'dynamic_sidebar_after', [ __CLASS__, 'create_sidebar_after_action' ], 10, 2 );
		add_filter( 'is_active_sidebar', [ __CLASS__, 'allow_empty_sidebars' ], 10, 2 );
	}

	/**
	 * Get the placement key from the sidebar name.
	 *
	 * @param int|string $index Index, name, or ID of the dynamic sidebar.
	 *
	 * @return string The placement key.
	 */
	private static function get_placement_key( $index ) {
		return sprintf( 'sidebar_%s', $index );
	}

	/**
	 * Create a dynamic sidebar before action appropriate for ad unit insertion.
	 *
	 * @param int|string $index       Index, name, or ID of the dynamic sidebar.
	 * @param boolean    $has_widgets Whether the sidebar is populated with widgets. Default true.
	 */
	public static function create_sidebar_before_action( $index, $has_widgets ) {
		if ( $has_widgets || in_array( $index, self::ALLOWED_EMPTY_SIDEBARS, true ) ) {
			do_action( sprintf( self::SIDEBAR_BEFORE_HOOK_NAME, $index ) );
		}
	}

	/**
	 * Ensure that a sidebar is active if it has ads and is allowed to show ads
	 * even without any registered widgets.
	 *
	 * @param bool       $is_active_sidebar Whether the sidebar is active.
	 * @param int|string $index             Index, name, or ID of the dynamic sidebar.
	 *
	 * @return bool Whether the sidebar is active.
	 */
	public static function allow_empty_sidebars( $is_active_sidebar, $index ) {
		if ( ! $is_active_sidebar && in_array( $index, self::ALLOWED_EMPTY_SIDEBARS, true ) ) {
			$is_active_sidebar = Placements::can_display_ad_unit( self::get_placement_key( $index ) );
		}
		return $is_active_sidebar;
	}

	/**
	 * Create a dynamic sidebar after action appropriate for ad unit insertion.
	 *
	 * @param int|string $index       Index, name, or ID of the dynamic sidebar.
	 * @param boolean    $has_widgets Whether the sidebar is populated with widgets. Default true.
	 */
	public static function create_sidebar_after_action( $index, $has_widgets ) {
		if ( $has_widgets || in_array( $index, self::ALLOWED_EMPTY_SIDEBARS, true ) ) {
			do_action( sprintf( self::SIDEBAR_AFTER_HOOK_NAME, $index ) );
		}
	}

	/**
	 * Register sidebars as ad placements.
	 */
	public static function register_placements() {
		$sidebars = $GLOBALS['wp_registered_sidebars'];

		$disallowed_sidebars  = apply_filters( 'newspack_ads_disallowed_sidebar_placements', self::DISALLOWED_SIDEBARS );
		$single_unit_sidebars = apply_filters( 'newspack_ads_single_unit_sidebar_placements', self::SINGLE_UNIT_SIDEBARS );

		foreach ( $sidebars as $sidebar ) {
			if ( isset( $sidebar['id'] ) ) {
				$placement_key = self::get_placement_key( $sidebar['id'] );

				// Skip disallowed sidebar placements.
				$is_disallowed = in_array( $sidebar['id'], $disallowed_sidebars, true );

				// Disable SCAIP sidebars.
				if ( 'scaip' === substr( $sidebar['id'], 0, 5 ) ) {
					$is_disallowed = true;
				}

				if ( $is_disallowed ) {
					continue;
				}

				$supports = [];

				if ( in_array( $sidebar['id'], self::STICK_TO_TOP_SIDEBARS, true ) ) {
					$supports[] = 'stick_to_top';
				}

				$placement_config = [
					'name'        => $sidebar['name'],
					// Translators: %s: The name of the sidebar.
					'description' => sprintf( __( 'Choose an ad unit to display in the "%s" widget area.', 'newspack-ads' ), $sidebar['name'] ),
					'supports'    => $supports,
				];

				if ( in_array( $sidebar['id'], $single_unit_sidebars, true ) ) {
					$placement_config['hook_name'] = sprintf( self::SIDEBAR_BEFORE_HOOK_NAME, $sidebar['id'] );
				} else {
					$placement_config['hooks'] = [
						'before' => [
							'name'      => __( 'Before Widget Area', 'newspack-ads' ),
							'hook_name' => sprintf( self::SIDEBAR_BEFORE_HOOK_NAME, $sidebar['id'] ),
						],
						'after'  => [
							'name'      => __( 'After Widget Area', 'newspack-ads' ),
							'hook_name' => sprintf( self::SIDEBAR_AFTER_HOOK_NAME, $sidebar['id'] ),
						],
					];
				}
				Placements::register_placement( $placement_key, $placement_config );
			}
		}
	}
}
Sidebar_Placements::init();
