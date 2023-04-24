/**
 * WordPress dependencies
 */
import { getCategories } from '@wordpress/blocks';
import { pullquote } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import edit from './edit';

/**
 * Style dependencies - will load in editor
 */
import './editor.scss';

export const name = 'ad-unit';
export const title = __( 'Ad Unit', 'newspack-ads' );

export const settings = {
	title,
	icon: {
		src: pullquote,
		foreground: '#36f',
	},
	category: getCategories().some( ( { slug } ) => slug === 'newspack' ) ? 'newspack' : 'common',
	keywords: [
		__( 'ad', 'newspack-ads' ),
		__( 'advert', 'newspack-ads' ),
		__( 'ads', 'newspack-ads' ),
	],
	description: __( 'Render an ad unit from your inventory.', 'newspack-ads' ),
	attributes: {
		provider: {
			type: 'string',
		},
		ad_unit: {
			type: 'string',
		},
		bidders_ids: {
			type: 'object',
			default: {},
		},
		// Legacy attribute.
		activeAd: {
			type: 'string',
		},
	},
	supports: {
		html: false,
		align: [ 'left', 'center', 'right', 'wide', 'full' ],
		color: {
			text: false,
			background: true,
		},
	},
	edit,
	save: () => null, // to use Newspack_Ads_Blocks::render_block()
};
