<?php
/**
 * Newspack user profile management.
 *
 * @package Newspack
 */

namespace Newspack;

use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . 'includes/configuration_managers/class-configuration-managers.php';
/**
 * Manages user profile data.
 */
class Profile {

	/**
	 * The capability required to access this wizard.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
	}
	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	private function get_wpseo_fields() {
		return [
			[
				'key'         => 'facebook_site',
				'label'       => __( 'Facebook Page', 'newspack' ),
				'placeholder' => __( 'https://facebook.com/page', 'newspack' ),
			],
			[
				'key'         => 'twitter_site',
				'label'       => __( 'Twitter Username', 'newspack' ),
				'placeholder' => __( 'username', 'newspack' ),
			],
			[
				'key'         => 'instagram_url',
				'label'       => 'Instagram',
				'placeholder' => __( 'https://instagram.com/user', 'newspack' ),
			],
			[
				'key'         => 'linkedin_url',
				'label'       => 'Linkedin',
				'placeholder' => __( 'https://linkedin.com/user', 'newspack' ),
			],
			[
				'key'         => 'youtube_url',
				'label'       => 'YouTube',
				'placeholder' => __( 'https://youtube.com/c/channel', 'newspack' ),
			],
			[
				'key'         => 'pinterest_url',
				'label'       => 'Pinterest',
				'placeholder' => __( 'https://pinterest.com/user', 'newspack' ),
			],
		];
	}

	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	private function get_profile_fields() {
		$site_icon                = get_site_icon_url();
		$wc_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'woocommerce' );
		$wc_location_data         = $wc_configuration_manager->location_data();
		$profile_fields           = [
			[
				'name'    => 'site_icon',
				'value'   => ! empty( $site_icon ) ? [
					'url' => $site_icon,
					'id'  => get_option( 'site_icon' ),
				] : false,
				'updater' => function( $value ) {
					update_option( 'site_icon', isset( $value['id'] ) ? $value['id'] : '' );
					update_option( 'sitelogo', isset( $value['id'] ) ? $value['id'] : '' );
				},
			],
			[
				'name'    => 'site_title',
				'value'   => get_option( 'blogname' ),
				'updater' => function( $value ) {
					update_option( 'blogname', $value );
				},
			],
			[
				'name'    => 'tagline',
				'value'   => get_option( 'blogdescription' ),
				'updater' => function( $value ) {
					update_option( 'blogdescription', $value );
				},
			],
			[
				'name'    => 'countrystate',
				'value'   => $wc_location_data['countrystate'],
				'updater' => function( $value ) {
					$wc_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'woocommerce' );
					$wc_configuration_manager->update_location( [ 'countrystate' => $value ] );
				},
			],
			[
				'name'    => 'currency',
				'value'   => $wc_location_data['currency'],
				'updater' => function( $value ) {
					$wc_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'woocommerce' );
					$wc_configuration_manager->update_location( [ 'currency' => $value ] );
				},
			],
		];

		$wpseo_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'wordpress_seo' );
		foreach ( self::get_wpseo_fields() as $field ) {
			$value = $wpseo_manager->get_option( $field['key'], '' );
			if ( is_wp_error( $value ) ) {
				$value = '';
			}
			$profile_fields[] = [
				'name'    => $field['key'],
				'value'   => $value,
				'updater' => function( $value ) use ( $field ) {
					$wpseo_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'wordpress_seo' );
					$wpseo_manager->set_option( $field['key'], $value );
				},
			];
		}

		return $profile_fields;
	}

	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	public function register_api_endpoints() {
		// Get profile data.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/profile/',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_profile' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);

		// Update profile data.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/profile/',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_profile' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => array(
					'profile' => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			]
		);
	}

	/**
	 * Get complete user profile.
	 */
	public function newspack_get_profile() {
		$profile = [];
		foreach ( self::get_profile_fields() as $field ) {
			$profile[ $field['name'] ] = $field['value'];
		}
		return $profile;
	}

	/**
	 * Retrieve complete profile.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return object|WP_Error
	 */
	public function api_get_profile( $request ) {
		$response = [
			'profile'      => $this->newspack_get_profile(),
			'currencies'   => newspack_get_currencies_options(),
			'countries'    => newspack_get_countries(),
			'wpseo_fields' => self::get_wpseo_fields(),
		];
		return rest_ensure_response( $response );
	}

	/**
	 * Update the site profile.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return object|WP_Error
	 */
	public function api_update_profile( $request ) {
		$updates = $request['profile'];
		foreach ( self::get_profile_fields() as $field ) {
			$updater = $field['updater'];
			$updater( $updates[ $field['name'] ] );
		}

		$profile               = $this->newspack_get_profile();
		$social_menu_name      = __( 'Social Links', 'newspack' );
		$social_menu_placement = 'social';

		// Create social menu if there is none and social links are set.
		if (
			// No menu assigned to 'social' placement (defined by Newspack Theme).
			! has_nav_menu( $social_menu_placement ) &&
			// No menu called "Social Links".
			! wp_get_nav_menu_object( $social_menu_name )
		) {
			$social_menu_items = [];
			foreach ( self::get_wpseo_fields() as $social_field ) {
				if ( ! empty( $profile[ $social_field['key'] ] ) ) {
					$social_menu_items[] = [
						'key'   => $social_field['key'],
						'label' => $social_field['label'],
						'value' => $profile[ $social_field['key'] ],
					];
				}
			}
			if ( ! empty( $social_menu_items ) ) {
				// Create the menu.
				$social_menu_id = wp_create_nav_menu( $social_menu_name );
				// Set nav menu location.
				$locations                           = get_theme_mod( 'nav_menu_locations' );
				$locations[ $social_menu_placement ] = $social_menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
				// Set the menu items.
				foreach ( $social_menu_items as $social_item ) {
					if ( 'twitter_site' === $social_item['key'] ) {
						// Twitter is the only one stored (by Yoast) as a username, not full URL.
						$social_item['value'] = 'https://twitter.com/' . $social_item['value'];
					}
					wp_update_nav_menu_item(
						$social_menu_id,
						0,
						[
							'menu-item-title'  => $social_item['label'],
							'menu-item-url'    => $social_item['value'],
							'menu-item-status' => 'publish',
						]
					);
				}
			}
		}

		$response = [
			'profile' => $profile,
		];
		return rest_ensure_response( $response );
	}

	/**
	 * Check capabilities for using API.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function api_permissions_check( $request ) {
		if ( ! current_user_can( $this->capability ) ) {
			return new \WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => 403,
				]
			);
		}
		return true;
	}

}
new Profile();
