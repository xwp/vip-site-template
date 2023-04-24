/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Component, Fragment } from '@wordpress/element';
import { Placeholder, Spinner, PanelBody, RangeControl } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';
import { addQueryArgs } from '@wordpress/url';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies.
 */
import AutocompleteTokenField from '../../components/autocomplete-tokenfield';

class Edit extends Component {
	constructor( props ) {
		super( props );
		this.state = {
			isLoading: false,
			error: '',
			embed: '',
			interactive: false,
		};
	}

	/**
	 * Refresh preview on load.
	 */
	componentDidMount() {
		this.refreshPreview();
	}

	/**
	 * Refresh preview if something changes.
	 *
	 * @param {Object} prevProps Previous object props.
	 */
	componentDidUpdate( prevProps ) {
		const { attributes } = this.props;
		const { categories, videosToShow } = attributes;
		const { attributes: prevAttributes } = prevProps;
		const { categories: prevCategories, videosToShow: prevVideosToShow } = prevAttributes;

		if ( categories !== prevCategories || videosToShow !== prevVideosToShow ) {
			this.refreshPreview();
		}
	}

	/**
	 * Bring the overlay back up when the block is un-selected.
	 * The overlay handling is the same as the core embed blocks.
	 *
	 * @see https://github.com/WordPress/gutenberg/blob/8e7f7b1a388829ac46fb6917351c7d064905af9a/packages/block-library/src/embed/embed-preview.js#L27-L111
	 * @param {Object} nextProps Next object props.
	 * @param {Object} state     Object state.
	 * @return {Object} State or null.
	 */
	static getDerivedStateFromProps( nextProps, state ) {
		if ( ! nextProps.isSelected && state.interactive ) {
			return { interactive: false };
		}

		return null;
	}

	/**
	 * Fetch the latest preview for the current attributes.
	 */
	refreshPreview() {
		const { attributes } = this.props;
		const { categories, videosToShow } = attributes;

		const basePath = '/newspack-blocks/v1/video-playlist';
		const path = addQueryArgs( basePath, {
			categories,
			videosToShow,
		} );

		this.setState( { isLoading: true }, () => {
			apiFetch( {
				path,
			} )
				.then( response => {
					const { html } = response;
					this.setState( {
						embed: html,
						isLoading: false,
					} );
				} )
				.catch( error => {
					this.setState( {
						isLoading: false,
						error: error.message,
					} );
				} );
		} );
	}

	/**
	 * Render an informational placeholder for loading and errors.
	 */
	renderPlaceholder() {
		const { isLoading, error, embed } = this.state;

		if ( isLoading ) {
			return <Placeholder icon={ <Spinner /> } className="component-placeholder__align-center" />;
		}

		if ( error.length ) {
			return (
				<Placeholder
					icon="warning"
					label={ __( 'Error', 'newspack-blocks' ) }
					instructions={ error }
				/>
			);
		}

		if ( ! embed.length ) {
			return (
				<Placeholder
					icon="warning"
					label={ __( 'Error', 'newspack-blocks' ) }
					instructions={ __( 'No videos found', 'newspack-blocks' ) }
				/>
			);
		}

		return null;
	}

	/**
	 * Fetch and decode Category autocomplete options.
	 *
	 * @param {string} search The text input into the field so far.
	 * @return {Array} Autocomplete suggestion tokens.
	 */
	fetchCategorySuggestions = search => {
		return apiFetch( {
			path: addQueryArgs( '/wp/v2/categories', {
				search,
				per_page: 20,
				_fields: 'id,name',
				orderby: 'count',
				order: 'desc',
			} ),
		} ).then( function ( categories ) {
			return categories.map( category => ( {
				value: category.id,
				label: decodeEntities( category.name ) || __( '(no title)', 'newspack-blocks' ),
			} ) );
		} );
	};

	/**
	 * Convert saved Category IDs into tokens.
	 *
	 * @param {Array} categoryIDs array of Category IDs.
	 * @return {Array} Tokens.
	 */
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

	/**
	 * Hide the overlay so users can play the videos.
	 */
	hideOverlay() {
		this.setState( { interactive: true } );
	}

	/**
	 * Render.
	 */
	render() {
		const { attributes, setAttributes } = this.props;
		const { categories, videosToShow } = attributes;
		const { embed, isLoading, interactive } = this.state;

		return (
			<Fragment>
				{ ( isLoading || '' === embed ) && this.renderPlaceholder() }
				{ ! ( isLoading || '' === embed ) && (
					<div className="wpbnbvp-preview">
						<div dangerouslySetInnerHTML={ { __html: embed } } />
						{ ! interactive && (
							// eslint-disable-next-line jsx-a11y/no-static-element-interactions
							<div className="wpbnbvp__overlay" onMouseUp={ () => this.hideOverlay() } />
						) }
					</div>
				) }
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'newspack-blocks' ) } initialOpen={ true }>
						<Fragment>
							<RangeControl
								className="videosToShow"
								label={ __( 'Videos', 'newspack-blocks' ) }
								help={ __( 'The maximum number of videos to pull from posts.', 'newspack-blocks' ) }
								value={ videosToShow }
								onChange={ _videosToShow => setAttributes( { videosToShow: _videosToShow } ) }
								min={ 1 }
								max={ 30 }
								required
							/>
							<AutocompleteTokenField
								key="categories"
								tokens={ categories || [] }
								onChange={ _categories => setAttributes( { categories: _categories } ) }
								fetchSuggestions={ this.fetchCategorySuggestions }
								fetchSavedInfo={ this.fetchSavedCategories }
								label={ __( 'Categories', 'newspack-blocks' ) }
							/>
						</Fragment>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	}
}

export default Edit;
