<?php
/**
 * Patches for known issues affecting Newspack sites.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class Patches {
	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		add_filter( 'wpseo_enhanced_slack_data', [ __CLASS__, 'use_cap_for_slack_preview' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_reusable_blocks_menu_link' ] );
		add_filter( 'wpseo_opengraph_url', [ __CLASS__, 'http_ogurls' ] );
		add_filter( 'map_meta_cap', [ __CLASS__, 'prevent_accidental_page_deletion' ], 10, 4 );
		add_action( 'pre_post_update', [ __CLASS__, 'prevent_unpublish_front_page' ], 10, 2 );
		add_action( 'pre_get_posts', [ __CLASS__, 'maybe_display_author_page' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'restrict_others_posts' ] );
		add_filter( 'ajax_query_attachments_args', [ __CLASS__, 'restrict_media_library_access_ajax' ] );
		add_filter( 'script_loader_tag', [ __CLASS__, 'add_async_defer_support' ], 10, 2 );
		add_filter( 'script_loader_tag', [ __CLASS__, 'add_amp_plus_attr_support' ], 10, 2 );

		// Disable WooCommerce image regeneration to prevent regenerating thousands of images.
		add_filter( 'woocommerce_background_image_regeneration', '__return_false' );

		// Disable Publicize automated sharing for WooCommerce products.
		add_action( 'init', [ __CLASS__, 'disable_publicize_for_products' ] );

		// Fix an issue when running The Events Calendar where all posts block items have same date.
		add_action( 'tribe_events_views_v2_after_make_view', [ __CLASS__, 'remove_tec_extra_excerpt_filtering' ], 1 );
	}

	/**
	 * Add async/defer support to `wp_script_add_data()`
	 *
	 * See https://github.com/WordPress/WordPress/blob/bab3bdf2df4ea57766793932719665a14c810698/wp-content/themes/twentytwenty/classes/class-twentytwenty-script-loader.php.
	 *
	 * @link https://core.trac.wordpress.org/ticket/12009
	 *
	 * @param string $tag The script tag.
	 * @param string $handle The script handle.
	 *
	 * @return @string Script HTML string.
	 */
	public static function add_async_defer_support( $tag, $handle ) {
		foreach ( array( 'async', 'defer' ) as $attr ) {
			if ( ! wp_scripts()->get_data( $handle, $attr ) ) {
				continue;
			}
			// Prevent adding attribute when already added in #12009.
			if ( ! preg_match( ":\s$attr(=|>|\s):", $tag ) ) {
				$tag = preg_replace( ':(?=></script>):', " $attr", $tag, 1 );
			}
			// Only allow async or defer, not both.
			break;
		}
		return $tag;
	}

	/**
	 * Similar to async/defer support from `add_async_defer_support()`, adds
	 * 'amp-plus' support to `wp_script_add_data()`.
	 *
	 * @param string $tag The script tag.
	 * @param string $handle The script handle.
	 *
	 * @return @string Script HTML string.
	 */
	public static function add_amp_plus_attr_support( $tag, $handle ) {
		$data_name = 'amp-plus';
		$attr      = 'data-amp-plus-allowed';
		if ( ! wp_scripts()->get_data( $handle, $data_name ) ) {
			return $tag;
		}
		if ( ! preg_match( ":\s$attr(=|>|\s):", $tag ) ) {
			$tag = preg_replace( ':(?=></script>):', " $attr", $tag, 1 );
		}
		return $tag;
	}

	/**
	 * Use the Co-Author in Slack preview metadata instead of the regular post author if needed.
	 *
	 * @param array $slack_data Array of data which will be output in twitter:data tags.
	 * @return array Modified $slack_data
	 */
	public static function use_cap_for_slack_preview( $slack_data ) {
		if ( function_exists( 'coauthors' ) && is_single() && isset( $slack_data[ __( 'Written by', 'wordpress-seo' ) ] ) ) {
			$slack_data[ __( 'Written by', 'wordpress-seo' ) ] = coauthors( null, null, null, null, false );
		}

		return $slack_data;
	}

	/**
	 * Add a menu link in WP Admin to easily edit and manage reusable blocks.
	 */
	public static function add_reusable_blocks_menu_link() {
		add_submenu_page( 'edit.php', 'manage_reusable_blocks', __( 'Reusable Blocks' ), 'edit_posts', 'edit.php?post_type=wp_block', '', 2 );
	}

	/**
	 * On Atomic infrastructure, URLs are `http` for Facebook requests.
	 * This forces the `og:url` to `http` for consistency, to prevent 301 redirect loop issues.
	 *
	 * @param string $og_url The opengraph URL.
	 * @return string modified $og_url
	 */
	public static function http_ogurls( $og_url ) {
		if ( defined( 'ATOMIC_SITE_ID' ) && ATOMIC_SITE_ID ) {
			$og_url = str_replace( 'https:', 'http:', $og_url );
		}
		return $og_url;
	}

	/**
	 * Dynamically look up post IDs for protected pages.
	 *
	 * @return array Array of post IDs for "special" pages.
	 */
	public static function get_protected_page_ids() {
		$protected_page_ids = [];

		// Homepage.
		$front_page = intval( get_option( 'page_on_front', -1 ) );
		if ( 0 < $front_page ) {
			$protected_page_ids[] = $front_page;
		}

		// Blog posts page.
		$posts_page = intval( get_option( 'page_for_posts', -1 ) );
		if ( 0 < $posts_page ) {
			$protected_page_ids[] = $posts_page;
		}

		// Privacy policy page.
		$privacy_policy = intval( get_option( 'wp_page_for_privacy_policy', -1 ) );
		if ( 0 < $privacy_policy ) {
			$protected_page_ids[] = $privacy_policy;
		}

		// Donate page.
		$donate_page = intval( get_option( Donations::DONATION_PAGE_ID_OPTION, -1 ) );
		if ( 0 < $donate_page ) {
			$protected_page_ids[] = $donate_page;
		}

		// WooCommerce pages.
		if ( function_exists( 'wc_get_page_id' ) && function_exists( 'wc_privacy_policy_page_id' ) ) {
			// WooCommerce myaccount page.
			$account_page = wc_get_page_id( 'myaccount' );
			if ( 0 < $account_page ) {
				$protected_page_ids[] = $account_page;
			}

			// WooCommerce shop page.
			$shop = wc_get_page_id( 'shop' );
			if ( 0 < $shop ) {
				$protected_page_ids[] = $shop;
			}

			// WooCommerce cart page.
			$cart = wc_get_page_id( 'cart' );
			if ( 0 < $cart ) {
				$protected_page_ids[] = $cart;
			}

			// WooCommerce checkout page.
			$checkout = wc_get_page_id( 'checkout' );
			if ( 0 < $checkout ) {
				$protected_page_ids[] = $checkout;
			}

			// WooCommerce view_order page.
			$view_order = wc_get_page_id( 'view_order' );
			if ( 0 < $view_order ) {
				$protected_page_ids[] = $view_order;
			}

			// WooCommerce terms page.
			$terms = wc_get_page_id( 'terms' );
			if ( 0 < $terms ) {
				$protected_page_ids[] = $terms;
			}

			// WooCommerce privacy policy page.
			$wc_privacy_policy = wc_privacy_policy_page_id();
			if ( 0 < $wc_privacy_policy ) {
				$protected_page_ids[] = $wc_privacy_policy;
			}
		}

		return $protected_page_ids;
	}

	/**
	 * Prevent deletion of essential pages such as the homepage, blog posts page, and WooCommerce pages.
	 *
	 * @param array  $caps Primitive capabilities required of the user.
	 * @param string $cap Capability being checked.
	 * @param int    $user_id The user ID.
	 * @param array  $args Adds context to the capability check, typically starting with an object ID.
	 *
	 * @return array Filtered array of capabilities.
	 */
	public static function prevent_accidental_page_deletion( $caps, $cap, $user_id, $args ) {
		// If no $args to check, bail early.
		if ( empty( $args ) ) {
			return $caps;
		}

		$post_id = $args[0]; // First item is usually the post ID.

		// If $post_id isn't a valid post, bail early.
		if ( false === get_post_type( $post_id ) ) {
			return $caps;
		}

		// If the current page ID is protected, and the capability being checked is for deletion, do not allow.
		if ( 'delete_post' === $cap && in_array( $post_id, self::get_protected_page_ids(), true ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	/**
	 * Prevent unpublishing of the homepage.
	 *
	 * @param int   $post_id ID of post being updated.
	 * @param array $data Array of unslashed post data.
	 */
	public static function prevent_unpublish_front_page( $post_id, $data ) {
		$homepage_id = intval( get_option( 'page_on_front', 0 ) );

		// No need to run if not the homepage, or the homepage isn't a static page.
		if ( ! $homepage_id || $homepage_id !== $post_id ) {
			return;
		}

		$post         = get_post( $post_id );
		$old_status   = $post->post_status;
		$new_status   = $data['post_status'];
		$is_published = 'publish' === $old_status;

		// Prevent attempts to unpublish a published homepage.
		if ( $is_published && 'publish' !== $new_status ) {
			wp_die( __( 'Please choose a new homepage before attempting to unpublish this page.', 'newspack-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Force author pages for non-valid author roles to 404.
	 * Prevents author pages for users like subscribers and donors from being publicly accessible.
	 *
	 * @param WP_Query $query The WP query object.
	 */
	public static function maybe_display_author_page( $query ) {
		if ( $query->is_admin() || ! $query->is_main_query() || ! $query->is_author() ) {
			return;
		}

		$author_name = $query->query_vars['author_name'];
		$user        = get_user_by( 'slug', $author_name );

		// For CAP guest authors, $user will be false.
		if ( ! $user || ! isset( $user->roles ) ) {
			return;
		}

		if ( is_array( $user->roles ) ) {
			/**
			 * Filter to add/remove the default user roles that are allowed to have public author archives.
			 *
			 * @param array $allowed_user_roles Array of WP user roles that can have author archives,
			 */
			$allowed_user_roles = apply_filters(
				'newspack_user_roles_with_author_archives',
				[
					'administrator',
					'editor',
					'author',
					'contributor',
				]
			);

			// Sometimes, authors who leave a publication are set to be subscribers. We still want those authors to have archives.
			$has_no_posts = function_exists( '\wpcom_vip_count_user_posts' ) ?
				0 === (int) \wpcom_vip_count_user_posts( $user->ID, 'post', true ) :
				0 === (int) count_user_posts( $user->ID, 'post', true ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.count_user_posts_count_user_posts

			if ( $has_no_posts && 0 === count( array_intersect( $user->roles, $allowed_user_roles ) ) ) {
				$query->set_404();
			}
		}
	}

	/**
	 * Restrict non-privileged users from seeing posts not owned by them.
	 * Affects all admin post lists and the legacy (non-AJAX) media library list page.
	 *
	 * @param WP_Query $query Query to alter.
	 */
	public static function restrict_others_posts( $query ) {
		global $current_screen;
		$current_user_id = get_current_user_id();

		// If not in a dashboard page or there's no user to check permissions for.
		if ( ! $current_screen || ! $current_user_id || ! $query->is_main_query() ) {
			return;
		}

		$is_media_library = 'upload' === $current_screen->id && 'attachment' === $query->get( 'post_type' );
		$is_posts_list    = 'edit' === $current_screen->base;

		// If the user can't edit others' posts, only allow them to view their own posts.
		if ( ( $is_media_library || $is_posts_list ) && ! current_user_can( 'edit_others_posts' ) ) {
			$query->set( 'author', $current_user_id ); // phpcs:ignore WordPressVIPMinimum.Hooks.PreGetPosts.PreGetPosts
			add_filter( 'wp_count_posts', [ __CLASS__, 'fix_post_counts' ], 10, 2 );
		}
	}

	/**
	 * Updates the post counts shown alongside each status in admin post lists.
	 *
	 * @param object $counts Post counts keyed by status.
	 * @param string $type Post type.
	 *
	 * @return object Filtered $counts object.
	 */
	public static function fix_post_counts( $counts, $type ) {
		$current_user_id = get_current_user_id();
		foreach ( (array) $counts as $status => $count ) {
			if ( 0 < $count ) {
				$args = [
					'author'      => $current_user_id,
					'post_status' => $status,
					'post_type'   => $type,
				];

				$results         = new \WP_Query( $args );
				$counts->$status = $results->found_posts;
			}
		}
		return $counts;
	}

	/**
	 * Restrict non-privileged users from seeing media library items not uploaded by them.
	 * Affects media library AJAX requests.
	 *
	 * @param array $query_args Query args for the AJAX request.
	 *
	 * @return array Filtered query args.
	 */
	public static function restrict_media_library_access_ajax( $query_args ) {
		$current_user_id = get_current_user_id();

		if ( $current_user_id && ! current_user_can( 'edit_others_posts' ) ) {
			$query_args['author'] = $current_user_id;
		}

		return $query_args;
	}

	/**
	 * Disable automated social media sharing of WooCommerce products via Publicize.
	 */
	public static function disable_publicize_for_products() {
		remove_post_type_support( 'product', 'publicize' );
	}

	/**
	 * The 'action_include_filters_excerpt' hooked on this action to modify the 'Read More' text by The Events Calendar
	 * causes issues because of the weird `avoiding_filter_loop` usage in the call stack. It introduces a race condition that
	 * messes up the query that the posts block uses by resetting the query early, and WP will think the current posts block item is
	 * the parent Page that the posts block is embedded on.
	 *
	 * @see https://github.com/the-events-calendar/the-events-calendar/blob/0b8caed6049ee6c16bb3d1e06ea9026d995f636e/src/Tribe/Views/V2/Hooks.php#L92
	 * @see https://github.com/the-events-calendar/the-events-calendar/blob/77910c9de7f5640064d4eef4eb4841a523f27719/src/Tribe/Views/V2/Template/Excerpt.php#L71-L73
	 */
	public static function remove_tec_extra_excerpt_filtering() {
		remove_all_actions( 'tribe_events_views_v2_after_make_view' );
	}
}
Patches::init();
