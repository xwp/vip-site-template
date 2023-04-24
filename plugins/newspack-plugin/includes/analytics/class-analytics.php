<?php
/**
 * Newspack Analytics features.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Analytics integrations.
 */
class Analytics {
	/**
	 * An integer to indicate the context a block is rendered in, e.g. content|overlay campaign|inline campaign.
	 *
	 * @var integer
	 */
	public static $block_render_context = 3;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'googlesitekit_gtag_opt', [ __CLASS__, 'set_extra_analytics_config_options' ] );

		add_action( 'newspack_campaigns_before_campaign_render', [ __CLASS__, 'set_campaign_render_context' ], 10, 1 );
		add_action( 'newspack_campaigns_after_campaign_render', [ __CLASS__, 'reset_render_context' ], 10, 1 );

		// Reader Activation hooks.
		add_action( 'newspack_registered_reader', [ __CLASS__, 'newspack_registered_reader' ], 10, 5 );
		add_action( 'newspack_newsletters_add_contact', [ __CLASS__, 'newspack_newsletters_add_contact' ], 10, 4 );
	}

	/**
	 * When a Newspack Campaign is being rendered, set the block rendering context based on whether campaign is inline or overlay.
	 *
	 * @param object $campaign The campaign object.
	 */
	public static function set_campaign_render_context( $campaign ) {
		$placement = isset( $campaign['options'], $campaign['options']['placement'] ) ? $campaign['options']['placement'] : null;
		switch ( $placement ) {
			case 'inline':
				self::$block_render_context = 1;
				break;
			default: // All other placement options are overlays.
				self::$block_render_context = 2;
				break;
		}
	}

	/**
	 * Reset rendering context after campaign rendering is complete.
	 *
	 * @param object $campaign The campaign object.
	 */
	public static function reset_render_context( $campaign ) {
		self::$block_render_context = 3;
	}

	/**
	 * Filter the Google Analytics config options via Site Kit.
	 * Allows us to update or set additional config options for GA.
	 *
	 * @param array $gtag_opt gtag config options.
	 *
	 * @return array Filtered config options.
	 */
	public static function set_extra_analytics_config_options( $gtag_opt ) {
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return $gtag_opt;
		}

		if ( ! self::can_use_site_kits_analytics() ) {
			return $gtag_opt;
		}

		// Set transport type to 'beacon' to allow async requests to complete after a new page is loaded.
		// See: https://developers.google.com/analytics/devguides/collection/gtagjs/sending-data#specify_different_transport_mechanisms.
		$gtag_opt['transport_type'] = 'beacon';

		return $gtag_opt;
	}

	/**
	 * Can we rely on Site Kit's Analytics module?
	 */
	public static function can_use_site_kits_analytics() {
		if ( ! class_exists( '\Google\Site_Kit\Modules\Analytics\Settings' ) ) {
			return false;
		}
		$sitekit_manager     = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'google-site-kit' );
		$sitekit_ga_settings = get_option( \Google\Site_Kit\Modules\Analytics\Settings::OPTION, false );
		return (bool) $sitekit_manager->is_module_active( 'analytics' ) && $sitekit_ga_settings['canUseSnippet'];
	}

	/**
	 * When a new reader registers, sent an event to GA.
	 *
	 * @param string         $email         Email address.
	 * @param bool           $authenticate  Whether to authenticate after registering.
	 * @param false|int      $user_id       The created user id.
	 * @param false|\WP_User $existing_user The existing user object.
	 * @param array          $metadata      Metadata.
	 */
	public static function newspack_registered_reader( $email, $authenticate, $user_id, $existing_user, $metadata ) {
		if ( $existing_user ) {
			return;
		}
		$event_spec = [
			'category' => __( 'Newspack Reader Activation', 'newspack' ),
			'action'   => __( 'Registration', 'newspack' ),
		];

		if ( isset( $metadata['registration_method'] ) ) {
			$event_spec['action'] .= ' (' . $metadata['registration_method'] . ')';
		}

		if ( isset( $metadata['lists'] ) ) {
			$event_spec['label'] = __( 'Signed up for lists:', 'newspack' ) . ' ' . implode( ', ', $metadata['lists'] );
		}

		if ( isset( $metadata['current_page_url'] ) ) {
			$parsed_url = \wp_parse_url( $metadata['current_page_url'] );
			if ( $parsed_url ) {
				if ( isset( $parsed_url['host'] ) ) {
					$event_spec['host'] = $parsed_url['host'];
				}
				if ( isset( $parsed_url['path'] ) ) {
					$event_spec['path'] = $parsed_url['path'];
				}
			}
		}

		\Newspack\Google_Services_Connection::send_custom_event( $event_spec );

		if ( Analytics_Wizard::ntg_events_enabled() ) {
			\Newspack\Google_Services_Connection::send_custom_event(
				[
					'category' => __( 'NTG account', 'newspack' ),
					'action'   => __( 'registration', 'newspack' ),
					'label'    => 'success',
				]
			);
		}
	}

	/**
	 * When a reader signs up for the newsletter, send an event to GA.
	 *
	 * @param string         $provider The provider name.
	 * @param array          $contact  {
	 *    Contact information.
	 *
	 *    @type string   $email                 Contact email address.
	 *    @type string   $name                  Contact name. Optional.
	 *    @type string   $existing_contact_data Existing contact data, if updating a contact. The hook will be also called when
	 *    @type string[] $metadata              Contact additional metadata. Optional.
	 * }
	 * @param string[]|false $lists    Array of list IDs to subscribe the contact to.
	 * @param bool|WP_Error  $result   True if the contact was added or error if failed.
	 */
	public static function newspack_newsletters_add_contact( $provider, $contact, $lists, $result ) {
		if (
			! Analytics_Wizard::ntg_events_enabled()
			|| ! method_exists( '\Newspack_Newsletters_Subscription', 'get_contact_data' )
			// Don't send events for updates to a contact.
			|| $contact['existing_contact_data']
		) {
			return;
		}

		// If the user is only being subscribed to the master list, they did not sign up for the newsletter explicitly.
		$lists = Newspack_Newsletters::get_lists_without_active_campaign_master_list( $lists );
		if ( empty( $lists ) ) {
			return;
		}

		\Newspack\Google_Services_Connection::send_custom_event(
			[
				'category' => __( 'NTG newsletter', 'newspack' ),
				'action'   => __( 'newsletter signup', 'newspack' ),
				'label'    => 'success',
			]
		);
	}
}
new Analytics();
