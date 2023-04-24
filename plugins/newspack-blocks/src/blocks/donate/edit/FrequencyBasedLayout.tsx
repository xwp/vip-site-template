/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useMemo, useEffect, useRef } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { RichText } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { AmountValueInput } from './components';
import { getColorForContrast } from '../utils';
import { FREQUENCIES } from '../consts';
import type { DonateBlockAttributes, ComponentProps, DonationFrequencySlug } from '../types';

const PAYMENT_REQUEST_BUTTON_TYPE_OPTIONS: {
	label: string;
	value: DonateBlockAttributes[ 'paymentRequestType' ];
}[] = [
	{ label: __( 'Donate', 'newspack-blocks' ), value: 'donate' },
	{ label: __( 'Pay', 'newspack-blocks' ), value: 'default' },
	{ label: __( 'Book', 'newspack-blocks' ), value: 'book' },
	{ label: __( 'Buy', 'newspack-blocks' ), value: 'buy' },
];

const FrequencyBasedLayout = ( props: { isTiered: boolean } & ComponentProps ) => {
	// Unique identifier to prevent collisions with other Donate blocks' labels.
	const uid = useMemo( () => Math.random().toString( 16 ).slice( 2 ), [] );

	const { attributes, settings, amounts, availableFrequencies, setAttributes } = props;

	const formRef = useRef< HTMLFormElement >( null );

	// Update selected frequency when available frequencies change.
	useEffect( () => {
		if ( formRef.current ) {
			const formValues = Object.fromEntries( new FormData( formRef.current ) );
			if ( ! formValues.donation_frequency && formRef.current.elements ) {
				const frequencyRadioInput = formRef.current.elements[ 0 ];
				if ( frequencyRadioInput instanceof HTMLInputElement ) {
					frequencyRadioInput.click();
				}
			}
		}
		if ( availableFrequencies.indexOf( attributes.defaultFrequency ) === -1 ) {
			setAttributes( { defaultFrequency: availableFrequencies[ 0 ] } );
		}
	}, [ attributes.disabledFrequencies ] );

	// Update selected frequency when the default frequency attribute is updated.
	useEffect( () => {
		if ( formRef.current ) {
			const defaultFrequencyInput = formRef.current.querySelector(
				`[name="donation_frequency"][value="${ attributes.defaultFrequency }"]`
			);
			if ( defaultFrequencyInput instanceof HTMLInputElement ) {
				defaultFrequencyInput.click();
			}
		}
	}, [ attributes.defaultFrequency ] );

	const isRenderingStripePaymentForm =
		window.newspack_blocks_data?.is_rendering_stripe_payment_form;

	const renderFrequencySelect = ( frequencySlug: DonationFrequencySlug ) => (
		<>
			<input
				type="radio"
				value={ frequencySlug }
				id={ `newspack-donate-${ frequencySlug }-${ uid }` }
				name="donation_frequency"
				defaultChecked={ frequencySlug === attributes.defaultFrequency }
			/>
			<label
				htmlFor={ 'newspack-donate-' + frequencySlug + '-' + uid }
				className="wpbnbd__button freq-label"
			>
				{ FREQUENCIES[ frequencySlug ] }
			</label>
		</>
	);

	const displayAmount = ( amount: number ) => amount.toFixed( 2 ).replace( /\.?0*$/, '' );

	const renderUntieredForm = () => (
		<div className="wp-block-newspack-blocks-donate__options">
			<div className="wp-block-newspack-blocks-donate__frequencies frequencies">
				{ availableFrequencies.map( frequencySlug => (
					<div
						className="wp-block-newspack-blocks-donate__frequency frequency"
						key={ frequencySlug }
					>
						{ renderFrequencySelect( frequencySlug ) }
						<div className="input-container">
							<label
								className="donate-label"
								htmlFor={ 'newspack-' + frequencySlug + '-' + uid + '-untiered-input' }
							>
								{ __( 'Donation amount', 'newspack-blocks' ) }
							</label>
							<div className="wp-block-newspack-blocks-donate__money-input money-input">
								<span className="currency">{ settings.currencySymbol }</span>
								<AmountValueInput
									{ ...props }
									frequencySlug={ frequencySlug }
									tierIndex={ 3 }
									id={ `newspack-${ frequencySlug }-${ uid }-untiered-input` }
								/>
							</div>
						</div>
					</div>
				) ) }
			</div>
		</div>
	);

	const renderTieredForm = () => (
		<div className="wp-block-newspack-blocks-donate__options">
			<div className="wp-block-newspack-blocks-donate__frequencies frequencies">
				{ availableFrequencies.map( frequencySlug => (
					<div
						className="wp-block-newspack-blocks-donate__frequency frequency"
						key={ frequencySlug }
					>
						{ renderFrequencySelect( frequencySlug ) }

						<div className="wp-block-newspack-blocks-donate__tiers tiers">
							{ amounts[ frequencySlug ].map( ( suggestedAmount, index: number ) => {
								const isOtherTier = index === 3;
								const id = `newspack-tier-${ frequencySlug }-${ uid }-${
									isOtherTier ? 'other' : index
								}`;
								return (
									<div
										className={ classNames(
											'wp-block-newspack-blocks-donate__tier',
											`wp-block-newspack-blocks-donate__tier--${
												isOtherTier ? 'other' : 'frequency'
											}`
										) }
										key={ index }
									>
										<input
											type="radio"
											value={ isOtherTier ? 'other' : suggestedAmount }
											className={ isOtherTier ? 'other-input' : 'frequency-input' }
											id={ id }
											name={ `donation_value_${ frequencySlug }` }
											defaultChecked={ index === 1 }
										/>
										<label className="tier-select-label tier-label" htmlFor={ id }>
											{ isOtherTier
												? __( 'Other', 'newspack-blocks' )
												: settings.currencySymbol + displayAmount( suggestedAmount ) }
										</label>
										{ isOtherTier ? (
											<>
												<label className="odl" htmlFor={ id + '-other-input' }>
													{ __( 'Donation amount', 'newspack-blocks' ) }
												</label>
												<div className="wp-block-newspack-blocks-donate__money-input money-input">
													<span className="currency">{ settings.currencySymbol }</span>
													<AmountValueInput
														{ ...props }
														frequencySlug={ frequencySlug }
														tierIndex={ index }
														id={ `${ id }-other-input` }
													/>
												</div>
											</>
										) : null }
									</div>
								);
							} ) }
						</div>
					</div>
				) ) }
			</div>
		</div>
	);

	const renderButton = () => (
		<button
			type="submit"
			onClick={ evt => evt.preventDefault() }
			style={ {
				backgroundColor: attributes.buttonColor,
				color: getColorForContrast( attributes.buttonColor ),
			} }
		>
			{ isRenderingStripePaymentForm ? (
				<RichText
					onChange={ ( value: string ) => setAttributes( { buttonWithCCText: value } ) }
					placeholder={ __( 'Button text…', 'newspack-blocks' ) }
					value={ attributes.buttonWithCCText }
					tagName="span"
				/>
			) : (
				<RichText
					onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
					placeholder={ __( 'Button text…', 'newspack-blocks' ) }
					value={ attributes.buttonText }
					tagName="span"
				/>
			) }
		</button>
	);

	const selectedPaymentRequestTypeOption = PAYMENT_REQUEST_BUTTON_TYPE_OPTIONS.find(
		option => option.value === attributes.paymentRequestType
	);
	const selectedPaymentRequestType = selectedPaymentRequestTypeOption
		? selectedPaymentRequestTypeOption.value
		: 'donate';

	const renderFooter = () => (
		<>
			<p className="wp-block-newspack-blocks-donate__thanks thanks">
				<RichText
					onChange={ ( value: string ) => setAttributes( { thanksText: value } ) }
					placeholder={ __( 'Thank you text…', 'newspack-blocks' ) }
					value={ attributes.thanksText }
					tagName="span"
				/>
			</p>
			{ isRenderingStripePaymentForm ? (
				<div className="wp-block-newspack-blocks-donate__stripe stripe-payment">
					<div className="stripe-payment__inputs">
						<input
							className="stripe-payment__element stripe-payment__card"
							type="text"
							placeholder={ __( 'Card number', 'newspack-blocks' ) }
						/>
						<div className="stripe-payment__row stripe-payment__row--flex">
							<input required placeholder="Email" type="email" name="email" />
							<input required placeholder="Full Name" type="text" name="full_name" />
						</div>
						<div className="stripe-payment__row stripe-payment__row--additional-fields">
							{ attributes.additionalFields.map( ( field, index ) => (
								<input
									key={ index }
									type="text"
									name={ field.name }
									placeholder={ field.label }
									style={ { width: `calc(${ field.width }% - 0.5rem)` } }
								/>
							) ) }
						</div>
					</div>

					<div className="stripe-payment__row stripe-payment__row--flex stripe-payment__footer">
						<div className="stripe-payment__methods">
							<div className="stripe-payment__request-button">
								<SelectControl
									options={ PAYMENT_REQUEST_BUTTON_TYPE_OPTIONS }
									value={ selectedPaymentRequestType }
									onChange={ (
										paymentRequestType: DonateBlockAttributes[ 'paymentRequestType' ]
									) => setAttributes( { paymentRequestType } ) }
								/>
								{ __( 'with Apple/Google Pay', 'newspack-blocks' ) }
							</div>
							{ renderButton() }
						</div>
					</div>
				</div>
			) : (
				renderButton()
			) }
		</>
	);

	return (
		<form ref={ formRef }>
			{ props.isTiered ? renderTieredForm() : renderUntieredForm() }
			{ renderFooter() }
		</form>
	);
};
export default FrequencyBasedLayout;
