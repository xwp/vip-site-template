/**
 * Placement Control Component.
 */

/**
 * WordPress dependencies
 */
import { Notice, SelectControl, TextControl } from '@wordpress/components';
import { Fragment, useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Get select options from object of ad units.
 *
 * @param {Array} providers List of providers.
 * @return {Array} Providers options for select control.
 */
const getProvidersForSelect = providers => {
	return [
		{
			label: __( 'Select a provider', 'newspack-ads' ),
			value: '',
		},
		...providers.map( unit => {
			return {
				label: unit.name,
				value: unit.id,
			};
		} ),
	];
};

/**
 * Get select options from object of ad units.
 *
 * @param {Object} provider Provider object.
 * @return {Array} Ad unit options for select control.
 */
const getProviderUnitsForSelect = provider => {
	if ( ! provider?.units ) {
		return [];
	}
	return [
		{
			label: __( 'Select an Ad Unit', 'newspack-ads' ),
			value: '',
		},
		...provider.units.map( unit => {
			return {
				label: unit.name,
				value: unit.value,
			};
		} ),
	];
};

/**
 * Whether any `sizesToCheck` size exists in `sizes`.
 *
 * @param {Array} sizes        Array of sizes.
 * @param {Array} sizesToCheck Array of sizes to check.
 * @return {boolean} Whether any size was found.
 */
const hasAnySize = ( sizes, sizesToCheck ) => {
	return sizesToCheck.some( sizeToCheck => {
		return ( sizes || [] ).find(
			size => size[ 0 ] === sizeToCheck[ 0 ] && size[ 1 ] === sizeToCheck[ 1 ]
		);
	} );
};

const PlacementControl = ( {
	label = __( 'Ad Unit', 'newspack' ),
	providers = [],
	bidders = {},
	value = {},
	disabled = false,
	onChange = () => {},
} ) => {
	const [ biddersErrors, setBiddersErrors ] = useState( {} );

	// Default provider is GAM or first index if GAM is not active.
	const placementProvider =
		providers.find( provider => provider?.id === ( value.provider || 'gam' ) ) || providers[ 0 ];

	useEffect( () => {
		const errors = {};
		Object.keys( bidders ).forEach( bidderKey => {
			const bidder = bidders[ bidderKey ];
			const unit = placementProvider?.units.find( u => u.value === value.ad_unit );
			const supported = value.ad_unit && unit && hasAnySize( bidder.ad_sizes, unit.sizes );
			errors[ bidderKey ] =
				! value.ad_unit || ! unit || supported
					? null
					: sprintf(
							// Translators: Ad bidder name.
							__( '%s does not support the selected ad unit sizes.', 'newspack' ),
							bidder.name,
							''
					  );
		} );
		setBiddersErrors( errors );
	}, [ providers, value.ad_unit ] );

	if ( ! providers.length ) {
		return (
			<Notice
				isWarning
				noticeText={ __( 'There is no provider available.', 'newspack-ads' ) }
				isDismissible={ false }
			/>
		);
	}

	return (
		<Fragment>
			{ providers.length > 1 && (
				<SelectControl
					label={ __( 'Provider', 'newspack' ) }
					value={ placementProvider?.id }
					options={ getProvidersForSelect( providers ) }
					onChange={ provider => onChange( { ...value, provider } ) }
					disabled={ disabled }
				/>
			) }
			<SelectControl
				label={ label }
				value={ value.ad_unit }
				options={ getProviderUnitsForSelect( placementProvider ) }
				onChange={ data =>
					onChange( {
						...value,
						ad_unit: data,
					} )
				}
				disabled={ disabled }
			/>
			{ placementProvider?.id === 'gam' &&
				Object.keys( bidders ).map( bidderKey => {
					const bidder = bidders[ bidderKey ];
					// Translators: Bidder name.
					const bidderLabel = sprintf( __( '%s Placement ID', 'newspack-ads' ), bidder.name );
					return (
						<TextControl
							key={ bidderKey }
							value={ value.bidders_ids ? value.bidders_ids[ bidderKey ] : null }
							label={ bidderLabel }
							disabled={ biddersErrors[ bidderKey ] || disabled }
							onChange={ data =>
								onChange( {
									...value,
									bidders_ids: {
										...value.bidders_ids,
										[ bidderKey ]: data,
									},
								} )
							}
						/>
					);
				} ) }
			{ placementProvider?.id === 'gam' &&
				Object.keys( biddersErrors ).map( bidderKey => {
					if ( biddersErrors[ bidderKey ] ) {
						return (
							<Notice key={ bidderKey } status="warning" isDismissible={ false }>
								{ biddersErrors[ bidderKey ] }
							</Notice>
						);
					}
					return null;
				} ) }
		</Fragment>
	);
};

export default PlacementControl;
