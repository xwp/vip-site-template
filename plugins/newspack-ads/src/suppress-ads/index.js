'use strict';

/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { Fragment, Component } from '@wordpress/element';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';

/**
 * Add a section to the Document settings with a toggle for suppressing ads on the current single.
 */
class NewspackSuppressAdsPanel extends Component {
	render() {
		const placements = window.newspackAdsSuppressAds?.placements || {};
		const {
			newspack_ads_suppress_ads,
			newspack_ads_suppress_ads_placements,
			updateSuppressAds,
			updateSuppressPlacements,
		} = this.props;
		return (
			<PluginDocumentSettingPanel
				name="newspack-ad-free"
				title={ __( 'Newspack Ads Settings', 'newspack-ads' ) }
				className="newspack-subtitle"
			>
				<ToggleControl
					label={ __( "Don't show ads on this content", 'newspack-ads' ) }
					checked={ newspack_ads_suppress_ads }
					onChange={ value => {
						updateSuppressAds( value );
					} }
				/>
				{ ! newspack_ads_suppress_ads && (
					<Fragment>
						<p>{ __( 'Suppress specific placements:', 'newspack-ads' ) }</p>
						{ Object.keys( placements ).map( placementKey => (
							<ToggleControl
								key={ placementKey }
								label={ placements[ placementKey ].name }
								checked={
									newspack_ads_suppress_ads_placements &&
									newspack_ads_suppress_ads_placements.indexOf( placementKey ) !== -1
								}
								onChange={ () => {
									const suppressPlacements = newspack_ads_suppress_ads_placements?.length
										? [ ...newspack_ads_suppress_ads_placements ]
										: [];
									if ( suppressPlacements.indexOf( placementKey ) !== -1 ) {
										suppressPlacements.splice( suppressPlacements.indexOf( placementKey ), 1 );
									} else {
										suppressPlacements.push( placementKey );
									}
									updateSuppressPlacements( suppressPlacements );
								} }
							/>
						) ) }
					</Fragment>
				) }
			</PluginDocumentSettingPanel>
		);
	}
}

const ComposedPanel = compose( [
	withSelect( select => {
		const { newspack_ads_suppress_ads, newspack_ads_suppress_ads_placements } =
			select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		return { newspack_ads_suppress_ads, newspack_ads_suppress_ads_placements };
	} ),
	withDispatch( dispatch => ( {
		updateSuppressAds( value ) {
			dispatch( 'core/editor' ).editPost( { meta: { newspack_ads_suppress_ads: value } } );
		},
		updateSuppressPlacements( value ) {
			dispatch( 'core/editor' ).editPost( {
				meta: { newspack_ads_suppress_ads_placements: value },
			} );
		},
	} ) ),
] )( NewspackSuppressAdsPanel );

registerPlugin( 'plugin-document-setting-panel-newspack-suppress-ads', {
	render: ComposedPanel,
	icon: null,
} );
