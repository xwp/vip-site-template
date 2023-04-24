<?php
/**
 * Subscribe Pattern 4.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Subscribe Pattern 4', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"className\":\"newspack-pattern subscribe__style-4\"} -->\n<div class=\"wp-block-group newspack-pattern subscribe__style-4\"><div class=\"wp-block-group__inner-container\"><!-- wp:columns {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-center\"><!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\"><!-- wp:heading -->\n<h2>" . esc_html__( 'Subscribe to our newsletter', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\"><!-- wp:jetpack/mailchimp {\"consentText\":\"" . esc_html__( 'By subscribing, you agree to share your email address with us and Mailchimp to receive marketing, updates, and other emails from us. Use the unsubscribe link in those emails to opt out at any time.', 'newspack-blocks' ) . "\",\"interests\":[]} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"text\":\"" . esc_html__( 'Subscribe', 'newspack-blocks' ) . "\"} /-->\n<!-- /wp:jetpack/mailchimp --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-subscribe' ),
);
