/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { RichText } from '@wordpress/block-editor';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import type {
	ComponentProps,
	DonationFrequencySlug,
	DonateBlockAttributes,
	TierBasedOptionValue,
} from '../types';
import { getColorForContrast } from '../utils';
import { FREQUENCIES, DISABLED_IN_TIERS_BASED_LAYOUT_TIER_INDEX } from '../consts';

const getFrequencyLabel = ( frequencySlug: DonationFrequencySlug ) => {
	switch ( frequencySlug ) {
		case 'once':
			return __( 'once', 'newspack-blocks' );
		case 'month':
			return __( 'per month', 'newspack-blocks' );
		case 'year':
			return __( 'per year', 'newspack-blocks' );
	}
};

const TierBasedLayout = ( props: ComponentProps ) => {
	const { amounts, availableFrequencies, attributes } = props;
	const [ currentFrequency, setCurrencyFrequency ] = useState( availableFrequencies[ 0 ] );

	const displayedAmounts = amounts[ currentFrequency ].slice(
		0,
		DISABLED_IN_TIERS_BASED_LAYOUT_TIER_INDEX
	);

	useEffect( () => {
		setCurrencyFrequency( availableFrequencies[ 0 ] );
	}, [ availableFrequencies.length ] );

	const handleTierOptionChange =
		( tierIndex: number, optionKey: keyof TierBasedOptionValue ) => ( value: string ) => {
			const tiersBasedOptions: DonateBlockAttributes[ 'tiersBasedOptions' ] = [
				...attributes.tiersBasedOptions, // The value has to be copied in order for the block editor to pick up the change.
			];
			tiersBasedOptions[ tierIndex ] = {
				...tiersBasedOptions[ tierIndex ],
				[ optionKey ]: value,
			};
			props.setAttributes( { tiersBasedOptions } );
		};

	const isAnyRecommended = attributes.tiersBasedOptions.some( tier =>
		Boolean( tier.recommendLabel )
	);

	return (
		<form className="wpbnbd__tiers" onSubmit={ e => e.preventDefault() }>
			<div className="wpbnbd__tiers__selection">
				{ availableFrequencies.map( frequencySlug => {
					const isActive = currentFrequency === frequencySlug;
					return (
						<button
							key={ frequencySlug }
							className={ classNames( 'wpbnbd__button', {
								'wpbnbd__button--active': isActive,
							} ) }
							onClick={ () => setCurrencyFrequency( frequencySlug ) }
						>
							{ FREQUENCIES[ frequencySlug ] }
						</button>
					);
				} ) }
			</div>
			<div className="wpbnbd__tiers__options">
				{ displayedAmounts.map( ( amount, index ) => {
					const recommendLabel = attributes.tiersBasedOptions[ index ].recommendLabel || '';
					return (
						<div
							key={ index }
							className={ classNames( 'wpbnbd__tiers__tier', {
								'wpbnbd__tiers__tier--recommended': recommendLabel.length,
							} ) }
						>
							<div className="wpbnbd__tiers__top">
								<h2 className="wpbnbd__tiers__heading">
									<RichText
										onChange={ handleTierOptionChange( index, 'heading' ) }
										placeholder={ __( 'Heading…', 'newspack-blocks' ) }
										value={ attributes.tiersBasedOptions[ index ].heading }
										tagName="span"
									/>
								</h2>
								<h3
									className="wpbnbd__tiers__recommend-label"
									style={ { opacity: recommendLabel ? 1 : 0.5 } }
								>
									<RichText
										onChange={ handleTierOptionChange( index, 'recommendLabel' ) }
										placeholder={ __( '…', 'newspack-blocks' ) }
										value={ recommendLabel }
										tagName="span"
									/>
								</h3>
							</div>
							<div className="wpbnbd__tiers__amount">
								<h3 className="wpbnbd__tiers__amount__number">
									{ props.settings.currencySymbol }
									<span>{ amount }</span>
								</h3>
								<span className="wpbnbd__tiers__amount__frequency">
									{ ' ' }
									{ getFrequencyLabel( currentFrequency ) }
								</span>
							</div>
							<button
								type="submit"
								style={ {
									borderColor: attributes.buttonColor,
									...( isAnyRecommended && ! recommendLabel
										? {
												backgroundColor: 'transparent',
												color: attributes.buttonColor,
										  }
										: {
												backgroundColor: attributes.buttonColor,
												color: getColorForContrast( attributes.buttonColor ),
										  } ),
								} }
							>
								<RichText
									onChange={ handleTierOptionChange( index, 'buttonText' ) }
									placeholder={ __( 'Button text…', 'newspack-blocks' ) }
									value={ attributes.tiersBasedOptions[ index ].buttonText }
									tagName="span"
								/>
							</button>
							<div className="wpbnbd__tiers__description">
								<RichText
									onChange={ handleTierOptionChange( index, 'description' ) }
									placeholder={ __( 'Description…', 'newspack-blocks' ) }
									value={ attributes.tiersBasedOptions[ index ].description }
									tagName="span"
								/>
							</div>
						</div>
					);
				} ) }
			</div>
		</form>
	);
};

export default TierBasedLayout;
