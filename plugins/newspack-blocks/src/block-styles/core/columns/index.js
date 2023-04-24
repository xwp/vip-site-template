/**
 * External dependencies
 */
import { registerBlockStyle } from '@wordpress/blocks';
import './editor.scss';

registerBlockStyle( 'core/columns', {
	name: 'borders',
	label: 'Borders',
} );

registerBlockStyle( 'core/columns', {
	name: 'first-col-to-second',
	label: 'Move first column to second',
} );

registerBlockStyle( 'core/columns', {
	name: 'first-col-to-third',
	label: 'Move first column to third',
} );
