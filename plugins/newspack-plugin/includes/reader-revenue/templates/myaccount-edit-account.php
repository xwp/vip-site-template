<?php
/**
 * My Account Account Details page.
 * Based on woocommerce/templates/myaccount/form-edit-account.php.
 *
 * @package Newspack
 * @version 7.0.1
 */

namespace Newspack;

use Newspack\WooCommerce_My_Account;
use Newspack\Reader_Activation;

defined( 'ABSPATH' ) || exit;

\do_action( 'newspack_woocommerce_before_edit_account_form' );

$newspack_reset_password_arg = WooCommerce_My_Account::RESET_PASSWORD_URL_PARAM;
$newspack_delete_account_arg = WooCommerce_My_Account::DELETE_ACCOUNT_URL_PARAM;

$message = false;
if ( isset( $_GET['message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$message = $_GET['message']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

$is_error = false;
if ( isset( $_GET['is_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$is_error = $_GET['is_error']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

$without_password = true === Reader_Activation::is_reader_without_password( $user );
$is_reader        = true === Reader_Activation::is_user_reader( $user );
?>

<?php
if ( $message ) :
	?>
	<div class="newspack-wc-message <?php echo $is_error ? 'newspack-wc-message--error' : ''; ?>">
		<p><?php echo \esc_html( $message ); ?></p>
	</div>
	<?php
endif;
?>

<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php \do_action( 'newspack_woocommerce_edit_account_form_tag' ); ?> >

	<?php \do_action( 'newspack_woocommerce_edit_account_form_start' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide mt0">
		<label for="account_display_name"><?php \esc_html_e( 'Display name', 'newspack' ); ?>&nbsp;<span class="required">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" autocomplete="name" value="<?php echo \esc_attr( $user->display_name ); ?>" />
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_email_display"><?php \esc_html_e( 'Email address', 'newspack' ); ?>&nbsp;<span class="required">*</span></label>
		<input type="email" disabled class="woocommerce-Input woocommerce-Input--email input-text" name="account_email_display" id="account_email_display" autocomplete="email" value="<?php echo \esc_attr( $user->user_email ); ?>" />
		<input type="hidden" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo \esc_attr( $user->user_email ); ?>" />
	</p>

	<?php \do_action( 'newspack_woocommerce_edit_account_form' ); ?>

	<p class="woocommerce-buttons-card">
		<?php \wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<button type="submit" class="woocommerce-Button button ma0" name="save_account_details" value="<?php \esc_attr_e( 'Save changes', 'newspack' ); ?>"><?php \esc_html_e( 'Save changes', 'newspack' ); ?></button>
		<input type="hidden" name="action" value="save_account_details" />
	</p>

	<?php \do_action( 'newspack_woocommerce_edit_account_form_end' ); ?>
</form>

<hr class="is-style-wide" />

<div class="woocommerce-card woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
	<a href="<?php echo '?' . \esc_attr( $newspack_reset_password_arg ) . '=' . \esc_attr( \wp_create_nonce( $newspack_reset_password_arg ) ); ?>">
		<span class="woocommerce-card__content">
			<h4 class="woocommerce-card__title">
				<?php
				if ( $without_password ) {
					\esc_html_e( 'Create a Password', 'newspack' );
				} else {
					\esc_html_e( 'Reset Password', 'newspack' );
				}
				?>
			</h4>
			<span class="woocommerce-card__description">
				<?php
				if ( $without_password ) {
					\esc_html_e( 'Email me a link to set my password', 'newspack' );
				} else {
					\esc_html_e( 'Email me a password reset link', 'newspack' );
				}
				?>
			</span>
		</span>
	</a>
</div>

<?php
if ( $is_reader ) :
	?>

<div class="woocommerce-card woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
	<a href="<?php echo '?' . \esc_attr( $newspack_delete_account_arg ) . '=' . \esc_attr( \wp_create_nonce( $newspack_delete_account_arg ) ); ?>" class="is-destructive">
		<span class="woocommerce-card__content">
			<h4 class="woocommerce-card__title">
				<?php \esc_html_e( 'Delete Account', 'newspack' ); ?>
			</h4>
			<span class="woocommerce-card__description">
				<?php \esc_html_e( 'Request account deletion', 'newspack' ); ?>
			</span>
		</span>
	</a>
	<p>
		<?php \esc_html_e( 'Deleting your account will also cancel any newsletter subscriptions and recurring payments.', 'newspack' ); ?>
	</p>
</div>

<?php endif; ?>

<?php \do_action( 'newspack_woocommerce_after_edit_account_form' ); ?>
