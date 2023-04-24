<?php
/**
 * Homepage Posts Pattern 30.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 30', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":75} -->\n<div class=\"wp-block-column\" style=\"flex-basis:75%\"><!-- wp:newspack-blocks/homepage-articles {\"excerptLength\":20,\"postsToShow\":1,\"mediaPosition\":\"right\",\"typeScale\":5} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"excerptLength\":10,\"showAvatar\":false,\"postLayout\":\"grid\",\"typeScale\":3} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-default\",\"showExcerpt\":false,\"showImage\":false,\"showAvatar\":false,\"postLayout\":\"grid\",\"postsToShow\":6,\"typeScale\":3} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":25} -->\n<div class=\"wp-block-column\" style=\"flex-basis:25%\"><!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-default\",\"showExcerpt\":false,\"showImage\":false,\"showAuthor\":false,\"showAvatar\":false,\"postsToShow\":10,\"typeScale\":2} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
