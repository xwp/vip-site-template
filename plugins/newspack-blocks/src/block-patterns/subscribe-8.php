<?php
/**
 * Subscribe Pattern 8.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'      => esc_html__( 'Subscribe Pattern 8', 'newspack-blocks' ),
	'content'    => "<!-- wp:group {\"templateLock\":\"all\",\"className\":\"newspack-pattern subscribe__style-8\"} -->\n<div class=\"wp-block-group newspack-pattern subscribe__style-8\"><!-- wp:image {\"lock\":{\"remove\":false},\"id\":999} -->\n<figure class=\"wp-block-image\"><img src=\"https://newspack.newspackstaging.com/wp-content/uploads/2022/03/pexels-brotin-biswas-518543-crop-scaled.jpg\" alt=\"\" class=\"wp-image-999\"/></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"newspack-pattern__inner\"} -->\n<div class=\"wp-block-group newspack-pattern__inner\"><!-- wp:heading {\"lock\":{\"remove\":false},\"level\":4,\"className\":\"newspack-pattern__heading\"} -->\n<h4 class=\"newspack-pattern__heading\">" . esc_html__( 'Subscribe to our Newsletter', 'newspack-blocks' ) . "</h4>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"lock\":{\"remove\":false}} -->\n<p>" . esc_html__( 'Be the first to know about breaking news, articles, and updates.', 'newspack-blocks' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:jetpack/mailchimp {\"consentText\":\"" . esc_html__( 'By subscribing, you agree to share your email address with us. Use the unsubscribe link in those emails to opt out at any time.', 'newspack-blocks' ) . "\"} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"text\":\"" . esc_html__( 'Subscribe', 'newspack-blocks' ) . "\"} /-->\n<!-- /wp:jetpack/mailchimp --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->",
	'categories' => array( 'newspack-subscribe' ),
);
