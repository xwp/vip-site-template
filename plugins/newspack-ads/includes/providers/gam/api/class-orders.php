<?php
/**
 * Newspack Ads GAM Orders
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers\GAM\Api;

use Newspack_Ads\Providers\GAM\Api;
use Newspack_Ads\Providers\GAM\Api\Api_Object;
use Google\AdsApi\AdManager\Util\v202208\StatementBuilder;
use Google\AdsApi\AdManager\v202208\ServiceFactory;
use Google\AdsApi\AdManager\v202208\Order;
use Google\AdsApi\AdManager\v202208\ArchiveOrders as ArchiveOrdersAction;
use Google\AdsApi\AdManager\v202208\ApiException;

/**
 * Newspack Ads GAM Orders
 */
final class Orders extends Api_Object {
	/**
	 * Create order service.
	 *
	 * @return OrderService Order service.
	 */
	private function get_order_service() {
		$service_factory = new ServiceFactory();
		return $service_factory->createOrderService( $this->session );
	}

	/**
	 * Get all GAM orders in the user's network.
	 *
	 * @param StatementBuilder $statement_builder (optional) Statement builder.
	 *
	 * @return Order[] Array of Orders.
	 */
	private function get_orders( StatementBuilder $statement_builder = null ) {
		$orders                = [];
		$order_service         = $this->get_order_service();
		$page_size             = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$total_result_set_size = 0;
		$statement_builder     = $statement_builder ?? new StatementBuilder();
		$statement_builder->orderBy( 'name ASC' )->limit( $page_size );
		if ( ! empty( $ids ) ) {
			$statement_builder = $statement_builder->where( 'ID IN(' . implode( ', ', $ids ) . ')' );
		}
		do {
			$page = $order_service->getOrdersByStatement( $statement_builder->toStatement() );
			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $order ) {
					$orders[] = $order;
				}
			}
			$statement_builder->increaseOffsetBy( $page_size );
		} while ( $statement_builder->getOffset() < $total_result_set_size );
		return $orders;
	}

	/**
	 * Get orders by a list of IDs.
	 *
	 * @param int[] $ids Array of order IDs.
	 *
	 * @return Order[] Array of Orders.
	 */
	public function get_orders_by_id( $ids = [] ) {
		if ( ! is_array( $ids ) ) {
			$ids = [ $ids ];
		}
		$statement_builder = ( new StatementBuilder() )->where( 'ID IN(' . implode( ', ', $ids ) . ')' );
		return $this->get_orders( $statement_builder );
	}

	/**
	 * Get orders by advertiser ID.
	 *
	 * @param int $advertiser_id Advertiser ID.
	 *
	 * @return Order[] Array of Orders.
	 */
	public function get_orders_by_advertiser( $advertiser_id ) {
		$statement_builder = ( new StatementBuilder() )->where( sprintf( 'advertiserId = %d', $advertiser_id ) );
		return $this->get_orders( $statement_builder );
	}

	/**
	 * Get all orders in the user's network, serialized.
	 *
	 * @param Order[] $orders (optional) Array of Orders.
	 *
	 * @return object[] Array of serialized orders.
	 */
	public function get_serialized_orders( $orders = null ) {
		return array_map(
			function( $order ) {
				return [
					'id'            => $order->getId(),
					'name'          => $order->getName(),
					'status'        => $order->getStatus(),
					'is_archived'   => $order->getIsArchived(),
					'advertiser_id' => $order->getAdvertiserId(),
				];
			},
			null !== $orders ? $orders : $this->get_orders()
		);
	}

	/**
	 * Create a GAM Order.
	 *
	 * @param string $name          Order Name.
	 * @param string $advertiser_id Order Advertiser ID.
	 *
	 * @return array|WP_Error Serialised created order or error if it fails.
	 */
	public function create_order( $name, $advertiser_id ) {
		$order = new Order();
		$order->setName( $name );
		$order->setAdvertiserId( $advertiser_id );
		$order->setTraffickerId( $this->api->get_current_user()->getId() );
		try {
			$service        = $this->get_order_service();
			$created_orders = $service->createOrders( [ $order ] );
		} catch ( ApiException $e ) {
			return $this->api->get_error( $e, __( 'Order was not created due to an unexpected error.', 'newspack-ads' ) );
		}
		return $this->get_serialized_orders( $created_orders )[0];
	}

	/**
	 * Archive a GAM Order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return UpdateResult
	 */
	public function archive_order( $order_id ) {
		return $this->get_order_service()->performOrderAction(
			new ArchiveOrdersAction(),
			( new StatementBuilder() )->where( 'id = :id' )
				->orderBy( 'id ASC' )
				->limit( StatementBuilder::SUGGESTED_PAGE_LIMIT )
				->withBindVariableValue( 'id', $order_id )
				->toStatement()
		);
	}
}
