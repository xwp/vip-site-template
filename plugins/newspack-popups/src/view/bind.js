/**
 * Internal dependencies
 */
import { parseOnHandlers, shouldPolyfillAMPModule } from './utils';

const manageBind = bindElement => {
	const onHandlers = parseOnHandlers( bindElement.getAttribute( 'on' ) );
	onHandlers.forEach( onHandler => {
		if ( onHandler.action === 'tap' ) {
			const handleClick = () => {
				if ( onHandler.method === 'hide' ) {
					const target = document.getElementById( onHandler.id );
					if ( target ) {
						target.setAttribute( 'hidden', '' );
					}
				}
			};
			bindElement.addEventListener( 'click', handleClick );
		}
	} );
};

export const manageBinds = () => {
	if ( ! shouldPolyfillAMPModule( 'bind' ) ) {
		return;
	}
	[ ...document.querySelectorAll( '.newspack-popup button[on]' ) ].map( manageBind );
};
