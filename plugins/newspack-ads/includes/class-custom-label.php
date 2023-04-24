<?php
/**
 * Newspack Ads Custom Ad Label
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Settings;

/**
 * Newspack Ads Custom Ad Label Class.
 */
final class Custom_Label {

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'newspack_ads_settings_list', [ __CLASS__, 'settings_list' ] );
		add_action( 'wp_head', [ __CLASS__, 'render_label' ] );
	}

	/**
	 * Register Custom Ad Placement Label settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public static function settings_list( $settings_list ) {
		return array_merge(
			[
				[
					'description' => esc_html__( 'Custom Ad Label', 'newspack-ads' ),
					'help'        => esc_html__( 'Add a custom text to be displayed right before your rendered ads.' ),
					'section'     => 'custom_label',
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => false,
				],
				[
					'description' => esc_html__( 'Label', 'newspack-ads' ),
					'section'     => 'custom_label',
					'key'         => 'label_text',
					'type'        => 'string',
					'default'     => esc_html__( 'Advertisement', 'newspack-ads' ),
				],
			],
			$settings_list
		);
	}

	/**
	 * Render custom label.
	 */
	public static function render_label() {
		$enabled    = Settings::get_setting( 'custom_label', 'active' );
		$label_text = Settings::get_setting( 'custom_label', 'label_text' );
		if ( true !== $enabled || empty( $label_text ) ) {
			return;
		}

		/**
		 * Filters the CSS selectors that contain an ad unit to be labeled.
		 * The ad should be a direct child of the element so the label does not 
		 * display in case there's no ad to render.
		 *
		 * @param string[] $selectors CSS selectors.
		 */
		$selectors = apply_filters(
			'newspack_ads_custom_label_container_selectors',
			[
				'.newspack_global_ad',
				'.widget.widget_newspack-ads-widget .textwidget',
			]
		);

		if ( empty( $selectors ) ) {
			return;
		}
		$selectors_str = implode(
			",\n",
			array_map(
				function( $selector ) {
					return sprintf( '%s > *::before', esc_html( $selector ) );
				},
				$selectors
			)
		);
		?>
		<style>
			<?php echo $selectors_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> {
				content: '<?php echo esc_html( $label_text ); ?>';
				display: block;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				font-size: 10px;
				line-height: 1.6;
				margin-bottom: 0.4em;
				opacity: 0.75;
				text-align: center;
			}
		</style>
		<?php
	}

}
Custom_Label::init();
