<?php
/**
 * Newspack setup wizard. Required plugins, introduction, and data collection.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error, WP_REST_Server;
defined( 'ABSPATH' ) || exit;
require_once NEWSPACK_ABSPATH . '/includes/wizards/class-wizard.php';

define( 'NEWSPACK_SETUP_COMPLETE', 'newspack_setup_complete' );

/**
 * Setup Newspack.
 */
class Setup_Wizard extends Wizard {
	const SERVICE_ENABLED_OPTION_PREFIX = 'newspack_service_enabled_';

	const SERVICE_ENDPOINT_SCHEMA_BASE = [
		'type'       => 'object',
		'properties' => [
			'is_service_enabled' => [
				'type'     => 'boolean',
				'required' => true,
			],
		],
	];

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-setup-wizard';

	/**
	 * The capability required to access this wizard.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * An array of theme mods that are media library IDs.
	 *
	 * @var array
	 */
	protected $media_theme_mods = [ 'newspack_footer_logo', 'custom_logo' ];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
		if ( ! get_option( NEWSPACK_SETUP_COMPLETE ) ) {
			add_action( 'current_screen', [ $this, 'redirect_to_setup' ] );
			add_action( 'admin_menu', [ $this, 'hide_non_setup_menu_items' ], 1000 );
		}
		add_filter( 'show_admin_bar', [ $this, 'show_admin_bar' ], 10, 2 ); // phpcs:ignore WordPressVIPMinimum.UserExperience.AdminBarRemoval.RemovalDetected
		$this->hidden = get_option( NEWSPACK_SETUP_COMPLETE, false );
	}

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return esc_html__( 'Setup', 'newspack' );
	}

	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	public function register_api_endpoints() {
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/initial-check',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_initial_check' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		// Update option when setup is complete.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/complete',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_complete' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/theme',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_retrieve_theme_and_set_defaults' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/theme',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_theme_with_mods' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'theme_mods' => [
						'sanitize_callback' => [ $this, 'sanitize_theme_mods' ],
					],
					'theme'      => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/starter-content/init',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_starter_content_init' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/starter-content/post/(?P<id>[\a-z]+)',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_starter_content_post' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/starter-content/theme',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_starter_content_theme' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/starter-content/homepage',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_starter_content_homepage' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/services',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_services' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/services',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_services' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'reader-revenue'    => self::SERVICE_ENDPOINT_SCHEMA_BASE,
					'newsletters'       => self::SERVICE_ENDPOINT_SCHEMA_BASE,
					'google-ad-sense'   => self::SERVICE_ENDPOINT_SCHEMA_BASE,
					'google-ad-manager' => self::SERVICE_ENDPOINT_SCHEMA_BASE,
				],
			]
		);
	}

	/**
	 * Perform an initial check before starting setup.
	 *
	 * @return WP_REST_Response containing info.
	 */
	public function api_initial_check() {
		$required_plugins_slugs = [
			'jetpack',
			'pwa',
			'wordpress-seo',
			'google-site-kit',
			'newspack-blocks',
			'newspack-newsletters',
			'newspack-theme',
		];

		// Gather information about required plugins.
		$managed_plugins = Plugin_Manager::get_managed_plugins();
		$plugin_info     = [];
		foreach ( $required_plugins_slugs as $slug ) {
			$plugin               = $managed_plugins[ $slug ];
			$plugin['Configured'] = \Newspack\Configuration_Managers::is_configured( $slug );
			$plugin_info[]        = $plugin;
		}

		return rest_ensure_response(
			[
				'plugins' => $plugin_info,
				'is_ssl'  => is_ssl() || Starter_Content::is_e2e(),
			]
		);
	}

	/**
	 * Update option when setup is complete.
	 *
	 * @return WP_REST_Response containing info.
	 */
	public function api_complete() {
		update_option( NEWSPACK_SETUP_COMPLETE, true );
		return rest_ensure_response( [ 'complete' => true ] );
	}

	/**
	 * Initialize a starter content generator.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_starter_content_init( $request ) {
		$request_params = $request->get_params();
		if ( empty( $request_params ) || empty( $request_params['type'] ) ) {
			return new WP_Error( 'newspack_setup', __( 'Missing starter content initialization info', 'newspack' ) );
		}

		$starter_content_type = 'import' === $request_params['type'] ? 'import' : 'generated';
		$existing_site_url    = ! empty( $request_params['site'] ) && wp_http_validate_url( $request_params['site'] ) ? esc_url_raw( $request_params['site'] ) : '';

		if ( 'import' === $starter_content_type && ! $existing_site_url ) {
			return new WP_Error(
				'newspack_setup',
				sprintf(
					/* translators: %s - Site URL */
					__( 'Invalid site URL: "%s".', 'newspack' ),
					sanitize_text_field( $request_params['site'] )
				)
			);
		}

		$result = Starter_Content::initialize( $starter_content_type, $existing_site_url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'status' => 200 ] );
	}

	/**
	 * Install one starter content post
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response containing info.
	 */
	public function api_starter_content_post( $request ) {
		$id     = $request['id'];
		$status = Starter_Content::create_post( $id );
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		return rest_ensure_response( [ 'status' => $status ] );
	}

	/**
	 * Set up initial theme mods
	 *
	 * @return WP_REST_Response containing info.
	 */
	public function api_starter_content_theme() {
		$status = Starter_Content::initialize_theme();
		return rest_ensure_response( [ 'status' => $status ] );
	}

	/**
	 * Set up Homepage
	 *
	 * @return WP_REST_Response containing info.
	 */
	public function api_starter_content_homepage() {
		$status = Starter_Content::create_homepage();
		return rest_ensure_response( [ 'status' => $status ] );
	}

	/**
	 * Get current theme & mods.
	 *
	 * @return WP_REST_Response containing info.
	 */
	public function api_retrieve_theme_and_set_defaults() {
		$theme_mods = get_theme_mods();

		$theme_mods['header_color'] = get_theme_mod( 'header_color', 'default' );
		// Force custom header color, since the default depends on the theme.
		if ( 'default' === $theme_mods['header_color'] ) {
			set_theme_mod( 'header_color', 'custom' );
			$theme_mods['header_color'] = get_theme_mod( 'header_color' );
		}

		foreach ( $theme_mods as $key => &$theme_mod ) {
			if ( in_array( $key, $this->media_theme_mods ) ) {
				$attachment = wp_get_attachment_image_src( $theme_mod, 'large' );
				if ( $attachment ) {
					$theme_mod = [
						'id'  => $theme_mod,
						'url' => is_array( $attachment ) ? $attachment[0] : null,
					];
				}
			}
		}
		$theme_mods['theme_colors'] = get_theme_mod( 'theme_colors', 'default' );
		if ( 'default' === $theme_mods['theme_colors'] ) {
			$theme_mods['primary_color_hex']   = newspack_get_primary_color();
			$theme_mods['secondary_color_hex'] = newspack_get_secondary_color();
		} else {
			$theme_mods['primary_color_hex']   = get_theme_mod( 'primary_color_hex', newspack_get_primary_color() );
			$theme_mods['secondary_color_hex'] = get_theme_mod( 'secondary_color_hex', newspack_get_secondary_color() );
		}

		// Set custom header color to primary, if not set.
		if ( ! isset( $theme_mods['header_color_hex'] ) ) {
			set_theme_mod( 'header_color_hex', $theme_mods['primary_color_hex'] );
			$theme_mods['header_color_hex'] = get_theme_mod( 'header_color_hex' );
		}
		if ( ! isset( $theme_mods['homepage_pattern_index'] ) ) {
			$theme_mods['homepage_pattern_index'] = 0;
		}

		$theme_mods['accent_allcaps'] = get_theme_mod( 'accent_allcaps', true );

		// Footer.
		$theme_mods['footer_color']     = get_theme_mod( 'footer_color', 'default' );
		$theme_mods['footer_logo_size'] = get_theme_mod( 'footer_logo_size', 'medium' );
		$theme_mods['footer_copyright'] = get_theme_mod( 'footer_copyright', false );
		if ( false === $theme_mods['footer_copyright'] ) {
			$theme_mods['footer_copyright'] = get_theme_mod( 'footer_copyright', '' );
		}

		$theme_mods['header_text']            = get_theme_mod( 'header_text', '' );
		$theme_mods['header_display_tagline'] = get_theme_mod( 'header_display_tagline', '' );

		// Append media credits settings.
		$media_credits_settings = Newspack_Image_Credits::get_settings();
		foreach ( $media_credits_settings as $key => $value ) {
			$theme_mods[ $key ] = $value;

			if ( 'newspack_image_credits_placeholder' === $key && ! empty( $value ) ) {
				$attachment_url = wp_get_attachment_image_url( $value, 'full' );
				if ( $attachment_url ) {
					$theme_mods['newspack_image_credits_placeholder_url'] = $attachment_url;
				}
			}
		}

		return rest_ensure_response(
			[
				'theme'             => Starter_Content::get_theme(),
				'theme_mods'        => $theme_mods,
				'homepage_patterns' => $this->get_homepage_patterns(),
			]
		);
	}

	/**
	 * Load homepage pattern.
	 *
	 * @param string $file_path File path of the pattern.
	 * @return string The pattern markup.
	 */
	private function load_homepage_pattern( $file_path ) {
		$has_donations   = $this->check_service_enabled( 'reader-revenue' );
		$has_newsletters = $this->check_service_enabled( 'newsletters' );
		$donate_block    = $has_donations ? '
		<!-- wp:group {"align":"full","backgroundColor":"primary","className":"has-white-color has-text-color"} -->
		<div class="wp-block-group alignfull has-white-color has-text-color has-primary-background-color has-background"><div class="wp-block-group__inner-container"><!-- wp:spacer {"height":20} -->
		<div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer -->

		<!-- wp:columns -->
		<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center","width":"25%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:25%"><!-- wp:heading -->
		<h2>' . __( 'Support our publication', 'newspack' ) . '</h2>
		<!-- /wp:heading --></div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"75%"} -->
		<div class="wp-block-column" style="flex-basis:75%"><!-- wp:newspack-blocks/donate /--></div>
		<!-- /wp:column --></div>

		<!-- wp:spacer {"height":20} -->
		<div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer --></div></div>
		<!-- /wp:group -->
		' : '';
		$image_url       = 'https://picsum.photos/id/424/600/400	';
		$subscribe_block = $has_newsletters ? '<!-- wp:group {"className":"newspack-pattern subscribe__style-1"} -->
		<div class="wp-block-group newspack-pattern subscribe__style-1"><div class="wp-block-group__inner-container"><!-- wp:media-text {"align":"","mediaPosition":"right","mediaId":131,"mediaType":"image","verticalAlignment":"center","imageFill":true,"backgroundColor":"light-gray"} -->
		<div class="wp-block-media-text has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center is-image-fill has-light-gray-background-color has-background"><figure class="wp-block-media-text__media" style="background-image:url(' . $image_url . ');background-position:50% 50%"><img src="' . $image_url . '" alt="" class="wp-image-131 size-full"/></figure><div class="wp-block-media-text__content"><!-- wp:spacer {"height":32} -->
		<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer -->

		<!-- wp:heading -->
		<h2>' . __( 'Subscribe', 'newspack' ) . '</h2>
		<!-- /wp:heading -->

		<!-- wp:jetpack/mailchimp {"interests":[]} -->
		<!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /-->
		<!-- /wp:jetpack/mailchimp -->

		<!-- wp:spacer {"height":32} -->
		<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer --></div></div>
		<!-- /wp:media-text --></div></div>
		<!-- /wp:group -->' : '';
		return require $file_path;
	}

	/**
	 * Get homepage patterns registered by the Newspack Blocks plugin.
	 *
	 * @param int $index Pattern ID.
	 * @return array|object|bool Homepage pattern (false if not found) or all patterns.
	 */
	private function get_homepage_patterns( $index = null ) {
		$patterns_directory = dirname( NEWSPACK_PLUGIN_FILE ) . '/includes/templates/block-patterns/homepage/';

		// Load a single homepage pattern.
		if ( null !== $index ) {
			$index_padded = str_pad( $index + 1, 2, '0', STR_PAD_LEFT );
			$file_path    = $patterns_directory . $index_padded . '.php';
			if ( file_exists( $file_path ) ) {
				return $this->load_homepage_pattern( $file_path );
			} else {
				return false;
			}
		}

		// Load all homepage patterns.
		foreach ( scandir( $patterns_directory ) as $file_name ) {
			if ( '.' !== $file_name && '..' !== $file_name ) {
				$file_path           = $patterns_directory . $file_name;
				$homepage_patterns[] = $this->load_homepage_pattern( $file_path );
			}
		}

		return $homepage_patterns;
	}

	/**
	 * Update theme
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response containing info.
	 */
	public function api_update_theme_with_mods( $request ) {
		// Set theme before updating theme mods, since a theme might be setting theme mod defaults.
		$theme = $request['theme'];
		Theme_Manager::install_activate_theme( $theme );
		// If the theme has to be installed, the set_theme_mod calls below will – for some reason – have no effect
		// If there's a switch_theme call here, even though it's already called in Theme_Manager::install_activate_theme,
		// correct mods will be saved.
		if ( ! empty( $theme ) ) {
			switch_theme( $theme );
		}

		// Set homepage pattern.
		if ( isset( $request['theme_mods']['homepage_pattern_index'] ) ) {
			$homepage_pattern_index = (int) $request['theme_mods']['homepage_pattern_index'];
			$homepage_pattern       = $this->get_homepage_patterns( $homepage_pattern_index );
			if ( false !== $homepage_pattern ) {
				$homepage_id = get_option( 'page_on_front', false );
				if ( $homepage_id ) {
					wp_update_post(
						[
							'ID'           => $homepage_id,
							'post_content' => $homepage_pattern['content'],
						]
					);
				}
			}
		}

		$theme_mods = $request['theme_mods'];
		foreach ( $theme_mods as $key => $value ) {
			// Media credits are actually options, not theme mods.
			if ( 'newspack_image_credits' === substr( $key, 0, 22 ) ) {
				Newspack_Image_Credits::update_setting( $key, $value );
				continue;
			}

			if ( null !== $value && in_array( $key, $this->media_theme_mods ) ) {
				$value = $value['id'];
			}
			set_theme_mod( $key, $value );
		}
		return self::api_retrieve_theme_and_set_defaults();
	}

	/**
	 * Check if a particular service is enabled.
	 *
	 * @param string $service_name Name of the service.
	 * @return bool True if the service is enabled.
	 */
	private function check_service_enabled( $service_name ) {
		return (bool) get_option( self::SERVICE_ENABLED_OPTION_PREFIX . $service_name, false );
	}

	/**
	 * Get Services step initial data.
	 *
	 * @return WP_REST_Response containing info.
	 */
	public function api_get_services() {
		$response = [
			'reader-revenue'    => [ 'configuration' => [ 'is_service_enabled' => $this->check_service_enabled( 'reader-revenue' ) ] ],
			'newsletters'       => [ 'configuration' => [ 'is_service_enabled' => $this->check_service_enabled( 'newsletters' ) ] ],
			'google-ad-manager' => [ 'configuration' => [ 'is_service_enabled' => $this->check_service_enabled( 'google-ad-manager' ) ] ],
		];
		return rest_ensure_response( $response );
	}

	/**
	 * Update Services step.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response containing info.
	 */
	public function api_update_services( $request ) {
		if ( true === $request['newsletters']['is_service_enabled'] ) {
			$newsletters_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-newsletters' );
			$newsletters_configuration_manager->update_settings( $request['newsletters'] );
		}
		if ( true === $request['reader-revenue']['is_service_enabled'] ) {
			Plugin_Manager::activate( 'woocommerce' );
			$rr_wizard = new Reader_Revenue_Wizard();
			if ( isset( $request['reader-revenue']['donation_data'] ) ) {
				$rr_wizard->update_donation_settings( $request['reader-revenue']['donation_data'] );
			}
			if ( isset( $request['reader-revenue']['stripe_data'] ) ) {
				$stripe_settings            = $request['reader-revenue']['stripe_data'];
				$stripe_settings['enabled'] = true;
				$rr_wizard->update_stripe_settings( $stripe_settings );
			}
		}
		if ( true === $request['google-ad-manager']['is_service_enabled'] ) {
			$service = 'google_ad_manager';
			update_option( Advertising_Wizard::NEWSPACK_ADVERTISING_SERVICE_PREFIX . $service, true );
			if ( isset( $request['google-ad-manager']['networkCode'] ) && ! empty( $request['google-ad-manager']['networkCode'] ) ) {
				$network_code = $request['google-ad-manager']['networkCode'];
				// Update legacy network code in case service account credentials are not set.
				update_option( Advertising_Wizard::OPTION_NAME_LEGACY_NETWORK_CODE, $network_code );
				// Update network code used by authenticated credentials. Ensures use of desired code in case the credentials are for multiple networks.
				update_option( Advertising_Wizard::OPTION_NAME_GAM_NETWORK_CODE, $network_code );
			}
			Plugin_Manager::activate( 'newspack-ads' );
		}

		$available_services = [ 'newsletters', 'reader-revenue', 'google-ad-sense', 'google-ad-manager' ];
		foreach ( $available_services as $service_name ) {
			if ( isset( $request[ $service_name ] ) ) {
				update_option( self::SERVICE_ENABLED_OPTION_PREFIX . $service_name, $request[ $service_name ]['is_service_enabled'] );
			}
		}

		return rest_ensure_response( [] );
	}

	/**
	 * Enqueue Subscriptions Wizard scripts and styles.
	 */
	public function enqueue_scripts_and_styles() {
		parent::enqueue_scripts_and_styles();
		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) !== $this->slug ) {
			return;
		}
		wp_enqueue_script(
			'newspack-setup-wizard',
			Newspack::plugin_url() . '/dist/setup.js',
			$this->get_script_dependencies(),
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		wp_register_style(
			'newspack-setup-wizard',
			Newspack::plugin_url() . '/dist/setup.css',
			$this->get_style_dependencies(),
			NEWSPACK_PLUGIN_VERSION
		);
		wp_style_add_data( 'newspack-setup-wizard', 'rtl', 'replace' );
		wp_enqueue_style( 'newspack-setup-wizard' );
	}

	/**
	 * Hide all menu items besides setup if setup is incomplete.
	 */
	public function hide_non_setup_menu_items() {
		global $submenu;
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}
		foreach ( $submenu['newspack'] as $key => $value ) {
			if ( 'newspack-setup-wizard' !== $value[2] ) {
				unset( $submenu['newspack'][ $key ] );
			}
		}
	}

	/**
	 * If initial setup is incomplete, redirect to Setup Wizard.
	 */
	public function redirect_to_setup() {
		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_newspack' === $screen->id ) {
			$setup_url = Wizards::get_url( 'setup' );
			wp_safe_redirect( esc_url( $setup_url ) );
			exit;
		}
	}

	/**
	 * Should admin bar be shown.
	 *
	 * @param bool $show Whether to show admin bar.
	 * @return boolean Whether admin bar should be shown.
	 */
	public static function show_admin_bar( $show ) {
		if ( $show && isset( $_GET['newspack_design_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		return $show;
	}

	/**
	 * Sanitize theme mods.
	 *
	 * @param array $theme_mods An array of theme mods.
	 */
	public function sanitize_theme_mods( $theme_mods ) {
		return $theme_mods;
	}
}
