/* globals newspack_popups_view */

/**
 * WordPress dependencies
 */
import { getQueryArg, removeQueryArgs, getQueryString } from '@wordpress/url';

/**
 * External dependencies
 */
import { parse, stringify } from 'qs';

export const values = object => Object.keys( object ).map( key => object[ key ] );

export const performXHRequest = ( { url, data } ) => {
	const XHR = new XMLHttpRequest();
	XHR.open( 'POST', url );
	XHR.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

	const encodedData = Object.keys( data )
		.map( key => encodeURIComponent( key ) + '=' + encodeURIComponent( data[ key ] ) )
		.join( '&' )
		.replace( /%20/g, '+' );

	XHR.send( encodedData );
};

export const getCookies = () =>
	document.cookie.split( '; ' ).reduce( ( acc, cookieStr ) => {
		const cookie = cookieStr.split( '=' );
		acc[ cookie[ 0 ] ] = cookie[ 1 ];
		return acc;
	}, {} );

export const getClientIDValue = () => getCookies()[ newspack_popups_view.cid_cookie_name ];

export const setCookie = ( name, value, expirationDays = 365 ) => {
	const date = new Date();
	date.setTime( date.getTime() + expirationDays * 24 * 60 * 60 * 1000 );
	document.cookie = `${ name }=${ value }; expires=${ date.toUTCString() }; path=/`;
};

/**
 * Replace a dynamic value, like a client ID, in a string.
 *
 * @param {string} value A string to replace value in.
 * @return {string} String with the value replaced.
 */
export const substituteDynamicValue = value => {
	if ( value ) {
		const trimmedValue = String( value ).replace( /\s/g, '' );
		switch ( trimmedValue ) {
			case 'CLIENT_ID(newspack-cid)':
				value = getClientIDValue() || '';
				break;
			case 'DOCUMENT_REFERRER':
				value = document.referrer || '';
				break;
		}
	}
	return value;
};

/**
 * Replace dynamic values in a URL.
 *
 * @param {string} url A URL with dynamic values.
 * @return {string} URL with the values replaced.
 */
export const parseDynamicURL = url => {
	const parsed = parse( getQueryString( url ) );
	Object.keys( parsed ).forEach( key => {
		parsed[ key ] = substituteDynamicValue( parsed[ key ] );
	} );
	const withoutQuery = url.substring( 0, url.indexOf( '?' ) );
	return `${ withoutQuery }?${ stringify( parsed ) }`;
};

/**
 * Given a data object and a form HTML element,
 * update the data with values from the form.
 *
 * @param {Object}          data        An object.
 * @param {HTMLFormElement} formElement A form element.
 * @return {Object} Updated data.
 */
export const processFormData = ( data, formElement ) => {
	Object.keys( data ).forEach( key => {
		let value = data[ key ];
		if ( -1 < value.indexOf( '${formFields' ) ) {
			const inputEl = formElement.querySelector( '[name="email"], [type="email"]' );
			if ( inputEl ) {
				value = inputEl.value;
			}
		}
		data[ key ] = substituteDynamicValue( value );
	} );
	return data;
};

// Get the hash from a URL without any query strings.
const getHash = url => {
	const hash = new URL( url ).hash.split( /\?|\&/ );

	return hash[ 0 ];
};

/**
 * Given an amp-analytics configuration, a current url, and cookies,
 * retrieve client ID related linker param to be inserted into site cookies.
 *
 * @param {Object} config                           amp-analytics configuration.
 * @param {Object} config.linkers                   Linkers configuration.
 * @param {Object} config.cookies                   Cookies configuration.
 * @param {string} [url=window.location.href]       A URL, presumably with the linker param.
 * @param {string} [documentCookie=document.cookie] The cookie.
 * @return {Object} Cookie value and a clean URL – without the linker param.
 */
export const getCookieValueFromLinker = (
	{ linkers, cookies },
	url = window.location.href,
	documentCookie = document.cookie
) => {
	let cookieValue;
	let cleanURL = url;
	if ( linkers && linkers.enabled && cookies && cookies.enabled ) {
		const linkerName = Object.keys( linkers ).filter( k => k !== 'enabled' )[ 0 ];
		const cookieName = Object.keys( cookies ).filter( k => k !== 'enabled' )[ 0 ];
		const linkerParam = getQueryArg( url, linkerName );
		const hasCIDCookie = documentCookie.indexOf( cookieName ) >= 0;

		// URLs with a hash fragment preceding a query string won't be able to extract the query string by itself.
		// Let's remove the hash fragment before processing the query string, then add it back afterward.
		const hash = getHash( url );
		if ( hash ) {
			cleanURL = url.replace( hash, '' );
		}
		cleanURL = removeQueryArgs( cleanURL, linkerName ) + hash;

		// Strip trailing `?` character from clean URL.
		if ( '?' === cleanURL.charAt( cleanURL.length - 1 ) ) {
			cleanURL = cleanURL.slice( 0, cleanURL.length - 1 );
		}

		if ( linkerParam && ! hasCIDCookie ) {
			// eslint-disable-next-line @typescript-eslint/no-unused-vars
			const [ version, checksum, cidName, cidValue ] = linkerParam.split( '*' );
			try {
				// Strip dots, not sure why they were in a URL – maybe chrome devtools?
				const decodedCID = atob( cidValue.replace( /\./g, '' ) );
				if ( decodedCID ) {
					cookieValue = `${ cookieName }=${ decodedCID }`;
				}
			} catch ( e ) {
				// Nothingness.
			}
		}
	}
	return { cookieValue, cleanURL };
};

export const waitUntil = ( condition, callback, maxTries = 10 ) => {
	let tries = 0;
	const interval = setInterval( () => {
		tries++;
		if ( tries <= maxTries ) {
			if ( condition() ) {
				callback();
				clearInterval( interval );
			}
		} else {
			clearInterval( interval );
		}
	}, 200 );
};

/**
 * If an AMP module was loaded, e.g. via another plugin or a custom header script, it should not be polyfilled.
 */
export const shouldPolyfillAMPModule = name => undefined === customElements.get( `amp-${ name }` );

export const parseOnHandlers = onAttributeValue =>
	onAttributeValue
		.split( ';' )
		.filter( Boolean )
		.map( onHandler => /(?<action>\w*):(?<id>(\w|-)*)\.(?<method>.*)/.exec( onHandler ).groups );
