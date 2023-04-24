<?php
/**
 * Subscribe Pattern 10.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'      => esc_html__( 'Subscribe Pattern 10', 'newspack-blocks' ),
	'content'    => "<!-- wp:group {\"templateLock\":\"all\",\"className\":\"newspack-pattern subscribe__style-10\"} -->\n<div class=\"wp-block-group newspack-pattern subscribe__style-10\"><!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":\"66.66%\"} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\"><!-- wp:paragraph {\"lock\":{\"remove\":true}} -->\n<p>" . esc_html__( 'Be the first to know about breaking news, articles, and updates.', 'newspack-blocks' ) . "</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":\"33.33%\"} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33.33%\"><!-- wp:jetpack/mailchimp {\"lock\":{\"remove\":true},\"consentText\":\"\"} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"borderRadius\":0,\"text\":\"→\"} /-->\n<!-- /wp:jetpack/mailchimp --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div>\n<!-- /wp:group -->",
	'categories' => array( 'newspack-subscribe' ),
);
