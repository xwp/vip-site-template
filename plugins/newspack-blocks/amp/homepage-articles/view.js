/* eslint-disable */
let isFetching = false;
let isEndOfData = false;
buildLoadMoreHandler( document.querySelector( '.wp-block-newspack-blocks-homepage-articles' ) );
function buildLoadMoreHandler( blockWrapperEl ) {
	const btnEl = blockWrapperEl.querySelector( '[data-next]' );
	if ( ! btnEl ) {
		return;
	}
	const postsContainerEl = blockWrapperEl.querySelector( '[data-posts]' );
	btnEl.addEventListener( 'click', function() {
		if ( isFetching || isEndOfData ) {
			return false;
		}
		isFetching = true;
		blockWrapperEl.classList.remove( 'is-error' );
		blockWrapperEl.classList.add( 'is-loading' );
		AMP.getState( 'newspackHomepagePosts.exclude_ids' ).then( function( exclude_ids ) {
			const requestURL =
				btnEl.getAttribute( 'data-next' ) + '&exclude_ids=' + JSON.parse( exclude_ids ).join( ',' );
			apiFetchWithRetry( { url: requestURL, onSuccess, onError }, 3 );
		} );
		function onSuccess( data ) {
			AMP.getState( 'newspackHomepagePosts.exclude_ids' ).then( function( exclude_ids ) {
				AMP.setState( {
					newspackHomepagePosts: { exclude_ids: JSON.parse( exclude_ids ).concat( data.ids ) },
				} );
			} );
			if ( isPostsDataValid( data ) ) {
				data.items.forEach( item => {
					const tempDIV = document.createElement( 'div' );
					tempDIV.innerHTML = item.html.trim();
					postsContainerEl.appendChild( tempDIV.childNodes[ 0 ] );
				} );
				if ( data.next ) {
					btnEl.setAttribute( 'data-next', data.next );
				}
				if ( ! data.items.length || ! data.next ) {
					isEndOfData = true;
					blockWrapperEl.classList.remove( 'has-more-button' );
				}
				isFetching = false;
				blockWrapperEl.classList.remove( 'is-loading' );
			}
		}
		function onError() {
			isFetching = false;
			blockWrapperEl.classList.remove( 'is-loading' );
			blockWrapperEl.classList.add( 'is-error' );
		}
	} );
}
function apiFetchWithRetry( options, n ) {
	const xhr = new XMLHttpRequest();
	xhr.onreadystatechange = () => {
		if ( xhr.readyState !== 4 || n === 0 ) {
			return;
		}
		if ( xhr.status >= 200 && xhr.status < 300 ) {
			const data = JSON.parse( xhr.responseText );
			options.onSuccess( data );
			return;
		}
		options.onError();
		apiFetchWithRetry( options, n - 1 );
	};
	xhr.open( 'GET', options.url );
	xhr.send();
}
function isPostsDataValid( data ) {
	if (
		data &&
		hasOwnProp( data, 'items' ) &&
		hasOwnProp( data, 'next' ) &&
		Array.isArray( data.items ) &&
		data.items.length &&
		hasOwnProp( data.items[ 0 ], 'html' ) &&
		typeof data.items[ 0 ].html === 'string'
	) {
		return true;
	}

	return false;
}
function hasOwnProp( obj, prop ) {
	return Object.prototype.hasOwnProperty.call( obj, prop );
}
