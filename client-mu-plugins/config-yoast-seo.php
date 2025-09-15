<?php
/**
 * Plugin Name: Yoast SEO Config
 *
 * @package XWP\VIP_Site_Template
 */

/**
 * Prevent Yoast SEO from adding all blog sitemaps to
 * robots.txt of all blogs. VIP creates WP multisites
 * as subdirectory installs but we never use it like that.
 */
add_filter(
	'wpseo_should_add_subdirectory_multisite_xml_sitemaps',
	'__return_false',
);
