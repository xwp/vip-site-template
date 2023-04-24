/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { registerBlockStyle } from '@wordpress/blocks';
import './editor.scss';

addFilter( 'blocks.registerBlockType', 'newspack-blocks', ( settings, name ) => {
	if ( name === 'core/group' ) {
		const { supports } = settings;
		const { align } = supports;
		if ( align.indexOf( 'left' ) === -1 ) {
			align.push( 'left' );
		}
		if ( align.indexOf( 'right' ) === -1 ) {
			align.push( 'right' );
		}
	}
	return settings;
} );

registerBlockStyle( 'core/group', {
	name: 'border',
	label: 'Border',
} );
