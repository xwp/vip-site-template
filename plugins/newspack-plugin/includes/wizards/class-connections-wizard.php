<?php
/**
 * Newspack's Connections Wizard
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error, \WP_Query;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/wizards/class-wizard.php';

/**
 * Easy interface for setting up general store info.
 */
class Connections_Wizard extends Wizard {

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-connections-wizard';

	/**
	 * The capability required to access this wizard.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return \esc_html__( 'Connections', 'newspack' );
	}

	/**
	 * Enqueue Subscriptions Wizard scripts and styles.
	 */
	public function enqueue_scripts_and_styles() {
		parent::enqueue_scripts_and_styles();

		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) !== $this->slug ) {
			return;
		}

		\wp_register_script(
			'newspack-connections-wizard',
			Newspack::plugin_url() . '/dist/connections.js',
			$this->get_script_dependencies( [] ),
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		\wp_localize_script(
			'newspack-connections-wizard',
			'newspack_connections_data',
			[
				'can_connect_google'   => OAuth::is_proxy_configured( 'google' ),
				'can_connect_fivetran' => OAuth::is_proxy_configured( 'fivetran' ),
				'can_use_webhooks'     => defined( 'NEWSPACK_EXPERIMENTAL_WEBHOOKS' ) && NEWSPACK_EXPERIMENTAL_WEBHOOKS,
			]
		);
		\wp_enqueue_script( 'newspack-connections-wizard' );

		\wp_register_style(
			'newspack-connections-wizard',
			Newspack::plugin_url() . '/dist/connections.css',
			$this->get_style_dependencies(),
			NEWSPACK_PLUGIN_VERSION
		);
		\wp_style_add_data( 'newspack-connections-wizard', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-connections-wizard' );
	}
}
