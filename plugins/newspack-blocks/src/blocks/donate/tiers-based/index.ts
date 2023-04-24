/**
 * Internal dependencies
 */
import './style.scss';
import handleTiersBasedElement from './view';

/**
 * Specify a function to execute when the DOM is fully loaded.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/packages/dom-ready/
 *
 * @param {Function} callback A function to execute after the DOM is ready.
 * @return {void}
 */
function domReady( callback: () => void ): void {
	if ( typeof document === 'undefined' ) {
		return;
	}
	if (
		document.readyState === 'complete' || // DOMContentLoaded + Images/Styles/etc loaded, so we call directly.
		document.readyState === 'interactive' // DOMContentLoaded fires at this point, so we call directly.
	) {
		return void callback();
	}
	// DOMContentLoaded has not fired yet, delay callback until then.
	document.addEventListener( 'DOMContentLoaded', callback );
}

export const processTiersBasedElements = ( parentEl = document ) => {
	const elements = parentEl.querySelectorAll( '.wpbnbd--tiers-based' ) as NodeListOf< HTMLElement >;
	elements.forEach( handleTiersBasedElement );
};

if ( typeof window !== 'undefined' ) {
	domReady( () => processTiersBasedElements() );
}
