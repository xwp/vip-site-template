<?php
/**
 * Newspack Ads GAM Advertisers
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers\GAM\Api;

use Newspack_Ads\Providers\GAM\Api\Api_Object;
use Google\AdsApi\AdManager\Util\v202208\StatementBuilder;
use Google\AdsApi\AdManager\v202208\ServiceFactory;
use Google\AdsApi\AdManager\v202208\Company;
use Google\AdsApi\AdManager\v202208\CompanyType;

/**
 * Newspack Ads GAM Advertisers
 */
final class Advertisers extends Api_Object {

	/**
	 * Create company service.
	 *
	 * @return CompanyService Company service.
	 */
	private function get_company_service() {
		$service_factory = new ServiceFactory();
		return $service_factory->createCompanyService( $this->session );
	}

	/**
	 * Get all GAM Advertisers in the user's network.
	 *
	 * @return Company[] Array of Companies of typer Advertiser.
	 */
	private function get_advertisers() {
		$line_items            = [];
		$service               = $this->get_company_service();
		$page_size             = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$statement_builder     = ( new StatementBuilder() )->orderBy( 'id ASC' )->limit( $page_size )->withBindVariableValue( 'type', CompanyType::ADVERTISER );
		$total_result_set_size = 0;
		do {
			$page = $service->getCompaniesByStatement( $statement_builder->toStatement() );
			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $company ) {
					$line_items[] = $company;
				}
			}
			$statement_builder->increaseOffsetBy( $page_size );
		} while ( $statement_builder->getOffset() < $total_result_set_size );
		return $line_items;
	}

	/**
	 * Get all Advertisers in the user's network, serialized.
	 *
	 * @param Company[] $companies Optional array of companies to serialise. If empty, return all advertisers.
	 *
	 * @return array[] Array of serialised companies.
	 */
	public function get_serialized_advertisers( $companies = null ) {
		return array_map(
			function( $item ) {
				return [
					'id'   => $item->getId(),
					'name' => $item->getName(),
				];
			},
			null !== $companies ? $companies : $this->get_advertisers()
		);
	}

	/**
	 * Create an advertiser in GAM.
	 *
	 * @param string $name The advertiser name.
	 *
	 * @return array The created serialized advertiser.
	 *
	 * @throws \Exception In case of error in googleads lib.
	 */
	public function create_advertiser( $name ) {
		$advertiser = new Company();
		$advertiser->setName( $name );
		$advertiser->setType( CompanyType::ADVERTISER );
		$service = $this->get_company_service();
		$results = $service->createCompanies( [ $advertiser ] );
		return $this->get_serialized_advertisers( $results )[0];
	}
}
