<?php
/**
 * Server-side rendering of the `newspack-blocks/donate` block.
 *
 * @package WordPress
 */

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/frontend/class-newspack-blocks-donate-renderer-frequency-based.php';
require_once NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/frontend/class-newspack-blocks-donate-renderer-tiers-based.php';

/**
 * Server-side rendering of the `newspack-blocks/donate` block.
 */
class Newspack_Blocks_Donate_Renderer {
	/**
	 * Whether the modal checkout is used by donate any block.
	 *
	 * @var boolean
	 */
	private static $has_modal_checkout = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'woocommerce_checkout_fields' ] );
		if ( defined( 'NEWSPACK_EXPERIMENTAL_READER_ACTIVATION' ) && NEWSPACK_EXPERIMENTAL_READER_ACTIVATION ) {
			add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_modal_checkout_scripts' ] );
			add_action( 'wp_footer', [ __CLASS__, 'render_modal_checkout_markup' ] );
			add_action( 'template_include', [ __CLASS__, 'get_modal_checkout_template' ] );
			add_filter( 'wc_get_template', [ __CLASS__, 'wc_get_template' ], 10, 2 );
			add_filter( 'show_admin_bar', [ __CLASS__, 'show_admin_bar' ] );
			add_filter( 'woocommerce_checkout_get_value', [ __CLASS__, 'woocommerce_checkout_get_value' ], 10, 2 );
			add_filter( 'woocommerce_get_return_url', [ __CLASS__, 'woocommerce_get_return_url' ], 10, 2 );
		}
	}

	/**
	 * Get the keys of the billing fields to render for logged out users.
	 *
	 * @return array
	 */
	public static function get_billing_fields_keys() {
		$fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_email',
		];
		/**
		 * Filters the billing fields used on modal checkout.
		 *
		 * @param array $fields Billing fields.
		 */
		return apply_filters( 'newspack_blocks_donate_billing_fields_keys', $fields );
	}

	/**
	 * Modify fields for modal checkout.
	 *
	 * @param array $fields Checkout fields.
	 *
	 * @return array
	 */
	public static function woocommerce_checkout_fields( $fields ) {
		if ( ! class_exists( 'Newspack\Donations' ) || ! method_exists( 'Newspack\Donations', 'is_donation_cart' ) ) {
			return $fields;
		}
		if ( ! \Newspack\Donations::is_donation_cart() ) {
			return $fields;
		}
		$billing_fields = self::get_billing_fields_keys();
		if ( empty( $billing_fields ) ) {
			return $fields;
		}
		$billing_keys = array_keys( $fields['billing'] );
		foreach ( $billing_keys as $key ) {
			if ( in_array( $key, $billing_fields, true ) ) {
				continue;
			}
			unset( $fields['billing'][ $key ] );
		}
		return $fields;
	}

	/**
	 * Enqueue scripts for the checkout page rendered in a modal.
	 */
	public static function enqueue_modal_checkout_scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		self::enqueue_scripts( 'modal-checkout', [] );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @param string $handle_slug The slug of the script to enqueue.
	 * @param array  $dependencies The dependencies of the script to enqueue.
	 */
	private static function enqueue_scripts( $handle_slug, $dependencies = [ 'wp-i18n' ] ) {
		if ( 'streamlined' === $handle_slug ) {
			if ( method_exists( '\Newspack\Recaptcha', 'can_use_captcha' ) && \Newspack\Recaptcha::can_use_captcha() ) {
				$dependencies[] = \Newspack\Recaptcha::SCRIPT_HANDLE;
			}
		}

		$has_css = true;
		switch ( $handle_slug ) {
			case 'streamlined':
				$filename = 'donateStreamlined';
				break;
			case 'frequency-based':
				$filename = 'frequencyBased';
				break;
			case 'tiers-based':
				$filename = 'tiersBased';
				break;
			case 'modal-checkout':
				$filename = 'donateCheckoutModal';
				break;
			case 'modal-checkout-block':
				$filename = 'donateCheckoutBlock';
				$has_css  = false;
				break;
			default:
				$filename = false;
				break;
		}

		if ( false === $filename ) {
			return;
		}

		$handle      = Newspack_Blocks::SCRIPT_HANDLES[ $handle_slug ];
		$script_data = Newspack_Blocks::script_enqueue_helper( NEWSPACK_BLOCKS__BLOCKS_DIRECTORY . '/' . $filename . '.js' );
		wp_enqueue_script(
			$handle,
			$script_data['script_path'],
			$dependencies,
			NEWSPACK_BLOCKS__VERSION,
			true
		);
		wp_script_add_data( $handle, 'async', true );

		if ( $has_css ) {
			$style_path = NEWSPACK_BLOCKS__BLOCKS_DIRECTORY . $filename . ( is_rtl() ? '.rtl' : '' ) . '.css';
			wp_enqueue_style(
				$handle,
				plugins_url( $style_path, NEWSPACK_BLOCKS__PLUGIN_FILE ),
				[],
				NEWSPACK_BLOCKS__VERSION
			);
		}
	}

	/**
	 * Renders the `newspack-blocks/donate` block on server.
	 *
	 * @param array $attributes The block attributes.
	 *
	 * @return string
	 */
	public static function render( $attributes ) {
		if ( ! class_exists( 'Newspack\Donations' ) ) {
			return '';
		}

		$configuration = Newspack_Blocks_Donate_Renderer_Frequency_Based::get_configuration( $attributes );
		if ( \is_wp_error( $configuration ) ) {
			return '';
		}

		if ( $configuration['is_rendering_stripe_payment_form'] ) {
			self::enqueue_scripts( 'streamlined' );
		}

		Newspack_Blocks::enqueue_view_assets( 'donate' );
		wp_script_add_data( 'newspack-blocks-donate', 'async', true );
		wp_script_add_data( 'newspack-blocks-donate', 'amp-plus', true );

		if ( true === $attributes['useModalCheckout'] && ! $configuration['is_rendering_stripe_payment_form'] ) {
			self::enqueue_scripts( 'modal-checkout-block' );
			self::$has_modal_checkout = true;
		}

		if ( $configuration['is_tier_based_layout'] ) {
			self::enqueue_scripts( 'tiers-based' );
			return Newspack_Blocks_Donate_Renderer_Tiers_Based::render( $attributes );
		} else {
			self::enqueue_scripts( 'frequency-based' );
			return Newspack_Blocks_Donate_Renderer_Frequency_Based::render( $attributes );
		}
	}

	/**
	 * Render the markup necessary for the modal checkout.
	 */
	public static function render_modal_checkout_markup() {
		if ( ! self::$has_modal_checkout ) {
			return;
		}
		?>
		<div class="newspack-blocks-donate-checkout-modal" style="display: none;">
			<div class="newspack-blocks-donate-checkout-modal__content">
				<a href="#" class="newspack-blocks-donate-checkout-modal__close">
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'newspack-blocks' ); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
						<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
					</svg>
				</a>
				<div class="newspack-blocks-donate-checkout-modal__spinner">
					<span></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Return URL for modal checkout "thank you" page.
	 *
	 * @param string $url The URL to redirect to.
	 *
	 * @return string
	 */
	public static function woocommerce_get_return_url( $url ) {
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $url;
		}
		return add_query_arg(
			[
				'modal_checkout' => '1',
				'email'          => isset( $_REQUEST['billing_email'] ) ? rawurlencode( sanitize_email( wp_unslash( $_REQUEST['billing_email'] ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			],
			$url
		);
	}

	/**
	 * Disable admin bar for modal checkout.
	 *
	 * @param bool $show Whether to show the admin bar.
	 *
	 * @return bool
	 */
	public static function show_admin_bar( $show ) {
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $show;
		}
		return false;
	}

	/**
	 * Use stripped down template for modal checkout.
	 *
	 * @param string $template The template to render.
	 *
	 * @return string
	 */
	public static function get_modal_checkout_template( $template ) {
		if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'is_order_received_page' ) ) {
			return $template;
		}
		if ( ! is_checkout() && ! is_order_received_page() ) {
			return $template;
		}
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $template;
		}
		ob_start();
		wp_head();
		while ( have_posts() ) {
			the_post();
			echo '<div id="newspack_modal_checkout">';
			the_content();
			echo '</div>';
		}
		wp_footer();
		ob_end_flush();
	}

	/**
	 * Check the nonce for the edit billing request.
	 *
	 * @return bool
	 */
	private static function validate_edit_billing_request() {
		if ( ! isset( $_REQUEST['newspack_donate_edit_billing_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_REQUEST['newspack_donate_edit_billing_nonce'] ), 'newspack_donate_edit_billing' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		return true;
	}

	/**
	 * Get the prefilled values for billing fields.
	 *
	 * @return array
	 */
	public static function get_prefilled_fields() {
		$checkout        = WC()->checkout();
		$fields          = $checkout->get_checkout_fields( 'billing' );
		$customer        = new WC_Customer( get_current_user_id() );
		$customer_fields = $customer->get_billing();
		// If the user is logged in and there's no billing email, use the user's email.
		if ( is_user_logged_in() && empty( $customer_fields['email'] ) ) {
			$customer_fields['email'] = $customer->get_email();
		}
		$valid_request    = self::validate_edit_billing_request();
		$prefilled_fields = [];
		foreach ( $fields as $key => $field ) {
			$key = str_replace( 'billing_', '', $key );
			if ( $valid_request && isset( $_REQUEST[ 'billing_' . $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$value = sanitize_text_field( wp_unslash( $_REQUEST[ 'billing_' . $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $customer_fields[ $key ] ) ) {
				$value = $customer_fields[ $key ];
			}
			$prefilled_fields[ $key ] = $value;
		}
		return $prefilled_fields;
	}

	/**
	 * Whether the current checkout session has all required billing fields filled.
	 *
	 * @return bool
	 */
	public static function has_filled_required_fields() {
		$checkout        = WC()->checkout();
		$fields          = $checkout->get_checkout_fields( 'billing' );
		$required        = array_filter(
			$fields,
			function( $field ) {
				return isset( $field['required'] ) && $field['required'];
			}
		);
		$required_keys   = array_keys( $required );
		$customer_fields = self::get_prefilled_fields();
		$is_request      = self::validate_edit_billing_request();
		foreach ( $required_keys as $key ) {
			$key = str_replace( 'billing_', '', $key );
			if ( empty( $customer_fields[ $key ] ) ) {
				if ( $is_request ) {
					/* translators: %s: field name */
					wc_add_notice( sprintf( __( '%s is a required field.', 'newspack-blocks' ), $fields[ 'billing_' . $key ]['label'] ), 'error' );
				}
				return false;
			}
		}
		return true;
	}

	/**
	 * Modify WC checkout field value.
	 *
	 * @param null   $value Value.
	 * @param string $input Input name.
	 *
	 * @return string|null Value or null if unaltered.
	 */
	public static function woocommerce_checkout_get_value( $value, $input ) {
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $value;
		}
		$valid_request = self::validate_edit_billing_request(); // This performs nonce verification.
		if ( ! $valid_request ) {
			return $value;
		}
		if ( isset( $_REQUEST[ $input ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$value = sanitize_text_field( wp_unslash( $_REQUEST[ $input ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return $value;
	}

	/**
	 * Use modal checkout template when rendering the checkout form.
	 *
	 * @param string $located       Template file.
	 * @param string $template_name Template name.
	 *
	 * @return string Template file.
	 */
	public static function wc_get_template( $located, $template_name ) {
		if ( 'checkout/form-checkout.php' === $template_name && isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$located = NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/templates/checkout-form.php';
		}
		return $located;
	}
}
new Newspack_Blocks_Donate_Renderer();
