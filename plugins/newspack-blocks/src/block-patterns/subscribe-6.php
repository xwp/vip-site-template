<?php
/**
 * Subscribe Pattern 6.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Subscribe Pattern 6', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"align\":\"full\",\"backgroundColor\":\"light-gray\",\"className\":\"newspack-pattern subscribe__style-6\"} -->\n<div class=\"wp-block-group alignfull newspack-pattern subscribe__style-6 has-light-gray-background-color has-background\"><!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:columns {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-center\"><!-- wp:column {\"verticalAlignment\":\"center\",\"width\":\"33.33%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\" style=\"flex-basis:33.33%\"><!-- wp:heading -->\n<h2>" . esc_html__( 'Subscribe to our newsletter', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"verticalAlignment\":\"center\",\"width\":\"66.66%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\" style=\"flex-basis:66.66%\"><!-- wp:jetpack/mailchimp {\"consentText\":\"" . esc_html__( 'By subscribing, you agree to share your email address with us and Mailchimp to receive marketing, updates, and other emails from us. Use the unsubscribe link in those emails to opt out at any time.', 'newspack-blocks' ) . "\",\"interests\":[]} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"text\":\"" . esc_html__( 'Subscribe', 'newspack-blocks' ) . "\"} /-->\n<!-- /wp:jetpack/mailchimp --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->\n\n<!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-subscribe' ),
);
