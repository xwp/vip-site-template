/**
 * Internal dependencies
 */
import { performXHRequest, processFormData } from './utils';

const manageSingleForm = container => {
	const forms = [ ...container.querySelectorAll( 'form.popup-action-form' ) ];
	forms.forEach( form => {
		form.addEventListener( 'submit', event => {
			const inputs = [ ...form.querySelectorAll( 'input' ) ];
			const data = inputs.reduce( ( acc, input ) => {
				acc[ input.name ] = input.value;
				return acc;
			}, {} );
			performXHRequest( {
				url: form.attributes[ 'action-xhr' ].value,
				data: processFormData( data, form ),
			} );
			event.preventDefault();
		} );
	} );
};

export const manageForms = () => {
	const popupsElements = [
		...document.querySelectorAll( '.newspack-lightbox, .newspack-inline-popup' ),
	];
	popupsElements.forEach( manageSingleForm );
};
