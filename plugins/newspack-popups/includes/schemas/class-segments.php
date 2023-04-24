<?php
/**
 * The Segments Schema
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\Schemas;

use \Newspack\Campaigns\Schema;

/**
 * The Segments Schema
 */
class Segments extends Schema {

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
				'name'          => [
					'name'     => 'name',
					'type'     => 'string',
					'required' => true,
				],
				'id'            => [
					'name'     => 'id',
					'type'     => 'string',
					'required' => true,
				],
				'priority'      => [
					'name'     => 'priority',
					'type'     => 'integer',
					'required' => false,
					'default'  => PHP_INT_MAX,
				],
				'configuration' => [
					'name'                 => 'configuration',
					'type'                 => 'object',
					'required'             => true,
					'additionalProperties' => false,
					'properties'           => [
						'min_posts'           => [
							'name'     => 'min_posts',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'max_posts'           => [
							'name'     => 'max_posts',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'min_session_posts'   => [
							'name'     => 'min_session_posts',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'max_session_posts'   => [
							'name'     => 'max_session_posts',
							'type'     => 'integer',
							'required' => false,
							'default'  => 0,
						],
						'is_subscribed'       => [
							'name'     => 'is_subscribed',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'is_not_subscribed'   => [
							'name'     => 'is_not_subscribed',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'is_donor'            => [
							'name'     => 'is_donor',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'is_not_donor'        => [
							'name'     => 'is_not_donor',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'is_former_donor'     => [
							'name'     => 'is_former_donor',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'is_logged_in'        => [
							'name'     => 'is_logged_in',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'is_not_logged_in'    => [
							'name'     => 'is_not_logged_in',
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						],
						'favorite_categories' => [
							'name'     => 'favorite_categories',
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
						'referrers'           => [
							'name'     => 'referrers',
							'type'     => 'string',
							'required' => false,
							'default'  => '',
						],
						'referrers_not'       => [
							'name'     => 'referrers_not',
							'type'     => 'string',
							'required' => false,
							'default'  => '',
						],
						'is_disabled'         => [
							'name'     => 'is_disabled',
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
