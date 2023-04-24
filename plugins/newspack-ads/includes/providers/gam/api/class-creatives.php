<?php
/**
 * Newspack Ads GAM Creatives
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers\GAM\Api;

use Newspack_Ads\Providers\GAM\Api;
use Newspack_Ads\Providers\GAM\Api\Api_Object;
use Google\AdsApi\AdManager\Util\v202208\StatementBuilder;
use Google\AdsApi\AdManager\v202208\ServiceFactory;
use Google\AdsApi\AdManager\v202208\Creative;
use Google\AdsApi\AdManager\v202208\Size;

/**
 * Newspack Ads GAM Creatives
 */
final class Creatives extends Api_Object {
	/**
	 * Create creative service.
	 *
	 * @return CreativeService Creative service.
	 */
	private function get_creative_service() {
		$service_factory = new ServiceFactory();
		return $service_factory->createCreativeService( $this->session );
	}

	/**
	 * Get creatives from an optional initialized statement builder.
	 *
	 * @param StatementBuilder $statement_builder (optional) Statement builder.
	 *
	 * @return Creative[] Array of creatives.
	 */
	private function get_creatives( StatementBuilder $statement_builder = null ) {
		$creatives             = [];
		$creative_service      = $this->get_creative_service();
		$page_size             = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$total_result_set_size = 0;
		$statement_builder     = $statement_builder ?? new StatementBuilder();
		$statement_builder->orderBy( 'id ASC' )->limit( $page_size );
		do {
			$page = $creative_service->getCreativesByStatement( $statement_builder->toStatement() );
			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $creative ) {
					$creatives[] = $creative;
				}
			}
			$statement_builder->increaseOffsetBy( $page_size );
		} while ( $statement_builder->getOffset() < $total_result_set_size );
		return $creatives;
	}

	/**
	 * Get creatives from an advertiser.
	 *
	 * @param int $advertiser_id Advertiser ID.
	 *
	 * @return Creative[] Array of creatives.
	 */
	private function get_creatives_by_advertiser( $advertiser_id ) {
		$statement_builder = ( new StatementBuilder() )->where( sprintf( 'advertiserId = %d', $advertiser_id ) );
		return $this->get_creatives( $statement_builder );
	}

	/**
	 * Get all creatives in the user's network, serialized.
	 *
	 * @param Creative[] $creatives (optional) Array of Creatives.
	 *
	 * @return array[] Array of serialised creatives.
	 */
	public function get_serialized_creatives( $creatives = null ) {
		return array_map(
			function( $creatives ) {
				return [
					'id'           => $creatives->getId(),
					'name'         => $creatives->getName(),
					'advertiserId' => $creatives->getAdvertiserId(),
				];
			},
			null !== $creatives ? $creatives : $this->get_creatives()
		);
	}

	/**
	 * Get creatives from an advertiser, serialized.
	 *
	 * @param int $advertiser_id Advertiser ID.
	 *
	 * @return array[] Array of serialised creatives.
	 */
	public function get_serialized_creatives_by_advertiser( $advertiser_id ) {
		return $this->get_serialized_creatives( $this->get_creatives_by_advertiser( $advertiser_id ) );
	}

	/**
	 * Build creative objects from a configuration array.
	 *
	 * @param array[] $creatives_config Array of creatives configurations.
	 *
	 * @return Creative[] Array of creatives.
	 *
	 * @throws \Exception If a creative configuration is invalid.
	 */
	public static function build_creatives_from_config( $creatives_config ) {
		$creatives = [];
		$xsi_types = [
			'BaseDynamicAllocationCreative',
			'BaseRichMediaStudioCreative',
			'ClickTrackingCreative',
			'HasDestinationUrlCreative',
			'Html5Creative',
			'InternalRedirectCreative',
			'LegacyDfpCreative',
			'ProgrammaticCreative',
			'TemplateCreative',
			'ThirdPartyCreative',
			'UnsupportedCreative',
			'VastRedirectCreative',
		];
		foreach ( $creatives_config as $creative_config ) {
			$creative_config = wp_parse_args(
				$creative_config,
				[
					'xsi_type' => 'Creative',
				]
			);
			if ( ! in_array( $creative_config['xsi_type'], $xsi_types, true ) ) {
				throw new \Exception( 'Invalid xsi type' );
			}
			$fully_qualified_creative_class = 'Google\\AdsApi\\AdManager\\' . Api::API_VERSION . '\\' . $creative_config['xsi_type'];
			$creative                       = new $fully_qualified_creative_class();
			$creative->setName( $creative_config['name'] );
			$creative->setAdvertiserId( $creative_config['advertiser_id'] );
			$creative->setSize( new Size( $creative_config['width'], $creative_config['height'] ) );
			switch ( $creative_config['xsi_type'] ) {
				case 'ThirdPartyCreative':
					$creative_config = wp_parse_args(
						$creative_config,
						[
							'snippet'                  => '',
							'is_safe_frame_compatible' => true,
						]
					);
					$creative->setSnippet( $creative_config['snippet'] );
					$creative->setIsSafeFrameCompatible( $creative_config['is_safe_frame_compatible'] );
					break;
			}
			$creatives[] = $creative;
		}
		return $creatives;
	}

	/**
	 * Create a GAM Creative.
	 *
	 * @param array[] $creatives_config Array of creatives configurations.
	 *
	 * @return array Created creative.
	 *
	 * @throws \Exception If unable to create creatives.
	 */
	public function create_creatives( $creatives_config = [] ) {
		$creatives         = self::build_creatives_from_config( $creatives_config );
		$service           = $this->get_creative_service();
		$created_creatives = $service->createCreatives( $creatives );
		return $this->get_serialized_creatives( $created_creatives );
	}
}
