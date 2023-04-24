/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import { Button, QueryControls as BasicQueryControls, ToggleControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies.
 */
import AutocompleteTokenField from './autocomplete-tokenfield';

const getCategoryTitle = category =>
	decodeEntities( category.name ) || __( '(no title)', 'newspack-blocks' );

class QueryControls extends Component {
	state = {
		showAdvancedFilters: false,
	};

	fetchPostSuggestions = search => {
		const { postType } = this.props;
		const restUrl = window.newspack_blocks_data.specific_posts_rest_url;
		return apiFetch( {
			url: addQueryArgs( restUrl, {
				search,
				postsToShow: 20,
				_fields: 'id,title',
				type: 'post',
				postType,
			} ),
		} ).then( function ( posts ) {
			const result = posts.map( post => ( {
				value: post.id,
				label: decodeEntities( post.title ) || __( '(no title)', 'newspack-blocks' ),
			} ) );
			return result;
		} );
	};
	fetchSavedPosts = postIDs => {
		const { postType } = this.props;
		const restUrl = window.newspack_blocks_data.posts_rest_url;
		return apiFetch( {
			url: addQueryArgs( restUrl, {
				// These params use the block query parameters (see Newspack_Blocks::build_articles_query).
				postsToShow: 100,
				include: postIDs.join( ',' ),
				_fields: 'id,title',
				postType,
			} ),
		} ).then( function ( posts ) {
			return posts.map( post => ( {
				value: post.id,
				label: decodeEntities( post.title.rendered ) || __( '(no title)', 'newspack-blocks' ),
			} ) );
		} );
	};

	fetchAuthorSuggestions = search => {
		const restUrl = window.newspack_blocks_data.authors_rest_url;
		return apiFetch( {
			url: addQueryArgs( restUrl, {
				search,
				per_page: 20,
				fields: 'id,name',
			} ),
		} ).then( function ( users ) {
			return users.map( user => ( {
				value: user.id,
				label: decodeEntities( user.name ) || __( '(no name)', 'newspack-blocks' ),
			} ) );
		} );
	};
	fetchSavedAuthors = userIDs => {
		const restUrl = window.newspack_blocks_data.authors_rest_url;
		return apiFetch( {
			url: addQueryArgs( restUrl, {
				per_page: 100,
				include: userIDs.join( ',' ),
				fields: 'id,name',
			} ),
		} ).then( function ( users ) {
			return users.map( user => ( {
				value: user.id,
				label: decodeEntities( user.name ) || __( '(no name)', 'newspack-blocks' ),
			} ) );
		} );
	};

	fetchCategorySuggestions = search => {
		return apiFetch( {
			path: addQueryArgs( '/wp/v2/categories', {
				search,
				per_page: 20,
				_fields: 'id,name,parent',
				orderby: 'count',
				order: 'desc',
			} ),
		} ).then( categories =>
			Promise.all(
				categories.map( category => {
					if ( category.parent > 0 ) {
						return apiFetch( {
							path: addQueryArgs( `/wp/v2/categories/${ category.parent }`, {
								_fields: 'name',
							} ),
						} ).then( parentCategory => ( {
							value: category.id,
							label: `${ getCategoryTitle( category ) } – ${ getCategoryTitle( parentCategory ) }`,
						} ) );
					}
					return Promise.resolve( {
						value: category.id,
						label: getCategoryTitle( category ),
					} );
				} )
			)
		);
	};
	fetchSavedCategories = categoryIDs => {
		return apiFetch( {
			path: addQueryArgs( '/wp/v2/categories', {
				per_page: 100,
				_fields: 'id,name',
				include: categoryIDs.join( ',' ),
			} ),
		} ).then( function ( categories ) {
			return categories.map( category => ( {
				value: category.id,
				label: decodeEntities( category.name ) || __( '(no title)', 'newspack-blocks' ),
			} ) );
		} );
	};

	fetchTagSuggestions = search => {
		return apiFetch( {
			path: addQueryArgs( '/wp/v2/tags', {
				search,
				per_page: 20,
				_fields: 'id,name',
				orderby: 'count',
				order: 'desc',
			} ),
		} ).then( function ( tags ) {
			return tags.map( tag => ( {
				value: tag.id,
				label: decodeEntities( tag.name ) || __( '(no title)', 'newspack-blocks' ),
			} ) );
		} );
	};
	fetchSavedTags = tagIDs => {
		return apiFetch( {
			path: addQueryArgs( '/wp/v2/tags', {
				per_page: 100,
				_fields: 'id,name',
				include: tagIDs.join( ',' ),
			} ),
		} ).then( function ( tags ) {
			return tags.map( tag => ( {
				value: tag.id,
				label: decodeEntities( tag.name ) || __( '(no title)', 'newspack-blocks' ),
			} ) );
		} );
	};

	render = () => {
		const {
			specificMode,
			onSpecificModeChange,
			specificPosts,
			onSpecificPostsChange,
			authors,
			onAuthorsChange,
			categories,
			onCategoriesChange,
			tags,
			onTagsChange,
			tagExclusions,
			onTagExclusionsChange,
			categoryExclusions,
			onCategoryExclusionsChange,
			enableSpecific,
		} = this.props;
		const { showAdvancedFilters } = this.state;

		return (
			<>
				{ enableSpecific && (
					<ToggleControl
						checked={ specificMode }
						onChange={ onSpecificModeChange }
						label={ __( 'Choose Specific Posts', 'newspack-blocks' ) }
					/>
				) }
				{ specificMode ? (
					<AutocompleteTokenField
						tokens={ specificPosts || [] }
						onChange={ onSpecificPostsChange }
						fetchSuggestions={ this.fetchPostSuggestions }
						fetchSavedInfo={ this.fetchSavedPosts }
						label={ __( 'Posts', 'newspack-blocks' ) }
						help={ __(
							'Begin typing post title, click autocomplete result to select.',
							'newspack-blocks'
						) }
					/>
				) : (
					<>
						<BasicQueryControls { ...this.props } />
						{ onAuthorsChange && (
							<AutocompleteTokenField
								tokens={ authors || [] }
								onChange={ onAuthorsChange }
								fetchSuggestions={ this.fetchAuthorSuggestions }
								fetchSavedInfo={ this.fetchSavedAuthors }
								label={ __( 'Authors', 'newspack-blocks' ) }
							/>
						) }
						{ onCategoriesChange && (
							<AutocompleteTokenField
								tokens={ categories || [] }
								onChange={ onCategoriesChange }
								fetchSuggestions={ this.fetchCategorySuggestions }
								fetchSavedInfo={ this.fetchSavedCategories }
								label={ __( 'Categories', 'newspack-blocks' ) }
							/>
						) }
						{ onTagsChange && (
							<AutocompleteTokenField
								tokens={ tags || [] }
								onChange={ onTagsChange }
								fetchSuggestions={ this.fetchTagSuggestions }
								fetchSavedInfo={ this.fetchSavedTags }
								label={ __( 'Tags', 'newspack-blocks' ) }
							/>
						) }
						{ onTagExclusionsChange && (
							<p>
								<Button
									isLink
									onClick={ () => this.setState( { showAdvancedFilters: ! showAdvancedFilters } ) }
								>
									{ showAdvancedFilters
										? __( 'Hide Advanced Filters', 'newspack-blocks' )
										: __( 'Show Advanced Filters', 'newspack-blocks' ) }
								</Button>
							</p>
						) }
						{ showAdvancedFilters && (
							<>
								{ onTagExclusionsChange && (
									<AutocompleteTokenField
										tokens={ tagExclusions || [] }
										onChange={ onTagExclusionsChange }
										fetchSuggestions={ this.fetchTagSuggestions }
										fetchSavedInfo={ this.fetchSavedTags }
										label={ __( 'Excluded Tags', 'newspack-blocks' ) }
									/>
								) }
								{ onCategoryExclusionsChange && (
									<AutocompleteTokenField
										tokens={ categoryExclusions || [] }
										onChange={ onCategoryExclusionsChange }
										fetchSuggestions={ this.fetchCategorySuggestions }
										fetchSavedInfo={ this.fetchSavedCategories }
										label={ __( 'Excluded Categories', 'newspack-blocks' ) }
									/>
								) }
							</>
						) }
					</>
				) }
			</>
		);
	};
}

QueryControls.defaultProps = {
	enableSpecific: true,
	specificPosts: [],
	authors: [],
	categories: [],
	tags: [],
	tagExclusions: [],
};

export default QueryControls;
