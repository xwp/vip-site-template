/* global gtag */

/**
 * External dependencies
 */
import 'intersection-observer';

/**
 * Internal dependencies
 */
import {
	values,
	performXHRequest,
	substituteDynamicValue,
	processFormData,
	getCookieValueFromLinker,
	parseDynamicURL,
	shouldPolyfillAMPModule,
} from './utils';

const getAnalyticsConfigs = () =>
	[ ...document.querySelectorAll( 'amp-analytics' ) ].map( ampAnalyticsElement => ( {
		type: ( ampAnalyticsElement.getAttribute( 'type' ) || '' ).trim(),
		config: ( ampAnalyticsElement.getAttribute( 'config' ) || '' ).trim(),
		...( ampAnalyticsElement.children.length
			? {
					...JSON.parse( ampAnalyticsElement.children[ 0 ].innerText ),
			  }
			: {} ),
	} ) );

export const manageAnalyticsLinkers = () => {
	if ( ! shouldPolyfillAMPModule( 'analytics' ) ) {
		return;
	}
	getAnalyticsConfigs().forEach( config => {
		// Linker reader – if incoming from AMP Cache, read linker param and set cookie and a linker-less URL.
		// https://github.com/ampproject/amphtml/blob/master/extensions/amp-analytics/linker-id-receiving.md
		const { cookieValue, cleanURL } = getCookieValueFromLinker( config );
		if ( cookieValue ) {
			document.cookie = cookieValue;
		}
		if ( cleanURL ) {
			window.history.replaceState( {}, document.title, cleanURL );
		}
	} );
};

export const manageAnalyticsEvents = () => {
	if ( ! shouldPolyfillAMPModule( 'analytics' ) ) {
		return;
	}
	getAnalyticsConfigs().forEach( ( { type, config, requests, triggers } ) => {
		/**
		 * Fetch remote GTAG config and trigger GTAG reporting using it.
		 */
		if ( typeof gtag !== 'undefined' && type === 'gtag' && config ) {
			fetch( parseDynamicURL( config ) )
				.then( response => response.json() )
				.then( remoteConfig => {
					const gaId = remoteConfig?.vars?.gtag_id;
					if ( gaId ) {
						gtag( 'config', gaId, remoteConfig.vars.config[ gaId ] );
					}
				} );
		}

		if ( triggers && requests ) {
			const triggerSpecs = values( triggers );
			const visibilityHandlers = [];

			let observer;
			const hasVisibilityTriggers =
				triggerSpecs.filter( ( { on } ) => on === 'visible' ).length > 0;
			if ( hasVisibilityTriggers ) {
				const timers = {};
				observer = new IntersectionObserver(
					entries => {
						entries.forEach( observerEntry => {
							const visibilitySpecsForEntry = visibilityHandlers.filter( handler =>
								observerEntry.target.matches( handler.selector )
							);
							visibilitySpecsForEntry.forEach( visibilitySpec => {
								if ( observerEntry.isIntersecting ) {
									if ( ! timers[ visibilitySpec.id ] ) {
										timers[ visibilitySpec.id ] = setTimeout( () => {
											performXHRequest( visibilitySpec.request );
											if ( ! visibilitySpec.repeat ) {
												observer.unobserve( observerEntry.target );
											}
										}, visibilitySpec.totalTimeMin || 0 );
									}
								} else if ( timers[ visibilitySpec.id ] ) {
									clearTimeout( timers[ visibilitySpec.id ] );
									timers[ visibilitySpec.id ] = false;
								}
							} );
						} );
					},
					{
						// The threshold should be the value of the visibilitySpec's visiblePercentageMin,
						// but that would require a separate IntersectionObserver for each trigger.
						// Since it's value the same for every popup, it can be hardcoded.
						threshold: 0.5,
					}
				);
			}

			triggerSpecs.forEach( ( { on, extraUrlParams = {}, request, ...trigger }, i ) => {
				const url = requests[ request ];
				let element;

				// Data for the XHR request.
				const data = {};
				if ( extraUrlParams ) {
					Object.keys( extraUrlParams ).forEach( key => {
						data[ key ] = substituteDynamicValue( extraUrlParams[ key ] );
					} );
				}

				switch ( on ) {
					case 'visible':
						element = document.querySelector( trigger.visibilitySpec?.selector );
						if ( element && observer ) {
							observer.observe( element );
							visibilityHandlers.push( {
								...trigger.visibilitySpec,
								request: { data, url },
								id: i,
							} );
						}
						break;
					case 'amp-form-submit-success':
						element = document.querySelector( trigger.selector );
						if ( element ) {
							element.addEventListener( 'submit', () => {
								performXHRequest( {
									url,
									data: processFormData( extraUrlParams, element ),
								} );
							} );
						}
						break;
					case 'ini-load':
						performXHRequest( { url, data } );
						break;
					default:
						throw new Error( `Trigger "${ on }" not handled` );
				}
			} );
		}
	} );
};
