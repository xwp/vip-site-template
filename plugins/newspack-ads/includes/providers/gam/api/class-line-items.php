<?php
/**
 * Newspack Ads GAM Line Items
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers\GAM\Api;

use Newspack_Ads\Providers\GAM\Api;
use Newspack_Ads\Providers\GAM\Api\Api_Object;
use Google\AdsApi\AdManager\Util\v202208\StatementBuilder;
use Google\AdsApi\AdManager\v202208\ServiceFactory;
use Google\AdsApi\AdManager\v202208\LineItemService;
use Google\AdsApi\AdManager\v202208\LineItemCreativeAssociation;
use Google\AdsApi\AdManager\v202208\LineItem;
use Google\AdsApi\AdManager\v202208\Size;
use Google\AdsApi\AdManager\v202208\Money;
use Google\AdsApi\AdManager\v202208\Goal;
use Google\AdsApi\AdManager\v202208\Targeting;
use Google\AdsApi\AdManager\v202208\AdUnitTargeting;
use Google\AdsApi\AdManager\v202208\InventoryTargeting;
use Google\AdsApi\AdManager\v202208\CreativePlaceholder;
use Google\AdsApi\AdManager\v202208\CustomCriteriaSet;
use Google\AdsApi\AdManager\v202208\CustomCriteria;
use Google\AdsApi\AdManager\v202208\ApiException;

/**
 * Newspack Ads GAM Line Items
 */
final class Line_Items extends Api_Object {
	/**
	 * Get line item service.
	 *
	 * @return LineItemService Line Item service.
	 */
	private function get_line_item_service() {
		$service_factory = new ServiceFactory();
		return $service_factory->createLineItemService( $this->session );
	}

	/**
	 * Get all GAM Line Items in the user's network.
	 *
	 * @param StatementBuilder $statement_builder (optional) Statement builder.
	 *
	 * @return LineItem[] Array of Orders.
	 */
	private function get_line_items( StatementBuilder $statement_builder = null ) {
		$line_items        = [];
		$service           = $this->get_line_item_service();
		$page_size         = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$statement_builder = $statement_builder ?? new StatementBuilder();
		$statement_builder->orderBy( 'id ASC' )->limit( $page_size );
		$total_result_set_size = 0;
		do {
			$page = $service->getLineItemsByStatement( $statement_builder->toStatement() );
			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $line_item ) {
					$line_items[] = $line_item;
				}
			}
			$statement_builder->increaseOffsetBy( $page_size );
		} while ( $statement_builder->getOffset() < $total_result_set_size );
		return $line_items;
	}

	/**
	 * Get all line items given an order ID.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return LineItems[] Array of LineItems.
	 */
	public function get_line_items_by_order_id( $order_id ) {
		$statement_builder = ( new StatementBuilder() )->where( sprintf( 'orderId = %d', $order_id ) );
		return $this->get_line_items( $statement_builder );
	}

	/**
	 * Get all Line Items in the user's network, serialized.
	 *
	 * @param LineItem[] $line_items (optional) Array of line items.
	 *
	 * @return object[] Array of serialized orders.
	 */
	public function get_serialized_line_items( $line_items = null ) {
		return array_map(
			function( $item ) {
				return [
					'id'          => $item->getId(),
					'orderId'     => $item->getOrderId(),
					'name'        => $item->getName(),
					'status'      => $item->getStatus(),
					'is_archived' => $item->getIsArchived(),
					'type'        => $item->getLineItemType(),
				];
			},
			null !== $line_items ? $line_items : $this->get_line_items()
		);
	}

	/**
	 * Create or update line items.
	 *
	 * If the line item config contains the `id` property, it will attempt to
	 * update. Otherwise it will create.
	 *
	 * @param array[] $line_item_configs List of line item configurations.
	 *
	 * @return LineItem[] Created and/or updated line items.
	 *
	 * @throws \Exception If unsupported configuration or unable to create line items.
	 */
	public function create_or_update_line_items( $line_item_configs = [] ) {
		$network              = $this->api->get_network();
		$line_items_to_create = [];
		$line_items_to_update = [];
		foreach ( $line_item_configs as $config ) {
			$config    = wp_parse_args(
				$config,
				[
					'start_date_time_type'   => 'IMMEDIATELY',
					'line_item_type'         => 'PRICE_PRIORITY',
					'cost_type'              => 'CPM',
					'creative_rotation_type' => 'EVEN',
					'primary_goal'           => [
						'goal_type' => 'NONE',
					],
				]
			);
			$line_item = new LineItem();
			$line_item->setOrderId( $config['order_id'] );
			$line_item->setName( $config['name'] );
			$line_item->setLineItemType( $config['line_item_type'] );
			$line_item->setCreativeRotationType( $config['creative_rotation_type'] );
			$line_item->setPrimaryGoal( new Goal( $config['primary_goal']['goal_type'] ) );

			// Creative placeholders (or expected creatives).
			if ( isset( $config['creative_placeholders'] ) && ! empty( $config['creative_placeholders'] ) ) {
				$creative_placeholders = array_map(
					function ( $size ) {
						return new CreativePlaceholder( new Size( $size[0], $size[1] ) );
					},
					$config['creative_placeholders']
				);
				$line_item->setCreativePlaceholders( $creative_placeholders );
			}

			// Date and time options.
			$line_item->setStartDateTimeType( $config['start_date_time_type'] );
			if ( isset( $config['unlimited_end_date_time'] ) ) {
				$line_item->setUnlimitedEndDateTime( (bool) $config['unlimited_end_date_time'] );
			}

			// Cost options.
			$line_item->setCostType( $config['cost_type'] );
			if ( isset( $config['cost_per_unit'] ) ) {
				if ( ! isset( $config['cost_per_unit']['currency_code'] ) ) {
					$config['cost_per_unit']['currency_code'] = $network->getCurrencyCode();
				}
				$cost_per_unit = new Money( $config['cost_per_unit']['currency_code'], $config['cost_per_unit']['micro_amount'] );
				$line_item->setCostPerUnit( $cost_per_unit );
				if ( isset( $config['cost_per_unit']['micro_amount_value'] ) ) {
					$value_cost_per_unit = new Money( $config['cost_per_unit']['currency_code'], $config['cost_per_unit']['micro_amount_value'] );
					$line_item->setValueCostPerUnit( $value_cost_per_unit );
				}
			}

			// Targeting options.
			if ( isset( $config['targeting'] ) ) {
				$targeting = new Targeting();

				// Obligatory inventory targeting.
				// Default is "Run of network", which is targeted to the network's root ad unit including descendants.
				$inventory_targeting = new InventoryTargeting();
				if ( ! isset( $config['targeting']['inventory_targeting'] ) ) {
					$inventory_targeting->setTargetedAdUnits( [ new AdUnitTargeting( $network->getEffectiveRootAdUnitId(), true ) ] );
				} else {
					throw new \Exception( 'Inventory targeting is not supported yet' );
				}
				$targeting->setInventoryTargeting( $inventory_targeting );

				// Custom targeting.
				if ( isset( $config['targeting']['custom_targeting'] ) ) {
					$criteria_set = new CustomCriteriaSet( 'AND' );
					$children     = [];
					foreach ( $config['targeting']['custom_targeting'] as $key_id => $value_ids ) {
						$children[] = new CustomCriteria( $key_id, $value_ids, 'IS' );
					}
					$criteria_set->setChildren( $children );
					$targeting->setCustomTargeting( $criteria_set );
				}

				// Apply configured targeting to line item.
				$line_item->setTargeting( $targeting );
			}

			if ( isset( $config['id'] ) ) {
				$line_item->setId( $config['id'] );
				$line_items_to_update[] = $line_item;
			} else {
				$line_items_to_create[] = $line_item;
			}
		}
		$service            = $this->get_line_item_service();
		$created_line_items = ! empty( $line_items_to_create ) ? $service->createLineItems( $line_items_to_create ) : [];
		$updated_line_items = ! empty( $line_items_to_update ) ? $service->updateLineItems( $line_items_to_update ) : [];
		return array_merge( $created_line_items, $updated_line_items );
	}

	/**
	 * Create line item to creative associations (LICAs).
	 *
	 * @param array[] $lica_configs LICA configurations.
	 *
	 * @return LineItemCreativeAssociation[] Created LICAs.
	 */
	public function associate_creatives_to_line_items( $lica_configs ) {
		$licas = [];
		foreach ( $lica_configs as $lica_config ) {
			$lica = new LineItemCreativeAssociation();
			$lica->setLineItemId( (int) $lica_config['line_item_id'] );
			$lica->setCreativeId( (int) $lica_config['creative_id'] );
			if ( isset( $lica_config['sizes'] ) && ! empty( $lica_config['sizes'] ) ) {
				$lica->setSizes(
					array_map(
						function ( $size ) {
							return new Size( $size[0], $size[1] );
						},
						$lica_config['sizes']
					)
				);
			}
			$licas[] = $lica;
		}
		$service        = ( new ServiceFactory() )->createLineItemCreativeAssociationService( $this->session );
		$attempt_create = true;
		while ( $attempt_create ) {
			try {
				$result         = $service->createLineItemCreativeAssociations( array_values( $licas ) );
				$attempt_create = false;
			} catch ( ApiException $e ) {
				foreach ( $e->getErrors() as $error ) {
					if ( 'CommonError.ALREADY_EXISTS' === $error->getErrorString() ) {
						// Attempt to create again without duplicate associations.
						$index = $error->getFieldPathElements()[0]->getIndex();
						unset( $licas[ $index ] );
					} else {
						// Bail if there are other errors.
						return $this->api->get_error( $e, __( 'Unexpected error while creating creative associations.', 'newspack-ads' ) );
					}
				}
				// Leave without errors if entire batch exists.
				if ( 0 === count( $licas ) ) {
					$attempt_create = false;
					return [];
				}
			}
		}
		return $result;
	}
}
