<?php
/**
 * Newspack Ads Placement Customize Control.
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Placements;
use Newspack_Ads\Providers;

// Require WP_Customize_Control.
require_once ABSPATH . 'wp-includes/class-wp-customize-control.php';

/**
 * Newspack Ads Placement Customize Control Class.
 */
final class Placement_Customize_Control extends \WP_Customize_Control {

	/**
	 * Customize control type.
	 *
	 * @var string
	 */
	public $type = 'newspack_ads_placement';

	/**
	 * Availabe providers and its data.
	 *
	 * @var array[]
	 */
	private $providers = [];

	/**
	 * Enabled bidders.
	 *
	 * @var array[]
	 */
	private $bidders = [];

	/**
	 * Placement configuration.
	 *
	 * @var array
	 */
	private $placement = null;

	/**
	 * Constructor.
	 *
	 * @param WP_Customize_Manager $manager Customizer manager.
	 * @param string               $id      Control ID.
	 * @param array                $args    Control arguments.
	 */
	public function __construct( $manager, $id, $args = [] ) {
		// Available ad units.
		$this->providers = Providers::get_active_providers_data();
		$this->bidders   = \Newspack_Ads\get_bidders();

		// Placement configuration.
		if ( isset( $args['placement'] ) ) {
			$placements      = Placements::get_placements();
			$this->placement = $placements[ $args['placement'] ];
		} else {
			return new \WP_Error( 'newspack_ads_placement_customize_control_no_placement', __( 'No placement specified.', 'newspack-ads' ) );
		}

		parent::__construct( $manager, $id, $args );
	}

	/**
	 * To Json.
	 */
	public function to_json() {
		parent::to_json();

		$value = $this->value();
		if ( $value ) {
			if ( $this->placement['hook_name'] ) {
				$this->json['provider'] = $this->get_provider_value();
				$this->json['ad_unit']  = $this->get_ad_unit_value();
			}
			if ( isset( $this->placement['hooks'] ) && count( $this->placement['hooks'] ) ) {
				foreach ( array_keys( $this->placement['hooks'] ) as $hook_key ) {
					$this->json['hooks'][ $hook_key ]['provider'] = $this->get_provider_value( $hook_key );
					$this->json['hooks'][ $hook_key ]['ad_unit']  = $this->get_ad_unit_value( $hook_key );
				}
			}
		}
	}

	/**
	 * Get the value of the provider given its hook key.
	 *
	 * @param string $hook_key Optional hook key, will look root placement otherwise.
	 *
	 * @return string Provider ID or empty string.
	 */
	private function get_provider_value( $hook_key = '' ) {
		$value   = json_decode( $this->value(), true );
		$default = '';
		$data    = $value;
		if ( ! empty( $hook_key ) && isset( $value['hooks'], $value['hooks'][ $hook_key ] ) ) {
			$data = $value['hooks'][ $hook_key ];
		}
		return isset( $data['provider'] ) ? $data['provider'] : $default;
	}

	/**
	 * Get the value of an ad unit given its hook key.
	 *
	 * @param string $hook_key Optional hook key, will look root placement otherwise.
	 *
	 * @return string Ad unit ID or empty string if not found.
	 */
	private function get_ad_unit_value( $hook_key = '' ) {
		$value = json_decode( $this->value(), true );
		$data  = $value;
		if ( ! empty( $hook_key ) && isset( $value['hooks'], $value['hooks'][ $hook_key ] ) ) {
			$data = $value['hooks'][ $hook_key ];
		}
		return isset( $data['ad_unit'] ) ? $data['ad_unit'] : '';
	}

	/**
	 * Get the value of "stick to top" feature given its hook key.
	 *
	 * @param string $hook_key Optional hook key, will look root placement otherwise.
	 *
	 * @return string Ad unit ID or empty string if not found.
	 */
	private function get_stick_to_top_value( $hook_key = '' ) {
		$value = json_decode( $this->value(), true );
		$data  = $value;
		if ( ! empty( $hook_key ) && isset( $value['hooks'], $value['hooks'][ $hook_key ] ) ) {
			$data = $value['hooks'][ $hook_key ];
		}
		return isset( $data['stick_to_top'] ) ? (bool) $data['stick_to_top'] : false;
	}

	/**
	 * Get the value of a bidder given its hook key.
	 *
	 * @param string $bidder_id The bidder ID.
	 * @param string $hook_key  Optional hook key, will look root placement otherwise.
	 *
	 * @return string Ad unit ID or empty string if not found.
	 */
	private function get_bidder_value( $bidder_id, $hook_key = '' ) {
		$value = json_decode( $this->value(), true );
		$data  = $value;
		if ( ! empty( $hook_key ) && isset( $value['hooks'], $value['hooks'][ $hook_key ] ) ) {
			$data = $value['hooks'][ $hook_key ];
		}
		if ( ! isset( $data['bidders_ids'], $data['bidders_ids'][ $bidder_id ] ) ) {
			return '';
		}
		return $data['bidders_ids'][ $bidder_id ];
	}

	/**
	 * Get the element ID for form inputs.
	 *
	 * @param string   $name Name of the input.
	 * @param string[] $args Extra arguments to append to the ID.
	 *
	 * @return string The element ID.
	 */
	private function get_element_id( $name, $args = [] ) {
		return sprintf( '_customize-%s-%s-%s', $name, $this->id, implode( '-', $args ) );
	}

	/**
	 * Render the control's provider select given its value and hook key.
	 *
	 * @param string $value    The current value of the control.
	 * @param string $hook_key The hook key.
	 */
	private function render_provider_select( $value = '', $hook_key = '' ) {
		$id_args = [ 'provider' ];
		if ( $hook_key ) {
			$id_args[] = $hook_key;
		}
		$input_id       = $this->get_element_id( 'input', $id_args );
		$description_id = $this->get_element_id( 'description', $id_args );
		$label          = __( 'Provider', 'newspack-ads' );
		$description    = __( 'Select which provider to use for this placement.', 'newspack-ads' );
		?>
		<span class="customize-control provider-select">
			<label for="<?php echo esc_attr( $input_id ); ?>" class="customize-control-title"><?php echo esc_html( $label ); ?></label>
			<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description"><?php echo esc_html( $description ); ?></span>
			<select id="<?php echo esc_attr( $input_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>" data-hook="<?php echo esc_attr( $hook_key ); ?>">
				<option <?php selected( $value, '' ); ?> value=""><?php esc_html_e( '&mdash; Select a provider &mdash;', 'newspack-ads' ); ?></option>
				<?php
				foreach ( $this->providers as $provider ) {
						echo '<option value="' . esc_attr( $provider['id'] ) . '"' . selected( $value, $provider['id'], false ) . '>' . esc_html( $provider['name'] ) . '</option>';
				}
				?>
			</select>
		</span>
		<?php
	}

	/**
	 * Render the control's ad unit select given its provider, value and hook key.
	 *
	 * @param string  $provider The provider ID.
	 * @param array[] $ad_units The provider ad units to render.
	 * @param string  $value    The current value of the control.
	 * @param string  $hook_key The hook key.
	 */
	private function render_ad_unit_select( $provider, $ad_units, $value = '', $hook_key = '' ) {
		$id_args = [ $provider, 'ad-unit' ];
		if ( $hook_key ) {
			$id_args[] = $hook_key;
		}
		$input_id       = $this->get_element_id( 'input', $id_args );
		$description_id = $this->get_element_id( 'description', $id_args );
		$label          = __( 'Ad Unit', 'newspack-ads' );
		$description    = __( 'Select an ad unit to display in this placement.', 'newspack-ads' );
		?>
		<span class="customize-control ad-unit-select" data-provider="<?php echo esc_attr( $provider ); ?>">
			<label for="<?php echo esc_attr( $input_id ); ?>" class="customize-control-title"><?php echo esc_html( $label ); ?></label>
			<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description"><?php echo esc_html( $description ); ?></span>
			<select id="<?php echo esc_attr( $input_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>">
				<option <?php selected( $value, '' ); ?> value=""><?php esc_html_e( '&mdash; Select an ad unit &mdash;', 'newspack-ads' ); ?></option>
				<?php
				foreach ( $ad_units as $ad_unit ) {
						echo '<option value="' . esc_attr( $ad_unit['value'] ) . '"' . selected( $value, $ad_unit['value'], false ) . '>' . esc_html( $ad_unit['name'] ) . '</option>';
				}
				?>
			</select>
		</span>
		<?php
	}

	/**
	 * Render the control's ad unit select given its provider, value and hook key.
	 *
	 * @param string $bidder_id The bidder ID.
	 * @param string $value     The current value of the control.
	 * @param string $hook_key  The hook key.
	 */
	private function render_bidder_input( $bidder_id, $value, $hook_key = '' ) {
		$bidder  = $this->bidders[ $bidder_id ];
		$id_args = [ 'bidder', $bidder_id ];
		if ( $hook_key ) {
			$id_args[] = $hook_key;
		}
		$input_id       = $this->get_element_id( 'input', $id_args );
		$description_id = $this->get_element_id( 'description', $id_args );
		/* translators: %s is the bidder name */
		$label       = sprintf( __( '%s Placement ID', 'newspack-ads' ), $bidder['name'] );
		$description = __( 'Enter the bidder ID for this placement.', 'newspack-ads' );
		?>
		<span class="customize-control bidder-id-input" data-provider="gam">
			<label for="<?php echo esc_attr( $input_id ); ?>" class="customize-control-title"><?php echo esc_html( $label ); ?></label>
			<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description"><?php echo esc_html( $description ); ?></span>
			<input id="<?php echo esc_attr( $input_id ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" data-bidder-id="<?php echo esc_attr( $bidder_id ); ?>" />
		</span>
		<?php
	}

	/**
	 * Render the control's "stick to top" checkbox.
	 *
	 * @param bool   $value    The current value of the control.
	 * @param string $hook_key The hook key.
	 */
	private function render_stick_to_top_checkbox( $value = false, $hook_key = '' ) {
		$id_args = [ 'stick-to-top' ];
		if ( $hook_key ) {
			$id_args[] = $hook_key;
		}
		$input_id = $this->get_element_id( 'input', $id_args );
		$label    = __( 'Stick to top', 'newspack-ads' );
		?>
		<span class="customize-control stick-to-top-checkbox">
			<input id="<?php echo esc_attr( $input_id ); ?>" type="checkbox" value="1" <?php checked( $value, '1' ); ?> />
			<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
		</span>
		<?php
	}

	/**
	 * Render a placement hook control.
	 *
	 * @param string $hook_key Optional hook_key key, will treat as root placement otherwise.
	 */
	private function render_placement_hook_control( $hook_key = '' ) {
		?>
		<div class="placement-hook-control" data-hook="<?php echo esc_attr( $hook_key ); ?>">
			<?php
			if ( $hook_key ) :
				$hook_name = $this->placement['hooks'][ $hook_key ]['name'];
				?>
				<span class="customize-control-title"><?php echo esc_html( $hook_name ); ?></span>
				<?php
			endif;
			$this->render_provider_select( $this->get_provider_value( $hook_key ), $hook_key );
			foreach ( $this->providers as $provider ) {
				$this->render_ad_unit_select( $provider['id'], $provider['units'], $this->get_ad_unit_value( $hook_key ), $hook_key );
			}
			foreach ( array_keys( $this->bidders ) as $bidder_id ) {
				$this->render_bidder_input( $bidder_id, $this->get_bidder_value( $bidder_id, $hook_key ), $hook_key );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the control's content.
	 */
	public function render_content() {
		$value           = json_decode( $this->value(), true );
		$container_id    = $this->get_element_id( 'container' );
		$enabled         = $value && isset( $value['enabled'] ) && $value['enabled'];
		$enabled_id      = $this->get_element_id( 'input', [ 'enabled' ] );
		$enabled_desc_id = $this->get_element_id( 'description', [ 'enabled' ] );
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>">
			<span class="customize-control placement-toggle">
				<input
					id="<?php echo esc_attr( $enabled_id ); ?>"
					aria-describedby="<?php echo esc_attr( $enabled_desc_id ); ?>"
					type="checkbox"
					value="1"
					<?php checked( $enabled ); ?>
				/>
				<label for="<?php echo esc_attr( $enabled_id ); ?>"><?php esc_html_e( 'Enabled', 'newspack-ads' ); ?></label>
				<span id="<?php echo esc_attr( $enabled_desc_id ); ?>" class="description customize-control-description"><?php esc_html_e( 'Enable this placement', 'newspack-ads' ); ?></span>
			</span>
			<?php
			if ( isset( $this->placement['hook_name'] ) && $this->placement['hook_name'] ) {
				$this->render_placement_hook_control( '', $this->placement );
			}
			if ( isset( $this->placement['hooks'] ) && count( $this->placement['hooks'] ) ) {
				foreach ( array_keys( $this->placement['hooks'] ) as $hook_key ) {
					$this->render_placement_hook_control( $hook_key, $this->placement );
				}
			}
			if ( in_array( 'stick_to_top', $this->placement['supports'], true ) ) {
				$this->render_stick_to_top_checkbox( $this->get_stick_to_top_value() );
			}
			?>
		</div>
		<?php
	}
}
