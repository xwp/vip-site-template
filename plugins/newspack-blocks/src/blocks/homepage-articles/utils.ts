/**
 * External dependencies
 */
import { times, isEqual, isUndefined, pick, pickBy } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { dispatch as wpDataDispatch } from '@wordpress/data';

import { STORE_NAMESPACE } from './store';

/**
 * Based global WP.com blog_public option, checks whether current blog is
 * private or not.
 */
export const isBlogPrivate = (): boolean =>
	typeof window === 'object' &&
	window.wpcomGutenberg &&
	Number( window.wpcomGutenberg.blogPublic ) === -1;

/**
 * Block attributes which influence posts query
 */
const POST_QUERY_ATTRIBUTES = [
	'postsToShow',
	'authors',
	'categories',
	'excerptLength',
	'tags',
	'showExcerpt',
	'specificPosts',
	'specificMode',
	'tagExclusions',
	'categoryExclusions',
	'postType',
	'includedPostStatuses',
];

type HomepageArticlesAttributes = {
	postsToShow: number;
	authors: AuthorId[];
	categories: CategoryId[];
	excerptLength: number;
	postType: PostType[];
	showExcerpt: boolean;
	tags: TagId[];
	specificPosts: string[];
	specificMode: boolean;
	tagExclusions: TagId[];
	categoryExclusions: CategoryId[];
};

type HomepageArticlesProps = {
	attributes: HomepageArticlesAttributes;
	topBlocksClientIdsInOrder: Block[ 'clientId' ][];
	latestPosts: Post[];
	isEditorBlock: boolean;
	isUIDisabled: boolean;
	error: undefined | string;
};

/**
 * Does the props change necessitate a reflow?
 * A reflow should happen if:
 * 1. Query-changing attributes of a block change
 * 2. The top-level blocks order changes. A Homepage Articles
 *    block might be nested somewhere.
 */
export const shouldReflow = (
	prevProps: HomepageArticlesProps,
	props: HomepageArticlesProps
): boolean =>
	! isEqual(
		pick( prevProps.attributes, POST_QUERY_ATTRIBUTES ),
		pick( props.attributes, POST_QUERY_ATTRIBUTES )
	) || ! isEqual( prevProps.topBlocksClientIdsInOrder, props.topBlocksClientIdsInOrder );

/**
 * Builds query criteria from given attributes.
 */
export const queryCriteriaFromAttributes = ( attributes: Block[ 'attributes' ] ): PostsQuery => {
	const {
		postsToShow,
		authors,
		categories,
		excerptLength,
		postType,
		showExcerpt,
		tags,
		specificPosts = [],
		specificMode,
		tagExclusions,
		categoryExclusions,
		includedPostStatuses,
	} = pick( attributes, POST_QUERY_ATTRIBUTES );

	const cleanPosts = sanitizePostList( specificPosts );
	const isSpecificPostModeActive = specificMode && cleanPosts && cleanPosts.length;
	const criteria: PostsQuery = pickBy(
		isSpecificPostModeActive
			? {
					include: cleanPosts,
					postsToShow: specificPosts.length,
					postType,
			  }
			: {
					postsToShow,
					categories,
					authors,
					tags,
					tagExclusions,
					categoryExclusions,
					postType,
					includedPostStatuses,
			  },
		( value: unknown ) => ! isUndefined( value )
	);
	criteria.excerptLength = excerptLength;
	criteria.showExcerpt = showExcerpt;
	return criteria;
};

export const sanitizePostList = ( postList: HomepageArticlesAttributes[ 'specificPosts' ] ) =>
	postList.map( id => parseInt( id ) ).filter( id => id > 0 );

/**
 * Each eligible block's attributes can be used to create a posts query.
 * This function is recursively traversing an array of blocks and creating an aray
 * of {postsQuery, clientId} objects.
 * The eligible blocks are identified by block name, passed in the second argument.
 */
export const getBlockQueries = (
	blocks: Block[],
	blockNames: Block[ 'name' ][]
): { postsQuery: PostsQuery; clientId: Block[ 'clientId' ] }[] =>
	blocks.flatMap( ( block: Block ) => {
		const homepageArticleBlocks = [];
		if ( blockNames.indexOf( block.name ) >= 0 ) {
			const postsQuery = queryCriteriaFromAttributes( block.attributes );
			homepageArticleBlocks.push( { postsQuery, clientId: block.clientId } );
		}
		return homepageArticleBlocks.concat( getBlockQueries( block.innerBlocks, blockNames ) );
	} );

export const getEditorBlocksIds = ( blocks: Block[] ): Block[ 'clientId' ][] =>
	blocks.flatMap( ( block: Block ) => {
		const homepageArticleBlocks = [];
		homepageArticleBlocks.push( block.clientId );
		return homepageArticleBlocks.concat( getEditorBlocksIds( block.innerBlocks ) );
	} );

const PREVIEW_IMAGE_BASE = window.newspack_blocks_data.assets_path;
const generatePreviewPost = ( id: PostId ) => {
	const now = new Date();
	now.setHours( 12, 0, 0, 0 );
	return {
		author: 1,
		content: {
			rendered: '<p>' + __( 'The post content.', 'newspack-blocks' ) + '</p>',
		},
		date_gmt: now.toISOString(),
		excerpt: {
			rendered: '<p>' + __( 'The post excerpt.', 'newspack-blocks' ) + '</p>',
		},
		featured_media: '1',
		id,
		meta: {
			newspack_post_subtitle: __( 'Post Subtitle', 'newspack-blocks' ),
		},
		title: {
			rendered: __( 'Post Title', 'newspack-blocks' ),
		},
		newspack_article_classes: 'type-post',
		newspack_author_info: [
			{
				display_name: __( 'Author Name', 'newspack-blocks' ),
				avatar: `<div style="background: #36f;width: 40px;height: 40px;display: block;overflow: hidden;border-radius: 50%; max-width: 100%; max-height: 100%;"></div>`,
				id: 1,
				author_link: '/',
			},
		],
		newspack_category_info: __( 'Category', 'newspack-blocks' ),
		newspack_featured_image_caption: __( 'Featured image caption', 'newspack-blocks' ),
		newspack_featured_image_src: {
			large: `${ PREVIEW_IMAGE_BASE }/newspack-1024x536.jpg`,
			landscape: `${ PREVIEW_IMAGE_BASE }/newspack-800x600.jpg`,
			portrait: `${ PREVIEW_IMAGE_BASE }/newspack-600x800.jpg`,
			square: `${ PREVIEW_IMAGE_BASE }/newspack-800x800.jpg`,
			uncropped: `${ PREVIEW_IMAGE_BASE }/newspack-1024x536.jpg`,
		},
		newspack_has_custom_excerpt: false,
		newspack_post_sponsors: false,
	};
};

const getPreviewPosts = ( attributes: HomepageArticlesAttributes ) =>
	times( attributes.postsToShow, generatePreviewPost );

type Select = ( namespace: string ) => {
	// core/blocks-editor
	getBlocks: () => Block[];
	// core/editor
	getEditorBlocks: () => Block[];
	// core
	getPostTypes: ( query: object ) => null | PostType[];
	// STORE_NAMESPACE - TODO: move these to src/blocks/homepage-articles/store.js once it's TS
	getPosts: ( query: object ) => Post[];
	getError: ( config: { clientId: Block[ 'clientId' ] } ) => undefined | string;
	isUIDisabled: () => boolean;
};

/**
 * wordpress/data selector for blocks using this custom store.
 */
export const postsBlockSelector = (
	select: Select,
	{
		clientId,
		attributes,
	}: { clientId: Block[ 'clientId' ]; attributes: HomepageArticlesAttributes }
): Omit< HomepageArticlesProps, 'attributes' > => {
	const { getEditorBlocks } = select( 'core/editor' );
	const { getBlocks } = select( 'core/block-editor' );
	const editorBlocksIds = getEditorBlocksIds( getEditorBlocks() );
	// The block might be rendered in the block styles preview, not in the editor.
	const isWidgetEditor = getBlocks().some( block => block.name === 'core/widget-area' );
	const isEditorBlock = editorBlocksIds.indexOf( clientId ) >= 0 || isWidgetEditor;

	const { getPosts, getError, isUIDisabled } = select( STORE_NAMESPACE );
	const props = {
		isEditorBlock,
		isUIDisabled: isUIDisabled(),
		error: getError( { clientId } ),
		topBlocksClientIdsInOrder: getBlocks().map( block => block.clientId ),
		latestPosts: isEditorBlock
			? getPosts( { clientId } )
			: // For block preview, display static content.
			  getPreviewPosts( attributes ),
	};

	return props;
};

/**
 * wordpress/data dispatch for blocks using this custom store.
 */
export const postsBlockDispatch = (
	dispatch: typeof wpDataDispatch,
	{ isEditorBlock }: { isEditorBlock: boolean }
) => {
	return {
		// Only editor blocks can trigger reflows.
		triggerReflow: isEditorBlock ? dispatch( STORE_NAMESPACE ).reflow : () => undefined,
	};
};
