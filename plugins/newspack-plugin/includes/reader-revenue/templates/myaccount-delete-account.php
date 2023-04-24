<?php
/**
 * My Account page for reader account deletion.
 *
 * @package Newspack
 */

namespace Newspack;

use Newspack\WooCommerce_My_Account;

defined( 'ABSPATH' ) || exit;

$delete_account_form = WooCommerce_My_Account::DELETE_ACCOUNT_FORM;

\do_action( 'newspack_woocommerce_before_edit_account_form' );

$nonce_value = isset( $_GET[ $delete_account_form ] ) ? \sanitize_text_field( $_GET[ $delete_account_form ] ) : '';
if ( ! \wp_verify_nonce( $nonce_value, $delete_account_form ) ) {
	\wc_add_notice( __( 'Invalid nonce.', 'newspack' ), 'error' );
	return;
}

if ( ! isset( $_GET['token'] ) ) {
	\wc_add_notice( __( 'Invalid token', 'newspack' ), 'error' );
	return;
}

$token           = \sanitize_text_field( $_GET['token'] );
$transient_token = get_transient( 'np_reader_account_delete_' . \get_current_user_id() );
if ( ! $transient_token || $transient_token !== $token ) {
	\wc_add_notice( __( 'Invalid token', 'newspack' ), 'error' );
	return;
}
?>

<div class="newspack-verify-account-message">
	<p>
		<?php esc_html_e( 'Confirm to delete your account permanently.', 'newspack' ); ?>
	</p>
	<p>
		<?php \esc_html_e( 'Deleting your account will also cancel any newsletter subscriptions and recurring payments.', 'newspack' ); ?>
	</p>
	<p>
		<strong><?php esc_html_e( 'Caution, this action is irreversible!', 'newspack' ); ?></strong>
	</p>
	<form method="POST">
		<input type="hidden" name="<?php echo \esc_attr( $delete_account_form ); ?>" value="<?php echo \esc_attr( $nonce_value ); ?>">
		<input type="hidden" name="token" value="<?php echo \esc_attr( $token ); ?>">
		<input type="hidden" name="confirm_delete" value="1" />
		<button type="submit" class="woocommerce-Button button">
			<?php esc_html_e( 'Delete Account', 'newspack' ); ?>
		</button>
	</form>
</div>

<?php \do_action( 'newspack_woocommerce_after_edit_account_form' ); ?>
