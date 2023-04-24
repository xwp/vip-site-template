<?php
/**
 * Newspack Admin Plugins Screen
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack functionality on the WP Admin Plugins screen.
 */
class Admin_Plugins_Screen {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'all_plugins', [ $this, 'inject_managed_plugins' ] );
		add_filter( 'plugin_action_links', [ $this, 'modify_action_links' ], 10, 3 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_and_styles' ] );
		add_action( 'admin_action_newspack_install_plugin', [ $this, 'handle_plugin_install' ] );
		add_action( 'all_admin_notices', [ $this, 'plugin_install_notices' ] );
	}

	/**
	 * Add all the managed plugins to the plugins table.
	 *
	 * @param  array $plugins Array of plugin info.
	 * @return array Modified $plugins.
	 */
	public function inject_managed_plugins( $plugins ) {
		// Don't add managed plugins to the plugins list when using WP CLI.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $plugins;
		}

		$managed_plugins   = Plugin_Manager::get_managed_plugins();
		$installed_plugins = Plugin_Manager::get_installed_plugins();

		foreach ( $managed_plugins as $slug => $plugin_info ) {
			// If plugin is already installed, just use that info.
			if ( isset( $installed_plugins[ $slug ] ) ) {
				continue;
			}

			if ( isset( $plugin_info['Quiet'] ) && $plugin_info['Quiet'] ) {
				continue;
			}

			$plugins[ $slug ] = $plugin_info;
		}

		$plugins = $this->order_newspack_plugins_first( $plugins );

		return $plugins;
	}

	/**
	 * Set up a custom ordering for the screen, with managed plugins at the top.
	 *
	 * @param  array $plugins Array of plugin info.
	 * @return array Modified $plugins.
	 */
	protected function order_newspack_plugins_first( $plugins ) {
		global $orderby, $status;

		if ( 'all' !== $status ) {
			return $plugins;
		}

		$orderby = 'NewspackOrderIndex'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Override.Prohibited

		$managed_plugins = Plugin_Manager::get_managed_plugins();

		foreach ( $plugins as $index => $plugin ) {
			$slug = strpos( $index, '.' ) ? dirname( $index ) : $index;

			if ( isset( $managed_plugins[ $slug ] ) ) {
				$plugins[ $index ]['NewspackOrderIndex'] = '0-Newspack-Plugin-' . $plugin['Name'];
				$plugins[ $index ]['Name']               = '&mdash; ' . $plugin['Name'];
			} elseif ( 'newspack-plugin' === $slug ) {
				$plugins[ $index ]['NewspackOrderIndex'] = '0-Newspack-Plugin';
			} else {
				$plugins[ $index ]['NewspackOrderIndex'] = '1-' . $plugin['Name'];
			}
		}

		return $plugins;
	}

	/**
	 * Remove 'Activate' and 'Delete' links for uninstalled plugins, and add an 'Install' link.
	 *
	 * @param  array  $actions Array of plugin action links.
	 * @param  string $plugin_file The plugin file.
	 * @param  array  $plugin_data Information about the plugin.
	 * @return array  Modified $actions.
	 */
	public function modify_action_links( $actions, $plugin_file, $plugin_data ) {
		$plugin_slug       = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : $plugin_file;
		$installed_plugins = Plugin_Manager::get_installed_plugins();
		$managed_plugins   = array_keys( Plugin_Manager::get_managed_plugins() );

		if ( in_array( $plugin_slug, $managed_plugins ) && ! isset( $installed_plugins[ $plugin_slug ] ) ) {
			unset( $actions['activate'] );
			unset( $actions['delete'] );
			if ( current_user_can( 'install_plugins' ) ) {
				if ( empty( $plugin_data['Download'] ) ) {
					$actions['install'] = __( 'Premium', 'newspack' );
				} else {
					$actions['install'] = '<a href="' .
						wp_nonce_url( 'plugins.php?action=newspack_install_plugin&plugin=' . urlencode( $plugin_slug ), 'newspack-install-plugin_' . $plugin_slug, 'install_nonce' ) .
						'" class="edit" aria-label="' .
						/* translators: %s - plugin name */
						esc_attr( sprintf( __( 'Install %s', 'newspack' ), 'newspack' ) ) .
						'">' .
						__( 'Install', 'newspack' ) .
						'</a>';
				}
			}
		}

		return $actions;
	}

	/**
	 * Install a plugin when the installation link is clicked.
	 */
	public function handle_plugin_install() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to install plugins.', 'newspack' ) );
		}

		$plugin = filter_input( INPUT_GET, 'plugin', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! $plugin ) {
			wp_die( esc_html__( 'Invalid plugin.', 'newspack' ) );
		}
		check_admin_referer( 'newspack-install-plugin_' . $plugin, 'install_nonce' );

		$managed_plugins = Plugin_Manager::get_managed_plugins();
		if ( ! isset( $managed_plugins[ $plugin ] ) ) {
			wp_die( esc_html__( 'Invalid plugin.', 'newspack' ) );
		}

		$plugin_info = $managed_plugins[ $plugin ];
		if ( 'wporg' === $plugin_info['Download'] ) {
			$result = Plugin_Manager::install( $plugin );
		} else {
			$result = Plugin_Manager::install( $plugin_info['Download'] );
		}

		if ( is_wp_error( $result ) ) {
			$redirect = self_admin_url( 'plugins.php?newspack_install_error=true&message=' . urlencode( $result->get_error_message() ) . '&plugin=' . urlencode( $plugin ) );
			wp_safe_redirect( add_query_arg( '_error_nonce', wp_create_nonce( 'newspack-install-error_' . $plugin ), $redirect ) );
			exit;
		} else {
			wp_safe_redirect( self_admin_url( 'plugins.php?newspack_install_success=true' ) );
			exit;
		}
	}

	/**
	 * Output success/failure notices on plugin installation.
	 */
	public function plugin_install_notices() {
		if ( filter_input( INPUT_GET, 'newspack_install_success', FILTER_VALIDATE_BOOLEAN ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php echo esc_html__( 'Plugin installed.', 'newspack' ); ?></strong></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php echo esc_html__( 'Dismiss.', 'newspack' ); ?></span>
				</button>
			</div>
			<?php
		} elseif ( filter_input( INPUT_GET, 'newspack_install_error', FILTER_VALIDATE_BOOLEAN ) && filter_input( INPUT_GET, 'plugin', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) && filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
			$plugin = filter_input( INPUT_GET, 'plugin', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			check_admin_referer( 'newspack-install-error_' . $plugin, '_error_nonce' );
			$message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php echo esc_html__( 'Failed to install plugin:', 'newspack' ); ?></strong> <?php echo esc_html( $message ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php echo esc_html__( 'Dismiss.', 'newspack' ); ?></span>
				</button>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue scripts and styles for the Plugins screen.
	 *
	 * @param string $hook The current screen.
	 */
	public function enqueue_scripts_and_styles( $hook ) {
		if ( ! in_array( $hook, array( 'plugins.php', 'plugin-install.php' ) ) ) {
			return;
		}

		wp_register_script(
			'newspack_plugins_screen',
			Newspack::plugin_url() . '/dist/plugins-screen.js',
			[ 'jquery', 'mediaelement-core' ],
			NEWSPACK_PLUGIN_VERSION,
			true
		);

		$plugins   = array_keys( Plugin_Manager::get_managed_plugins() );
		$plugins[] = 'newspack';

		$installed_plugins   = array_values( array_intersect( array_keys( Plugin_Manager::get_managed_plugins() ), array_keys( Plugin_Manager::get_installed_plugins() ) ) );
		$installed_plugins[] = 'newspack';

		$newspack_plugin_info = [
			'plugins'                    => $plugins,
			'installed_plugins'          => $installed_plugins,
			'screen'                     => $hook,
			'plugin_review_link'         => defined( 'NEWSPACK_PLUGIN_REVIEW_FORM_URL' ) ? NEWSPACK_PLUGIN_REVIEW_FORM_URL : null,
			'approved_plugins_list_link' => defined( 'NEWSPACK_APPROVED_PLUGINS_LIST_LINK' ) ? NEWSPACK_APPROVED_PLUGINS_LIST_LINK : null,
		];

		wp_localize_script( 'newspack_plugins_screen', 'newspack_plugin_info', $newspack_plugin_info );
		wp_enqueue_script( 'newspack_plugins_screen' );

		wp_register_style(
			'newspack_plugins_screen',
			Newspack::plugin_url() . '/dist/plugins-screen.css',
			[],
			NEWSPACK_PLUGIN_VERSION
		);
		wp_style_add_data( 'newspack_plugins_screen', 'rtl', 'replace' );
		wp_enqueue_style( 'newspack_plugins_screen' );
	}

	/**
	 * Prepare a list of plugins for display in a notification.
	 *
	 * @param string $info Info about one plugin.
	 * @return string Markup displaying the plugin name, as a link if PluginURI is available.
	 */
	public static function prepare_plugin_list( $info ) {
		if ( ! empty( $info['PluginURI'] ) ) {
			return '<a href="' . esc_url( $info['PluginURI'] ) . '" target="_blank">' . esc_html( $info['Name'] ) . '</a>';
		}
		return esc_html( $info['Name'] );
	}
}
new Admin_Plugins_Screen();
