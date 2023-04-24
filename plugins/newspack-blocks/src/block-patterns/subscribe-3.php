<?php
/**
 * Subscribe Pattern 3.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Subscribe Pattern 3', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"className\":\"newspack-pattern subscribe__style-3\"} -->\n<div class=\"wp-block-group newspack-pattern subscribe__style-3\"><!-- wp:columns {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-center\"><!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\"><!-- wp:cover {\"url\":\"https://newspack.newspackstaging.com/wp-content/uploads/2020/03/automated_upload-62.jpg\",\"id\":131,\"contentPosition\":\"center center\"} -->\n<div class=\"wp-block-cover has-background-dim\"><img class=\"wp-block-cover__image-background wp-image-131\" alt=\"\" src=\"https://newspack.newspackstaging.com/wp-content/uploads/2020/03/automated_upload-62.jpg\" data-object-fit=\"cover\"/><div class=\"wp-block-cover__inner-container\"><!-- wp:heading {\"textColor\":\"white\"} -->\n<h2 class=\"has-white-color has-text-color\">" . esc_html__( 'Subscribe to our newsletter', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading --></div></div>\n<!-- /wp:cover --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\"><!-- wp:jetpack/mailchimp {\"consentText\":\"" . esc_html__( 'By subscribing, you agree to share your email address with us and Mailchimp to receive marketing, updates, and other emails from us. Use the unsubscribe link in those emails to opt out at any time.', 'newspack-blocks' ) . "\",\"interests\":[]} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"text\":\"" . esc_html__( 'Subscribe', 'newspack-blocks' ) . "\"} /-->\n<!-- /wp:jetpack/mailchimp --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-subscribe' ),
);
