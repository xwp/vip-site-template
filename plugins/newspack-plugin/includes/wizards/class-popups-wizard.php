<?php
/**
 * Newspack Pop-ups Wizard
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error, \WP_Query;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/wizards/class-wizard.php';
require_once NEWSPACK_ABSPATH . 'includes/popups-analytics/class-popups-analytics-utils.php';

/**
 * Interface for managing Pop-ups.
 */
class Popups_Wizard extends Wizard {

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-popups-wizard';

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
	}

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return \esc_html__( 'Campaigns', 'newspack' );
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
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_popup' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'api_delete_popup' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/(?P<id>\d+)/restore',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_restore_popup' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/(?P<original_id>\d+)/(?P<id>\d+)/duplicate',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_duplicate_title' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'original_id' => [
						'sanitize_callback' => 'absint',
					],
					'id'          => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/(?P<id>\d+)/duplicate',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_duplicate_popup' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id'    => [
						'sanitize_callback' => 'absint',
					],
					'title' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/(?P<id>\d+)/publish',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_publish_popup' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/(?P<id>\d+)/publish',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'api_unpublish_popup' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Plugin settings.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_plugin_settings' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/settings',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_settings_section' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'option_name'  => [
						'validate_callback' => [ 'Newspack_Popups_API', 'validate_settings_option_name' ],
						'sanitize_callback' => 'esc_attr',
					],
					'option_value' => [
						'sanitize_callback' => 'esc_attr',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/segmentation',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_segments' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/segmentation',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_create_segment' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/segmentation/(?P<id>\w+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_update_segment' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/segmentation/(?P<id>\w+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'api_delete_segment' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/segmentation-reach',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_segment_reach' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'config' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/segmentation-sort',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_sort_segments' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'segments' => [
						'sanitize_callback' => [ $this, 'sanitize_array' ],
					],
				],
			]
		);

		// Register newspack/v1/popups-analytics/report endpoint.
		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/popups-analytics/report',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_popups_analytics_report' ],
					'permission_callback' => [ $this, 'api_permissions_check' ],
					'args'                => [
						'start_date'        => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'end_date'          => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'event_label_id'    => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'event_action'      => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'with_report_by_id' => [
							'sanitize_callback' => 'rest_sanitize_boolean',
						],
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/batch-publish',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_batch_publish' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/batch-publish',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'api_batch_unpublish' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/create-campaign/',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_campaign_create' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'name' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/delete-campaign/(?P<id>\w+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'api_campaign_delete' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/archive-campaign/(?P<id>\w+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_campaign_archive' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/archive-campaign/(?P<id>\w+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'api_campaign_unarchive' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/duplicate-campaign/(?P<id>\w+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_campaign_duplicate' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id'   => [
						'sanitize_callback' => 'absint',
					],
					'name' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			NEWSPACK_API_NAMESPACE,
			'/wizard/' . $this->slug . '/rename-campaign/(?P<id>\w+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_campaign_rename' ],
				'permission_callback' => [ $this, 'api_permissions_check' ],
				'args'                => [
					'id'   => [
						'sanitize_callback' => 'absint',
					],
					'name' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Enqueue Wizard scripts and styles.
	 */
	public function enqueue_scripts_and_styles() {
		parent::enqueue_scripts_and_styles();

		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) !== $this->slug ) {
			return;
		}

		\wp_enqueue_script(
			'newspack-popups-wizard',
			Newspack::plugin_url() . '/dist/popups.js',
			$this->get_script_dependencies( [ 'wp-html-entities', 'wp-date' ] ),
			NEWSPACK_PLUGIN_VERSION,
			true
		);

		$preview_post = '';
		if ( method_exists( 'Newspack_Popups', 'preview_post_permalink' ) ) {
			$preview_post = \Newspack_Popups::preview_post_permalink();
		}

		$preview_archive = '';
		if ( method_exists( 'Newspack_Popups', 'preview_archive_permalink' ) ) {
			$preview_archive = \Newspack_Popups::preview_archive_permalink();
		}

		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$custom_placements                     = $newspack_popups_configuration_manager->get_custom_placements();
		$overlay_placements                    = $newspack_popups_configuration_manager->get_overlay_placements();
		$overlay_sizes                         = $newspack_popups_configuration_manager->get_overlay_sizes();
		$preview_query_keys                    = $newspack_popups_configuration_manager->preview_query_keys();

		\wp_localize_script(
			'newspack-popups-wizard',
			'newspack_popups_wizard_data',
			[
				'preview_post'       => $preview_post,
				'preview_archive'    => $preview_archive,
				'frontend_url'       => get_site_url(),
				'custom_placements'  => $custom_placements,
				'overlay_placements' => $overlay_placements,
				'overlay_sizes'      => $overlay_sizes,
				'preview_query_keys' => $preview_query_keys,
				'experimental'       => Reader_Activation::is_enabled(),
			]
		);

		\wp_register_style(
			'newspack-popups-wizard',
			Newspack::plugin_url() . '/dist/popups.css',
			$this->get_style_dependencies(),
			NEWSPACK_PLUGIN_VERSION
		);
		\wp_style_add_data( 'newspack-popups-wizard', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-popups-wizard' );
	}

	/**
	 * API endpoint callbacks
	 */

	/**
	 * Get data to render Wizard.
	 *
	 * @param array|null $extras optional array of parameters to include in response.
	 * @return WP_REST_Response
	 */
	public function api_get_settings( $extras = [] ) {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );

		$response = array_merge(
			[
				'prompts'   => [],
				'segments'  => [],
				'campaigns' => [],
				'settings'  => [],
			],
			(array) $extras
		);

		if ( $newspack_popups_configuration_manager->is_configured() ) {
			$response['prompts']   = array_map(
				function( $prompt ) {
					$prompt['edit_link'] = get_edit_post_link( $prompt['id'] );
					return $prompt;
				},
				$newspack_popups_configuration_manager->get_prompts( true, true )
			);
			$response['segments']  = $newspack_popups_configuration_manager->get_segments( true );
			$response['settings']  = $newspack_popups_configuration_manager->get_settings();
			$response['campaigns'] = $newspack_popups_configuration_manager->get_campaigns();
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Set terms for one Popup.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with the info.
	 */
	public function api_set_popup_terms( $request ) {
		$id       = $request['id'];
		$terms    = $request['terms'];
		$taxonomy = $request['taxonomy'];

		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );

		$response = $newspack_popups_configuration_manager->set_popup_terms( $id, $terms, $taxonomy );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->api_get_settings();
	}

	/**
	 * Update settings for a Pop-up.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with the info.
	 */
	public function api_update_popup( $request ) {
		$id     = $request['id'];
		$config = $request['config'];

		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$response                              = $newspack_popups_configuration_manager->set_popup_options( $id, $config['options'] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = $newspack_popups_configuration_manager->set_popup_terms( $id, self::sanitize_terms( $config['campaign_groups'] ), 'newspack_popups_taxonomy' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = $newspack_popups_configuration_manager->set_popup_terms( $id, self::sanitize_terms( $config['categories'] ), 'category' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = $newspack_popups_configuration_manager->set_popup_terms( $id, self::sanitize_terms( $config['tags'] ), 'post_tag' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->api_get_settings();
	}

	/**
	 * Delete a Pop-up.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_delete_popup( $request ) {
		$id = $request['id'];

		$popup = get_post( $id );
		if ( is_a( $popup, 'WP_Post' ) && 'newspack_popups_cpt' === $popup->post_type ) {
			if ( 'trash' === $popup->post_status ) {
				wp_delete_post( $id, true );
			} else {
				wp_trash_post( $id );
			}
		}

		return $this->api_get_settings();
	}

	/**
	 * Restore a deleted a Pop-up.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_restore_popup( $request ) {
		$id = $request['id'];

		$popup = get_post( $id );
		if ( is_a( $popup, 'WP_Post' ) && 'newspack_popups_cpt' === $popup->post_type ) {
			wp_untrash_post( $id );
		}

		return $this->api_get_settings();
	}

	/**
	 * Get default title for a duplicated prompt.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_get_duplicate_title( $request ) {
		$cm            = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$default_title = $cm->get_duplicate_title( $request['original_id'], $request['id'] );
		return $default_title;
	}

	/**
	 * Duplicate a Pop-up.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_duplicate_popup( $request ) {
		$cm           = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$duplicate_id = $cm->duplicate_popup( $request['id'], $request['title'] );
		return $this->api_get_settings( [ 'duplicated' => $duplicate_id ] );
	}

	/**
	 * Publish a Pop-up.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_publish_popup( $request ) {
		$id = $request['id'];

		$popup = get_post( $id );
		if ( is_a( $popup, 'WP_Post' ) && 'newspack_popups_cpt' === $popup->post_type ) {
			wp_publish_post( $id );
		}
		return $this->api_get_settings();
	}

	/**
	 * Unpublish a Pop-up.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response with complete info to render the Engagement Wizard.
	 */
	public function api_unpublish_popup( $request ) {
		$id = $request['id'];

		$popup = get_post( $id );
		if ( is_a( $popup, 'WP_Post' ) && 'newspack_popups_cpt' === $popup->post_type ) {
			$popup->post_status = 'draft';
			wp_update_post( $popup );
		}
		return $this->api_get_settings();
	}

	/**
	 * API sanitization and validation functions
	 */

	/**
	 * Sanitize array of terms.
	 *
	 * @param array $terms Array of terms to sanitize.
	 * @return array Sanitized array.
	 */
	public static function sanitize_terms( $terms ) {
		if ( ! $terms ) {
			$terms = [];
		}
		$categories = is_array( $terms ) ? $terms : [];
		$sanitized  = [];
		foreach ( $terms as $term ) {
			if ( ! isset( $term['id'] ) && isset( $term['term_id'] ) ) {
				$term['id'] = $term['term_id'];
			}
			$term['id']   = isset( $term['id'] ) ? absint( $term['id'] ) : null;
			$term['name'] = isset( $term['name'] ) ? sanitize_title( $term['name'] ) : null;
			$sanitized[]  = $term;
		}
		return $sanitized;
	}

	/**
	 * Recursively sanitize an array of arbitrary values.
	 *
	 * @param array $array Array to be sanitized.
	 * @return array Sanitized array.
	 */
	public static function sanitize_array( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::sanitize_array( $value );
			} elseif ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$value = intval( $value );
			} else {
				$value = boolval( $value );
			}
		}

		return $array;
	}

	/**
	 * Get the plugin settings.
	 */
	public static function api_get_plugin_settings() {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		return $newspack_popups_configuration_manager->get_settings();
	}

	/**
	 * Set the plugin settings.
	 *
	 * @param array $options options.
	 */
	public static function api_update_settings_section( $options ) {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		return $newspack_popups_configuration_manager->update_settings_section( $options );
	}

	/**
	 * Get Campaigns Analytics report.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_popups_analytics_report( $request ) {
		$options = array(
			'start_date'        => $request['start_date'],
			'end_date'          => $request['end_date'],
			'event_label_id'    => $request['event_label_id'],
			'event_action'      => $request['event_action'],
			'with_report_by_id' => $request['with_report_by_id'],
		);
		return rest_ensure_response( \Popups_Analytics_Utils::get_report( $options ) );
	}

	/**
	 * Get Campaign Segments.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_get_segments() {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$response                              = $newspack_popups_configuration_manager->get_segments();
		return $response;
	}

	/**
	 * Create a Campaign Segment.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_create_segment( $request ) {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$response                              = $newspack_popups_configuration_manager->create_segment(
			[
				'name'          => $request['name'],
				'configuration' => $request['configuration'],
			]
		);
		return $response;
	}

	/**
	 * Update a Campaign Segment.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_update_segment( $request ) {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$response                              = $newspack_popups_configuration_manager->update_segment(
			[
				'id'            => $request['id'],
				'name'          => $request['name'],
				'configuration' => $request['configuration'],
			]
		);
		return $response;
	}

	/**
	 * Delete a Campaign Segment.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_delete_segment( $request ) {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$response                              = $newspack_popups_configuration_manager->delete_segment( $request['id'] );
		return $response;
	}

	/**
	 * Get a specific segment's potential reach.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_get_segment_reach( $request ) {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$response                              = $newspack_popups_configuration_manager->get_segment_reach( json_decode( $request['config'] ) );
		return $response;
	}

	/**
	 * Update all segments with the given priority sorting.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_sort_segments( $request ) {
		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$response                              = $newspack_popups_configuration_manager->sort_segments( $request['segmentIds'] );
		return $response;
	}

	/**
	 * Activate a campaign group.
	 * Activate a campaign.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_batch_publish( $request ) {
		$data = $request->get_json_params();
		$ids  = $data['ids'];

		if ( empty( $ids ) ) {
			return new WP_Error(
				'newspack_missing_ids',
				esc_html__( 'Could not activate campaigns.', 'newspack' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}

		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );

		$response = $newspack_popups_configuration_manager->batch_publish( $ids );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->api_get_settings();
	}

	/**
	 * Deactivate a campaign group.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_batch_unpublish( $request ) {
		$data = $request->get_json_params();
		$ids  = $data['ids'];

		if ( empty( $ids ) ) {
			return new WP_Error(
				'newspack_missing_ids',
				esc_html__( 'Could not deactivate campaigns.', 'newspack' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}

		$newspack_popups_configuration_manager = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );

		$response = $newspack_popups_configuration_manager->batch_unpublish( $ids );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->api_get_settings();
	}

	/**
	 * Create a campaign.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_campaign_create( $request ) {
		$cm     = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$result = $cm->create_campaign( $request['name'] );
		return $this->api_get_settings(
			[
				'term_id' => $result,
			]
		);
	}

	/**
	 * Duplicate a campaign.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_campaign_duplicate( $request ) {
		$cm     = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$result = $cm->duplicate_campaign( $request['id'], $request['name'] );
		return $this->api_get_settings(
			[
				'term_id' => $result,
			]
		);
	}

	/**
	 * Archive a campaign.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_campaign_archive( $request ) {
		$cm = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$cm->archive_campaign( $request['id'], true );
		return $this->api_get_settings();
	}

	/**
	 * Unarchive a campaign.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_campaign_unarchive( $request ) {
		$cm = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$cm->archive_campaign( $request['id'], false );
		return $this->api_get_settings();
	}

	/**
	 * Delete a campaign.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_campaign_delete( $request ) {
		$cm = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$cm->delete_campaign( $request['id'] );
		return $this->api_get_settings();
	}

	/**
	 * Rename a campaign.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function api_campaign_rename( $request ) {
		$cm = Configuration_Managers::configuration_manager_class_for_plugin_slug( 'newspack-popups' );
		$cm->rename_campaign( $request['id'], $request['name'] );
		return $this->api_get_settings();
	}
}
