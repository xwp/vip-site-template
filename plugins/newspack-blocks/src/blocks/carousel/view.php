<?php
/**
 * Server-side rendering of the `newspack-blocks/carousel` block.
 *
 * @package WordPress
 */

/**
 * Renders the `newspack-blocks/carousel` block on server.
 *
 * @param array $attributes The block attributes.
 *
 * @return string Returns the post content with latest posts added.
 */
function newspack_blocks_render_block_carousel( $attributes ) {
	static $newspack_blocks_carousel_id = 0;
	global $newspack_blocks_post_id;

	// This will let the FSE plugin know we need CSS/JS now.
	do_action( 'newspack_blocks_render_post_carousel' );

	$newspack_blocks_carousel_id++;
	$autoplay = isset( $attributes['autoplay'] ) ? $attributes['autoplay'] : false;
	$delay    = isset( $attributes['delay'] ) ? absint( $attributes['delay'] ) : 3;
	$authors  = isset( $attributes['authors'] ) ? $attributes['authors'] : array();
	$is_amp   = Newspack_Blocks::is_amp();

	$other = array();
	if ( $autoplay ) {
		$other[] = 'wp-block-newspack-blocks-carousel__autoplay-playing';
	}
	$other[] = 'slides-per-view-' . $attributes['slidesPerView'];
	$other[] = 'wpnbpc';
	$classes = Newspack_Blocks::block_classes( 'carousel', $attributes, $other );

	$article_query = new WP_Query( Newspack_Blocks::build_articles_query( $attributes, apply_filters( 'newspack_blocks_block_name', 'newspack-blocks/carousel' ) ) );
	if ( false === $article_query->have_posts() ) {
		return;
	}

	$counter = 0;

	ob_start();
	if ( $article_query->have_posts() ) :
		while ( $article_query->have_posts() ) :
			$article_query->the_post();
			$post_id                             = get_the_ID();
			$authors                             = Newspack_Blocks::prepare_authors();
			$newspack_blocks_post_id[ $post_id ] = true;

			$article_classes = [
				'post-has-image',
			];
			if ( $is_amp ) {
				$article_classes[] = 'amp-carousel-slide';
			} else {
				$article_classes[] = 'swiper-slide';
			}

			// Add classes based on the post's assigned categories and tags.
			$article_classes[] = Newspack_Blocks::get_term_classes( $post_id );

			// Get sponsors for this post.
			$sponsors = Newspack_Blocks::get_all_sponsors( $post_id );

			$counter++;
			$has_featured_image = has_post_thumbnail();
			$post_type          = get_post_type();
			$post_link          = Newspack_Blocks::get_post_link( $post_id );

			// Support Newspack Listings hide author/publish date options.
			$hide_author       = apply_filters( 'newspack_listings_hide_author', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$hide_publish_date = apply_filters( 'newspack_listings_hide_publish_date', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$show_author       = $attributes['showAuthor'] && ! $hide_author;
			$show_date         = $attributes['showDate'] && ! $hide_publish_date;
			?>

			<article data-post-id="<?php echo esc_attr( $post_id ); ?>" class="<?php echo esc_attr( implode( ' ', $article_classes ) . ' ' . $post_type ); ?>">
				<?php echo Newspack_Blocks::get_post_status_label(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<figure class="post-thumbnail">
					<?php if ( $post_link ) : ?>
					<a href="<?php echo esc_url( $post_link ); ?>" rel="bookmark" tabindex="-1" aria-hidden="true">
					<?php endif; ?>
						<?php if ( $has_featured_image ) : ?>
							<?php
								the_post_thumbnail(
									'large',
									array(
										'object-fit' => $attributes['imageFit'],
										'layout'     => 'fill',
										'class'      => 'contain' === $attributes['imageFit'] ? 'image-fit-contain' : 'image-fit-cover',
										'alt'        => trim( wp_strip_all_tags( get_the_title( $post_id ) ) ),
									)
								);
							?>
						<?php else : ?>
							<div class="wp-block-newspack-blocks-carousel__placeholder"></div>
						<?php endif; ?>
					<?php if ( $post_link ) : ?>
					</a>
					<?php endif; ?>
				</figure>

				<?php if ( ! empty( $sponsors ) || $attributes['showCategory'] || $attributes['showTitle'] || $show_author || $show_date ) : ?>
					<div class="entry-wrapper">
						<?php if ( ! empty( $sponsors ) || $attributes['showCategory'] ) : ?>
							<div class="cat-links <?php if ( ! empty( $sponsors ) ) : ?>sponsor-label<?php endif; // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace ?>">
								<?php if ( ! empty( $sponsors ) ) : ?>
									<span class="flag">
										<?php echo esc_html( Newspack_Blocks::get_sponsor_label( $sponsors ) ); ?>
									</span>
									<?php
								endif;
								$category = false;

								// Use Yoast primary category if set.
								if ( class_exists( 'WPSEO_Primary_Term' ) ) {
									$primary_term = new WPSEO_Primary_Term( 'category', $post_id );
									$category_id  = $primary_term->get_primary_term();
									if ( $category_id ) {
										$category = get_term( $category_id );
									}
								}

								if ( ! $category ) {
									$categories_list = get_the_category();
									if ( ! empty( $categories_list ) ) {
										$category = $categories_list[0];
									}
								}

								if ( $attributes['showCategory'] && $category && ( empty( $sponsors ) || Newspack_Blocks::newspack_display_sponsors_and_categories( $sponsors ) ) ) :
									?>
									<?php $category_link = get_category_link( $category->term_id ); ?>
									<?php if ( ! empty( $category_link ) ) : ?>
										<a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>">
									<?php endif; ?>
										<?php echo esc_html( $category->name ); ?>
									<?php if ( ! empty( $category_link ) ) : ?>
									</a>
										<?php
										endif;
									endif;
								?>
								</div>
							<?php
						endif;

						if ( $attributes['showTitle'] ) {
							the_title( '<h3 class="entry-title"><a href="' . esc_url( $post_link ) . '" rel="bookmark">', '</a></h3>' );
						}
						?>

						<div class="entry-meta">
							<?php
							if ( ! empty( $sponsors ) ) :
								$sponsor_classes = [ 'entry-sponsors' ];
								if ( Newspack_Blocks::newspack_display_sponsors_and_authors( $sponsors ) ) {
									$sponsor_classes[] = 'plus-author';
								}
								?>
								<span class="<?php echo esc_attr( implode( ' ', $sponsor_classes ) ); ?>">
									<?php
									$logos = Newspack_Blocks::get_sponsor_logos( $sponsors );
									if ( ! empty( $logos ) ) :
										?>
									<span class="sponsor-logos">
										<?php
										foreach ( $logos as $logo ) {
											if ( '' !== $logo['url'] ) {
												echo '<a href="' . esc_url( $logo['url'] ) . '" target="_blank">';
											}
											echo '<img src="' . esc_url( $logo['src'] ) . '" alt="' . esc_attr( $logo['alt'] ) . '" width="' . esc_attr( $logo['width'] ) . '" height="' . esc_attr( $logo['height'] ) . '">';
											if ( '' !== $logo['url'] ) {
												echo '</a>';
											}
										}
										?>
									</span>
									<?php endif; ?>

									<span class="byline sponsor-byline">
										<?php
										$bylines = Newspack_Blocks::get_sponsor_byline( $sponsors );
										echo esc_html( $bylines[0]['byline'] ) . ' ';
										foreach ( $bylines as $byline ) {
											echo '<span class="author">';
											if ( '' !== $byline['url'] ) {
												echo '<a target="_blank" href="' . esc_url( $byline['url'] ) . '">';
											}
											echo esc_html( $byline['name'] );
											if ( '' !== $byline['url'] ) {
												echo '</a>';
											}
											echo '</span>' . esc_html( $byline['sep'] );
										}
										?>
									</span>
								</span><!-- .entry-sponsors -->
								<?php
							endif;

							if ( $show_author && ( empty( $sponsors ) || Newspack_Blocks::newspack_display_sponsors_and_authors( $sponsors ) ) ) :
								if ( $attributes['showAvatar'] ) :
									echo wp_kses(
										newspack_blocks_format_avatars( $authors ),
										array(
											'img'      => array(
												'class'  => true,
												'src'    => true,
												'alt'    => true,
												'width'  => true,
												'height' => true,
												'data-*' => true,
												'srcset' => true,
											),
											'noscript' => array(),
											'a'        => array(
												'href' => true,
											),
										)
									);
								endif;
								?>
								<span class="byline">
									<?php echo wp_kses_post( newspack_blocks_format_byline( $authors ) ); ?>
								</span><!-- .author-name -->
								<?php
							endif;
							if ( $show_date ) :
								printf(
									'<time class="entry-date published" datetime="%1$s">%2$s</time>',
									esc_attr( get_the_date( DATE_W3C ) ),
									esc_html( get_the_date() )
								);
							endif;
							?>
						</div><!-- .entry-meta -->
					</div><!-- .entry-wrapper -->
				<?php endif; ?>
			</article>
			<?php
		endwhile;
		endif;
		wp_reset_postdata();
	$slides  = ob_get_clean();
	$buttons = array();
	for ( $x = 0; $x < $counter; $x++ ) {
		$aria_label = sprintf(
			/* translators: %d: Slide number. */
			__( 'Go to slide %d', 'newspack-blocks' ),
			absint( $x + 1 )
		);
		$buttons[] = sprintf(
			'<button option="%d" class="swiper-pagination-bullet" aria-label="%s" %s></button>',
			absint( $x ),
			esc_attr( $aria_label ),
			0 === $x ? 'selected' : ''
		);
	}

	$slides_per_view = absint( ! empty( $attributes['slidesPerView'] ) ? $attributes['slidesPerView'] : 1 );
	$slides_to_show  = $slides_per_view <= $counter ? $slides_per_view : $counter;

	if ( $is_amp ) {
		$selector = sprintf(
			'<amp-selector id="wp-block-newspack-carousel__amp-pagination__%1$d" class="swiper-pagination-bullets amp-pagination" on="select:wp-block-newspack-carousel__amp-carousel__%1$d.goToSlide(index=event.targetOption)" layout="container" %2$s>%3$s</amp-selector>',
			absint( $newspack_blocks_carousel_id ),
			$attributes['hideControls'] ? 'aria-hidden="true"' : '',
			implode( '', $buttons )
		);

		$carousel    = sprintf(
			'<amp-base-carousel class="wp-block-newspack-carousel__amp-carousel" width="%1$s" height="%2$s" heights="%3$s" layout="responsive" snap="true" data-next-button-aria-label="%4$s" data-prev-button-aria-label="%5$s" controls="auto" loop="true" %6$s id="wp-block-newspack-carousel__amp-carousel__%7$s" on="slideChange:wp-block-newspack-carousel__amp-pagination__%7$s.toggle(index=event.index, value=true)" advance-count="1" visible-count="%8$s">%9$s</amp-base-carousel>',
			$attributes['slidesPerView'] * 1,
			$attributes['aspectRatio'],
			'(min-width: 1168px) ' . ( $attributes['aspectRatio'] / $slides_to_show * 100 ) . '% !important, (min-width: 782px) ' . ( $slides_to_show > 1 ? ( $attributes['aspectRatio'] / 2 * 100 ) . '% !important' : ( $attributes['aspectRatio'] * 100 ) . '% !important' ) . ', ' . ( $attributes['aspectRatio'] * 100 ) . '% !important',
			esc_attr__( 'Next Slide', 'newspack-blocks' ),
			esc_attr__( 'Previous Slide', 'newspack-blocks' ),
			$autoplay ? 'auto-advance="true" auto-advance-interval=' . esc_attr( $delay * 1000 ) : '',
			absint( $newspack_blocks_carousel_id ),
			'(min-width: 1168px) ' . $slides_to_show . ', (min-width: 782px) ' . ( $slides_to_show > 1 ? 2 : 1 ) . ', ' . 1,
			$slides
		);
		$autoplay_ui = $autoplay ? newspack_blocks_carousel_block_autoplay_ui_amp( $newspack_blocks_carousel_id ) : '';
	} else {
		$selector    = sprintf(
			'<div class="swiper-pagination-bullets amp-pagination" %1$s>%2$s</div>',
			$attributes['hideControls'] ? 'aria-hidden="true"' : '',
			implode( '', $buttons )
		);
		$navigation  = 1 === $counter ? '' : sprintf(
			'<button class="swiper-button swiper-button-prev" aria-label="%1$s" %3$s></button><button class="swiper-button swiper-button-next" aria-label="%2$s" %3$s></button>',
			esc_attr__( 'Previous Slide', 'newspack-blocks' ),
			esc_attr__( 'Next Slide', 'newspack-blocks' ),
			$attributes['hideControls'] ? 'aria-hidden="true"' : ''
		);
		$carousel    = sprintf(
			'<div class="swiper"><div class="swiper-wrapper">%s</div>%s</div>',
			$slides,
			$navigation
		);
		$autoplay_ui = $autoplay ? newspack_blocks_carousel_block_autoplay_ui( $newspack_blocks_carousel_id ) : '';
	}
	$data_attributes = [
		'data-current-post-id=' . $post_id,
		'data-slides-per-view=' . $attributes['slidesPerView'],
		'data-slide-count=' . $counter,
		'data-aspect-ratio=' . $attributes['aspectRatio'],
	];

	if ( $autoplay && ! $is_amp ) {
		$data_attributes[] = 'data-autoplay=1';
		$data_attributes[] = sprintf( 'data-autoplay_delay=%s', esc_attr( $delay ) );
	}
	Newspack_Blocks::enqueue_view_assets( 'carousel' );
	if ( 1 === $counter ) {
		$selector = '';
	}
	return sprintf(
		'<div class="%1$s" id="wp-block-newspack-carousel__%2$d" %3$s>%4$s%5$s%6$s</div>',
		esc_attr( $classes ),
		absint( $newspack_blocks_carousel_id ),
		esc_attr( implode( ' ', $data_attributes ) ),
		$autoplay_ui,
		$carousel,
		$selector
	);
}

/**
 * Generate autoplay play/pause UI for non-AMP requests.
 *
 * @return string Autoplay UI markup.
 */
function newspack_blocks_carousel_block_autoplay_ui() {
	return sprintf(
		'<button aria-label="%s" class="swiper-button swiper-button-pause"></button><button aria-label="%s" class="swiper-button swiper-button-play"></button>',
		esc_attr__( 'Pause Slideshow', 'newspack-blocks' ),
		esc_attr__( 'Play Slideshow', 'newspack-blocks' )
	);
}

/**
 * Generate autoplay play/pause UI for AMP requests.
 *
 * @param int $block_ordinal The ordinal number of the block, used in unique ID.
 *
 * @return string Autoplay UI markup.
 */
function newspack_blocks_carousel_block_autoplay_ui_amp( $block_ordinal = 0 ) {
	$block_id        = sprintf(
		'wp-block-newspack-carousel__%d',
		absint( $block_ordinal )
	);
	$amp_carousel_id = sprintf(
		'wp-block-newspack-carousel__amp-carousel__%d',
		absint( $block_ordinal )
	);
	$autoplay_pause  = sprintf(
		'<button aria-label="%s" class="amp-carousel-button-pause amp-carousel-button" on="tap:%s.toggleAutoplay(toggleOn=false),%s.toggleClass(class=wp-block-newspack-blocks-carousel__autoplay-playing,force=false)"></button>',
		esc_attr__( 'Pause Slideshow', 'newspack-blocks' ),
		esc_attr( $amp_carousel_id ),
		esc_attr( $block_id )
	);
	$autoplay_play   = sprintf(
		'<button aria-label="%s" class="amp-carousel-button-play amp-carousel-button" on="tap:%s.toggleAutoplay(toggleOn=true),%s.toggleClass(class=wp-block-newspack-blocks-carousel__autoplay-playing,force=true)"></button>',
		esc_attr__( 'Play Slideshow', 'newspack-blocks' ),
		esc_attr( $amp_carousel_id ),
		esc_attr( $block_id )
	);
	return $autoplay_pause . $autoplay_play;
}

/**
 * Registers the `newspack-blocks/carousel` block on server.
 */
function newspack_blocks_register_carousel() {
	register_block_type(
		apply_filters( 'newspack_blocks_block_name', 'newspack-blocks/carousel' ),
		apply_filters(
			'newspack_blocks_block_args',
			array(
				'attributes'      => array(
					'className'     => array(
						'type' => 'string',
					),
					'postsToShow'   => array(
						'type'    => 'integer',
						'default' => 3,
					),
					'authors'       => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type' => 'integer',
						),
					),
					'categories'    => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type' => 'integer',
						),
					),
					'tags'          => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type' => 'integer',
						),
					),
					'autoplay'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'delay'         => array(
						'type'    => 'integer',
						'default' => 5,
					),
					'showAuthor'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAvatar'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showCategory'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'showDate'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'imageFit'      => array(
						'type'    => 'string',
						'default' => 'cover',
					),
					'showTitle'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'slidesPerView' => array(
						'type'    => 'number',
						'default' => 1,
					),
					'hideControls'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'aspectRatio'   => array(
						'type'    => 'number',
						'default' => 0.75,
					),
				),
				'render_callback' => 'newspack_blocks_render_block_carousel',
				'supports'        => [],
			),
			'carousel'
		)
	);
}
add_action( 'init', 'newspack_blocks_register_carousel' );
