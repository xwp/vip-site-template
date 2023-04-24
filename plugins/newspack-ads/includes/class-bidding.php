<?php
/**
 * Newspack Ads Bidding
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Core;
use Newspack_Ads\Settings;
use Newspack_Ads\Providers;
use Newspack_Ads\Placements;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Bidding Class.
 */
final class Bidding {

	const SETTINGS_SECTION_NAME = 'bidding';

	const PREBID_SCRIPT_HANDLE = 'newspack-ads-prebid';

	/**
	 * Standard sizes accepted by partners.
	 *
	 * This is a subset of \Newspack_Ads\get_iab_sizes(). Not all IAB sizes are
	 * accepted by partners.
	 */
	const ACCEPTED_AD_SIZES = [
		[ 728, 90 ],
		[ 970, 90 ],
		[ 970, 250 ],
		[ 320, 50 ],
		[ 320, 100 ],
		[ 300, 250 ],
		[ 300, 600 ],
		[ 160, 600 ],
	];

	// Default precision to use for price bucket increments.
	const DEFAULT_BUCKET_PRECISION = 2;

	// Default price granularity to use for price bucket increments.
	const DEFAULT_PRICE_GRANULARITY = 'dense';

	/**
	 * Get custom price granularities.
	 *
	 * @return array[] Custom price granularities.
	 */
	public static function get_price_granularities() {
		$price_granularities = [
			'low'    => [
				'label'   => __( 'Low', 'newspack-ads' ),
				'buckets' => [
					[
						'increment' => 0.5,
						'max'       => 5,
					],
				],
			],
			'medium' => [
				'label'   => __( 'Medium', 'newspack-ads' ),
				'buckets' => [
					[
						'increment' => 0.1,
						'max'       => 20,
					],
				],
			],
			'auto'   => [
				'label'   => __( 'Auto', 'newspack-ads' ),
				'buckets' => [
					[
						'increment' => 0.05,
						'max'       => 5,
					],
					[
						'increment' => 0.1,
						'max'       => 10,
					],
					[
						'increment' => 0.5,
						'max'       => 20,
					],  
				],
			],
			'dense'  => [
				'label'   => __( 'Dense', 'newspack-ads' ),
				'buckets' => [
					[
						'increment' => 0.01,
						'max'       => 0.6,
					],
					[
						'increment' => 0.05,
						'max'       => 5,
					],
					[
						'increment' => 0.1,
						'max'       => 10,
					],
					[
						'increment' => 0.5,
						'max'       => 20,
					],
				],
			],
		];
		/**
		 * Filters custom price granularities.
		 *
		 * @param array $price_granularities Custom price granularities.
		 */
		return apply_filters( 'newspack_ads_price_granularities', $price_granularities );
	}

	/**
	 * Get a price granularity by key.
	 *
	 * @param string $key Price granularity key.
	 *
	 * @return array|false Price granularity or false if not found.
	 */
	public static function get_price_granularity( $key ) {
		$price_granularities = self::get_price_granularities();
		if ( ! isset( $price_granularities[ $key ] ) ) {
			return false;
		}
		return $price_granularities[ $key ];
	}

	/**
	 * Registered bidders.
	 *
	 * @var array
	 */
	protected $bidders = array();

	/**
	 * The single instance of the class.
	 *
	 * @var Bidding
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Ads Bidding Instance.
	 * Ensures only one instance of Newspack Ads Bidding is loaded or can be loaded.
	 *
	 * @return Bidding - Main instance.
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
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
		add_filter( 'newspack_ads_settings_list', [ $this, 'register_settings' ] );

		// Scripts setup.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_filter( 'newspack_ads_gtag_ads_data', [ $this, 'add_gtag_ads_data' ] );
		add_action( 'newspack_ads_gtag_before_script', [ $this, 'prebid_script' ], 10, 2 );
	}

	/**
	 * Enqueue scripts.
	 */
	public static function enqueue_scripts() {
		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}
		if ( ! Providers::is_provider_active( 'gam' ) ) {
			return;
		}
		if ( Core::is_amp() ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}
		wp_enqueue_script(
			self::PREBID_SCRIPT_HANDLE,  
			plugins_url( '../dist/prebid.js', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/prebid.js' ),
			true 
		);
		add_filter(
			'script_loader_tag',
			function( $tag, $handle, $src ) {
				if ( self::PREBID_SCRIPT_HANDLE === $handle ) {
					return '<script data-amp-plus-allowed async src="' . $src . '"></script>';
				}
				return $tag;
			},
			10,
			3
		);
	}

	/**
	 * Add placement bidder data to the inline script parsed data.
	 *
	 * @param array[] $data Ads data parsed for inline script.
	 *
	 * @return array Updated ads data.
	 */
	public function add_gtag_ads_data( $data ) {
		$bidders    = $this->get_bidders();
		$placements = Placements::get_placements_data_by_id();
		foreach ( $data as $container_id => $ad_data ) {
			$unique_id = $ad_data['unique_id'];
			// Skip if no placement data.
			if ( ! isset( $placements[ $unique_id ] ) ) {
				continue;
			}
			$placement = $placements[ $unique_id ];
			foreach ( $bidders as $bidder_id => $bidder ) {
				// Skip if no bidder data.
				if ( ! isset( $placement['bidders_ids'][ $bidder_id ] ) ) {
					continue;
				}
				$data[ $container_id ]['bidders'][ $bidder_id ] = $placement['bidders_ids'][ $bidder_id ];
			}
		}
		return $data;
	}

	/**
	 * Sanitize an array of price buckets.
	 *
	 * @param array[] $price_buckets Array of price buckets.
	 *
	 * @return array[] Sanitized array of price buckets.
	 */
	public static function sanitize_price_buckets( $price_buckets ) {
		return array_map(
			function ( $bucket ) {
				return wp_parse_args(
					$bucket,
					[
						'precision' => self::DEFAULT_BUCKET_PRECISION,
						'increment' => 0,
						'max'       => 0,
					]
				);
			},
			$price_buckets
		);
	}

	/**
	 * Get a list of all sizes being used by all active bidders.
	 *
	 * @return array[] List of sizes.
	 */
	public function get_all_sizes() {
		$bidders       = $this->get_bidders();
		$bidders_sizes = array_unique(
			array_merge(
				...array_map(
					function( $bidder ) {
						return $bidder['ad_sizes'];
					},
					array_values( $bidders )
				)
			),
			SORT_REGULAR
		);
		if ( empty( $bidders_sizes ) ) {
			return self::ACCEPTED_AD_SIZES;
		}
		return $bidders_sizes;
	}

	/**
	 * Prebid script.
	 *
	 * @param array   $ad_config Ad config.
	 * @param array[] $data      Ads data parsed for inline script.
	 */
	public function prebid_script( $ad_config, $data ) {

		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}
		if ( ! Providers::is_provider_active( 'gam' ) ) {
			return;
		}
		if ( Core::is_amp() ) {
			return;
		}

		$bidders = $this->get_bidders();

		if ( ! count( $bidders ) ) {
			return;
		}

		$ad_units = array();

		foreach ( $data as $container_id => $ad_data ) {

			if ( isset( $ad_data['bidders'] ) && count( $ad_data['bidders'] ) ) {

				// Detect sizes supported by available bidders.
				$sizes = array_intersect(
					array_map( 'Newspack_Ads\get_size_string', $ad_data['sizes'] ),
					array_map( 'Newspack_Ads\get_size_string', $this->get_all_sizes() )
				);
				// Reindex filtered array.
				$sizes = array_values( $sizes );
				if ( ! count( $sizes ) ) {
					continue;
				}
				$sizes = array_map( 'Newspack_Ads\get_size_array', $sizes );

				$bids = [];

				foreach ( $bidders as $bidder_id => $bidder ) {

					if ( isset( $ad_data['bidders'][ $bidder_id ] ) && ! empty( $ad_data['bidders'][ $bidder_id ] ) ) {

						$bidder_placement_id = $ad_data['bidders'][ $bidder_id ];

						/**
						 * Filters the bid configuration of the ad unit according to the
						 * bidder.
						 *
						 * The dynamic portion of the hook name, `$bidder_id`, refers to
						 * the registered bidder key.
						 *
						 * @param array|null $bid                 The bid configuration.
						 * @param array      $bidder              Bidder configuration.
						 * @param string     $bidder_placement_id The bidder placement ID for this ad unit.
						 * @param array      $data                Ad unit data.
						 */
						$bid = apply_filters( "newspack_ads_{$bidder_id}_ad_unit_bid", null, $bidder, $bidder_placement_id, $ad_data );
						if ( ! empty( $bid ) ) {
							$bids[] = $bid;
						}
					}
				}

				// Skip if no bid was configured.
				if ( ! count( $bids ) ) {
					continue;
				}

				$ad_units[] = [
					'code'       => $container_id,
					'mediaTypes' => [
						'banner' => [
							'sizes' => $sizes,
						],
					],
					'bids'       => $bids,
				];
			}
		}
		// Configure price buckets.
		$price_granularities = self::get_price_granularities();
		$price_granularity   = $price_granularities[ self::get_setting( 'price_granularity', self::DEFAULT_PRICE_GRANULARITY ) ];

		/**
		 * Filters the Prebid.js default config.
		 * See https://docs.prebid.org/dev-docs/publisher-api-reference/setConfig.html.
		 */
		$prebid_config = apply_filters(
			'newspack_ads_prebid_config',
			[
				'debug'            => (bool) self::get_setting( 'debug', false ),
				'priceGranularity' => [ 'buckets' => self::sanitize_price_buckets( $price_granularity['buckets'] ) ],
				'bidderTimeout'    => 1000,
				'userSync'         => [
					'enabled' => true,
				],
			]
		);
		/**
		 * Filters the Ad Units configured for Prebid.js
		 *
		 * @param array[] $ad_units  Prebid Ad Units.
		 * @param array   $ad_config Ad config for gtag.
		 * @param array[] $data      Ads data parsed for gtag.
		 */
		$ad_units = apply_filters( 'newspack_ads_prebid_ad_units', $ad_units, $ad_config, $data );

		// Skip if no ad unit was configured.
		if ( ! count( $ad_units ) ) {
			return;
		}
		?>
		<script data-amp-plus-allowed>
			( function() {
				window.pbjs = window.pbjs || { que: [] };
				window.googletag = window.googletag || { cmd: [] };
				googletag.cmd.push( function() {
					googletag.pubads().disableInitialLoad();
				} );
				var config = <?php echo wp_json_encode( $prebid_config ); ?>;
				var adUnits = <?php echo wp_json_encode( $ad_units ); ?>;
				pbjs.que.push( function() {
					pbjs.setConfig( config );
					pbjs.addAdUnits( adUnits );
					pbjs.requestBids( {
						timeout: config.bidderTimeout,
						bidsBackHandler: initAdserver,
					} );
					<?php
					/**
					 * GAM Express Module.
					 * Temporarily disabled as it requires us to use GAM's ad unit paths instead of preferable div ID.
					 * https://docs.prebid.org/dev-docs/modules/dfp_express.html
					 * window.pbjs.express();
					 */
					?>
				} );
				function initAdserver() {
					if ( pbjs.initAdserverSet ) return;
					pbjs.initAdserverSet = true;
					googletag.cmd.push( function() {
						pbjs.setTargetingForGPTAsync && pbjs.setTargetingForGPTAsync();
						googletag.pubads().refresh();
					} );
				}
				// In case pbjs doesnt load, try again after the failsafe timeout of 3000ms.
				setTimeout( initAdserver, 3000 );
			} )();
		</script>
		<?php
	}

	/**
	 * Register a new bidder.
	 *
	 * @param string $bidder_id Unique bidder ID.
	 * @param array  $config    {
	 *   Optional configuration for the bidder.
	 *   @type string  $name       Name of the bidder.
	 *   @type string  $ad_sizes   Optional custom ad sizes accepted by the bidder.
	 *   @type string  $active_key Optional setting key that determines if the bidder is active.
	 *   @type array[] $settings   Optional Newspack_Settings_Ads array of settings.
	 * }
	 */
	public function register_bidder( $bidder_id, $config = array() ) {
		$this->bidders[ $bidder_id ] = wp_parse_args(
			$config,
			array(
				'name'     => $bidder_id,
				'ad_sizes' => self::ACCEPTED_AD_SIZES,
				'settings' => array(),
			)
		);
	}

	/**
	 * Get header bidding settings.
	 *
	 * @return array Header bidding settings.
	 */
	public static function get_settings() {
		return Settings::get_settings( self::SETTINGS_SECTION_NAME );
	}

	/**
	 * Get a header bidding setting.
	 *
	 * @param string $key           The key of the setting to retrieve.
	 * @param mixed  $default_value The default value to return if the setting is not found.
	 *
	 * @return mixed The setting value or null if not found.
	 */
	public static function get_setting( $key, $default_value = null ) {
		return Settings::get_setting( self::SETTINGS_SECTION_NAME, $key, $default_value );
	}

	/**
	 * Return whether header bidding is active.
	 *
	 * @return bool Whether header bidding is active.
	 */
	public static function is_enabled() {
		return Settings::get_setting( self::SETTINGS_SECTION_NAME, 'active' );
	}

	/**
	 * Return whether the bidder adapter is enabled.
	 *
	 * @param string $bidder_id Bidder ID.
	 *
	 * @return boolean Whether the bidder adapter is enabled.
	 */
	public static function is_bidder_enabled( $bidder_id ) { 
		$enabled_bidders = Settings::get_setting( self::SETTINGS_SECTION_NAME, 'enabled_bidders', [] );
		return in_array( $bidder_id, $enabled_bidders );
	}

	/**
	 * Get available bidders.
	 *
	 * @return array[] Associative array by bidder key containing its name and accepted sizes.
	 */
	public function get_bidders() {

		$bidders  = array();
		$settings = self::get_settings();

		if ( self::is_enabled() ) {
			$bidders_configs = $this->bidders;

			foreach ( $bidders_configs as $bidder_id => $bidder_config ) {

				// Check if bidder is active or doesn't require activation.
				if (
					self::is_bidder_enabled( $bidder_id ) && (
						! isset( $bidder_config['active_key'] ) ||
						( isset( $settings[ $bidder_config['active_key'] ] ) && $settings[ $bidder_config['active_key'] ] )
					)
				) {

					// Add bidder settings data.
					$settings_data = [];
					if ( count( $bidder_config['settings'] ) ) {
						foreach ( $bidder_config['settings'] as $setting ) {
							$key                   = $setting['key'];
							$settings_data[ $key ] = $settings[ $key ];
						}
					}

					$bidders[ $bidder_id ] = array(
						'name'       => $bidder_config['name'],
						'ad_sizes'   => $bidder_config['ad_sizes'],
						'active_key' => isset( $bidder_config['active_key'] ) ? $bidder_config['active_key'] : '',
						'data'       => $settings_data,
					);
				}
			}
		}

		/**
		 * Filters the available bidders.
		 *
		 * @param array[] $bidders  {
		 *   Associative array by bidder key.
		 *
		 *   @type string  $name     Name of the bidder.
		 *   @type string  $ad_sizes Ad sizes accepted by the bidder.
		 *   @type mixed[] $data     Bidder settings data.
		 * }
		 * @param array   $settings Newspack_Settings_Ads array of settings.
		 */
		return apply_filters( 'newspack_ads_bidders', $bidders, $settings );
	}

	/**
	 * Get bidder config by its ID.
	 *
	 * @param string $bidder_id Bidder ID.
	 *
	 * @return array|false Bidder config or false if not found.
	 */
	public function get_bidder( $bidder_id ) {
		$bidders = $this->get_bidders();
		if ( isset( $bidders[ $bidder_id ] ) ) {
			return $bidders[ $bidder_id ];
		}
		return false;
	}

	/**
	 * Whether there are any registered bidders, regardless of whether they are active.
	 *
	 * @return bool Whether there are any registered bidders.
	 */
	private function has_registered_bidders() {
		return count( $this->bidders ) > 0;
	}

	/**
	 * Get settings for registered bidders to use with Settings.
	 *
	 * @return array[] List of settings from registered bidders.
	 */
	private function get_bidders_settings_config() {
		$settings = array();
		foreach ( $this->bidders as $config ) {
			foreach ( $config['settings'] as $setting ) {
				// Ensure bidder setting is in the proper section.
				$setting['section'] = self::SETTINGS_SECTION_NAME;
				$settings[]         = $setting;
			}
		}
		return $settings;
	}

	/**
	 * Get setting for toggling bidder adapters.
	 *
	 * @return array Setting for toggling bidder adapters.
	 */
	private function get_enabled_bidders_setting() {
		return [
			'section'     => self::SETTINGS_SECTION_NAME,
			'type'        => 'string',
			'key'         => 'enabled_bidders',
			'description' => __( 'Adapters', 'newspack' ),
			'help'        => __( 'Select which bidder adapters should be enabled.', 'newspack' ),
			'multiple'    => true,
			'options'     => array_map(
				function( $bidder_id, $bidder ) {
					return [
						'name'  => $bidder['name'],
						'value' => $bidder_id,
					];
				},
				array_keys( $this->bidders ),
				array_values( $this->bidders ) 
			),
		];
	}

	/**
	 * Register API endpoints.
	 */
	public function register_api_endpoints() {
		register_rest_route(
			Settings::API_NAMESPACE,
			'/bidders',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_bidders' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
	}

	/**
	 * API callback to retrieve available bidders.
	 *
	 * @return WP_REST_Response containing the registered bidders.
	 */
	public function api_get_bidders() {
		return \rest_ensure_response( $this->get_bidders() );
	}

	/**
	 * Register Bidding settings to the list of settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public function register_settings( $settings_list ) {

		// Skip if using AMP.
		if ( Core::is_amp() ) {
			return $settings_list;
		}

		// Skip if no bidders are registered.
		if ( false === $this->has_registered_bidders() ) {
			return $settings_list;
		}

		$bidding_settings = array_merge(
			array(
				array(
					'description' => __( 'Header Bidding', 'newspack-ads' ),
					'help'        => __( 'Configure your settings to quickly implement header bidding.', 'newspack-ads' ),
					'section'     => self::SETTINGS_SECTION_NAME,
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => false,
				),
				$this->get_enabled_bidders_setting(),
			),
			$this->get_bidders_settings_config(),
			array(
				array(
					'description' => __( 'Debug mode', 'newspack-ads' ),
					'help'        => __( 'Run Prebid.js in debug mode.', 'newspack-ads' ),
					'section'     => self::SETTINGS_SECTION_NAME,
					'key'         => 'debug',
					'type'        => 'boolean',
					'default'     => false,
				),
			)
		);
		return array_merge( $settings_list, $bidding_settings );
	}
}

/**
 * Get available bidders.
 *
 * @return string[] Associative array containing a bidder key and name.
 */
function get_bidders() {
	return $GLOBALS['newspack_ads_bidding']->get_bidders();
}

/**
 * Get bidder config by its ID.
 *
 * @param string $bidder_id Bidder ID.
 *
 * @return array|false Bidder config or false if not found.
 */
function get_bidder( $bidder_id ) {
	return $GLOBALS['newspack_ads_bidding']->get_bidder( $bidder_id );
}

/**
 * Get a list of all sizes being used by all active bidders.
 *
 * @return array[] List of sizes.
 */
function get_bidders_sizes() {
	return $GLOBALS['newspack_ads_bidding']->get_all_sizes();
}

/**
 * Register a new bidder.
 *
 * @param string $bidder_id Unique bidder ID.
 * @param array  $config    {
 *   Optional configuration for the bidder.
 *   @type string  $name       Name of the bidder.
 *   @type string  $ad_sizes   Optional custom ad sizes accepted by the bidder.
 *   @type string  $active_key Optional setting key that determines if the bidder is active.
 *   @type array[] $settings   Optional Newspack_Settings_Ads array of settings.
 * }
 */
function register_bidder( $bidder_id, $config = array() ) {
	$GLOBALS['newspack_ads_bidding']->register_bidder( $bidder_id, $config );
}

$GLOBALS['newspack_ads_bidding'] = Bidding::instance();
