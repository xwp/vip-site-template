<?php
/**
 * RSS.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * RSS feed enhancements.
 */
class RSS {
	const FEED_CPT           = 'partner_rss_feed';
	const FEED_QUERY_ARG     = 'partner-feed';
	const FEED_SETTINGS_META = 'partner_feed_settings';

	/**
	 * Hooks and filters.
	 */
	public static function maybe_init() {
		add_action( 'init', [ __CLASS__, 'init' ] );
	}

	/**
	 * Initialise.
	 */
	public static function init() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// If the standalone plugin is active, deactivate it and activate as a module.
		if ( is_plugin_active( 'newspack-rss-enhancements/newspack-rss-enhancements.php' ) ) {
			deactivate_plugins( 'newspack-rss-enhancements/newspack-rss-enhancements.php' );
			Settings::activate_optional_module( 'rss' );
		}

		if ( ! Settings::is_optional_module_active( 'rss' ) ) {
			return;
		}

		// Backend.
		self::register_feed_cpt();
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_autocomplete_scripts' ] );
		add_action( 'save_post_' . self::FEED_CPT, [ __CLASS__, 'save_settings' ] );
		add_filter( 'manage_' . self::FEED_CPT . '_posts_columns', [ __CLASS__, 'columns_head' ] );
		add_action( 'manage_' . self::FEED_CPT . '_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );

		// Frontend.
		add_filter( 'option_rss_use_excerpt', [ __CLASS__, 'filter_use_rss_excerpt' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'modify_feed_query' ] );
		add_action( 'rss2_item', [ __CLASS__, 'add_extra_tags' ] );
		add_filter( 'the_excerpt_rss', [ __CLASS__, 'maybe_remove_content_featured_image' ], 1 );
		add_filter( 'the_content_feed', [ __CLASS__, 'maybe_remove_content_featured_image' ], 1 );
		add_filter( 'wpseo_include_rss_footer', [ __CLASS__, 'maybe_suppress_yoast' ] );
		add_action( 'rss2_ns', [ __CLASS__, 'maybe_inject_yahoo_namespace' ] );
	}

	/**
	 * Get URL for a feed.
	 *
	 * @param WP_Post $feed_post RSS feed post object.
	 */
	public static function get_feed_url( $feed_post ) {
		$base_feed_url = get_bloginfo( 'rss2_url' );
		$feed_slug     = is_numeric( $feed_post ) ? get_post_field( 'post_name', $feed_post ) : $feed_post->post_name;
		return add_query_arg( self::FEED_QUERY_ARG, $feed_slug, $base_feed_url );
	}

	/**
	 * Get feed settings array.
	 *
	 * @param WP_Post|int $feed_post A feed WP_Post object, post ID. (optional on frontend).
	 * @return array|false Feed settings or false if no feed found.
	 */
	public static function get_feed_settings( $feed_post = null ) {
		$default_settings = [
			'category_include'       => [],
			'category_exclude'       => [],
			'use_image_tags'         => false,
			'use_media_tags'         => false,
			'use_updated_tags'       => false,
			'use_tags_tags'          => false,
			'full_content'           => true,
			'num_items_in_feed'      => 10,
			'offset'                 => 0,
			'timeframe'              => false,
			'content_featured_image' => false,
			'suppress_yoast'         => false,
			'yahoo_namespace'        => false,
		];

		if ( ! $feed_post ) {
			$query_feed = filter_input( INPUT_GET, self::FEED_QUERY_ARG, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! $query_feed ) {
				return false;
			}

			$feed_post = get_page_by_path( sanitize_text_field( $query_feed ), OBJECT, self::FEED_CPT ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path
			if ( ! $feed_post ) {
				return false;
			}
		}

		$feed_post_id   = is_numeric( $feed_post ) ? $feed_post : $feed_post->ID;
		$saved_settings = get_post_meta( $feed_post_id, self::FEED_SETTINGS_META, true );
		if ( ! is_array( $saved_settings ) ) {
			return $default_settings;
		}

		return shortcode_atts( $default_settings, $saved_settings );
	}

	/**
	 * Register the partner feed CPT.
	 */
	public static function register_feed_cpt() {
		$labels = array(
			'name'               => _x( 'RSS Feeds', 'post type general name', 'newspack-plugin' ),
			'singular_name'      => _x( 'RSS Feed', 'post type singular name', 'newspack-plugin' ),
			'menu_name'          => _x( 'RSS Feeds', 'admin menu', 'newspack-plugin' ),
			'name_admin_bar'     => _x( 'RSS Feed', 'add new on admin bar', 'newspack-plugin' ),
			'add_new'            => _x( 'Add New', 'rss feed', 'newspack-plugin' ),
			'add_new_item'       => __( 'Add New RSS Feed', 'newspack-plugin' ),
			'new_item'           => __( 'New RSS Feed', 'newspack-plugin' ),
			'edit_item'          => __( 'Edit RSS Feed', 'newspack-plugin' ),
			'view_item'          => __( 'View RSS Feed', 'newspack-plugin' ),
			'all_items'          => __( 'All RSS Feeds', 'newspack-plugin' ),
			'search_items'       => __( 'Search RSS Feeds', 'newspack-plugin' ),
			'parent_item_colon'  => __( 'Parent RSS Feeds:', 'newspack-plugin' ),
			'not_found'          => __( 'No RSS feeds found.', 'newspack-plugin' ),
			'not_found_in_trash' => __( 'No RSS seeds found in Trash.', 'newspack-plugin' ),
			'item_published'     => __( 'RSS Feed published', 'newspack-plugin' ),
			'item_updated'       => __( 'RSS Feed updated', 'newspack-plugin' ),
		);

		$args = array(
			'labels'               => $labels,
			'description'          => __( 'RSS feeds customized for third-party services.', 'newspack-plugin' ),
			'public'               => true,
			'exclude_from_search'  => true,
			'publicly_queryable'   => false,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'menu_icon'            => 'dashicons-rss',
			'query_var'            => true,
			'capability_type'      => 'post',
			'has_archive'          => false,
			'hierarchical'         => false,
			'menu_position'        => null,
			'supports'             => array( 'title' ),
			'rewrite'              => false,
			'show_in_admin_bar'    => false,
			'register_meta_box_cb' => [ __CLASS__, 'add_metaboxes' ],
		);

		register_post_type( self::FEED_CPT, $args );
	}

	/**
	 * Add a feed URL column to the Edit RSS Feeds screen.
	 *
	 * @param array $columns Screen columns.
	 * @return array Modified $columns.
	 */
	public static function columns_head( $columns ) {
		$columns['feed_url'] = __( 'Feed URL', 'newspack-plugin' );
		return $columns;
	}

	/**
	 * Populate feed URL column on Edit RSS Feeds screen.
	 *
	 * @param string $column_name The column identifier.
	 * @param int    $post_id The current element's post ID.
	 */
	public static function column_content( $column_name, $post_id ) {
		if ( 'feed_url' === $column_name ) {
			$feed_url = self::get_feed_url( $post_id );
			?>
			<a href='<?php echo esc_url( $feed_url ); ?>' target='_blank'>
				<?php echo esc_url( $feed_url ); ?>
			</a>
			<?php
		}
	}

	/**
	 * Add metaboxes to CPT screen.
	 *
	 * @param WP_Post $feed_post RSS feed post object.
	 */
	public static function add_metaboxes( $feed_post ) {
		add_meta_box(
			'partner_rss_feed_url',
			__( 'Feed URL', 'newspack-plugin' ),
			[ __CLASS__, 'render_url_metabox' ],
			self::FEED_CPT
		);
		add_meta_box(
			'partner_rss_feed_content_settings',
			__( 'Content Settings', 'newspack-plugin' ),
			[ __CLASS__, 'render_content_settings_metabox' ],
			self::FEED_CPT
		);
		add_meta_box(
			'partner_rss_feed_technical_settings',
			__( 'Technical Settings', 'newspack-plugin' ),
			[ __CLASS__, 'render_technical_settings_metabox' ],
			self::FEED_CPT
		);
	}

	/**
	 * Enqueue autocomplete scripts.
	 *
	 * @param string $hook The current screen.
	 */
	public static function enqueue_autocomplete_scripts( $hook ) {
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
			$screen = get_current_screen();

			if ( is_object( $screen ) && self::FEED_CPT == $screen->post_type ) {
				wp_enqueue_style(
					'newspack-select2',
					'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.12/css/select2.min.css',
					[],
					'4.0.12'
				);

				wp_enqueue_script(
					'newspack-select2',
					'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.12/js/select2.full.min.js',
					[ 'jquery' ],
					'4.0.12',
					false
				);
			}
		}
	}

	/**
	 * Render URL metabox for CPT.
	 *
	 * @param WP_Post $feed_post RSS feed post object.
	 */
	public static function render_url_metabox( $feed_post ) {
		if ( 'publish' !== $feed_post->post_status ) {
			?>
			<h3>
				<?php esc_html_e( 'A URL will be generated for this feed once published', 'newspack-plugin' ); ?>
			</h3>
			<?php
			return;
		}

		$feed_url = self::get_feed_url( $feed_post );

		?>
		<h3>
			<a href='<?php echo esc_url( $feed_url ); ?>' target='_blank'>
				<?php echo esc_url( $feed_url ); ?>
			</a>
		</h3>
		<?php
	}

	/**
	 * Render content settings metabox for CPT.
	 *
	 * @param WP_Post $feed_post RSS feed post object.
	 */
	public static function render_content_settings_metabox( $feed_post ) {
		$settings   = self::get_feed_settings( $feed_post );
		$categories = get_categories();
		wp_nonce_field( 'newspack_rss_enhancements_nonce', 'newspack_rss_enhancements_nonce' );
		?>
		<table>
			<tr>
				<th><?php esc_html_e( 'Number of posts to display in feed:', 'newspack-plugin' ); ?></th>
				<td>
					<input name="num_items_in_feed" type="number" min="1" value="<?php echo esc_attr( $settings['num_items_in_feed'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Offset posts by:', 'newspack-plugin' ); ?></th>
				<td>
					<input name="offset" type="number" min="0" value="<?php echo esc_attr( $settings['offset'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Limit timeframe to last # of hours:', 'newspack-plugin' ); ?></th>
				<td>
					<input name="timeframe" type="number" value="<?php echo esc_attr( $settings['timeframe'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Use post full content or excerpt:', 'newspack-plugin' ); ?></th>
				<td>
					<select name="full_content">
						<option value="1" <?php selected( $settings['full_content'] ); ?> ><?php esc_html_e( 'Full content', 'newspack-plugin' ); ?></option>
						<option value="0" <?php selected( ! $settings['full_content'] ); ?> ><?php esc_html_e( 'Excerpt', 'newspack-plugin' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Include only posts from these categories:', 'newspack-plugin' ); ?></th>
				<td>
					<select id="category_include" name="category_include[]" multiple="multiple" style="width:300px">
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( in_array( $category->term_id, $settings['category_include'] ) ); ?>><?php echo esc_html( $category->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Exclude posts from these categories:', 'newspack-plugin' ); ?></th>
				<td>
					<select id="category_exclude" name="category_exclude[]" multiple="multiple" style="width:300px">
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( in_array( $category->term_id, $settings['category_exclude'] ) ); ?>><?php echo esc_html( $category->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<script>
			jQuery( document ).ready( function() {
				jQuery( '#category_include' ).select2();
				jQuery( '#category_exclude' ).select2();
			} );
		</script>
		<?php
	}

	/**
	 * Render technical settings metabox for CPT.
	 *
	 * @param WP_Post $feed_post RSS feed post object.
	 */
	public static function render_technical_settings_metabox( $feed_post ) {
		$settings = self::get_feed_settings( $feed_post );
		?>
		<p><strong>Note:</strong> These settings are for modifying a feed to make it compatible with various integrations (SmartNews, PugPig, etc.). They should only be used if a specific integration requires a non-standard RSS feed. Consult the integration's documentation or support for information about which elements are required.</p>

		<table>
			<tr>
				<th><?php esc_html_e( 'Add post featured images in <image> tags', 'newspack-plugin' ); ?></th>
				<td>
					<input type="hidden" name="use_image_tags" value="0" />
					<input type="checkbox" name="use_image_tags" value="1" <?php checked( $settings['use_image_tags'] ); ?>/>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Add post featured images in <media:> tags', 'newspack-plugin' ); ?></th>
				<td>
					<input type="hidden" name="use_media_tags" value="0" />
					<input type="checkbox" name="use_media_tags" value="1" <?php checked( $settings['use_media_tags'] ); ?> />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Add post updated time in <updated> tags', 'newspack-plugin' ); ?></th>
				<td>
					<input type="hidden" name="use_updated_tags" value="0" />
					<input type="checkbox" name="use_updated_tags" value="1" <?php checked( $settings['use_updated_tags'] ); ?> />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Add categories and tags in <tags> element', 'newspack-plugin' ); ?></th>
				<td>
					<input type="hidden" name="use_tags_tags" value="0" />
					<input type="checkbox" name="use_tags_tags" value="1" <?php checked( $settings['use_tags_tags'] ); ?> />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Add featured image at the top of feed content', 'newspack-plugin' ); ?></th>
				<td>
					<input type="hidden" name="content_featured_image" value="0" />
					<input type="checkbox" name="content_featured_image" value="1" <?php checked( $settings['content_featured_image'] ); ?> />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Add Yahoo namespace to RSS namespace: xmlns:media="http://search.yahoo.com/mrss/"', 'newspack-plugin' ); ?></th>
				<td>
					<input type="hidden" name="yahoo_namespace" value="0" />
					<input type="checkbox" name="yahoo_namespace" value="1" <?php checked( $settings['yahoo_namespace'] ); ?> />
				</td>
			</tr>
			<?php if ( defined( 'WPSEO_VERSION' ) && WPSEO_VERSION ) : ?>
				<tr>
					<th>
						<?php
						echo sprintf(
						/* translators: %s: URL to Yoast settings */
							__( 'Suppress <a href="%s">Yoast RSS content at the top and bottom of feed posts</a>' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							admin_url( 'admin.php?page=wpseo_titles#top#rss' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</th>
					<td>
						<input type="hidden" name="suppress_yoast" value="0" />
						<input type="checkbox" name="suppress_yoast" value="1" <?php checked( $settings['suppress_yoast'] ); ?> />
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Save CPT settings.
	 *
	 * @param int $feed_post_id The post ID of feed.
	 */
	public static function save_settings( $feed_post_id ) {
		$nonce = filter_input( INPUT_POST, 'newspack_rss_enhancements_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'newspack_rss_enhancements_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$settings = self::get_feed_settings( $feed_post_id );

		$use_image_tags             = filter_input( INPUT_POST, 'use_image_tags', FILTER_SANITIZE_NUMBER_INT );
		$settings['use_image_tags'] = (bool) $use_image_tags;

		$use_media_tags             = filter_input( INPUT_POST, 'use_media_tags', FILTER_SANITIZE_NUMBER_INT );
		$settings['use_media_tags'] = (bool) $use_media_tags;

		$use_updated_tags             = filter_input( INPUT_POST, 'use_updated_tags', FILTER_SANITIZE_NUMBER_INT );
		$settings['use_updated_tags'] = (bool) $use_updated_tags;

		$use_updated_tags          = filter_input( INPUT_POST, 'use_tags_tags', FILTER_SANITIZE_NUMBER_INT );
		$settings['use_tags_tags'] = (bool) $use_updated_tags;

		$full_content             = filter_input( INPUT_POST, 'full_content', FILTER_SANITIZE_NUMBER_INT );
		$settings['full_content'] = (bool) $full_content;

		$content_featured_image             = filter_input( INPUT_POST, 'content_featured_image', FILTER_SANITIZE_NUMBER_INT );
		$settings['content_featured_image'] = (bool) $content_featured_image;

		$num_items_in_feed             = filter_input( INPUT_POST, 'num_items_in_feed', FILTER_SANITIZE_NUMBER_INT );
		$settings['num_items_in_feed'] = absint( $num_items_in_feed );

		$offset             = filter_input( INPUT_POST, 'offset', FILTER_SANITIZE_NUMBER_INT );
		$settings['offset'] = absint( $offset );

		$timeframe             = filter_input( INPUT_POST, 'timeframe', FILTER_SANITIZE_NUMBER_INT );
		$settings['timeframe'] = absint( $timeframe );

		$suppress_yoast             = filter_input( INPUT_POST, 'suppress_yoast', FILTER_SANITIZE_NUMBER_INT );
		$settings['suppress_yoast'] = (bool) $suppress_yoast;

		$yahoo_namespace             = filter_input( INPUT_POST, 'yahoo_namespace', FILTER_SANITIZE_NUMBER_INT );
		$settings['yahoo_namespace'] = (bool) $yahoo_namespace;

		$category_settings = filter_input_array(
			INPUT_POST,
			[
				'category_include' => [
					'filter' => FILTER_SANITIZE_NUMBER_INT,
					'flags'  => FILTER_REQUIRE_ARRAY,
				],
				'category_exclude' => [
					'filter' => FILTER_SANITIZE_NUMBER_INT,
					'flags'  => FILTER_REQUIRE_ARRAY,
				],
			]
		);
		if ( $category_settings ) {
			if ( isset( $category_settings['category_include'] ) && is_array( $category_settings['category_include'] ) ) {
				$settings['category_include'] = array_map( 'intval', $category_settings['category_include'] );
			} else {
				$settings['category_include'] = [];
			}

			if ( isset( $category_settings['category_exclude'] ) && is_array( $category_settings['category_exclude'] ) ) {
				$settings['category_exclude'] = array_map( 'intval', $category_settings['category_exclude'] );
			} else {
				$settings['category_exclude'] = [];
			}
		}

		update_post_meta( $feed_post_id, self::FEED_SETTINGS_META, $settings );
		// @todo flush feed cache here.
	}

	/**
	 * Apply settings on frontend to WP query.
	 *
	 * @param WP_Query $query WP_Query object.
	 */
	public static function modify_feed_query( $query ) {
		if ( ! $query->is_feed() || ! $query->is_main_query() ) {
			return;
		}

		$settings = self::get_feed_settings();
		if ( ! $settings ) {
			return;
		}

		$query->set( 'posts_per_rss', absint( $settings['num_items_in_feed'] ) );

		$query->set( 'offset', absint( $settings['offset'] ) );

		if ( ! empty( $settings['timeframe'] ) ) {
			$query->set( 'date_query', [ 'after' => gmdate( 'Y-m-d H:i:s', strtotime( '- ' . $settings['timeframe'] . ' hours' ) ) ] );
		}

		if ( ! empty( $settings['category_include'] ) ) {
			$query->set( 'category__in', array_map( 'absint', $settings['category_include'] ) );
		}

		if ( ! empty( $settings['category_exclude'] ) ) {
			$query->set( 'category__not_in', array_map( 'absint', $settings['category_exclude'] ) );
		}
	}

	/**
	 * Toggle full-content/excerpt display on frontend.
	 *
	 * @param bool $value Whether to use excerpt in RSS.
	 * @return bool Modified $value.
	 */
	public static function filter_use_rss_excerpt( $value ) {
		if ( ! is_feed() ) {
			return $value;
		}

		$settings = self::get_feed_settings();
		if ( ! $settings ) {
			return $value;
		}

		return ! $settings['full_content'];
	}

	/**
	 * Add extra tags to RSS items on frontend.
	 */
	public static function add_extra_tags() {
		$settings = self::get_feed_settings();
		if ( ! $settings ) {
			return;
		}

		$post = get_post();

		if ( $settings['use_image_tags'] ) {
			$thumbnail_url = get_the_post_thumbnail_url( $post, 'full' );
			if ( $thumbnail_url ) :
				?>
				<image><?php echo esc_url( $thumbnail_url ); ?></image>
				<?php
			endif;
		}

		if ( $settings['use_updated_tags'] ) {
			?>
			<updated><?php echo esc_html( get_the_modified_date( 'Y-m-d\TH:i:s' ) ); ?></updated>
			<?php
		}

		if ( $settings['use_tags_tags'] ) {
			$cats         = get_the_terms( $post, 'category' );
			$cats         = ( ! is_array( $cats ) ) ? [] : $cats;
			$tags         = get_the_terms( $post, 'post_tag' );
			$tags         = ( ! is_array( $tags ) ) ? [] : $tags;
			$all_terms    = array_merge( $cats, $tags );
			$terms_string = implode( ',', wp_list_pluck( $all_terms, 'name' ) );
			?>
			<tags><?php echo esc_html( $terms_string ); ?></tags>
			<?php
		}

		if ( $settings['use_media_tags'] ) {
			$thumbnail_id = get_post_thumbnail_id();
			if ( $thumbnail_id ) {
				$thumbnail_data = wp_get_attachment_image_src( $thumbnail_id, 'full' );
				if ( $thumbnail_data ) {
					$caption = get_the_post_thumbnail_caption();
					?>
					<media:content type="<?php echo esc_attr( get_post_mime_type( $thumbnail_id ) ); ?>" url="<?php echo esc_url( $thumbnail_data[0] ); ?>">
						<?php if ( ! empty( $caption ) ) : ?>
						<media:description><?php echo esc_html( $caption ); ?></media:description>
						<?php endif; ?>
						<media:thumbnail url="<?php echo esc_url( $thumbnail_data[0] ); ?>" width="<?php echo esc_attr( $thumbnail_data[1] ); ?>" height="<?php echo esc_attr( $thumbnail_data[2] ); ?>" />
					</media:content>
					<?php
				}
			}
		}
	}

	/**
	 * The Newspack Theme adds featured images to the top of feed content by default. This setting toggles whether to do that.
	 *
	 * @param string $content Feed content.
	 * @return string Unmodified $content.
	 */
	public static function maybe_remove_content_featured_image( $content ) {
		$settings = self::get_feed_settings();
		if ( ! $settings ) {
			return $content;
		}

		if ( ! $settings['content_featured_image'] ) {
			remove_filter( 'the_excerpt_rss', 'newspack_thumbnails_in_rss' );
			remove_filter( 'the_content_feed', 'newspack_thumbnails_in_rss' );
		}

		return $content;
	}

	/**
	 * Suppress the Yoast prepended and appended content depending on setting.
	 *
	 * @param bool $include_yoast Whether to prepand and append content to the feed items.
	 * @return bool Modified $include_yoast
	 */
	public static function maybe_suppress_yoast( $include_yoast ) {
		$settings = self::get_feed_settings();
		if ( ! $settings ) {
			return $include_yoast;
		}

		return ! (bool) $settings['suppress_yoast'];
	}

	/**
	 * Add the 'xmlns:media="http://search.yahoo.com/mrss/"' namespace to feed if setting is checked.
	 */
	public static function maybe_inject_yahoo_namespace() {
		$settings = self::get_feed_settings();
		if ( ! $settings ) {
			return;
		}

		if ( $settings['yahoo_namespace'] ) {
			?>
xmlns:media="http://search.yahoo.com/mrss/"
			<?php
		}
	}
}
RSS::maybe_init();
