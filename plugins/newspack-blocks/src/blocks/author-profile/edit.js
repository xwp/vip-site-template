/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { BlockControls, InspectorControls } from '@wordpress/block-editor';
import {
	BaseControl,
	Button,
	ButtonGroup,
	Notice,
	PanelBody,
	PanelRow,
	Placeholder,
	Spinner,
	ToggleControl,
	Toolbar,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { Icon, edit, postAuthor, pullLeft, pullRight } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { SingleAuthor } from './single-author';
import { AuthorDisplaySettings } from '../shared/author';

/**
 * External dependencies
 */
import { AutocompleteWithSuggestions } from 'newspack-components';

// Available units for avatarBorderRadius option.
export const units = [
	{
		value: '%',
		label: '%',
	},
	{
		value: 'px',
		label: 'px',
	},
	{
		value: 'em',
		label: 'em',
	},
	{
		value: 'rem',
		label: 'rem',
	},
];

// Textsize options.
export const textSizeOptions = [
	{
		value: 'small',
		label: /* translators: label for small text size option */ __( 'Small', 'newspack-blocks' ),
		shortName: /* translators: abbreviation for small text size option */ __(
			'S',
			'newspack-blocks'
		),
	},
	{
		value: 'medium',
		label: /* translators: label for medium text size option */ __( 'Medium', 'newspack-blocks' ),
		shortName: /* translators: abbreviation for medium text size option */ __(
			'M',
			'newspack-blocks'
		),
	},
	{
		value: 'large',
		label: /* translators: label for small text size option */ __( 'Large', 'newspack-blocks' ),
		shortName: /* translators: abbreviation for large text size option */ __(
			'L',
			'newspack-blocks'
		),
	},
	{
		value: 'extra-large',
		label: /* translators: label for extra-large text size option */ __(
			'Extra Large',
			'newspack-blocks'
		),
		shortName: /* translators: abbreviation for small text size option */ __(
			'XL',
			'newspack-blocks'
		),
	},
];

// Avatar size options.
export const avatarSizeOptions = [
	{
		value: 72,
		label: /* translators: label for small avatar size option */ __( 'Small', 'newspack-blocks' ),
		shortName: /* translators: abbreviation for small avatar size option */ __(
			'S',
			'newspack-blocks'
		),
	},
	{
		value: 128,
		label: /* translators: label for medium avatar size option */ __( 'Medium', 'newspack-blocks' ),
		shortName: /* translators: abbreviation for medium avatar size option */ __(
			'M',
			'newspack-blocks'
		),
	},
	{
		value: 192,
		label: /* translators: label for large avatar size option */ __( 'Large', 'newspack-blocks' ),
		shortName: /* translators: abbreviation for large avatar size option */ __(
			'L',
			'newspack-blocks'
		),
	},
	{
		value: 256,
		label: /* translators: label for extra-large avatar size option */ __(
			'Extra-large',
			'newspack-blocks'
		),
		shortName: /* translators: abbreviation for extra-large avatar size option  */ __(
			'XL',
			'newspack-blocks'
		),
	},
];

const AuthorProfile = ( { attributes, setAttributes } ) => {
	const [ author, setAuthor ] = useState( null );
	const [ suggestions, setSuggestions ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ maxItemsToSuggest, setMaxItemsToSuggest ] = useState( 0 );
	const {
		authorId,
		isGuestAuthor,
		showSocial,
		showEmail,
		textSize,
		showAvatar,
		avatarAlignment,
		avatarBorderRadius,
		avatarSize,
		avatarHideDefault,
	} = attributes;

	useEffect( () => {
		if ( 0 !== authorId ) {
			getAuthorById();
		}
	}, [ authorId, avatarHideDefault, isGuestAuthor ] );

	const getAuthorById = async () => {
		setError( null );
		setIsLoading( true );
		try {
			const params = {
				author_id: authorId,
				is_guest_author: isGuestAuthor ? 1 : 0,
				fields: 'id,name,bio,email,social,avatar,url',
			};

			if ( avatarHideDefault ) {
				params.avatar_hide_default = 1;
			}

			const response = await apiFetch( {
				path: addQueryArgs( '/newspack-blocks/v1/authors', params ),
			} );

			const _author = response.pop();

			if ( ! _author ) {
				throw sprintf(
					/* translators: Error text for when no authors are found. */
					__( 'No authors or guest authors found for ID %s.', 'newspack-blocks' ),
					authorId
				);
			}
			setAuthor( _author );
		} catch ( e ) {
			setError(
				e.message ||
					e ||
					sprintf(
						/* translators: Error text for when no authors are found. */
						__( 'No authors or guest authors found for ID %s.', 'newspack-blocks' ),
						authorId
					)
			);
		}
		setIsLoading( false );
	};

	// Combine social links and email, which are shown together.
	const socialLinks = ( showSocial && author && author.social ) || {};
	if ( showEmail && author && author.email ) {
		socialLinks.email = author.email;
	} else {
		delete socialLinks.email;
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Author Profile Settings', 'newspack-blocks' ) }>
					<BaseControl
						label={ __( 'Text Size', 'newspack-blocks' ) }
						id="newspack-blocks__text-size-control"
					>
						<PanelRow>
							<ButtonGroup
								id="newspack-blocks__text-size-control-buttons"
								aria-label={ __( 'Text Size', 'newspack-blocks' ) }
							>
								{ textSizeOptions.map( option => {
									const isCurrent = textSize === option.value;
									return (
										<Button
											isLarge
											isPrimary={ isCurrent }
											aria-pressed={ isCurrent }
											aria-label={ option.label }
											key={ option.value }
											onClick={ () => setAttributes( { textSize: option.value } ) }
										>
											{ option.shortName }
										</Button>
									);
								} ) }
							</ButtonGroup>
						</PanelRow>
					</BaseControl>
					<AuthorDisplaySettings attributes={ attributes } setAttributes={ setAttributes } />
				</PanelBody>
				<PanelBody title={ __( 'Avatar Settings', 'newspack-blocks' ) }>
					<PanelRow>
						<ToggleControl
							label={ __( 'Display avatar', 'newspack-blocks' ) }
							checked={ showAvatar }
							onChange={ () => setAttributes( { showAvatar: ! showAvatar } ) }
						/>
					</PanelRow>
					{ showAvatar && (
						<PanelRow>
							<ToggleControl
								label={ __( 'Hide default avatar', 'newspack-blocks' ) }
								checked={ avatarHideDefault }
								onChange={ () => setAttributes( { avatarHideDefault: ! avatarHideDefault } ) }
							/>
						</PanelRow>
					) }
					{ showAvatar && (
						<BaseControl
							label={ __( 'Avatar size', 'newspack-blocks' ) }
							id="newspack-blocks__avatar-size-control"
						>
							<PanelRow>
								<ButtonGroup
									id="newspack-blocks__avatar-size-control-buttons"
									aria-label={ __( 'Avatar size', 'newspack-blocks' ) }
								>
									{ avatarSizeOptions.map( option => {
										const isCurrent = avatarSize === option.value;
										return (
											<Button
												isLarge
												isPrimary={ isCurrent }
												aria-pressed={ isCurrent }
												aria-label={ option.label }
												key={ option.value }
												onClick={ () => setAttributes( { avatarSize: option.value } ) }
											>
												{ option.shortName }
											</Button>
										);
									} ) }
								</ButtonGroup>
							</PanelRow>
						</BaseControl>
					) }
					{ showAvatar && (
						<PanelRow>
							<UnitControl
								label={ __( 'Avatar border radius', 'newspack-blocks' ) }
								labelPosition="edge"
								__unstableInputWidth="80px"
								units={ units }
								value={ avatarBorderRadius }
								onChange={ value =>
									setAttributes( { avatarBorderRadius: 0 > parseFloat( value ) ? '0' : value } )
								}
							/>
						</PanelRow>
					) }
				</PanelBody>
			</InspectorControls>
			{ author && (
				<BlockControls>
					{ showAvatar && 'is-style-center' !== attributes.className && (
						<Toolbar
							controls={ [
								{
									icon: <Icon icon={ pullLeft } />,
									title: __( 'Show avatar on left', 'newspack-blocks' ),
									isActive: avatarAlignment === 'left',
									onClick: () => setAttributes( { avatarAlignment: 'left' } ),
								},
								{
									icon: <Icon icon={ pullRight } />,
									title: __( 'Show avatar on right', 'newspack-blocks' ),
									isActive: avatarAlignment === 'right',
									onClick: () => setAttributes( { avatarAlignment: 'right' } ),
								},
							] }
						/>
					) }
					<Toolbar
						controls={ [
							{
								icon: <Icon icon={ edit } />,
								title: __( 'Edit selection', 'newspack-blocks' ),
								onClick: () => {
									setAttributes( { authorId: 0 } );
									setAuthor( null );
								},
							},
						] }
					/>
				</BlockControls>
			) }
			{ author ? (
				<SingleAuthor author={ author } attributes={ attributes } />
			) : (
				<Placeholder
					className="newspack-blocks-author-profile"
					icon={ <Icon icon={ postAuthor } /> }
					label={ __( 'Author Profile', 'newspack-blocks' ) }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					{ isLoading && (
						<div className="is-loading">
							{ __( 'Fetching author info…', 'newspack-blocks' ) }
							<Spinner />
						</div>
					) }
					{ ! isLoading && (
						<AutocompleteWithSuggestions
							label={ __( 'Search for an author to display', 'newspack-blocks' ) }
							help={ __(
								'Begin typing name, click autocomplete result to select.',
								'newspack-blocks'
							) }
							fetchSuggestions={ async ( search = null, offset = 0 ) => {
								// Reset suggestions in state.
								setSuggestions( null );

								// If we already have a selected author, no need to fetch suggestions.
								if ( authorId && ! error ) {
									return [];
								}

								const response = await apiFetch( {
									parse: false,
									path: addQueryArgs( '/newspack-blocks/v1/authors', {
										search,
										offset,
										fields: 'id,name',
									} ),
								} );

								const total = parseInt( response.headers.get( 'x-wp-total' ) || 0 );
								const authors = await response.json();

								// Set max items for "load more" functionality in suggestions list.
								if ( ! maxItemsToSuggest && ! search ) {
									setMaxItemsToSuggest( total );
								}

								const _suggestions = authors.map( _author => ( {
									value: _author.id,
									label: decodeEntities( _author.name ) || __( '(no name)', 'newspack-blocks' ),
									isGuestAuthor: _author.is_guest,
								} ) );

								setSuggestions( _suggestions );

								return _suggestions;
							} }
							maxItemsToSuggest={ maxItemsToSuggest }
							onChange={ items => {
								let selectionIsGuest = false;
								const selection = items[ 0 ];

								// We need to check whether the selected author is a guest author or not.
								if ( suggestions ) {
									suggestions.forEach( suggestion => {
										if (
											parseInt( selection?.value ) === parseInt( suggestion?.value ) &&
											suggestion?.isGuestAuthor
										) {
											selectionIsGuest = true;
										}
									} );
								}

								setAttributes( {
									authorId: parseInt( selection?.value || 0 ),
									isGuestAuthor: selectionIsGuest,
								} );
							} }
							postTypeLabel={ __( 'author', 'newspack-blocks' ) }
							postTypeLabelPlural={ __( 'authors', 'newspack-blocks' ) }
							selectedItems={ [] }
						/>
					) }
				</Placeholder>
			) }
		</>
	);
};

export default AuthorProfile;
