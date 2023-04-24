/**
 * External dependencies
 */
import 'intersection-observer';

/**
 * Internal dependencies
 */
import { shouldPolyfillAMPModule } from './utils';
import * as store from './store';

const parseAnimationElement = animationElement => {
	try {
		const config = JSON.parse( animationElement.children[ 0 ].innerText );
		return {
			config,
			id: animationElement.getAttribute( 'id' ),
			trigger: animationElement.getAttribute( 'trigger' ),
		};
	} catch ( error ) {
		return false;
	}
};

const parseAnimationConfg = ( animationSpec, animation ) => {
	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	const { keyframes, animations, selector, ...keyframesConfig } = {
		...animationSpec.config, // Contains default values for the whole animation.
		...animation, // Values of this animation.
	};
	keyframesConfig.iterations = parseInt( keyframesConfig.iterations ) || undefined;
	keyframesConfig.delay = parseInt( keyframesConfig.delay ) || undefined;
	keyframesConfig.duration = parseInt( keyframesConfig.duration ) || undefined;
	return { keyframes, keyframesConfig };
};

const runAnimation = animationSpec => {
	animationSpec.config.animations.forEach( animation => {
		const animatedElements = document.querySelectorAll( animation.selector );
		[ ...animatedElements ].forEach( animatedElement => {
			const { keyframes, keyframesConfig } = parseAnimationConfg( animationSpec, animation );
			animatedElement.animate( keyframes, keyframesConfig );
		} );
	} );
};

export const runAnimationById = id => {
	if ( store.get( id ) ) {
		runAnimation( store.get( id ) );
	}
};

const visibilityObserver = new IntersectionObserver( entries => {
	entries.forEach( observerEntry => {
		if ( observerEntry.isIntersecting ) {
			runAnimationById( observerEntry.target.getAttribute( 'data-anim-id' ) );
			visibilityObserver.unobserve( observerEntry.target );
		}
	} );
} );

const setupAnimations = animationSpec => {
	animationSpec.config.animations.forEach( animation => {
		const animatedElements = document.querySelectorAll( animation.selector );
		[ ...animatedElements ].forEach( animatedElement => {
			if ( animationSpec.trigger === 'visibility' ) {
				visibilityObserver.observe( animatedElement );
			}
			store.set( animationSpec.id, animationSpec );
			animatedElement.setAttribute( 'data-anim-id', animationSpec.id );
		} );
	} );
};

const manageAnimation = animationElement =>
	setupAnimations( parseAnimationElement( animationElement ) );

export const manageAnimations = () => {
	if ( ! shouldPolyfillAMPModule( 'animation' ) ) {
		return;
	}
	[ ...document.querySelectorAll( 'amp-animation' ) ].map( manageAnimation );
};
