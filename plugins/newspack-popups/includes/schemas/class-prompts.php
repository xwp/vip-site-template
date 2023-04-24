<?php
/**
 * The Prompts Schema
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\Schemas;

use \Newspack\Campaigns\Schema;
use Newspack_Popups_Model;

/**
 * The Prompts Schema
 */
class Prompts extends Schema {

	/**
	 * Get the schema.
	 *
	 * @return array The schema.
	 */
	public static function get_schema() {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'title'           => [
					'name'     => 'title',
					'type'     => 'string',
					'required' => true,
				],
				'content'         => [
					'name'     => 'content',
					'type'     => 'string',
					'required' => true,
				],
				'campaign_groups' => [
					'name'     => 'campaign_groups',
					'type'     => 'array',
					'required' => false,
					'default'  => [],
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'id'   => [
								'type' => 'integer',
							],
							'name' => [
								'type' => 'string',
							],
						],
					],
				],
				'status'          => [
					'name'     => 'status',
					'type'     => 'string',
					'required' => true,
					'enum'     => [
						'publish',
						'draft',
					],
				],
				'categories'      => [
					'name'     => 'categories',
					'type'     => 'array',
					'required' => false,
					'default'  => [],
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'id'   => [
								'type' => 'integer',
							],
							'name' => [
								'type' => 'string',
							],
						],
					],
				],
				'tags'            => [
					'name'     => 'tags',
					'type'     => 'array',
					'required' => false,
					'default'  => [],
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'id'   => [
								'type' => 'integer',
							],
							'name' => [
								'type' => 'string',
							],
						],
					],
				],
				'options'         => [
					'type'                 => 'object',
					'required'             => true,
					'additionalProperties' => false,
					'properties'           => [
						'background_color'               => [
							'name'     => 'background_color',
							'type'     => 'string',
							'format'   => 'hex-color',
							'required' => false,
							'default'  => '#FFFFFF',
						],
						'display_title'                  => [
							'name'     => 'display_title',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'hide_border'                    => [
							'name'     => 'hide_border',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'large_border'                   => [
							'name'     => 'large_border',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'frequency'                      => [
							'name'     => 'frequency',
							'type'     => 'string',
							'required' => true,
							'enum'     => [
								'once',
								'weekly',
								'daily',
								'always',
								'custom',
							],

						],
						'frequency_max'                  => [
							'name'     => 'frequency_max',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'frequency_start'                => [
							'name'     => 'frequency_start',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'frequency_between'              => [
							'name'     => 'frequency_between',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'frequency_reset'                => [
							'name'     => 'frequency_reset',
							'type'     => 'string',
							'required' => false,
							'default'  => 'month',
							'enum'     => [
								'month',
								'week',
								'day',
							],
						],
						'overlay_color'                  => [
							'name'     => 'overlay_color',
							'type'     => 'string',
							'required' => false,
							'format'   => 'hex-color',
							'default'  => '#000000',
						],
						'overlay_opacity'                => [
							'name'     => 'overlay_opacity',
							'type'     => 'integer',
							'required' => false,
							'default'  => 30,
							'maximum'  => 100,
						],
						'overlay_size'                   => [
							'name'     => 'overlay_size',
							'type'     => 'string',
							'required' => false,
							'default'  => 'medium',
							'enum'     => array_map(
								function( $item ) {
									return $item['value'];
								},
								Newspack_Popups_Model::get_popup_size_options()
							),
						],
						'no_overlay_background'          => [
							'name'     => 'no_overlay_background',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'placement'                      => [
							'name'     => 'placement',
							'type'     => 'string',
							'required' => true,
							'enum'     => [
								// overlay.
								'top_left',
								'top_right',
								'top',
								'bottom_left',
								'bottom_right',
								'bottom',
								'center',
								'center_left',
								'center_right',

								// inline.
								'inline',
								'archives',
								'above_header',
								'manual',
								'custom1',
								'custom2',
								'custom3',
							],
						],
						'trigger_type'                   => [
							'name'     => 'trigger_type',
							'type'     => 'string',
							'required' => false,
							'default'  => 'time',
							'enum'     => [
								'time',
								'scroll',
								'blocks_count',
							],
						],
						'trigger_delay'                  => [
							'name'     => 'trigger_delay',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'trigger_scroll_progress'        => [
							'name'     => 'trigger_scroll_progress',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'trigger_blocks_count'           => [
							'name'     => 'trigger_blocks_count',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'archive_insertion_posts_count'  => [
							'name'     => 'archive_insertion_posts_count',
							'type'     => 'integer',
							'required' => false,
							'default'  => 1,
						],
						'archive_insertion_is_repeating' => [
							'name'     => 'archive_insertion_is_repeating',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'utm_suppression'                => [
							'name'     => 'utm_suppression',
							'type'     => 'string',
							'required' => false,
							'default'  => '',
						],
						'selected_segment_id'            => [
							'name'     => 'selected_segment_id',
							'type'     => 'string',
							'required' => false,
							'default'  => '',
						],
						'post_types'                     => [
							'name'     => 'post_types',
							'type'     => 'array',
							'required' => false,
							'default'  => Newspack_Popups_Model::get_default_popup_post_types(),
							'items'    => [
								'type' => 'string',
							],
						],
						'archive_page_types'             => [
							'name'     => 'archive_page_types',
							'type'     => 'array',
							'required' => false,
							'default'  => Newspack_Popups_Model::get_supported_archive_page_types(),
							'items'    => [
								'type' => 'string',
							],
						],
						'additional_classes'             => [
							'name'     => 'additional_classes',
							'type'     => 'string',
							'required' => false,
							'default'  => '',
						],
						'excluded_categories'            => [
							'name'     => 'excluded_categories',
							'type'     => 'array',
							'required' => false,
							'default'  => [],
							'items'    => [
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => [
									'id'   => [
										'type' => 'integer',
									],
									'name' => [
										'type' => 'string',
									],
								],
							],
						],
						'excluded_tags'                  => [
							'name'     => 'excluded_tags',
							'type'     => 'array',
							'required' => false,
							'default'  => [],
							'items'    => [
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => [
									'id'   => [
										'type' => 'integer',
									],
									'name' => [
										'type' => 'string',
									],
								],
							],
						],
						'duplicate_of'                   => [
							'name'     => 'duplicate_of',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'newspack_popups_has_disabled_popups' => [
							'name'     => 'newspack_popups_has_disabled_popups',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
					],
				],
			],
		];
	}
}
