<?php
/**
 * Server-side rendering of the `newspack-blocks/video-playlist` block.
 *
 * @package WordPress
 */

/**
 * Renders the `newspack-blocks/video-playlist` block on server.
 *
 * @param array $attributes The block attributes.
 *
 * @return string
 */
function newspack_blocks_render_block_video_playlist( $attributes ) {
	Newspack_Blocks::enqueue_view_assets( 'video-playlist' );

	$videos = newspack_blocks_get_video_playlist( $attributes );
	return $videos['html'];
}

/**
 * Registers the `newspack-blocks/donate` block on server.
 */
function newspack_blocks_register_video_playlist() {
	register_block_type(
		'newspack-blocks/youtube-video-playlist',
		array(
			'attributes'      => array(
				'className'    => array(
					'type' => 'string',
				),
				'categories'   => array(
					'type'    => 'array',
					'default' => array(),
					'items'   => array(
						'type' => 'integer',
					),
				),
				'videosToShow' => array(
					'type'    => 'integer',
					'default' => 5,
				),
			),
			'render_callback' => 'newspack_blocks_render_block_video_playlist',
			'supports'        => [],
		)
	);
}
add_action( 'init', 'newspack_blocks_register_video_playlist' );

/**
 * Get video playlist data for general args.
 *
 * @param array $args Arguments. See $defaults.
 * @return array of video info.
 */
function newspack_blocks_get_video_playlist( $args ) {
	$defaults = array(
		'categories'   => array(),
		'videos'       => array(),
		'videosToShow' => 5,
		'className'    => '',
		'manual'       => false,
		'align'        => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	if ( $args['manual'] ) {
		$videos = array_map( 'esc_url_raw', $args['videos'] );
	} else {
		$videos = newspack_blocks_get_video_playlist_videos( $args );
	}

	$html = '';
	if ( $videos ) {
		$html = newspack_blocks_get_video_playlist_html( $videos, $args );
	}

	return array(
		'videos' => $videos,
		'html'   => $html,
	);
}

/**
 * Get video URLs from among published post content.
 *
 * @param array $args Arguments. See $defaults.
 * @return array of strings.
 */
function newspack_blocks_get_video_playlist_videos( $args ) {
	$defaults = array(
		'categories'   => array(),
		'videosToShow' => 5,
	);
	$args     = wp_parse_args( $args, $defaults );

	$query_args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		's'              => 'youtube',
		'posts_per_page' => $args['videosToShow'],
	);

	if ( ! empty( $args['categories'] ) ) {
		$query_args['category__in'] = $args['categories'];
	}

	$videos = array();
	$posts  = get_posts( $query_args );
	foreach ( $posts as $post ) {
		$blocks         = parse_blocks( $post->post_content );
		$youtube_blocks = array_filter(
			$blocks,
			function( $block ) {
				return ( 'core/embed' === $block['blockName'] && 'youtube' === $block['attrs']['providerNameSlug'] )
					|| 'core-embed/youtube' === $block['blockName'];
			}
		);
		foreach ( $youtube_blocks as $youtube_block ) {
			$videos[] = $youtube_block['attrs']['url'];
		}
	}

	return array_slice( array_unique( $videos ), 0, $args['videosToShow'] );
}

/**
 * Get embed html for an array of YouTube video URLs.
 *
 * @param array $videos YouTube video URLs.
 * @param array $args 'className', 'align' are currently supported.
 * @return string HTML embed.
 */
function newspack_blocks_get_video_playlist_html( $videos, $args = array() ) {
	$video_ids = array();
	foreach ( $videos as $video ) {
		$url_parts = wp_parse_url( $video );
		if ( empty( $url_parts['query'] ) ) {
			continue;
		}

		wp_parse_str( $url_parts['query'], $query_parts );
		if ( ! empty( $query_parts['v'] ) ) {
			$video_ids[] = $query_parts['v'];
		}
	}

	if ( empty( $video_ids ) ) {
		return '';
	}

	$url = 'https://www.youtube.com/embed/' . $video_ids[0] . '?rel=0&showinfo=1';
	if ( count( $video_ids ) > 1 ) {
		$url .= '&playlist=' . implode( ',', array_slice( $video_ids, 1 ) );
	}

	$classes = array(
		'wp-block-newspack-blocks-video-playlist',
		'wpbnbvp',
		'wp-block-embed-youtube',
		'wp-block-embed',
		'is-type-video',
		'is-provider-youtube',
		'wp-embed-aspect-16-9',
		'wp-has-aspect-ratio',
	);

	if ( ! empty( $args['className'] ) ) {
		$classes[] = $args['className'];
	}
	if ( ! empty( $args['align'] ) ) {
		$classes[] = 'align' . $args['align'];
	}
	ob_start();
	?>
	<figure class='<?php echo esc_attr( implode( ' ', $classes ) ); ?>'>
		<div class='wp-block-embed__wrapper'>
			<iframe width='960' height='540' src='<?php echo esc_attr( $url ); ?>' frameborder='0' allowfullscreen allow="autoplay; encrypted-media"></iframe>
		</div>
	</figure>
	<?php
	return ob_get_clean();
}
