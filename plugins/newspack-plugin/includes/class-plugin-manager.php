<?php
/**
 * Newspack setup
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * General purpose class for managing installation/activation of plugins.
 */
class Plugin_Manager {

	/**
	 * Array of Newspack required plugins.
	 *
	 * @var array
	 */
	public static $required_plugins = [
		'jetpack',
		'pwa',
		'google-site-kit',
		'newspack-blocks',
		'wp-parsely',
	];

	/**
	 * Get info about all the managed plugins and their status.
	 *
	 * @todo Define what the structure of this looks like better and load it up from a config or something.
	 *
	 * @return array of plugins info.
	 */
	public static function get_managed_plugins() {
		$managed_plugins = [
			'akismet'                       => [
				'Name'        => esc_html__( 'Akismet Anti-Spam', 'newspack' ),
				'Description' => esc_html__( 'Used by millions, Akismet is quite possibly the best way in the world to protect your blog from spam. It keeps your site protected even while you sleep.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://automattic.com/wordpress-plugins/' ),
				'PluginURI'   => esc_url( 'https://akismet.com/' ),
				'Download'    => 'wporg',
			],
			'co-authors-plus'               => [
				'Name'        => esc_html__( 'Co-Authors Plus', 'newspack' ),
				'Description' => esc_html__( 'Allows multiple authors and guest authors to be assigned to a post.', 'newspack' ),
				'Author'      => esc_html__( 'Mohammad Jangda, Daniel Bachhuber, Automattic, Weston Ruter', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://wordpress.org/extend/plugins/co-authors-plus/' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/extend/plugins/co-authors-plus/' ),
				'Download'    => 'wporg',
			],
			'google-site-kit'               => [
				'Name'        => esc_html__( 'Google Site Kit', 'newspack' ),
				'Description' => esc_html__( 'Site Kit is is a one-stop solution for WordPress users to use everything Google has to offer to make them successful on the web.', 'newspack' ),
				'Author'      => esc_html__( 'Google', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://opensource.google.com' ),
				'PluginURI'   => esc_url( 'https://sitekit.withgoogle.com/' ),
				'Download'    => 'wporg',
				'EditPath'    => 'admin.php?page=googlesitekit-splash',
				'Configurer'  => [
					'filename'   => 'class-site-kit-configuration-manager.php',
					'class_name' => 'Site_Kit_Configuration_Manager',
				],
			],
			'gravityforms'                  => [
				'Name'        => esc_html__( 'Gravity Forms', 'newspack' ),
				'Description' => esc_html__( 'Create custom web forms to capture leads, collect payments, automate your workflows, and build your business online.', 'newspack' ),
				'Author'      => esc_html__( 'Rocketgenius', 'newspack' ),
				'PluginURI'   => esc_url( 'https://www.gravityforms.com/' ),
				'AuthorURI'   => esc_url( 'https://rocketgenius.com/' ),
			],
			'jetpack'                       => [
				'Name'        => __( 'Jetpack', 'newspack' ),
				'Description' => esc_html__( 'Bring the power of the WordPress.com cloud to your self-hosted WordPress. Jetpack enables you to connect your blog to a WordPress.com account to use the powerful features normally only available to WordPress.com users.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://jetpack.com/' ),
				'AuthorURI'   => esc_url( 'https://automattic.com/' ),
				'Download'    => 'wporg',
				'EditPath'    => 'admin.php?page=jetpack',
			],
			'mailchimp-for-woocommerce'     => [
				'Name'        => 'Mailchimp for WooCommerce',
				'Description' => esc_html__( 'Connects WooCommerce to Mailchimp to sync your store data, send targeted campaigns to your customers, and sell more stuff.', 'newspack' ),
				'Author'      => esc_html__( 'Mailchimp', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://mailchimp.com' ),
				'PluginURI'   => esc_url( 'https://mailchimp.com/connect-your-store/' ),
				'Download'    => 'wporg',
			],
			'mailchimp-for-wp'              => [
				'Name'        => esc_html__( 'MC4WP: Mailchimp for WordPress', 'newspack' ),
				'Description' => esc_html__( 'Mailchimp for WordPress by ibericode. Adds various highly effective sign-up methods to your site.', 'newspack' ),
				'Author'      => esc_html__( 'ibericode', 'newspack' ),
				'PluginURI'   => esc_url( 'https://mc4wp.com' ),
				'AuthorURI'   => esc_url( 'https://ibericode.com' ),
				'Download'    => 'wporg',
			],
			'newspack-ads'                  => [
				'Name'        => esc_html__( 'Newspack Ads', 'newspack' ),
				'Description' => esc_html__( 'Ads integration.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://newspack.com' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'Download'    => 'https://github.com/Automattic/newspack-ads/releases/latest/download/newspack-ads.zip',
			],
			'newspack-blocks'               => [
				'Name'        => esc_html__( 'Newspack Blocks', 'newspack' ),
				'Description' => esc_html__( 'A collection of blocks for news publishers.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://newspack.com' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'Download'    => 'https://github.com/Automattic/newspack-blocks/releases/latest/download/newspack-blocks.zip',
			],
			'newspack-content-converter'    => [
				'Name'        => esc_html__( 'Newspack Content Converter', 'newspack' ),
				'Description' => esc_html__( 'Batch conversion of Classic->Gutenberg post conversion.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://newspack.com' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'Download'    => 'https://github.com/Automattic/newspack-content-converter/releases/latest/download/newspack-content-converter.zip',
				'Quiet'       => true,
			],
			'newspack-newsletters'          => [
				'Name'        => esc_html__( 'Newspack Newsletters', 'newspack' ),
				'Description' => esc_html__( 'Newsletter authoring using the Gutenberg editor.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://newspack.com' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'Download'    => 'https://github.com/Automattic/newspack-newsletters/releases/latest/download/newspack-newsletters.zip',
			],
			'newspack-popups'               => [
				'Name'        => esc_html__( 'Newspack Campaigns', 'newspack' ),
				'Description' => esc_html__( 'AMP-compatible overlay and inline Campaigns.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://newspack.com' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'Download'    => 'https://github.com/Automattic/newspack-popups/releases/latest/download/newspack-popups.zip',
			],
			'newspack-sponsors'             => [
				'Name'        => esc_html__( 'Newspack Sponsors', 'newspack' ),
				'Description' => esc_html__( 'Sponsored and underwritten content for Newspack sites.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://newspack.com' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'Download'    => 'https://github.com/Automattic/newspack-sponsors/releases/latest/download/newspack-sponsors.zip',
			],
			'newspack-listings'             => [
				'Name'        => esc_html__( 'Newspack Listings', 'newspack' ),
				'Description' => esc_html__( 'Create reusable content in list form using the Gutenberg editor.', 'newspack' ),
				'Author'      => esc_html__( 'Automattic', 'newspack' ),
				'PluginURI'   => esc_url( 'https://newspack.com' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'Download'    => 'https://github.com/Automattic/newspack-listings/releases/latest/download/newspack-listings.zip',
			],
			'newspack-theme'                => [
				'Name'        => esc_html__( 'Newspack Theme', 'newspack' ),
				'Description' => esc_html__( 'The Newspack theme.', 'newspack' ),
				'Author'      => esc_html__( 'Newspack', 'newspack' ),
			],
			'wp-parsely'                    => [
				'Name'        => esc_html__( 'Parse.ly', 'newspack' ),
				'Description' => esc_html__( 'This plugin makes it a snap to add Parse.ly tracking code to your WordPress blog.', 'newspack' ),
				'Author'      => esc_html__( 'Mike Sukmanowsky ( mike@parsely.com )', 'newspack' ),
				'PluginURI'   => esc_url( 'https://www.parsely.com/' ),
				'AuthorURI'   => esc_url( 'https://www.parsely.com/' ),
				'Download'    => 'wporg',
			],
			'password-protected'            => [
				'Name'        => esc_html__( 'Password Protected', 'newspack' ),
				'Description' => esc_html__( 'A very simple way to quickly password protect your WordPress site with a single password. Please note: This plugin does not restrict access to uploaded files and images and does not work with some caching setups.', 'newspack' ),
				'Author'      => esc_html__( 'Ben Huson', 'newspack' ),
				'PluginURI'   => esc_url( 'https://github.com/benhuson/password-protected/' ),
				'AuthorURI'   => esc_url( 'https://github.com/benhuson/password-protected/' ),
				'Download'    => 'wporg',
				'EditPath'    => 'options-general.php?page=password-protected',
			],
			'publish-to-apple-news'         => [
				'Name'        => esc_html__( 'Publish to Apple News', 'newspack' ),
				'Description' => esc_html__( 'Export and synchronize posts to Apple format', 'newspack' ),
				'Author'      => esc_html__( 'Alley Interactive', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://www.alleyinteractive.com' ),
				'PluginURI'   => esc_url( 'https://github.com/alleyinteractive/apple-news' ),
				'Download'    => 'wporg',
				'EditPath'    => 'admin.php?page=apple_news_index',
				'Configurer'  => [
					'filename'   => 'class-publish-to-apple-news-configuration-manager.php',
					'class_name' => 'Publish_To_Apple_News_Configuration_Manager',
				],
			],
			'pwa'                           => [
				'Name'        => esc_html__( 'PWA', 'newspack' ),
				'Description' => esc_html__( 'Feature plugin to bring Progressive Web App (PWA) capabilities to Core.', 'newspack' ),
				'Author'      => esc_html__( 'PWA Plugin Contributors', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://github.com/xwp/pwa-wp/graphs/contributors' ),
				'PluginURI'   => esc_url( 'https://github.com/xwp/pwa-wp' ),
				'Download'    => 'wporg',
			],
			'recaptcha-for-woocommerce'     => [
				'Name'        => esc_html__( 'reCaptcha for WooCommerce', 'newspack' ),
				'Description' => esc_html__( 'Protect your eCommerce store from malicious and automated attacks by using reCaptcha for WooCommerce.', 'newspack' ),
				'Author'      => esc_html__( 'I13 Web Solution', 'newspack' ),
				'PluginURI'   => esc_url( 'https://woocommerce.com/products/recaptcha-for-woocommerce/' ),
				'AuthorURI'   => esc_url( 'https://woocommerce.com/vendor/i13-web-solution/' ),
			],
			'redirection'                   => [
				'Name'        => esc_html__( 'Redirection', 'newspack' ),
				'Description' => esc_html__( 'Manage all your 301 redirects and monitor 404 errors.', 'newspack' ),
				'Author'      => esc_html__( 'John Godley', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://redirection.me/' ),
				'PluginURI'   => esc_url( 'https://redirection.me/' ),
				'Download'    => 'wporg',
			],
			'woocommerce'                   => [
				'Name'        => esc_html__( 'WooCommerce', 'newspack' ),
				'Description' => esc_html__( 'An eCommerce toolkit that helps you sell anything. Beautifully.', 'newspack' ),
				'Author'      => esc_html__( 'WordPress.com VIP, XWP, Google, and contributors', 'newspack' ),
				'PluginURI'   => esc_url( 'https://woocommerce.com/' ),
				'AuthorURI'   => esc_url( 'https://woocommerce.com' ),
				'Download'    => 'wporg',
				'EditPath'    => 'admin.php?page=wc-settings',
			],
			'woocommerce-gateway-stripe'    => [
				'Name'        => esc_html__( 'WooCommerce Stripe Gateway', 'newspack' ),
				'Description' => esc_html__( 'Take credit card payments on your store using Stripe.', 'newspack' ),
				'Author'      => esc_html__( 'WooCommerce', 'newspack' ),
				'PluginURI'   => esc_url( 'https://woocommerce.com/' ),
				'AuthorURI'   => esc_url( 'https://woocommerce.com/' ),
				'Download'    => 'wporg',
				'EditPath'    => 'admin.php?page=wc-settings&tab=checkout&section=stripe',
			],
			'woocommerce-name-your-price'   => [
				'Name'        => esc_html__( 'WooCommerce Name Your Price', 'newspack' ),
				'Description' => esc_html__( 'WooCommerce Name Your Price allows customers to set their own price for products or donations.', 'newspack' ),
				'Author'      => esc_html__( 'Kathy Darling', 'newspack' ),
				'PluginURI'   => esc_url( 'https://www.woocommerce.com/products/name-your-price/' ),
				'AuthorURI'   => esc_url( 'https://kathyisawesome.com' ),
			],
			'woocommerce-subscriptions'     => [
				'Name'        => esc_html__( 'WooCommerce Subscriptions', 'newspack' ),
				'Description' => esc_html__( 'Sell products and services with recurring payments in your WooCommerce Store.', 'newspack' ),
				'Author'      => esc_html__( 'WooCommerce', 'newspack' ),
				'PluginURI'   => esc_url( 'https://woocommerce.com/products/woocommerce-subscriptions/' ),
				'AuthorURI'   => esc_url( 'https://woocommerce.com/' ),
			],
			'wordpress-seo'                 => [
				'Name'        => esc_html__( 'Yoast SEO', 'newspack' ),
				'Description' => esc_html__( 'The first true all-in-one SEO solution for WordPress, including on-page content analysis, XML sitemaps and much more.', 'newspack' ),
				'Author'      => esc_html__( 'Team Yoast', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://yoa.st/1uk' ),
				'Download'    => 'wporg',
				'EditPath'    => 'admin.php?page=wpseo_dashboard',
			],
			'wordpress-settings-discussion' => [
				'Name'     => esc_html__( 'Wordpress Commenting', 'newspack' ),
				'WPCore'   => true,
				'EditPath' => 'options-discussion.php',
			],
			'wp-gdpr-cookie-notice'         => [
				'Name'        => esc_html__( 'WP GDPR Cookie Notice', 'newspack' ),
				'Description' => esc_html__( 'Simple performant cookie consent notice that supports AMP, granular cookie control and live preview customization.', 'newspack' ),
				'Author'      => esc_html__( 'Felix Arntz', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://felix-arntz.me/' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/wp-gdpr-cookie-notice/' ),
				'Download'    => 'wporg',
			],
			'simple-local-avatars'          => [
				'Name'        => esc_html__( 'Simple Local Avatars', 'newspack' ),
				'Description' => esc_html__( 'Adds an avatar upload field to user profiles if the current user has media permissions. Generates requested sizes on demand just like Gravatar! Simple and lightweight.', 'newspack' ),
				'Author'      => esc_html__( 'Jake Goldman, 10up', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://10up.com' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/simple-local-avatars/' ),
				'Download'    => 'wporg',
			],
			'distributor-stable'            => [
				'Name'        => esc_html__( 'Distributor', 'newspack' ),
				'Description' => esc_html__( 'Distributor is a WordPress plugin that makes it easy to syndicate and reuse content across your websites — whether in a single multisite or across the web.', 'newspack' ),
				'Author'      => '10up',
				'AuthorURI'   => esc_url( 'https://10up.com' ),
				'PluginURI'   => esc_url( 'https://distributorplugin.com/' ),
				'Download'    => esc_url( 'https://github.com/10up/distributor/archive/stable.zip' ),
			],
			'super-cool-ad-inserter'        => [
				'Name'        => esc_html__( 'Super Cool Ad Inserter Plugin', 'newspack' ),
				'Description' => esc_html__( 'Display ads within your article content.', 'newspack' ),
				'Author'      => esc_html__( 'INN Labs, Automattic', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://automattic.com' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/super-cool-ad-inserter/' ),
				'Download'    => 'wporg',
			],
			'ad-refresh-control'            => [
				'Name'        => esc_html__( 'Ad Refresh Control', 'newspack' ),
				'Description' => esc_html__( 'Enable Active View refresh for Google Ad Manager ads without needing to modify any code.', 'newspack' ),
				'Author'      => esc_html__( 'Gary Thayer, David Green, 10up', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://10up.com' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/ad-refresh-control/' ),
				'Download'    => 'wporg',
			],
			'publisher-media-kit'           => [
				'Name'        => esc_html__( 'Publisher Media Kit', 'newspack' ),
				'Description' => esc_html__( 'Quick and easy option for small to medium sized publishers to digitize their media kit.', 'newspack' ),
				'Author'      => esc_html__( '10up', 'newspack' ),
				'AuthorURI'   => esc_url( 'https://10up.com' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/publisher-media-kit/' ),
				'Download'    => 'wporg',
			],
			'broadstreet'                   => [
				'Name'        => esc_html__( 'Broadstreet', 'newspack' ),
				'Description' => esc_html__( 'Integrate Broadstreet’s business directory and ad-serving features into your site.', 'newspack' ),
				'Author'      => 'Broadstreet',
				'AuthorURI'   => esc_url( 'https://broadstreetads.com/' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/broadstreet/' ),
				'Download'    => 'wporg',
			],
		];

		$default_info = [
			'Name'        => '',
			'Description' => '',
			'Author'      => '',
			'Version'     => '',
			'PluginURI'   => '',
			'AuthorURI'   => '',
			'TextDomain'  => '',
			'DomainPath'  => '',
			'Download'    => '',
			'Status'      => '',
		];

		// Add plugin status info and fill in defaults.
		foreach ( $managed_plugins as $plugin_slug => $managed_plugin ) {
			$status = self::get_managed_plugin_status( $plugin_slug );

			if ( 'newspack-theme' === $plugin_slug ) {
				if ( 'newspack-theme' === get_stylesheet() ) {
					$status = 'active';
				}
			}
			if ( isset( $managed_plugin['WPCore'] ) ) {
				$status = 'active';
			}

			$managed_plugins[ $plugin_slug ]['Status']      = $status;
			$managed_plugins[ $plugin_slug ]['Slug']        = $plugin_slug;
			$managed_plugins[ $plugin_slug ]['HandoffLink'] = isset( $managed_plugins[ $plugin_slug ]['EditPath'] ) ? admin_url( $managed_plugins[ $plugin_slug ]['EditPath'] ) : null;
			$managed_plugins[ $plugin_slug ]                = wp_parse_args( $managed_plugins[ $plugin_slug ], $default_info );
		}
		return $managed_plugins;
	}

	/**
	 * Determine a managed plugin status.
	 *
	 * @param string $plugin_slug Plugin slug.
	 */
	private static function get_managed_plugin_status( $plugin_slug ) {
		if ( Newspack::is_debug_mode() ) {
			return 'active';
		}
		$status            = 'uninstalled';
		$installed_plugins = self::get_installed_plugins();
		if ( isset( $installed_plugins[ $plugin_slug ] ) ) {
			if ( is_plugin_active( $installed_plugins[ $plugin_slug ] ) ) {
				$status = 'active';
			} else {
				$status = 'inactive';
			}
		}

		// Yoast Premium can be used as a replacement for regular Yoast.
		if ( 'wordpress-seo' === $plugin_slug && 'active' !== $status && isset( $installed_plugins['wordpress-seo-premium'] ) && $installed_plugins['wordpress-seo-premium'] ) {
			if ( is_plugin_active( $installed_plugins['wordpress-seo-premium'] ) ) {
				$status = 'active';
			};
		}

		return $status;
	}

	/**
	 * Get the list of plugins which are supported, but not managed.
	 * These plugins will not be added to the WP Admin plugins screen,
	 * but installing them will not raise any issues in Health Check.
	 */
	private static function get_supported_plugins_slugs() {
		return [
			'amp',
			'gutenberg',
			'classic-widgets',
			'republication-tracker-tool',
			'the-events-calendar',
			'wordpress-seo-premium',
			'gravityformspolls',
			'gravityformsmailchimp',
			'gravityformsstripe',
			'perfmatters',
			'onesignal-free-web-push-notifications',
			'web-stories',
			'ads-txt',
			'woocommerce-memberships',
		];
	}

	/**
	 * Get info about all the unmanaged plugins that are installed.
	 *
	 * @return array of plugin info.
	 */
	public static function get_unsupported_plugins() {
		$plugins_info            = self::get_installed_plugins_info();
		$managed_plugins         = self::get_managed_plugins();
		$supported_plugins_slugs = self::get_supported_plugins_slugs();
		$unmanaged_plugins       = [];
		foreach ( $plugins_info as $slug => $info ) {
			$is_managed      = ! isset( $managed_plugins[ $slug ] );
			$is_not_newspack = 0 !== strpos( $slug, 'newspack-' );
			$is_active       = is_plugin_active( $info['Path'] );
			$is_supported    = in_array( $slug, $supported_plugins_slugs );
			if ( ! $is_supported && $is_managed && $is_not_newspack && $is_active ) {
				$unmanaged_plugins[ $slug ] = $info;
			}
		}
		return $unmanaged_plugins;
	}

	/**
	 * Get info about all the required plugins that aren't installed and active.
	 *
	 * @return array of plugin info.
	 */
	public static function get_missing_plugins() {
		$managed_plugins = self::get_managed_plugins();
		$plugins_info    = self::get_installed_plugins_info();
		$missing_plugins = array();
		foreach ( self::$required_plugins as $slug ) {
			if ( 'active' !== self::get_managed_plugin_status( $slug ) ) {
				$missing_plugins[ $slug ] = $managed_plugins[ $slug ];
			}
		}
		return $missing_plugins;
	}

	/**
	 * Determine whether plugin installation is allowed in the current environment.
	 *
	 * @return bool
	 */
	public static function can_install_plugins() {
		if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ||
			( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Activate a plugin, installing it first if necessary.
	 *
	 * @param string $plugin The plugin slug or URL to the plugin.
	 * @return bool True on success. False on failure or if plugin was already activated.
	 */
	public static function activate( $plugin ) {
		if ( 'newspack-theme' === $plugin ) {
			return Theme_Manager::install_activate_theme();
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_slug = self::get_plugin_slug( $plugin );
		if ( ! $plugin_slug ) {
			return new WP_Error( 'newspack_invalid_plugin', __( 'Invalid plugin.', 'newspack' ) );
		}

		$installed_plugins = self::get_installed_plugins();

		// Install the plugin if it's not installed already.
		$plugin_installed = isset( $installed_plugins[ $plugin_slug ] );
		if ( ! $plugin_installed ) {
			$plugin_installed = self::install( $plugin );
		}
		if ( is_wp_error( $plugin_installed ) ) {
			return $plugin_installed;
		}

		// Refresh the installed plugin list if the plugin isn't present because we just installed it.
		if ( ! isset( $installed_plugins[ $plugin_slug ] ) ) {
			$installed_plugins = self::get_installed_plugins();
		}

		if ( is_plugin_active( $installed_plugins[ $plugin_slug ] ) ) {
			return new WP_Error( 'newspack_plugin_already_active', __( 'The plugin is already active.', 'newspack' ) );
		}

		$activated = activate_plugin( $installed_plugins[ $plugin_slug ] );
		if ( is_wp_error( $activated ) ) {
			return new WP_Error( 'newspack_plugin_failed_activation', $activated->get_error_message() );
		}

		return true;
	}

	/**
	 * Deactivate a plugin.
	 *
	 * @param string $plugin The plugin slug (e.g. 'newspack') or path to the plugin file. e.g. ('newspack/newspack.php').
	 * @return bool True on success. False on failure.
	 */
	public static function deactivate( $plugin ) {
		$installed_plugins = self::get_installed_plugins();
		if ( ! in_array( $plugin, $installed_plugins ) && ! isset( $installed_plugins[ $plugin ] ) ) {
			return new WP_Error( 'newspack_plugin_not_installed', __( 'The plugin is not installed.', 'newspack' ) );
		}

		if ( isset( $installed_plugins[ $plugin ] ) ) {
			$plugin_file = $installed_plugins[ $plugin ];
		} else {
			$plugin_file = $plugin;
		}

		if ( ! is_plugin_active( $plugin_file ) ) {
			return new WP_Error( 'newspack_plugin_not_active', __( 'The plugin is not active.', 'newspack' ) );
		}

		deactivate_plugins( $plugin_file );
		if ( is_plugin_active( $plugin_file ) ) {
			return new WP_Error( 'newspack_plugin_failed_deactivation', __( 'Failed to deactivate plugin.', 'newspack' ) );
		}
		return true;
	}

	/**
	 * Get a simple list of all installed plugins.
	 *
	 * @return array of 'plugin_slug => plugin_file_path' entries for all installed plugins.
	 */
	public static function get_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array_reduce( array_keys( get_plugins() ), array( __CLASS__, 'reduce_plugin_info' ) );
		$themes  = array_reduce( array_keys( wp_get_themes() ), array( __CLASS__, 'reduce_plugin_info' ) );
		return array_merge( $plugins, $themes );
	}

	/**
	 * Get a list of all installed plugins, with complete info for each.
	 *
	 * @return array of 'plugin_slug => []' entries for all installed plugins.
	 */
	public static function get_installed_plugins_info() {
		$plugins = array_merge( get_plugins(), wp_get_themes() );

		$installed_plugins_info = [];
		foreach ( self::get_installed_plugins() as $key => $path ) {
			$installed_plugins_info[ $key ]         = $plugins[ $path ];
			$installed_plugins_info[ $key ]['Path'] = $path;
		}
		return $installed_plugins_info;
	}

	/**
	 * Parse a plugin slug from the slug or URL to download a plugin.
	 *
	 * @param string $plugin A plugin slug or the URL to a plugin zip file.
	 * @return string|bool Parsed slug on success. False on failure.
	 */
	public static function get_plugin_slug( $plugin ) {
		if ( ! is_string( $plugin ) || empty( $plugin ) ) {
			return false;
		}

		$url = wp_http_validate_url( $plugin );

		// A plugin slug was passed in, so just return it.
		if ( ! $url ) {
			return $plugin;
		}

		if ( ! stripos( $url, '.zip' ) ) {
			return false;
		}

		$result = preg_match_all( '/\/([^\.\/*]+)/', $url, $matches );
		if ( ! $result ) {
			return false;
		}

		$group = end( $matches );
		$slug  = end( $group );
		return $slug;
	}

	/**
	 * Installs a plugin.
	 *
	 * @param string $plugin Plugin slug or URL to plugin zip file.
	 * @return bool True on success. False on failure.
	 */
	public static function install( $plugin ) {
		if ( ! self::can_install_plugins() ) {
			return new WP_Error( 'newspack_plugin_failed_install', __( 'Plugins cannot be installed.', 'newspack' ) );
		}

		if ( wp_http_validate_url( $plugin ) ) {
			return self::install_from_url( $plugin );
		} else {
			return self::install_from_slug( $plugin );
		}
	}

	/**
	 * Uninstall a plugin.
	 *
	 * @param string|array $plugin The plugin slug (e.g. 'newspack') or path to the plugin file. e.g. ('newspack/newspack.php'), or an array thereof.
	 * @return bool True on success. False on failure.
	 */
	public static function uninstall( $plugin ) {
		if ( ! self::can_install_plugins() ) {
			return new WP_Error( 'newspack_plugin_failed_uninstall', __( 'Plugins cannot be uninstalled.', 'newspack' ) );
		}

		$plugins_to_uninstall = [];
		$installed_plugins    = self::get_installed_plugins();

		if ( ! is_array( $plugin ) ) {
			$plugin = [ $plugin ];
		}

		foreach ( $plugin as $plugin_slug ) {
			if ( ! in_array( $plugin_slug, $installed_plugins ) && ! isset( $installed_plugins[ $plugin_slug ] ) ) {
				return new WP_Error( 'newspack_plugin_failed_uninstall', __( 'The plugin is not installed.', 'newspack' ) );
			}

			if ( isset( $installed_plugins[ $plugin_slug ] ) ) {
				$plugin_file = $installed_plugins[ $plugin_slug ];
			} else {
				$plugin_file = $plugin_slug;
			}

			// Deactivate plugin before uninstalling.
			self::deactivate( $plugin_file );

			$plugins_to_uninstall[] = $plugin_file;
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$success = (bool) delete_plugins( $plugins_to_uninstall );
		if ( $success ) {
			wp_clean_plugins_cache();
			return true;
		}
		return new WP_Error( 'newspack_plugin_failed_uninstall', __( 'The plugin could not be uninstalled.', 'newspack' ) );
	}

	/**
	 * Install a plugin by slug.
	 *
	 * @param string $plugin_slug The slug for the plugin.
	 * @return Mixed True on success. WP_Error on failure.
	 */
	protected static function install_from_slug( $plugin_slug ) {
		// Quick check to make sure plugin directory doesn't already exist.
		$plugin_directory = WP_PLUGIN_DIR . '/' . $plugin_slug;
		if ( is_dir( $plugin_directory ) ) {
			return new WP_Error( 'newspack_plugin_already_installed', __( 'The plugin directory already exists.', 'newspack' ) );
		}

		$managed_plugins = self::get_managed_plugins();
		if ( ! isset( $managed_plugins[ $plugin_slug ] ) ) {
			return new WP_Error(
				'newspack_plugin_failed_install',
				__( 'Plugin not found.', 'newspack' )
			);
		}

		// Return a useful error if we are unable to get download info for the plugin.
		if ( empty( $managed_plugins[ $plugin_slug ]['Download'] ) ) {
			$error_message = __( 'Newspack cannot install this plugin. You will need to get it from the plugin\'s site and install it manually.', 'newspack' );
			if ( ! empty( $managed_plugins[ $plugin_slug ]['PluginURI'] ) ) {
				/* translators: %s: plugin URL */
				$error_message = sprintf( __( 'Newspack cannot install this plugin. You will need to get it from <a href="%s">the plugin\'s site</a> and install it manually.', 'newspack' ), esc_url( $managed_plugins[ $plugin_slug ]['PluginURI'] ) );
			}

			return new WP_Error(
				'newspack_plugin_failed_install',
				$error_message
			);
		}

		// If the plugin has a URL as its Download, install it from there.
		if ( wp_http_validate_url( $managed_plugins[ $plugin_slug ]['Download'] ) ) {
			return self::install_from_url( $managed_plugins[ $plugin_slug ]['Download'] );
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		// Check WP.org for a download link, and install it from WP.org.
		$plugin_info = plugins_api(
			'plugin_information',
			[
				'slug'   => $plugin_slug,
				'fields' => [
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				],
			]
		);

		if ( is_wp_error( $plugin_info ) ) {
			return new WP_Error( 'newspack_plugin_failed_install', $plugin_info->get_error_message() );
		}

		return self::install_from_url( $plugin_info->download_link );
	}

	/**
	 * Install a plugin from an arbitrary URL.
	 *
	 * @param string $plugin_url The URL to the plugin zip file.
	 * @return bool True on success. False on failure.
	 */
	protected static function install_from_url( $plugin_url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		WP_Filesystem();

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \WP_Upgrader( $skin );
		$upgrader->init();

		$download = $upgrader->download_package( $plugin_url );
		if ( is_wp_error( $download ) ) {
			return new WP_Error( 'newspack_plugin_failed_install', $download->get_error_message() );
		}

		// GitHub appends random strings to the end of its downloads.
		// If we asked for foo.zip, make sure the downloaded file is called foo.tmp.
		if ( stripos( $plugin_url, 'github' ) ) {
			$plugin_url_parts  = explode( '/', $plugin_url );
			$desired_file_name = str_replace( '.zip', '', end( $plugin_url_parts ) );
			$new_file_name     = preg_replace( '#(' . $desired_file_name . '.*).tmp#', $desired_file_name . '.tmp', $download );
			rename( $download, $new_file_name ); // phpcs:ignore
			$download = $new_file_name;
		}

		$working_dir = $upgrader->unpack_package( $download );
		if ( is_wp_error( $working_dir ) ) {
			return new WP_Error( 'newspack_plugin_failed_install', $working_dir->get_error_message() );
		}

		$result = $upgrader->install_package(
			[
				'source'        => $working_dir,
				'destination'   => WP_PLUGIN_DIR,
				'clear_working' => true,
				'hook_extra'    => [
					'type'   => 'plugin',
					'action' => 'install',
				],
			]
		);
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'newspack_plugin_failed_install', $result->get_error_message() );
		}

		wp_clean_plugins_cache();
		return true;
	}

	/**
	 * Reduce get_plugins() info to form 'folder => file'.
	 *
	 * @param array  $plugins Associative array of plugin files to paths.
	 * @param string $key Plugin relative path. Example: newspack/newspack.php.
	 * @return array
	 */
	private static function reduce_plugin_info( $plugins, $key ) {
		$path   = explode( '/', $key );
		$folder = current( $path );

		// Strip version info from key. (e.g. 'woocommerce-stripe-gateway-4.1.2' should just be 'woocommerce-stripe-gateway').
		$folder = preg_replace( '/[\-0-9\.]+$/', '', $folder );

		$plugins[ $folder ] = $key;
		return $plugins;
	}
}
