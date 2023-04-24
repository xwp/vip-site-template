<?php
/**
 * Homepage Posts Pattern 5.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 5', 'newspack-blocks' ),
	'content'       => "<!-- wp:columns {\"className\":\"is-style-default\"} -->\n<div class=\"wp-block-columns is-style-default\"><!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showAvatar\":false,\"postsToShow\":1,\"categories\":[],\"typeScale\":3,\"imageScale\":1} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showImage\":false,\"showAuthor\":false,\"showAvatar\":false,\"mediaPosition\":\"left\",\"categories\":[],\"typeScale\":2,\"imageScale\":1} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showAvatar\":false,\"postsToShow\":1,\"categories\":[],\"typeScale\":3,\"imageScale\":1} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showImage\":false,\"showAuthor\":false,\"showAvatar\":false,\"mediaPosition\":\"left\",\"categories\":[],\"typeScale\":2,\"imageScale\":1} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showAvatar\":false,\"postsToShow\":1,\"categories\":[],\"typeScale\":3,\"imageScale\":1,\"mobileStack\":true} /-->\n\n<!-- wp:newspack-blocks/homepage-articles {\"className\":\"is-style-borders\",\"showExcerpt\":false,\"showImage\":false,\"showAuthor\":false,\"showAvatar\":false,\"mediaPosition\":\"left\",\"categories\":[],\"typeScale\":2,\"imageScale\":1} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->",
	'viewportWidth' => 1000,
	'categories'    => array( 'newspack-homepage-posts' ),
);
