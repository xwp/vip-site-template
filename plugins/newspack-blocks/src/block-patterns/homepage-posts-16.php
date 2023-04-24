<?php
/**
 * Homepage Posts Pattern 16.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 16', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"backgroundColor\":\"dark-gray\",\"textColor\":\"white\",\"align\":\"full\"} -->\n<div class=\"wp-block-group alignfull has-dark-gray-background-color has-white-color has-text-color has-background\"><div class=\"wp-block-group__inner-container\"><!-- wp:spacer {\"height\":20} -->\n<div style=\"height:20px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"showAuthor\":false,\"showAvatar\":false,\"mediaPosition\":\"left\",\"typeScale\":2,\"imageScale\":1,\"textColor\":\"white\"} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"showAuthor\":false,\"showAvatar\":false,\"mediaPosition\":\"left\",\"typeScale\":2,\"imageScale\":1,\"textColor\":\"white\"} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"showAuthor\":false,\"showAvatar\":false,\"mediaPosition\":\"left\",\"typeScale\":2,\"imageScale\":1,\"textColor\":\"white\"} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->\n\n<!-- wp:spacer {\"height\":20} -->\n<div style=\"height:20px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1280,
	'categories'    => array( 'newspack-homepage-posts' ),
);
