<?php
/**
 * Subscribe Pattern 5.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Subscribe Pattern 5', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"align\":\"full\",\"backgroundColor\":\"primary\",\"textColor\":\"white\",\"className\":\"newspack-pattern subscribe__style-5\"} -->\n<div class=\"wp-block-group alignfull newspack-pattern subscribe__style-5 has-white-color has-primary-background-color has-text-color has-background\"><!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:heading {\"textAlign\":\"center\",\"level\":4} -->\n<h4 class=\"has-text-align-center\">" . esc_html__( 'Subscribe to our newsletter', 'newspack-blocks' ) . "</h4>\n<!-- /wp:heading -->\n\n<!-- wp:jetpack/mailchimp {\"consentText\":\"" . esc_html__( 'By subscribing, you agree to share your email address with us and Mailchimp to receive marketing, updates, and other emails from us. Use the unsubscribe link in those emails to opt out at any time.', 'newspack-blocks' ) . "\",\"interests\":[]} -->\n<!-- wp:jetpack/button {\"element\":\"button\",\"uniqueId\":\"mailchimp-widget-id\",\"text\":\"" . esc_html__( 'Subscribe', 'newspack-blocks' ) . "\",\"textColor\":\"dark-gray\",\"backgroundColor\":\"white\",\"className\":\"is-style-fill\"} /-->\n<!-- /wp:jetpack/mailchimp -->\n\n<!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-subscribe' ),
);
