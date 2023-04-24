<?php
/**
 * Homepage Posts Pattern 22.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 22', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns {\"className\":\"is-style-default\"} -->\n<div class=\"wp-block-columns is-style-default\"><!-- wp:column {\"width\":67} -->\n<div class=\"wp-block-column\" style=\"flex-basis:67%\"><!-- wp:newspack-blocks/homepage-articles {\"showAvatar\":false,\"postsToShow\":1,\"typeScale\":5} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":33} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33%\"><!-- wp:newspack-blocks/homepage-articles {\"excerptLength\":10,\"showAvatar\":false,\"postsToShow\":5,\"mediaPosition\":\"left\",\"typeScale\":2,\"imageScale\":1} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
