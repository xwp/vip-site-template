<?php
/**
 * Reader Activation related emails.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Reader Activation related emails.
 */
class Reader_Activation_Emails {
	const EMAIL_TYPES = [
		'VERIFICATION'   => 'reader-activation-verification',
		'MAGIC_LINK'     => 'reader-activation-magic-link',
		'OTP_AUTH'       => 'reader-activation-otp-authentication',
		'RESET_PASSWORD' => 'reader-activation-reset-password',
		'DELETE_ACCOUNT' => 'reader-activation-delete-account',
	];

	/**
	 * Initialize.
	 *
	 * @codeCoverageIgnore
	 */
	public static function init() {
		add_filter( 'newspack_email_configs', [ __CLASS__, 'add_email_configs' ] );

		// Disable the default WC password reset email and replace it with ours.
		add_filter( 'woocommerce_email_enabled_customer_reset_password', '__return_false' );
		add_action( 'woocommerce_reset_password_notification', [ __CLASS__, 'send_reset_password_email' ], 10, 2 );
	}

	/**
	 * Register email type.
	 *
	 * @param array $configs Email types.
	 */
	public static function add_email_configs( $configs ) {
		$configs[ self::EMAIL_TYPES['VERIFICATION'] ]   = [
			'name'                   => self::EMAIL_TYPES['VERIFICATION'],
			'label'                  => __( 'Verification', 'newspack' ),
			'description'            => __( "Email sent to the reader after they've registered.", 'newspack' ),
			'template'               => dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/reader-activation-emails/verification.php',
			'editor_notice'          => __( 'This email will be sent to a reader after they\'ve registered.', 'newspack' ),
			'available_placeholders' => [
				[
					'label'    => __( 'the verification link', 'newspack' ),
					'template' => '*VERIFICATION_URL*',
				],
			],
		];
		$configs[ self::EMAIL_TYPES['MAGIC_LINK'] ]     = [
			'name'                   => self::EMAIL_TYPES['MAGIC_LINK'],
			'label'                  => __( 'Login link', 'newspack' ),
			'description'            => __( 'Email with a login link.', 'newspack' ),
			'template'               => dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/reader-activation-emails/magic-link.php',
			'editor_notice'          => __( 'This email will be sent to a reader when they request a login link.', 'newspack' ),
			'available_placeholders' => [
				[
					'label'    => __( 'the one-time password', 'newspack' ),
					'template' => '*MAGIC_LINK_OTP*',
				],
			],
		];
		$configs[ self::EMAIL_TYPES['OTP_AUTH'] ]       = [
			'name'                   => self::EMAIL_TYPES['OTP_AUTH'],
			'label'                  => __( 'Login one-time password', 'newspack' ),
			'description'            => __( 'Email with a one-time password and login link.', 'newspack' ),
			'template'               => dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/reader-activation-emails/otp.php',
			'editor_notice'          => __( 'This email will be sent to a reader when they request a login link and a one-time password is available.', 'newspack' ),
			'available_placeholders' => [
				[
					'label'    => __( 'the one-time password', 'newspack' ),
					'template' => '*MAGIC_LINK_OTP*',
				],
				[
					'label'    => __( 'the login link', 'newspack' ),
					'template' => '*MAGIC_LINK_URL*',
				],
			],
		];
		$configs[ self::EMAIL_TYPES['RESET_PASSWORD'] ] = [
			'name'                   => self::EMAIL_TYPES['RESET_PASSWORD'],
			'label'                  => __( 'Set a New Password', 'newspack' ),
			'description'            => __( 'Email with password reset link.', 'newspack' ),
			'template'               => dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/reader-activation-emails/password-reset.php',
			'editor_notice'          => __( 'This email will be sent to a reader when they request a password creation or reset.', 'newspack' ),
			'available_placeholders' => [
				[
					'label'    => __( 'the password reset link', 'newspack' ),
					'template' => '*PASSWORD_RESET_LINK*',
				],
			],
		];
		$configs[ self::EMAIL_TYPES['DELETE_ACCOUNT'] ] = [
			'name'                   => self::EMAIL_TYPES['DELETE_ACCOUNT'],
			'label'                  => __( 'Delete Account', 'newspack' ),
			'description'            => __( 'Email with account deletion link.', 'newspack' ),
			'template'               => dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/reader-activation-emails/delete-account.php',
			'editor_notice'          => __( 'This email will be sent to a reader when they request an account deletion.', 'newspack' ),
			'available_placeholders' => [
				[
					'label'    => __( 'the account deletion link', 'newspack' ),
					'template' => '*DELETION_LINK*',
				],
			],
		];
		return $configs;
	}

	/**
	 * Send password reset email.
	 *
	 * @param string $user_login User login.
	 * @param string $key Password reset key.
	 */
	public static function send_reset_password_email( $user_login, $key ) {
		$user = get_user_by( 'login', $user_login );
		Emails::send_email(
			self::EMAIL_TYPES['RESET_PASSWORD'],
			$user->data->user_email,
			[
				[
					'template' => '*PASSWORD_RESET_LINK*',
					'value'    => Emails::get_password_reset_url( $user, $key ),
				],
			]
		);
	}
}
Reader_Activation_Emails::init();
