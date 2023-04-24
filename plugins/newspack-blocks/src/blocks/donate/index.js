/**
 * WordPress dependencies
 */
import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { registerBlockStyle } from '@wordpress/blocks';
import { payment } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Style dependencies - will load in editor
 */
import './styles/editor.scss';
import './styles/view.scss';

const { name, attributes, category, supports } = metadata;

// Name must be exported separately.
export { name };
export const title = __( 'Donate', 'newspack-blocks' );

export const settings = {
	title,
	icon: {
		src: payment,
		foreground: '#36f',
	},
	category,
	keywords: [
		__( 'donate', 'newspack-blocks' ),
		__( 'memberships', 'newspack-blocks' ),
		__( 'subscriptions', 'newspack-blocks' ),
	],
	description: (
		<>
			<p>
				{ __(
					'Manually place a donation block on any post or page on your site.',
					'newspack-blocks'
				) }
			</p>
			<ExternalLink href="https://help.newspack.com/publishing-and-appearance/blocks/donate-block/">
				{ __( 'Support reference', 'newspack-blocks' ) }
			</ExternalLink>
		</>
	),
	attributes,
	supports,
	edit,
	save: () => null, // to use view.php
};

/**
 * Block Styles
 */
registerBlockStyle( 'newspack-blocks/donate', {
	name: 'alternate',
	label: __( 'Alternate', 'newapack-blocks' ),
} );

registerBlockStyle( 'newspack-blocks/donate', {
	name: 'minimal',
	label: __( 'Minimal', 'newapack-blocks' ),
} );
