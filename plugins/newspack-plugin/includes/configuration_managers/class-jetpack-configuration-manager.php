<?php
/**
 * Jetpack plugin configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of Jetpack.
 */
class Jetpack_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'jetpack';

	/**
	 * Configure AMP for Newspack use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		return true;
	}

	/**
	 * Is Jetpack installed and connected?
	 *
	 * @return bool Plugin ready state.
	 */
	public function is_configured() {
		if ( $this->is_active() && class_exists( 'Jetpack' ) && \Jetpack::is_active() ) {
			return true;
		}
		return false;
	}

	/**
	 * Get the Jetpack-Mailchimp connection settings.
	 *
	 * @see jetpack/_inc/lib/core-api/wpcom-endpoints/class-wpcom-rest-api-v2-endpoint-mailchimp.php
	 * @return Array with the info or WP_Error on error.
	 */
	public function get_mailchimp_connection_status() {
		if ( ! class_exists( 'Jetpack_Options' ) ) {
			return new \WP_Error(
				'newspack_missing_required_plugin',
				esc_html__( 'The Jetpack plugin is not installed and activated. Install and/or activate it to access this feature.', 'newspack' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}

		$is_wpcom = ( defined( 'IS_WPCOM' ) && IS_WPCOM );
		$site_id  = $is_wpcom ? get_current_blog_id() : \Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return new \WP_Error(
				'unavailable_site_id',
				__( 'Sorry, something is wrong with your Jetpack connection.', 'newspack' ),
				[
					'status' => 403,
					'level'  => 'fatal',
				]
			);
		}

		$connect_url = sprintf( 'https://wordpress.com/marketing/connections/%s', rawurlencode( $site_id ) );

		$option = get_option( 'jetpack_mailchimp', false );
		if ( $option ) {
			$data = json_decode( $option, true );

			$mailchimp_connected = $data ? isset( $data['follower_list_id'], $data['keyring_id'] ) : false;
		} else {
			$mailchimp_connected = false;
		}

		return array(
			'connected'  => $mailchimp_connected,
			'connectURL' => $connect_url,
		);
	}

	/**
	 * Is Related Posts module active?
	 *
	 * @return bool Returns true if the module is currently active.
	 */
	public function is_related_posts_enabled() {
		return class_exists( 'Jetpack' ) && \Jetpack::is_module_active( 'related-posts' );
	}
}
