<?php
/**
 * Server-side rendering of the `newspack-blocks/donate` block.
 *
 * @package WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the Donate block rendering functionality.
 */
abstract class Newspack_Blocks_Donate_Renderer_Base {
	const FREQUENCY_PARAM   = 'donation_frequency';
	const TIER_PARAM_PREFIX = 'donation_value_';

	private static $configurations_cache = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	/**
	 * Get configuration, based on block attributes and global settings.
	 *
	 * @param array $attributes Block attributes.
	 */
	public static function get_configuration( $attributes ) {
		$attributes_hash = md5( wp_json_encode( $attributes ) );
		if ( isset( self::$configurations_cache[ $attributes_hash ] ) ) {
			return self::$configurations_cache[ $attributes_hash ];
		}
		$configuration = Newspack\Donations::get_donation_settings();
		if ( is_wp_error( $configuration ) ) {
			return new \WP_Error( 'newspack_blocks_donation', __( 'Could not retrieve donation settings.', 'newspack-blocks' ) );
		}

		$configuration['defaultFrequency'] = $attributes['defaultFrequency'];

		/* If block is in "manual" mode, override certain state properties with values stored in attributes */
		if ( $attributes['manual'] ?? false ) {
			// Migrate old attributes.
			if ( empty( $attributes['amounts'] ) && isset( $attributes['suggestedAmounts'] ) ) {
				$other_amount = $configuration['amounts']['month'][3];
				if ( isset( $attributes['suggestedAmountUntiered'] ) ) {
					$other_amount = $attributes['suggestedAmountUntiered'];
				}
				$suggested_amounts     = $attributes['suggestedAmounts'];
				$multiplied_amounts    = [
					$suggested_amounts[0] * 12,
					$suggested_amounts[1] * 12,
					$suggested_amounts[2] * 12,
					$other_amount * 12,
				];
				$attributes['amounts'] = [
					'once'  => $multiplied_amounts,
					'month' => [
						$suggested_amounts[0],
						$suggested_amounts[1],
						$suggested_amounts[2],
						$other_amount,
					],
					'year'  => $multiplied_amounts,
				];
			}
			if ( isset( $attributes['tiered'] ) ) {
				$configuration['tiered'] = $attributes['tiered'];
			}
			if ( isset( $attributes['amounts'] ) && ! empty( $attributes['amounts'] ) ) {
				$configuration['amounts'] = $attributes['amounts'];
			}

			if ( isset( $attributes['minimumDonation'] ) ) {
				$configuration['minimumDonation'] = $attributes['minimumDonation'];
			}

			// Override defaults with manual config.
			$configuration['defaultFrequency']    = $attributes['defaultFrequency'];
			$configuration['disabledFrequencies'] = $attributes['disabledFrequencies'];
		}

		// Ensure default frequency is valid (not disabled).
		foreach ( array_keys( $configuration['disabledFrequencies'] ) as $frequency_slug ) {
			if ( $configuration['defaultFrequency'] === $frequency_slug && $configuration['disabledFrequencies'][ $frequency_slug ] ) {
				$non_disabled_frequencies          = array_keys(
					array_filter(
						$configuration['disabledFrequencies'],
						function ( $item ) {
							return ! $item;
						}
					)
				);
				$configuration['defaultFrequency'] = $non_disabled_frequencies[0];
			}
		}

		$is_tiers_based = $configuration['tiered'] && 'tiers' === $attributes['layoutOption'];
		if ( ! Newspack_Blocks::can_render_tiers_based_layout() ) {
			$is_tiers_based = false;
		}
		$configuration['is_tier_based_layout'] = $is_tiers_based;

		$frequencies = [
			'once'  => __( 'One-time', 'newspack-blocks' ),
			'month' => __( 'Monthly', 'newspack-blocks' ),
			'year'  => __( 'Annually', 'newspack-blocks' ),
		];
		foreach ( array_keys( $frequencies ) as $frequency_slug ) {
			if ( $configuration['disabledFrequencies'][ $frequency_slug ] ) {
				unset( $frequencies[ $frequency_slug ] );
			}
			if ( $is_tiers_based && 'once' === $frequency_slug ) {
				unset( $frequencies[ $frequency_slug ] );
			}
		}
		$configuration['frequencies'] = $frequencies;

		/* If block has additional CSS class(es)  */
		if ( isset( $attributes['className'] ) ) {
			$classname = $attributes['className'];
		} else {
			$classname = 'is-style-default';
		}

		$layout_version                        = ( $is_tiers_based ? 'tiers' : 'frequency' );
		$container_classnames                  = implode(
			' ',
			[
				'wp-block-newspack-blocks-donate',
				'wpbnbd',
				'wpbnbd--' . $layout_version . '-based',
				'wpbnbd--platform-' . $configuration['platform'],
				$classname,
				'wpbnbd-frequencies--' . count( $configuration['frequencies'] ),
			]
		);
		$configuration['container_classnames'] = $container_classnames;

		$configuration['is_rendering_stripe_payment_form'] = false;

		if ( Newspack_Blocks::is_rendering_stripe_payment_form() ) {
			$stripe_data      = \Newspack\Stripe_Connection::get_stripe_data();
			$currency         = $stripe_data['currency'];
			$captcha_site_key = null;

			if ( method_exists( '\Newspack\Recaptcha', 'can_use_captcha' ) && \Newspack\Recaptcha::can_use_captcha() ) {
				$captcha_site_key = \Newspack\Recaptcha::get_setting( 'site_key' );
			}

			$configuration_for_streamlined                     = [
				$currency,
				$configuration['currencySymbol'],
				get_bloginfo( 'name' ),
				Newspack\Stripe_Connection::is_currency_zero_decimal( $currency ),
				$stripe_data['location_code'],
				$configuration['frequencies'],
				$stripe_data['fee_multiplier'],
				$stripe_data['fee_static'],
				$stripe_data['usedPublishableKey'],
				$attributes['paymentRequestType'],
				$captcha_site_key,
				$configuration['minimumDonation'],
			];
			$configuration['is_rendering_stripe_payment_form'] = true;
		} else {
			$configuration_for_streamlined = [];
		}
		$configuration['configuration_for_streamlined'] = $configuration_for_streamlined;

		if ( isset( $configuration['minimumDonation'] ) ) {
			foreach ( $configuration['amounts'] as $frequency => $amounts ) {
				foreach ( $amounts as $index => $amount ) {
					$configuration['amounts'][ $frequency ][ $index ] = max( $configuration['minimumDonation'], $amount );
				}
			}
		}

		$configuration['uid'] = uniqid();

		self::$configurations_cache[ $attributes_hash ] = $configuration;

		return $configuration;
	}

	/**
	 * Get style for a button.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $is_reverse_style Reverse background/foreground colors.
	 */
	protected static function get_button_style( $attributes, $is_reverse_style = false ) {
		$button_color      = $attributes['buttonColor'];
		$button_text_color = Newspack_Blocks::get_color_for_contrast( $button_color );
		if ( $is_reverse_style ) {
			return 'border-color: ' . esc_attr( $button_color ) . '; color: ' . esc_attr( $button_color ) . '; background: transparent;';
		} else {
			return 'border-color: ' . esc_attr( $button_color ) . '; background-color: ' . esc_attr( $button_color ) . '; color: ' . esc_attr( $button_text_color ) . ';';
		}
	}

	/**
	 * Render hidden form input indentifying a donate form submission.
	 *
	 * @param array $attributes The block attributes.
	 *
	 * @return string
	 */
	protected static function render_streamlined_payment_ui( $attributes ) {
		$is_rendering_newsletter_list_opt_in = false;
		$user_email                          = '';
		$user_display_name                   = '';

		$stripe_data               = \Newspack\Stripe_Connection::get_stripe_data();
		$is_rendering_fee_checkbox = 0 < (float) $stripe_data['fee_multiplier'] + (float) $stripe_data['fee_static'];
		if ( class_exists( 'Newspack_Newsletters' ) ) {
			$is_rendering_newsletter_list_opt_in = isset( $stripe_data['newsletter_list_id'] ) && ! empty( $stripe_data['newsletter_list_id'] );
		}

		$current_user = wp_get_current_user();
		if ( 0 !== $current_user->ID ) {
			$user_email        = $current_user->user_email;
			$user_display_name = trim( $current_user->first_name . ' ' . $current_user->last_name );
		}

		$button_style_attr = 'style="' . self::get_button_style( $attributes ) . '"';

		ob_start();
		?>
			<div class="wp-block-newspack-blocks-donate__stripe stripe-payment stripe-payment--invisible stripe-payment--disabled">
				<div class="stripe-payment__inputs stripe-payment--hidden">
					<div class="stripe-payment__row">
						<div class="stripe-payment__element stripe-payment__card"></div>
					</div>
					<div class="stripe-payment__row stripe-payment__row--flex">
						<input
							required
							placeholder="<?php echo esc_html__( 'Email', 'newspack-blocks' ); ?>"
							type="email"
							name="email"
							value="<?php echo esc_attr( $user_email ); ?>"
							<?php if ( '' !== $user_email ) : ?>
								readonly
							<?php endif; ?>
						>
						<input
							required
							placeholder="<?php echo esc_html__( 'Full Name', 'newspack-blocks' ); ?>"
							type="text"
							name="full_name"
							value="<?php echo esc_attr( $user_display_name ); ?>"
						>
					</div>
					<div class="stripe-payment__row stripe-payment__row--additional-fields">
						<?php foreach ( $attributes['additionalFields'] as $field ) : ?>
							<input
								data-is-additional-field
								type="<?php echo esc_attr( $field['type'] ); ?>"
								name="<?php echo esc_attr( $field['name'] ); ?>"
								placeholder="<?php echo esc_attr( $field['label'] ); ?>"
								style="width: calc(<?php echo esc_attr( $field['width'] ); ?>% - 0.5rem);"
								<?php if ( $field['isRequired'] ) : ?>
									required
								<?php endif; ?>
							>
						<?php endforeach; ?>
					</div>
				</div>
				<?php if ( $is_rendering_fee_checkbox ) : ?>
					<div class="stripe-payment__row stripe-payment__row--small" id="stripe-fees-amount-container">
						<label class="stripe-payment__checkbox">
							<input type="checkbox" name="agree_to_pay_fees" checked value="true"><?php echo esc_html__( 'Agree to pay fees?', 'newspack-blocks' ); ?>
							<span id="stripe-fees-amount">($0)</span>
						</label>
						<div class="stripe-payment__info"><?php echo esc_html__( 'Paying the transaction fee is not required, but it directs more money in support of our mission.', 'newspack-blocks' ); ?></div>
					</div>
				<?php endif; ?>
				<?php if ( $is_rendering_newsletter_list_opt_in ) : ?>
					<div class="stripe-payment__row stripe-payment__row--small">
						<label class="stripe-payment__checkbox">
							<input type="checkbox" name="newsletter_opt_in" checked value="true"><?php echo esc_html__( 'Sign up for our newsletter', 'newspack-blocks' ); ?>
						</label>
					</div>
				<?php endif; ?>
				<div class="stripe-payment__messages">
					<div class="type-error"></div>
					<div class="type-success"></div>
					<div class="type-info"></div>
				</div>
				<div class="stripe-payment__row stripe-payment__row--flex stripe-payment__footer">
					<div class="stripe-payment__methods">
						<div class="stripe-payment__request-button stripe-payment--hidden stripe-payment__request-button--invisible stripe-payment--transition"></div>
						<button type='submit' <?php echo $button_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo esc_html( $attributes['buttonWithCCText'] ); ?>
						</button>
					</div>
				</div>
			</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render hidden form input indentifying a donate form submission.
	 */
	protected static function render_donate_form_input() {
		ob_start();
		/**
		 * Action to add custom fields before the form fields of the donation block.
		 */
		do_action( 'newspack_blocks_donate_before_form_fields' );
		wp_referer_field();
		?>
			<input type='hidden' name='newspack_donate' value='1' />
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Client ID hidden form input.
	 */
	protected static function render_client_id_form_input() {
		if ( class_exists( 'Newspack_Popups_Segmentation' ) ) {
			$client_id = Newspack_Popups_Segmentation::NEWSPACK_SEGMENTATION_CID_NAME;
			ob_start();
			?>
				<input
					name="cid"
					type="hidden"
					value="CLIENT_ID(<?php echo esc_attr( $client_id ); ?>)"
					data-amp-replace="CLIENT_ID"
				/>
			<?php
			return ob_get_clean();
		}
	}

	/**
	 * Render the block HTML.
	 *
	 * @param array $attributes Block attributes.
	 */
	abstract public static function render( $attributes );
}
