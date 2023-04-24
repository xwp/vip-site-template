<?php
/**
 * Newspack Pop-ups Configuration Manager
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ABSPATH . '/includes/configuration_managers/class-configuration-manager.php';

/**
 * Provide an interface for configuring and querying the configuration of Newspack Pop-ups.
 */
class Newspack_Popups_Configuration_Manager extends Configuration_Manager {

	/**
	 * The slug of the plugin.
	 *
	 * @var string
	 */
	public $slug = 'newspack-popups';

	/**
	 * Get whether the Newspack Popups plugin is active and set up.
	 *
	 * @return bool Whether Newspack Popups is installed and activated.
	 */
	public function is_configured() {
		return class_exists( 'Newspack_Popups_Model' );
	}

	/**
	 * Retrieve all prompt CPTs
	 *
	 * @param  boolean $include_unpublished Whether to include unpublished posts.
	 * @param  boolean $include_trash Whether to include trashed posts.
	 * @return array All prompts
	 */
	public function get_prompts( $include_unpublished = false, $include_trash = false ) {
		return $this->is_configured() ?
			\Newspack_Popups_Model::retrieve_popups( $include_unpublished, $include_trash ) :
			$this->unconfigured_error();
	}

	/**
	 * Set taxonomy terms for a Popup.
	 *
	 * @param integer $id ID of popup.
	 * @param array   $terms Array of terms to be set.
	 * @param string  $taxonomy Taxonomy slug.
	 */
	public function set_popup_terms( $id, $terms, $taxonomy ) {
		return $this->is_configured() ?
			\Newspack_Popups_Model::set_popup_terms( $id, $terms, $taxonomy ) :
			$this->unconfigured_error();
	}

	/**
	 * Set Popup options.
	 *
	 * @param integer $id ID of popup.
	 * @param array   $options Array of categories to be set.
	 */
	public function set_popup_options( $id, $options ) {
		return $this->is_configured() ?
			\Newspack_Popups_Model::set_popup_options( $id, $options ) :
			$this->unconfigured_error();
	}

	/**
	 * Get plugin settings.
	 */
	public function get_settings() {
		return $this->is_configured() ?
			\Newspack_Popups_Settings::get_settings( true ) :
			$this->unconfigured_error();
	}

	/**
	 * Get segments.
	 */
	public function get_segments() {
		return $this->is_configured() ?
			\Newspack_Popups_Segmentation::get_segments() :
			$this->unconfigured_error();
	}

	/**
	 * Get custom placements.
	 */
	public function get_custom_placements() {
		return $this->is_configured() ?
			\Newspack_Popups_Custom_Placements::get_custom_placements() :
			$this->unconfigured_error();
	}

	/**
	 * Get custom placement values as a simple array.
	 */
	public function get_custom_placement_values() {
		return $this->is_configured() ?
			\Newspack_Popups_Custom_Placements::get_custom_placement_values() :
			$this->unconfigured_error();
	}

	/**
	 * Get overlay placements.
	 */
	public function get_overlay_placements() {
		return $this->is_configured() ?
			\Newspack_Popups_Model::get_overlay_placements() :
			$this->unconfigured_error();
	}

	/**
	 * Get overlay sizes.
	 */
	public function get_overlay_sizes() {
		return $this->is_configured() ?
			\Newspack_Popups_Model::get_popup_size_options() :
			$this->unconfigured_error();
	}

	/**
	 * Update plugin settings section.
	 *
	 * @param object $options options.
	 */
	public function update_settings_section( $options ) {
		return $this->is_configured() ?
			\Newspack_Popups_Settings::update_section( $options ) :
			$this->unconfigured_error();
	}

	/**
	 * Create a segment.
	 *
	 * @param object $segment Segment configuration.
	 */
	public function create_segment( $segment ) {
		return $this->is_configured() ?
			\Newspack_Popups_Segmentation::create_segment( $segment ) :
			$this->unconfigured_error();
	}

	/**
	 * Update a segment.
	 *
	 * @param object $segment Segment configuration.
	 */
	public function update_segment( $segment ) {
		return $this->is_configured() ?
			\Newspack_Popups_Segmentation::update_segment( $segment ) :
			$this->unconfigured_error();
	}

	/**
	 * Delete a segment.
	 *
	 * @param string $id A segment ID.
	 */
	public function delete_segment( $id ) {
		return $this->is_configured() ?
			\Newspack_Popups_Segmentation::delete_segment( $id ) :
			$this->unconfigured_error();
	}

	/**
	 * Get segment's potential reach.
	 *
	 * @param object $config Segment configuration.
	 */
	public function get_segment_reach( $config ) {
		return $this->is_configured() ?
			\Newspack_Popups_Segmentation::get_segment_reach( $config ) :
			$this->unconfigured_error();
	}

	/**
	 * Sort all segments.
	 *
	 * @param object $segment_ids Sorted array of segment IDs.
	 */
	public function sort_segments( $segment_ids ) {
		return $this->is_configured() ?
			\Newspack_Popups_Segmentation::sort_segments( $segment_ids ) :
			$this->unconfigured_error();
	}

	/**
	 * Activate campaign group.
	 *
	 * @param int $id Campaign group ID.
	 */
	public function batch_publish( $id ) {
		return $this->is_configured() ?
			\Newspack_Popups_Settings::batch_publish( $id ) :
			$this->unconfigured_error();
	}

	/**
	 * Deactivate campaign group.
	 *
	 * @param int $id Campaign group ID.
	 */
	public function batch_unpublish( $id ) {
		return $this->is_configured() ?
			\Newspack_Popups_Settings::batch_unpublish( $id ) :
			$this->unconfigured_error();
	}

	/**
	 * Create campaign.
	 *
	 * @param string $name Campaign name.
	 */
	public function create_campaign( $name ) {
		return $this->is_configured() ?
			\Newspack_Popups::create_campaign( $name ) :
			$this->unconfigured_error();
	}

	/**
	 * Duplicate campaign.
	 *
	 * @param int    $id Campaign group ID.
	 * @param string $name Campaign name.
	 */
	public function duplicate_campaign( $id, $name ) {
		return $this->is_configured() ?
			\Newspack_Popups::duplicate_campaign( $id, $name ) :
			$this->unconfigured_error();
	}

	/**
	 * Rename campaign.
	 *
	 * @param int    $id Campaign group ID.
	 * @param string $name Campaign name.
	 */
	public function rename_campaign( $id, $name ) {
		return $this->is_configured() ?
			\Newspack_Popups::rename_campaign( $id, $name ) :
			$this->unconfigured_error();
	}

	/**
	 * Archive campaign.
	 *
	 * @param int  $id Campaign group ID.
	 * @param bool $status Whether to archive or unarchive (true = archive, false = unarchive).
	 */
	public function archive_campaign( $id, $status ) {
		return $this->is_configured() ?
			\Newspack_Popups::archive_campaign( $id, $status ) :
			$this->unconfigured_error();
	}

	/**
	 * Delete campaign
	 *
	 * @param int $id Campaign group ID.
	 */
	public function delete_campaign( $id ) {
		return $this->is_configured() ?
			\Newspack_Popups::delete_campaign( $id ) :
			$this->unconfigured_error();
	}

	/**
	 * Get campaigns.
	 */
	public function get_campaigns() {
		return $this->is_configured() ?
			\Newspack_Popups::get_groups() :
			$this->unconfigured_error();
	}

	/**
	 * Get default title for a duplicated prompt.
	 *
	 * @param int $original_id Original Prompt ID.
	 * @param int $id Prompt ID to duplicate.
	 */
	public function get_duplicate_title( $original_id, $id ) {
		return $this->is_configured() ?
			\Newspack_Popups::get_duplicate_title( $original_id, $id ) :
			$this->unconfigured_error();
	}

	/**
	 * Duplicate a prompt.
	 *
	 * @param int    $id Prompt ID to duplicate.
	 * @param string $title Title to give the duplicated prompt.
	 */
	public function duplicate_popup( $id, $title ) {
		return $this->is_configured() ?
			\Newspack_Popups::duplicate_popup( $id, $title ) :
			$this->unconfigured_error();
	}

	/**
	 * Get abbreviated query keys for preview requests.
	 */
	public function preview_query_keys() {
		return $this->is_configured() ?
			\Newspack_Popups::PREVIEW_QUERY_KEYS :
			$this->unconfigured_error();
	}

	/**
	 * Configure Newspack Popups for Newspack use.
	 *
	 * @return bool || WP_Error Return true if successful, or WP_Error if not.
	 */
	public function configure() {
		return true;
	}

	/**
	 * Error to return if the plugin is not installed and activated.
	 *
	 * @return WP_Error
	 */
	private function unconfigured_error() {
		return new \WP_Error(
			'newspack_missing_required_plugin',
			esc_html__( 'The Newspack Popups plugin is not installed and activated. Install and/or activate it to access this feature.', 'newspack' ),
			[
				'status' => 400,
				'level'  => 'fatal',
			]
		);
	}
}
