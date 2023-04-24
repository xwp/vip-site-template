<?php
/**
 * Newspack Ads Placements
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Core;
use Newspack_Ads\Settings;
use Newspack_Ads\Providers;

/**
 * Newspack Ads Placements
 */
final class Placements {

	/**
	 * Configured placements.
	 *
	 * @var array[
	 * 'placement_id' => array[
	 *   'name'            => string,
	 *   'description'     => string,
	 *   'show_ui'         => bool,
	 *   'default_enabled' => string,
	 *   'default_ad_unit' => string,
	 *   'hook_name'       => string,
	 *   'hooks'           => array[
	 *     'name'      => string,
	 *     'hook_name' => string
	 *   ],
	 *   'supports'        => string[]
	 * ]
	 */
	protected static $placements = [];

	/**
	 * Initialize settings.
	 */
	public static function init() {
		self::register_default_placements();
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
	}

	/**
	 * Register the endpoints needed to fetch and update placements.
	 */
	public static function register_api_endpoints() {

		register_rest_route(
			Settings::API_NAMESPACE,
			'/placements',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_placements' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);

		register_rest_route(
			Settings::API_NAMESPACE,
			'/placements/(?P<placement>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update_placement' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'placement'    => [
						'sanitize_callback' => 'sanitize_title',
					],
					'provider'     => [
						'sanitize_callback' => 'sanitize_title',
					],
					'ad_unit'      => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'bidders_ids'  => [
						'sanitize_callback' => [ __CLASS__, 'sanitize_bidders_ids' ],
					],
					'hooks'        => [
						'sanitize_callback' => [ __CLASS__, 'sanitize_hooks_data' ],
					],
					'stick_to_top' => [
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);

		register_rest_route(
			Settings::API_NAMESPACE,
			'/placements/(?P<placement>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_disable_placement' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'placement' => [
						'sanitize_callback' => 'sanitize_title',
					],
				],
			]
		);
	}

	/**
	 * Sanitize placement data.
	 *
	 * @param array $data Placement data.
	 *
	 * @return array Sanitized placement data.
	 */
	public static function sanitize_placement( $data ) {

		$sanitized_data = [];

		if ( ! is_array( $data ) ) {
			return $sanitized_data;
		}

		if ( isset( $data['enabled'] ) ) {
			$sanitized_data['enabled'] = rest_sanitize_boolean( $data['enabled'] );
		}

		if ( isset( $data['provider'] ) ) {
			$sanitized_data['provider'] = sanitize_text_field( $data['provider'] );
		}

		if ( isset( $data['ad_unit'] ) ) {
			$sanitized_data['ad_unit'] = sanitize_text_field( $data['ad_unit'] );
		}

		if ( isset( $data['bidders_ids'] ) ) {
			$sanitized_data['bidders_ids'] = self::sanitize_bidders_ids( $data['bidders_ids'] );
		}

		if ( isset( $data['hooks'] ) ) {
			$sanitized_data['hooks'] = self::sanitize_hooks_data( $data['hooks'] );
		}

		if ( isset( $data['stick_to_top'] ) ) {
			$sanitized_data['stick_to_top'] = rest_sanitize_boolean( $data['stick_to_top'] );
		}

		return $sanitized_data;
	}

	/**
	 * Sanitize hooks data.
	 *
	 * @param array                  $hooks                    Hooks data.
	 * @param WP_REST_Request|string $request_or_placement_key Full details about the request or placement key.
	 *
	 * @return array Sanitized hooks data.
	 */
	public static function sanitize_hooks_data( $hooks, $request_or_placement_key = '' ) {
		if ( is_string( $request_or_placement_key ) ) {
			$placement_key = $request_or_placement_key;
		} else {
			$placement_key = (string) $request_or_placement_key->get_param( 'placement' );
		}
		if ( $placement_key ) {
			$placements = self::get_placements();
			$placement  = $placements[ $placement_key ];
		}
		$sanitized_hooks = [];
		if ( is_array( $hooks ) ) {
			foreach ( $hooks as $key => $hook ) {
				// If placement is available, check if hook is valid.
				if ( ! $placement_key || ( $placement && isset( $placement['hooks'][ $key ] ) ) ) {
					// Sanitize bidders IDs data.
					if ( isset( $hooks['bidders_ids'] ) ) {
						$hook['bidders_ids'] = self::sanitize_bidders_ids( $hook['bidders_ids'] );
					}
					$sanitized_hooks[ $key ] = $hook;
				}
			}
		}
		return $sanitized_hooks;
	}

	/**
	 * Sanitize bidders IDs.
	 *
	 * @param string $bidders_ids Bidders IDs.
	 *
	 * @return string Sanitized Bidders IDs.
	 */
	public static function sanitize_bidders_ids( $bidders_ids ) {
		if ( ! is_array( $bidders_ids ) || ! count( $bidders_ids ) ) {
			return [];
		}
		foreach ( $bidders_ids as $key => $bidders_id ) {
			$bidders_ids[ $key ] = sanitize_text_field( $bidders_id );
		}
		return $bidders_ids;
	}

	/**
	 * Get placements.
	 *
	 * @return WP_REST_Response containing the configured placements.
	 */
	public static function api_get_placements() {
		$placements = self::get_placements();
		// Filter out dynamic placements.
		$placements = array_filter(
			$placements,
			function( $placement ) {
				return ! empty( $placement['name'] ) && true === $placement['show_ui'];
			}
		);
		return \rest_ensure_response( $placements );
	}

	/**
	 * Update a placement.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the configured placements.
	 */
	public static function api_update_placement( $request ) {
		$data   = [
			'provider'     => $request['provider'],
			'ad_unit'      => $request['ad_unit'],
			'bidders_ids'  => $request['bidders_ids'],
			'hooks'        => $request['hooks'],
			'stick_to_top' => $request['stick_to_top'],
		];
		$result = self::update_placement( $request['placement'], $data );
		if ( \is_wp_error( $result ) ) {
			return \rest_ensure_response( $result );
		}
		return \rest_ensure_response( self::get_placements() );
	}

	/**
	 * Disable a placement.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the configured placements.
	 */
	public static function api_disable_placement( $request ) {
		$result = self::disable_placement( $request['placement'] );
		if ( \is_wp_error( $result ) ) {
			return \rest_ensure_response( $result );
		}
		return \rest_ensure_response( self::get_placements() );
	}

	/**
	 * Get the option name
	 *
	 * @param string $placement_key Placement key.
	 *
	 * @return string Option name.
	 */
	public static function get_option_name( $placement_key ) {
		return Settings::OPTION_NAME_PREFIX . 'placement_' . $placement_key;
	}

	/**
	 * Get placement ad unit data.
	 *
	 * @param string $placement_key Placement key.
	 * @param object $config        Placement configuration.
	 *
	 * @return object Placement ad unit data.
	 */
	public static function get_placement_data( $placement_key, $config = array() ) {
		/**
		 * Default placement data to return if not configured or stored yet.
		 */
		$default_data = wp_parse_args(
			isset( $config['data'] ) ? $config['data'] : [],
			[
				'enabled'  => isset( $config['default_enabled'] ) ? $config['default_enabled'] : false,
				'ad_unit'  => isset( $config['default_ad_unit'] ) ? $config['default_ad_unit'] : '',
				'provider' => Providers::get_default_provider(),
			]
		);

		$data = wp_parse_args(
			json_decode( get_option( self::get_option_name( $placement_key ) ), true ) ?? [],
			$default_data
		);

		// Generate unique ID if not yet stored.
		if ( isset( $data['ad_unit'] ) && $data['ad_unit'] && ! isset( $data['id'] ) ) {
			$data['id'] = self::get_id( [ $placement_key, $data['ad_unit'] ] );
		}
		if ( isset( $data['hooks'] ) ) {
			foreach ( $data['hooks'] as $hook_key => $hook ) {
				$data['hooks'][ $hook_key ] = wp_parse_args(
					$hook,
					[
						'provider' => Providers::get_default_provider(),
					]
				);
				if ( isset( $hook['ad_unit'] ) && $hook['ad_unit'] && ! isset( $hook['id'] ) ) {
					$data['hooks'][ $hook_key ]['id'] = self::get_id( [ $placement_key, $hook_key, $data['ad_unit'] ] );
				}
			}
		}

		/**
		 * Filters the placement data.
		 *
		 * @param array  $data          Placement data.
		 * @param string $placement_key Placement key.
		 * @param array  $config        Optional placement configuration.
		 */
		return apply_filters( 'newspack_ads_placement_data', $data, $placement_key, $config );
	}

	/**
	 * Get the available placements.
	 *
	 * A placement is an array with the following keys:
	 * - name: The name of the placement.
	 * - description: A description of the placement.
	 * - show_ui: Whether this placement can be edited through the wizard. Default is true.
	 * - default_enabled: Whether this placement should be enabled by default.
	 * - default_ad_unit: A default ad unit name to be used for this placement.
	 * - hook_name: The name of the WordPress action hook to inject an ad unit into.
	 * - hooks: An array of action hooks to inject an ad unit into.
	 *   - name: The friendly name of the hook.
	 *   - hook_name: The name of the WordPress action hook to inject the ad unit into.
	 * - supports: An array of supported placement features. Available features are:
	 *   - `stick_to_top`: Whether the placement should be sticky to the top of the page.
	 *
	 * @return array Placement objects.
	 */
	public static function get_placements() {

		$placements = apply_filters( 'newspack_ads_placements', self::$placements );

		foreach ( $placements as $placement_key => $placement ) {

			// Force disable `stick_to_top` on AMP.
			if ( isset( $placement['supports'] ) && Core::is_amp() ) {
				$feature_index = array_search( 'stick_to_top', $placement['supports'] );
				if ( false !== $feature_index ) {
					unset( $placement['supports'][ $feature_index ] );
				}
			}

			$placement['data'] = self::get_placement_data( $placement_key, $placement );

			$placements[ $placement_key ] = wp_parse_args(
				$placement,
				[
					'name'            => '',
					'description'     => '',
					'show_ui'         => true,
					'default_enabled' => false,
					'hook_name'       => '',
					'supports'        => [],
				]
			);
		}
		return $placements;
	}

	/**
	 * Register default placements.
	 */
	private static function register_default_placements() {
		$placements = array(
			'global_above_header' => array(
				'name'        => __( 'Global: Above Header', 'newspack-ads' ),
				'description' => __( 'Choose an ad unit to display above the header.', 'newspack-ads' ),
				'hook_name'   => 'before_header',
			),
			'global_below_header' => array(
				'name'        => __( 'Global: Below Header', 'newspack-ads' ),
				'description' => __( 'Choose an ad unit to display below the header.', 'newspack-ads' ),
				'hook_name'   => 'after_header',
			),
			'global_above_footer' => array(
				'name'        => __( 'Global: Above Footer', 'newspack-ads' ),
				'description' => __( 'Choose an ad unit to display above the footer.', 'newspack-ads' ),
				'hook_name'   => 'before_footer',
			),
			'sticky'              => array(
				'name'        => __( 'Mobile Sticky Footer', 'newspack-ads' ),
				'description' => __( 'Choose a sticky ad unit to display at the bottom of the viewport on mobile devices (recommended sizes are 320x50, 300x50)', 'newspack-ads' ),
				'hook_name'   => 'before_footer',
			),
		);
		foreach ( $placements as $placement_key => $placement_config ) {
			self::register_placement( $placement_key, $placement_config );
		}
	}

	/**
	 * Register a new ad placement.
	 *
	 * @param string $key    The placement key.
	 * @param array  $config The placement config.
	 *
	 * @return bool|\WP_Error True if placement was registered or error otherwise.
	 */
	public static function register_placement( $key, $config = [] ) {
		if ( empty( $key ) || empty( $config ) ) {
			return new \WP_Error( 'newspack_ads_invalid_placement', __( 'Invalid placement.', 'newspack-ads' ) );
		}
		if ( isset( self::$placements[ $key ] ) ) {
			return new \WP_Error( 'newspack_ads_placement_exists', __( 'Placement already exists.', 'newspack-ads' ) );
		}
		self::$placements[ $key ] = $config;

		/**
		 * Setup hooks for the placement.
		 */
		if ( isset( $config['hook_name'] ) ) {
			add_action(
				$config['hook_name'],
				function () use ( $key ) {
					self::inject_placement_ad( $key );
				}
			);
		}
		if ( isset( $config['hooks'] ) && ! empty( $config['hooks'] ) ) {
			foreach ( $config['hooks'] as $hook_key => $hook ) {
				add_action(
					$hook['hook_name'],
					function () use ( $key, $hook_key ) {
						self::inject_placement_ad( $key, $hook_key );
					}
				);
			}
		}
		return true;
	}

	/**
	 * Retrieves an associative array of all available placements data by ID.
	 *
	 * @return array[] Placements data by ID.
	 */
	public static function get_placements_data_by_id() {
		$placements       = self::get_placements();
		$placements_by_id = array();
		foreach ( $placements as $placement ) {

			// Skip disabled placements.
			if ( ! isset( $placement['data']['enabled'] ) || ! $placement['data']['enabled'] ) {
				continue;
			}

			// Add placement and its hooks data to array.
			if ( isset( $placement['data']['ad_unit'] ) && $placement['data']['ad_unit'] ) {
				$placements_by_id[ $placement['data']['id'] ] = $placement['data'];
				// Remove `enabled` key from placement data.
				unset( $placements_by_id[ $placement['data']['id'] ]['enabled'] );
			}

			if ( isset( $placement['data']['hooks'] ) ) {
				foreach ( $placement['data']['hooks'] as $hook ) {
					if ( isset( $hook['id'] ) && isset( $hook['ad_unit'] ) && $hook['ad_unit'] ) {
						$placements_by_id[ $hook['id'] ] = $hook;
					}
				}
			}

			// Remove hook data from root placement.
			if ( isset( $placement['data']['id'] ) && isset( $placements_by_id[ $placement['data']['id'] ]['hooks'] ) ) {
				unset( $placements_by_id[ $placement['data']['id'] ]['hooks'] );
			}
		}
		return $placements_by_id;
	}

	/**
	 * Whether the placement supports `stick to top` feature.
	 *
	 * @param string $placement_key Placement Key.
	 *
	 * @return bool Whether the placement supports `stick to top` feature.
	 */
	private static function is_stick_to_top( $placement_key ) {
		$placements = self::get_placements();
		if ( ! isset( $placements[ $placement_key ] ) ) {
			return false;
		}
		$placement = $placements[ $placement_key ];
		if ( in_array( 'stick_to_top', $placement['supports'], true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Update a placement with an ad unit. Enables the placement by default.
	 *
	 * @param string $placement_key Placement key.
	 * @param array  $data {
	 *   Placement data.
	 *
	 *   @type string   $ad_unit     Ad unit ID.
	 *   @type string[] $bidders_ids Optional associative array with bidders key and its placement ID.
	 *   @type array[]  $hooks       Optional hooks data with ad unit and bidders ids.
	 * }
	 *
	 * @return bool Whether the placement has been updated or not.
	 */
	public static function update_placement( $placement_key, $data ) {
		// Updates always enables the placement.
		$data['enabled'] = true;

		$data['provider'] = isset( $data['provider'] ) ? $data['provider'] : Providers::get_default_provider();

		// Generate unique ID.
		if ( isset( $data['ad_unit'] ) && $data['ad_unit'] ) {
			$data['id'] = self::get_id( [ $placement_key, $data['ad_unit'] ] );
		}
		if ( isset( $data['hooks'] ) ) {
			foreach ( $data['hooks'] as $hook_key => $hook ) {
				if ( isset( $hook['ad_unit'] ) && $hook['ad_unit'] ) {
					$data['hooks'][ $hook_key ]['id'] = self::get_id( [ $placement_key, $hook_key, $data['ad_unit'] ] );
				}
			}
		}

		// Handle "stick to top" feature update.
		if ( self::is_stick_to_top( $placement_key ) ) {
			$data['stick_to_top'] = isset( $data['stick_to_top'] ) ? (bool) $data['stick_to_top'] : false;
		}

		return update_option( self::get_option_name( $placement_key ), wp_json_encode( $data ) );
	}

	/**
	 * Disable a placement.
	 *
	 * @param string $placement_key Placement key.
	 *
	 * @return bool Whether the placement has been disabled or not.
	 */
	public static function disable_placement( $placement_key ) {
		$placements = self::get_placements();
		if ( ! isset( $placements[ $placement_key ] ) ) {
			return new \WP_Error( 'newspack_ads_invalid_placement', __( 'This placement does not exist.', 'newspack-ads' ) );
		}
		$placement_data = self::get_placement_data( $placement_key, $placements[ $placement_key ] );
		return update_option(
			self::get_option_name( $placement_key ),
			wp_json_encode(
				wp_parse_args(
					[ 'enabled' => false ],
					$placement_data
				)
			)
		);
	}

	/**
	 * Internal method for whether the placement can display an ad unit.
	 *
	 * @param string $placement_key Placement key.
	 *
	 * @return bool Whether the placement can display an ad unit.
	 */
	private static function can_display( $placement_key ) {
		$placements = self::get_placements();

		// Suppressed by post definition.
		if ( is_singular() ) {
			$post_suppressed = get_post_meta( get_the_ID(), 'newspack_ads_suppress_ads_placements', true );
			if ( is_array( $post_suppressed ) && ! empty( $post_suppressed ) && in_array( $placement_key, $post_suppressed, true ) ) {
				return false;
			}
		}

		// Placement does not exist.
		if ( ! isset( $placements[ $placement_key ] ) ) {
			return false;
		}

		$placement  = $placements[ $placement_key ];
		$is_enabled = $placement['data']['enabled'];
		// Placement is disabled.
		if ( ! $is_enabled ) {
			return false;
		}

		// Placement contains hooks.
		if ( isset( $placement['data']['hooks'] ) && count( $placement['data']['hooks'] ) ) {
			$can_display = false;
			foreach ( $placement['data']['hooks'] as $hook ) {
				// A hook contains an ad unit.
				if ( isset( $hook['ad_unit'] ) && $hook['ad_unit'] ) {
					$can_display = true;
				}
			}
			return $can_display;
		}

		// Placement contains an ad unit.
		if ( isset( $placement['data']['ad_unit'] ) && $placement['data']['ad_unit'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the placement can display an ad unit with filterable output.
	 *
	 * @param string $placement_key Placement key.
	 *
	 * @return bool Whether the placement can display an ad unit.
	 */
	public static function can_display_ad_unit( $placement_key ) {
		/**
		 * Filters whether the placement can display an ad unit.
		 *
		 * @param bool   $can_display   Whether the placement can display an ad unit.
		 * @param string $placement_key The placement key.
		 */
		return apply_filters( 'newspack_ads_placement_can_display_ad_unit', self::can_display( $placement_key ), $placement_key );
	}

	/**
	 * Generate an ID given a list of strings as arguments.
	 *
	 * @param string[] $args List of strings.
	 */
	private static function get_id( $args ) {
		return substr( hash( 'sha1', implode( $args ) ), 0, 10 );
	}

	/**
	 * Render ad unit mock with dimensions.
	 *
	 * @param string   $provider_id    Provider.
	 * @param string   $ad_unit        Ad unit.
	 * @param array    $placement_data Optional placement data to be serialized into the element.
	 * @param string[] $classes        Optional list of additional classes.
	 */
	public static function render_ad_unit_mock( $provider_id, $ad_unit, $placement_data = [], $classes = [] ) {
		$provider     = Providers::get_provider( $provider_id );
		$ad_unit_data = Providers::get_provider_unit_data( $provider_id, $ad_unit );
		if ( ! $ad_unit_data ) {
			return;
		}
		/**
		 * Default to a 300x200 size if no sizes are provided.
		 */
		$sizes  = $ad_unit_data['sizes'];
		$width  = count( $sizes ) ? max( array_column( $sizes, 0 ) ) : 300;
		$height = count( $sizes ) ? max( array_column( $sizes, 1 ) ) : 200;
		?>
		<div
			class="newspack-ads__ad-placement-mock <?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php ( ! empty( $placement_data ) ) ? printf( 'data-placement="%s"', esc_attr( wp_json_encode( $placement_data ) ) ) : ''; ?>
		>
			<div
				class="newspack-ads__ad-placement-mock__content"
				style="width:<?php echo esc_attr( $width ); ?>px;height:<?php echo esc_attr( $height ); ?>px;"
			>
				<svg
					class="newspack-ads__ad-placement-mock__svg"
					width="<?php echo esc_attr( $width ); ?>"
					viewbox="0 0 <?php echo esc_attr( $width ); ?> <?php echo esc_attr( $height ); ?>"
				>
					<rect
						width="<?php echo esc_attr( $width ); ?>"
						height="<?php echo esc_attr( $height ); ?>"
						strokeDasharray="2"
					/>
					<line x1="0" y1="0" x2="100%" y2="100%" strokeDasharray="2" />
				</svg>
				<span class="newspack-ads__ad-placement-mock__label">
					<?php printf( '%s - %s', esc_html( $provider->get_provider_name() ), esc_html( $ad_unit_data['name'] ) ); ?>
					<br />
					<?php
					if ( count( $sizes ) ) {
						echo esc_html( implode( ', ', array_map( 'Newspack_Ads\get_size_string', $sizes ) ) );
					} else {
						esc_html_e( 'Unknown size', 'newspack-ads' );
					}
					?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Inject Ad into given placement.
	 *
	 * @param string $placement_key Placement key.
	 * @param string $hook_key      Optional hook key in case of multiple hooks available.
	 */
	public static function inject_placement_ad( $placement_key, $hook_key = '' ) {

		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}

		if ( ! self::can_display_ad_unit( $placement_key ) ) {
			return;
		}

		$placements = self::get_placements();
		$placement  = $placements[ $placement_key ];

		$stick_to_top = self::is_stick_to_top( $placement_key ) && isset( $placement['data']['stick_to_top'] ) ? (bool) $placement['data']['stick_to_top'] : false;

		if ( $hook_key && isset( $placement['data']['hooks'], $placement['data']['hooks'][ $hook_key ] ) ) {
			$placement_data = $placement['data']['hooks'][ $hook_key ];
		} else {
			$placement_data = $placement['data'];
		}

		// Provider is not active.
		if (
			isset( $placement_data['provider'] ) &&
			! Providers::is_provider_active( $placement_data['provider'] )
		) {
			return;
		}

		if ( ! isset( $placement_data['ad_unit'] ) || empty( $placement_data['ad_unit'] ) ) {
			return;
		}

		$provider_id = isset( $placement_data['provider'] ) && ! empty( $placement_data['provider'] ) ? $placement_data['provider'] : Providers::get_default_provider();
		$ad_unit     = $placement_data['ad_unit'];

		$is_amp        = Core::is_amp();
		$is_sticky_amp = 'sticky' === $placement_key && true === $is_amp;

		/**
		 * Fires before an ad is injected into a placement.
		 *
		 * @param string $placement_key  The placement key.
		 * @param string $hook_key       The placement hook hey.
		 * @param array  $placement_data The placement data.
		 */
		do_action( 'newspack_ads_before_placement_ad', $placement_key, $hook_key, $placement_data );

		/**
		 * Filters the classnames applied to the ad container.
		 *
		 * @param bool[] Associative array with classname as key and boolean as value.
		 * @param string Placement key.
		 * @param string Hook key.
		 * @param array  Placement data.
		 */
		$classnames = apply_filters(
			'newspack_ads_placement_classnames',
			[
				'newspack_global_ad'                => ! $is_sticky_amp,
				'newspack_amp_sticky_ad__container' => $is_sticky_amp,
				$placement_key                      => true,
				$placement_key . '-' . $hook_key    => ! empty( $hook_key ),
				'hook-' . $hook_key                 => ! empty( $hook_key ),
				'stick-to-top'                      => $stick_to_top,
				'fixed-height'                      => Settings::get_setting( 'fixed_height', 'active' ),
			],
			$placement_key,
			$hook_key,
			$placement_data
		);

		$classnames_str = implode( ' ', array_keys( array_filter( $classnames ) ) );

		?>
		<div class='<?php echo esc_attr( $classnames_str ); ?>'>
			<?php if ( 'sticky' === $placement_key && false === $is_amp ) : ?>
				<button class='newspack_sticky_ad__close'></button>
			<?php endif; ?>
			<?php
			/**
			 * Render ad unit mock when in WordPress Customizer.
			 */
			if ( ! empty( $GLOBALS['wp_customize'] ) ) {
				self::render_ad_unit_mock( $provider_id, $ad_unit, $placement['data'] );
			} else {
				Providers::render_placement_ad_code(
					$ad_unit,
					$provider_id,
					$placement_key,
					$hook_key,
					$placement_data
				);
			}
			?>
		</div>
		<?php

		do_action( 'newspack_ads_after_placement_ad', $placement_key, $hook_key, $placement_data );
	}
}
Placements::init();
