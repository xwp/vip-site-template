<?php
/**
 * Newspack Ads Settings
 *
 * @package Newspack
 */

namespace Newspack_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Settings Class.
 */
final class Settings {

	const API_NAMESPACE      = 'newspack-ads/v1';
	const API_CAPABILITY     = 'manage_options';
	const OPTION_NAME_PREFIX = '_newspack_ads_';

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
	}

	/**
	 * Register the endpoints needed to fetch and update settings.
	 */
	public static function register_api_endpoints() {

		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_settings_list' ],
				'permission_callback' => [ __CLASS__, 'api_permissions_check' ],
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update_section' ],
				'permission_callback' => [ __CLASS__, 'api_permissions_check' ],
				'args'                => [
					'section'  => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'settings' => [
						'required'          => true,
						'sanitize_callback' => [ __CLASS__, 'api_sanitize_settings' ],
					],
				],
			]
		);
	}

	/**
	 * Check capabilities for using API.
	 *
	 * @return bool|\WP_Error True or error object.
	 */
	public static function api_permissions_check() {
		if ( ! current_user_can( self::API_CAPABILITY ) ) {
			return new \WP_Error(
				'newspack_ads_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack-ads' ),
				[
					'status' => 403,
				]
			);
		}
		return true;
	}

	/**
	 * Sanitize settings coming from the API.
	 *
	 * @param array           $settings Settings to sanitize.
	 * @param WP_REST_Request $request  Full details about the request.
	 *
	 * @return array Sanitized settings.
	 */
	public static function api_sanitize_settings( $settings, $request ) {
		$section            = (string) $request['section'];
		$sanitized_settings = [];
		foreach ( $settings as $key => $value ) {
			$config = self::get_setting_config( $section, $key );
			if ( isset( $config['options'] ) && isset( $config['multiple'] ) && true === $config['multiple'] ) {
				$value = array_map(
					function ( $value ) use ( $config ) {
						settype( $value, $config['type'] );
						return $value;
					},
					$value
				);
			} else {
				settype( $value, $config['type'] );
			}
			$sanitized_settings[ $key ] = $value;
		}
		return $sanitized_settings;
	}

	/**
	 * Get settings list.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_get_settings_list() {
		return \rest_ensure_response( self::get_settings_list( true ) );
	}

	/**
	 * Update setting section.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_update_section( $request ) {
		$result = self::update_section( $request['section'], $request['settings'] );
		if ( is_wp_error( $result ) ) {
			return \rest_ensure_response( $result );
		}
		return \rest_ensure_response( self::get_settings_list( true ) );
	}

	/**
	 * Get the setting option name to be used on the options table.
	 *
	 * @param object $setting The setting object to retrieve the key from.
	 *
	 * @return string Option name.
	 */
	private static function get_setting_option_name( $setting ) {
		$option_name = isset( $setting['key'] ) ? $setting['section'] . '_' . $setting['key'] : $setting['section'];
		return apply_filters( 'newspack_ads_setting_option_name', self::OPTION_NAME_PREFIX . $option_name, $setting );
	}

	/**
	 * Retrieves list of configured settings.
	 *
	 * A setting is an array with the following keys:
	 * - description: The description of the setting.
	 * - help: The help text for the setting.
	 * - section: The section the setting is in.
	 * - key: The key of the setting. Should be used along with the section name.
	 * - type: The type of the setting. Used to typecast the value.
	 * - default: The default value of the setting.
	 * - options: Options to be used for a select field. Values outside of this array will not update.
	 *   - name: The name of the option.
	 *   - value: The value of the option.
	 * - public: Whether the setting value is allowed to be displayed publicly on the frontend.
	 *
	 * Settings without `key` or with the `key` of value `active` should be
	 * intepreted as section headers on the UI. In the case of `active`, it is a
	 * module that can be activated or deactivated.
	 *
	 * @param boolean $assoc Whether to return an associative array with the section as key or indexed array.
	 *
	 * @return array Indexed or associative array of configured settings grouped by section name.
	 */
	public static function get_settings_list( $assoc = false ) {
		$settings_list = [];

		$default_setting = array(
			'section' => '',
			'type'    => 'string',
			'public'  => false,
		);

		$settings_list = apply_filters( 'newspack_ads_settings_list', $settings_list );

		// Add default settings and get values.
		$settings_list = array_map(
			function ( $item ) use ( $default_setting ) {
				$item          = wp_parse_args( $item, $default_setting );
				$default_value = isset( $item['default'] ) ? $item['default'] : false;
				$value         = get_option( self::get_setting_option_name( $item ), $default_value );
				if ( false !== $value ) {
					if ( isset( $item['options'] ) && isset( $item['multiple'] ) && true === $item['multiple'] ) {
						$value = array_map(
							function ( $value ) use ( $item ) {
								settype( $value, $item['type'] );
								return $value;
							},
							$value
						);
					} else {
						settype( $value, $item['type'] );
					}
					$item['value'] = $value;
				}
				return $item;
			},
			$settings_list
		);

		if ( $assoc ) {
			$settings_list = array_reduce(
				$settings_list,
				function ( $carry, $item ) {
					$carry[ $item['section'] ][] = $item;
					return $carry;
				},
				array()
			);
		}

		return $settings_list;
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
		$settings_list    = self::get_settings_list();
		$filtered_configs = array_filter(
			$settings_list,
			function( $setting ) use ( $section, $key ) {
				return isset( $setting['key'] ) && $key === $setting['key'] && isset( $setting['section'] ) && $section === $setting['section'];
			}
		);
		return array_shift( $filtered_configs );
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
			return new \WP_Error( 'newspack_ads_invalid_setting_update', __( 'Invalid setting.', 'newspack-ads' ) );
		}
		if ( isset( $config['options'] ) && is_array( $config['options'] ) ) {
			$accepted_values = array_map(
				function ( $option ) {
					return $option['value'];
				},
				$config['options']
			);
			if ( isset( $config['multiple'] ) && true === $config['multiple'] ) {
				if ( ! is_array( $value ) ) {
					// translators: %s is the description of the option.
					return new \WP_Error( 'newspack_ads_invalid_setting_update', sprintf( __( 'Value for "%s" should be an array.', 'newspack-ads' ), $config['description'] ) );
				}
				$value = array_map(
					function ( $option ) use ( $accepted_values ) {
						return in_array( $option, $accepted_values, true ) ? $option : '';
					},
					$value
				);
				$value = array_filter( $value );
			} elseif ( ! in_array( $value, $accepted_values, true ) ) {
				// translators: %s is the description of the option.
				return new \WP_Error( 'newspack_ads_invalid_setting_update', sprintf( __( 'Invalid setting value for "%s".', 'newspack-ads' ), $config['description'] ) );
			}
		}
		do_action( 'newspack_ads_before_update_setting', $section, $key, $value );
		$updated = update_option(
			self::get_setting_option_name( $config ),
			is_array( $value ) ?
				array_map(
					function( $value ) use ( $config ) {
						return self::sanitize_setting_option( $config['type'], $value );
					},
					$value
				) :
				self::sanitize_setting_option( $config['type'], $value )
		);
		do_action( 'newspack_ads_after_update_setting', $updated, $section, $key, $value );
		return $updated;
	}

	/**
	 * Update settings from a specific section.
	 *
	 * @param string $section  The key for the section to update.
	 * @param array  $data     Associative array with data to update.
	 *
	 * @return array|WP_Error The settings list or error if a setting update fails.
	 */
	public static function update_section( $section, $data ) {
		foreach ( $data as $key => $value ) {
			$updated = self::update_setting( $section, $key, $value );
			if ( \is_wp_error( $updated ) ) {
				return $updated;
			}
		}
		return self::get_settings_list();
	}

	/**
	 * Get settings values grouped by sections.
	 *
	 * @param string  $section   The section to retrieve settings from.
	 * @param boolean $is_public Whether to return only public settings.
	 *
	 * @return object Settings values or empty array if no values were found.
	 */
	public static function get_settings( $section = '', $is_public = false ) {
		$list   = self::get_settings_list();
		$values = [];
		foreach ( $list as $setting ) {
			if ( ! isset( $values[ $setting['section'] ] ) ) {
				$values[ $setting['section'] ] = [];
			}
			// Skip non-public settings if specified.
			if ( true === $is_public && ( ! isset( $setting['public'] ) || true !== $setting['public'] ) ) {
				continue;
			}
			// Skip settings without key or value.
			if ( ! isset( $setting['key'] ) || ! isset( $setting['value'] ) ) {
				continue;
			}
			$values[ $setting['section'] ][ $setting['key'] ] = $setting['value'];
		}
		if ( $section ) {
			return isset( $values[ $section ] ) ? $values[ $section ] : [];
		} else {
			return $values;
		}
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $section       The section to retrieve settings from.
	 * @param string $key           The key of the setting to retrieve.
	 * @param mixed  $default_value The default value to return if the setting is not found.
	 *
	 * @return mixed The setting value or null if not found.
	 */
	public static function get_setting( $section, $key, $default_value = null ) {
		$settings = self::get_settings( $section );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
	}
}
Settings::init();
