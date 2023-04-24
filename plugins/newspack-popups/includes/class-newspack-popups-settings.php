<?php
/**
 * Newspack Popups Settings Page
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages Settings page.
 */
class Newspack_Popups_Settings {
	const NEWSPACK_POPUPS_SETTINGS_PAGE = 'newspack-popups-settings-admin';

	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_plugin_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Add settings page.
	 */
	public static function add_plugin_page() {
		add_submenu_page(
			'edit.php?post_type=' . Newspack_Popups::NEWSPACK_POPUPS_CPT,
			__( 'Campaigns Settings', 'newspack-popups' ),
			__( 'Settings', 'newspack-popups' ),
			'manage_options',
			self::NEWSPACK_POPUPS_SETTINGS_PAGE,
			[ __CLASS__, 'create_admin_page' ]
		);
	}

	/**
	 * Settings page callback.
	 */
	public static function create_admin_page() {
		?>
		<div id="newspack-popups-settings-root"></div>
		<?php
	}

	/**
	 * Update settings from the standalone Settings page.
	 * The standalone Settings page is an alternative UI used only when the main
	 * Newspack plugin's UI is not available.
	 *
	 * @param object $options options.
	 */
	public static function set_settings_standalone( $options ) {
		$settings_list = self::get_settings();
		$option_name   = $options['option_name'];
		$setting       = array_reduce(
			$settings_list,
			function( $acc, $config ) use ( $option_name ) {
				if ( $option_name === $config['key'] ) {
					$acc = $config;
				}
				return $acc;
			},
			false
		);

		if ( ! $setting ) {
			return new \WP_Error(
				'newspack_popups_settings_error',
				sprintf(
					// Translators: error message if trying to update an invalid setting key.
					esc_html__( 'Option %s does not exist.', 'newspack-popups' ),
					$option_name
				)
			);
		}

		$option_type  = 'select' === $setting['type'] ? 'string' : $setting['type'];
		$option_value = self::sanitize_setting_option( $option_type, $options['option_value'] );

		if ( update_option( $option_name, $option_value ) ) {
			return true;
		} else {
			return new \WP_Error(
				'newspack_popups_settings_error',
				esc_html__( 'Error updating the settings.', 'newspack-popups' )
			);
		}
	}

	/**
	 * Update settings from a specific section.
	 *
	 * @param WP_Request $request Request object with section and settings to update.
	 *
	 * @return array|WP_Error The settings list or error if a setting update fails.
	 */
	public static function update_section( $request ) {
		$section  = $request['section'];
		$settings = $request['settings'];
		foreach ( $settings as $key => $value ) {
			$updated = self::update_setting( $section, $key, $value );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
		return self::get_settings( true );
	}

	/**
	 * Update a setting from a provided section.
	 *
	 * @param string $section The section to update.
	 * @param string $key     The key to update.
	 * @param mixed  $value   The value to update.
	 *
	 * @return bool|WP_Error Whether the value was updated or error if key does not match settings configuration.
	 */
	private static function update_setting( $section, $key, $value ) {
		$config = self::get_setting_config( $section, $key );
		if ( ! $config ) {
			return new WP_Error( 'newspack_ads_invalid_setting_update', __( 'Invalid setting.', 'newspack-ads' ) );
		}
		if ( isset( $config['options'] ) && is_array( $config['options'] ) ) {
			$accepted_values = array_map(
				function ( $option ) {
					return $option['value'];
				},
				$config['options']
			);
			if ( ! in_array( $value, $accepted_values, true ) ) {
				// translators: %s is the description of the option.
				return new WP_Error( 'newspack_ads_invalid_setting_update', sprintf( __( 'Invalid setting value for "%s".', 'newspack-ads' ), $config['description'] ) );
			}
		}
		$updated = update_option( $config['key'], self::sanitize_setting_option( $config['type'], $value ) );
		return $updated;
	}

	/**
	 * Retrieves a sanitized setting value to be stored as wp_option.
	 *
	 * @param string $type The type of the setting.
	 * @param mixed  $value The value to sanitize.
	 *
	 * @return mixed The sanitized value.
	 */
	private static function sanitize_setting_option( $type, $value ) {
		switch ( $type ) {
			case 'int':
			case 'integer':
			case 'boolean':
				return (int) $value;
			case 'float':
				return (float) $value;
			case 'string':
				return sanitize_text_field( $value );
			default:
				return '';
		}
	}

	/**
	 * Retrieves a setting configuration.
	 *
	 * @param string $section The section the setting is in.
	 * @param string $key The key of the setting.
	 *
	 * @return object|null Setting configuration or null if not found.
	 */
	public static function get_setting_config( $section, $key ) {
		$settings_list    = self::get_settings();
		$filtered_configs = array_filter(
			$settings_list,
			function( $setting ) use ( $section, $key ) {
				return isset( $setting['key'] ) && $key === $setting['key'] && isset( $setting['section'] ) && $section === $setting['section'];
			}
		);
		return array_shift( $filtered_configs );
	}

	/**
	 * Return a single setting value.
	 *
	 * @param string $key Key name.
	 */
	public static function get_setting( $key ) {
		return get_option( $key, true );
	}

	/**
	 * Return all settings.
	 *
	 * @param boolean $assoc If true, return settings list as an associative array keyed by option name.
	 *                       If false, return a flat array of settings objects.
	 * @param boolean $get_segments If true, append segmentation info to response.
	 *                              Used by the AMP Access request to match reader segment to prompt segment.
	 *
	 * @return array Array of settings objects.
	 */
	public static function get_settings( $assoc = false, $get_segments = false ) {
		$donor_landing_options = [
			[
				'label' => __( '-- None --', 'newspack-popups' ),
				'value' => '',
			],
		];

		// Before executing the query, make sure we can filter it to remove any CPTs that might be added by other plugins.
		add_action( 'pre_get_posts', [ __CLASS__, 'prevent_other_post_types_in_page_query' ], PHP_INT_MAX );
		$donor_landing_options_query = new \WP_Query(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_parent'    => 0,
				'posts_per_page' => -1,
			]
		);
		// Remove the query filter so we don't unintentionally affect other queries.
		remove_action( 'pre_get_posts', [ __CLASS__, 'prevent_other_post_types_in_page_query' ], PHP_INT_MAX );

		if ( $donor_landing_options_query->have_posts() ) {
			foreach ( $donor_landing_options_query->posts as $page ) {
				$donor_landing_options[] = [
					'label' => $page->post_title,
					'value' => (string) $page->ID,
				];
			}
		}

		$settings_list = [
			[
				'description' => __( 'Donor Settings', 'newspack-ads' ),
				'help'        => __( 'Configure when readers are considered donors.', 'newspack-ads' ),
				'section'     => 'donor_settings',
				'key'         => 'active',
				'type'        => 'boolean',
				'public'      => true,
				'value'       => null,
			],
			[
				'section'     => 'donor_settings',
				'key'         => 'newspack_popups_donor_landing_page',
				'type'        => 'string',
				'value'       => self::donor_landing_page(),
				'default'     => '',
				'description' => __( 'Donor landing page', 'newspack-popups' ),
				'help'        => __(
					"Set a page on your site as a donation landing page. Once a reader views this page, they will be considered a donor. This is helpful if you're using an off-site donation platform but still want to target donors as an audience segment.",
					'newspack-popups'
				),
				'options'     => $donor_landing_options,
			],
			[
				'section'     => 'donor_settings',
				'key'         => 'newspack_popups_mc_donor_merge_field',
				'type'        => 'string',
				'value'       => get_option( 'newspack_popups_mc_donor_merge_field', 'DONAT' ),
				'default'     => 'DONAT',
				'description' => __( 'Mailchimp donor merge fields', 'newspack-popups' ),
				'help'        => __(
					'A comma-delimited list of strings to match against Mailchimp merge field names. If a Mailchimp merge field name contains one of these strings and a subscriber has a true value in this field, the subscriber will be considered a donor for segmentation purposes.',
					'newspack-popups'
				),
			],
			[
				'description' => __( 'General Settings', 'newspack-ads' ),
				'help'        => __( 'Other settings for Newspack Campaigns.', 'newspack-ads' ),
				'section'     => 'general_settings',
				'key'         => 'active',
				'type'        => 'boolean',
				'public'      => true,
				'value'       => null,
			],
			[
				'section'     => 'general_settings',
				'key'         => 'newspack_popups_non_interative_mode',
				'type'        => 'boolean',
				'value'       => self::is_non_interactive(),
				'default'     => false,
				'description' => __( 'Non-interactive mode', 'newspack-popups' ),
				'help'        => __( 'Use this setting in high traffic scenarios. No API requests will be made, reducing server load. Inline prompts will be shown to all users, and overlay prompts will be suppressed.', 'newspack-popups' ),
				'public'      => false,
			],
			[
				'section'     => 'general_settings',
				'key'         => 'newspack_popups_dismiss_overlays_on_tap',
				'type'        => 'boolean',
				'value'       => self::enable_dismiss_overlays_on_background_tap(),
				'default'     => false,
				'description' => __( 'Dismiss overlays on background tap', 'newspack-popups' ),
				'help'        => __( 'If enabled, readers can dismiss overlay prompts by tapping on the colored overlay background underneath the prompt content. This will make it easier for readers to dismiss prompts, but potentially result in less reader engagement.', 'newspack-popups' ),
				'public'      => false,
			],
		];

		$default_setting = array(
			'section' => '',
			'type'    => 'string',
			'public'  => false,
		);

		// Add default settings and get values.
		$settings_list = array_map(
			function ( $item ) use ( $default_setting ) {
				if ( 'active' === $item['key'] ) {
					return $item;
				}

				$item          = wp_parse_args( $item, $default_setting );
				$default_value = isset( $item['default'] ) ? $item['default'] : false;
				$value         = isset( $item['value'] ) ? $item['value'] : $default_value;
				if ( false !== $value ) {
					settype( $value, $item['type'] );
					$item['value'] = $value;
				}
				return $item;
			},
			$settings_list
		);

		// Append segment configuration if coming from the lightweight API.
		if ( $get_segments ) {
			$settings_list = array_merge(
				$settings_list,
				[
					[
						'key'   => 'all_segments',
						'value' => array_reduce(
							Newspack_Popups_Segmentation::get_segments(),
							function( $acc, $item ) {
								$acc[ $item['id'] ]             = $item['configuration'];
								$acc[ $item['id'] ]['priority'] = $item['priority'];
								return $acc;
							},
							[]
						),
					],
					[
						'key'   => Newspack_Popups::NEWSPACK_POPUPS_ACTIVE_CAMPAIGN_GROUP,
						'value' => get_option( Newspack_Popups::NEWSPACK_POPUPS_ACTIVE_CAMPAIGN_GROUP ),
					],
				]
			);
		}

		if ( $assoc ) {
			$settings_list = array_reduce(
				$settings_list,
				function ( $carry, $item ) {
					$carry[ $item['section'] ][] = $item;
					return $carry;
				},
				[]
			);
		}

		return $settings_list;
	}

	/**
	 * Is the non-interactive setting on?
	 */
	public static function is_non_interactive() {
		// Handle legacy option name.
		return get_option( 'newspack_newsletters_non_interative_mode', false ) || get_option( 'newspack_popups_non_interative_mode', false ) || get_option( 'newspack_popups_non_interactive_mode', false );
	}

	/**
	 * Donor landing page.
	 */
	public static function donor_landing_page() {
		return get_option( 'newspack_popups_donor_landing_page', '' );
	}

	/**
	 * Enable overlay dismiss on background tap.
	 *
	 * @return boolean
	 */
	public static function enable_dismiss_overlays_on_background_tap() {
		return get_option( 'newspack_popups_dismiss_overlays_on_tap', false );
	}

	/**
	 * Load up common JS/CSS for settings.
	 */
	public static function admin_enqueue_scripts() {
		$screen = get_current_screen();

		if ( Newspack_Popups::NEWSPACK_POPUPS_CPT . '_page_' . self::NEWSPACK_POPUPS_SETTINGS_PAGE !== $screen->base ) {
			return;
		}

		\wp_register_script(
			'newspack-popups-settings',
			plugins_url( '../dist/settings.js', __FILE__ ),
			[ 'wp-components', 'wp-api-fetch' ],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/settings.js' ),
			true
		);
		wp_localize_script(
			'newspack-popups-settings',
			'newspack_popups_settings',
			self::get_settings()
		);
		\wp_enqueue_script( 'newspack-popups-settings' );
		\wp_register_style(
			'newspack-popups-settings',
			plugins_url( '../dist/settings.css', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/settings.css' )
		);
		\wp_style_add_data( 'newspack-popups-settings', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-popups-settings' );
	}

	/**
	 * Activate prompts by campaign.
	 *
	 * @param int $ids Campaign IDs to publish.
	 * @return bool Whether operation was successful.
	 */
	public static function batch_publish( $ids ) {
		if ( empty( $ids ) ) {
			return new \WP_Error(
				'newspack_popups_settings_error',
				esc_html__( 'Invalid campaign IDs.', 'newspack' )
			);
		}

		$all_campaigns = new \WP_Query(
			[
				'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status'    => [ 'draft', 'pending', 'future', 'publish' ],
				'posts_per_page' => 100,
			]
		);

		if ( $all_campaigns->have_posts() ) {
			foreach ( $all_campaigns->posts as $campaign ) {
				if ( in_array( $campaign->ID, $ids ) ) {
					if ( 'publish' !== $campaign->post_status ) {
						wp_publish_post( $campaign );
					}
				} else {
					if ( 'publish' === $campaign->post_status ) {
						$campaign->post_status = 'draft';
						wp_update_post( $campaign );
					}
				}
			}
		}

		return true;
	}

	/**
	 * Unpublish prompts by campaign.
	 *
	 * @param int $ids Campaign IDs to unpublish.
	 * @return bool Whether operation was successful.
	 */
	public static function batch_unpublish( $ids ) {
		if ( empty( $ids ) ) {
			return new \WP_Error(
				'newspack_popups_settings_error',
				esc_html__( 'Invalid campaign IDs.', 'newspack' )
			);
		}

		$campaigns_to_unpublish = new \WP_Query(
			[
				'post_type'      => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status'    => [ 'publish' ],
				'post__in'       => $ids,
				'posts_per_page' => 100,
			]
		);

		if ( $campaigns_to_unpublish->have_posts() ) {
			foreach ( $campaigns_to_unpublish->posts as $campaign ) {
				$campaign->post_status = 'draft';
				wp_update_post( $campaign );
			}
		}

		return true;
	}

	/**
	 * Prevents other plugins from adding additional post types to the page query.
	 * Note: No query checking needed because this callback is only added for the one query we need to filter.
	 *
	 * @param WP_Query $query The WP query object.
	 */
	public static function prevent_other_post_types_in_page_query( $query ) {
		$query->set( 'post_type', 'page' ); // phpcs:ignore WordPressVIPMinimum.Hooks.PreGetPosts.PreGetPosts
	}
}

if ( is_admin() ) {
	Newspack_Popups_Settings::init();
}
