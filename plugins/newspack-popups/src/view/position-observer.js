/**
 * External dependencies
 */
import 'intersection-observer';

/**
 * Internal dependencies
 */
import { parseOnHandlers, shouldPolyfillAMPModule } from './utils';
import * as store from './store';
import { runAnimationById } from './animation';

const runEffect = effect => {
	if ( effect.id ) {
		runAnimationById( effect.id );
	}
};

const runById = id => {
	if ( store.get( id ) ) {
		const { on } = store.get( id );
		on.forEach( effect => {
			if ( effect.action === 'enter' ) {
				runEffect( effect );
			}
		} );
	}
};

const visibilityObserver = new IntersectionObserver( entries => {
	entries.forEach( observerEntry => {
		if ( observerEntry.isIntersecting ) {
			runById( observerEntry.target.getAttribute( 'id' ) );
			visibilityObserver.unobserve( observerEntry.target );
		}
	} );
} );

const managePositionObserver = positionObserverElement => {
	let config;
	try {
		config = {
			target: positionObserverElement.getAttribute( 'target' ),
			on: parseOnHandlers( positionObserverElement.getAttribute( 'on' ) ),
			once: positionObserverElement.hasAttribute( 'once' ),
		};
	} catch ( error ) {
		return;
	}
	let targetElement = positionObserverElement.parentElement;
	if ( config.target ) {
		targetElement = document.getElementById( config.target );
	}
	store.set( targetElement.getAttribute( 'id' ), config );
	visibilityObserver.observe( targetElement );
};

export const managePositionObservers = () => {
	if ( ! shouldPolyfillAMPModule( 'position-observer' ) ) {
		return;
	}
	[ ...document.querySelectorAll( 'amp-position-observer' ) ].map( managePositionObserver );
};
