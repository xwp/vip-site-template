/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import { loadStripe } from '@stripe/stripe-js/pure';
import type * as Stripe from '@stripe/stripe-js/types';
import 'regenerator-runtime'; // Required in WP >=5.8.

/**
 * Internal dependencies
 */
import * as utils from './utils';
import './style.scss';

const getAdditionalFieldsValues = ( formElement: HTMLFormElement ) => {
	const fields = [
		...formElement.querySelectorAll( 'input[data-is-additional-field]' ),
	] as HTMLInputElement[];
	return fields.map( field => ( { name: field.name, value: field.value } ) );
};

export const processStreamlinedElements = ( parentElement = document ) =>
	[ ...parentElement.querySelectorAll( '.stripe-payment' ) ].forEach( async el => {
		let stripe: Stripe.Stripe | null,
			cardElement: Stripe.StripeCardElement,
			paymentRequest: Stripe.PaymentRequest,
			paymentRequestToken: Stripe.Token,
			isCardUIVisible = false;

		const formElement: HTMLFormElement | null = el.closest( 'form' );
		const messagesEl: HTMLDivElement | null = el.querySelector( '.stripe-payment__messages' );
		if ( ! formElement || ! messagesEl ) {
			return;
		}

		const blockWrapperElement = el.closest( '.wpbnbd' );
		const paymentFlowCompleteEventName = `newspackPaymentFlowComplete-${
			blockWrapperElement?.id || ''
		}`;

		const settings = utils.getSettings( formElement );

		const disableForm = () => el.classList.add( 'stripe-payment--disabled' );
		const enableForm = () => el.classList.remove( 'stripe-payment--disabled' );
		enableForm();

		// Display CC UI.
		const displayCardUI = () => {
			if ( isCardUIVisible ) {
				return;
			}
			const inputsHiddenEl = el.querySelector( '.stripe-payment__inputs.stripe-payment--hidden' );
			if ( inputsHiddenEl ) {
				inputsHiddenEl.classList.remove( 'stripe-payment--hidden' );
				isCardUIVisible = true;
			}
		};

		const getCaptchaToken = async ( captchaSiteKey: string ) => {
			return new Promise( ( res, rej ) => {
				const { grecaptcha } = window;
				if ( ! grecaptcha ) {
					return res( '' );
				}

				if ( ! grecaptcha?.ready || ! captchaSiteKey ) {
					rej( __( 'Error loading the reCaptcha library.', 'newspack-blocks' ) );
				}

				grecaptcha.ready( async () => {
					try {
						const token = await grecaptcha.execute( captchaSiteKey, { action: 'submit' } );
						return res( token );
					} catch ( e ) {
						rej( e );
					}
				} );
			} );
		};

		// Universal payment handling, for both card and payment request button flows.
		// In card flow, this will happen after user submits their card data in the HTML form.
		// In payment request flow, this will happen after the user validates the payment in
		// the browser/OS UI.
		const payWithSource = async (
			source: Stripe.Source,
			token: Stripe.Token | undefined = undefined,
			/**
			 * Overrides for sent donation data. In a card flow the data is
			 * provided explicitly by the user (via the form), but in payment request flow
			 * the data is from the payment request event.
			 */
			requestPayloadOverrides = {}
		) => {
			if ( ! stripe ) {
				return;
			}

			// Add reCaptcha challenge to form submission, if available.
			const captchaSiteKey = settings?.captchaSiteKey;
			let captchaToken;
			if ( captchaSiteKey ) {
				try {
					captchaToken = await getCaptchaToken( captchaSiteKey );
				} catch ( e ) {
					const errorMessage =
						e instanceof Error
							? e.message
							: __( 'Error processing captcha request.', 'newspack-blocks' );
					utils.renderMessages( [ errorMessage ], messagesEl );
					return { error: true };
				}
			}
			const formValues = utils.getDonationFormValues( formElement );
			const promptOrigin = formElement.closest( 'amp-layout.newspack-popup' );

			// If the donation originated from a Campaigns prompt, append the prompt ID to the event label.
			const origin =
				promptOrigin && promptOrigin.hasAttribute( 'amp-access' )
					? promptOrigin.getAttribute( 'amp-access' )
					: null;

			const additionalFields: { name: string; value: string }[] =
				getAdditionalFieldsValues( formElement );

			const apiRequestPayload = {
				captchaToken,
				stripe_tokenization_method: token ? token.card?.tokenization_method : 'card',
				stripe_source_id: source.id,
				amount: utils.getTotalAmount( formElement ),
				email: formValues.email,
				full_name: formValues.full_name,
				frequency: formValues.donation_frequency,
				newsletter_opt_in: Boolean( formValues.newsletter_opt_in ),
				newspack_popup_id: formValues.newspack_popup_id,
				_wp_http_referer: formValues._wp_http_referer,
				clientId: formValues.cid,
				origin,
				additional_fields: additionalFields,
				...requestPayloadOverrides,
			};

			const chargeResultData = await utils.sendAPIRequest( '/donate', apiRequestPayload );

			// Error handling.
			if ( chargeResultData.data?.status !== 200 && chargeResultData.message ) {
				utils.renderMessages( [ chargeResultData.message ], messagesEl );
				return { error: true };
			}
			if ( chargeResultData.error ) {
				utils.renderMessages( [ chargeResultData.error ], messagesEl );
				return { error: true };
			}

			const exitWithError = ( errorMessage: Stripe.StripeError[ 'message' ] ) => {
				utils.renderMessages( [ errorMessage ], messagesEl );
				enableForm();
				window.dispatchEvent( new Event( paymentFlowCompleteEventName ) );
				return { error: true };
			};

			// Additional steps handling.
			if ( chargeResultData.client_secret ) {
				const confirmationResult = await stripe.confirmCardPayment(
					chargeResultData.client_secret
				);

				if ( confirmationResult.error ) {
					return exitWithError( confirmationResult.error.message );
				} else if ( confirmationResult.paymentIntent.status === 'succeeded' ) {
					utils.renderSuccessMessageWithEmail( apiRequestPayload.email, messagesEl );
				} else if ( confirmationResult.paymentIntent.status === 'requires_action' ) {
					// Could not test this flow (with 3D Secure test cards), but it's listed in the docs:
					// https://stripe.com/docs/stripe-js/elements/payment-request-button#html-js-complete-payment
					const { error: confirmationError } = await stripe.confirmCardPayment(
						chargeResultData.client_secret
					);
					if ( confirmationError ) {
						return exitWithError( confirmationError.message );
					}
					utils.renderSuccessMessageWithEmail( apiRequestPayload.email, messagesEl );
				} else {
					return exitWithError(
						__(
							'Something went wrong with the payment. Please try again later.',
							'newspack-blocks'
						)
					);
				}
			}

			if ( chargeResultData.status === 'success' ) {
				utils.renderSuccessMessageWithEmail( apiRequestPayload.email, messagesEl );
			}
			window.dispatchEvent( new Event( paymentFlowCompleteEventName ) );
			return {};
		};

		const initStripe = async () => {
			stripe = await loadStripe( settings.stripePublishableKey );
			if ( ! stripe ) {
				return;
			}
			const cardElementContainer: HTMLElement | null = el.querySelector( '.stripe-payment__card' );
			if ( ! cardElementContainer ) {
				return;
			}
			const elements = stripe.elements();

			// Handle card element.
			cardElement = elements.create( 'card' );
			cardElement.mount( cardElementContainer );

			// Handle payment request button (Apple/Google Pay). This has to be initialised to see if
			// such payments are available in the browser (canMakePayment method).
			paymentRequest = stripe.paymentRequest( {
				country: settings.countryCode,
				currency: settings.currency,
				total: utils.getPaymentRequestTotal( formElement ),
				// Docs: Use the requestPayerName parameter to collect the payer’s billing address for Apple Pay.
				requestPayerName: true,
				requestPayerEmail: true,
			} );

			const rendersPaymentRequestButton = await paymentRequest.canMakePayment();
			if ( rendersPaymentRequestButton ) {
				paymentRequest.on( 'source', async event => {
					const result = await payWithSource( event.source, paymentRequestToken, {
						email: event.payerEmail,
						full_name: event.payerName,
					} );
					// The UI messages are handled in payWithSource, this event listener only
					// has to notify the browser that the payment is done.
					event.complete( result?.error ? 'fail' : 'success' );
				} );
				paymentRequest.on( 'token', async event => {
					paymentRequestToken = event.token;
				} );
				// Update payment request when the form values are updated.
				formElement.addEventListener( 'change', () => {
					paymentRequest.update( {
						total: utils.getPaymentRequestTotal( formElement ),
					} );
				} );
				// Create and mount the payment request button.
				const prButton = elements.create( 'paymentRequestButton', {
					paymentRequest,
					style: {
						paymentRequestButton: {
							type: settings.paymentRequestType,
							height: '46px',
						},
					},
				} );
				const prButtonElement: HTMLButtonElement | null = el.querySelector(
					'.stripe-payment__request-button'
				);
				if ( ! prButtonElement ) {
					return;
				}
				prButton.mount( prButtonElement );
				prButtonElement.classList.remove( 'stripe-payment--hidden' );
				setTimeout( () => {
					prButtonElement.classList.remove( 'stripe-payment__request-button--invisible' );
				}, 0 );
			} else {
				displayCardUI();
			}

			el.classList.remove( 'stripe-payment--invisible' );
		};

		// Initialise Stripe once the element is visible.
		const observer = new IntersectionObserver( entries => {
			if ( entries[ 0 ].isIntersecting ) {
				initStripe();
			}
		} );
		observer.observe( el );

		// Card form unravelling.
		const submitButtonEl: HTMLButtonElement | null = el.querySelector( 'button[type="submit"]' );
		if ( submitButtonEl ) {
			submitButtonEl.onclick = e => {
				if ( ! isCardUIVisible ) {
					e.preventDefault();
					displayCardUI();
				}
			};
		}

		const updateFeesAmount = () => {
			const feesAmountEl = el.querySelector( '#stripe-fees-amount' );
			if ( feesAmountEl ) {
				const formValues = Object.fromEntries( new FormData( formElement ) );
				const feeAmount = utils.getFeeAmount( formElement );
				if ( feeAmount === 0 ) {
					const feesAmountContainerEl: HTMLElement | null = el.querySelector(
						'#stripe-fees-amount-container'
					);
					if ( feesAmountContainerEl ) {
						feesAmountContainerEl.style.display = 'none';
					}
				}
				if ( typeof formValues.donation_frequency === 'string' ) {
					feesAmountEl.innerHTML = `(${ settings.currencySymbol }${ feeAmount.toFixed(
						2
					) } ${ settings.frequencies[ formValues.donation_frequency ].toLowerCase() })`;
				}
			}
		};

		updateFeesAmount();
		formElement.addEventListener( 'change', updateFeesAmount );

		// Card payment flow – on form submission.
		formElement.addEventListener( 'submit', async e => {
			if ( ! stripe ) {
				return;
			}
			e.preventDefault();
			disableForm();
			utils.renderMessages(
				[ __( 'Processing payment…', 'newspack-blocks' ) ],
				messagesEl,
				'info'
			);

			const formValues = utils.getDonationFormValues( formElement );
			const validationErrors = Object.values( utils.validateFormData( formValues, settings ) );
			if ( validationErrors.length > 0 ) {
				utils.renderMessages( validationErrors, messagesEl );
				enableForm();
				return;
			}

			const handleStripeSDKError = ( error: Stripe.StripeError ) => {
				if ( error.message ) {
					validationErrors.push( error.message );
				}
				utils.renderMessages( validationErrors, messagesEl );
				enableForm();
			};

			const sourceCreationResult = await stripe.createSource( cardElement, {
				type: 'card',
				owner: { name: formValues.full_name, email: formValues.email },
			} );
			if ( sourceCreationResult.error ) {
				handleStripeSDKError( sourceCreationResult.error );
				return;
			}
			await payWithSource( sourceCreationResult.source );
			if ( window.newspackReaderActivation?.refreshAuthentication ) {
				window.newspackReaderActivation.refreshAuthentication();
			}
		} );
	} );

processStreamlinedElements();
