<?php
/**
 * AMP_Polyfills.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Manages settings for AMP_Polyfills.
 */
class AMP_Polyfills {
	/**
	 * Add hooks.
	 */
	public static function init() {
		add_filter( 'the_content', [ __CLASS__, 'amp_tags' ], 1, 1 );
		add_filter( 'render_block', [ __CLASS__, 'render_block' ], 1, 2 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'wp_enqueue_scripts' ], 99999 );
	}

	/**
	 * Insert HTML into a DOMDocument.
	 *
	 * @param \DOMDocument $dom DOMDocument.
	 * @param string       $html HTML.
	 * @param \DOMElement  $tag Tag.
	 */
	private static function insert_html( $dom, $html, $tag ) {
		$replacement = $dom->createElement( 'div' );
		$fragment    = $dom->createDocumentFragment();
		$fragment->appendXML( $html );
		foreach ( $fragment->childNodes as $node ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$imported_node = $dom->importNode( $node, true );
			$replacement->appendChild( $imported_node );
		}
		$tag->parentNode->replaceChild( $replacement, $tag ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Get embeed HTML for a video.
	 *
	 * @param string $type Video type.
	 * @param string $id Video ID.
	 */
	private static function get_video_embed_html( $type, $id ) {
		switch ( $type ) {
			case 'vimeo':
				$video_url = 'https://vimeo.com/' . $id;
				break;
			default:
				$video_url = 'https://www.youtube.com/watch?v=' . $id;
				break;
		}
		return '<div><!-- wp:embed {"url":"' . $video_url . '","type":"video","providerNameSlug":"' . $type . '","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
			<figure class="wp-block-embed is-type-video is-provider-' . $type . ' wp-block-embed-' . $type . ' wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
			' . $video_url . '
			</div></figure>
		<!-- /wp:embed --></div>';
	}

	/**
	 * Polyfill AMP tags.
	 *
	 * @param string $content Content.
	 */
	public static function amp_tags( $content ) {
		// If the AMP plugin is active, don't do anything.
		if ( function_exists( 'amp_is_canonical' ) ) {
			return $content;
		}

		// Polyfill amp-img.
		$content = preg_replace(
			'/<amp-img([^>]*)>[^<]*<\/amp-img>/',
			'<img\1 />', // img is a void element.
			$content
		);
		// Polyfill amp-iframe.
		$content = preg_replace(
			'/<amp-iframe([^>]*)>(.*?)<\/amp-iframe>/',
			'<iframe$1></iframe>',
			$content
		);

		$has_amp_fit_text = false !== stripos( $content, '<amp-fit-text' );
		$has_amp_youtube  = false !== stripos( $content, '<amp-youtube' );
		$has_amp_vimeo    = false !== stripos( $content, '<amp-vimeo' );

		if ( $has_amp_fit_text || $has_amp_youtube || $has_amp_vimeo ) {
			$dom = new \DomDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', get_bloginfo( 'charset' ) ) );
			$xpath = new \DOMXpath( $dom );

			// Process amp-fit-text tags.
			if ( $has_amp_fit_text ) {
				foreach ( $xpath->query( '//amp-fit-text' ) as $tag ) {
					$outer_html = $dom->saveHTML( $tag );
					// AMP plugin used to (https://github.com/ampproject/amp-wp/pull/5729) have a feature to wrap
					// headings in amp-fit-text, but it was removed. The enclosing amp-fit-text tag will be stripped,
					// leaving the original heading tag.
					$html = preg_replace( '/<amp-fit-text[^>]*>(.*)<\/amp-fit-text>/', '\1', $outer_html );
					self::insert_html( $dom, $html, $tag );
					$content = $dom->saveHTML();
				}
			}

			// Process amp-youtube tags.
			if ( $has_amp_youtube ) {
				foreach ( $xpath->query( '//amp-youtube' ) as $tag ) {
					$yt_id = false;
					foreach ( $tag->attributes as $attribute ) {
						if ( 'data-videoid' === $attribute->name ) {
							$yt_id = $attribute->value;
						}
					}
					if ( $yt_id ) {
						self::insert_html( $dom, self::get_video_embed_html( 'youtube', $yt_id ), $tag );
						$body    = $dom->getElementsByTagName( 'body' )->item( 0 );
						$content = preg_replace( '/<\/?body>/', '', $dom->saveHTML( $body ) );
					}
				}
			}

			// Process amp-vimeo tags.
			if ( $has_amp_vimeo ) {
				foreach ( $xpath->query( '//amp-vimeo' ) as $tag ) {
					$vimeo_id = false;
					foreach ( $tag->attributes as $attribute ) {
						if ( 'data-videoid' === $attribute->name ) {
							$vimeo_id = $attribute->value;
						}
					}
					if ( $vimeo_id ) {
						self::insert_html( $dom, self::get_video_embed_html( 'vimeo', $vimeo_id ), $tag );
						$body    = $dom->getElementsByTagName( 'body' )->item( 0 );
						$content = preg_replace( '/<\/?body>/', '', $dom->saveHTML( $body ) );
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Hook into render_block to polyfill blocks.
	 *
	 * @param string $block_content Block content.
	 * @param array  $block Block.
	 */
	public static function render_block( $block_content, $block ) {
		if (
			'core/image' === $block['blockName']
			&& isset( $block['attrs']['ampLightbox'] )
			&& $block['attrs']['ampLightbox']
			&& stripos( $block_content, '<a href=' ) === false // Don't add lightbox if the image is a link.
		) {
			return str_replace( '<figure', '<figure data-lightbox', $block_content );
		}
		return $block_content;
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public static function wp_enqueue_scripts() {
		if ( preg_match( '/wp:image.*"ampLightbox":true/', get_the_content() ) ) {
			\wp_enqueue_script(
				'newspack-image-lightbox',
				\Newspack\Newspack::plugin_url() . '/dist/other-scripts/lightbox.js',
				[],
				NEWSPACK_PLUGIN_VERSION,
				true
			);
			\wp_enqueue_style(
				'newspack-image-lightbox',
				Newspack::plugin_url() . '/dist/other-scripts/lightbox.css',
				[],
				NEWSPACK_PLUGIN_VERSION
			);
		}
	}
}
AMP_Polyfills::init();
