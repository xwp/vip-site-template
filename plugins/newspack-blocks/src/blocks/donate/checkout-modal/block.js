/**
 * Specify a function to execute when the DOM is fully loaded.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/packages/dom-ready/
 *
 * @param {Function} callback A function to execute after the DOM is ready.
 * @return {void}
 */
function domReady( callback ) {
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

let iframeResizeObserver;

function closeCheckout( element ) {
	const iframe = element.querySelector( 'iframe' );
	if ( iframe ) {
		iframe.src = 'about:blank';
	}
	document.body.classList.remove( 'newspack-modal-checkout-open' );
	iframeResizeObserver.disconnect();
	element.style.display = 'none';
}

domReady( () => {
	const modalCheckout = document.querySelector( '.newspack-blocks-donate-checkout-modal' );
	if ( ! modalCheckout ) {
		return;
	}
	const spinner = document.querySelector( '.newspack-blocks-donate-checkout-modal__spinner' );
	const iframeName = 'newspack_modal_checkout';
	const modalCheckoutInput = document.createElement( 'input' );
	modalCheckoutInput.type = 'hidden';
	modalCheckoutInput.name = 'modal_checkout';
	modalCheckoutInput.value = '1';
	const modalContent = modalCheckout.querySelector(
		'.newspack-blocks-donate-checkout-modal__content'
	);
	const iframe = document.createElement( 'iframe' );
	iframe.name = iframeName;
	modalContent.appendChild( iframe );
	const blocks = document.querySelectorAll( '.wpbnbd.wpbnbd--platform-wc' );
	blocks.forEach( block => {
		const forms = block.querySelectorAll( 'form' );
		forms.forEach( form => {
			form.appendChild( modalCheckoutInput.cloneNode() );
			form.target = iframeName;
			form.addEventListener( 'submit', () => {
				spinner.style.display = 'flex';
				modalCheckout.style.display = 'block';
				document.body.classList.add( 'newspack-modal-checkout-open' );
				iframeResizeObserver = new ResizeObserver( entries => {
					if ( ! entries || ! entries.length ) {
						return;
					}
					const contentRect = entries[ 0 ].contentRect;
					if ( contentRect ) {
						modalContent.style.height = contentRect.top + contentRect.bottom + 'px';
					}
				} );
				iframe.addEventListener( 'load', () => {
					const location = iframe.contentWindow.location;
					// If RAS is available, set the front-end authentication.
					if ( window.newspackReaderActivation && location.href.indexOf( 'order-received' ) > -1 ) {
						const ras = window.newspackReaderActivation;
						const params = new Proxy( new URLSearchParams( location.search ), {
							get: ( searchParams, prop ) => searchParams.get( prop ),
						} );
						if ( params.email ) {
							ras.setReaderEmail( params.email );
							ras.setAuthenticated( true );
						}
					}
					const container = iframe.contentWindow.document.querySelector(
						'#newspack_modal_checkout'
					);
					if ( container ) iframeResizeObserver.observe( container );
					spinner.style.display = 'none';
				} );
			} );
		} );
	} );
	modalCheckout.addEventListener( 'click', ev => {
		if ( ev.target === modalCheckout ) {
			closeCheckout( modalCheckout );
		}
	} );
	const closeButtons = modalCheckout.querySelectorAll(
		'.newspack-blocks-donate-checkout-modal__close'
	);
	closeButtons.forEach( button => {
		button.addEventListener( 'click', ev => {
			ev.preventDefault();
			closeCheckout( modalCheckout );
		} );
	} );
} );
