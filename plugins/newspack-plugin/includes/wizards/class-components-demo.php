<?php
/**
 * Newspack Temporary Components Demo.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/wizards/class-wizard.php';

/**
 * Temporary components demo.
 */
class Components_Demo extends Wizard {

	/**
	 * The slug of this wizard.
	 *
	 * @var string
	 */
	protected $slug = 'newspack-components-demo';

	/**
	 * The capability required to access this wizard.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Priority setting for ordering admin submenu items.
	 *
	 * @var int.
	 */
	protected $menu_priority = 100;

	/**
	 * Whether the wizard should be displayed in the Newspack submenu.
	 *
	 * @var bool.
	 */
	protected $hidden = true;

	/**
	 * Get the name for this wizard.
	 *
	 * @return string The wizard name.
	 */
	public function get_name() {
		return esc_html__( 'Components demo', 'newspack' );
	}

	/**
	 * Enqueue Subscriptions Wizard scripts and styles.
	 */
	public function enqueue_scripts_and_styles() {
		parent::enqueue_scripts_and_styles();
		wp_enqueue_media();

		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) !== $this->slug ) {
			return;
		}

		wp_enqueue_script(
			'newspack-components-demo',
			Newspack::plugin_url() . '/dist/componentsDemo.js',
			$this->get_script_dependencies( [ 'wp-html-entities' ] ),
			NEWSPACK_PLUGIN_VERSION,
			true
		);
	}
}
