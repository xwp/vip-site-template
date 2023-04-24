<?php
/**
 * Homepage Posts Pattern 2.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 2', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns {\"className\":\"is-style-first-col-to-second\"} -->\n<div class=\"wp-block-columns is-style-first-col-to-second\"><!-- wp:column {\"width\":50} -->\n<div class=\"wp-block-column\" style=\"flex-basis:50%\"><!-- wp:newspack-blocks/homepage-articles {\"postsToShow\":1,\"typeScale\":6} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":25} -->\n<div class=\"wp-block-column\" style=\"flex-basis:25%\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"minHeight\":25,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":2} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showImage\":false,\"showAvatar\":false,\"postsToShow\":5,\"typeScale\":2,\"imageScale\":1} /-->\n\n<!-- wp:newspack-ads/ad-unit /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":25} -->\n<div class=\"wp-block-column\" style=\"flex-basis:25%\">\n\n<!-- wp:newspack-ads/ad-unit /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"minHeight\":25,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":2} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showImage\":false,\"showAvatar\":false,\"postsToShow\":5,\"mediaPosition\":\"left\",\"typeScale\":2,\"imageScale\":1} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
