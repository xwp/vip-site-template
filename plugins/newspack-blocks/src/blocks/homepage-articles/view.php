<?php
/**
 * Server-side rendering of the `newspack-blocks/homepage-posts` block.
 *
 * @package WordPress
 */

/**
 * Calculate the maximum width of an image in the Homepage Posts block.
 */
function newspack_blocks_hpb_maximum_image_width() {
	$max_width = 0;

	global $newspack_blocks_hpb_rendering_context;
	if ( isset( $newspack_blocks_hpb_rendering_context['attrs'] ) ) {
		$attributes = $newspack_blocks_hpb_rendering_context['attrs'];
		if ( empty( $attributes ) ) {
			return $max_width;
		}
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], [ 'full', 'wide' ], true ) ) {
			// For full and wide alignments, the image width is more than 100% of the content width
			// and depends on site width. Can't make assumptions about the site width.
			return $max_width;
		}
		$site_content_width  = 1200;
		$is_image_half_width = in_array( $attributes['mediaPosition'], [ 'left', 'right' ], true );
		if ( 'grid' === $attributes['postLayout'] ) {
			$columns = $attributes['columns'];
			if ( $is_image_half_width ) {
				// If the media position is on left or right, the image is 50% of the column width.
				$columns = $columns * 2;
			}
			return $site_content_width / $columns;
		} elseif ( 'list' === $attributes['postLayout'] && $is_image_half_width ) {
			return $site_content_width / 2;
		}
	}
	return $max_width;
}

/**
 * Set image `sizes` attribute based on the maximum image width.
 *
 * @param array $sizes Sizes for the sizes attribute.
 */
function newspack_blocks_filter_hpb_sizes( $sizes ) {
	if ( defined( 'NEWSPACK_DISABLE_HPB_IMAGE_OPTIMISATION' ) && NEWSPACK_DISABLE_HPB_IMAGE_OPTIMISATION ) {
		// Allow disabling the image optimisation per-site.
		return $sizes;
	}
	global $newspack_blocks_hpb_current_theme;
	if ( ! $newspack_blocks_hpb_current_theme ) {
		$newspack_blocks_hpb_current_theme = wp_get_theme()->template;
	}
	if ( stripos( $newspack_blocks_hpb_current_theme, 'newspack' ) === false ) {
		// Bail if not using a Newspack theme – assumptions about the site content width can't be made then.
		return $sizes;
	}
	$max_width = newspack_blocks_hpb_maximum_image_width();
	if ( 0 !== $max_width ) {
		// >=782px is the desktop size – set width as computed.
		$sizes = '(min-width: 782px) ' . $max_width . 'px';
		// Between 600-782px is the tablet size – all columns will collapse to two-column layout
		// (assuming 5% padding on each side and between columns).
		$sizes .= ', (min-width: 600px) 42.5vw';
		// <=600px is the mobile size – columns will stack to full width
		// (assumming 5% side padding on each side).
		$sizes .= ', 90vw';
	}
	return $sizes;
}

/**
 * Renders the `newspack-blocks/homepage-posts` block on server.
 *
 * @param array $attributes The block attributes.
 *
 * @return string Returns the post content with latest posts added.
 */
function newspack_blocks_render_block_homepage_articles( $attributes ) {
	// Don't output the block inside RSS feeds.
	if ( is_feed() ) {
		return;
	}

	// This will let the FSE plugin know we need CSS/JS now.
	do_action( 'newspack_blocks_render_homepage_articles' );

	$article_query = new WP_Query( Newspack_Blocks::build_articles_query( $attributes, apply_filters( 'newspack_blocks_block_name', 'newspack-blocks/homepage-articles' ) ) );

	$classes = Newspack_Blocks::block_classes( 'homepage-articles', $attributes, [ 'wpnbha' ] );

	if ( isset( $attributes['postLayout'] ) && 'grid' === $attributes['postLayout'] ) {
		$classes .= ' is-grid';
	}
	if ( isset( $attributes['columns'] ) && 'grid' === $attributes['postLayout'] ) {
		$classes .= ' columns-' . $attributes['columns'] . ' colgap-' . $attributes['colGap'];
	}
	if ( $attributes['showImage'] ) {
		$classes .= ' show-image';
	}
	if ( $attributes['showImage'] && isset( $attributes['mediaPosition'] ) ) {
		$classes .= ' image-align' . $attributes['mediaPosition'];
	}
	if ( isset( $attributes['typeScale'] ) ) {
		$classes .= ' ts-' . $attributes['typeScale'];
	}
	if ( $attributes['showImage'] && isset( $attributes['imageScale'] ) ) {
		$classes .= ' is-' . $attributes['imageScale'];
	}
	if ( $attributes['showImage'] ) {
		$classes .= ' is-' . $attributes['imageShape'];
	}
	if ( $attributes['showImage'] && $attributes['mobileStack'] ) {
		$classes .= ' mobile-stack';
	}
	if ( $attributes['showCaption'] ) {
		$classes .= ' show-caption';
	}
	if ( $attributes['showCategory'] ) {
		$classes .= ' show-category';
	}
	if ( isset( $attributes['className'] ) ) {
		$classes .= ' ' . $attributes['className'];
	}
	if ( $attributes['textAlign'] ) {
		$classes .= ' has-text-align-' . $attributes['textAlign'];
	}

	if ( '' !== $attributes['textColor'] || '' !== $attributes['customTextColor'] ) {
		$classes .= ' has-text-color';
	}
	if ( '' !== $attributes['textColor'] ) {
		$classes .= ' has-' . $attributes['textColor'] . '-color';
	}

	$styles = '';

	if ( '' !== $attributes['customTextColor'] ) {
		$styles = 'color: ' . $attributes['customTextColor'] . ';';
	}
	$articles_rest_url = add_query_arg(
		array_merge(
			array_map(
				function( $attribute ) {
					return false === $attribute ? '0' : str_replace( '#', '%23', $attribute );
				},
				$attributes
			),
			[
				'page' => 2,
				'amp'  => Newspack_Blocks::is_amp(),
			]
		),
		rest_url( '/newspack-blocks/v1/articles' )
	);

	$page = $article_query->paged ?? 1;

	$has_more_pages = ( ++$page ) <= $article_query->max_num_pages;

	/**
	 * Hide the "More" button on private sites.
	 *
	 * Client-side fetching from a private WP.com blog requires authentication,
	 * which is not provided in the current implementation.
	 * See https://github.com/Automattic/newspack-blocks/issues/306.
	 */
	$is_blog_private = (int) get_option( 'blog_public' ) === -1;

	$has_more_button = ! $is_blog_private && $has_more_pages && (bool) $attributes['moreButton'];

	if ( $has_more_button ) {
		$classes .= ' has-more-button';
	}

	ob_start();

	if ( $article_query->have_posts() ) : ?>
		<?php if ( $has_more_button && Newspack_Blocks::is_amp() ) : ?>
			<amp-script layout="container" src="<?php echo esc_url( plugins_url( '/newspack-blocks/amp/homepage-articles/view.js' ) ); ?>">
		<?php endif; ?>
		<div
			class="<?php echo esc_attr( $classes ); ?>"
			style="<?php echo esc_attr( $styles ); ?>"
			>
			<div data-posts data-current-post-id="<?php the_ID(); ?>">
				<?php if ( '' !== $attributes['sectionHeader'] ) : ?>
					<h2 class="article-section-title">
						<span><?php echo wp_kses_post( $attributes['sectionHeader'] ); ?></span>
					</h2>
				<?php endif; ?>
				<?php
				echo Newspack_Blocks::template_inc( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					__DIR__ . '/templates/articles-list.php',
					[
						'articles_rest_url' => $articles_rest_url,
						'article_query'     => $article_query,
						'attributes'        => $attributes,
					]
				);
				?>
			</div>
			<?php

			if ( $has_more_button ) :
				?>
				<button type="button" class="wp-block-button__link" data-next="<?php echo esc_url( $articles_rest_url ); ?>">
				<?php
				if ( ! empty( $attributes['moreButtonText'] ) ) {
					echo esc_html( $attributes['moreButtonText'] );
				} else {
					esc_html_e( 'Load more posts', 'newspack-blocks' );
				}
				?>
				</button>
				<p class="loading">
					<?php esc_html_e( 'Loading...', 'newspack-blocks' ); ?>
				</p>
				<p class="error">
					<?php esc_html_e( 'Something went wrong. Please refresh the page and/or try again.', 'newspack-blocks' ); ?>
				</p>

			<?php endif; ?>

		</div>
		<?php if ( $has_more_button && Newspack_Blocks::is_amp() ) : ?>
			</amp-script>
		<?php endif; ?>
		<?php
	endif;

	$content = ob_get_clean();
	Newspack_Blocks::enqueue_view_assets( 'homepage-articles' );

	return $content;
}

/**
 * Registers the `newspack-blocks/homepage-articles` block on server.
 */
function newspack_blocks_register_homepage_articles() {
	$block = json_decode(
		file_get_contents( __DIR__ . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		true
	);
	register_block_type(
		apply_filters( 'newspack_blocks_block_name', 'newspack-blocks/' . $block['name'] ),
		apply_filters(
			'newspack_blocks_block_args',
			array(
				'attributes'      => $block['attributes'],
				'render_callback' => 'newspack_blocks_render_block_homepage_articles',
				'supports'        => [],
			),
			$block['name']
		)
	);
}
add_action( 'init', 'newspack_blocks_register_homepage_articles' );


/**
 * Renders author avatar markup.
 *
 * @param array $author_info Author info array.
 *
 * @return string Returns formatted Avatar markup
 */
function newspack_blocks_format_avatars( $author_info ) {
	$elements = array_map(
		function ( $author ) {
			return sprintf( '<a href="%s">%s</a>', $author->url, $author->avatar );
		},
		$author_info
	);

	return implode( '', $elements );
}

/**
 * Renders byline markup.
 *
 * @param array $author_info Author info array.
 *
 * @return string Returns byline markup.
 */
function newspack_blocks_format_byline( $author_info ) {
	$index    = -1;
	$elements = array_merge(
		[
			'<span class="author-prefix">' . esc_html_x( 'by', 'post author', 'newspack-blocks' ) . '</span> ',
		],
		array_reduce(
			$author_info,
			function ( $accumulator, $author ) use ( $author_info, &$index ) {
				$index ++;
				$penultimate = count( $author_info ) - 2;

				$get_author_posts_url = get_author_posts_url( $author->ID );
				if ( function_exists( 'coauthors_posts_links' ) ) {
					$get_author_posts_url = get_author_posts_url( $author->ID, $author->user_nicename );
				}

				return array_merge(
					$accumulator,
					[
						sprintf(
							/* translators: 1: author link. 2: author name. 3. variable seperator (comma, 'and', or empty) */
							'<span class="author vcard"><a class="url fn n" href="%1$s">%2$s</a></span>',
							esc_url( $get_author_posts_url ),
							esc_html( $author->display_name )
						),
						( $index < $penultimate ) ? ', ' : '',
						( count( $author_info ) > 1 && $penultimate === $index ) ? esc_html_x( ' and ', 'post author', 'newspack-blocks' ) : '',
					]
				);
			},
			[]
		)
	);

	return implode( '', $elements );
}

/**
 * Inject amp-state containing all post IDs visible on page load.
 */
function newspack_blocks_inject_amp_state() {
	if ( ! Newspack_Blocks::is_amp() ) {
		return;
	}
	global $newspack_blocks_post_id;
	if ( ! $newspack_blocks_post_id || ! count( $newspack_blocks_post_id ) ) {
		return;
	}
	$post_ids = implode( ', ', array_merge( array_keys( $newspack_blocks_post_id ), [ get_the_ID() ] ) );
	ob_start();
	?>
	<amp-state id='newspackHomepagePosts'>
		<script type="application/json">
			{
				"exclude_ids": [ <?php echo esc_attr( $post_ids ); ?> ]
			}
		</script>
	</amp-state>
	<?php
	echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_action( 'wp_footer', 'newspack_blocks_inject_amp_state' );
