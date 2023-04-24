/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { Icon, megaphone } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import './editor.scss';
import metadata from './block.json';
const { attributes, category, name } = metadata;
import { CustomPlacementEditor } from './edit';

export const registerCustomPlacementBlock = () => {
	const isPrompt = Boolean( window.newspack_popups_blocks_data?.is_prompt );

	// No prompts inside prompts.
	if ( isPrompt ) {
		return null;
	}

	registerBlockType( name, {
		title: __( 'Campaigns: Custom Placement', 'newspack-listing' ),
		icon: {
			src: <Icon icon={ megaphone } />,
			foreground: '#36f',
		},
		category,
		keywords: [
			__( 'newspack', 'newspack-popups' ),
			__( 'campaigns', 'newspack-popups' ),
			__( 'campaign', 'newspack-popups' ),
			__( 'prompt', 'newspack-popups' ),
			__( 'custom', 'newspack-popups' ),
			__( 'placement', 'newspack-popups' ),
		],

		attributes,

		edit: CustomPlacementEditor,
		save: () => null, // uses view.php
	} );
};
