<?php
/**
 * Newspack Ads Customizer.
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Placements;
use Newspack_Ads\Settings;
use Newspack_Ads\Placement_Customize_Control;

/**
 * Newspack Ads Customizer Class.
 */
final class Customizer {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'customize_register', [ __CLASS__, 'register_customizer_controls' ] );
		\add_action( 'customize_preview_init', [ __CLASS__, 'enqueue_preview_scripts' ] );
		\add_action( 'customize_controls_enqueue_scripts', [ __CLASS__, 'enqueue_control_scripts' ] );
	}

	/**
	 * Enqueue customizer preview script.
	 */
	public static function enqueue_preview_scripts() {
		\wp_enqueue_script(
			'newspack-ads-customizer-preview',
			\plugins_url( '../../dist/customizer-preview.js', __FILE__ ),
			[ 'customize-preview', 'jquery' ],
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/customizer-preview.js' ),
			true
		);
		\wp_register_style(
			'newspack-ads-customizer-preview-style',
			plugins_url( '../../dist/customizer-preview.css', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/customizer-preview.css' )
		);
		\wp_style_add_data( 'newspack-ads-customizer-preview-style', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-ads-customizer-preview-style' );
	}

	/**
	 * Enqueue customizer control script.
	 */
	public static function enqueue_control_scripts() {
		\wp_enqueue_script(
			'newspack-ads-customizer-control',
			\plugins_url( '../../dist/customizer-control.js', __FILE__ ),
			[ 'customize-controls', 'jquery', 'lodash' ],
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/customizer-control.js' ),
			true
		);
	}

	/**
	 * Get customizer section ID given a placement key.
	 *
	 * @param string $placement_key Placement key.
	 *
	 * @return string Section ID.
	 */
	private static function get_section_id( $placement_key ) {
		return sprintf( 'newspack_ads_placement_%s', $placement_key );
	}

	/**
	 * Sanitize placement value.
	 *
	 * @param string $value Placement value in JSON.
	 *
	 * @return array[] Sanitized placement value.
	 */
	public static function sanitize( $value ) {
		return \wp_json_encode( Placements::sanitize_placement( json_decode( $value, true ) ) );
	}

	/**
	 * Register customizer controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 */
	public static function register_customizer_controls( $wp_customize ) {
		include_once NEWSPACK_ADS_ABSPATH . '/includes/customizer/class-placement-customize-control.php';

		$placements = Placements::get_placements();
		$capability = Settings::API_CAPABILITY;

		// Register panel.
		$wp_customize->add_panel(
			'newspack-ads',
			[
				'title'       => __( 'Ad Placements', 'newspack-ads' ),
				'description' => __( 'Customize your ad placements.', 'newspack-ads' ),
				'priority'    => 110,
			]
		);
		foreach ( $placements as $placement_key => $placement ) {
			if ( false === $placement['show_ui'] ) {
				continue;
			}
			$section_id = self::get_section_id( $placement_key );
			$setting_id = Placements::get_option_name( $placement_key );
			$wp_customize->add_section(
				$section_id,
				[
					'title' => $placement['name'],
					'panel' => 'newspack-ads',
				]
			);
			$wp_customize->add_setting(
				$setting_id,
				[
					'type'              => 'option',
					'capability'        => $capability,
					'transport'         => 'postMessage',
					'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				]
			);
			$wp_customize->add_control(
				new Placement_Customize_Control(
					$wp_customize,
					$setting_id,
					[
						'placement' => $placement_key,
						'priority'  => 1,
						'section'   => $section_id,
					]
				)
			);
			$wp_customize->selective_refresh->add_partial(
				$setting_id,
				[
					'selector'            => sprintf( '.newspack_global_ad.%s', $placement_key ),
					'container_inclusive' => false,
					'fallback_refresh'    => true,
					'render_callback'     => function() use ( $placement_key ) {
						$data = Placements::get_placement_data( $placement_key );
						if ( ! isset( $data['enabled'] ) || false === $data['enabled'] ) {
							return;
						}
						if ( isset( $data['ad_unit'] ) && ! empty( $data['ad_unit'] ) ) {
							Placements::render_ad_unit_mock( $data['provider'], $data['ad_unit'], $data );
						}
						if ( isset( $data['hooks'] ) && ! empty( $data['hooks'] ) ) {
							foreach ( $data['hooks'] as $hook_key => $hook ) {
								if ( isset( $hook['ad_unit'] ) && ! empty( $hook['ad_unit'] ) ) {
									Placements::render_ad_unit_mock(
										$hook['provider'],
										$hook['ad_unit'],
										$data,
										[ sprintf( 'hook-%s', $hook_key ) ]
									);
								}
							}
						}
					},
				]
			);
		}
	}
}
Customizer::init();
