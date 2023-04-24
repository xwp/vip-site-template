<?php
/**
 * Donations Pattern 3.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Donations Pattern 3', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"backgroundColor\":\"primary\",\"textColor\":\"white\",\"align\":\"full\",\"className\":\"newspack-pattern donation__style-3\"} -->\n<div class=\"wp-block-group alignfull has-primary-background-color has-white-color has-text-color has-background newspack-pattern donation__style-3\"><div class=\"wp-block-group__inner-container\"><!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"verticalAlignment\":\"center\",\"width\":25} -->\n<div class=\"wp-block-column is-vertically-aligned-center\" style=\"flex-basis:25%\"><!-- wp:heading -->\n<h2>" . __( 'Support our publication', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":75} -->\n<div class=\"wp-block-column\" style=\"flex-basis:75%\"><!-- wp:newspack-blocks/donate /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->\n\n<!-- wp:spacer {\"height\":7} -->\n<div style=\"height:7px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-donations' ),
);
