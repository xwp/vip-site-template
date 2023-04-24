<?php
/**
 * My Account Payments.
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

?>

<?php
if ( $error_message ) :
	?>

	<div class="newspack-wc-message newspack-wc-message--error">
		<p><?php echo esc_html( $error_message ); ?></p>
	</div>

	<?php
else :
	?>
	<p><?php esc_html_e( 'Visit Stripe to change your payment method, cancel recurring payments, and view billing history.', 'newspack' ); ?></p>
	<a href="<?php echo esc_url( $stripe_billing_portal_url ); ?>" class="button">
		<?php echo esc_html__( 'Manage billing information', 'newspack' ); ?>
	</a>

	<?php
endif;
