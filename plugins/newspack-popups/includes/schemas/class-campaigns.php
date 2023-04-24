<?php
/**
 * The Campaigns Schema
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\Schemas;

use \Newspack\Campaigns\Schema;

/**
 * The Campaigns Schema
 */
class Campaigns extends Schema {

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
				'id'   => [
					'name'     => 'id',
					'type'     => 'integer',
					'required' => true,
				],
				'name' => [
					'name'     => 'name',
					'type'     => 'string',
					'required' => true,
				],
			],
		];
	}
}
