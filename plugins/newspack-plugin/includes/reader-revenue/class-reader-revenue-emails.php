<?php
/**
 * Reader-revenue related emails.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Reader-revenue related emails.
 */
class Reader_Revenue_Emails {
	const EMAIL_TYPES = [
		'RECEIPT' => 'receipt',
	];

	/**
	 * Initialize.
	 *
	 * @codeCoverageIgnore
	 */
	public static function init() {
		add_filter( 'newspack_email_configs', [ __CLASS__, 'add_email_configs' ] );
	}

	/**
	 * Get the from email address for reader revenue emails.
	 *
	 * @return string
	 */
	public static function get_from_email() {
		// Get the site domain and get rid of www.
		$sitename   = wp_parse_url( network_home_url(), PHP_URL_HOST );
		$from_email = 'receipts@';

		if ( null !== $sitename ) {
			if ( 'www.' === substr( $sitename, 0, 4 ) ) {
				$sitename = substr( $sitename, 4 );
			}

			$from_email .= $sitename;
		}

		return apply_filters( 'newspack_reader_revenue_from_email', $from_email );
	}

	/**
	 * Register email type.
	 *
	 * @param array $configs Email types.
	 */
	public static function add_email_configs( $configs ) {
		$configs[ self::EMAIL_TYPES['RECEIPT'] ] = [
			'name'                   => self::EMAIL_TYPES['RECEIPT'],
			'label'                  => __( 'Receipt', 'newspack' ),
			'description'            => __( "Email sent to the donor after they've donated.", 'newspack' ),
			'template'               => dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/reader-revenue-emails/receipt.php',
			'editor_notice'          => __( 'This email will be sent to a reader after they contribute to your site.', 'newspack' ),
			'from_email'             => self::get_from_email(),
			'available_placeholders' => [
				[
					'label'    => __( 'the payment amount', 'newspack' ),
					'template' => '*AMOUNT*',
				],
				[
					'label'    => __( 'payment date', 'newspack' ),
					'template' => '*DATE*',
				],
				[
					'label'    => __( 'payment method (last four digits of the card used)', 'newspack' ),
					'template' => '*PAYMENT_METHOD*',
				],
				[
					'label'    => __(
						'the contact email to your site (same as the "From" email address)',
						'newspack'
					),
					'template' => '*CONTACT_EMAIL*',
				],
				[
					'label'    => __( 'automatically-generated receipt link', 'newspack' ),
					'template' => '*RECEIPT_URL*',
				],
			],
		];
		return $configs;
	}
}
Reader_Revenue_Emails::init();
