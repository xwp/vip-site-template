/* global newspack_popups_admin */

/**
 * Internal dependencies
 */
import './admin.scss';

const toggle = document.querySelectorAll( '.newspack-campaigns-preview-toggle a' );
const { label_visible: labelVisible, label_hidden: labelHidden } = newspack_popups_admin;
let isHidden = parseInt( localStorage.getItem( 'newspackPopupsHide' ) );

if ( isHidden ) {
	document.body.classList.add( 'newspack-popups-hide-prompts' );
}

const toggleHandler = e => {
	e.preventDefault();
	isHidden = ! isHidden;
	document.body.classList.toggle( 'newspack-popups-hide-prompts' );
	localStorage.setItem( 'newspackPopupsHide', isHidden ? 1 : 0 );

	e.currentTarget.textContent = isHidden ? labelHidden : labelVisible;
};

for ( let i = 0; i < toggle.length; i++ ) {
	const thisToggle = toggle[ i ];
	thisToggle.addEventListener( 'click', toggleHandler );

	if ( isHidden ) {
		thisToggle.textContent = labelHidden;
	}
}
