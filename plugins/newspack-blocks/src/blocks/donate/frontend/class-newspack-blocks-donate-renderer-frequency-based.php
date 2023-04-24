<?php
/**
 * Renders the frequency-based Donate block.
 *
 * @package WordPress
 */

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/frontend/class-newspack-blocks-donate-renderer-base.php';

/**
 * Renders the frequency-based Donate block.
 */
class Newspack_Blocks_Donate_Renderer_Frequency_Based extends Newspack_Blocks_Donate_Renderer_Base {
	/**
	 * Renders the frequency selection of the donation form.
	 *
	 * @param string $frequency_slug Frequency slug.
	 * @param string $frequency_name Frequency name.
	 * @param number $uid Unique ID.
	 * @param array  $configuration The donations settings.
	 *
	 * @return string
	 */
	private static function render_frequency_selection( $frequency_slug, $frequency_name, $uid, $configuration ) {
		ob_start();
		?>
		<input
			type='radio'
			value='<?php echo esc_attr( $frequency_slug ); ?>'
			id='newspack-donate-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>'
			name="<?php echo esc_attr( self::FREQUENCY_PARAM ); ?>"
			<?php
			checked(
				$configuration['defaultFrequency'],
				$frequency_slug
			);
			?>
		/>
		<label
			for='newspack-donate-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>'
			class='wpbnbd__button freq-label'
		>
			<?php echo esc_html( $frequency_name ); ?>
		</label>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the footer of the donation form.
	 *
	 * @param array $attributes The block attributes.
	 *
	 * @return string
	 */
	private static function render_footer( $attributes ) {
		$configuration     = self::get_configuration( $attributes );
		$campaign          = $attributes['campaign'] ?? false;
		$button_style_attr = 'style="' . self::get_button_style( $attributes ) . '"';

		ob_start();
		?>
		<p class='wp-block-newspack-blocks-donate__thanks thanks'>
			<?php echo wp_kses_post( $attributes['thanksText'] ); ?>
		</p>

		<?php if ( $configuration['is_rendering_stripe_payment_form'] ) : ?>
			<?php echo self::render_streamlined_payment_ui( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<button type='submit' <?php echo $button_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo wp_kses_post( $attributes['buttonText'] ); ?>
			</button>
		<?php endif; ?>
		<?php if ( $campaign ) : ?>
			<input type='hidden' name='campaign' value='<?php echo esc_attr( $campaign ); ?>' />
		<?php endif; ?>
		<?php echo self::render_client_id_form_input(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render the frequency-based layout.
	 *
	 * @param array $attributes Block attributes.
	 */
	public static function render( $attributes ) {
		$uid = wp_rand( 10000, 99999 ); // Unique identifier to prevent labels colliding with other instances of Donate block.

		$configuration = self::get_configuration( $attributes );

		ob_start();

		/**
		 * For AMP-compatibility, the donation forms are implemented as pure HTML forms (no JS).
		 * Each frequency and tier option is a radio input, styled to look like a button.
		 * As the radio inputs are checked/unchecked, fields are hidden/displayed using only CSS.
		 */
		if ( ! $configuration['tiered'] ) :
			?>
		<div
			class="untiered <?php echo esc_html( $configuration['container_classnames'] ); ?>"
			id="<?php echo esc_html( $configuration['uid'] ); ?>"
		>
			<form data-streamlined-config="<?php echo esc_html( htmlspecialchars( wp_json_encode( $configuration['configuration_for_streamlined'] ), ENT_QUOTES, 'UTF-8' ) ); ?>">
				<?php echo self::render_donate_form_input(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class='wp-block-newspack-blocks-donate__options'>
					<div class='wp-block-newspack-blocks-donate__frequencies frequencies'>
						<?php foreach ( $configuration['frequencies'] as $frequency_slug => $frequency_name ) : ?>
							<?php
								$formatted_amount = $configuration['amounts'][ $frequency_slug ][3];
							?>

							<div class='wp-block-newspack-blocks-donate__frequency frequency'>
								<?php echo self::render_frequency_selection( $frequency_slug, $frequency_name, $uid, $configuration ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div class='input-container'>
									<label
										class='donate-label'
										for='newspack-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-untiered-input'
									>
										<?php echo esc_html__( 'Donation amount', 'newspack-blocks' ); ?>
									</label>
									<div class='wp-block-newspack-blocks-donate__money-input money-input'>
										<span class='currency'>
											<?php echo esc_html( $configuration['currencySymbol'] ); ?>
										</span>
										<input
											type='number'
											min='<?php echo esc_attr( $configuration['minimumDonation'] ); ?>'
											name='donation_value_<?php echo esc_attr( $frequency_slug ); ?>_untiered'
											value='<?php echo esc_attr( $formatted_amount ); ?>'
											id='newspack-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-untiered-input'
										/>
									</div>
								</div>
							</div>

						<?php endforeach; ?>
					</div>
				</div>
					<?php echo self::render_footer( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</form>
		</div>

			<?php
			else :
				$suggested_amounts = $configuration['amounts'];
				?>

		<div
			class="tiered <?php echo esc_html( $configuration['container_classnames'] ); ?>"
			id="<?php echo esc_html( $configuration['uid'] ); ?>"
		>
			<form data-streamlined-config="<?php echo esc_html( htmlspecialchars( wp_json_encode( $configuration['configuration_for_streamlined'] ), ENT_QUOTES, 'UTF-8' ) ); ?>">
				<?php echo self::render_donate_form_input(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class='wp-block-newspack-blocks-donate__options'>
					<div class='wp-block-newspack-blocks-donate__frequencies frequencies'>
						<?php foreach ( $configuration['frequencies'] as $frequency_slug => $frequency_name ) : ?>

							<div class='wp-block-newspack-blocks-donate__frequency frequency'>
								<?php echo self::render_frequency_selection( $frequency_slug, $frequency_name, $uid, $configuration ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div class='wp-block-newspack-blocks-donate__tiers tiers'>
									<?php foreach ( $suggested_amounts[ $frequency_slug ] as $index => $amount ) : ?>
										<div class='wp-block-newspack-blocks-donate__tier'>
											<?php
											if ( 3 === $index ) : // The "other" tier.
												?>
												<input
													type='radio'
													class='other-input'
													name='donation_value_<?php echo esc_attr( $frequency_slug ); ?>'
													value='other'
													id='newspack-tier-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-other'
												/>
												<label
													class='tier-select-label tier-label'
													for='newspack-tier-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-other'
												>
												<?php echo esc_html__( 'Other', 'newspack-blocks' ); ?>
												</label>
												<label
													class='odl'
													for='newspack-tier-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-other-input'
												>
												<?php echo esc_html__( 'Donation amount', 'newspack-blocks' ); ?>
												</label>
												<div class='wp-block-newspack-blocks-donate__money-input money-input'>
													<span class='currency'>
													<?php echo esc_html( $configuration['currencySymbol'] ); ?>
													</span>
													<input
														type='number'
														min='<?php echo esc_attr( $configuration['minimumDonation'] ); ?>'
														name='donation_value_<?php echo esc_attr( $frequency_slug ); ?>_other'
														value='<?php echo esc_attr( $amount ); ?>'
														id='newspack-tier-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-other-input'
													/>
												</div>
												<?php
												else :
													?>
												<input
													type='radio'
													name='donation_value_<?php echo esc_attr( $frequency_slug ); ?>'
													value='<?php echo esc_attr( $amount ); ?>'
													id='newspack-tier-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-<?php echo (int) $index; ?>'
													<?php checked( 1, $index ); ?>
												/>
												<label
													class='tier-select-label tier-label'
													for='newspack-tier-<?php echo esc_attr( $frequency_slug . '-' . $uid ); ?>-<?php echo (int) $index; ?>'
												>
													<?php echo esc_html( $configuration['currencySymbol'] . $amount ); ?>
												</label>
													<?php
												endif;
												?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>

						<?php endforeach; ?>
					</div>
				</div>
					<?php echo self::render_footer( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</form>
		</div>
				<?php
		endif;

			return apply_filters( 'newspack_blocks_donate_block_html', ob_get_clean(), $attributes );
	}
}
