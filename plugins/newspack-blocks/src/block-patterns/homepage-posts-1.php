<?php
/**
 * Homepage Posts Pattern 1.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 1', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":\"66.66%\"} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"minHeight\":60,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":5} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"minHeight\":30,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":3} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":\"33.33%\"} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33.33%\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"minHeight\":60,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":3} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"minHeight\":30,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":2} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
