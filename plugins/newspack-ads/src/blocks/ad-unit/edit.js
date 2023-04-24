/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useState, useEffect } from '@wordpress/element';
import { BlockControls, useBlockProps } from '@wordpress/block-editor';
import {
	SVG,
	ToolbarGroup,
	ToolbarButton,
	Placeholder,
	Spinner,
	Notice,
	Button,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { pencil, pullquote } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import PlacementControl from '../../placements/placement-control';

function Edit( { attributes, setAttributes } ) {
	const [ inFlight, setInFlight ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ isEditing, setIsEditing ] = useState( false );
	const [ biddersError, setBiddersError ] = useState( null );
	const [ providers, setProviders ] = useState( [] );
	const [ bidders, setBidders ] = useState( {} );
	const blockProps = useBlockProps( {
		className: 'newspack-ads-ad-block',
	} );

	const provider = providers.find( p => p.id.toString() === attributes.provider );
	const unit = provider?.units?.find( u => u.value.toString() === attributes.ad_unit );
	const sizes = unit?.sizes || [];
	const containerWidth = sizes.length ? Math.max( ...sizes.map( s => s[ 0 ] ) ) : 300;
	const containerHeight = sizes.length ? Math.max( ...sizes.map( s => s[ 1 ] ) ) : 200;

	useEffect( () => {
		const fetchData = async () => {
			// Legacy attribute.
			if ( attributes.activeAd && ! attributes.ad_unit ) {
				setAttributes( { ad_unit: attributes.activeAd } );
			}

			setInFlight( true );
			// Fetch providers.
			try {
				setProviders( await apiFetch( { path: '/newspack-ads/v1/providers' } ) );
			} catch ( err ) {
				setError( err );
			}
			// Fetch bidders.
			try {
				setBidders( await apiFetch( { path: '/newspack-ads/v1/bidders' } ) );
			} catch ( err ) {
				setBiddersError( err );
			}
			setInFlight( false );
		};
		fetchData();
	}, [] );

	useEffect( () => {
		if ( providers?.length && ! attributes.provider ) {
			setAttributes( { provider: providers[ 0 ].id } );
		}
	}, [ providers ] );

	return (
		<div { ...blockProps }>
			{ ! isEditing && unit ? (
				<Fragment>
					{ ! inFlight && (
						<BlockControls>
							<ToolbarGroup>
								<ToolbarButton
									icon={ pencil }
									label={ __( 'Edit', 'newspack-ads' ) }
									onClick={ () => setIsEditing( true ) }
								/>
							</ToolbarGroup>
						</BlockControls>
					) }
					{ error && <Notice isError noticeText={ error } isDismissible={ false } /> }
					{ provider === 'gam' && biddersError && (
						<Notice isWarning noticeText={ biddersError } isDismissible={ false } />
					) }
					<div
						className="newspack-ads-ad-block-placeholder"
						style={ { width: containerWidth, height: containerHeight } }
					>
						<Fragment>
							<SVG
								className="newspack-ads-ad-block-mock"
								width={ containerWidth }
								viewBox={ '0 0 ' + containerWidth + ' ' + containerHeight }
							>
								<rect width={ containerWidth } height={ containerHeight } strokeDasharray="2" />
								<line x1="0" y1="0" x2="100%" y2="100%" strokeDasharray="2" />
							</SVG>
							<span className="newspack-ads-ad-block-ad-label">
								{ providers.length > 1 && `${ provider.name } - ` } { unit.name }
								<br />
								{ sizes.length
									? sizes.map( size => `${ size[ 0 ] }x${ size[ 1 ] }` ).join( ', ' )
									: __( 'Unknown size', 'newspack-ads' ) }
							</span>
						</Fragment>
						{ inFlight && <Spinner /> }
					</div>
				</Fragment>
			) : (
				<Placeholder label={ __( 'Ad Unit', 'newspack-ads' ) } icon={ pullquote }>
					<div className="newspack-ads-ad-block-edit">
						{ inFlight ? (
							<Spinner />
						) : (
							<Fragment>
								<div
									className={ classNames( {
										'newspack-ads-ad-block-edit-fields': true,
									} ) }
								>
									<PlacementControl
										providers={ providers }
										bidders={ bidders }
										value={ attributes }
										onChange={ value => {
											setIsEditing( true );
											setAttributes( value );
										} }
									/>
									<Button disabled={ ! unit } onClick={ () => setIsEditing( false ) } isPrimary>
										{ __( 'Save', 'newspack-ads' ) }
									</Button>
								</div>
							</Fragment>
						) }
					</div>
				</Placeholder>
			) }
		</div>
	);
}

export default Edit;
