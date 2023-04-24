<?php
/**
 * Wizard management API endpoints.
 *
 * @package Newspack\API
 */

namespace Newspack\API;

use \WP_REST_Controller;
use \WP_Error;
use Newspack\Wizards;

defined( 'ABSPATH' ) || exit;

/**
 * REST API endpoints for managing wizards.
 */
class Wizards_Controller extends WP_REST_Controller {

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
	protected $resource_name = 'wizards';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		// Register newspack/v1/wizards/some-wizard endpoint.
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
							'sanitize_callback' => [ $this, 'sanitize_wizard_slug' ],
						],
					],
				],
			]
		);

		// Register newspack/v1/wizards/some-wizard/complete endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<slug>[\a-z]+)\/complete',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'complete_item' ],
					'permission_callback' => [ $this, 'complete_item_permissions_check' ],
					'args'                => [
						'slug' => [
							'sanitize_callback' => [ $this, 'sanitize_wizard_slug' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Get info about one wizard.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$slug = $request['slug'];

		$is_valid_wizard = $this->validate_wizard( $slug );
		if ( is_wp_error( $is_valid_wizard ) ) {
			return $is_valid_wizard;
		}

		$wizard   = Wizards::get_wizard( $slug );
		$response = [
			'name'      => $wizard->get_name(),
			'url'       => $wizard->get_url(),
			'completed' => $wizard->is_completed(),
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Mark a wizard as completed.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function complete_item( $request ) {
		$slug = $request['slug'];

		$is_valid_wizard = $this->validate_wizard( $slug );
		if ( is_wp_error( $is_valid_wizard ) ) {
			return $is_valid_wizard;
		}

		$wizard = Wizards::get_wizard( $slug );
		$wizard->set_completed();

		return rest_ensure_response( true );
	}

	/**
	 * Check capabilities when getting plugin info.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
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
	 * Check capabilities when completing a wizard.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return bool|WP_Error
	 */
	public function complete_item_permissions_check( $request ) {
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
	 * Check that a wizard slug is valid.
	 *
	 * @param string $slug The plugin slug.
	 * @return bool|WP_Error
	 */
	protected function validate_wizard( $slug ) {
		if ( ! Wizards::get_wizard( $slug ) ) {
			return new WP_Error(
				'newspack_rest_invalid_wizard',
				esc_html__( 'Resource does not exist.', 'newspack' ),
				[
					'status' => 404,
				]
			);
		}

		return true;
	}

	/**
	 * Sanitize the slug for a wizard.
	 *
	 * @param string $slug The wizard slug.
	 * @return string
	 */
	public function sanitize_wizard_slug( $slug ) {
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
}
