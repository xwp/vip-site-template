<?php
/**
 * Subscribe Pattern 2.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Subscribe Pattern 2', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"className\":\"newspack-pattern subscribe__style-2\"} -->\n<div class=\"wp-block-group newspack-pattern subscribe__style-2\"><div class=\"wp-block-group__inner-container\"><!-- wp:cover {\"url\":\"https://newspack.newspackstaging.com/wp-content/uploads/2020/03/automated_upload-62.jpg\",\"id\":131} -->\n<div class=\"wp-block-cover has-background-dim\"><img class=\"wp-block-cover__image-background wp-image-131\" alt=\"\" src=\"https://newspack.newspackstaging.com/wp-content/uploads/2020/03/automated_upload-62.jpg\" data-object-fit=\"cover\"/><div class=\"wp-block-cover__inner-container\"><!-- wp:heading {\"textAlign\":\"center\",\"textColor\":\"white\"} -->\n<h2 class=\"has-text-align-center has-white-color has-text-color\">" . esc_html__( 'Subscribe to our newsletter', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading --></div></div>\n<!-- /wp:cover -->\n\n<!-- wp:jetpack/mailchimp {\"consentText\":\"" . esc_html__( 'By subscribing, you agree to share your email address with us and Mailchimp to receive marketing, updates, and other emails from us. Use the unsubscribe link in those emails to opt out at any time.', 'newspack-blocks' ) . "\",\"interests\":[]} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"text\":\"" . esc_html__( 'Subscribe', 'newspack-blocks' ) . "\"} /-->\n<!-- /wp:jetpack/mailchimp --></div></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-subscribe' ),
);
