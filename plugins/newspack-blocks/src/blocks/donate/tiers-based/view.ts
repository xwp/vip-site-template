import { setupSlider } from './slider';
import { parseTiersBasedConfig } from './utils';

const BUTTON_ACTIVE_CLASSNAME = 'wpbnbd__button--active';

const getSelectedAmountSelector = ( frequency: string ) =>
	`.wpbnbd__tiers__amount [data-frequency-slug="${ frequency }"]`;

type SelectedFrequency = 'month' | 'year';

export default ( parentEl: HTMLElement ) => {
	const config = parseTiersBasedConfig( parentEl.getAttribute( 'data-tiers-based-config' ) || '' );
	if ( ! config ) {
		return;
	}
	const frequencyButtonsEls = parentEl.querySelectorAll( '.wpbnbd__tiers__selection button' );
	const amountsEls: NodeListOf< HTMLElement > = parentEl.querySelectorAll(
		'.wpbnbd__tiers__amount [data-frequency-slug]'
	);
	const frequencyInputEl = parentEl.querySelector( `input[name="${ config.params.frequency }"]` );
	const initFormEl = parentEl.querySelector( 'form[data-is-init-form]' );
	if ( ! frequencyButtonsEls || ! amountsEls || ! frequencyInputEl || ! initFormEl ) {
		return;
	}

	setupSlider( parentEl, config );

	let selectedFrequency: SelectedFrequency = config.initialFrequency;
	let selectedTierIndex = 0;

	// Frequency choosing interaction.
	frequencyButtonsEls.forEach( buttonEl => {
		buttonEl.addEventListener( 'click', () => {
			const maybeSelectedFrequency = buttonEl.getAttribute(
				'data-frequency-slug'
			) as SelectedFrequency | null;
			if ( ! maybeSelectedFrequency ) {
				return;
			}

			selectedFrequency = maybeSelectedFrequency;

			// Set hidden input value.
			frequencyInputEl.setAttribute( 'value', selectedFrequency );

			// Toggle the amount & frequency UI.
			amountsEls.forEach( amountEl => ( amountEl.style.display = 'none' ) );
			const selectedAmountEls: NodeListOf< HTMLElement > = parentEl.querySelectorAll(
				getSelectedAmountSelector( selectedFrequency )
			);
			selectedAmountEls.forEach( el => ( el.style.display = 'inline' ) );

			// Toggle the frequency buttons' active states.
			frequencyButtonsEls.forEach( el => el.classList.toggle( BUTTON_ACTIVE_CLASSNAME ) );

			// Update the value attributes in the form's submit buttons.
			const submitButtonsEls = parentEl.querySelectorAll( 'button[type="submit"]' );
			submitButtonsEls.forEach( ( _buttonEl: Element ) => {
				if ( ! selectedFrequency ) {
					return;
				}
				_buttonEl.setAttribute( 'name', `${ config.params.tierPrefix }${ selectedFrequency }` );
				const buttonTierIndex = _buttonEl.getAttribute( 'data-tier-index' );
				const amountEl = document.querySelector(
					`${ getSelectedAmountSelector(
						selectedFrequency
					) }[data-tier-index="${ buttonTierIndex }"]`
				);
				if ( ! amountEl ) {
					return;
				}
				const value = amountEl.getAttribute( 'data-amount' );
				if ( value ) {
					_buttonEl.setAttribute( 'value', value );
				}
			} );
		} );
	} );

	// View transitions.
	const backButton = parentEl.querySelector( 'button.wpbnbd__tiers__back-button' ) as HTMLElement;
	const tierSelectionButtonsEls = parentEl.querySelectorAll( '.wpbnbd__tiers__tier button' );
	const viewEls = parentEl.querySelectorAll( '.wpbnbd__tiers__view' );
	const toggleView = () =>
		viewEls.forEach( viewEl => viewEl.classList.toggle( 'wpbnbd__tiers__view--hidden' ) );

	tierSelectionButtonsEls.forEach( buttonEl => {
		buttonEl.addEventListener( 'click', () => {
			const tierIndex = parseInt( buttonEl.getAttribute( 'data-tier-index' ) || '' );
			selectedTierIndex = tierIndex;
			const tierHeadingEl: HTMLElement | null = parentEl.querySelector(
				'.wpbnbd__tiers__tier-tile h2'
			);
			const tierAmountEl: HTMLElement | null = parentEl.querySelector(
				'.wpbnbd__tiers__tier-tile span[data-amount]'
			);
			const tierFrequencyLabelEl: HTMLElement | null = parentEl.querySelector(
				'.wpbnbd__tiers__tier-tile span[data-frequency]'
			);
			const renewsOnEl: HTMLElement | null = parentEl.querySelector(
				'.wpbnbd__tiers__tier-tile span[data-renews-date]'
			);
			if (
				isNaN( tierIndex ) ||
				! tierHeadingEl ||
				! tierAmountEl ||
				! tierFrequencyLabelEl ||
				! renewsOnEl
			) {
				return;
			}

			const tierOption = config.tiersBasedOptions[ tierIndex ];
			const frequencyLabel = parentEl
				.querySelector( `[data-frequency-slug="${ selectedFrequency }"]` )
				?.getAttribute( 'data-frequency-label' );
			tierHeadingEl.textContent = tierOption.heading;
			tierFrequencyLabelEl.textContent = frequencyLabel || '';
			tierAmountEl.textContent = config.amounts[ selectedFrequency ][ tierIndex ];
			renewsOnEl.textContent = config.renewsAt[ selectedFrequency ];

			toggleView();
		} );
	} );

	if ( config.isRenderingStripePaymentForm ) {
		initFormEl.addEventListener( 'submit', e => {
			e.preventDefault();

			// Update values for the payment form.
			const paymentFormEl = parentEl.querySelector( 'form[data-is-streamlined-form]' );
			if ( ! paymentFormEl ) {
				return;
			}
			const paymentFormAmountInputEl = paymentFormEl.querySelector(
				'input[data-is-streamlined-input-amount]'
			);
			const paymentFormFrequencyInputEl = paymentFormEl.querySelector(
				`input[name="${ config.params.frequency }"]`
			);
			if ( ! paymentFormAmountInputEl || ! paymentFormFrequencyInputEl ) {
				return;
			}
			const amount = config.amounts[ selectedFrequency ][ selectedTierIndex ];
			paymentFormAmountInputEl.setAttribute( 'value', amount );
			paymentFormAmountInputEl.setAttribute(
				'name',
				`${ config.params.tierPrefix }${ selectedFrequency }`
			);
			paymentFormFrequencyInputEl.setAttribute( 'value', selectedFrequency );
			// Trigger a change event, so the UI updates.
			paymentFormEl.dispatchEvent( new Event( 'change' ) );
		} );
		backButton?.addEventListener( 'click', toggleView );
	}

	window.addEventListener( `newspackPaymentFlowComplete-${ parentEl.id }`, () => {
		if ( backButton ) {
			backButton.style.display = 'none';
		}
	} );
};
