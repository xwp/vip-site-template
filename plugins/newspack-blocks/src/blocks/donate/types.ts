import { FREQUENCY_SLUGS } from './consts';

export type DonationFrequencySlug = 'once' | 'month' | 'year';

export type DonationFormInputName = `donation_value_${ DonationFrequencySlug }`;
type DonationFormInputOtherName = `donation_value_${ DonationFrequencySlug }_other`;
type DonationFormInputUntieredName = `donation_value_${ DonationFrequencySlug }_untiered`;

export type DonationFormValues = {
	[ key in DonationFormInputName ]: string;
} & {
	[ key in DonationFormInputOtherName ]: string;
} & {
	[ key in DonationFormInputUntieredName ]: string;
} & {
	amount: string;
	donation_frequency: DonationFrequencySlug;
	email: string;
	full_name: string;
	cid?: string;
	agree_to_pay_fees?: string;
	newsletter_opt_in?: string;
	newspack_popup_id?: string;
	_wp_http_referer?: string;
};

export type DonationSettings = {
	currency: string;
	currencySymbol: string;
	siteName: string;
	isCurrencyZeroDecimal: boolean;
	countryCode: string;
	availableFrequencies: typeof FREQUENCY_SLUGS;
	feeMultiplier: number;
	feeStatic: number;
	stripePublishableKey: string;
	paymentRequestType: string;
	captchaSiteKey: string;
	minimumDonation: number;
	amounts: DonationAmounts;
	tiered: boolean;
	disabledFrequencies: {
		[ Key in DonationFrequencySlug as string ]: boolean;
	};
	platform: string;
};

export type EditState = Pick<
	DonationSettings,
	'amounts' | 'currencySymbol' | 'tiered' | 'disabledFrequencies' | 'minimumDonation' | 'platform'
>;

export type DonationAmountsArray = [ number, number, number, number ];
export type DonationAmounts = {
	[ Key in DonationFrequencySlug as string ]: DonationAmountsArray;
};

export type OverridableConfiguration = {
	amounts: DonationAmounts;
	tiered: boolean;
	disabledFrequencies: {
		[ Key in DonationFrequencySlug as string ]: boolean;
	};
	minimumDonation: number;
};

export type TierBasedOptionValue = {
	heading: string;
	description: string;
	buttonText: string;
	recommendLabel: string;
};

export type AdditionalField = {
	type: 'text';
	name: string;
	label: string;
	isRequired: boolean;
	width: number;
	isNew?: boolean;
	fieldIndex?: number;
};

export type DonateBlockAttributes = OverridableConfiguration & {
	buttonText: string;
	buttonWithCCText: string;
	// https://stripe.com/docs/stripe-js/elements/payment-request-button
	paymentRequestType: 'donate' | 'default' | 'book' | 'buy';
	buttonColor: string;
	thanksText: string;
	defaultFrequency: DonationFrequencySlug;
	campaign: string;
	className: string;
	layoutOption: 'frequency' | 'tiers';
	// For tiers-based layout option.
	tiersBasedOptions: [ TierBasedOptionValue, TierBasedOptionValue, TierBasedOptionValue ];
	// Manual mode enables block-level overrides of the global Donate settings.
	manual: boolean;
	// Legacy attributes.
	suggestedAmounts?: [ number, number, number ];
	suggestedAmountUntiered?: number;
	minimumDonation: number;
	additionalFields: AdditionalField[];
};

export type ComponentProps = {
	attributes: DonateBlockAttributes;
	settings: EditState;
	setAttributes: ( attributes: Partial< DonateBlockAttributes > ) => void;
	setSettings: ( settings: Partial< EditState > ) => void;
	amounts: DonationAmounts;
	availableFrequencies: typeof FREQUENCY_SLUGS;
};

export type EditProps = {
	attributes: DonateBlockAttributes;
	setAttributes: ( attributes: Partial< DonateBlockAttributes > ) => void;
	className: string;
};
