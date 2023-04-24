/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { BlockControls, InspectorControls } from '@wordpress/block-editor';
import {
	BaseControl,
	Button,
	ButtonGroup,
	CheckboxControl,
	Notice,
	PanelBody,
	PanelRow,
	Placeholder,
	RadioControl,
	RangeControl,
	Spinner,
	ToggleControl,
	Toolbar,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import {
	Icon,
	columns as columnsIcon,
	edit,
	listView,
	pullLeft,
	pullRight,
} from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { avatarSizeOptions, textSizeOptions, units } from '../author-profile/edit';

/**
 * Internal dependencies
 */
import { SingleAuthor } from '../author-profile/single-author';
import { AuthorDisplaySettings } from '../shared/author';

/**
 * External dependencies
 */
import { AutocompleteWithSuggestions } from 'newspack-components';
import classnames from 'classnames';

const AuthorList = ( { attributes, clientId, setAttributes } ) => {
	const [ authors, setAuthors ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ maxItemsToSuggest, setMaxItemsToSuggest ] = useState( 0 );
	const canUseCAP = Boolean( window.newspack_blocks_data?.can_use_cap );
	const editableRoles = window.newspack_blocks_data?.editable_roles;
	const separators = [];
	const {
		authorRoles,
		authorType,
		columns,
		exclude,
		excludeEmpty,
		layout,
		showAvatar,
		showSeparators,
		separatorSections,
		textSize,
		avatarAlignment,
		avatarBorderRadius,
		avatarSize,
		avatarHideDefault,
	} = attributes;
	const isColumns = 'columns' === layout;

	useEffect( () => {
		getAuthors();
	}, [ authorRoles, authorType, avatarHideDefault, exclude, excludeEmpty ] );

	const getAuthors = async () => {
		setError( null );
		setIsLoading( true );
		try {
			const params = {
				author_type: authorType,
				author_roles: authorRoles,
				exclude: exclude.map( exclusion => ( {
					value: exclusion.value,
					isGuest: exclusion.isGuest,
				} ) ),
			};

			if ( excludeEmpty ) {
				params.exclude_empty = 1;
			}

			if ( avatarHideDefault ) {
				params.avatar_hide_default = 1;
			}

			const response = await apiFetch( {
				path: addQueryArgs( '/newspack-blocks/v1/author-list', params ),
			} );

			if ( ! response ) {
				throw __( 'Error fetching authors.', 'newspack-blocks' );
			}
			setAuthors( response );
		} catch ( e ) {
			setError( e.message || e || __( 'Error fetching authors.', 'newspack-blocks' ) );
		}
		setIsLoading( false );
	};

	// Authors grouped by alphabet.
	const chunkedAuthors = {};
	if ( Array.isArray( authors ) && separatorSections ) {
		authors.forEach( author => {
			const { last_name: lastName } = author;
			const firstLetter = lastName.charAt( 0 ).toUpperCase();
			if ( ! chunkedAuthors.hasOwnProperty( firstLetter ) ) {
				chunkedAuthors[ firstLetter ] = [];
			}
			chunkedAuthors[ firstLetter ].push( author );
		} );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Author List Settings', 'newspack-blocks' ) }>
					{ canUseCAP && (
						<PanelRow>
							<RadioControl
								label={ __( 'Author Type', 'newspack-blocks' ) }
								help={ sprintf(
									// translators: help text for author type selection.
									__( '%s will be displayed.', 'newspack-blocks' ),
									'all' === authorType
										? __( 'Both guest authors and WP users', 'newspack-blocks' )
										: sprintf(
												// translators: currently selected author type option.
												__( '%s only', 'newspack-blocks' ),
												'guest-authors' === authorType
													? __( 'Guest authors', 'newspack-blocks' )
													: __( 'WP users', 'newspack-blocks' )
										  )
								) }
								selected={ authorType || 'all' }
								options={ [
									{ label: __( 'All authors', 'newspack-blocks' ), value: 'all' },
									{ label: __( 'Guest authors', 'newspack-blocks' ), value: 'guest-authors' },
									{ label: __( 'WP users', 'newspack-blocks' ), value: 'users' },
								] }
								onChange={ value => setAttributes( { authorType: value } ) }
							/>
						</PanelRow>
					) }
					{ isColumns && (
						<PanelRow>
							<RangeControl
								label={ __( 'Columns', 'newspack-blocks' ) }
								value={ columns }
								onChange={ _columns => setAttributes( { columns: _columns } ) }
								min={ 2 }
								max={ 6 }
								required
							/>
						</PanelRow>
					) }
					{ 'guest-authors' !== authorType && (
						<PanelRow>
							<BaseControl
								id="newspack-blocks__author-list-roles"
								label={ __( 'WP User Roles', 'newspack-blocks' ) }
							>
								{ editableRoles.map( ( role, index ) => (
									<CheckboxControl
										checked={ -1 < authorRoles.indexOf( role ) }
										key={ index }
										label={ role }
										value={ role }
										onChange={ check => {
											const selectedRoles = check
												? [ ...authorRoles, role ]
												: authorRoles.filter( _role => _role !== role );

											setAttributes( { authorRoles: selectedRoles } );
										} }
									/>
								) ) }
							</BaseControl>
						</PanelRow>
					) }
					<PanelRow>
						<ToggleControl
							label={ __( 'Display alphabetical separators', 'newspack-blocks' ) }
							help={ __( 'Chunk authors alphabetically.', 'newspack-blocks' ) }
							checked={ showSeparators }
							onChange={ () => setAttributes( { showSeparators: ! showSeparators } ) }
						/>
					</PanelRow>
					{ isColumns && showSeparators && (
						<PanelRow>
							<ToggleControl
								label={ __( 'Group authors by alphabet', 'newspack-blocks' ) }
								help={ __(
									'Display each alphabetical chunk as a discrete section.',
									'newspack-blocks'
								) }
								checked={ separatorSections }
								onChange={ () => setAttributes( { separatorSections: ! separatorSections } ) }
							/>
						</PanelRow>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Author List Exclusions', 'newspack-block' ) }>
					<PanelRow>
						<ToggleControl
							label={ __( 'Exclude authors with 0 posts', 'newspack-blocks' ) }
							help={ sprintf(
								// Translators: Help message for "include empty authors" toggle.
								__( 'Authors with no published posts will be %s.', 'newspack-blocks' ),
								excludeEmpty
									? __( 'hidden', 'newspack-blocks' )
									: __( 'displayed', 'newspack-blocks' )
							) }
							checked={ excludeEmpty }
							onChange={ () => setAttributes( { excludeEmpty: ! excludeEmpty } ) }
						/>
					</PanelRow>
					<PanelRow>
						<AutocompleteWithSuggestions
							label={ __( 'Search by author name', 'newspack-blocks' ) }
							help={ __( 'Authors selected here will not be displayed.', 'newspack-blocks' ) }
							fetchSuggestions={ async ( search = null, offset = 0 ) => {
								const response = await apiFetch( {
									parse: false,
									path: addQueryArgs( '/newspack-blocks/v1/authors', {
										search,
										offset,
										fields: 'id,name',
									} ),
								} );

								const total = parseInt( response.headers.get( 'x-wp-total' ) || 0 );
								const suggestions = await response.json();

								// Set max items for "load more" functionality in suggestions list.
								if ( ! maxItemsToSuggest && ! search ) {
									setMaxItemsToSuggest( total );
								}

								return suggestions.map( _author => ( {
									value: _author.id,
									label: decodeEntities( _author.name ) || __( '(no name)', 'newspack-blocks' ),
									isGuest: _author.is_guest,
								} ) );
							} }
							maxItemsToSuggest={ maxItemsToSuggest }
							multiSelect={ true }
							onChange={ items => setAttributes( { exclude: items } ) }
							postTypeLabel={ __( 'author', 'newspack-blocks' ) }
							postTypeLabelPlural={ __( 'authors', 'newspack-blocks' ) }
							selectedItems={ exclude }
						/>
					</PanelRow>
				</PanelBody>
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
							label={ __( 'Avatar Size', 'newspack-blocks' ) }
							id="newspack-blocks__avatar-size-control"
						>
							<PanelRow>
								<ButtonGroup
									id="newspack-blocks__avatar-size-control-buttons"
									aria-label={ __( 'Avatar Size', 'newspack-blocks' ) }
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
			{ authors && ! isLoading && (
				<BlockControls>
					<Toolbar
						controls={ [
							{
								icon: <Icon icon={ listView } />,
								title: __( 'List', 'newspack-blocks' ),
								isActive: 'list' === layout,
								onClick: () => {
									setAttributes( { layout: 'list' } );
								},
							},
							{
								icon: <Icon icon={ columnsIcon } />,
								title: __( 'Columns', 'newspack-blocks' ),
								isActive: isColumns,
								onClick: () => {
									setAttributes( { layout: 'columns' } );
								},
							},
						] }
					/>
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
								},
							},
						] }
					/>
				</BlockControls>
			) }
			<div className={ classnames( attributes.className, 'wp-block-newspack-blocks-author-list' ) }>
				{ ! isLoading && ! error && authors && Array.isArray( authors ) && (
					<>
						{ isColumns && showSeparators && separatorSections ? (
							Object.keys( chunkedAuthors ).map( ( firstLetter, index ) => (
								<Fragment key={ index }>
									<h2
										className="newspack-blocks__author-list-separator"
										id={ `newspack-blocks__author-list-separator__${ clientId }__${ firstLetter }` }
									>
										{ firstLetter }
									</h2>
									<ul
										className={ classnames( 'newspack-blocks__author-list-container', {
											'is-columns': isColumns,
											[ `columns-${ columns }` ]: isColumns,
										} ) }
									>
										{ chunkedAuthors[ firstLetter ].map( ( author, i ) => (
											<li key={ i } className="newspack-blocks__author-list-item">
												<SingleAuthor author={ author } attributes={ attributes } />
											</li>
										) ) }
									</ul>
								</Fragment>
							) )
						) : (
							<ul
								className={ classnames( 'newspack-blocks__author-list-container', {
									'is-columns': isColumns,
									[ `columns-${ columns }` ]: isColumns,
								} ) }
							>
								{ authors.map( ( author, index ) => {
									const { last_name: lastName } = author;
									const firstLetter = lastName.charAt( 0 ).toUpperCase();
									const showSeparator = 0 > separators.indexOf( firstLetter );

									if ( showSeparator ) {
										separators.push( firstLetter );
									}

									return (
										<Fragment key={ index }>
											{ showSeparators && showSeparator && (
												<li className="newspack-blocks__author-list-item">
													<h2
														className="newspack-blocks__author-list-separator"
														id={ `newspack-blocks__author-list-separator__${ clientId }__${ firstLetter }` }
													>
														{ firstLetter }
													</h2>
												</li>
											) }

											<li className="newspack-blocks__author-list-item">
												<SingleAuthor author={ author } attributes={ attributes } />
											</li>
										</Fragment>
									);
								} ) }
							</ul>
						) }
					</>
				) }
				{ ( ! authors || isLoading ) && (
					<Placeholder
						icon={ <Icon icon={ listView } /> }
						label={ __( 'Author List', 'newspack-blocks' ) }
					>
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						{ isLoading && (
							<div className="is-loading">
								{ __( 'Fetching authors…', 'newspack-blocks' ) }
								<Spinner />
							</div>
						) }
					</Placeholder>
				) }
			</div>
		</>
	);
};

export default AuthorList;
