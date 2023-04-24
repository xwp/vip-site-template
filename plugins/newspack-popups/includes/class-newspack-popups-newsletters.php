<?php
/**
 * Newspack Popups Newsletters
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/../api/campaigns/class-campaign-data-utils.php';

/**
 * Main Newspack Popups Newsletters Class.
 */
final class Newspack_Popups_Newsletters {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Newsletters
	 */
	protected static $instance = null;

	/**
	 * Ensures the is-subscriber status check is run only once per request.
	 *
	 * @var bool
	 */
	private static $is_checking_status = false;

	/**
	 * Main Newspack Popups Newsletters Instance.
	 * Ensures only one instance of Newspack Popups Newsletters Instance is loaded or can be loaded.
	 *
	 * @return Newspack Popups Newsletters Instance - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		\add_action( 'newspack_newsletters_add_contact', [ __CLASS__, 'handle_newsletter_subscription' ], 10, 4 );
		\add_action( 'wp_login', [ __CLASS__, 'status_check_on_login' ], 10, 2 );
		\add_action( 'newspack_registered_reader', [ __CLASS__, 'newspack_registered_reader' ], 10, 5 );
	}

	/**
	 * Check status on login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user User object.
	 */
	public static function status_check_on_login( $user_login, $user ) {
		if ( ! method_exists( '\Newspack\Reader_Activation', 'is_user_reader' ) ) {
			return;
		}
		if ( ! \Newspack\Reader_Activation::is_user_reader( $user ) ) {
			return;
		}
		try {
			self::fetch_reader_data_from_esp( $user->user_email );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing.
		}
	}

	/**
	 * Update reader events when the Newspack Newsletters subscribe form adds a contact to a list.
	 *
	 * @param string         $provider The provider name.
	 * @param array          $contact  {
	 *     Contact information.
	 *
	 *    @type string   $email    Contact email address.
	 *    @type string   $name     Contact name. Optional.
	 *    @type string[] $metadata Contact additional metadata. Optional.
	 * }
	 * @param string[]|false $lists    Array of list IDs to subscribe the contact to.
	 * @param bool|WP_Error  $result   True if the contact was added or error if failed.
	 */
	public static function handle_newsletter_subscription( $provider, $contact, $lists, $result ) {
		if ( \is_wp_error( $result ) || ! $result || empty( $lists ) ) {
			return;
		}

		// If adding to the master list only, don't add a new event. Adding to the master list is
		// not an explicit action taken by the reader.
		if ( method_exists( '\Newspack\Newspack_Newsletters', 'get_lists_without_active_campaign_master_list' ) ) {
			$lists = \Newspack\Newspack_Newsletters::get_lists_without_active_campaign_master_list( $lists );
			if ( empty( $lists ) ) {
				return;
			}
		}

		$subscription_event = [
			'type'    => 'subscription',
			'context' => $contact['email'],
			'value'   => [
				'esp'   => $provider,
				'lists' => $lists,
			],
		];


		$client_id = isset( $contact['client_id'] ) ? $contact['client_id'] : \Newspack_Popups_Segmentation::get_client_id();
		$nonce     = \wp_create_nonce( 'newspack_campaigns_lightweight_api' );
		$api       = \Campaign_Data_Utils::get_api( $nonce );

		if ( ! $api || ! $client_id ) {
			return;
		}

		$api->save_reader_events( $client_id, [ $subscription_event ] );
	}

	/**
	 * Handle the newspack_registered_reader hook.
	 *
	 * @param string         $email_address   Email address.
	 * @param bool           $authenticate    Whether to authenticate after registering.
	 * @param false|int      $user_id         The created user id.
	 * @param false|\WP_User $existing_user   The existing user object.
	 * @param array          $metadata        Metadata.
	 */
	public static function newspack_registered_reader( $email_address, $authenticate, $user_id, $existing_user, $metadata ) {
		if ( false !== $existing_user ) {
			// Fetch data only if it's a new user registration.
			return;
		}
		self::fetch_reader_data_from_esp( $email_address );
	}

	/**
	 * When a reader provides an email address through the Newspack auth flow and
	 * the reader's newsletter subscription status is unknown, check the status
	 * of the email address with the ESP currently active in Newspack Newsletters.
	 *
	 * @param string $email_address Email address.
	 */
	private static function fetch_reader_data_from_esp( $email_address ) {
		if ( self::$is_checking_status ) {
			return;
		}
		self::$is_checking_status = true;

		if ( ! $email_address || ! class_exists( '\Newspack_Newsletters' ) || ! class_exists( '\Newspack_Newsletters_Subscription' ) ) {
			return;
		}

		$nonce = \wp_create_nonce( 'newspack_campaigns_lightweight_api' );
		$api   = Campaign_Data_Utils::get_api( $nonce );
		if ( ! $api ) {
			return;
		}

		$client_id           = Newspack_Popups_Segmentation::get_client_id();
		$events_to_add       = [];
		$newsletter_provider = \Newspack_Newsletters::get_service_provider();

		// If the reader is already known to be a newsletter subscriber, no need to proceed.
		$has_subscription_events = ! empty( $api->get_reader_events( $client_id, 'subscription', $email_address ) );
		if ( ! $has_subscription_events ) {
			// Look up the email address as a contact with the connected ESP. If not a contact, no need to proceed.
			$subscribed_lists = \Newspack_Newsletters_Subscription::get_contact_lists( $email_address );
			if ( ! is_wp_error( $subscribed_lists ) && ! empty( $subscribed_lists ) && is_array( $subscribed_lists ) ) {
				// The reader is subscribed to one or more lists, so they should be segmented as a subscriber.
				$events_to_add[] = [
					'type'    => 'subscription',
					'context' => $email_address,
					'value'   => [
						'esp'   => $newsletter_provider->service,
						'lists' => $subscribed_lists,
					],
				];
			}
		}

		// If the reader is already known to be a donor, no need to proceed.
		$has_donation_events = ! empty( $api->get_reader_events( $client_id, 'donation', $email_address ) );
		if ( ! $has_donation_events ) {
			$contact_details = \Newspack_Newsletters_Subscription::get_contact_data( $email_address, true );
			if ( ! is_wp_error( $contact_details ) && isset( $contact_details['metadata'] ) ) {
				$metadata        = $contact_details['metadata'];
				$donation_amount = 0;

				if ( isset( $metadata['NP_LAST_PAYMENT_AMOUNT'] ) ) {
					$donation_amount = (int) $metadata['NP_LAST_PAYMENT_AMOUNT'];
				} elseif ( isset( $metadata['TOTAL_SPENT'] ) ) {
					$donation_amount = (int) $metadata['TOTAL_SPENT'];
				}
				if ( 0 < $donation_amount ) {
					$events_to_add[] = [
						'type'    => 'donation',
						'context' => $newsletter_provider->service,
						'value'   => [
							'amount' => $donation_amount,
						],
					];
				}
			}
		}

		if ( ! empty( $events_to_add ) ) {
			$api->save_reader_events( $client_id, $events_to_add );
		}
	}
}

Newspack_Popups_Newsletters::instance();
