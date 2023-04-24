<?php
/**
 * Plugin management API endpoints.
 *
 * @package Newspack\API
 */

namespace Newspack\API;

use \WP_REST_Controller;
use \WP_Error;
use Newspack\Plugin_Manager;
use Newspack\Handoff_Banner;
use Newspack\Configuration_Managers;

defined( 'ABSPATH' ) || exit;

/**
 * REST API endpoints for managing plugins.
 */
class Plugins_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = NEWSPACK_API_NAMESPACE;

	/**
	 * Endpoint resource.
	 *
	 * @var string
	 */
	protected $resource_name = 'plugins';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		// Register newspack/v1/plugins endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name,
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		// Register newspack/v1/plugins/some-plugin endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_plugin_slug' ],
						],
					],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		// Register newspack/v1/plugins/some-plugin/activate endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)\/activate',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'activate_item' ],
					'permission_callback' => [ $this, 'activate_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_plugin_slug' ],
						],
					],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		// Register newspack/v1/plugins/some-plugin/deactivate endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)\/deactivate',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'deactivate_item' ],
					'permission_callback' => [ $this, 'deactivate_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_plugin_slug' ],
						],
					],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		// Register newspack/v1/plugins/some-plugin/install endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)\/install',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'install_item' ],
					'permission_callback' => [ $this, 'install_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_plugin_slug' ],
						],
					],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		// Register newspack/v1/plugins/some-plugin/uninstall endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)\/uninstall',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'uninstall_item' ],
					'permission_callback' => [ $this, 'uninstall_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_plugin_slug' ],
						],
					],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		// Register newspack/v1/plugins/some-plugin/handoff endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)\/handoff',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'handoff_item' ],
					'permission_callback' => [ $this, 'handoff_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_plugin_slug' ],
						],
					],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		// Register newspack/v1/plugins/some-plugin/configure.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)\/configure',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'configure_item' ],
					'permission_callback' => [ $this, 'configure_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_plugin_slug' ],
						],
					],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);
	}

	/**
	 * Get info about all managed plugins.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		return rest_ensure_response( Plugin_Manager::get_managed_plugins() );
	}

	/**
	 * Get info about one managed plugin.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$slug = $request['slug'];

		$is_valid_plugin = $this->validate_managed_plugin( $slug );
		if ( is_wp_error( $is_valid_plugin ) ) {
			return $is_valid_plugin;
		}

		$managed_plugins = Plugin_Manager::get_managed_plugins();

		$plugin = $managed_plugins[ $slug ];

		$plugin['Configured'] = \Newspack\Configuration_Managers::is_configured( $slug );

		return rest_ensure_response( $plugin );
	}

	/**
	 * Activate a managed plugin (installing it if needed).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function activate_item( $request ) {
		$slug = $request['slug'];

		$is_valid_plugin = $this->validate_managed_plugin( $slug );
		if ( is_wp_error( $is_valid_plugin ) ) {
			return $is_valid_plugin;
		}

		$managed_plugins = Plugin_Manager::get_managed_plugins();

		$result = Plugin_Manager::activate( $slug );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->get_item( $request );
	}

	/**
	 * Deactivate a managed plugin.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function deactivate_item( $request ) {
		$slug = $request['slug'];

		$is_valid_plugin = $this->validate_managed_plugin( $slug );
		if ( is_wp_error( $is_valid_plugin ) ) {
			return $is_valid_plugin;
		}

		$result = Plugin_Manager::deactivate( $slug );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->get_item( $request );
	}

	/**
	 * Install a managed plugin.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function install_item( $request ) {
		$slug = $request['slug'];

		$is_valid_plugin = $this->validate_managed_plugin( $slug );
		if ( is_wp_error( $is_valid_plugin ) ) {
			return $is_valid_plugin;
		}

		$managed_plugins = Plugin_Manager::get_managed_plugins();
		if ( 'wporg' === $managed_plugins[ $slug ]['Download'] ) {
			$result = Plugin_Manager::install( $slug );
		} else {
			$result = Plugin_Manager::install( $managed_plugins[ $slug ]['Download'] );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->get_item( $request );
	}

	/**
	 * Uninstall a managed plugin.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function uninstall_item( $request ) {
		$slug = $request['slug'];

		$is_valid_plugin = $this->validate_managed_plugin( $slug );
		if ( is_wp_error( $is_valid_plugin ) ) {
			return $is_valid_plugin;
		}

		$result = Plugin_Manager::uninstall( $slug );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->get_item( $request );
	}

	/**
	 * Handoff to a managed plugin.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function handoff_item( $request ) {
		$slug = $request['slug'];

		$is_valid_plugin = $this->validate_managed_plugin( $slug );
		if ( is_wp_error( $is_valid_plugin ) ) {
			return $is_valid_plugin;
		}

		$show_on_block_editor = $request->get_param( 'showOnBlockEditor' );
		Handoff_Banner::register_handoff_for_plugin( $slug, (bool) $show_on_block_editor );
		$managed_plugins = Plugin_Manager::get_managed_plugins();

		$response           = $managed_plugins[ $slug ];
		$edit_link          = $request->get_param( 'editLink' );
		$handoff_return_url = $request->get_param( 'handoffReturnUrl' );

		if ( 'admin.php?page=googlesitekit-module-analytics' === $edit_link && 'google-site-kit' === $slug ) {
			$sitekit_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'google-site-kit' );
			$sitekit_manager->activate_module( 'analytics' );
		}

		if ( ! empty( $edit_link ) ) {
			$response['HandoffLink'] = $edit_link;
		}
		if ( ! empty( $handoff_return_url ) ) {
			update_option( NEWSPACK_HANDOFF_RETURN_URL, esc_url( $handoff_return_url ) );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Configure a managed plugin (installing and activating it if needed).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function configure_item( $request ) {
		$slug = $request['slug'];

		$is_valid_plugin = $this->validate_managed_plugin( $slug );
		if ( is_wp_error( $is_valid_plugin ) ) {
			return $is_valid_plugin;
		}

		$result = Plugin_Manager::activate( $slug );
		if ( is_wp_error( $result ) ) {
			/* If the plugin is already installed and active, simply proceed to configuration */
			if ( 'newspack_plugin_already_active' !== $result->get_error_code() ) {
				return $result;
			}
		}

		$managed_plugins = Plugin_Manager::get_managed_plugins();

		\Newspack\Configuration_Managers::configure( $slug );
		return rest_ensure_response( $managed_plugins[ $slug ] );
	}

	/**
	 * Check capabilities when getting plugins info.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot view this resource.', 'newspack' ),
				[
					'status' => $this->authorization_status_code(),
				]
			);
		}

		return true;
	}

	/**
	 * Check capabilities when getting plugin info.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check capabilities when activating (with potential installation) a plugin.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function activate_item_permissions_check( $request ) {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => $this->authorization_status_code(),
				]
			);
		}

		return true;
	}

	/**
	 * Check capabilities when deactivating a plugin.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function deactivate_item_permissions_check( $request ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => $this->authorization_status_code(),
				]
			);
		}

		return true;
	}

	/**
	 * Check capabilities when installing a plugin.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function install_item_permissions_check( $request ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => $this->authorization_status_code(),
				]
			);
		}

		return true;
	}

	/**
	 * Check capabilities when uninstalling a plugin.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function uninstall_item_permissions_check( $request ) {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return new WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => $this->authorization_status_code(),
				]
			);
		}

		return true;
	}

	/**
	 * Check capabilities when getting handoff data for a plugin.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function handoff_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => $this->authorization_status_code(),
				]
			);
		}

		return true;
	}

	/**
	 * Check capabilities when configuring a plugin.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function configure_item_permissions_check( $request ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => $this->authorization_status_code(),
				]
			);
		}

		return true;
	}

	/**
	 * Check that a plugin slug is for a managed plugin.
	 *
	 * @param string $slug The plugin slug.
	 * @return bool|WP_Error
	 */
	protected function validate_managed_plugin( $slug ) {
		$managed_plugins = Plugin_Manager::get_managed_plugins();
		if ( ! isset( $managed_plugins[ $slug ] ) ) {
			return new WP_Error(
				'newspack_rest_invalid_plugin',
				esc_html__( 'Resource does not exist.', 'newspack' ),
				[
					'status' => 404,
				]
			);
		}

		return true;
	}

	/**
	 * Sanitize the slug for a plugin.
	 *
	 * @param string $slug The plugin slug.
	 * @return string
	 */
	public function sanitize_plugin_slug( $slug ) {
		return sanitize_title( $slug );
	}

	/**
	 * Get the appropriate status code for errors.
	 *
	 * @return int
	 */
	public function authorization_status_code() {
		$status = 401;
		if ( is_user_logged_in() ) {
			$status = 403;
		}

		return $status;
	}

	/**
	 * Get the REST schema for the endpoints.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->resource_name,
			'type'       => 'object',
			'properties' => [
				'Name'        => [
					'description' => __( 'The name of the plugin.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'Description' => [
					'description' => __( 'The description of the plugin.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'Author'      => [
					'description' => __( 'The author of the plugin.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'Version'     => [
					'description' => __( 'The version of the plugin (if available).', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'PluginURI'   => [
					'description' => __( 'The URL of the plugin site.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'AuthorURI'   => [
					'description' => __( 'The URL of the plugin author.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'TextDomain'  => [
					'description' => __( 'The textdomain of the plugin (if available).', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'DomainPath'  => [
					'description' => __( 'The path for the textdomain of the plugin (if available).', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'Download'    => [
					'description' => __( 'The location of the plugin download.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'Status'      => [
					'description' => __( 'The status of the plugin.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'enum'        => [ 'active', 'inactive', 'uninstalled' ],
					'readonly'    => true,
				],
				'Slug'        => [
					'description' => __( 'The slug of the plugin.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'HandoffLink' => [
					'description' => __( 'The edit link of the plugin.', 'newspack' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			],
		];
	}
}
