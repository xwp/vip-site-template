<?php
/**
 * Donations Pattern 1.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Donations Pattern 1', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"className\":\"newspack-pattern donation__style-1\"} -->\n<div class=\"wp-block-group newspack-pattern donation__style-1\"><div class=\"wp-block-group__inner-container\"><!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"verticalAlignment\":\"center\",\"width\":25} -->\n<div class=\"wp-block-column is-vertically-aligned-center\" style=\"flex-basis:25%\"><!-- wp:heading -->\n<h2>" . __( 'Support our publication', 'newspack-blocks' ) . "</h2>\n<!-- /wp:heading --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":75} -->\n<div class=\"wp-block-column\" style=\"flex-basis:75%\"><!-- wp:newspack-blocks/donate /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-donations' ),
);
