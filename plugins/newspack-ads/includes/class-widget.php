<?php
/**
 * Newspack Ads Widget management
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Core;
use Newspack_Ads\Providers;
use Newspack_Ads\Providers\GAM_Model;

/**
 * Newspack Ads Blocks Management
 */
final class Widget extends \WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'newspack-ads-widget',
			'Newspack Ad Unit'
		);
		add_action(
			'widgets_init',
			function() {
				register_widget( 'Newspack_Ads\Widget' );
			}
		);
	}

	/**
	 * Widget renderer.
	 *
	 * @param object $args Args.
	 * @param object $instance The Widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! newspack_ads_should_show_ads() || ! isset( $args['id'] ) || 0 === (int) $instance['selected_ad_unit'] ) {
			return;
		}
		if ( ! Providers::is_provider_active( 'gam' ) ) {
			return;
		}

		$selected_ad_unit = $instance['selected_ad_unit'];
		$ad_unit          = GAM_Model::get_ad_unit_for_display(
			$selected_ad_unit,
			array(
				'placement' => 'newspack_ads_widget',
				'context'   => $args['id'],
			) 
		);

		if ( \is_wp_error( $ad_unit ) ) {
			return;
		}
		if ( ! Core::is_amp() && isset( $instance['stick_to_top'] ) ) {
			$stick_to_top = $instance['stick_to_top'];
		} else {
			$stick_to_top = false;
		}

		$code = Core::is_amp() ? $ad_unit['amp_ad_code'] : $ad_unit['ad_code'];

		$before_widget = $args['before_widget'];
		if ( false !== $stick_to_top ) {
			$before_widget = str_replace( 'class="', 'class="stick-to-top ', $before_widget );
		}

		echo $before_widget; // phpcs:ignore
		echo '<div class="textwidget">';
		echo $code; // phpcs:ignore
		echo '</div>';
		echo $args['after_widget']; // phpcs:ignore
	}

	/**
	 * Widget form.
	 *
	 * @param object $instance The Widget instance.
	 */
	public function form( $instance ) {

		$instance = wp_parse_args( (array) $instance, [ 'selected_ad_unit' => 0 ] );

		$selected_ad_unit = $instance['selected_ad_unit'];
		if ( isset( $instance['stick_to_top'] ) ) {
			$stick_to_top = $instance['stick_to_top'];
		} else {
			$stick_to_top = false;
		}

		$ad_units = GAM_Model::get_ad_units();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'selected_ad_unit' ) ); ?>">
				<?php echo esc_html__( 'Ad Unit', 'newspack-ads' ); ?>
				<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'selected_ad_unit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'selected_ad_unit' ) ); ?>">
					<option value=0><?php echo esc_html( '---' ); ?></option>
					<?php foreach ( $ad_units as $ad_unit ) : ?>
						<option
							value="<?php echo esc_attr( $ad_unit['id'] ); ?>"
							<?php selected( $selected_ad_unit, $ad_unit['id'] ); ?>
							<?php echo esc_attr( 'INACTIVE' === $ad_unit['status'] ? 'disabled' : '' ); ?>
						>
							<?php echo esc_html( $ad_unit['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( Core::is_amp_plus_configured() ) : ?>
				<br/>
				<br/>
				<label for="<?php echo esc_attr( $this->get_field_id( 'stick_to_top' ) ); ?>">
					<?php echo esc_html__( 'Stick to top (only if the last widget in the sidebar)', 'newspack-ads' ); ?>
					<input
						type="checkbox"
						id="<?php echo esc_attr( $this->get_field_id( 'stick_to_top' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'stick_to_top' ) ); ?>"
						<?php checked( ! $stick_to_top, isset( $ad_unit['stick_to_top'] ) ? $ad_unit['stick_to_top'] : false ); ?>
					>
				</label>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Widget updater.
	 *
	 * @param object $new_instance New instance.
	 * @param object $old_instance Old instance.
	 */
	public function update( $new_instance, $old_instance ) {
		if ( ! isset( $new_instance['stick_to_top'] ) ) {
			$new_instance['stick_to_top'] = false;
		}
		return [
			'selected_ad_unit' => $new_instance['selected_ad_unit'],
			'stick_to_top'     => $new_instance['stick_to_top'],
		];
	}
}
$newspack_ads_widget = new Widget();
