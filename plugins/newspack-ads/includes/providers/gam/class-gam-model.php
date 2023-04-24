<?php
/**
 * Newspack Ads Google Ad Manager Provider Model.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Newspack_Ads\Placements;
use Newspack_Ads\Providers\GAM\Api as GAM_Api;

/**
 * Newspack Ads GAM Model Class.
 */
final class GAM_Model {
	const SIZES = 'sizes';
	const CODE  = 'code';
	const FLUID = 'fluid';

	const SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME = '_newspack_ads_gam_credentials';

	// Legacy network code manually inserted.
	const OPTION_NAME_LEGACY_NETWORK_CODE = '_newspack_ads_service_google_ad_manager_network_code';

	// GAM network code pulled from user credentials.
	const OPTION_NAME_GAM_NETWORK_CODE = '_newspack_ads_gam_network_code';

	const OPTION_NAME_GAM_ITEMS = '_newspack_ads_gam_items';

	const OPTION_NAME_DEFAULT_UNITS = '_newspack_ads_gam_default_units';

	/**
	 * GAM Api
	 *
	 * @var GAM_Api
	 */
	private static $api = null;

	/**
	 * GAM Api fatal error message.
	 *
	 * @var string
	 */
	private static $api_fatal_error = null;

	/**
	 * GAM Api session error message.
	 *
	 * @var string
	 */
	private static $api_session_error = null;

	/**
	 * Custom post type
	 *
	 * @var string
	 */
	public static $custom_post_type = 'newspack_ad_codes';

	/**
	 * Associative array of all ad slots that will be rendered on the page.
	 *
	 * @var array
	 */
	public static $slots = [];

	/**
	 * Whether GAM units were already synced.
	 *
	 * @var boolean Whether GAM units were already synced.
	 */
	public static $synced = false;

	/**
	 * Initialize Google Ads Model
	 *
	 * @codeCoverageIgnore
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_ad_post_type' ] );
		add_action( 'newspack_ads_activation_hook', [ __CLASS__, 'register_default_placements_units' ] );
	}

	/**
	 * Get the GAM API
	 *
	 * @return GAM_Api|false The GAM API, or false if unable to initialized.
	 */
	public static function get_api() {
		if ( isset( self::$api ) ) {
			return self::$api;
		}

		/** Default method is through stored service account. */
		$auth_method = 'service_account';
		$credentials = self::get_service_account_credentials();

		/** If service account method is not available, look for OAuth2. */
		if ( ! $credentials && class_exists( 'Newspack\Google_Services_Connection' ) ) {
			$oauth2_credentials = \Newspack\Google_Services_Connection::get_oauth2_credentials();
			if ( false !== $oauth2_credentials ) {
				$auth_method = 'oauth2';
				$credentials = $oauth2_credentials;
			}
		}

		$network_code = self::get_active_network_code();

		$api = null;
		try {
			$api = new GAM_Api( $auth_method, $credentials, $network_code );
		} catch ( \Exception $e ) {
			self::$api_fatal_error = $e->getMessage();
			return false;
		}
		$init_res = $api->init();
		if ( is_wp_error( $init_res ) ) {
			self::$api_session_error = $init_res->get_error_message();
			return false;
		}
		self::$api = $api;
		return self::$api;
	}

	/**
	 * Register ad unit post type
	 *
	 * @codeCoverageIgnore
	 */
	public static function register_ad_post_type() {
		register_post_type(
			self::$custom_post_type,
			array(
				'public'             => false,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
			)
		);
	}

	/**
	 * Register default ad units to placements
	 */
	public static function register_default_placements_units() {
		$ad_units       = self::get_default_ad_units();
		$placements_map = [
			'global_below_header' => 'newspack_below_header',
			'sticky'              => 'newspack_sticky_footer',
			'sidebar_sidebar-1'   => [
				'before' => 'newspack_sidebar_1',
				'after'  => 'newspack_sidebar_2',
			],
			'scaip-1'             => 'newspack_in_article_1',
			'scaip-2'             => 'newspack_in_article_2',
			'scaip-3'             => 'newspack_in_article_3',
		];
		$default_data   = [
			'enabled'  => true,
			'provider' => 'gam',
		];
		foreach ( $placements_map as $placement => $ad_unit ) {
			$should_update  = false;
			$placement_data = Placements::get_placement_data( $placement );
			$placement_data = wp_parse_args( $placement_data, $default_data );
			if ( 'sidebar_sidebar-1' === $placement ) {
				$placement_data['stick_to_top'] = true;
			}
			if ( ! is_array( $ad_unit ) && empty( $placement_data['ad_unit'] ) ) {
				$should_update             = true;
				$placement_data['ad_unit'] = $ad_unit;
			} elseif ( is_array( $ad_unit ) ) {
				foreach ( $ad_unit as $hook => $hook_ad_unit ) {
					if ( ! isset( $placement_data['hooks'][ $hook ]['ad_unit'] ) || empty( $placement_data['hooks'][ $hook ]['ad_unit'] ) ) {
						$should_update                               = true;
						$placement_data['hooks'][ $hook ]['ad_unit'] = $hook_ad_unit;
					}
				}
			}
			if ( $should_update ) {
				Placements::update_placement( $placement, $placement_data );
			}
		}
	}

	/**
	 * Initial GAM setup.
	 *
	 * @return object|\WP_Error Setup results or error if setup fails.
	 */
	public static function setup_gam() {
		$setup_results = array();
		$api           = self::get_api();
		if ( $api ) {
			try {
				$setup_results['created_targeting_keys'] = $api->targeting_keys->update_default_targeting_keys();
			} catch ( \Exception $e ) {
				return new \WP_Error( 'newspack_ads_setup_gam', $e->getMessage() );
			}
		}
		return $setup_results;
	}

	/**
	 * Get a single ad unit to display on the page.
	 *
	 * @param string $id     The id of the ad unit to retrieve.
	 * @param array  $config {
	 *   Optional additional configuration parameters for the ad unit.
	 *
	 *   @type string $unique_id The unique ID for this ad displayment.
	 *   @type string $placement The id of the placement region.
	 *   @type string $context   An optional parameter to describe the context of the ad. For example, in the Widget, the widget ID.
	 * }
	 *
	 * @return object Prepared ad unit, with markup for injecting on a page.
	 */
	public static function get_ad_unit_for_display( $id, $config = array() ) {
		if ( empty( $id ) ) {
			return new \WP_Error(
				'newspack_no_adspot_found',
				\esc_html__( 'No such ad spot.', 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}

		$unique_id = $config['unique_id'] ?? uniqid();
		$placement = $config['placement'] ?? '';
		$context   = $config['context'] ?? '';

		$ad_units = self::get_ad_units( false );

		$index = array_search( $id, array_column( $ad_units, 'id' ) );
		if ( false === $index ) {
			return new \WP_Error(
				'newspack_no_adspot_found',
				\esc_html__( 'No such ad spot.', 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}
		$ad_unit = $ad_units[ $index ];

		$ad_unit['placement'] = $placement;
		$ad_unit['context']   = $context;

		$ad_unit['ad_code']     = self::get_ad_unit_code( $ad_unit, $unique_id );
		$ad_unit['amp_ad_code'] = self::get_ad_unit_amp_code( $ad_unit, $unique_id );
		return $ad_unit;
	}

	/**
	 * Get default ad units.
	 *
	 * @param boolean $sync Whether to sync the ad units with GAM.
	 *
	 * @return array Array of ad units.
	 */
	public static function get_default_ad_units( $sync = true ) {
		$ad_units = [
			'newspack_below_header'  => [
				'name'  => \esc_html__( 'Newspack Below Header', 'newspack' ),
				'sizes' => [
					[ 320, 50 ],
					[ 320, 100 ],
					[ 728, 90 ],
					[ 970, 90 ],
					[ 970, 250 ],
				],
			],
			'newspack_sticky_footer' => [
				'name'  => \esc_html__( 'Newspack Sticky Footer', 'newspack' ),
				'sizes' => [
					[ 320, 50 ],
					[ 320, 100 ],
				],
			],
			'newspack_sidebar_1'     => [
				'name'  => \esc_html__( 'Newspack Sidebar 1', 'newspack' ),
				'sizes' => [
					[ 300, 250 ],
					[ 300, 600 ],
				],
			],
			'newspack_sidebar_2'     => [
				'name'  => \esc_html__( 'Newspack Sidebar 2', 'newspack' ),
				'sizes' => [
					[ 300, 250 ],
					[ 300, 600 ],
				],
			],
			'newspack_in_article_1'  => [
				'name'  => \esc_html__( 'Newspack In-Article 1', 'newspack' ),
				'sizes' => [
					[ 728, 90 ],
					[ 300, 250 ],
				],
			],
			'newspack_in_article_2'  => [
				'name'  => \esc_html__( 'Newspack In-Article 2', 'newspack' ),
				'sizes' => [
					[ 728, 90 ],
					[ 300, 250 ],
				],
			],
			'newspack_in_article_3'  => [
				'name'  => \esc_html__( 'Newspack In-Article 3', 'newspack' ),
				'sizes' => [
					[ 728, 90 ],
					[ 300, 250 ],
				],
			],
		];
		/**
		 * Filters the default ad units.
		 *
		 * @param array $ad_units Array of ad units.
		 */
		$ad_units = apply_filters( 'newspack_ads_default_ad_units', $ad_units );

		/**
		 * Update values with stored data.
		 */
		$stored_units = get_option( self::OPTION_NAME_DEFAULT_UNITS, [] );
		if ( is_array( $stored_units ) ) {
			$ad_units = array_merge( $ad_units, $stored_units );
		}

		/**
		 * If API is available, sync ad unit in GAM and replace local config with
		 * remote.
		 */
		if ( true === $sync ) {
			$api = self::get_api();
			if ( $api ) {
				$gam_ad_units = $api->ad_units->get_serialized_ad_units( [], true );
				if ( ! is_wp_error( $gam_ad_units ) ) {
					foreach ( $ad_units as $ad_unit_key => $ad_unit_config ) {
						/** Validate config before continuing. */
						if ( ! is_array( $ad_unit_config ) || empty( $ad_unit_config['name'] ) ) {
							continue;
						}
						$ad_unit_idx = array_search( $ad_unit_config['name'], array_column( $gam_ad_units, 'name' ) );
						if ( $ad_unit_idx ) {
							$gam_ad_unit = $gam_ad_units[ $ad_unit_idx ];
							/** Update ad unit status to 'ACTIVE' if not active. */
							if ( 'ACTIVE' !== $gam_ad_unit['status'] ) {
								$api->ad_units->update_ad_unit_status( $gam_ad_unit['id'], 'ACTIVE' );
								$gam_ad_unit = $api->ad_units->get_serialized_ad_units( [ $gam_ad_unit['id'] ] )[0];
							}
						} else {
							/** Create ad unit if not synced. */
							$created_unit = $api->ad_units->create_ad_unit( $ad_unit_config );
							if ( ! is_wp_error( $created_unit ) ) {
								$gam_ad_unit = $created_unit;
							}
						}
						$ad_units[ $ad_unit_key ] = $gam_ad_unit;
					}
					update_option( self::OPTION_NAME_DEFAULT_UNITS, $ad_units );
				}
			}
		}

		return array_map(
			function( $id, $ad_unit ) {
				/** Prepare unsynced default units. */
				if ( empty( $ad_unit['id'] ) ) {
					$ad_unit['id']         = $id;
					$ad_unit['code']       = $id;
					$ad_unit['fluid']      = false;
					$ad_unit['status']     = 'ACTIVE';
					$ad_unit['is_default'] = true;
				}
				return $ad_unit;
			},
			array_keys( $ad_units ),
			array_values( $ad_units )
		);
	}

	/**
	 * Get the legacy ad units (defined as CPTs).
	 */
	private static function get_legacy_ad_units() {
		$legacy_ad_units = [];
		$query           = new \WP_Query(
			[
				'post_type'      => self::$custom_post_type,
				'posts_per_page' => -1,
				'post_status'    => [ 'publish' ],
			]
		);
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				if ( self::$custom_post_type === $post->post_type ) {
					$legacy_ad_units[] = [
						'id'        => $post->ID,
						'name'      => html_entity_decode( $post->post_title, ENT_QUOTES ),
						'sizes'     => self::sanitize_sizes( \get_post_meta( $post->ID, self::SIZES, true ) ),
						'code'      => esc_html( \get_post_meta( $post->ID, self::CODE, true ) ),
						'fluid'     => (bool) \get_post_meta( $post->ID, self::FLUID, true ),
						'status'    => 'ACTIVE',
						'is_legacy' => true,
					];
				}
			}
		}
		return $legacy_ad_units;
	}

	/**
	 * Get the ad units.
	 *
	 * @param bool $sync Whether to attempt sync with connected GAM.
	 *
	 * @return array Array of ad units.
	 */
	public static function get_ad_units( $sync = true ) {
		if ( $sync && ! self::$synced ) {
			// Only sync once per execution.
			if ( self::is_api_connected() ) {
				$api = self::get_api();
				if ( $api ) {
					$gam_ad_units = $api->ad_units->get_serialized_ad_units();
					if ( ! \is_wp_error( $gam_ad_units ) && ! empty( $gam_ad_units ) ) {
						self::sync_gam_settings( $gam_ad_units );
						self::$synced = true;
					}
				}
			}
		}
		$default_units = self::get_default_ad_units( $sync );
		$synced_units  = self::get_synced_ad_units();

		/* Clear default units that are synced. */
		foreach ( $synced_units as $ad_unit ) {
			$default_unit_idx = array_search( $ad_unit['id'], array_column( $default_units, 'id' ) );
			if ( false !== $default_unit_idx ) {
				unset( $default_units[ $default_unit_idx ] );
				$default_units = array_values( $default_units );
			}
		}

		$ad_units = array_merge(
			self::get_legacy_ad_units(),
			$synced_units,
			$default_units
		);
		return $ad_units;
	}

	/**
	 * Add a new ad unit.
	 *
	 * @param array $ad_unit The new ad unit info to add.
	 */
	public static function add_ad_unit( $ad_unit ) {
		if ( self::is_api_connected() ) {
			$api    = self::get_api();
			$result = $api->ad_units->create_ad_unit( $ad_unit );
			self::sync_gam_settings();
		} else {
			$result = self::legacy_add_ad_unit( $ad_unit );
		}
		return $result;
	}

	/**
	 * Add a new legacy ad unit.
	 *
	 * @param array $ad_unit The new ad unit info to add.
	 */
	private static function legacy_add_ad_unit( $ad_unit ) {
		$name = strlen( trim( $ad_unit['name'] ) ) ? $ad_unit['name'] : $ad_unit[ self::CODE ];

		// Save the ad unit.
		$ad_unit_post = \wp_insert_post(
			array(
				'post_author' => \get_current_user_id(),
				'post_title'  => $name,
				'post_type'   => self::$custom_post_type,
				'post_status' => 'publish',
			)
		);
		if ( \is_wp_error( $ad_unit_post ) ) {
			return new \WP_Error(
				'newspack_ad_unit_exists',
				\esc_html__( 'An ad unit with that name already exists', 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}

		// Add the code to our new post.
		\add_post_meta( $ad_unit_post, self::SIZES, $ad_unit[ self::SIZES ] );
		\add_post_meta( $ad_unit_post, self::CODE, $ad_unit[ self::CODE ] );
		\add_post_meta( $ad_unit_post, self::FLUID, (bool) $ad_unit[ self::FLUID ] );

		return array(
			'id'        => $ad_unit_post,
			'name'      => $ad_unit['name'],
			self::SIZES => $ad_unit[ self::SIZES ],
			self::CODE  => $ad_unit[ self::CODE ],
			self::FLUID => $ad_unit[ self::FLUID ],
		);
	}

	/**
	 * Update a legacy ad unit.
	 *
	 * @param array $ad_unit The updated ad unit.
	 */
	private static function legacy_update_ad_unit( $ad_unit ) {
		$ad_unit_post = \get_post( $ad_unit['id'] );
		if ( ! is_a( $ad_unit_post, 'WP_Post' ) ) {
			return new \WP_Error(
				'newspack_ad_unit_not_exists',
				\esc_html__( "Can't update an ad unit that doesn't already exist", 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}

		$name = strlen( trim( $ad_unit['name'] ) ) ? $ad_unit['name'] : $ad_unit[ self::CODE ];

		\wp_update_post(
			array(
				'ID'         => $ad_unit['id'],
				'post_title' => $name,
			)
		);
		\update_post_meta( $ad_unit['id'], self::SIZES, $ad_unit[ self::SIZES ] );
		\update_post_meta( $ad_unit['id'], self::CODE, $ad_unit[ self::CODE ] );
		\update_post_meta( $ad_unit['id'], self::FLUID, (bool) $ad_unit[ self::FLUID ] );
		return array(
			'id'        => $ad_unit['id'],
			'name'      => $ad_unit['name'],
			self::SIZES => $ad_unit[ self::SIZES ],
			self::CODE  => $ad_unit[ self::CODE ],
			self::FLUID => $ad_unit[ self::FLUID ],
		);
	}

	/**
	 * Update an ad unit. Updating the code is not possible, it's set at ad unit creation.
	 *
	 * @param array $ad_unit The updated ad unit.
	 */
	public static function update_ad_unit( $ad_unit ) {
		if ( isset( $ad_unit['is_legacy'] ) && true === $ad_unit['is_legacy'] ) {
			$result = self::legacy_update_ad_unit( $ad_unit );
		} else {
			$api    = self::get_api();
			$result = $api->ad_units->update_ad_unit( $ad_unit );
			self::sync_gam_settings();
		}
		return $result;
	}

	/**
	 * Delete an ad unit
	 *
	 * @param integer $id The id of the ad unit to delete.
	 */
	public static function delete_ad_unit( $id ) {
		$ad_unit_cpt = \get_post( $id );
		if ( is_a( $ad_unit_cpt, 'WP_Post' ) ) {
			if ( $ad_unit_cpt->post_type === self::$custom_post_type ) {
				\wp_delete_post( $id );
				return true;
			}
		} else {
			$api = self::get_api();
			if ( $api ) {
				$result = $api->ad_units->update_ad_unit_status( $id, 'ARCHIVE' );
				self::sync_gam_settings();
				return $result;
			} else {
				return new \WP_Error(
					'newspack_ads_gam_error',
					\esc_html__( 'Not connected to GAM', 'newspack-ads' ),
					array(
						'status' => '400',
					)
				);
			}
		}
	}

	/**
	 * Retrieve the active network code.
	 * This might be updateable in the future to enable handling users with
	 * multiple GAM networks - for now simply the first available GAM network
	 * is retrieved.
	 *
	 * @return string The network code.
	 */
	public static function get_active_network_code() {
		$gam_network_code    = get_option( self::OPTION_NAME_GAM_NETWORK_CODE, '' );
		$legacy_network_code = get_option( self::OPTION_NAME_LEGACY_NETWORK_CODE, '' );
		return sanitize_text_field( ! empty( $gam_network_code ) ? $gam_network_code : $legacy_network_code );
	}

	/**
	 * Save GAM configuration locally.
	 * First reason is so the GAM API is not queried on frontend requests - information
	 * about ad units & GAM settings will be read from the DB.
	 * Second reason is backwards compatibility - in a previous version of the plugin,
	 * the sync was done manually.
	 *
	 * @param object[] $serialised_ad_units An array of ad units configurations.
	 * @param object[] $settings Settings to use.
	 */
	public static function sync_gam_settings( $serialised_ad_units = null, $settings = null ) {
		$api = self::get_api();
		if ( null === $serialised_ad_units ) {
			$serialised_ad_units = $api->ad_units->get_serialized_ad_units();
		}
		if ( null === $settings ) {
			try {
				$settings = $api->get_settings();
			} catch ( \Exception $e ) {
				return new \WP_Error(
					'newspack_ads_failed_gam_sync',
					__( 'Unable to synchronize with GAM', 'newspack-ads' )
				);
			}
		}

		if ( isset( $settings['network_code'] ) && $serialised_ad_units && ! empty( $serialised_ad_units ) ) {
			$synced_gam_items                              = get_option( self::OPTION_NAME_GAM_ITEMS, [] );
			$network_code                                  = sanitize_text_field( $settings['network_code'] );
			$synced_gam_items[ $network_code ]['ad_units'] = $serialised_ad_units;
			update_option( self::OPTION_NAME_LEGACY_NETWORK_CODE, $network_code );
			update_option( self::OPTION_NAME_GAM_ITEMS, $synced_gam_items );
		}
	}

	/**
	 * The user on whose behalf GAM is authorised might be using
	 * a different GAM account than the one already configured on the site.
	 *
	 * @return boolean True if there is a network code mismatch.
	 */
	private static function is_network_code_matched() {
		$legacy_network_code = get_option( self::OPTION_NAME_LEGACY_NETWORK_CODE, '' );
		if ( empty( $legacy_network_code ) ) {
			// No network code set yet.
			return true;
		}
		try {
			$gam_network_code = get_option( self::OPTION_NAME_GAM_NETWORK_CODE, '' );

			// Active Network Code might be a comma-delimited list of codes.
			return array_reduce(
				explode( ',', $legacy_network_code ),
				function( $valid_code, $code ) use ( $gam_network_code ) {
					if ( absint( $code ) === absint( $gam_network_code ) ) {
						$valid_code = true;
					}
					return $valid_code;
				},
				false
			);
		} catch ( \Exception $e ) {
			// Can't get user's network code.
			return false;
		}
	}

	/**
	 * Retrieve the synced GAM items.
	 *
	 * @return array[] GAM items.
	 */
	private static function get_synced_gam_items() {
		$network_code     = self::get_active_network_code();
		$synced_gam_items = get_option( self::OPTION_NAME_GAM_ITEMS, null );
		if ( $network_code && $synced_gam_items && isset( $synced_gam_items[ $network_code ] ) ) {
			return $synced_gam_items[ $network_code ];
		}
		return null;
	}

	/**
	 * Retrieve the synced GAM items.
	 *
	 * @return array[] GAM items.
	 */
	public static function get_synced_ad_units() {
		$gam_items = self::get_synced_gam_items();
		if ( $gam_items ) {
			return $gam_items['ad_units'];
		}
		return [];
	}

	/**
	 * Sanitize array of ad unit sizes.
	 *
	 * @param array $sizes Array of sizes to sanitize.
	 * @return array Sanitized array.
	 */
	public static function sanitize_sizes( $sizes ) {
		$sizes     = is_array( $sizes ) ? $sizes : [];
		$sanitized = [];
		foreach ( $sizes as $size ) {
			$size    = is_array( $size ) && 2 === count( $size ) ? $size : [ 0, 0 ];
			$size[0] = absint( $size[0] );
			$size[1] = absint( $size[1] );

			$sanitized[] = $size;
		}
		return $sanitized;
	}

	/**
	 * Code for ad unit.
	 *
	 * @param array  $ad_unit   The ad unit to generate code for.
	 * @param string $unique_id The unique ID for this ad displayment.
	 */
	public static function get_ad_unit_code( $ad_unit, $unique_id = '' ) {
		$code         = $ad_unit['code'];
		$network_code = self::get_active_network_code();
		$unique_id    = $unique_id ?? uniqid();

		if ( ! is_array( $ad_unit['sizes'] ) ) {
			$ad_unit['sizes'] = [];
		}

		// Remove all ad sizes greater than 600px wide for sticky ads.
		if ( self::is_sticky( $ad_unit ) ) {
			$ad_unit['sizes'] = array_filter(
				$ad_unit['sizes'],
				function( $size ) {
					return $size[0] < 600;
				}
			);
		}

		self::$slots[ $unique_id ] = $ad_unit;

		$code = sprintf(
			"<!-- /%s/%s --><div id='div-gpt-ad-%s-0'></div>",
			$network_code,
			$code,
			$unique_id
		);
		return $code;
	}

	/**
	 * AMP code for ad unit.
	 *
	 * @param array  $ad_unit   The ad unit to generate AMP code for.
	 * @param string $unique_id Optional pre-defined unique ID for this ad displayment.
	 */
	public static function get_ad_unit_amp_code( $ad_unit, $unique_id = '' ) {
		$code         = $ad_unit['code'];
		$network_code = self::get_active_network_code();
		$targeting    = self::get_ad_targeting( $ad_unit );
		$unique_id    = $unique_id ?? uniqid();

		if ( ! is_array( $ad_unit['sizes'] ) ) {
			$ad_unit['sizes'] = [];
		}

		// Remove all ad sizes greater than 600px wide for sticky ads.
		if ( self::is_sticky( $ad_unit ) ) {
			$ad_unit['sizes'] = array_filter(
				$ad_unit['sizes'],
				function( $size ) {
					return $size[0] < 600;
				}
			);
		}

		$sizes = $ad_unit['sizes'];

		$size_map = self::get_ad_unit_size_map( $ad_unit, $sizes );

		/**
		 * Legacy filter for custom size map.
		 */
		$size_map = apply_filters( 'newspack_ads_multisize_ad_sizes', $size_map, $ad_unit );

		// Do not use responsive strategy if the size map only results in one viewport or the ad unit is fluid.
		if ( 1 < count( $size_map ) && false === $ad_unit['fluid'] ) {
			return self::get_ad_unit_responsive_amp_code( $unique_id, $ad_unit, $size_map );
		}

		$attrs      = [];
		$multisizes = [];

		if ( isset( $ad_unit['fluid'] ) && true === $ad_unit['fluid'] ) {
			$attrs['height'] = 'fluid';
			$attrs['layout'] = 'fluid';
			$multisizes[]    = 'fluid';
		}

		if ( count( $sizes ) ) {
			// Sort sizes by squareness.
			usort(
				$sizes,
				function( $a, $b ) {
					return $a[0] * $a[1] < $b[0] * $b[1] ? 1 : -1;
				}
			);
			if ( ! isset( $attrs['layout'] ) ) {
				$attrs['width']  = max( array_column( $sizes, 0 ) );
				$attrs['height'] = max( array_column( $sizes, 1 ) );
				$attrs['layout'] = 'fixed';
			}
			foreach ( $sizes as $size ) {
				$multisizes[] = $size[0] . 'x' . $size[1];
			}
		}

		if ( 1 < count( $multisizes ) ) {
			$attrs['data-multi-size']            = implode( ',', $multisizes );
			$attrs['data-multi-size-validation'] = 'false';
		}

		$attrs['type']                  = 'doubleclick';
		$attrs['data-slot']             = sprintf( '/%s/%s', $network_code, $code );
		$attrs['data-loading-strategy'] = 'prefer-viewability-over-views';
		$attrs['json']                  = sprintf( '{"targeting": %s}', wp_json_encode( $targeting ) );

		$code = sprintf(
			'<amp-ad %s></amp-ad>',
			implode(
				' ',
				array_map(
					function( $key, $value ) {
						return sprintf( "%s='%s'", $key, $value );
					},
					array_keys( $attrs ),
					array_values( $attrs )
				)
			)
		);

		return $code;
	}

	/**
	 * Get size map for responsive ads.
	 *
	 * Gather up all of the ad sizes which should be displayed on the same
	 * viewports. As a heuristic, each ad slot can safely display ads with a 30%
	 * difference from slot's width. e.g. for the following setup: [[300,200],
	 * [350,200]], we can display [[300,200], [350,200]] on viewports >= 350px
	 * and [[300,200]] on viewports <= 300px.
	 *
	 * Sizes above the determined width threshold (default to 600) will not have
	 * their ratio difference considered and will always share viewport with the
	 * next largest size. e.g. for the following setup: [[640,320], [960,540]],
	 * we can display [[640,320], [960,540]] on viewports >= 960px even though
	 * the ratio difference is higher than the default 30%.
	 *
	 * @param array[]   $sizes            Array of sizes.
	 * @param float     $width_diff_ratio Minimum width ratio difference for sizes to share same viewport.
	 * @param int|false $width_threshold  Width threshold to ignore ratio difference. False disables threshold.
	 *
	 * @return array[] Size map keyed by the viewport width.
	 */
	public static function get_responsive_size_map( $sizes, $width_diff_ratio = 0.3, $width_threshold = 600 ) {

		array_multisort( $sizes );

		// Each existing size's width is size map viewport.
		$viewports = array_unique( array_column( $sizes, 0 ) );

		$size_map = [];
		foreach ( $viewports as $viewport_width ) {
			foreach ( $sizes as $size ) {
				$is_in_viewport     = $size[0] <= $viewport_width;
				$is_above_threshold = false !== $width_threshold && $width_threshold <= $size[0];
				if ( 0 === $size[0] ) {
					continue;
				}
				$diff            = min( $viewport_width, $size[0] ) / max( $viewport_width, $size[0] );
				$is_within_ratio = ( 1 - $width_diff_ratio ) <= $diff;
				if ( $is_in_viewport && ( $is_within_ratio || $is_above_threshold ) ) {
					$size_map[ $viewport_width ][] = $size;
				}
			}
		}
		return $size_map;
	}

	/**
	 * Get the size map for an ad unit.
	 *
	 * @param array $ad_unit Ad unit.
	 * @param array $sizes   Optional array of sizes to use.
	 *
	 * @return array Size map keyed by the viewport width.
	 */
	public static function get_ad_unit_size_map( $ad_unit, $sizes = [] ) {

		if ( empty( $sizes ) ) {
			$sizes = $ad_unit['sizes'];
		}

		/**
		 * Filters the ad unit size map difference ratio.
		 *
		 * @param float   $width_diff_ratio The width diff ratio.
		 * @param array   $ad_unit          The ad unit config.
		 * @param array[] $sizes            The sizes being used.
		 */
		$width_diff_ratio = apply_filters( 'newspack_ads_gam_size_map_diff_ratio', 0.3, $ad_unit, $sizes );

		/**
		 * Filters the ad unit size map width threshold.
		 *
		 * @param int     $width_threshold The width threshold.
		 * @param array   $ad_unit         The ad unit config.
		 * @param array[] $sizes           The sizes being used.
		 */
		$width_threshold = apply_filters( 'newspack_ads_gam_size_map_width_threshold', 600, $ad_unit, $sizes );

		/**
		 * Filters the ad unit size map rules.
		 *
		 * @param array[] $size_map The size map array.
		 * @param array   $ad_unit  The ad unit config.
		 * @param array[] $sizes    The sizes being used.
		 */
		$size_map = apply_filters(
			'newspack_ads_gam_size_map',
			self::get_responsive_size_map( $sizes, $width_diff_ratio, $width_threshold ),
			$ad_unit,
			$sizes
		);

		return $size_map;
	}

	/**
	 * Generate responsive AMP ads for a series of ad sizes.
	 *
	 * @param string $unique_id Unique ID for this ad unit instance.
	 * @param array  $ad_unit   The ad unit to generate code for.
	 * @param array  $size_map  The size map.
	 */
	public static function get_ad_unit_responsive_amp_code( $unique_id, $ad_unit, $size_map ) {
		$network_code = self::get_active_network_code();
		$code         = $ad_unit['code'];
		$sizes        = $ad_unit['sizes'];
		$targeting    = self::get_ad_targeting( $ad_unit );

		$markup = [];
		$styles = [];

		// Build the amp-ad units according to size map.
		foreach ( $size_map as $viewport_width => $ad_sizes ) {

			// The size of the ad container should be equal to the largest width and height among all the sizes available.
			$width  = $viewport_width;
			$height = absint( max( array_column( $ad_sizes, 1 ) ) );

			$multisizes = array_map( '\Newspack_Ads\get_size_string', $ad_sizes );

			// If there is a multisize that's equal to the width and height of the container, remove it from the multisizes.
			// The container size is included by default, and should not also be included in the multisize.
			$container_multisize           = $width . 'x' . $height;
			$container_multisize_locations = array_keys( $multisizes, $container_multisize );
			foreach ( $container_multisize_locations as $container_multisize_location ) {
				unset( $multisizes[ $container_multisize_location ] );
			}

			$multisize_attribute = '';
			if ( count( $multisizes ) ) {
				$multisize_attribute = sprintf(
					'data-multi-size=\'%s\' data-multi-size-validation=\'false\'',
					implode( ',', $multisizes )
				);
			}

			$div_prefix = 'div-gpt-amp-';
			$div_id     = sprintf(
				'%s%s-%s-%dx%d',
				$div_prefix,
				sanitize_title( $ad_unit['code'] ),
				$unique_id,
				$width,
				$height
			);

			$markup[] = sprintf(
				'<div id="%s"><amp-ad width="%dpx" height="%dpx" type="doubleclick" data-slot="/%s/%s" data-loading-strategy="prefer-viewability-over-views" json=\'{"targeting":%s}\' %s></amp-ad></div>',
				$div_id,
				$width,
				$height,
				$network_code,
				$code,
				wp_json_encode( $targeting ),
				$multisize_attribute
			);

			// Generate styles for hiding/showing ads at different viewports out of the media queries.
			$media_query_elements   = [];
			$media_query_elements[] = sprintf( '(min-width:%dpx)', $viewport_width );
			$index                  = array_search( $viewport_width, array_keys( $size_map ) );
			// If there are ad sizes larger than the current size, the max_width is 1 less than the next ad's size.
			// If it's the largest ad size, there is no max width.
			if ( count( $size_map ) > $index + 1 ) {
				$max_width              = absint( array_keys( $size_map )[ $index + 1 ] ) - 1;
				$media_query_elements[] = sprintf( '(max-width:%dpx)', $max_width );
			}
			$styles[] = sprintf(
				'#%s{ display: none; }',
				$div_id
			);
			if ( count( $media_query_elements ) > 0 ) {
				$styles[] = sprintf(
					'@media %s {#%s{ display: block; } }',
					implode( ' and ', $media_query_elements ),
					$div_id
				);
			}
		}
		return sprintf(
			'<style>%s</style>%s',
			implode( ' ', $styles ),
			implode( ' ', $markup )
		);
	}

	/**
	 * Get ad targeting params for the current post or archive.
	 *
	 * @param array $ad_unit Ad unit to get targeting for.
	 * @return array Associative array of targeting keyvals.
	 */
	public static function get_ad_targeting( $ad_unit ) {
		$targeting = [];

		if ( is_singular() ) {
			// Add the post slug to targeting.
			$slug = get_post_field( 'post_name' );
			if ( $slug ) {
				$targeting['slug'] = sanitize_text_field( $slug );
			}

			$template_slug = get_page_template_slug();
			if ( ! empty( $template_slug ) ) {
				$targeting['template'] = sanitize_title( basename( $template_slug, '.php' ) );
			}

			// Add the category slugs to targeting.
			$categories = wp_get_post_categories( get_the_ID(), [ 'fields' => 'slugs' ] );
			if ( ! empty( $categories ) ) {
				$targeting['category'] = array_map( 'sanitize_text_field', $categories );
			}

			// Add tags slugs to targeting.
			$tags = wp_get_post_tags( get_the_ID(), [ 'fields' => 'slugs' ] );
			if ( ! empty( $tags ) ) {
				$targeting['tag'] = array_map( 'sanitize_text_field', $tags );
			}

			// Add the post authors to targeting.
			if ( function_exists( 'get_coauthors' ) ) {
				$authors = array_map(
					function( $user ) {
						return $user->user_login;
					},
					get_coauthors()
				);
			} else {
				$authors = [ get_the_author_meta( 'user_login' ) ];
			}
			if ( ! empty( $authors ) ) {
				$targeting['author'] = array_map( 'sanitize_text_field', $authors );
			}

			// Add post type to targeting.
			$targeting['post_type'] = get_post_type();

			// Add the post ID to targeting.
			$targeting['ID'] = get_the_ID();

			// Add the category slugs to targeting on category archives.
		} elseif ( get_queried_object() ) {
			$queried_object = get_queried_object();
			if ( 'WP_Term' === get_class( $queried_object ) ) {

				switch ( $queried_object->taxonomy ) {

					case 'category':
						$targeting['category'] = [ sanitize_text_field( $queried_object->slug ) ];
						break;

					case 'post_tag':
						$targeting['tag'] = [ sanitize_text_field( $queried_object->slug ) ];
						break;

				}
			}
		}

		return apply_filters( 'newspack_ads_ad_targeting', $targeting, $ad_unit );
	}

	/**
	 * Is the given ad unit a sticky ad?
	 *
	 * @param array $ad_unit Ad unit to check.
	 * @return boolean True if sticky, otherwise false.
	 */
	public static function is_sticky( $ad_unit ) {
		return 'sticky' === $ad_unit['placement'];
	}

	/**
	 * Is GAM connected?
	 *
	 * @return boolean True if GAM is connected.
	 */
	public static function is_api_connected() {
		return ! empty( self::get_api() );
	}

	/**
	 * Get GAM connection status.
	 *
	 * @return array Connection status information.
	 */
	public static function get_connection_status() {
		$api    = self::get_api();
		$status = [
			'connected'       => false,
			'connection_mode' => 'legacy',
			'network_code'    => self::get_active_network_code(),
		];
		if ( $api ) {
			$status['connected']       = self::is_api_connected();
			$status['connection_mode'] = $api->get_auth_method();
			$network_code              = $api->get_network_code();
			if ( ! empty( $network_code ) ) {
				$status['network_code'] = $network_code;
				update_option( self::OPTION_NAME_GAM_NETWORK_CODE, $network_code );
			}
			$status['is_network_code_matched'] = self::is_network_code_matched();
		} elseif ( self::$api_fatal_error ) {
			return new \WP_Error( 'newspack_ads_gam_api_fatal_error', self::$api_fatal_error );
		} elseif ( self::$api_session_error ) {
			return new \WP_Error( 'newspack_ads_gam_api_session_error', self::$api_session_error );
		}
		return $status;
	}

	/**
	 * Get GAM available networks.
	 *
	 * @return array[] Array of available networks. Empty array if no networks found or unable to fetch.
	 */
	public static function get_available_networks() {
		$api      = self::get_api();
		$networks = [];
		if ( $api ) {
			$networks = $api->get_serialized_networks();
		}
		return $networks;
	}

	/**
	 * Get GAM Service Account Credentials
	 *
	 * @param array $config Optional config to load.
	 *
	 * @return ServiceAccountCredentials|false
	 */
	public static function get_service_account_credentials( $config = [] ) {
		if ( empty( $config ) ) {
			$config = get_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME );
		}
		if ( ! $config ) {
			return false;
		}
		try {
			$credentials = new ServiceAccountCredentials( 'https://www.googleapis.com/auth/dfp', $config );
		} catch ( \Exception $e ) {
			return false;
		}
		return $credentials;
	}

	/**
	 * Update GAM credentials.
	 *
	 * @param array $config Credentials config to update.
	 *
	 * @return object Object with status information.
	 */
	public static function update_service_account_credentials( $config ) {
		if ( ! self::get_service_account_credentials( $config ) ) {
			return new \WP_Error( 'newspack_ads_gam_credentials', __( 'Invalid credentials configuration.', 'newspack-ads' ) );
		}
		$update_result = update_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME, $config );
		if ( ! $update_result ) {
			return new \WP_Error( 'newspack_ads_gam_credentials', __( 'Unable to update GAM credentials', 'newspack-ads' ) );
		}
		return self::get_connection_status();
	}

	/**
	 * Clear existing GAM credentials.
	 *
	 * @return object Object with status information.
	 */
	public static function remove_service_account_credentials() {
		$deleted_credentials_result  = delete_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME );
		$deleted_network_code_result = delete_option( self::OPTION_NAME_GAM_NETWORK_CODE );
		if ( ! $deleted_credentials_result || ! $deleted_network_code_result ) {
			return new \WP_Error( 'newspack_ads_gam_credentials', __( 'Unable to remove GAM credentials', 'newspack-ads' ) );
		}
		return self::get_connection_status();
	}
}
GAM_Model::init();
