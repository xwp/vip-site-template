/* globals newspack_popups_view */

/**
 * Internal dependencies
 */
import { shouldPolyfillAMPModule, getCookies, setCookie, parseDynamicURL } from './utils';
import './access.scss';

const setClientIDCookieIfNotSet = () => {
	const clientIDCookieName = newspack_popups_view.cid_cookie_name;
	if ( ! getCookies()[ clientIDCookieName ] ) {
		// If entropy is an issue, https://www.npmjs.com/package/nanoid can be used.
		const getShortStringId = () => Math.floor( Math.random() * 999999999 ).toString( 36 );
		setCookie( clientIDCookieName, `${ getShortStringId() }${ getShortStringId() }` );
	}
};

export const manageAccess = () => {
	if ( ! shouldPolyfillAMPModule( 'access' ) ) {
		return;
	}
	setClientIDCookieIfNotSet();
	const ampAccessScript = document.getElementById( 'amp-access' );
	if ( ! ampAccessScript ) {
		return;
	}
	let ampAccessConfig;
	try {
		ampAccessConfig = JSON.parse( ampAccessScript.innerText );
	} catch ( error ) {}
	if ( ! ampAccessConfig ) {
		return;
	}
	const { authorization, namespace } = ampAccessConfig;

	fetch( parseDynamicURL( authorization ) )
		.then( response => response.json() )
		.then( data => {
			Object.keys( data ).forEach( key => {
				const element = document.querySelectorAll( `[amp-access='${ namespace }.${ key }']` );
				if ( element ) {
					const shouldDisplay = data[ key ];
					if ( shouldDisplay ) {
						element.forEach( el => el.removeAttribute( 'amp-access-hide' ) );
					}
				}
			} );
		} );
};
