<?php
/**
 * Homepage Posts Pattern 18.
 *
 * @package Newspack_Blocks
 */

return array(
	'title'         => __( 'Homepage Posts Pattern 18', 'newspack-blocks' ),
	'content'       => "<!-- wp:group {\"backgroundColor\":\"light-gray\",\"align\":\"full\"} -->\n<div class=\"wp-block-group alignfull has-light-gray-background-color has-background\"><div class=\"wp-block-group__inner-container\"><!-- wp:spacer {\"height\":20} -->\n<div style=\"height:20px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:columns {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-center\"><!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"showAuthor\":false,\"mediaPosition\":\"right\",\"imageScale\":1} /--></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\"><!-- wp:newspack-blocks/homepage-articles {\"showExcerpt\":false,\"minHeight\":60,\"showAuthor\":false,\"showAvatar\":false,\"postsToShow\":1,\"mediaPosition\":\"behind\",\"typeScale\":6} /--></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->\n\n<!-- wp:spacer {\"height\":20} -->\n<div style=\"height:20px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div></div>\n<!-- /wp:group -->",
	'viewportWidth' => 1280,
	'categories'    => array( 'newspack-homepage-posts' ),
);
