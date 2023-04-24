<?php
/**
 * Homepage Posts Pattern 25.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 25', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":66.66} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\"><!-- wp:newspack-blocks/homepage-articles {\"postsToShow\":1,\"typeScale\":6} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":33.33} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33.33%\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"showAuthor\":false,\"postsToShow\":5,\"mediaPosition\":\"right\",\"typeScale\":3,\"imageScale\":2} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
