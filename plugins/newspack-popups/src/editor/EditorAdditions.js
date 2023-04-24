/**
 * Popup-related editor changes.
 */

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { isOverlayPlacement, updateEditorColors } from './utils';

const EditorAdditions = () => {
	const meta = useSelect( select => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) );
	const { background_color, overlay_size, placement } = meta;

	// Update editor colors to match popup colors.
	useEffect( () => {
		updateEditorColors( background_color );
	}, [ background_color ] );

	// Setting editor size as per the popup size.
	useEffect( () => {
		const blockEditor = document.querySelector( '.block-editor-block-list__layout' );
		if ( blockEditor ) {
			blockEditor.classList.forEach( className => {
				if ( className.startsWith( 'is-size-' ) ) {
					blockEditor.classList.remove( className );
				}
			} );

			if ( isOverlayPlacement( placement ) ) {
				blockEditor.classList.add( `is-size-${ overlay_size }` );
			}
		}
	}, [ overlay_size, placement ] );
	return null;
};

export default EditorAdditions;
