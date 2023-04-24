<?php
/**
 * Homepage Posts Pattern 14.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 14', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":66.66} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\"><!-- wp:newspack-blocks/homepage-articles {\"postsToShow\":1} /-->\n\n<!-- wp:newspack-ads/ad-unit /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"postLayout\":\"grid\",\"columns\":2,\"postsToShow\":2} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":33.33} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33.33%\">\n\n<!-- wp:newspack-ads/ad-unit /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"postsToShow\":1} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showImage\":false,\"showAvatar\":false,\"postsToShow\":5,\"typeScale\":3} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
