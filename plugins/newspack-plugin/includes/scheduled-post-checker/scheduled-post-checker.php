<?php
/**
 * Newspack Scheduled Post Checker
 * Checks to make sure posts haven't missed their schedule, and publishes them if needed.
 *
 * @package Newspack
 */

namespace Newspack\Scheduled_Post_Checker;

defined( 'ABSPATH' ) || exit;
define( 'NEWSPACK_SCHEDULED_POST_CHECKER_CRON_HOOK', 'newspack_scheduled_post_checker' );

/**
 * Set up the checking.
 */
function nspc_init() {
	add_action( 'newspack_deactivation', '\Newspack\Scheduled_Post_Checker\nspc_deactivate' );
	if ( ! wp_next_scheduled( NEWSPACK_SCHEDULED_POST_CHECKER_CRON_HOOK ) ) {
		wp_schedule_event( time(), 'fivemins', NEWSPACK_SCHEDULED_POST_CHECKER_CRON_HOOK );
	}
}
add_action( 'init', __NAMESPACE__ . '\nspc_init' );

/**
 * Clear the cron job when this plugin is deactivated.
 */
function nspc_deactivate() {
	wp_clear_scheduled_hook( NEWSPACK_SCHEDULED_POST_CHECKER_CRON_HOOK );
}

/**
 * Check to see if any posts have missed schedule, and try sending them live again if so.
 */
function nspc_run_check() {
	$time = wp_date( 'Y-m-d H:i:s' );

	$posts_with_missed_schedule = get_posts(
		[
			'post_status' => 'future',
			'post_type'   => 'any',
			'fields'      => 'ids',
			'date_query'  => [
				[
					'before'    => $time,
					'inclusive' => false,
				],
			],
		]
	);

	foreach ( $posts_with_missed_schedule as $post_id ) {
		check_and_publish_future_post( $post_id );
	}
}
add_action( NEWSPACK_SCHEDULED_POST_CHECKER_CRON_HOOK, __NAMESPACE__ . '\nspc_run_check' );

/**
 * Add a cron interval for every five minutes.
 *
 * @param array $schedules Defined cron schedules.
 * @return array Modified $schedules.
 */
function nspc_add_cron_schedule( $schedules ) {
	$schedules['fivemins'] = [
		'interval' => MINUTE_IN_SECONDS * 5,
		'display'  => 'Every 5 minutes',
	];
	return $schedules;
}
add_filter( 'cron_schedules', __NAMESPACE__ . '\nspc_add_cron_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- https://github.com/WordPress/WordPress-Coding-Standards/issues/1865
