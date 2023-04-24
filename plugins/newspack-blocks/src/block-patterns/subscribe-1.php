<?php
/**
 * Subscribe Pattern 1.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Subscribe Pattern 1', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"className\":\"newspack-pattern subscribe__style-1\"} -->\n<div class=\"wp-block-group newspack-pattern subscribe__style-1\"><div class=\"wp-block-group__inner-container\"><!-- wp:media-text {\"align\":\"\",\"mediaPosition\":\"right\",\"mediaId\":131,\"mediaType\":\"image\",\"verticalAlignment\":\"center\",\"imageFill\":true,\"backgroundColor\":\"light-gray\",\"className\":\"has-background\"} -->\n<div class=\"wp-block-media-text has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center is-image-fill has-background has-light-gray-background-color has-background\"><figure class=\"wp-block-media-text__media\" style=\"background-image:url(https://newspack.newspackstaging.com/wp-content/uploads/2020/03/automated_upload-62.jpg);background-position:50% 50%\"><img src=\"https://newspack.newspackstaging.com/wp-content/uploads/2020/03/automated_upload-62.jpg\" alt=\"\" class=\"wp-image-131 size-full\"/></figure><div class=\"wp-block-media-text__content\"><!-- wp:spacer {\"height\":32} -->\n<div style=\"height:32px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:heading -->\n<h2>" . esc_html__( 'Subscribe to our newsletter', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:jetpack/mailchimp {\"consentText\":\"" . esc_html__( 'By subscribing, you agree to share your email address with us and Mailchimp to receive marketing, updates, and other emails from us. Use the unsubscribe link in those emails to opt out at any time.', 'newspack-blocks' ) . "\",\"interests\":[]} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"text\":\"" . esc_html__( 'Subscribe', 'newspack-blocks' ) . "\"} /-->\n<!-- /wp:jetpack/mailchimp -->\n\n<!-- wp:spacer {\"height\":32} -->\n<div style=\"height:32px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div></div>\n<!-- /wp:media-text --></div></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-subscribe' ),
);
