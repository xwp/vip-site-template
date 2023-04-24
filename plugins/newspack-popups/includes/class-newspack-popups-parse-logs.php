<?php
/**
 * Newspack Segmentation Plugin
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/../api/segmentation/class-segmentation.php';
require_once dirname( __FILE__ ) . '/../api/classes/class-lightweight-api.php';
require_once dirname( __FILE__ ) . '/../api/campaigns/class-campaign-data-utils.php';

/**
 * Main Newspack Segmentation Plugin Class.
 */
final class Newspack_Popups_Parse_Logs {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Parse_Logs
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Segmentation Plugin Instance.
	 * Ensures only one instance of Newspack Segmentation Plugin Instance is loaded or can be loaded.
	 *
	 * @return Newspack Segmentation Plugin Instance - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( file_exists( Segmentation::get_log_file_path() ) ) {
			add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
			add_action( 'newspack_popups_segmentation_cron_hook', [ __CLASS__, 'parse_events_logs' ] );
			if ( ! wp_next_scheduled( 'newspack_popups_segmentation_cron_hook' ) ) {
				wp_schedule_event( time(), 'every_quarter_hour', 'newspack_popups_segmentation_cron_hook' );
			}
		}
	}

	/**
	 * Add a CRON interval of one minute.
	 *
	 * @param object $schedules The schedules.
	 */
	public static function add_cron_interval( $schedules ) {
		$schedules['every_quarter_hour'] = [
			'interval' => 60 * 15,
			'display'  => esc_html__( 'Every Quarter-Hour' ),
		];
		return $schedules;
	}

	/**
	 * Parse the log file, write data to the DB, and remove the file.
	 */
	public static function parse_events_logs() {
		$nonce = \wp_create_nonce( 'newspack_campaigns_lightweight_api' );
		$api   = \Campaign_Data_Utils::get_api( $nonce );
		$api->parse_event_logs();
	}
}
Newspack_Popups_Parse_Logs::instance();
