<?php
/**
 * Newspack's Analytics Wizard
 *
 * @package Newspack
 */

namespace Newspack;

use Google\Site_Kit_Dependencies\Google\Service\Analytics as Google_Service_Analytics;
use Google\Site_Kit_Dependencies\Google\Service\Analytics\CustomDimension as Google_Service_Analytics_CustomDimension;

use \WP_Error, \WP_Query;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/wizards/class-wizard.php';

/**
 * Easy interface for setting up general store info.
 */
class Analytics_Wizard extends Wizard {

	/**
	 * Name of the option storing site's custom events (serialised).
	 *
	 * @var string
	 */
	public static $custom_events_option_name = 'newspack_analytics_custom_events';

	/**
	 * NTG enabling option name.
	 *
	 * @var string
	 */
	public static $ntg_events_option_name = 'newspack_analytics_ntg_events';

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-analytics-wizard';

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

		// Ensure Site Kit asks for sufficient scopes to add custom dimensions.
		add_filter(
			'googlesitekit_auth_scopes',
			function( array $scopes ) {
				return array_merge( $scopes, [ 'https://www.googleapis.com/auth/analytics.edit' ] );
			},
			1
		);
	}

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return \esc_html__( 'Analytics', 'newspack' );
	}

	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	public function register_api_endpoints() {
		// Create a custom dimension.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/analytics/custom-dimensions',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'create_custom_dimension' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'name'  => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'scope' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Set the custom dimensions.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/analytics/custom-dimensions/(?P<id>[\w:]+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_set_custom_dimensions' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id'   => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'role' => [
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ __CLASS__, 'validate_custom_dimension_name' ],
					],
				],
			]
		);

		// Set custom events.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/analytics/custom-events',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_custom_events' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'events' => [
						'type'     => 'array',
						'required' => true,
						'items'    => [
							'type'       => 'object',
							'properties' => [
								'event_name'      => [
									'type' => 'string',
								],
								'event_category'  => [
									'type' => 'string',
								],
								'event_label'     => [
									'type' => 'string',
								],
								'on'              => [
									'type' => 'string',
									'enum' => [ 'click', 'submit' ],
								],
								'element'         => [
									'type' => 'string',
								],
								'amp_element'     => [
									'type' => 'string',
								],
								'non_interaction' => [
									'type' => 'boolean',
								],
								'is_active'       => [
									'type' => 'boolean',
								],
							],
							'required'   => [ 'event_name', 'event_category', 'on', 'element' ],
						],
					],
				],
			]
		);

		// NTG Events.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/analytics/ntg',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_ntg_events_status' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/analytics/ntg',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_enable_ntg_events' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/analytics/ntg',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'api_disable_ntg_events' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
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
			'newspack-analytics-wizard',
			Newspack::plugin_url() . '/dist/analytics.js',
			[ 'wp-components', 'wp-api-fetch' ],
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		$custom_dimensions          = self::list_custom_dimensions();
		$analytics_connection_error = null;
		if ( is_wp_error( $custom_dimensions ) ) {
			$analytics_connection_error = $custom_dimensions->get_error_message();
		}
		$custom_events = get_option( self::$custom_events_option_name, '[]' );
		\wp_localize_script(
			'newspack-analytics-wizard',
			'newspack_analytics_wizard_data',
			[
				'customEvents'             => json_decode( $custom_events ),
				'customDimensions'         => $custom_dimensions,
				'analyticsConnectionError' => $analytics_connection_error,
				'customDimensionsOptions'  => array_merge(
					[
						[
							'value' => '',
							'label' => __( 'none', 'newspack' ),
						],
					],
					array_map(
						function ( $item ) {
							return $item['option'];
						},
						self::get_custom_dimensions_config()
					)
				),
			]
		);

		\wp_register_style(
			'newspack-analytics-wizard',
			Newspack::plugin_url() . '/dist/analytics.css',
			$this->get_style_dependencies(),
			NEWSPACK_PLUGIN_VERSION
		);
		\wp_style_add_data( 'newspack-analytics-wizard', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-analytics-wizard' );
	}

	/**
	 * Get custom dimension option name.
	 *
	 * @param string $role Custom dimensions's role.
	 */
	public static function get_custom_dimensions_option_name( $role ) {
		if ( empty( $role ) ) {
			return null;
		}
		return 'newspack_analytics_' . $role . '_custom_dimension_id';
	}

	/**
	 * Get extendable custom dimensions configuration.
	 */
	public static function get_custom_dimensions_config() {
		$default_dimensions = [
			[
				'role'   => 'category',
				'option' => [
					'value' => 'category',
					'label' => __( 'Category', 'newspack' ),
				],
			],
			[
				'role'   => 'author',
				'option' => [
					'value' => 'author',
					'label' => __( 'Author', 'newspack' ),
				],
			],
			[
				'role'   => 'word_count',
				'option' => [
					'value' => 'word_count',
					'label' => __( 'Word count', 'newspack' ),
				],
			],
			[
				'role'   => 'publish_date',
				'option' => [
					'value' => 'publish_date',
					'label' => __( 'Publish date', 'newspack' ),
				],
			],
		];
		$custom_dimensions  = apply_filters(
			'newspack_custom_dimensions',
			$default_dimensions
		);
		return array_map(
			function ( $item ) {
				$item['gaID'] = get_option( self::get_custom_dimensions_option_name( $item['role'] ) );
				return $item;
			},
			$custom_dimensions
		);
	}

	/**
	 * Get GA utils.
	 *
	 * @return object authenticated Google_Service_Analytics service and Site Kit settings
	 */
	public static function get_ga_utils() {
		$analytics = \Newspack\Google_Services_Connection::get_site_kit_analytics_module();

		if ( $analytics && $analytics->is_connected() ) {
			$authentication = \Newspack\Google_Services_Connection::get_site_kit_authentication();

			if ( false === $authentication->is_authenticated() ) {
				return new WP_Error( 'newspack_analytics_sitekit_authentication', __( 'Please authenticate with the Site Kit plugin.', 'newspack' ) );
			}

			// A user might have authenticated with Site Kit before this version of the plugin,
			// which updated authorization scopes, was deployed.
			$unsatisfied_scopes = $authentication->get_oauth_client()->get_unsatisfied_scopes();
			if ( 0 !== count( $unsatisfied_scopes ) ) {
				return new WP_Error(
					'newspack_analytics_sitekit_unsatisfied_scopes',
					__( 'Please re-authorize', 'newspack' ) .
					' <a href="' . get_admin_url() . 'admin.php?page=googlesitekit-dashboard">' .
					__( 'Site Kit plugin', 'newspack' ) .
					'</a> ' .
					__( 'to allow updating Google Analytics settings.', 'newspack' )
				);
			}

			$client = $authentication->get_oauth_client()->get_client();

			return [
				'analytics_service' => new Google_Service_Analytics( $client ),
				'settings'          => $analytics->get_settings()->get(),
			];
		} else {
			return new WP_Error( 'newspack_analytics_sitekit_disconnected', __( 'Please connect Analytics in the Site Kit plugin.', 'newspack' ) );
		}
	}

	/**
	 * List Custom Dimensions from connected GA account, marking the configured ones with `role` attribute.
	 *
	 * @return Array|WP_Error Array of custom dimensions on success, or WP_Error object on failure.
	 */
	public static function list_custom_dimensions() {
		$ga_utils = self::get_ga_utils();
		if ( is_wp_error( $ga_utils ) ) {
			return $ga_utils;
		}
		try {
			$custom_dimensions = $ga_utils['analytics_service']->management_customDimensions->listManagementCustomDimensions(
				$ga_utils['settings']['accountID'],
				$ga_utils['settings']['propertyID']
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'newspack_analytics', __( 'Error retrieving custom dimensions.', 'newspack' ) );
		}
		if ( isset( $custom_dimensions['items'] ) ) {
			return array_map(
				function ( $dimension ) {
					// Assign role to custom dimension if it's found as a saved option.
					foreach ( self::get_custom_dimensions_config() as $config_item ) {
						$saved_dimension_id = get_option( self::get_custom_dimensions_option_name( $config_item['role'] ) );
						if ( $saved_dimension_id === $dimension['id'] ) {
							// Assign role if it's found as saved in option.
							$dimension->role = $config_item['role'];
						}
					}
					return $dimension;
				},
				$custom_dimensions['items']
			);
		}
		return new WP_Error( 'newspack_analytics', __( 'Error retrieving custom dimensions.', 'newspack' ) );
	}

	/**
	 * List configured Custom Dimensions.
	 *
	 * @return Array|WP_Error Array of configured custom dimensions on success, or WP_Error object on failure.
	 */
	public static function list_configured_custom_dimensions() {
		return array_filter(
			self::get_custom_dimensions_config(),
			function( $item ) {
				return $item['gaID'];
			}
		);
	}

	/**
	 * Validate custom dimension name.
	 *
	 * @param String $name Name.
	 */
	public static function validate_custom_dimension_name( $name ) {
		$valid_roles   = array_map(
			function ( $item ) {
				return $item['role'];
			},
			self::get_custom_dimensions_config()
		);
		$valid_roles[] = '';
		return in_array( $name, $valid_roles );
	}

	/**
	 * Create a Custom Dimension.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return Object|WP_Error Object on success, or WP_Error object on failure.
	 */
	public static function create_custom_dimension( $request ) {
		$ga_utils = self::get_ga_utils();
		if ( is_wp_error( $ga_utils ) ) {
			return $ga_utils;
		}

		try {
			$custom_dimension_body = new Google_Service_Analytics_CustomDimension();
			$custom_dimension_body->setName( $request['name'] );
			$custom_dimension_body->setScope( $request['scope'] );
			$custom_dimension_body->setActive( true );

			return $ga_utils['analytics_service']->management_customDimensions->insert(
				$ga_utils['settings']['accountID'],
				$ga_utils['settings']['propertyID'],
				$custom_dimension_body
			);
		} catch ( \Throwable $error ) {
			return new WP_Error( 'newspack_analytics', __( 'Error when creating custom dimension.', 'newspack' ) );
		}
	}

	/**
	 * Set custom dimension as the category-reporting dimension.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return Object|WP_Error Object on success, or WP_Error object on failure.
	 */
	public static function api_set_custom_dimensions( $request ) {
		self::set_custom_dimension( $request['id'], self::get_custom_dimensions_option_name( $request['role'] ) );
		return self::list_custom_dimensions();
	}

	/**
	 * Set custom dimension option.
	 *
	 * @param string $dimension_id Dimension id.
	 * @param string $dimension_option_name Dimension option name.
	 */
	public static function set_custom_dimension( $dimension_id, $dimension_option_name = null ) {
		global $wpdb;

		// Unset the option that was there before.
		$options_table_name = $wpdb->prefix . 'options';
		$data               = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT option_name FROM $options_table_name WHERE option_value=%s;", $dimension_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		foreach ( $data as $result ) {
			delete_option( $result->option_name );
		}
		update_option( $dimension_option_name, $dimension_id );
	}

	/**
	 * Update custom events collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return Object|WP_Error Object on success, or WP_Error object on failure.
	 */
	public static function set_custom_events( $request ) {
		$custom_events = array_map(
			function ( $event ) {
				$event = (array) $event;
				if ( ! isset( $event['id'] ) ) {
					$event['id'] = uniqid();
				}
				return $event;
			},
			$request['events']
		);
		if ( update_option( self::$custom_events_option_name, wp_json_encode( $custom_events ) ) ) {
			return [ 'events' => $custom_events ];
		} else {
			return new WP_Error( 'newspack_analytics', __( 'Error when setting custom events.', 'newspack' ) );
		}
	}

	/**
	 * NTG events status.
	 *
	 * @return bool Status of NTG events.
	 */
	public static function ntg_events_enabled() {
		return 'enabled' === get_option( self::$ntg_events_option_name, 'enabled' );
	}

	/**
	 * NTG events status.
	 *
	 * @return Object|WP_Error Object on success, or WP_Error object on failure.
	 */
	public static function api_ntg_events_status() {
		return rest_ensure_response(
			[
				'enabled' => self::ntg_events_enabled(),
			]
		);
	}

	/**
	 * Enable NTG events.
	 *
	 * @return Object|WP_Error Object on success, or WP_Error object on failure.
	 */
	public static function api_enable_ntg_events() {
		if ( update_option( self::$ntg_events_option_name, 'enabled' ) ) {
			return self::api_ntg_events_status();
		} else {
			return new WP_Error( 'newspack_analytics', __( 'Error when setting NTG events status.', 'newspack' ) );
		}
	}

	/**
	 * Disable NTG events.
	 *
	 * @return Object|WP_Error Object on success, or WP_Error object on failure.
	 */
	public static function api_disable_ntg_events() {
		if ( update_option( self::$ntg_events_option_name, 'disabled' ) ) {
			return self::api_ntg_events_status();
		} else {
			return new WP_Error( 'newspack_analytics', __( 'Error when setting NTG events status.', 'newspack' ) );
		}
	}
}
