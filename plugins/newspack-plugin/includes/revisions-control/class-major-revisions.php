<?php
/**
 * Adds a filter by author to the Posts page in the Admin dashboard.
 *
 * @package Newspack
 */

namespace Newspack;

use WP_Post;

require_once 'class-major-revision.php';

/**
 * Author Filter class
 */
class Major_Revisions {

	/**
	 * Flag to make sure hooks are initialized only once
	 *
	 * @var boolean
	 */
	private static $initiated = false;

	/**
	 * The slug of the post type used to store backups of major revisions
	 *
	 * @var string
	 */
	const BKP_POST_TYPE = 'nwspk-rev_bkp';

	/**
	 * Initializes the hook
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! Revisions_Control::is_active() ) {
			return;
		}
		if ( ! self::$initiated ) {
			add_action( 'load-revision.php', array( __CLASS__, 'admin_init' ) );
			add_action( 'wp_ajax_newspack_toggle_revision_major', array( __CLASS__, 'ajax_toggle_major' ) );
			add_filter( 'wp_prepare_revision_for_js', array( __CLASS__, 'filter_revision_for_js' ), 10, 3 );
			add_action( 'init', array( __CLASS__, 'register_post_type' ) );
			self::$initiated = true;
		}
	}

	/**
	 * Initializes the revisions page
	 *
	 * @return void
	 */
	public static function admin_init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_print_scripts', array( __CLASS__, 'print_scripts' ) );

		$current_revision = self::get_current_revision_id();
		if ( $current_revision ) {

			if ( ! empty( $_GET['action'] ) && 'restore_backup' === $_GET['action'] && check_admin_referer( 'newspack_restore_revisions' ) ) {
				self::restore_backup( $current_revision );
			} elseif ( ! self::check_revision_backup_integrity( $current_revision ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
			}
		}

	}

	/**
	 * Gets the current revision ID from the URL
	 *
	 * @return string
	 */
	public static function get_current_revision_id() {
		return ! empty( $_GET['revision'] ) ? intval( $_GET['revision'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Adds an admin notice with a link to restore the revisions backups
	 *
	 * @return void
	 */
	public static function admin_notices() {
		global $pagenow, $typenow;
		$current_revision = self::get_current_revision_id();
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo wp_kses(
			sprintf(
				// translators: 1 and 2 are the opening and closing <a> tag with the link to restore revisions.
				esc_html__(
					'Revisions marked as major were deleted from the database. %1$sRestore major revisions.%2$s',
					'newspack'
				),
				sprintf(
					'<a href="%s">',
					wp_nonce_url(
						admin_url( 'revision.php?revision=' . $current_revision . '&action=restore_backup' ),
						'newspack_restore_revisions'
					)
				),
				'</a>'
			),
			[
				'a' => [ 'href' => [] ],
			]
		);
		echo '</p></div>';
	}

	/**
	 * Checks if all major revisions of the current post still exist
	 *
	 * @param string|int $revision_id The revision ID.
	 * @return bool
	 */
	public static function check_revision_backup_integrity( $revision_id ) {
		$revision_post = get_post( $revision_id );
		if ( ! $revision_post ) {
			// If we can't perform the check, return as success.
			return true;
		}
		$revision        = new Major_Revision( $revision_post->post_parent, $revision_id );
		$major_revisions = $revision->get_post_revisions();
		foreach ( $major_revisions as $major_r_id ) {
			if ( ! get_post( $major_r_id ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if all major revisions of the current post still exist
	 *
	 * @param string|int $revision_id The revision ID.
	 * @param bool       $redirect Redirect the user back to the revisions page.
	 * @return void
	 */
	public static function restore_backup( $revision_id, $redirect = true ) {
		$revision_post = get_post( $revision_id );
		if ( $revision_post ) {
			$revision        = new Major_Revision( $revision_post->post_parent, $revision_id );
			$major_revisions = $revision->get_post_revisions();
			foreach ( $major_revisions as $major_r_id ) {
				if ( ! get_post( $major_r_id ) ) {
					$revision_check = new Major_Revision( $revision_post->post_parent, $major_r_id );
					$revision_check->restore_backup();
				}
			}
		}
		if ( $redirect ) {
			wp_safe_redirect( admin_url( 'revision.php?revision=' . $revision_id ) );
			exit;
		}
	}

	/**
	 * Enqueues js script
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script(
			'newspack_revisions_control',
			Newspack::plugin_url() . '/includes/revisions-control/newspack-revisions.js',
			[ 'jquery' ],
			NEWSPACK_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'newspack_revisions_control',
			'newspack_revisions_control',
			[
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'mark_major_nonce' => wp_create_nonce( 'newspack_mark_major' ),
				'labels'           => [
					'loading'        => __( 'Saving...', 'newspack' ),
					'saved'          => __( 'Changes applied', 'newspack' ),
					'unmark'         => __( 'Unmark as a major revision', 'newspack' ),
					'mark'           => __( 'Mark as a major revision', 'newspack' ),
					'major_revision' => __( 'Major revision', 'newspack' ),
				],
			]
		);
		wp_enqueue_style(
			'newspack_revisions_control',
			Newspack::plugin_url() . '/includes/revisions-control/newspack-revisions.css',
			NEWSPACK_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Print inline CSS for tha admin screen
	 *
	 * @return void
	 */
	public static function print_scripts() {
		?>
		<style>
			.newspack-revisions-color{ color: <?php echo esc_attr( self::get_admin_color() ); ?>; }
			.newspack-revisions-after-bgcolor:after{ background-color: <?php echo esc_attr( self::get_admin_color() ); ?>; }
		</style>
		<?php
	}

	/**
	 * Get the primary color for the current admin theme
	 *
	 * @return string
	 */
	public static function get_admin_color() {
		global $_wp_admin_css_colors;
		$theme           = get_user_option( 'admin_color' );
		$color           = '#2271b1';
		$highlight_index = [
			'fresh'     => 2,
			'light'     => 2,
			'modern'    => 1,
			'blue'      => 0,
			'coffee'    => 2,
			'ectoplasm' => 2,
			'midnight'  => 3,
			'ocean'     => 2,
			'sunrise'   => 2,
		];
		if ( ! empty( $_wp_admin_css_colors[ $theme ] ) ) {
			$color = $_wp_admin_css_colors[ $theme ]->colors[ $highlight_index[ $theme ] ];
		}
		return $color;
	}

	/**
	 * Filters the revision object used in the Backbone JS app
	 *
	 * @param array   $revisions_data The revision data we are filtering.
	 * @param WP_Post $revision       The revision's WP_Post object.
	 * @param WP_Post $post           The revision's parent WP_Post object.
	 * @return array
	 */
	public static function filter_revision_for_js( $revisions_data, $revision, $post ) {
		$revision                         = new Major_Revision( $post->ID, $revision->ID );
		$revisions_data['newspack_major'] = $revision->is_major();
		return $revisions_data;
	}

	/**
	 * Ajax callback to toggle the revision
	 *
	 * @return void
	 */
	public static function ajax_toggle_major() {
		check_ajax_referer( 'newspack_mark_major' );

		$revision_id = ! empty( $_POST['revision_id'] ) ? intval( $_POST['revision_id'] ) : null;
		$post_id     = ! empty( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;

		if ( ! $revision_id || ! $post_id ) {
			exit;
		}

		$revision      = new Major_Revision( $post_id, $revision_id );
		$current_state = $revision->toggle();

		echo wp_json_encode( [ 'major' => $current_state ] );
		die;
	}

	/**
	 * Registers the post type used to store backups of major revisions
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$args = array(
			'label'               => 'Newspack revisions backups',
			'description'         => 'Post type used to store backups of major revisions',
			'supports'            => false,
			'taxonomies'          => array(),
			'hierarchical'        => true,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => false,
		);
		register_post_type( self::BKP_POST_TYPE, $args );
	}

}

Major_Revisions::init();
