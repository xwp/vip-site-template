<?php
/**
 * Newspack's Engagement Wizard
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error, \WP_Query;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/wizards/class-wizard.php';

/**
 * Easy interface for setting up general store info.
 */
class Engagement_Wizard extends Wizard {

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-engagement-wizard';

	/**
	 * The capability required to access this wizard.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * The name of the option for Related Posts max age.
	 *
	 * @var string
	 */
	protected $related_posts_option = 'newspack_related_posts_max_age';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
		add_filter( 'jetpack_relatedposts_filter_date_range', [ $this, 'restrict_age_of_related_posts' ] );
		add_filter( 'newspack_newsletters_settings_url', [ $this, 'newsletters_settings_url' ] );
	}

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return \esc_html__( 'Engagement', 'newspack' );
	}

	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	public function register_api_endpoints() {
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/related-content',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_related_content_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/related-posts-max-age',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_related_posts_max_age' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'sanitize_callback'   => 'sanitize_text_field',
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/reader-activation',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_reader_activation_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/reader-activation',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_reader_activation_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/newsletters',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_newsletters_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/newsletters',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_newsletters_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/newsletters/lists',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_newsletters_lists' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);

		$meta_pixel = new Meta_Pixel();
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/social/meta_pixel',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $meta_pixel, 'api_get' ],
					'permission_callback' => [ $this, 'api_permissions_check' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $meta_pixel, 'api_save' ],
					'permission_callback' => [ $this, 'api_permissions_check' ],
					'args'                => [
						'active'   => [
							'type'              => 'boolean',
							'required'          => true,
							'validate_callback' => [ $meta_pixel, 'validate_active' ],
						],
						'pixel_id' => [
							'type'              => [ 'integer', 'string' ],
							'required'          => true,
							'validate_callback' => [ $meta_pixel, 'validate_pixel_id' ],
						],
					],
				],
			]
		);

		$twitter_pixel = new Twitter_Pixel();
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/social/twitter_pixel',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $twitter_pixel, 'api_get' ],
					'permission_callback' => [ $this, 'api_permissions_check' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $twitter_pixel, 'api_save' ],
					'permission_callback' => [ $this, 'api_permissions_check' ],
					'args'                => [
						'active'   => [
							'type'              => 'boolean',
							'required'          => true,
							'validate_callback' => [ $twitter_pixel, 'validate_active' ],
						],
						'pixel_id' => [
							'type'              => [ 'integer', 'string' ],
							'required'          => true,
							'validate_callback' => [ $twitter_pixel, 'validate_pixel_id' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Get memberships settings.
	 *
	 * @return array
	 */
	private static function get_memberships_settings() {
		return [
			'edit_gate_url' => WC_Memberships::get_edit_gate_url(),
			'gate_status'   => get_post_status( WC_Memberships::get_gate_post_id() ),
		];
	}

	/**
	 * Get reader activation settings.
	 *
	 * @return WP_REST_Response
	 */
	public function api_get_reader_activation_settings() {
		return rest_ensure_response(
			[
				'config'        => Reader_Activation::get_settings(),
				'memberships'   => self::get_memberships_settings(),
				'pluginsStatus' => Reader_Activation::is_woocommerce_active(),
			]
		);
	}

	/**
	 * Update reader activation settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function api_update_reader_activation_settings( $request ) {
		$args = $request->get_params();
		foreach ( $args as $key => $value ) {
			Reader_Activation::update_setting( $key, $value );
		}
		return rest_ensure_response( Reader_Activation::get_settings() );
	}

	/**
	 * Get lists of configured ESP.
	 */
	public static function api_get_newsletters_lists() {
		$newsletters_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-newsletters' );
		return $newsletters_configuration_manager->get_lists();
	}

	/**
	 * Get Newspack Newsletters setttings.
	 *
	 * @return object with the info.
	 */
	private static function get_newsletters_settings() {
		$newsletters_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-newsletters' );
		$settings                          = array_reduce(
			$newsletters_configuration_manager->get_settings(),
			function ( $acc, $value ) {
				$acc[ $value['key'] ] = $value;
				return $acc;
			},
			[]
		);
		return [
			'configured' => $newsletters_configuration_manager->is_configured(),
			'settings'   => $settings,
		];
	}

	/**
	 * Get Newspack Newsletters setttings API response.
	 *
	 * @return WP_REST_Response with the info.
	 */
	public function api_get_newsletters_settings() {
		return rest_ensure_response( self::get_newsletters_settings() );
	}

	/**
	 * Get Newspack Newsletters setttings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response with the info.
	 */
	public function api_update_newsletters_settings( $request ) {
		$args                              = $request->get_params();
		$newsletters_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-newsletters' );
		$newsletters_configuration_manager->update_settings( $args );
		return $this->api_get_newsletters_settings();
	}

	/**
	 * Update the Related Posts Max Age setting.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Updated value, if successful, or WP_Error.
	 */
	public function api_update_related_posts_max_age( $request ) {
		$args = $request->get_params();

		if ( is_numeric( $args['relatedPostsMaxAge'] ) && 0 <= $args['relatedPostsMaxAge'] ) {
			update_option( $this->related_posts_option, $args['relatedPostsMaxAge'] );
		} else {
			return new WP_Error(
				'newspack_related_posts_invalid_arg',
				esc_html__( 'Invalid argument: max age must be a number greater than zero.', 'newspack' ),
				[
					'status' => 400,
					'level'  => 'notice',
				]
			);
		}

		return \rest_ensure_response(
			[
				'relatedPostsMaxAge' => $args['relatedPostsMaxAge'],
			]
		);
	}

	/**
	 * Restrict the age of related content shown by Jetpack Related Posts.
	 *
	 * @param array $date_range Array of start and end dates.
	 * @return array Filtered array of start/end dates.
	 */
	public function restrict_age_of_related_posts( $date_range ) {
		$related_posts_max_age = get_option( $this->related_posts_option );

		if ( is_numeric( $related_posts_max_age ) && 0 < $related_posts_max_age ) {
			$date_range['from'] = strtotime( '-' . $related_posts_max_age . ' months' );
			$date_range['to']   = time();
		}

		return $date_range;
	}

	/**
	 * Get the Jetpack connection settings.
	 *
	 * @return WP_REST_Response with the info.
	 */
	public function api_get_related_content_settings() {
		$jetpack_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'jetpack' );
		return rest_ensure_response(
			[
				'relatedPostsEnabled' => $jetpack_configuration_manager->is_related_posts_enabled(),
				'relatedPostsMaxAge'  => get_option( $this->related_posts_option, 0 ),
			]
		);
	}

	/**
	 * Enqueue Subscriptions Wizard scripts and styles.
	 */
	public function enqueue_scripts_and_styles() {
		parent::enqueue_scripts_and_styles();

		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) !== $this->slug ) {
			return;
		}

		\wp_enqueue_script(
			'newspack-engagement-wizard',
			Newspack::plugin_url() . '/dist/engagement.js',
			$this->get_script_dependencies( array( 'wp-html-entities' ) ),
			NEWSPACK_PLUGIN_VERSION,
			true
		);

		$data = [
			'has_reader_activation' => defined( 'NEWSPACK_EXPERIMENTAL_READER_ACTIVATION' ) && NEWSPACK_EXPERIMENTAL_READER_ACTIVATION,
			'has_memberships'       => class_exists( 'WC_Memberships' ),
		];

		if ( method_exists( 'Newspack\Newsletters\Subscription_Lists', 'get_add_new_url' ) ) {
			$data['new_subscription_lists_url'] = \Newspack\Newsletters\Subscription_Lists::get_add_new_url();
		}

		\wp_localize_script(
			'newspack-engagement-wizard',
			'newspack_engagement_wizard',
			$data
		);
	}

	/**
	 * Sanitize array of categories.
	 *
	 * @param array $categories Array of categories to sanitize.
	 * @return array Sanitized array.
	 */
	public static function sanitize_categories( $categories ) {
		$categories = is_array( $categories ) ? $categories : [];
		$sanitized  = [];
		foreach ( $categories as $category ) {
			$category['id']   = isset( $category['id'] ) ? absint( $category['id'] ) : null;
			$category['name'] = isset( $category['name'] ) ? sanitize_title( $category['name'] ) : null;
			$sanitized[]      = $category;
		}
		return $sanitized;
	}

	/**
	 * Set the newsletters settings url
	 *
	 * @param string $url URL to the Newspack Newsletters settings page.
	 *
	 * @return string URL to the Newspack Newsletters settings page.
	 */
	public function newsletters_settings_url( $url = '' ) {
		return admin_url( 'admin.php?page=newspack-engagement-wizard#/newsletters' );
	}
}
