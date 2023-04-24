import { set, debounce } from 'lodash';

( function ( api, $ ) {
	api.bind( 'ready', function () {
		let controls = [];
		const sections = api.panel( 'newspack-ads' ).sections();
		sections.forEach( function ( section ) {
			controls = controls.concat( section.controls() );
		} );
		controls.forEach( function ( control ) {
			const container = control.container;
			if ( control.params.type !== 'newspack_ads_placement' ) {
				return;
			}
			let value;
			try {
				value = JSON.parse( control.setting.get() || '{}' );
			} catch ( e ) {
				value = { enabled: false };
			}
			container.find( '[data-provider]' ).hide();
			container.find( `[data-provider="${ value.provider }"]` ).show();
			const _update = debounce( function () {
				control.setting.set( JSON.stringify( value ) );
			}, 300 );
			const updateValue = ( hook, path, val ) => {
				if ( ! Array.isArray( path ) ) {
					path = [ path ];
				}
				if ( hook ) {
					path = [ 'hooks', hook ].concat( path );
				}
				value = set( value, path, val );
				_update();
			};
			const $toggle = container.find( '.placement-toggle input[type=checkbox]' );
			$toggle.on( 'change', function () {
				updateValue( '', 'enabled', $( this ).is( ':checked' ) );
			} );
			container.on( 'change', '.stick-to-top-checkbox input[type=checkbox]', function () {
				updateValue( '', 'stick_to_top', $( this ).is( ':checked' ) );
			} );
			container.find( '.placement-hook-control' ).each( function () {
				const $container = $( this );
				const $provider = $container.find( '.provider-select select' );
				const $adUnit = $container.find( '.ad-unit-select select' );
				const $biddersIds = $container.find( '.bidder-id-input input' );
				const hook = $container.data( 'hook' ) || '';
				$provider.on( 'change', function () {
					const val = $( this ).val();
					updateValue( hook, 'provider', val );
					// Clear ad unit values
					$adUnit.val( '' ).change();
					// Show provider specific inputs
					$container.find( '[data-provider]' ).hide();
					$container.find( `[data-provider="${ val }"]` ).show();
				} );
				$adUnit.on( 'change', function () {
					$toggle.attr( 'checked', true ).change();
					updateValue( hook, 'ad_unit', $( this ).val() );
				} );
				$biddersIds.on( 'change', function () {
					const bidderId = $( this ).data( 'bidder-id' );
					updateValue( hook, [ 'bidders_ids', bidderId ], $( this ).val() );
				} );
			} );
		} );
	} );
} )( window.wp.customize, window.jQuery );
