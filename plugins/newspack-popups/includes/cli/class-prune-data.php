<?php
/**
 * Campaigns Prune Data CLI command.
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\CLI;

use \WP_CLI;
use \WP_CLI_Command;
use Newspack_Popups_Segmentation;

/**
 * Campaigns Export CLI command.
 */
class Prune_Data extends WP_CLI_Command {

	/**
	 * Run the data prune job.
	 */
	public function __invoke() {
		WP_CLI::log( __( 'Starting data pruning', 'newspack-popups' ) );
		Newspack_Popups_Segmentation::prune_data();
		WP_CLI::success( __( 'Completed data pruning.', 'newspack-popups' ) );
	}
}
