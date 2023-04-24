<?php
/**
 * Newspack Blocks.
 *
 * @package Newspack
 */

namespace Newspack\Blocks\ReaderRegistration;

use Newspack;
use Newspack\Reader_Activation;
use Newspack\Recaptcha;

defined( 'ABSPATH' ) || exit;

const FORM_ACTION = 'newspack_reader_registration';

/**
 * Register block from metadata.
 */
function register_block() {
	// Allow render_block callback to run so we can ensure it renders nothing.
	\register_block_type_from_metadata(
		__DIR__ . '/block.json',
		array(
			'render_callback' => __NAMESPACE__ . '\\render_block',
		)
	);

	// No need to register block styles if Reader Activation is disabled.
	if ( ! Reader_Activation::is_enabled() ) {
		return;
	}

	\register_block_style(
		'newspack/reader-registration',
		[
			'name'       => 'stacked',
			'label'      => __( 'Stacked', 'newspack' ),
			'is_default' => true,
		]
	);
	\register_block_style(
		'newspack/reader-registration',
		[
			'name'  => 'columns',
			'label' => __( 'Columns (newsletter subscription)', 'newspack' ),
		]
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block' );

/**
 * Enqueue front-end scripts.
 */
function enqueue_scripts() {
	// No need to enqueue scripts if Reader Activation is disabled.
	if ( ! Reader_Activation::is_enabled() ) {
		return;
	}

	$handle = 'newspack-reader-registration-block';
	\wp_enqueue_style(
		$handle,
		\Newspack\Newspack::plugin_url() . '/dist/reader-registration-block.css',
		[],
		NEWSPACK_PLUGIN_VERSION
	);
	\wp_enqueue_script(
		$handle,
		\Newspack\Newspack::plugin_url() . '/dist/reader-registration-block.js',
		[ 'wp-polyfill', 'newspack-reader-activation' ],
		NEWSPACK_PLUGIN_VERSION,
		true
	);
	\wp_script_add_data( $handle, 'async', true );
	\wp_script_add_data( $handle, 'amp-plus', true );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );

/**
 * Generate a unique ID for each registration form.
 *
 * The ID for each form instance is unique only for each page render.
 * The main intent is to be able to pass this ID to analytics so we
 * can identify what type of form it is, so the ID doesn't need to be
 * predictable nor consistent across page renders.
 *
 * @return string A unique ID string to identify the form.
 */
function get_form_id() {
	return \wp_unique_id( 'newspack-register-' );
}

/**
 * Render Registration Block.
 *
 * @param array[] $attrs Block attributes.
 * @param string  $content Block content (inner blocks) – success state in this case.
 */
function render_block( $attrs, $content ) {
	// Render nothing if Reader Activation is disabled.
	if ( ! Reader_Activation::is_enabled() ) {
		return '';
	}

	$registered      = false;
	$my_account_url  = function_exists( 'wc_get_account_endpoint_url' ) ? \wc_get_account_endpoint_url( 'dashboard' ) : false;
	$message         = '';
	$success_message = __( 'Thank you for registering!', 'newspack' ) . '<br />';

	if ( $my_account_url ) {
		$success_message .= sprintf(
			// Translators: %s is a link to My Account.
			__( 'Please visit %s to verify and manage your account.', 'newspack' ),
			'<a href="' . esc_url( $my_account_url ) . '">' . __( 'My Account', 'newspack' ) . '</a>'
		);
	}

	/** Handle default attributes. */
	$default_attrs = [
		'style'            => 'stacked',
		'label'            => __( 'Sign up', 'newspack' ),
		'newsletterLabel'  => __( 'Subscribe to our newsletter', 'newspack' ),
		'haveAccountLabel' => __( 'Already have an account?', 'newspack' ),
		'signInLabel'      => __( 'Sign in', 'newspack' ),
		'signedInLabel'    => __( 'An account was already registered with this email. Please check your inbox for an authentication link.', 'newspack' ),
	];
	$attrs         = \wp_parse_args( $attrs, $default_attrs );
	foreach ( $default_attrs as $key => $value ) {
		if ( empty( $attrs[ $key ] ) ) {
			$attrs[ $key ] = $value;
		}
	}

	$sign_in_url = \wp_login_url();
	if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
		$sign_in_url = $my_account_url;
	}

	/** Setup list subscription */
	if ( $attrs['newsletterSubscription'] && method_exists( 'Newspack_Newsletters_Subscription', 'get_lists_config' ) ) {
		$list_config = \Newspack_Newsletters_Subscription::get_lists_config();
		if ( ! \is_wp_error( $list_config ) ) {
			$lists = array_intersect_key( $list_config, array_flip( $attrs['lists'] ) );
		}
	}

	$is_admin_preview = method_exists( 'Newspack_Popups', 'is_user_admin' ) && \Newspack_Popups::is_user_admin();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if (
		! \is_preview() &&
		! $is_admin_preview &&
		( ! method_exists( 'Newspack_Popups', 'is_preview_request' ) || ! \Newspack_Popups::is_preview_request() ) &&
		(
			\is_user_logged_in() ||
			( isset( $_GET['newspack_reader'] ) && absint( $_GET['newspack_reader'] ) )
		)
	) {
		$registered = true;
		$message    = $success_message;
	}
	if ( isset( $_GET['newspack_reader'] ) && isset( $_GET['message'] ) ) {
		$message = \sanitize_text_field( $_GET['message'] );
	}
	// phpcs:enable

	$success_registration_markup = $content;
	if ( empty( \wp_strip_all_tags( $content ) ) ) {
		$success_registration_markup = '<p class="has-text-align-center">' . $success_message . '</p>';
	}

	$success_login_markup = $attrs['signedInLabel'];
	if ( ! empty( \wp_strip_all_tags( $attrs['signedInLabel'] ) ) ) {
		$success_login_markup = '<p class="has-text-align-center">' . $attrs['signedInLabel'] . '</p>';
	}

	ob_start();
	?>
	<div class="newspack-registration <?php echo esc_attr( get_block_classes( $attrs ) ); ?>">
		<?php if ( $registered ) : ?>
			<div class="newspack-registration__registration-success">
				<div class="newspack-registration__icon"></div>
				<?php echo $success_registration_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php else : ?>
			<form id="<?php echo esc_attr( get_form_id() ); ?>">
				<div class="newspack-registration__have-account">
					<?php echo \wp_kses_post( $attrs['haveAccountLabel'] ); ?>
					<a href="<?php echo \esc_url( $sign_in_url ); ?>" data-newspack-reader-account-link>
						<?php echo \wp_kses_post( $attrs['signInLabel'] ); ?>
					</a>
				</div>
				<div class="newspack-registration__header">
					<?php if ( ! empty( $attrs['title'] ) ) : ?>
						<h2 class="newspack-registration__title"><?php echo \wp_kses_post( $attrs['title'] ); ?></h2>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $attrs['description'] ) ) : ?>
					<p class="newspack-registration__description"><?php echo \wp_kses_post( $attrs['description'] ); ?></p>
				<?php endif; ?>
				<?php \wp_nonce_field( FORM_ACTION, FORM_ACTION ); ?>
				<?php
				/**
				 * Action to add custom fields before the form fields of the registration block.
				 *
				 * @param array $attrs Block attributes.
				 */
				do_action( 'newspack_registration_before_form_fields', $attrs );
				?>
				<div class="newspack-registration__form-content">
					<?php
					if ( ! empty( $lists ) ) {
						if ( 1 === count( $lists ) && $attrs['hideSubscriptionInput'] ) {
							?>
							<input
							<?php
							if ( $is_admin_preview ) :
								?>
								disabled
								<?php endif; ?>
								type="hidden"
								name="lists[]"
								value="<?php echo \esc_attr( key( $lists ) ); ?>"
							/>
							<?php
						} else {
							Reader_Activation::render_subscription_lists_inputs(
								$lists,
								array_keys( $lists ),
								[
									'title'            => $attrs['newsletterTitle'],
									'single_label'     => $attrs['newsletterLabel'],
									'show_description' => $attrs['displayListDescription'],
								]
							);
						}
					}
					?>
					<div class="newspack-registration__main">
						<div>
							<div class="newspack-registration__inputs">
								<input
								<?php
								if ( $is_admin_preview ) :
									?>
									disabled
									<?php endif; ?>
									type="email" name="npe" autocomplete="email"
									placeholder="<?php echo \esc_attr( $attrs['placeholder'] ); ?>"
								/>
								<?php Reader_Activation::render_honeypot_field( $attrs['placeholder'] ); ?>
								<input
								<?php
								if ( $is_admin_preview ) :
									?>
									disabled
									<?php endif; ?>
									type="submit"
									value="<?php echo \esc_attr( $attrs['label'] ); ?>"
								/>
							</div>
							<?php Reader_Activation::render_third_party_auth(); ?>
							<div class="newspack-registration__response <?php echo ( empty( $message ) ) ? 'newspack-registration--hidden' : null; ?>">
								<?php if ( ! empty( $message ) ) : ?>
									<p><?php echo \esc_html( $message ); ?></p>
								<?php endif; ?>
							</div>
						</div>

						<div class="newspack-registration__help-text">
							<p>
								<?php
								$terms_url = wp_http_validate_url( Reader_Activation::get_setting( 'terms_url' ) );
								if ( $terms_url ) :
									?>
									<a href="<?php echo esc_url( $terms_url ); ?>">
									<?php
								endif;
								$terms_text = empty( $attrs['privacyLabel'] ) ? Reader_Activation::get_setting( 'terms_text' ) : $attrs['privacyLabel'];
								echo \wp_kses_post( $terms_text );
								?>
								<?php if ( $terms_url ) : ?>
								</a>
								<?php endif; ?>
							</p>
						</div>
					</div>
				</div>
			</form>
			<div class="newspack-registration__registration-success newspack-registration--hidden">
				<div class="newspack-registration__icon"></div>
				<?php echo $success_registration_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="newspack-registration__login-success newspack-registration--hidden">
				<div class="newspack-registration__icon"></div>
				<?php echo \wp_kses_post( $success_login_markup ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
		// Including a dummy element with used classes to prevent AMP stripping them.
	?>
	<div class="newspack-registration--in-progress newspack-registration--error newspack-registration--success"></div>
	<?php
	return ob_get_clean();
}

/**
 * Utility to assemble the class for a server-side rendered block.
 *
 * @param array $attrs Block attributes.
 *
 * @return string Class list separated by spaces.
 */
function get_block_classes( $attrs = [] ) {
	$classes = [];
	if ( isset( $attrs['align'] ) && ! empty( $attrs['align'] ) ) {
		$classes[] = 'align' . $attrs['align'];
	}
	if ( isset( $attrs['className'] ) ) {
		array_push( $classes, $attrs['className'] );
	}
	return implode( ' ', $classes );
}

/**
 * Send the form response to the client, whether it's a JSON or GET request.
 *
 * @param mixed  $data    The response to send to the client.
 * @param string $message Optional custom message.
 */
function send_form_response( $data, $message = '' ) {
	$is_error = \is_wp_error( $data );
	if ( empty( $message ) ) {
		$message = $is_error ? $data->get_error_message() : __( 'Thank you for registering!', 'newspack' );
	}
	if ( \wp_is_json_request() ) {
		\wp_send_json( compact( 'message', 'data' ), \is_wp_error( $data ) ? 400 : 200 );
		exit;
	} elseif ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
		$args_to_remove   = [
			'_wp_http_referer',
			FORM_ACTION,
		];
		$is_existing_user = 0;
		if ( ! $is_error ) {
			$args_to_remove   = array_merge( $args_to_remove, [ 'email', 'lists' ] );
			$is_existing_user = isset( $data['existing_user'] ) && boolval( $data['existing_user'] ) ? 1 : 0;
		}
		\wp_safe_redirect(
			\add_query_arg(
				[
					'newspack_reader' => $is_error ? '0' : '1',
					'message'         => $message,
					'existing_user'   => $is_existing_user,
				],
				\remove_query_arg( $args_to_remove )
			)
		);
		exit;
	}
}

/**
 * Process registration form.
 */
function process_form() {
	// No need to process form values if Reader Activation is disabled.
	if ( ! Reader_Activation::is_enabled() ) {
		return;
	}

	// No need to proceed if we don't have the required params.
	if ( ! isset( $_REQUEST[ FORM_ACTION ] ) || ! \wp_verify_nonce( \sanitize_text_field( $_REQUEST[ FORM_ACTION ] ), FORM_ACTION ) ) {
		return;
	}

	// Honeypot trap.
	if ( ! empty( $_REQUEST['email'] ) ) {
		return send_form_response(
			[
				'email'         => \sanitize_email( $_REQUEST['email'] ),
				'authenticated' => true,
				'existing_user' => false,
			]
		);
	}

	// reCAPTCHA test.
	if ( Recaptcha::can_use_captcha() ) {
		$captcha_token  = isset( $_REQUEST['captcha_token'] ) ? \sanitize_text_field( $_REQUEST['captcha_token'] ) : '';
		$captcha_result = Recaptcha::verify_captcha( $captcha_token );
		if ( \is_wp_error( $captcha_result ) ) {
			return send_form_response( $captcha_result );
		}
	}

	// Note that that the "true" email address field is called `npe` due to the honeypot strategy.
	// The honeypot field is called `email` to hopefully capture bots that might be looking for such a field.
	$email = isset( $_REQUEST['npe'] ) ? \sanitize_email( $_REQUEST['npe'] ) : '';
	if ( empty( $email ) ) {
		return send_form_response( new \WP_Error( 'invalid_email', __( 'You must enter a valid email address.', 'newspack' ) ) );
	}

	$metadata = [];
	$lists    = array_map( 'sanitize_text_field', isset( $_REQUEST['lists'] ) ? $_REQUEST['lists'] : [] );
	if ( ! empty( $lists ) ) {
		$metadata['lists'] = $lists;
	}
	$metadata['referer']             = \wp_get_raw_referer(); // wp_get_referer() will return false because it's a POST request to the same page.
	$metadata['current_page_url']    = home_url( add_query_arg( array(), $metadata['referer'] ) );
	$metadata['registration_method'] = 'registration-block';

	$popup_id                      = isset( $_REQUEST['newspack_popup_id'] ) ? (int) $_REQUEST['newspack_popup_id'] : false;
	$metadata['newspack_popup_id'] = $popup_id;
	if ( $popup_id ) {
		$metadata['registration_method'] = 'registration-block-popup';
	}

	$user_id = Reader_Activation::register_reader( $email, '', true, $metadata );

	/**
	 * Fires after a reader is registered through the Reader Registration Block.
	 *
	 * @param string              $email   Email address of the reader.
	 * @param int|false|\WP_Error $user_id The created user ID in case of registration, false if not created or a WP_Error object.
	 * @param array               $metadata Array with metadata about the user being registered.
	 */
	\do_action( 'newspack_reader_registration_form_processed', $email, $user_id, $metadata );

	if ( \is_wp_error( $user_id ) ) {
		return send_form_response( $user_id );
	}

	$user_logged_in = false !== $user_id;

	return send_form_response(
		[
			'email'         => $email,
			'authenticated' => $user_logged_in,
			'existing_user' => ! $user_logged_in,
		]
	);
}
add_action( 'template_redirect', __NAMESPACE__ . '\\process_form' );
