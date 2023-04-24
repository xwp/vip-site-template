<?php
/**
 * Newspack Ads Bidding GAM Integration.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Integrations;

use Newspack_Ads\Bidding;
use Newspack_Ads\Settings;
use Newspack_Ads\Providers\GAM_Model;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Bidding GAM Integration Class.
 */
final class Bidding_GAM {

	// The name of the company to be created on GAM.
	const ADVERTISER_NAME = 'Newspack Header Bidding';

	// Amount of creatives to attach per line item.
	// This number is arbitrary and should match at least the number of ad units displayed on a page.
	const CREATIVE_COUNT = 10;

	// Batch size of API requests for the creation of line item and creative association.
	const LICA_BATCH_SIZE = 500;

	/**
	 * Whether GAM is connected.
	 *
	 * @var bool
	 */
	protected static $connected = null;

	/**
	 * Header Bidding GAM Advertiser
	 *
	 * @var array
	 */
	protected static $advertiser = null;

	/**
	 * Header Bidding GAM Targeting Keys
	 *
	 * @var int[]
	 */
	protected static $targeting_keys = null;

	/**
	 * Header Bidding GAM Creatives
	 *
	 * @var array[]
	 */
	protected static $creatives = null;

	/**
	 * Default order config.
	 *
	 * @var array
	 */
	protected static $default_order_config = [
		'price_granularity_key' => Bidding::DEFAULT_PRICE_GRANULARITY,
		'revenue_share'         => 0,
		'bidders'               => [],
	];

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
		add_action( 'newspack_ads_after_update_setting', [ __CLASS__, 'update_gam_from_setting_update' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Register API endpoints.
	 */
	public static function register_api_endpoints() {
		register_rest_route(
			Settings::API_NAMESPACE,
			'/bidding/gam/orders',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_orders' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		register_rest_route(
			Settings::API_NAMESPACE,
			'/bidding/gam/order',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_order' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			Settings::API_NAMESPACE,
			'/bidding/gam/order',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_archive_order' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			Settings::API_NAMESPACE,
			'/bidding/gam/order',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update_order' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'id'     => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'config' => [
						'required'          => true,
						'sanitize_callback' => [ __CLASS__, 'sanitize_order_config' ],
					],
				],
			]
		);
		register_rest_route(
			Settings::API_NAMESPACE,
			'/bidding/gam/lica_config',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_lica_config' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			Settings::API_NAMESPACE,
			'/bidding/gam/create',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'api_create' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'id'     => [
						'sanitize_callback' => 'absint',
					],
					'type'   => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ __CLASS__, 'validate_create_type' ],
					],
					'config' => [
						'required'          => true,
						'sanitize_callback' => [ __CLASS__, 'sanitize_order_config' ],
					],
					'batch'  => [
						'sanitize_callback' => 'absint',
					],
					'fixing' => [
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);
	}

	/**
	 * API method for getting the current GAM order.
	 *
	 * @return WP_REST_Response containing the current order.
	 */
	public static function api_get_orders() {
		return \rest_ensure_response( self::get_advertiser_orders() );
	}

	/**
	 * API method for getting the current GAM order.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the current order.
	 */
	public static function api_get_order( $request ) {
		return \rest_ensure_response( self::get_order( $request->get_param( 'id' ) ) );
	}

	/**
	 * API method for archiving the current GAM order.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Rest_Response containing a boolean indicating success.
	 */
	public static function api_archive_order( $request ) {
		return \rest_ensure_response( self::archive_order( $request->get_param( 'id' ) ) );
	}

	/**
	 * API method for archiving the current GAM order.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Rest_Response containing a boolean indicating success.
	 */
	public static function api_update_order( $request ) {
		return \rest_ensure_response(
			self::update_order(
				$request->get_param( 'id' ),
				$request->get_param( 'config' )
			)
		);
	}



	/**
	 * Get a sanitized order config.
	 *
	 * @param array $order_config Config to sanitize.
	 *
	 * @return array $order_config Sanitized config.
	 */
	public static function sanitize_order_config( $order_config ) {
		$sanitized_config = [];
		if ( ! is_array( $order_config ) ) {
			$order_config = [];
		}
		foreach ( $order_config as $key => $value ) {
			if ( ! is_array( $value ) ) {
				$sanitized_config[ $key ] = sanitize_text_field( $value );
			} else {
				$sanitized_config[ $key ] = self::sanitize_order_config( $value );
			}
		}
		return $sanitized_config;
	}

	/**
	 * Validate if given type is a valid type for creating a new GAM entity.
	 *
	 * @param string $type Type to validate.
	 *
	 * @return bool Whether the type is valid.
	 */
	public static function validate_create_type( $type ) {
		return in_array( $type, [ 'order', 'line_items', 'creatives' ], true );
	}

	/**
	 * API method for creating a new GAM order.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the created order.
	 */
	public static function api_create( $request ) {
		$config = wp_parse_args(
			$request->get_param( 'config' ),
			self::$default_order_config
		);
		$type   = $request->get_param( 'type' );
		$fixing = $request->get_param( 'fixing' ) ?? false;
		switch ( $type ) {
			case 'order':
				$result = self::create_order( $config );
				break;
			case 'line_items':
				$result = self::create_line_items( $request->get_param( 'id' ), $fixing );
				break;
			case 'creatives':
				$result = self::associate_creatives( $request->get_param( 'id' ), $request->get_param( 'batch' ) );
				break;
			default:
				$result = new \WP_Error(
					'newspack_ads_bidding_gam_error',
					__( 'Invalid type.', 'newspack-ads' ),
					[ 'status' => 400 ]
				);
		}
		if ( 'order' === $type ) {
			$order_id = $result['order_id'];
		} else {
			$order_id = $request->get_param( 'id' );
		}
		return \rest_ensure_response( \is_wp_error( $result ) ? $result : self::get_order( $order_id ) );
	}

	/**
	 * API method for getting the configuration for creative and line item association.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the created order.
	 */
	public static function api_get_lica_config( $request ) {
		return \rest_ensure_response( self::get_lica_config( $request->get_param( 'id' ) ) );
	}

	/**
	 * Enqueue admin scripts.
	 */
	public static function enqueue_scripts() {

		$slug = 'newspack-advertising-wizard';
		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) !== $slug ) {
			return;
		}

		\wp_enqueue_script(
			'newspack-ads-bidding-gam',
			plugins_url( '../../dist/header-bidding-gam.js', __FILE__ ),
			[ 'wp-components', 'wp-api-fetch' ],
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/header-bidding-gam.js' ),
			true
		);
		wp_localize_script(
			'newspack-ads-bidding-gam',
			'newspack_ads_bidding_gam',
			[
				'network_code'    => GAM_Model::get_active_network_code(),
				'lica_batch_size' => self::LICA_BATCH_SIZE,
			]
		);
		\wp_register_style(
			'newspack-ads-bidding-gam',
			plugins_url( '../../dist/header-bidding-gam.css', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/header-bidding-gam.css' )
		);
		\wp_style_add_data( 'newspack-ads-bidding-gam', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-ads-bidding-gam' );
	}

	/**
	 * Get prefixed option name.
	 *
	 * @param string $name Option name.
	 *
	 * @return string Prefixed option name.
	 */
	private static function get_option_name( $name ) {
		return Settings::OPTION_NAME_PREFIX . 'bidding_gam_' . $name;
	}

	/**
	 * Get and caches, if not yet checked, whether GAM is connected.
	 *
	 * @return bool Whether GAM is connected.
	 */
	private static function is_connected() {
		return ! empty( GAM_Model::get_api() );
	}

	/**
	 * Update GAM orders and line items from setting update.
	 *
	 * @param bool   $updated Whether the setting was updated.
	 * @param string $section The setting section.
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 */
	public static function update_gam_from_setting_update( $updated, $section, $key, $value ) {
		if ( ! $updated || Bidding::SETTINGS_SECTION_NAME !== $section ) {
			return;
		}

		// Skip if GAM is not connected.
		if ( ! self::is_connected() ) {
			return;
		}

		// Initial setup on activation.
		if ( 'active' === $key && true === $value ) {
			self::initial_setup();
		}
	}

	/**
	 * Initial GAM setup for header bidding.
	 *
	 * Creates (if necessary) and stores the advertiser, targeting keys and
	 * creatives required for the order and its line items.
	 *
	 * @return array|\WP_Error Created GAM config or \WP_Error if setup errors.
	 */
	private static function initial_setup() {
		if ( ! self::is_connected() ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Google Ad Manager is not connected.', 'newspack-ads' ) );
		}
		$advertiser = self::get_advertiser();
		if ( \is_wp_error( $advertiser ) ) {
			return $advertiser;
		}
		$targeting_keys = self::get_targeting_keys();
		if ( \is_wp_error( $targeting_keys ) ) {
			return $targeting_keys;
		}
		$creatives = self::get_creatives( $advertiser['id'] );
		if ( \is_wp_error( $creatives ) ) {
			return $creatives;
		}
		// Store GAM config.
		$config = [
			'advertiser_id'     => $advertiser['id'],
			'targeting_key_ids' => $targeting_keys,
			'creative_ids'      => array_column( $creatives, 'id' ),
		];
		update_option( self::get_option_name( 'config' ), $config );
		return $config;
	}

	/**
	 * Get or create advertiser.
	 *
	 * @return array|\WP_Error The serialized advertiser or \WP_Error if creation fails.
	 */
	private static function get_advertiser() {
		if ( ! is_null( self::$advertiser ) ) {
			return self::$advertiser;
		}
		$api              = GAM_Model::get_api();
		$advertisers      = $api->advertisers->get_serialized_advertisers();
		$advertiser_index = array_search( self::ADVERTISER_NAME, array_column( $advertisers, 'name' ) );
		if ( false !== $advertiser_index ) {
			$advertiser = $advertisers[ $advertiser_index ];
		} else {
			try {
				$advertiser = $api->advertisers->create_advertiser( self::ADVERTISER_NAME );
			} catch ( \Exception $e ) {
				return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
			}
		}
		self::$advertiser = $advertiser;
		return $advertiser;
	}

	/**
	 * If not yet created, create a key-val segments.
	 *
	 * @return int[]|\WP_Error Associate array of created targeting keys IDs or error.
	 */
	private static function get_targeting_keys() {
		if ( ! is_null( self::$targeting_keys ) ) {
			return self::$targeting_keys;
		}
		$key_names = [
			'hb_pb'     => [],
			'hb_bidder' => array_keys( \Newspack_Ads\get_bidders() ),
		];
		$api       = GAM_Model::get_api();
		try {
			$targeting_keys = [];
			foreach ( $key_names as $key_name => $key_values ) {
				$result                      = $api->targeting_keys->create_targeting_key( $key_name, $key_values );
				$values                      = array_merge( $result['created_values'], $result['found_values'] );
				$targeting_keys[ $key_name ] = [
					'id'         => $result['targeting_key']->getId(),
					'values_ids' => array_map(
						function( $targeting_value ) {
							return $targeting_value->getId();
						},
						$values
					),
				];
			}
			self::$targeting_keys = $targeting_keys;
			return $targeting_keys;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
	}

	/**
	 * Get or create Prebid creatives.
	 *
	 * @param string $advertiser_id Advertiser ID to register creatives with.
	 */
	private static function get_creatives( $advertiser_id ) {
		if ( ! is_null( self::$creatives ) ) {
			return self::$creatives;
		}
		$api = GAM_Model::get_api();
		try {
			$creatives = $api->creatives->get_serialized_creatives_by_advertiser( $advertiser_id );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
		if ( ! $creatives || self::CREATIVE_COUNT > count( $creatives ) ) {
			ob_start();
			// Prebid Universal Creative: https://github.com/prebid/prebid-universal-creative.
			?>
			<script src="https://cdn.jsdelivr.net/npm/prebid-universal-creative@latest/dist/creative.js"></script>
			<script>
				var ucTagData = {};
				ucTagData.adServerDomain = "";
				ucTagData.pubUrl = "%%PATTERN:url%%";
				ucTagData.targetingMap = %%PATTERN:TARGETINGMAP%%;
				ucTagData.hbPb = "%%PATTERN:hb_pb%%";
				try {
						ucTag.renderAd(document, ucTagData);
				} catch (e) {
						console.log(e);
				}
			</script>
			<?php
			$snippet     = ob_get_clean();
			$base_config = [
				'advertiser_id'            => $advertiser_id,
				'xsi_type'                 => 'ThirdPartyCreative',
				'width'                    => 1,
				'height'                   => 1,
				'is_safe_frame_compatible' => true,
				'snippet'                  => $snippet,
			];
			$configs     = [];
			for ( $i = 0; $i < self::CREATIVE_COUNT; $i++ ) {
				$configs[] = wp_parse_args(
					[
						'name' => sprintf( '%s - %d', self::ADVERTISER_NAME, $i + 1 ),
					],
					$base_config
				);
			}
			try {
				$creatives = $api->creatives->create_creatives( $configs );
			} catch ( \Exception $e ) {
				return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
			}
		}
		self::$creatives = $creatives;
		return $creatives;
	}

	/**
	 * Get all orders from our configured advertiser.
	 *
	 * @return array[] The advertiser orders.
	 */
	private static function get_advertiser_orders() {
		if ( ! self::is_connected() ) {
			return new \WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Not authenticated.', 'newspack-ads' ),
				[
					'status' => '500',
				]
			);
		}
		$api = GAM_Model::get_api();
		try {
			$orders         = $api->orders->get_serialized_orders(
				$api->orders->get_orders_by_advertiser( self::get_advertiser()['id'] )
			);
			$orders_configs = get_option( self::get_option_name( 'orders' ), [] );
			return array_map(
				function( $order ) use ( $orders_configs ) {
					$order_config = array_filter(
						$orders_configs,
						function( $order_config ) use ( $order ) {
							return $order['id'] === $order_config['order_id'];
						}
					);
					if ( empty( $order_config ) ) {
						return $order;
					}
					return array_merge( $order, array_shift( $order_config ) );
				},
				$orders
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
	}

	/**
	 * Get local order config for a given GAM Order ID.
	 *
	 * @param int   $order_id GAM Order ID.
	 * @param array $order    Optional GAM Order data.
	 *
	 * @return array The stored order config.
	 */
	private static function get_order_local_config( $order_id, $order = [] ) {
		$orders       = get_option( self::get_option_name( 'orders' ), [] );
		$key          = sprintf( 'order-%s', $order_id );
		$order_config = $orders[ $key ] ?? array_filter(
			$orders,
			function( $config ) use ( $order_id ) {
				return $config['order_id'] === $order_id;
			}
		);

		// Update local order config if not properly keyed.
		if ( ! empty( $order_config ) && ( ! isset( $orders[ $key ] ) ) ) {
			$order_config = self::store_order( $order, $order_config );
		}

		// Update local order if not found and has order data.
		if ( empty( $order_config ) && ! empty( $order ) ) {
			$order_config = self::store_order( $order );
		}

		if ( empty( $order_config ) ) {
			return false;
		}

		return wp_parse_args( $order_config, self::$default_order_config );
	}

	/**
	 * Update order local config.
	 *
	 * @param int   $order_id The GAM Order ID.
	 * @param array $data     The data to update with.
	 *
	 * @return bool|\WP_Error Whether the config was updated or WP_Error if order does not exist.
	 */
	private static function update_order_local_config( $order_id, $data ) {
		if ( ! self::get_order_local_config( $order_id ) ) {
			return new \WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Order not found.', 'newspack-ads' ),
				[
					'status' => '500',
				]
			);
		}
		$orders         = get_option( self::get_option_name( 'orders' ), [] );
		$key            = sprintf( 'order-%s', $order_id );
		$orders[ $key ] = wp_parse_args( $data, $orders[ $key ] );
		return update_option( self::get_option_name( 'orders' ), $orders );
	}
	/**
	 * Store GAM order config locally.
	 *
	 * @param array $order Serialized GAM Order.
	 * @param array $order_config     Optional order config.
	 *
	 * @return array $order_config The stored order config.
	 */
	private static function store_order( $order, $order_config = [] ) {
		$order_config  = array_merge(
			wp_parse_args( $order_config, self::$default_order_config ),
			[
				'order_id'   => $order['id'],
				'order_name' => $order['name'],
			]
		);
		$option_name   = self::get_option_name( 'orders' );
		$stored_orders = get_option( $option_name, [] );
		$key           = sprintf( 'order-%s', $order['id'] );
		update_option( $option_name, array_merge( $stored_orders, [ $key => $order_config ] ) );
		return $order_config;
	}

	/**
	 * Get order config for a given GAM Order ID.
	 *
	 * @param int     $order_id     GAM Order ID.
	 * @param boolean $fetch_remote Whether to fetch the order from GAM.
	 *
	 * @return array The stored order config.
	 */
	private static function get_order( $order_id, $fetch_remote = false ) {
		if ( ! self::is_connected() ) {
			return new \WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Not authenticated.', 'newspack-ads' ),
				[
					'status' => '500',
				]
			);
		}

		$config = self::get_order_local_config( $order_id );

		if ( empty( $config ) || true === $fetch_remote ) {
			$api = GAM_Model::get_api();
			try {
				$order = $api->orders->get_orders_by_id( [ $order_id ] );
			} catch ( \Exception $e ) {
				return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
			}
			if ( empty( $order ) ) {
				return new \WP_Error(
					'newspack_ads_bidding_gam_order_not_found_gam',
					__( 'Order not found in Google Ad Manager.', 'newspack-ads' ),
					[
						'status' => '404',
					]
				);
			}
			$order = $api->orders->get_serialized_orders( $order )[0];

			$config = self::get_order_local_config( $order_id, $order );
		}

		return $config;
	}

	/**
	 * Archive the order for a given price granularity.
	 *
	 * @param string $order_id The GAM Order ID.
	 *
	 * @return boolean|\WP_Error True if order was successfully archived or error.
	 */
	private static function archive_order( $order_id ) {
		if ( ! self::is_connected() ) {
			return new \WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Not authenticated.', 'newspack-ads' ),
				[
					'status' => '500',
				]
			);
		}

		$orders    = get_option( self::get_option_name( 'orders' ), [] );
		$order_key = array_search( $order_id, array_column( $orders, 'order_id' ) );
		if ( false !== $order_key ) {
			// If found in local storage, remove the local order regardless of the GAM API result.
			unset( $orders[ $order_key ] );
			update_option( self::get_option_name( 'orders' ), $orders );
		}

		try {
			$api = GAM_Model::get_api();
			$api->orders->archive_order( [ $order_id ] );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Create order and line items based on price granularity.
	 *
	 * @param array $order_config Optional order config.
	 *
	 * @return array|\WP_Error The serialized order or \WP_Error if creation fails.
	 */
	private static function create_order( $order_config = [] ) {
		$config = self::initial_setup();
		if ( \is_wp_error( $config ) ) {
			return $config;
		}

		$order_config = wp_parse_args( $order_config, self::$default_order_config );

		$price_granularity = Bidding::get_price_granularity( $order_config['price_granularity_key'] );

		if ( false === $price_granularity ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Invalid price granularity', 'newspack-ads' ) );
		}

		try {
			$api   = GAM_Model::get_api();
			$order = $api->orders->create_order( $order_config['order_name'], $config['advertiser_id'] );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
		if ( \is_wp_error( $order ) ) {
			return $order;
		}

		return self::store_order( $order, $order_config );
	}

	/**
	 * Update an existing order configuration.
	 *
	 * Supported changes are the revenue share and the targeted bidders.
	 *
	 * @param int   $order_id     The GAM Order ID.
	 * @param array $order_config The order config to update.
	 *
	 * @return array|\WP_Error The updated order config or \WP_Error if update fails.
	 */
	private static function update_order( $order_id, $order_config ) {
		$order_config = wp_parse_args( $order_config, self::get_order_local_config( $order_id ) );
		try {
			self::create_line_items( $order_config, true );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
		self::update_order_local_config( $order_id, $order_config );
		return $order_config;
	}

	/**
	 * Validate order's existing line items.
	 *
	 * @param int $order_id The GAM Order ID.
	 *
	 * @return int[] Map of existing line item IDs keyed by their cost value per unit in micro amount.
	 */
	private static function validate_order_line_items( $order_id ) {
		$api        = GAM_Model::get_api();
		$line_items = $api->line_items->get_line_items_by_order_id( $order_id );
		$value_map  = [];
		foreach ( $line_items as $line_item ) {
			$value_map[ $line_item->getCostPerUnit()->getMicroAmount() ] = $line_item->getId();
		}
		return $value_map;
	}

	/**
	 * Create GAM line items based on price granularity
	 *
	 * @param int|array $order_id_or_config The GAM Order ID or the order config.
	 * @param boolean   $validate           Wether to validate order's existing line items before creating.
	 *
	 * @return LineItem[] Array of line items.
	 */
	private static function create_line_items( $order_id_or_config, $validate = false ) {

		$config = get_option( self::get_option_name( 'config' ) );
		if ( ! $config ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing config', 'newspack-ads' ) );
		}

		if ( is_array( $order_id_or_config ) ) {
			$order_config = wp_parse_args( $order_id_or_config, self::$default_order_config );
			if ( ! isset( $order_config['order_id'] ) || ! $order_config['order_id'] ) {
				return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing order ID', 'newspack-ads' ) );
			}
			$order_id = $order_config['order_id'];
		} else {
			$order_id     = $order_id_or_config;
			$order_config = self::get_order_local_config( $order_id );
		}

		if ( false === $order_config ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing order config', 'newspack-ads' ) );
		}

		$price_granularity = Bidding::get_price_granularity( $order_config['price_granularity_key'] );

		if ( false === $price_granularity ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Invalid price granularity', 'newspack-ads' ) );
		}

		$order_line_items = [];
		if ( true === $validate ) {
			$order_line_items = self::validate_order_line_items( $order_id );
		}

		// Sort buckets by max value.
		$buckets = $price_granularity['buckets'];
		usort(
			$buckets,
			function( $a, $b ) {
				return $a['max'] > $b['max'] ? 1 : -1;
			}
		);

		// Assume all buckets share the same precision.
		$precision = Bidding::DEFAULT_BUCKET_PRECISION;

		$buckets_max        = array_column( $buckets, 'max' );
		$buckets_increments = array_column( $buckets, 'increment' );

		$start = self::get_number_to_micro( min( $buckets_increments ), $precision );
		$max   = self::get_number_to_micro( max( $buckets_max ), $precision );

		$current        = $start;
		$prices         = [];
		$current_bucket = $buckets[0];
		while ( $current <= $max ) {

			// Get next available bucket if capped by current bucket.
			$current_bucket_max = self::get_number_to_micro( $current_bucket['max'], $precision );
			if ( $current > $current_bucket_max ) {
				$current_bucket = $buckets[ array_search( $current_bucket_max, $buckets_max ) + 1 ];
			}

			// Exit if no more buckets.
			if ( ! isset( $current_bucket ) ) {
				break;
			}

			// Increment prices.
			$prices[]  = $current;
			$increment = self::get_number_to_micro( $current_bucket['increment'], $precision );
			$current  += $increment;
		}

		// Add the last price if not yet accounted for.
		if ( end( $prices ) < $max ) {
			$prices[] = $max;
		}

		// GAM supports up to 450 line items.
		if ( 450 < count( $prices ) ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Unsupported amount of line items.', 'newspack-ads' ) );
		}

		$api = GAM_Model::get_api();

		// Batch create `hb_pb` values for all prices.
		$pb_targeting_keys_result = $api->targeting_keys->create_targeting_key(
			'hb_pb',
			array_map(
				function( $price ) {
					return self::get_number_to_price_string( self::get_micro_to_number( $price ) );
				},
				$prices
			)
		);
		$pb_targeting_keys_values = array_merge( $pb_targeting_keys_result['found_values'], $pb_targeting_keys_result['created_values'] );
		// Store result in value map for line item targeting.
		$pb_targeting_key_id = $pb_targeting_keys_result['targeting_key']->getId();
		$pb_values           = [];
		foreach ( $pb_targeting_keys_values as $value ) {
			$pb_values[ $value->getName() ] = $value->getId();
		}

		// Create `hb_bidder` values for selected bidders.
		if ( isset( $order_config['bidders'] ) && ! empty( $order_config['bidders'] ) ) {
			$bidders        = $order_config['bidders'];
			$bidders_result = $api->targeting_keys->create_targeting_key(
				'hb_bidder',
				$bidders
			);
			$bidders_values = array_merge( $bidders_result['found_values'], $bidders_result['created_values'] );
			$bidders_values = array_map(
				function( $value ) {
					return $value->getId();
				},
				$bidders_values
			);
		}

		$line_item_configs = [];
		foreach ( $prices as $price_micro ) {
			$price_number     = self::get_micro_to_number( $price_micro );
			$price_str        = self::get_number_to_price_string( $price_number );
			$custom_targeting = [
				$pb_targeting_key_id => [
					$pb_values[ $price_str ],
				],
			];
			if ( isset( $bidders_result, $bidders_values ) && ! empty( $bidders_values ) ) {
				$custom_targeting[ $bidders_result['targeting_key']->getId() ] = $bidders_values;
			}
			$config = [
				'id'                      => isset( $order_line_items[ $price_micro ] ) ? $order_line_items[ $price_micro ] : null,
				'name'                    => sprintf( '%s - %s', self::ADVERTISER_NAME, $price_str ),
				'order_id'                => $order_id,
				'start_date_time_type'    => 'IMMEDIATELY',
				'unlimited_end_date_time' => true,
				'line_item_type'          => 'PRICE_PRIORITY',
				'cost_type'               => 'CPM',
				'creative_rotation_type'  => 'EVEN',
				'primary_goal'            => [
					'goal_type' => 'NONE',
				],
				'cost_per_unit'           => [
					'micro_amount' => $price_micro,
				],
				'targeting'               => [
					'custom_targeting' => $custom_targeting,
				],
				'creative_placeholders'   => \Newspack_Ads\get_bidders_sizes(),
			];
			if ( isset( $order_config['revenue_share'] ) && 0 < absint( $order_config['revenue_share'] ) ) {
				$config['cost_per_unit']['micro_amount_value'] = $price_micro - ( $price_micro * absint( $order_config['revenue_share'] ) / 100 );
			}
			$line_item_configs[] = $config;
		}

		try {
			$line_items = $api->line_items->create_or_update_line_items( $line_item_configs );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
		if ( \is_wp_error( $line_items ) ) {
			return $line_items;
		}

		// Update order config with line item IDs.
		self::update_order_local_config(
			$order_id,
			[
				'line_item_ids' => array_map(
					function( $line_item ) {
						return $line_item->getId();
					},
					$line_items
				),
			]
		);

		return $line_items;
	}

	/**
	 * Get config for price granularity order line items to creatives association.
	 *
	 * @param int $order_id The GAM Order ID.
	 *
	 * @return array[] List of Line Item Creative Association configuration.
	 */
	public static function get_lica_config( $order_id ) {

		$config = get_option( self::get_option_name( 'config' ) );
		if ( ! $config ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing config', 'newspack-ads' ) );
		}

		$order_config = self::get_order_local_config( $order_id );
		if ( false === $order_config ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing order config', 'newspack-ads' ) );
		}

		if ( ! isset( $order_config['line_item_ids'] ) || empty( $order_config['line_item_ids'] ) ) {
			return new \WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Missing line item IDs.', 'newspack-ads' ),
				[
					'status' => '404',
				]
			);
		}

		$lica_configs = [];
		foreach ( $order_config['line_item_ids'] as $line_item_id ) {
			foreach ( $config['creative_ids'] as $creative_id ) {
				$lica_configs[] = [
					'line_item_id' => $line_item_id,
					'creative_id'  => $creative_id,
				];
			}
		}
		return $lica_configs;
	}

	/**
	 * Associate price granularity order line items to creatives.
	 *
	 * @param int $order_id The GAM Order ID.
	 * @param int $batch    The batch number. 0 means do not use batch.
	 *
	 * @return LineItemCreativeAssociation[] List of created Line Item Creative Association objects.
	 */
	public static function associate_creatives( $order_id, $batch = 0 ) {

		$order_config = self::get_order_local_config( $order_id );
		if ( false === $order_config ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing order config', 'newspack-ads' ) );
		}

		$lica_configs = self::get_lica_config( $order_id );
		if ( 0 < $batch ) {
			$lica_configs = array_slice( $lica_configs, ( $batch - 1 ) * self::LICA_BATCH_SIZE, self::LICA_BATCH_SIZE );
		}
		if ( empty( $lica_configs ) ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', __( 'No creatives to associate.', 'newspack-ads' ) );
		}
		$lica_configs = array_map(
			function( $lica_config ) {
				return wp_parse_args(
					$lica_config,
					[
						'sizes' => \Newspack_Ads\get_bidders_sizes(), // Override the Creative.Size value with the line item creative placeholders.
					]
				);
			},
			$lica_configs
		);
		try {
			$api   = GAM_Model::get_api();
			$licas = $api->line_items->associate_creatives_to_line_items( $lica_configs );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
		if ( is_wp_error( $licas ) ) {
			return $licas;
		}
		self::update_order_local_config( $order_id, [ 'lica_batch_count' => $batch ] );

		return $licas;
	}

	/**
	 * Get a price string.
	 *
	 * @param float $price The price.
	 *
	 * @return string The price string.
	 */
	private static function get_number_to_price_string( $price ) {
		return sprintf( '%01.2f', $price );
	}

	/**
	 * Convert a number to micro amounts rounded by the given precision.
	 *
	 * @param int|float $number    A number to convert to micro amounts.
	 * @param int       $precision The precision to round to.
	 *
	 * @return int The micro amount.
	 */
	private static function get_number_to_micro( $number, $precision ) {
		return round( $number * pow( 10, 6 ), -6 + $precision );
	}

	/**
	 * Convert a micro amount to a number.
	 *
	 * @param int $micro_amount Micro amount to convert to number.
	 *
	 * @return float The number.
	 */
	private static function get_micro_to_number( $micro_amount ) {
		return $micro_amount / pow( 10, 6 );
	}
}
Bidding_GAM::init();
