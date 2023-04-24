import './preview.scss';

( function ( api, $ ) {
	$( document ).ready( function () {
		if ( 'undefined' !== typeof api.selectiveRefresh ) {
			api.selectiveRefresh.bind( 'partial-content-rendered', function ( placement ) {
				const data = placement.container.find( '[data-placement]' ).data( 'placement' );
				/**
				 * The placement preview render callback does not differ different hooks
				 * and renders all placement mocks. We should hide placement hooks that
				 * don't belong in this preview.
				 */
				const containerClasses = placement.container[ 0 ].classList;
				const hookClass = [ ...containerClasses ].find( className => /^hook-/.test( className ) );
				if ( hookClass ) {
					placement.container
						.find( `.newspack-ads__ad-placement-mock:not(.${ hookClass })` )
						.hide();
				}
				/**
				 * Toggle `stick-to-top` class on the placement container.
				 */
				if ( data?.stick_to_top ) {
					placement.container.addClass( 'stick-to-top' );
				} else {
					placement.container.removeClass( 'stick-to-top' );
				}
			} );
		}
	} );
} )( wp.customize, window.jQuery );
