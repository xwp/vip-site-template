<?php
/**
 * Newspack's SEO Wizard
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
class SEO_Wizard extends Wizard {

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-seo-wizard';

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
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
		add_filter( 'wpseo_image_image_weight_limit', [ $this, 'ignore_yoast_weight_limit' ] );
	}

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return \esc_html__( 'SEO', 'newspack' );
	}

	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	public function register_api_endpoints() {
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_seo_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/settings',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_seo_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'under_construction' => [
						'sanitize_callback' => 'absint',
					],
					'separator'          => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'verification'       => [],
					'urls'               => [],
				],
			]
		);
	}

	/**
	 * API endpoint to retrieve all settings necessary to render the SEO wizard.
	 *
	 * @return WP_REST_Response with the info.
	 */
	public function api_get_seo_settings() {
		$response = $this->get_seo_settings();
		return rest_ensure_response( $response );
	}

	/**
	 * Update SEO wizard settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with the info.
	 */
	public function api_update_seo_settings( $request ) {
		$cm = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'wordpress_seo' );
		if ( isset( $request['urls'] ) ) {
			$urls = $request['urls'];
			if ( isset( $urls['facebook'] ) ) {
				$cm->set_option( 'facebook_site', $urls['facebook'] );
			}
			if ( isset( $urls['twitter'] ) ) {
				$cm->set_option( 'twitter_site', $urls['twitter'] );
			}
			if ( isset( $urls['instagram'] ) ) {
				$cm->set_option( 'instagram_url', $urls['instagram'] );
			}
			if ( isset( $urls['linkedin'] ) ) {
				$cm->set_option( 'linkedin_url', $urls['linkedin'] );
			}
			if ( isset( $urls['youtube'] ) ) {
				$cm->set_option( 'youtube_url', $urls['youtube'] );
			}
			if ( isset( $urls['pinterest'] ) ) {
				$cm->set_option( 'pinterest_url', $urls['pinterest'] );
			}
		}
		if ( isset( $request['verification'] ) ) {
			$verification = $request['verification'];
			if ( isset( $verification['bing'] ) ) {
				$cm->set_option( 'msverify', $verification['bing'] );
			}
			if ( isset( $verification['google'] ) ) {
				$cm->set_option( 'googleverify', $verification['google'] );
			}
		}
		if ( isset( $request['title_separator'] ) ) {
			$cm->set_option( 'separator', $request['title_separator'] );
		}
		if ( isset( $request['under_construction'] ) ) {
			$environment_type = absint( $request['under_construction'] ) ? 'staging' : 'production';
			$blog_public      = absint( $request['under_construction'] ) ? 0 : 1;
			$cm->set_option( 'environment_type', $environment_type );
			update_option( 'blog_public', $blog_public );
		}
		$response = $this->get_seo_settings();
		return rest_ensure_response( $response );
	}

	/**
	 * Retrieve all settings necessary to render the SEO wizard.
	 *
	 * @return Array with the info.
	 */
	public function get_seo_settings() {
		$cm = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'wordpress_seo' );

		$response = [
			'under_construction' => 'staging' === $cm->get_option( 'environment_type', 'production' ),
			'title_separator'    => $cm->get_option( 'separator', '' ),
			'verification'       => [
				'bing'   => $cm->get_option( 'msverify', '' ),
				'google' => $cm->get_option( 'googleverify', '' ),
			],
			'urls'               => [
				'facebook'  => $cm->get_option( 'facebook_site', '' ),
				'twitter'   => $cm->get_option( 'twitter_site', '' ),
				'instagram' => $cm->get_option( 'instagram_url', '' ),
				'linkedin'  => $cm->get_option( 'linkedin_url', '' ),
				'youtube'   => $cm->get_option( 'youtube_url', '' ),
				'pinterest' => $cm->get_option( 'pinterest_url', '' ),
			],
		];
		return $response;
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
			'newspack-seo-wizard',
			Newspack::plugin_url() . '/dist/seo.js',
			$this->get_script_dependencies( [ 'wp-html-entities' ] ),
			NEWSPACK_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * We don't want Yoast to exclude large images from og:image tags for 2 reasons:
	 * 1. Yoast cannot calculate the image size for images served via Jetpack CDN, so any calculations will be incorrect.
	 * 2. It increases support burden since Yoast doesn't provide the user any explanation for why the image was excluded.
	 *
	 * @param int $limit Max image size in bytes.
	 * @return int Modified $limit.
	 */
	public function ignore_yoast_weight_limit( $limit ) {
		return PHP_INT_MAX;
	}
}
