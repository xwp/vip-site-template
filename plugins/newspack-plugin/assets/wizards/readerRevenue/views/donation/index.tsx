/**
 * WordPress dependencies.
 */
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { ToggleControl, CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { MoneyInput } from '../../components';
import {
	Button,
	Card,
	Grid,
	Notice,
	SectionHeader,
	SelectControl,
	TextControl,
	Wizard,
} from '../../../../components/src';
import { READER_REVENUE_WIZARD_SLUG } from '../../constants';

type FrequencySlug = 'once' | 'month' | 'year';

const FREQUENCIES: {
	[ Key in FrequencySlug as string ]: { tieredLabel: string; staticLabel: string };
} = {
	once: {
		tieredLabel: __( 'One-time donations' ),
		staticLabel: __( 'Suggested one-time donation amount' ),
	},
	month: {
		tieredLabel: __( 'Monthly donations' ),
		staticLabel: __( 'Suggested donation amount per month' ),
	},
	year: {
		tieredLabel: __( 'Annual donations' ),
		staticLabel: __( 'Suggested donation amount per year' ),
	},
};
const FREQUENCY_SLUGS: FrequencySlug[] = Object.keys( FREQUENCIES ) as FrequencySlug[];

type WizardData = {
	donation_data:
		| { errors: { [ key: string ]: string[] } }
		| {
				amounts: {
					[ Key in FrequencySlug as string ]: [ number, number, number, number ];
				};
				disabledFrequencies: {
					[ Key in FrequencySlug as string ]: boolean;
				};
				currencySymbol: string;
				tiered: boolean;
				minimumDonation: string;
				billingFields: string[];
		  };
	donation_page: {
		editUrl: string;
		status: string;
	};
	available_billing_fields: {
		[ key: string ]: {
			autocomplete: string;
			class: string[];
			label: string;
			priority: number;
			required: boolean;
			type: string;
			validate: string[];
		};
	};
};

export const DonationAmounts = () => {
	const wizardData = Wizard.useWizardData( 'reader-revenue' ) as WizardData;
	const { updateWizardSettings } = useDispatch( Wizard.STORE_NAMESPACE );

	if ( ! wizardData.donation_data || 'errors' in wizardData.donation_data ) {
		return null;
	}

	const { amounts, currencySymbol, tiered, disabledFrequencies, minimumDonation } =
		wizardData.donation_data;

	const changeHandler = path => value =>
		updateWizardSettings( {
			slug: 'newspack-reader-revenue-wizard',
			path: [ 'donation_data', ...path ],
			value,
		} );

	const availableFrequencies = FREQUENCY_SLUGS.map( slug => ( {
		key: slug,
		...FREQUENCIES[ slug ],
	} ) );

	// Minimum donation is returned by the REST API as a string.
	const minimumDonationFloat = parseFloat( minimumDonation );

	return (
		<>
			<Card headerActions noBorder>
				<SectionHeader
					title={ __( 'Suggested Donations', 'newspack' ) }
					description={ __(
						'Set suggested donation amounts. These will be the default settings for the Donate block.',
						'newspack'
					) }
					noMargin
				/>
				<SelectControl
					label={ __( 'Donation Type', 'newspack' ) }
					onChange={ () => changeHandler( [ 'tiered' ] )( ! tiered ) }
					buttonOptions={ [
						{ value: true, label: __( 'Tiered', 'newspack' ) },
						{ value: false, label: __( 'Untiered', 'newspack' ) },
					] }
					buttonSmall
					value={ tiered }
					hideLabelFromVision
				/>
			</Card>
			{ tiered ? (
				<Grid columns={ 1 } gutter={ 16 }>
					{ availableFrequencies.map( section => {
						const isFrequencyDisabled = disabledFrequencies[ section.key ];
						const isOneFrequencyActive =
							Object.values( disabledFrequencies ).filter( Boolean ).length ===
							FREQUENCY_SLUGS.length - 1;
						return (
							<Card isMedium key={ section.key }>
								<Grid columns={ 1 } gutter={ 16 }>
									<ToggleControl
										checked={ ! isFrequencyDisabled }
										onChange={ () =>
											changeHandler( [ 'disabledFrequencies', section.key ] )(
												! isFrequencyDisabled
											)
										}
										label={ section.tieredLabel }
										disabled={ ! isFrequencyDisabled && isOneFrequencyActive }
									/>
									{ ! isFrequencyDisabled && (
										<Grid columns={ 3 } rowGap={ 16 }>
											<MoneyInput
												currencySymbol={ currencySymbol }
												label={ __( 'Low-tier' ) }
												error={
													amounts[ section.key ][ 0 ] < minimumDonationFloat
														? __(
																'Warning: suggested donations should be at least the minimum donation amount.',
																'newspack'
														  )
														: null
												}
												value={ amounts[ section.key ][ 0 ] }
												min={ minimumDonationFloat }
												onChange={ changeHandler( [ 'amounts', section.key, 0 ] ) }
											/>
											<MoneyInput
												currencySymbol={ currencySymbol }
												label={ __( 'Mid-tier' ) }
												error={
													amounts[ section.key ][ 1 ] < minimumDonationFloat
														? __(
																'Warning: suggested donations should be at least the minimum donation amount.',
																'newspack'
														  )
														: null
												}
												value={ amounts[ section.key ][ 1 ] }
												min={ minimumDonationFloat }
												onChange={ changeHandler( [ 'amounts', section.key, 1 ] ) }
											/>
											<MoneyInput
												currencySymbol={ currencySymbol }
												label={ __( 'High-tier' ) }
												error={
													amounts[ section.key ][ 2 ] < minimumDonationFloat
														? __(
																'Warning: suggested donations should be at least the minimum donation amount.',
																'newspack'
														  )
														: null
												}
												value={ amounts[ section.key ][ 2 ] }
												min={ minimumDonationFloat }
												onChange={ changeHandler( [ 'amounts', section.key, 2 ] ) }
											/>
										</Grid>
									) }
								</Grid>
							</Card>
						);
					} ) }
				</Grid>
			) : (
				<Card isMedium>
					<Grid columns={ 3 } rowGap={ 16 }>
						{ availableFrequencies.map( section => (
							<MoneyInput
								currencySymbol={ currencySymbol }
								label={ section.staticLabel }
								value={ amounts[ section.key ][ 3 ] }
								min={ minimumDonationFloat }
								error={
									amounts[ section.key ][ 3 ] < minimumDonationFloat
										? __(
												'Warning: suggested donations should be at least the minimum donation amount.',
												'newspack'
										  )
										: null
								}
								onChange={ changeHandler( [ 'amounts', section.key, 3 ] ) }
								key={ section.key }
							/>
						) ) }
					</Grid>
				</Card>
			) }
			<Card headerActions noBorder>
				<SectionHeader
					title={ __( 'Minimum Donation', 'newspack' ) }
					description={ __(
						'Set minimum donation amount. Setting a reasonable minimum donation amount can help protect your site from bot attacks.',
						'newspack'
					) }
					noMargin
				/>
				<TextControl
					label={ __( 'Minimum donation', 'newspack' ) }
					type="number"
					min={ 1 }
					value={ minimumDonationFloat }
					onChange={ value => changeHandler( [ 'minimumDonation' ] )( value ) }
				/>
			</Card>
		</>
	);
};

const BillingFields = () => {
	const wizardData = Wizard.useWizardData( 'reader-revenue' ) as WizardData;

	const { updateWizardSettings } = useDispatch( Wizard.STORE_NAMESPACE );

	if ( ! wizardData.donation_data || 'errors' in wizardData.donation_data ) {
		return null;
	}

	const changeHandler = path => value =>
		updateWizardSettings( {
			slug: 'newspack-reader-revenue-wizard',
			path: [ 'donation_data', ...path ],
			value,
		} );

	const availableFields = wizardData.available_billing_fields;
	if ( ! availableFields || ! Object.keys( availableFields ).length ) {
		return null;
	}

	const billingFields = wizardData.donation_data.billingFields.length
		? wizardData.donation_data.billingFields
		: Object.keys( availableFields );

	return (
		<>
			<Card noBorder headerActions>
				<SectionHeader
					title={ __( 'Billing Fields', 'newspack' ) }
					description={ __(
						'Configure which billing fields should be rendered on the donation form.',
						'newspack'
					) }
					noMargin
				/>
			</Card>
			<Grid columns={ 3 } rowGap={ 16 }>
				{ Object.keys( availableFields ).map( fieldKey => (
					<CheckboxControl
						key={ fieldKey }
						label={ availableFields[ fieldKey ].label }
						checked={ billingFields.includes( fieldKey ) }
						disabled={ fieldKey === 'billing_email' } // Email is always required.
						onChange={ () => {
							let newFields = [ ...billingFields ];
							if ( billingFields.includes( fieldKey ) ) {
								newFields = newFields.filter( field => field !== fieldKey );
							} else {
								newFields = [ ...newFields, fieldKey ];
							}
							changeHandler( [ 'billingFields' ] )( newFields );
						} }
					/>
				) ) }
			</Grid>
		</>
	);
};

const Donation = () => {
	const wizardData = Wizard.useWizardData( 'reader-revenue' ) as WizardData;

	const { saveWizardSettings } = useDispatch( Wizard.STORE_NAMESPACE );
	const onSave = () =>
		saveWizardSettings( {
			slug: READER_REVENUE_WIZARD_SLUG,
			section: 'donations',
			payloadPath: [ 'donation_data' ],
		} );

	return (
		<>
			{ wizardData.donation_page && (
				<>
					<Card noBorder headerActions>
						<h2>{ __( 'Donations Landing Page', 'newspack' ) }</h2>
						<Button
							variant="secondary"
							isSmall
							href={ wizardData.donation_page.editUrl }
							onClick={ undefined }
						>
							{ __( 'Edit Page' ) }
						</Button>
					</Card>
					{ 'publish' === wizardData.donation_page.status ? (
						<Notice
							isSuccess
							noticeText={ __(
								'Your donations landing page is set up and published.',
								'newspack'
							) }
						/>
					) : (
						<Notice
							isError
							noticeText={ __(
								"Your donations landing page has been created, but is not yet published. You can now edit it and publish when you're ready.",
								'newspack'
							) }
						/>
					) }
				</>
			) }
			<DonationAmounts />
			<BillingFields />
			<div className="newspack-buttons-card">
				<Button variant="primary" onClick={ onSave } href={ undefined }>
					{ __( 'Save Settings' ) }
				</Button>
			</div>
		</>
	);
};

export default Donation;
