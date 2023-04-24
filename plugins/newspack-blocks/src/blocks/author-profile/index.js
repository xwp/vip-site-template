/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { Icon, postAuthor } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import edit from './edit';

/**
 * Style dependencies - will load in editor
 */
import './editor.scss';
import './view.scss';
import metadata from './block.json';
const { name, attributes, category } = metadata;

// Name must be exported separately.
export { name };

export const title = __( 'Author Profile', 'newspack-blocks' );

// Add Newspack author custom fields to the block attributes.
const authorCustomFields = window.newspack_blocks_data?.author_custom_fields || [];
authorCustomFields.forEach( field => {
	attributes[ `show${ field.name }` ] = {
		type: 'boolean',
		default: true,
	};
} );

export const settings = {
	title,
	icon: {
		src: <Icon icon={ postAuthor } />,
		foreground: '#36f',
	},
	keywords: [ __( 'author', 'newspack-blocks' ), __( 'profile', 'newspack-blocks' ) ],
	description: __( 'Display an author profile card.', 'newspack-blocks' ),
	styles: [
		{ name: 'default', label: _x( 'Default', 'block style', 'newspack-blocks' ), isDefault: true },
		{ name: 'center', label: _x( 'Centered', 'block style', 'newspack-blocks' ) },
	],
	attributes,
	category,
	supports: {
		html: false,
		default: '',
	},
	edit,
	save: () => null, // to use view.php
};
