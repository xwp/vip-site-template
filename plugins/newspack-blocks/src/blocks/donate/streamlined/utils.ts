/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { DonationFormValues, DonationFormInputName, DonationSettings } from '../types';

const isValidEmail = ( string: string ) => /\S+@\S+/.test( string );

export const validateFormData = (
	values: DonationFormValues,
	settings: Partial< DonationSettings >
) => {
	const errors: { [ key: string ]: string } = {};
	if ( ! isValidEmail( values.email ) ) {
		errors.email = __( 'Email address is invalid.', 'newspack-blocks' );
	}
	const { minimumDonation } = settings;
	if ( minimumDonation && parseFloat( values.amount ) < minimumDonation ) {
		errors.amount = sprintf(
			/* Translators: %d is minimum donation amount set in Reader Revenue wizard or block attributes. */
			__( 'Amount must be at least %d.', 'newspack-blocks' ),
			minimumDonation
		);
	}
	if ( values.full_name.length === 0 ) {
		errors.amount = __( 'Full name should be provided.', 'newspack-blocks' );
	}
	return errors;
};

/**
 * Renders UI messages in a given DOM element.
 */
export const renderMessages = (
	messages: ( string | undefined )[],
	el: HTMLElement,
	type = 'error'
) => {
	el.innerHTML = '';
	messages.forEach( message => {
		if ( ! message ) {
			return;
		}
		const messageEl = document.createElement( 'div' );
		messageEl.classList.add( `type-${ type }` );
		messageEl.innerHTML = message;
		el.appendChild( messageEl );
	} );

	if ( 'success' === type && el.parentElement ) {
		el.parentElement.replaceWith( el );
	}
};

const getCookies = () =>
	document.cookie.split( '; ' ).reduce< { [ key: string ]: string } >( ( acc, cookieStr ) => {
		const cookie = cookieStr.split( '=' );
		acc[ cookie[ 0 ] ] = cookie[ 1 ];
		return acc;
	}, {} );

/**
 * Retrieves donation settings passed via a `data-streamlined-config` HTML attribute.
 */
export const getSettings = ( el: HTMLElement ) => {
	const settingsElement: HTMLElement | null = el.closest( '[data-streamlined-config]' );
	const settings = settingsElement?.getAttribute( 'data-streamlined-config' );
	if ( ! settings ) {
		return {};
	}
	const [
		currency,
		currencySymbol,
		siteName,
		isCurrencyZeroDecimal,
		countryCode,
		frequencies,
		feeMultiplier,
		feeStatic,
		stripePublishableKey,
		paymentRequestType,
		captchaSiteKey,
		minimumDonation,
	] = JSON.parse( settings );
	return {
		currency: currency.toLowerCase(),
		currencySymbol,
		siteName,
		isCurrencyZeroDecimal,
		countryCode,
		frequencies,
		feeMultiplier: parseFloat( feeMultiplier ),
		feeStatic: parseFloat( feeStatic ),
		stripePublishableKey,
		paymentRequestType,
		captchaSiteKey,
		minimumDonation: parseFloat( minimumDonation ),
	};
};

/**
 * Retrieves form values from the donation form HTML element.
 */
export const getDonationFormValues = ( formElement: HTMLFormElement ): DonationFormValues => {
	const formValues = Object.fromEntries( new FormData( formElement ) ) as DonationFormValues;
	const valueKey: DonationFormInputName = `donation_value_${ formValues.donation_frequency }`;
	formValues.amount = formValues[ valueKey ];
	if ( formValues.amount === 'other' ) {
		formValues.amount = formValues[ `${ valueKey }_other` ];
	}
	if ( ! formValues.amount ) {
		formValues.amount = formValues[ `${ valueKey }_untiered` ];
	}
	if (
		formValues.cid &&
		typeof formValues.cid === 'string' &&
		formValues.cid.indexOf( 'CLIENT_ID' ) === 0
	) {
		// In non-AMP environment, the value will not be dynamically substituted by AMP runtime.
		formValues.cid = getCookies()[ 'newspack-cid' ];
	}
	return formValues;
};

/**
 * Computes the total amount of the donation, taking into account if the
 * donor has opted to cover the processing fee.
 */
export const getTotalAmount = (
	formElement: HTMLFormElement,
	// For the payment request button (Apple/Google Pay), the amount has to be
	// delivered in subunits.
	{ convertToSubunit } = { convertToSubunit: false }
) => {
	const settings = getSettings( formElement );
	const { amount = '0', agree_to_pay_fees: paysFees } = getDonationFormValues( formElement );

	const processAmount = ( amountToProcess: number ) =>
		convertToSubunit
			? amountToProcess * ( settings.isCurrencyZeroDecimal ? 1 : 100 )
			: amountToProcess;

	let feesAmount = 0;
	if ( paysFees ) {
		feesAmount = processAmount( getFeeAmount( formElement ) || 0 );
	}
	return processAmount( parseFloat( amount ) ) + feesAmount;
};

/**
 * Creates a `total` value for Stripe's `paymentRequest` creation and updating.
 */
export const getPaymentRequestTotal = ( formElement: HTMLFormElement ) => {
	const settings = getSettings( formElement );
	const { donation_frequency: frequency } = getDonationFormValues( formElement );
	const frequencyLabel = settings.frequencies[ frequency ];
	return {
		label: `${ settings.siteName } (${ frequencyLabel })`,
		amount: getTotalAmount( formElement, { convertToSubunit: true } ),
	};
};

/**
 * Computes the fee amount.
 */
export const computeFeeAmount = ( amount: number, feeMultiplier: number, feeStatic: number ) => {
	return parseFloat(
		( ( ( amount + feeStatic ) / ( 100 - feeMultiplier ) ) * 100 - amount ).toFixed( 2 )
	);
};

/**
 * Given the donation form HTML element, computes the fee amount.
 */
export const getFeeAmount = ( formElement: HTMLFormElement ) => {
	const { feeMultiplier, feeStatic } = getSettings( formElement );
	if (
		undefined === feeMultiplier ||
		undefined === feeStatic ||
		isNaN( feeMultiplier ) ||
		isNaN( feeStatic )
	) {
		return 0;
	}
	const { amount } = getDonationFormValues( formElement );
	return computeFeeAmount( parseFloat( amount ), feeMultiplier, feeStatic );
};

export const sendAPIRequest = async ( endpoint: string, data: object, method = 'POST' ) =>
	fetch( `/wp-json/newspack-blocks/v1${ endpoint }`, {
		method,
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( data ),
	} ).then( async res => {
		const responseData = await res.json();
		if ( res.ok ) {
			return responseData;
		}
		return { error: responseData };
	} );

export const renderSuccessMessageWithEmail = ( emailAddress: string, messagesEl: HTMLElement ) => {
	const successMessage = sprintf(
		/* Translators: %s is the email address of the current user. */
		__(
			'Your payment has been processed. Thank you for your contribution! You will receive a confirmation email at %s.',
			'newspack-blocks'
		),
		emailAddress
	);
	renderMessages( [ successMessage ], messagesEl, 'success' );
};
