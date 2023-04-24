/* globals newspack_popups_customizer_data, jQuery */
import cookies from 'js-cookie';

const resetCIDCookie = () => {
	if ( newspack_popups_customizer_data.cookie_name ) {
		// Remove cookies for all possible domains.
		window.location.host
			.split( '.' )
			.reduce( ( acc, _, i, arr ) => {
				acc.push( arr.slice( -( i + 1 ) ).join( '.' ) );
				return acc;
			}, [] )
			.map( domain =>
				cookies.remove( newspack_popups_customizer_data.cookie_name, {
					domain: `.${ domain }`,
				} )
			);

		// Set the client ID cookie to a unique preview session identifier.
		cookies.set( newspack_popups_customizer_data.cookie_name, `preview-${ Date.now() }`, {
			domain: '.' + window.location.host,
		} );
	}
};

( function () {
	wp.customize.bind( 'ready', function () {
		resetCIDCookie();
	} );
} )( jQuery );
