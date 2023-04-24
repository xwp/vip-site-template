/**
 * External dependencies
 */
import classNames from 'classnames';
import { hooks } from 'newspack-components';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect, Fragment } from '@wordpress/element';
import {
	CheckboxControl,
	PanelBody,
	ExternalLink,
	Placeholder,
	Spinner,
	SelectControl,
	ToggleControl,
	TextControl,
	Button,
	Notice,
} from '@wordpress/components';
import { InspectorControls, ColorPaletteControl } from '@wordpress/block-editor';
import { isEmpty, pick } from 'lodash';

/**
 * Internal dependencies
 */
import { getMigratedAmount } from '../utils';
import type {
	DonationSettings,
	DonationFrequencySlug,
	DonationAmountsArray,
	EditState,
	EditProps,
} from '../types';
import TierBasedLayout from './TierBasedLayout';
import FrequencyBasedLayout from './FrequencyBasedLayout';
import { AmountValueInput, AdditionalFields } from './components';
import {
	FREQUENCIES,
	FREQUENCY_SLUGS,
	LAYOUT_OPTIONS,
	DISABLED_IN_TIERS_BASED_LAYOUT_TIER_INDEX,
} from '../consts';

const TIER_LABELS = [
	__( 'Low-tier', 'newspack-blocks' ),
	__( 'Mid-tier', 'newspack-blocks' ),
	__( 'High-tier', 'newspack-blocks' ),
	__( 'Other', 'newspack-blocks' ),
];

const Edit = ( { attributes, setAttributes, className }: EditProps ) => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const [ settings, setSettings ] = hooks.useObjectState< EditState >( {
		amounts: {},
		currencySymbol: '$',
		tiered: false,
		disabledFrequencies: {},
		minimumDonation: 5,
		platform: '',
	} );

	useEffect( () => {
		apiFetch< DonationSettings >( {
			path: '/newspack/v1/wizard/newspack-reader-revenue-wizard/donations',
		} )
			.then( ( donationSettings: DonationSettings ) => {
				setSettings( {
					amounts: donationSettings.amounts,
					currencySymbol: donationSettings.currencySymbol,
					tiered: donationSettings.tiered,
					disabledFrequencies: donationSettings.disabledFrequencies,
					minimumDonation: donationSettings.minimumDonation,
					platform: donationSettings.platform,
				} );

				if ( isEmpty( attributes.disabledFrequencies ) ) {
					setAttributes( { disabledFrequencies: donationSettings.disabledFrequencies } );
				}

				if ( isEmpty( attributes.minimumDonation ) ) {
					setAttributes( { minimumDonation: donationSettings.minimumDonation } );
				}

				// Migrate old attributes.
				if (
					isEmpty( attributes.amounts ) &&
					attributes.suggestedAmounts &&
					attributes.suggestedAmounts.length
				) {
					const untieredAmount =
						attributes.suggestedAmountUntiered || donationSettings.amounts.month[ 3 ];
					setAttributes( {
						suggestedAmounts: undefined,
						suggestedAmountUntiered: undefined,
						amounts: {
							once: getMigratedAmount( 'once', attributes.suggestedAmounts, untieredAmount ),
							month: getMigratedAmount( 'month', attributes.suggestedAmounts, untieredAmount ),
							year: getMigratedAmount( 'year', attributes.suggestedAmounts, untieredAmount ),
						},
					} );
				} else {
					setAttributes( { amounts: { ...donationSettings.amounts, ...attributes.amounts } } );
				}
			} )
			.catch( setError )
			.finally( () => setIsLoading( false ) );
	}, [] );

	if ( error.length ) {
		return (
			<Placeholder icon="warning" label={ __( 'Error', 'newspack-blocks' ) } instructions={ error }>
				<ExternalLink href="/wp-admin/admin.php?page=newspack-reader-revenue-wizard#/donations">
					{ __( 'Go to donation settings to troubleshoot.', 'newspack-blocks' ) }
				</ExternalLink>
			</Placeholder>
		);
	}

	if ( settings.platform === 'other' ) {
		return (
			<Placeholder
				icon="warning"
				label={ __( 'The Donate block will not be rendered.', 'newspack-blocks' ) }
				instructions={ __( 'The Reader Revenue platform is set to "other".', 'newspack-blocks' ) }
			>
				<ExternalLink href="/wp-admin/admin.php?page=newspack-reader-revenue-wizard#/donations">
					{ __( 'Go to donation settings to update the platform.', 'newspack-blocks' ) }
				</ExternalLink>
			</Placeholder>
		);
	}

	if ( isLoading ) {
		return <Placeholder icon={ <Spinner /> } className="component-placeholder__align-center" />;
	}

	const isTiered = attributes.manual ? attributes.tiered : settings.tiered;
	const canRenderTiersBasedLayout = Boolean(
		window.newspack_blocks_data?.can_render_tiers_based_layout
	);
	const isTierBasedLayoutEnabled =
		canRenderTiersBasedLayout && isTiered && attributes.layoutOption === 'tiers';

	const amounts = attributes.manual ? attributes.amounts : settings.amounts;
	const availableFrequencies = FREQUENCY_SLUGS.filter( slug =>
		attributes.manual
			? ! attributes.disabledFrequencies[ slug ]
			: ! settings.disabledFrequencies[ slug ]
	).filter( slug => ( isTierBasedLayoutEnabled ? slug !== 'once' : true ) );
	const displayedFrequencies = FREQUENCY_SLUGS.filter( slug =>
		isTierBasedLayoutEnabled ? slug !== 'once' : true
	);

	// Editor bug – initially, the default style is selected, but the class not applied.
	if ( className.indexOf( 'is-style' ) === -1 ) {
		className = className + ' is-style-default';
	}
	const getWrapperClassNames = ( classes: string[] = [] ) =>
		classNames(
			classes,
			className,
			'wpbnbd',
			`wpbnbd--${ isTierBasedLayoutEnabled ? 'tiers-based' : 'frequency-based' }`,
			`wpbnbd--platform-${ settings.platform }`,
			`wpbnbd-frequencies--${ availableFrequencies.length }`
		);

	const minimumDonation = attributes.manual ? attributes.minimumDonation : settings.minimumDonation;
	const displayedAmounts = { ...amounts };
	Object.keys( amounts ).forEach( frequency => {
		const amountsWithMinimum = amounts[ frequency ].map( amount =>
			Math.max( amount, minimumDonation )
		) as DonationAmountsArray;
		displayedAmounts[ frequency ] = amountsWithMinimum;
	} );

	const componentProps = {
		attributes,
		amounts,
		availableFrequencies,
		settings,
		setAttributes,
		setSettings,
	};

	const renderMinAmountWarning = () => (
		<p className="components-frequency-donations__error">
			{ sprintf(
				// Translators: %s is the currency symbol, %d is the minimum donation amount.
				__(
					'Warning: suggested donations should be at least the minimum donation amount (%1$s%2$d).',
					'newspack-blocks'
				),
				settings.currencySymbol,
				minimumDonation
			) }
		</p>
	);

	return (
		<>
			{ isTierBasedLayoutEnabled ? (
				<div className={ getWrapperClassNames() }>
					<TierBasedLayout { ...componentProps } amounts={ displayedAmounts } />
				</div>
			) : (
				<div className={ getWrapperClassNames( [ isTiered ? 'tiered' : 'untiered' ] ) }>
					<FrequencyBasedLayout
						isTiered={ isTiered }
						{ ...componentProps }
						amounts={ displayedAmounts }
					/>
				</div>
			) }

			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'newspack-blocks' ) }>
					<div className="newspack-blocks-donate__layout-selector">
						{ LAYOUT_OPTIONS.map( ( { label, key } ) => {
							const isSelected =
								key === 'tiers' ? isTierBasedLayoutEnabled : ! isTierBasedLayoutEnabled;
							return (
								<Button
									key={ key }
									variant={ isSelected ? 'primary' : 'secondary' }
									isPressed={ isSelected }
									onClick={ () => setAttributes( { layoutOption: key } ) }
									aria-current={ isSelected }
									disabled={ key === 'tiers' && ( ! canRenderTiersBasedLayout || ! isTiered ) }
								>
									{ label }
								</Button>
							);
						} ) }
					</div>
					{ ! isTiered && (
						<Notice isDismissible={ false } className="newspack-blocks-donate__notice">
							{ __(
								'Tiers layout is disabled if the block is set to render untiered.',
								'newspack-blocks'
							) }
						</Notice>
					) }
					{ ! isTierBasedLayoutEnabled && (
						<SelectControl
							label={ __( 'Default Tab', 'newspack-blocks' ) }
							value={ attributes.defaultFrequency }
							options={ availableFrequencies.map( key => ( {
								label: FREQUENCIES[ key ],
								value: key,
							} ) ) }
							onChange={ ( defaultFrequency: DonationFrequencySlug ) =>
								setAttributes( { defaultFrequency } )
							}
						/>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Suggested Donations', 'newspack-blocks' ) }>
					<ToggleControl
						checked={ Boolean( attributes.manual ) }
						onChange={ () => setAttributes( { manual: ! attributes.manual } ) }
						label={ __( 'Configure manually', 'newspack-blocks' ) }
					/>
					{ attributes.manual ? (
						<>
							<ToggleControl
								checked={ Boolean( attributes.tiered ) }
								onChange={ () => setAttributes( { tiered: ! attributes.tiered } ) }
								label={ __( 'Tiered', 'newspack-blocks' ) }
							/>
							{ attributes.tiered ? (
								<>
									<div className="components-frequency-donations">
										{ displayedFrequencies.map( ( frequency: DonationFrequencySlug ) => {
											const isFrequencyDisabled = attributes.disabledFrequencies[ frequency ];
											const disabledDisplayedFrequencyCount = Object.values(
												pick( attributes.disabledFrequencies, displayedFrequencies )
											).filter( Boolean ).length;
											const isOnlyOneFrequencyActive =
												displayedFrequencies.length - disabledDisplayedFrequencyCount === 1;
											return (
												<Fragment key={ frequency }>
													<CheckboxControl
														label={ FREQUENCIES[ frequency ] }
														checked={ ! isFrequencyDisabled }
														disabled={ ! isFrequencyDisabled && isOnlyOneFrequencyActive }
														onChange={ () => {
															setAttributes( {
																disabledFrequencies: {
																	...attributes.disabledFrequencies,
																	[ frequency ]: ! isFrequencyDisabled,
																},
															} );
														} }
													/>
													{ ! isFrequencyDisabled && (
														<div className="wp-block-newspack-blocks-donate__panel-inputs">
															{ amounts[ frequency ].reduce(
																( acc: boolean, suggestedAmount: number ) =>
																	suggestedAmount < minimumDonation ? true : acc,
																false
															) && renderMinAmountWarning() }

															{ amounts[ frequency ].map( ( suggestedAmount, tierIndex ) => (
																<AmountValueInput
																	ignoreMinimumAmount
																	{ ...componentProps }
																	key={ `amount-${ frequency }-${ tierIndex }` }
																	frequencySlug={ frequency }
																	tierIndex={ tierIndex }
																	label={ TIER_LABELS[ tierIndex ] }
																	id={ `${ frequency }-${ tierIndex }-amount` }
																	disabled={
																		isTierBasedLayoutEnabled &&
																		tierIndex === DISABLED_IN_TIERS_BASED_LAYOUT_TIER_INDEX
																	}
																/>
															) ) }
														</div>
													) }
												</Fragment>
											);
										} ) }
									</div>
								</>
							) : (
								<div className="components-frequency-donations">
									<div className="wp-block-newspack-blocks-donate__panel-inputs">
										{ FREQUENCY_SLUGS.reduce(
											( acc: boolean, frequencySlug: DonationFrequencySlug ) =>
												amounts[ frequencySlug ][ 3 ] < attributes.minimumDonation ? true : acc,
											false
										) && renderMinAmountWarning() }

										{ FREQUENCY_SLUGS.map( ( frequencySlug: DonationFrequencySlug ) => (
											<AmountValueInput
												ignoreMinimumAmount
												{ ...componentProps }
												key={ frequencySlug }
												frequencySlug={ frequencySlug }
												tierIndex={ 3 }
												label={ FREQUENCIES[ frequencySlug ] }
												id={ `${ frequencySlug }-${ 3 }-amount` }
											/>
										) ) }
									</div>
								</div>
							) }
							<TextControl
								className="components-frequency-donations__minimum-donation"
								type="number"
								label={ __( 'Minimum donation', 'newspack-blocks' ) }
								min={ 1 }
								onChange={ ( value: number ) => setAttributes( { minimumDonation: value } ) }
								value={ attributes.minimumDonation }
							/>
						</>
					) : (
						<p>
							{ __(
								'The Donate Block allows you to collect donations from readers. The fields are automatically defined based on your donation settings.',
								'newspack-blocks'
							) }
							<br />
							<br />
							<ExternalLink href="/wp-admin/admin.php?page=newspack-reader-revenue-wizard#/donations">
								{ __( 'Edit donation settings', 'newspack-blocks' ) }
							</ExternalLink>
						</p>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Styling', 'newspack-blocks' ) } initialOpen={ false }>
					<ColorPaletteControl
						value={ attributes.buttonColor }
						onChange={ ( buttonColor: string ) => setAttributes( { buttonColor } ) }
						label={ __( 'Button Color', 'newspack-blocks' ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Additional data fields', 'newspack-blocks' ) }
					initialOpen={ false }
				>
					<AdditionalFields attributes={ attributes } setAttributes={ setAttributes } />
				</PanelBody>
				<PanelBody title={ __( 'Campaign', 'newspack-blocks' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Campaign ID', 'newspack-blocks' ) }
						value={ attributes.campaign || '' }
						onChange={ ( value: string ) =>
							setAttributes( {
								campaign: value,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
};

export default Edit;
