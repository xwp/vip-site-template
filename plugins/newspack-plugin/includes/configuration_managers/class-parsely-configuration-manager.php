<?php
/**
 * Parse.ly plugin configuration manager.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of the Parse.ly Plugin.
 */
class Parsely_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'wp-parsely';

	/**
	 * Configure Parse.ly for Newspack use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		$active = $this->is_active();
		if ( ! $active || is_wp_error( $active ) ) {
			return $active;
		}

		if ( ! is_plugin_active( 'wp-parsely/wp-parsely.php' ) ) {
			return new \WP_Error(
				'newspack_missing_required_plugin',
				esc_html__( 'Parse.ly plugin is not installed and activated. Install and/or activate it to access this feature.', 'newspack' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}

		// Set the Parse.ly API key for the site.
		$parsely_settings = get_option( 'parsely', [] );
		if ( ! $parsely_settings ) {
			// Build the default Parse.ly settings if they don't exist yet.
			// @see https://github.com/Parsely/wp-parsely/blob/64cc83c6ed229c93b9e938cccc37b794a1977e7c/src/class-parsely.php#L38-L56.
			$parsely_settings = [
				'apikey'                      => '',
				'content_id_prefix'           => '',
				'api_secret'                  => '',
				'use_top_level_cats'          => false,
				'custom_taxonomy_section'     => 'category',
				'cats_as_tags'                => false,
				'track_authenticated_users'   => true,
				'lowercase_tags'              => true,
				'force_https_canonicals'      => false,
				'track_post_types'            => [ 'post' ],
				'track_page_types'            => [ 'page' ],
				'disable_javascript'          => false,
				'disable_amp'                 => false,
				'meta_type'                   => 'json_ld',
				'logo'                        => '',
				'metadata_secret'             => '',
				'parsely_wipe_metadata_cache' => false,
			];
		}

		// The Site ID field is confusingly stored in the 'apikey' field and is the site's domain.
		// This is the only non-default field we need to auto-populate.
		if ( empty( $parsely_settings['apikey'] ) ) {
			$parsely_settings['apikey'] = $this->get_parsely_api_key();
			update_option( 'parsely', $parsely_settings );
		}

		$this->set_newspack_has_configured( true );
		return true;
	}

	/**
	 * Get the appropriate API key for Parse.ly.
	 * On Newspack-hosted sites, this will be the host without 'http(s)' or 'www' or slashes.
	 *
	 * @return string API key.
	 */
	public function get_parsely_api_key() {
		$site_url  = get_site_url();
		$site_host = wp_parse_url( $site_url, PHP_URL_HOST );
		return sanitize_text_field( str_replace( 'www.', '', $site_host ) );
	}
}
