/**
 * WordPress dependencies
 */
import { Path, SVG } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import edit from './edit';

/**
 * Style dependencies - will load in editor
 */
import './view.scss';
import './editor.scss';

export const name = 'carousel';
export const title = __( 'Post Carousel' );

export const icon = (
	<SVG xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
		<Path
			clipRule="evenodd"
			d="M7 18V6a2 2 0 012-2h6a2 2 0 012 2v12a2 2 0 01-2 2H9a2 2 0 01-2-2zM8.5 6v12a.5.5 0 00.5.5h6a.5.5 0 00.5-.5V6a.5.5 0 00-.5-.5H9a.5.5 0 00-.5.5z"
			fillRule="evenodd"
		/>
		<Path d="M4 18.5v-13h1.5v13zM18.5 5.5v13H20v-13z" />
	</SVG>
);

export const settings = {
	title,
	icon: {
		src: icon,
		foreground: '#36f',
	},
	category: 'newspack',
	keywords: [ __( 'posts' ), __( 'slideshow' ), __( 'carousel' ) ],
	description: __( 'A carousel of posts.' ),
	attributes: {
		className: {
			type: 'string',
		},
		imageFit: {
			type: 'string',
			default: 'cover',
		},
		autoplay: {
			type: 'boolean',
			default: false,
		},
		delay: {
			type: 'number',
			default: 5,
		},
		postsToShow: {
			type: 'integer',
			default: 3,
		},
		authors: {
			type: 'array',
		},
		categories: {
			type: 'array',
		},
		tags: {
			type: 'array',
		},
		showDate: {
			type: 'boolean',
			default: true,
		},
		showAuthor: {
			type: 'boolean',
			default: true,
		},
		showAvatar: {
			type: 'boolean',
			default: true,
		},
		showCategory: {
			type: 'boolean',
			default: false,
		},
		showTitle: {
			type: 'boolean',
			default: true,
		},
		postType: {
			type: 'array',
			default: [ 'post' ],
			items: {
				type: 'string',
			},
		},
		specificMode: {
			type: 'boolean',
			default: false,
		},
		specificPosts: {
			type: 'array',
			default: [],
			items: { type: 'integer' },
		},
		slidesPerView: {
			type: 'number',
			default: 1,
		},
		hideControls: {
			type: 'boolean',
			default: false,
		},
		aspectRatio: {
			type: 'number',
			default: 0.75,
		},
		includedPostStatuses: {
			type: 'array',
			default: [ 'publish' ],
			items: { type: 'string' },
		},
	},
	supports: {
		html: false,
		align: [ 'center', 'wide', 'full' ],
	},
	edit,
	save: () => null, // to use view.php
};
