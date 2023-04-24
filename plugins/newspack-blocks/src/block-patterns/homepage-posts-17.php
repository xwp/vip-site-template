<?php
/**
 * Homepage Posts Pattern 17.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 17', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":66.66} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\"><!-- wp:newspack-blocks/homepage-articles {\"excerptLength\":30,\"showAvatar\":false,\"postsToShow\":5,\"mediaPosition\":\"left\",\"typeScale\":3,\"imageScale\":1} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":33.33} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33.33%\"><!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-default\",\"showExcerpt\":false,\"showAvatar\":false,\"postsToShow\":1,\"categories\":[],\"typeScale\":3,\"imageScale\":1,\"mobileStack\":true} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"showImage\":false,\"showAvatar\":false,\"postsToShow\":5,\"typeScale\":3} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
