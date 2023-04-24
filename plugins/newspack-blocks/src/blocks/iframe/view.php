<?php
/**
 * Server-side rendering of the `newspack-blocks/iframe` block.
 *
 * @package WordPress
 */

/**
 * Renders the `newspack-blocks/iframe` block on server.
 *
 * @param array $attributes The block attributes.
 *
 * @return string
 */
function newspack_blocks_render_block_iframe( $attributes ) {
	Newspack_Blocks::enqueue_view_assets( 'iframe' );

	return newspack_blocks_get_iframe( $attributes );
}

/**
 * Registers the `newspack-blocks/iframe` block on server.
 */
function newspack_blocks_register_iframe() {
	$block_json = json_decode(
		file_get_contents( __DIR__ . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		true
	);

	register_block_type(
		'newspack-blocks/' . $block_json['name'],
		[
			'attributes'      => $block_json['attributes'],
			'render_callback' => 'newspack_blocks_render_block_iframe',
		]
	);
}
add_action( 'init', 'newspack_blocks_register_iframe' );

/**
 * Get iframe data for general args.
 *
 * @param array $args Arguments. See $defaults.
 * @return array of iframe info.
 */
function newspack_blocks_get_iframe( $args ) {
	$defaults = array(
		'mode'         => 'iframe',
		'src'          => '',
		'height'       => '600px',
		'width'        => '100%',
		'isFullScreen' => false,
		'align'        => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	return newspack_blocks_get_iframe_html( $args['mode'], $args['src'], $args['height'], $args['width'], $args['isFullScreen'], $args['align'] );
}

/**
 * Get embed html for embbed iframe.
 *
 * @param string  $mode Embed mode (iframe or document).
 * @param string  $src Iframe source.
 * @param string  $height Iframe Height.
 * @param string  $width Iframe width.
 * @param boolean $is_full_screen If the Iframe should be rendered full screen.
 * @param string  $align Iframe block alignment.
 * @return string HTML embed.
 */
function newspack_blocks_get_iframe_html( $mode, $src, $height, $width, $is_full_screen, $align ) {
	$is_document = 'document' === $mode;
	$height      = $is_full_screen ? '100' : $height;
	$width       = $is_full_screen ? '100' : $width;
	$style       = "height: $height; width: $width;";
	$layout      = 'responsive';
	$iframe_id   = 'newspack-iframe-' . wp_generate_password( 12, false );
	$classes[]   = 'wp-block-newspack-blocks-iframe';

	if ( empty( $src ) ) {
		return;
	}

	if ( ! empty( $align ) ) {
		$classes[] = 'align' . $align;
	}

	if ( $is_full_screen ) {
		$layout = 'fill';
		$style  = 'height: 100vh; width: 100vw; max-width: 100vw; max-height: 100vh; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999999; margin-top: 0!important;';
	}

	if ( $is_document ) {
		$src = 'https://docs.google.com/gview?embedded=true&url=' . rawurlencode( $src );
	}

	ob_start();
	?>
	<figure class='<?php echo esc_attr( implode( ' ', $classes ) ); ?>'>
		<div class='wp-block-embed__wrapper' style="height:<?php echo esc_attr( $height ); ?>; width:<?php echo esc_attr( $width ); ?>;">
			<iframe
				id = '<?php echo esc_attr( $iframe_id ); ?>'
				layout = '<?php echo esc_attr( $layout ); ?>'
				height = '100'
				width = '100'
				src = '<?php echo esc_attr( $src ); ?>'
				style = '<?php echo esc_attr( $style ); ?>'
				frameborder = '0'
				allowfullscreen
			></iframe>
		</div>
	</figure>

	<?php if ( ! Newspack_Blocks::is_amp() ) : ?>
		<script>
			const iframe = document.getElementById("<?php echo esc_attr( $iframe_id ); ?>");
			const timerId = setInterval( function() {
					iframe.src = iframe.src;
				}, 2000 );

			iframe.onload = function() {
				clearInterval(timerId);
			}

		</script>
	<?php endif; ?>

	<?php
	return ob_get_clean();
}
