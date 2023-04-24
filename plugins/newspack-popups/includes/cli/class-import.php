<?php
/**
 * Campaigns Import CLI command.
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\CLI;

use \WP_CLI;
use \WP_CLI_Command;
use \WP_Error;
use Newspack_Popups_Importer;

/**
 * Campaigns Import CLI command.
 */
class Import extends WP_CLI_Command {

	/**
	 * Import Campaigns, Segments and Prompts from a JSON file generated with the newspac-popups export command.
	 *
	 * If the input file contains categories or tags that are not present in the site, the importer will ask you how to proceed.
	 * Use the --ignore-terms flag to ignore all the missing terms and skip the user interaction.
	 * Use the --create-terms flag to create all the missing terms automatically and skip the user interaction.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the input file.
	 *
	 * [--ignore-terms]
	 * : Ignore categories and tags that are not present in the site.
	 *
	 * [--create-terms]
	 * : Create categories and tags that are not present in the site.
	 *
	 * [--ras-defaults]
	 * : Import from the RAS defaults preset located at presets/ras-defaults.json. This will ignore the <file> argument.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-popups import newspack-campaigns.json
	 *
	 * @param array $args Args.
	 * @param array $assoc_args Assoc args.
	 */
	public function __invoke( $args, $assoc_args ) {
		$ignore_terms = WP_CLI\Utils\get_flag_value( $assoc_args, 'ignore-terms', false );
		$create_terms = WP_CLI\Utils\get_flag_value( $assoc_args, 'create-terms', false );
		$ras_defaults = WP_CLI\Utils\get_flag_value( $assoc_args, 'ras-defaults', false );
		$file         = $args[0] ?? null;

		if ( $ras_defaults ) {
			$file = dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/presets/ras-defaults.json';
		}

		if ( ! $file ) {
			WP_CLI::error( __( 'You must either supply a json file path or use the --ras-defaults flag.', 'newspack-popups' ) );
		}

		if ( ! is_readable( $file ) ) {
			WP_CLI::error( __( 'File not found or not readable.', 'newspack-popups' ) );
		}

		$data = wp_json_file_decode( $file, [ 'associative' => true ] ); // phpcs:ignore
		if ( empty( $data ) ) {
			WP_CLI::error( __( 'File is not valid JSON.', 'newspack-popups' ) );
		}

		WP_CLI::success( __( 'JSON file loaded, initializing the importer...', 'newspack-popups' ) );

		$importer = new Newspack_Popups_Importer( $data );

		$missing_terms = $importer->get_missing_terms_from_input();

		if ( ! $ignore_terms && ! empty( $missing_terms ) ) {
			foreach ( $missing_terms as $tax => $terms ) {
				foreach ( $terms as $term ) {
					$done = false;
					// If term creation fails, or if the user cancels the selection, we will reset the strategy and ask again, that's why we have this while loop.
					while ( ! $done ) {
						$question = sprintf(
							// translators: %1$s is the name of the term, %2$s is the taxonomy (category or tag).
							__( 'The "%1$s" %2$s does not exist in this site. How do you want to proceed?', 'newspack-popups' ),
							$term['name'],
							$this->tax_label( $tax )
						);

						if ( ! $create_terms ) {
							WP_CLI::warning( $question );
							$strategy = $this->choose_term_strategy();
						} else {
							$strategy = 1;
							$done     = true; // avoid infinte loop in case of error.
						}

						if ( 1 == $strategy ) { // Create term.

							$new_id = $this->create_term( $term['name'], $tax );
							if ( is_wp_error( $new_id ) ) {
								WP_CLI::error( $new_id->get_error_message(), false );
								// Dont set done and ask for the strategy again.
							} else {
								WP_CLI::success( __( 'Term created', 'newspack-popups' ) );
								$done = true;
							}
						} elseif ( 2 == $strategy ) { // Map to existing term.

							$new_id = $this->choose_existing_term( $tax );
							if ( ! is_wp_error( $new_id ) ) {
								WP_CLI::success( __( 'Term selected', 'newspack-popups' ) );
								$importer->add_term_mapping( $term['id'], $new_id );
								$done = true;
							} // else means the user aborted the selection. Dont set done and ask for the strategy again.

						} elseif ( 3 === $strategy ) { // Ignore term (do nothing).

							WP_CLI::success( __( 'Ignoring', 'newspack-popups' ) );
							$done = true;

						}
					}
				}
			}
		}

		WP_CLI::log( __( 'Running the importer', 'newspack-popups' ) );
		$result = $importer->import();

		if ( ! empty( $result['errors'] ) && ! empty( $result['errors']['validation'] ) ) {
			WP_CLI::warning( 'JSON could not be validated.' );
			foreach ( $result['errors']['validation'] as $message ) {
				WP_CLI::warning( $message );
			}
			WP_CLI::error( __( 'Could not import JSON file.', 'newspack-popups' ) );
		}

		foreach ( $result['errors'] as $error_type => $error_group ) {
			if ( empty( $error_group ) ) {
				continue;
			}
			WP_CLI::warning( $error_type );
			foreach ( $error_group as $error_id => $error ) {
				WP_CLI::warning( '-- ' . $error_id );
				foreach ( $error as $message ) {
					WP_CLI::warning( '---- ' . $message );
				}
			}
		}

		// translators: %d is the number of campaigns imported.
		WP_CLI::success( sprintf( _n( '%d campaign imported', '%d campaigns imported', $result['totals']['campaigns'], 'newspack-popups' ), $result['totals']['campaigns'] ) );
		// translators: %d is the number of segments imported.
		WP_CLI::success( sprintf( _n( '%d segment imported', '%d segments imported', $result['totals']['segments'], 'newspack-popups' ), $result['totals']['segments'] ) );
		// translators: %d is the number of prompts imported.
		WP_CLI::success( sprintf( _n( '%d prompt imported', '%d prompts imported', $result['totals']['prompts'], 'newspack-popups' ), $result['totals']['prompts'] ) );
		WP_CLI::success( __( 'Import complete!', 'newspack-popups' ) );

	}

	/**
	 * Gets a taxonomy label.
	 *
	 * @param string $tax Taxonomy slug.
	 * @return string
	 */
	private function tax_label( $tax ) {
		$labels = [
			'category' => __( 'category', 'newspack-popups' ),
			'post_tag' => __( 'tag', 'newspack-popups' ),
		];
		return $labels[ $tax ];
	}

	/**
	 * Prompts the user to choose the strategy to handle a missing term.
	 *
	 * @return int The user's choice.
	 */
	private function choose_term_strategy() {
		WP_CLI::line( __( '1) Create it', 'newspack-popups' ) );
		WP_CLI::line( __( '2) Choose an existing one', 'newspack-popups' ) );
		WP_CLI::line( __( '3) Ignore', 'newspack-popups' ) );
		$prompt  = __( 'Enter your choice (1, 2 or 3):', 'newspack-popups' ) . ' ';
		$choices = [ 1, 2, 3 ];
		$choice  = 0;
		while ( ! in_array( $choice, $choices, true ) ) {
			$choice = (int) readline( $prompt );
			if ( ! in_array( $choice, $choices, true ) ) {
				WP_CLI::warning( __( 'Invalid choice. Please try again.', 'newspack-popups' ) );
			}
		}
		return $choice;
	}

	/**
	 * Prompts the user to choose an existing term
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return int|WP_Error The term ID, or a WP_Error object if the process is cancelled.
	 */
	private function choose_existing_term( $taxonomy ) {
		// translators: %s is the taxonomy (category or tag).
		$prompt  = sprintf( __( 'Enter the existing %s name you want to use (or "c" to cancel):', 'newspack-popups' ) . ' ', $this->tax_label( $taxonomy ) );
		$term_id = false;
		while ( ! $term_id ) {
			$term_name = readline( $prompt );

			if ( 'c' === strtolower( $term_name ) ) {
				return new WP_Error( 'cancelled' );
			}

			$search = get_term_by( 'name', $term_name, $taxonomy );
			if ( is_a( $search, 'WP_Term' ) ) {
				$term_id = $search->term_id;
			} else {
				WP_CLI::warning( __( 'No term found with that name. Please try again.', 'newspack-popups' ) );
			}
		}
		return $term_id;
	}

	/**
	 * Create a term.
	 *
	 * @param string $name The term name.
	 * @param string $taxonomy The term taxonomy.
	 * @return int|WP_Error The term ID or a WP_Error object.
	 */
	private function create_term( $name, $taxonomy ) {
		$created = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			if ( 'term_exists' === $created->get_error_code() ) {
				// translators: %s is the taxonomy (category or tag).
				WP_CLI::warning( sprintf( __( 'This %s already exists. Using it instead.', 'newspack-popups' ), $this->tax_label( $tax ) ) );
				return $created->get_error_data();
			} else {
				return $created;
			}
		}
		return $created['term_id'];
	}
}
