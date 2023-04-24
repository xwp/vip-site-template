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
import './editor.scss';

export const name = 'youtube-video-playlist';
export const title = __( 'YouTube Video Playlist', 'newspack-blocks' );

export const icon = (
	<SVG xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
		<Path d="M13 10.5L8.833 8v5z" />
		<Path
			clipRule="evenodd"
			d="M4.625 3C3.728 3 3 3.728 3 4.625v11.75C3 17.272 3.728 18 4.625 18h11.75c.897 0 1.625-.728 1.625-1.625V4.625C18 3.728 17.272 3 16.375 3zm11.75 1.5H4.625a.125.125 0 00-.125.125v11.75c0 .069.056.125.125.125h11.75a.125.125 0 00.125-.125V4.625a.125.125 0 00-.125-.125z"
			fillRule="evenodd"
		/>
		<Path d="M20.25 8v11c0 .69-.56 1.25-1.249 1.25H6v1.5h13.001A2.749 2.749 0 0021.75 19V8z" />
	</SVG>
);

export const settings = {
	title,
	icon: {
		src: icon,
		foreground: '#36f',
	},
	category: 'newspack',
	keywords: [
		__( 'video', 'newspack-blocks' ),
		__( 'playlist', 'newspack-blocks' ),
		__( 'youtube', 'newspack-blocks' ),
	],
	description: __( 'Embed a playlist of latest YouTube videos.', 'newspack-blocks' ),
	attributes: {
		className: {
			type: 'string',
		},
		categories: {
			type: 'array',
			default: [],
		},
		videosToShow: {
			type: 'integer',
			default: 5,
		},
	},
	supports: {
		html: false,
		align: true,
	},
	edit,
	save: () => null, // to use view.php
};
