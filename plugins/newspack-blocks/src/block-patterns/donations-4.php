<?php
/**
 * Donations Pattern 4.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Donations Pattern 4', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"align\":\"full\",\"backgroundColor\":\"light-gray\",\"className\":\"newspack-pattern donation__style-4\"} -->\n<div class=\"wp-block-group alignfull newspack-pattern donation__style-4 has-light-gray-background-color has-background\"><!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"verticalAlignment\":\"center\",\"width\":\"33.33%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\" style=\"flex-basis:33.33%\"><!-- wp:heading -->\n<h2>" . __( 'Support our publication', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":\"66.66%\"} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\"><!-- wp:newspack-blocks/donate /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->\n\n<!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-donations' ),
);
