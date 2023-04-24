/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, ExternalLink, Notice, Placeholder, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { addQueryArgs } from '@wordpress/url';
import { megaphone } from '@wordpress/icons';

/**
 * External dependencies.
 */
import { AutocompleteWithSuggestions } from 'newspack-components';

export const SinglePromptEditor = ( { attributes, setAttributes } ) => {
	const [ loading, setLoading ] = useState( false );
	const [ prompt, setPrompt ] = useState( null );
	const [ error, setError ] = useState( null );
	const { promptId } = attributes;
	const { endpoint, post_type: postType } = window?.newspack_popups_blocks_data;

	const getPrompt = async () => {
		setError( null );
		setLoading( true );

		try {
			const response = await apiFetch( {
				path: addQueryArgs( endpoint, {
					per_page: 1,
					include: promptId,
				} ),
			} );

			if ( 0 === response.length ) {
				setError(
					sprintf(
						// Translators: An id of a popup.
						__(
							'No active prompts found with ID %s. Try choosing another prompt.',
							'newspack-popups'
						),
						promptId
					)
				);
				setLoading( false );
			} else {
				setPrompt( response.shift() );
				setLoading( false );
			}
		} catch ( e ) {
			setError(
				e.message ||
					sprintf(
						// Translators: An id of a popup.
						__( 'There was an error fetching prompt with ID %s.', 'newspack-popups' ),
						promptId
					)
			);
			setLoading( false );
		}
	};

	useEffect( () => {
		if ( promptId ) {
			getPrompt();
		}
	}, [ promptId ] );

	if ( ! loading && promptId && prompt ) {
		return (
			<div className="newspack-popups__single-prompt">
				<h4 className="newspack-popups__single-prompt-title">
					{ prompt.title || __( '(no title)', 'newspack-popups' ) }{ ' ' }
					<ExternalLink href={ `/wp-admin/post.php?post=${ promptId }&action=edit` }>
						{ __( 'edit', 'newspack-popups' ) }
					</ExternalLink>
				</h4>
				<div
					className="newspack-popup newspack-inline-popup"
					dangerouslySetInnerHTML={ { __html: prompt.content } }
				/>
				<Button
					isSecondary
					onClick={ () => {
						setAttributes( { promptId: 0 } );
						setPrompt( null );
					} }
				>
					{ __( 'Clear prompt' ) }
				</Button>
			</div>
		);
	}

	return (
		<Placeholder
			className="newspack-popups__single-prompt-placeholder"
			label={ __( 'Single Prompt', 'newspack-popups' ) }
			icon={ megaphone }
		>
			{ loading && (
				<div className="is-loading">
					{ sprintf(
						// Translators: An id of a popup.
						__( 'Loading prompt with ID %s…', 'newspack-popups' ),
						promptId
					) }
					<Spinner />
				</div>
			) }

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! prompt && ! loading && (
				<AutocompleteWithSuggestions
					label={ __( 'Search for an inline or manual-only prompt:', 'newspack' ) }
					help={ __(
						'Begin typing prompt title, click autocomplete result to select.',
						'newspack'
					) }
					fetchSavedPosts={ async postIDs => {
						const posts = await apiFetch( {
							path: addQueryArgs( endpoint, {
								per_page: 100,
								include: postIDs.join( ',' ),
							} ),
						} );

						return posts.map( post => ( {
							value: post.id,
							label: decodeEntities( post.title ) || __( '(no title)', 'newspack' ),
						} ) );
					} }
					fetchSuggestions={ async search => {
						const posts = await apiFetch( {
							path: addQueryArgs( endpoint, {
								search,
								per_page: 10,
							} ),
						} );

						// Format suggestions for FormTokenField display.
						const result = posts.reduce( ( acc, post ) => {
							acc.push( {
								value: post.id,
								label: decodeEntities( post.title ) || __( '(no title)', 'newspack' ),
							} );

							return acc;
						}, [] );
						return result;
					} }
					postType={ postType }
					postTypeLabel={ 'prompt' }
					maxLength={ 1 }
					onChange={ items => setAttributes( { promptId: parseInt( items.pop().value ) } ) }
					selectedPost={ null }
				/>
			) }
		</Placeholder>
	);
};
