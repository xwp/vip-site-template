<?php
/**
 * Homepage Posts Pattern 15.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 15', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":66.66} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\"><!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-default\",\"showExcerpt\":false,\"minHeight\":55,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":6} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":33.33} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33.33%\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"showImage\":false,\"showAvatar\":false,\"typeScale\":5} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1280,
	'categories'    => array( 'newspack-homepage-posts' ),
);
