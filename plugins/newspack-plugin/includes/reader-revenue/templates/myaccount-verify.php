<?php
/**
 * My Account page before account has been verified.
 * The user will be asked to verify before they can manage account settings.
 *
 * @package Newspack
 */

namespace Newspack;

use Newspack\WooCommerce_My_Account;

defined( 'ABSPATH' ) || exit;

\do_action( 'newspack_woocommerce_before_edit_account_form' );

$newspack_reset_password_arg  = WooCommerce_My_Account::RESET_PASSWORD_URL_PARAM;
$newspack_send_magic_link_arg = WooCommerce_My_Account::SEND_MAGIC_LINK_PARAM;

$message = false;
if ( isset( $_GET['message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$message = sanitize_text_field( $_GET['message'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$is_error = false;
if ( isset( $_GET['is_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$is_error = boolval( $_GET['is_error'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
?>

<?php
if ( $message ) :
	?>
	<div class="newspack-wc-message <?php echo $is_error ? 'newspack-wc-message--error' : ''; ?>">
		<p><?php echo \esc_html( $message ); ?></p>
	</div>
	<?php
endif;

$magic_link_args                                     = [];
$magic_link_args[ $newspack_send_magic_link_arg ]    = wp_create_nonce( $newspack_send_magic_link_arg );
$magic_link_url                                      = \add_query_arg(
	$magic_link_args,
	\wc_get_account_endpoint_url( 'edit-account' )
);
$reset_password_args                                 = [];
$reset_password_args[ $newspack_reset_password_arg ] = wp_create_nonce( $newspack_reset_password_arg );
$reset_password_url                                  = \add_query_arg(
	$reset_password_args,
	\wc_get_account_endpoint_url( 'edit-account' )
);
?>

<div class="newspack-verify-account-message">
	<p>
		<?php esc_html_e( 'You must verify your account before you can manage account settings. Verify with a link or by setting a password.', 'newspack' ); ?>
	</p>
	<p>
		<a class="woocommerce-Button button" href="<?php echo esc_url( $magic_link_url ); ?>">
			<?php esc_html_e( 'Send me a link', 'newspack' ); ?>
		</a>
		<a class="woocommerce-Button button" href="<?php echo esc_url( $reset_password_url ); ?>">
			<?php esc_html_e( 'Set a new password', 'newspack' ); ?>
		</a>
	</p>
</div>

<?php \do_action( 'newspack_woocommerce_after_edit_account_form' ); ?>
