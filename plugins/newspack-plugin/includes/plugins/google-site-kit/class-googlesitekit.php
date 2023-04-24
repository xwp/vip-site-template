<?php
/**
 * Google Site Kit integration class.
 *
 * @package Newspack
 */

namespace Newspack;

use Google\Site_Kit\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class GoogleSiteKit {
	const GA4_SETUP_DONE_OPTION_NAME = 'newspack_analytics_has_set_up_ga4';

	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'setup_sitekit_ga4' ] );
		add_action( 'wp_footer', [ __CLASS__, 'insert_ga4_analytics' ] );
		add_filter( 'option_googlesitekit_analytics_settings', [ __CLASS__, 'filter_ga_settings' ] );
	}

	/**
	 * Filter GA settings.
	 *
	 * @param array $googlesitekit_analytics_settings GA settings.
	 */
	public static function filter_ga_settings( $googlesitekit_analytics_settings ) {
		if ( Reader_Activation::is_enabled() ) {
			// If RA is enabled, readers will become logged in users. They should still be tracked in GA.
			if ( in_array( 'loggedinUsers', $googlesitekit_analytics_settings['trackingDisabled'] ) ) {
				$googlesitekit_analytics_settings['trackingDisabled'] = [ 'contentCreators' ];
			}
		}
		return $googlesitekit_analytics_settings;
	}

	/**
	 * Add GA4 analytics pageview reporting to AMP pages.
	 */
	public static function insert_ga4_analytics() {
		if ( ! function_exists( 'is_amp_endpoint' ) || ! is_amp_endpoint() ) {
			return;
		}
		$sitekit_ga4_settings = self::get_sitekit_ga4_settings();
		if ( false === $sitekit_ga4_settings || ! $sitekit_ga4_settings['useSnippet'] || ! isset( $sitekit_ga4_settings['measurementID'] ) ) {
			return;
		}
		$ga4_measurement_id = $sitekit_ga4_settings['measurementID'];
		// See https://github.com/analytics-debugger/google-analytics-4-for-amp.
		$config_path = Newspack::plugin_url() . '/includes/raw_assets/ga4.json';

		?>
			<amp-analytics type="googleanalytics" config="<?php echo esc_attr( $config_path ); ?>" data-credentials="include">
				<script type="application/json">
					{
						"vars": {
							"GA4_MEASUREMENT_ID": "<?php echo esc_attr( $ga4_measurement_id ); ?>",
							"DEFAULT_PAGEVIEW_ENABLED": true,
							"GOOGLE_CONSENT_ENABLED": false
						}
					}
				</script>
			</amp-analytics>
		<?php
	}

	/**
	 * Get whether the current user is connected.
	 *
	 * @return bool Whether the user is connected to Google through Site Kit.
	 */
	private static function is_user_connected() {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return ! empty( get_user_meta( $user_id, $wpdb->prefix . 'googlesitekit_site_verified_meta', true ) );
	}

	/**
	 * Get the name of the option under which Site Kit's GA4 settings are stored.
	 */
	private static function get_sitekit_ga4_settings_option_name() {
		if ( class_exists( '\Google\Site_Kit\Modules\Analytics_4\Settings' ) ) {
			return \Google\Site_Kit\Modules\Analytics_4\Settings::OPTION;
		}
		return false;
	}

	/**
	 * Get Site Kit's GA4 settings.
	 */
	private static function get_sitekit_ga4_settings() {
		$option_name = self::get_sitekit_ga4_settings_option_name();
		if ( false === $option_name ) {
			return false;
		}
		return get_option( $option_name, false );
	}

	/**
	 * Fetch data for the GA account data and set up GA4.
	 */
	public static function setup_sitekit_ga4() {
		if ( ! class_exists( 'Google\Site_Kit\Core\Modules\Module' ) ) {
			return;
		}
		require_once NEWSPACK_ABSPATH . 'includes/plugins/google-site-kit/class-googlesitekitanalytics.php';

		if ( ! self::is_user_connected() ) {
			return;
		}
		if ( get_option( self::GA4_SETUP_DONE_OPTION_NAME, false ) ) {
			return;
		}

		$sitekit_ga4_settings = self::get_sitekit_ga4_settings();
		if ( false !== $sitekit_ga4_settings && $sitekit_ga4_settings['useSnippet'] && isset( $sitekit_ga4_settings['measurementID'] ) ) {
			return;
		}

		if ( ! defined( 'GOOGLESITEKIT_PLUGIN_MAIN_FILE' ) ) {
			return;
		}

		$sitekit_ga_settings = get_option( \Google\Site_Kit\Modules\Analytics\Settings::OPTION, false );
		if ( false === $sitekit_ga_settings || ! isset( $sitekit_ga_settings['accountID'] ) ) {
			return;
		}
		$account_id = $sitekit_ga_settings['accountID'];

		try {
			$newspack_ga  = new GoogleSiteKitAnalytics( new Context( GOOGLESITEKIT_PLUGIN_MAIN_FILE ) );
			$ga4_settings = $newspack_ga->get_ga4_settings( $account_id );
			if ( false === $ga4_settings ) {
				return;
			}
			$ga4_settings['ownerID']    = get_current_user_id();
			$ga4_settings['useSnippet'] = true;

			$sitekit_ga4_option_name = self::get_sitekit_ga4_settings_option_name();
			Logger::log( 'Updating Site Kit GA4 settings option.' );
			update_option( self::GA4_SETUP_DONE_OPTION_NAME, true, true );
			update_option( $sitekit_ga4_option_name, $ga4_settings, true );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed updating Site Kit GA4 settings option: ' . $e->getMessage() );
		}
	}
}
GoogleSiteKit::init();
