<?php
/**
 * Newspack's Settings
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/wizards/class-wizard.php';

/**
 * Settings.
 */
class Settings extends Wizard {
	const SETTINGS_OPTION_NAME = 'newspack_settings';

	const MODULE_ENABLED_PREFIX = 'module_enabled_';

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-settings-wizard';

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
		$this->hidden = true;
	}

	/**
	 * Get all settings.
	 */
	private static function get_settings() {
		$default_settings = [
			self::MODULE_ENABLED_PREFIX . 'rss' => false,
		];
		return get_option( self::SETTINGS_OPTION_NAME, $default_settings );
	}

	/**
	 * Get the list of available optional modules.
	 */
	private static function get_available_optional_modules() {
		return [ 'rss' ];
	}

	/**
	 * Get settings.
	 */
	public static function api_get_settings() {
		return self::get_settings();
	}

	/**
	 * Check if an optional module is active.
	 *
	 * @param string $module_name Name of the module.
	 */
	public static function is_optional_module_active( $module_name ) {
		$settings     = self::api_get_settings();
		$setting_name = self::MODULE_ENABLED_PREFIX . $module_name;
		if ( isset( $settings[ $setting_name ] ) ) {
			return $settings[ $setting_name ];
		}
		return false;
	}

	/**
	 * Activate an optional module.
	 *
	 * @param string $module_name Name of the module.
	 */
	public static function activate_optional_module( $module_name ) {
		return self::update_setting( self::MODULE_ENABLED_PREFIX . $module_name, true );
	}

	/**
	 * Update a single setting value.
	 *
	 * @param string $key Setting key.
	 * @param string $value Setting value.
	 */
	private static function update_setting( $key, $value ) {
		$settings = self::get_settings();
		if ( isset( $settings[ $key ] ) ) {
			$settings[ $key ] = $value;
			update_option( self::SETTINGS_OPTION_NAME, $settings );
		}
		return $settings;
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with the info.
	 */
	public static function api_update_settings( $request ) {
		$settings = self::get_settings();
		foreach ( self::get_available_optional_modules() as $module_name ) {
			$setting_name              = self::MODULE_ENABLED_PREFIX . $module_name;
			$settings[ $setting_name ] = $request->get_param( $setting_name );
		}
		update_option( self::SETTINGS_OPTION_NAME, $settings );
		return self::api_get_settings();
	}

	/**
	 * Register the endpoints needed for the wizard screens.
	 */
	public function register_api_endpoints() {
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);

		$required_args = array_reduce(
			self::get_available_optional_modules(),
			function( $acc, $module_name ) {
				$acc[ self::MODULE_ENABLED_PREFIX . $module_name ] = [
					'required'          => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				];
				return $acc;
			},
			[]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug,
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => $required_args,
			]
		);
	}

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return \esc_html__( 'Settings', 'newspack' );
	}

	/**
	 * Get the description of this wizard.
	 *
	 * @return string The wizard description.
	 */
	public function get_description() {
		return \esc_html__( 'Configure settings.', 'newspack' );
	}

	/**
	 * Get the duration of this wizard.
	 *
	 * @return string A description of the expected duration (e.g. '10 minutes').
	 */
	public function get_length() {
		return esc_html__( '10 minutes', 'newspack' );
	}
}
