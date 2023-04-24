<?php
/**
 * Newspack Ads GAM Default Targeting Keys
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers\GAM\Api;

use Newspack_Ads\Providers\GAM\Api;
use Newspack_Ads\Providers\GAM\Api\Api_Object;
use Google\AdsApi\AdManager\v202208\Statement;
use Google\AdsApi\AdManager\v202208\String_ValueMapEntry;
use Google\AdsApi\AdManager\v202208\TextValue;
use Google\AdsApi\AdManager\v202208\SetValue;
use Google\AdsApi\AdManager\v202208\CustomTargetingKey;
use Google\AdsApi\AdManager\v202208\CustomTargetingValue;
use Google\AdsApi\AdManager\v202208\ServiceFactory;

/**
 * Newspack Ads GAM Default Targeting Keys
 */
final class Targeting_Keys extends Api_Object {
	/**
	 * Custom targeting keys.
	 *
	 * @var string[]
	 */
	public static $default_targeting_keys = [
		'id',
		'slug',
		'category',
		'post_type',
		'template',
	];

	/**
	 * Create a custom targeting key-val segmentation with optional sample values.
	 *
	 * @param string   $name   The name of the key.
	 * @param string[] $values Optional sample values.
	 *
	 * @return array[
	 *  'targeting_key'  => CustomTargetingKey,
	 *  'found_values'   => CustomTargetingValue[],
	 *  'created_values' => CustomTargetingValue[]
	 * ]
	 *
	 * @throws \Exception If there is an error while communicating with the API.
	 */
	public function create_targeting_key( $name, $values = [] ) {
		$service = ( new ServiceFactory() )->createCustomTargetingService( $this->session );

		$statement = new Statement(
			"WHERE name = :name AND status = 'ACTIVE'",
			[
				new String_ValueMapEntry(
					'name',
					new SetValue(
						[
							new TextValue( $name ),
						]
					)
				),
			]
		);

		$targeting_key = null;
		$found_keys    = $service->getCustomTargetingKeysByStatement( $statement )->getResults();
		if ( empty( $found_keys ) ) {
			$targeting_key = $service->createCustomTargetingKeys(
				[
					( new CustomTargetingKey() )->setName( $name )->setType( 'FREEFORM' )->setStatus( 'ACTIVE' ),
				]
			)[0];
		} else {
			$targeting_key = $found_keys[0];
		}

		$found_values   = [];
		$created_values = [];
		if ( $targeting_key && count( $values ) ) {
			$key_id           = $targeting_key->getId();
			$values_statement = new Statement(
				"WHERE customTargetingKeyId = :key_id AND name = :name AND status = 'ACTIVE'",
				[
					new String_ValueMapEntry(
						'key_id',
						new SetValue(
							[
								new TextValue( $key_id ),
							]
						)
					),
					new String_ValueMapEntry(
						'name',
						new SetValue(
							array_map(
								function ( $key ) {
									return new TextValue( $key );
								},
								$values
							)
						)
					),
				]
			);
			$found_values     = (array) $service->getCustomTargetingValuesByStatement( $values_statement )->getResults();
			$values_to_create = array_values(
				array_diff(
					$values,
					array_map(
						function ( $value ) {
							return $value->getName();
						},
						$found_values
					)
				)
			);
			$created_values   = $service->createCustomTargetingValues(
				array_map(
					function ( $value ) use ( $key_id ) {
						return ( new CustomTargetingValue() )->setCustomTargetingKeyId( $key_id )->setName( $value );
					},
					$values_to_create
				)
			);
		}
		return [
			'targeting_key'  => $targeting_key,
			'found_values'   => is_array( $found_values ) && count( $found_values ) ? $found_values : [],
			'created_values' => is_array( $created_values ) && count( $created_values ) ? $created_values : [],
		];
	}

	/**
	 * Update custom targeting keys with predefined values if necessary.
	 *
	 * @return string[] Created custom targeting keys names or empty array if none was created.
	 *
	 * @throws \Exception If there is an error while communicating with the API.
	 */
	public function update_default_targeting_keys() {
		$service = ( new ServiceFactory() )->createCustomTargetingService( $this->session );

		// Find existing keys.
		$or_clauses = implode(
			' OR ',
			array_map(
				function( $key ) {
					return sprintf( "name LIKE '%s'", strtolower( $key ) );
				},
				self::$default_targeting_keys
			)
		);
		$statement  = new Statement( sprintf( "WHERE ( %s ) AND status = 'ACTIVE'", $or_clauses ) );
		try {
			$keys = $service->getCustomTargetingKeysByStatement( $statement );
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			/* Ignore error if it's a network error (network is not set yet). Next request will have a network set. */
			if ( false !== strpos( $error_message, 'AuthenticationError.NETWORK_NOT_FOUND' ) ) {
				return [];
			} elseif ( false !== strpos( $error_message, 'NETWORK_API_ACCESS_DISABLED' ) ) {
				throw new \Exception( __( 'API access for this GAM account is disabled.', 'newspack-ads' ) );
			} else {
				throw new \Exception( __( 'Unable to find existing targeting keys.', 'newspack-ads' ) );
			}
		}

		$keys_to_create = array_values(
			array_diff(
				self::$default_targeting_keys,
				array_map(
					function ( $key ) {
						return strtolower( $key->getName() );
					},
					(array) $keys->getResults()
				)
			)
		);

		// Create custom targeting keys.
		if ( ! empty( $keys_to_create ) ) {
			try {
				$created_keys = $service->createCustomTargetingKeys(
					array_map(
						function ( $key ) {
								return ( new CustomTargetingKey() )->setName( $key )->setType( 'FREEFORM' );
						},
						$keys_to_create
					)
				);
			} catch ( \Exception $e ) {
				throw new \Exception( __( 'Unable to create custom targeting keys', 'newspack-ads' ) );
			}
			return array_map(
				function( $key ) {
					return $key->getName();
				},
				$created_keys
			);
		}
		return [];
	}
}
