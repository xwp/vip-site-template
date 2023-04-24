/**
 * WordPress dependencies
 */
import { Fragment, useState } from '@wordpress/element';
import { InspectorControls, BlockControls } from '@wordpress/block-editor';
import {
	PanelBody,
	Path,
	ToggleControl,
	Toolbar,
	FocusableIframe,
	Notice,
	SVG,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import IframePlaceholder from './iframe-placeholder';
import { iframeIcon } from './icons';

const iconPreview = (
	<SVG xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24">
		<Path d="M12 18.8c-4.8 0-8.5-6.1-8.6-6.4l-.3-.4.2-.4c.2-.3 3.9-6.4 8.6-6.4 4.7 0 8.5 6.1 8.6 6.4l.2.4-.2.4c0 .2-3.7 6.4-8.5 6.4zM4.9 12c.9 1.3 3.8 5.2 7.1 5.2s6.3-4 7.1-5.2c-.9-1.3-3.8-5.3-7.1-5.3-3.3.1-6.3 4-7.1 5.3z" />
		<Path d="M15 11.2c-.3.5-.8.8-1.4.8-.9 0-1.6-.7-1.6-1.6 0-.5.3-1 .7-1.3-.2 0-.5-.1-.7-.1-1.7 0-3.2 1.4-3.2 3.2s1.4 3.2 3.2 3.2c1.7 0 3.2-1.4 3.2-3.2 0-.3-.1-.7-.2-1z" />
	</SVG>
);

const IframeEdit = ( { attributes, setAttributes } ) => {
	const label = __( 'Iframe', 'block title' );
	const { mode, src, archiveFolder, isFullScreen, height, width } = attributes;
	const [ showPreview, setShowPreview ] = useState( true );
	const [ isUploadingArchive, setIsUploadingArchive ] = useState();
	const [ error, setError ] = useState();

	const sizeUnits = [
		{ value: 'px', label: 'px' },
		{ value: '%', label: '%' },
		{ value: 'em', label: 'em' },
	];

	const embedURL = async url => {
		setError( null );
		setIsUploadingArchive( true );

		try {
			const formData = new FormData();
			formData.append( 'iframe_url', url );

			const { mode: iframeMode } = await apiFetch( {
				path: '/newspack-blocks/v1/newspack-blocks-iframe-mode-from-url',
				method: 'POST',
				body: formData,
			} );

			setAttributes( {
				mode: iframeMode,
				src: url,
			} );
			setShowPreview( true );

			// remove current archive folder if exists.
			if ( archiveFolder ) {
				deleteIframeArchive();
			}
		} catch ( e ) {
			setError(
				e.message || __( 'An error occured when uploading the iframe archive.', 'newspack-blocks' )
			);
		}

		setIsUploadingArchive( false );
	};

	const uploadIframeArchive = async archiveFile => {
		setError( null );
		setIsUploadingArchive( true );

		try {
			const formData = new FormData();
			formData.append( 'archive_folder', archiveFolder );
			formData.append( 'iframe_file', archiveFile );

			const {
				src: iframeArchiveSrc,
				dir: iframeArchiveFolder,
				mode: iframeMode,
			} = await apiFetch( {
				path: '/newspack-blocks/v1/newspack-blocks-iframe-archive',
				method: 'POST',
				body: formData,
			} );

			setAttributes( {
				mode: iframeMode,
				src: iframeArchiveSrc,
				archiveFolder: iframeArchiveFolder,
			} );
			setShowPreview( true );
		} catch ( e ) {
			setError(
				e.message || __( 'An error occured when uploading the iframe archive.', 'newspack-blocks' )
			);
		}

		setIsUploadingArchive( false );
	};

	const setIframeArchiveFromMedia = async mediaId => {
		setError( null );
		setIsUploadingArchive( true );

		try {
			const formData = new FormData();
			formData.append( 'media_id', mediaId );

			const {
				src: iframeArchiveSrc,
				dir: iframeArchiveFolder,
				mode: iframeMode,
			} = await apiFetch( {
				path: '/newspack-blocks/v1/newspack-blocks-iframe-archive-from-media',
				method: 'POST',
				body: formData,
			} );

			setAttributes( {
				mode: iframeMode,
				src: iframeArchiveSrc,
				archiveFolder: iframeArchiveFolder,
			} );
			setShowPreview( true );
		} catch ( e ) {
			setError(
				e.message ||
					__(
						'An error occured when setting the iframe from the archive media.',
						'newspack-blocks'
					)
			);
		}

		setIsUploadingArchive( false );
	};

	const deleteIframeArchive = () => {
		// Do not remove archive if it's used (if a user clicks on embed after uploading the archive.)
		if ( src && archiveFolder && ! src.includes( archiveFolder ) ) {
			apiFetch( {
				path: '/newspack-blocks/v1/newspack-blocks-remove-iframe-archive',
				method: 'DELETE',
				body: JSON.stringify( { archive_folder: archiveFolder } ),
			} );
		}
	};

	const iframeControls = [
		{
			icon: <Icon icon={ iconPreview } />,
			title: showPreview
				? __( 'Hide iframe preview', 'newspack-blocks' )
				: __( 'Show iframe preview', 'newspack-blocks' ),
			onClick: () => setShowPreview( ! showPreview ),
			isActive: showPreview,
		},
	];

	return (
		<Fragment>
			{ isFullScreen && (
				<Notice
					status="warning"
					className="wp-block-newspack-blocks-iframe-notice"
					isDismissible={ false }
				>
					{ __( 'This block will take over the page content.', 'newspack-blocks' ) }
				</Notice>
			) }
			{ src && showPreview ? (
				<div className="iframe-container">
					<FocusableIframe
						title={ __( 'Newspack embedded iframe', 'newspack-blocks' ) }
						src={
							'document' === mode
								? `https://docs.google.com/gview?embedded=true&url=${ encodeURIComponent( src ) }`
								: src
						}
						style={ {
							width: isFullScreen ? '100vw' : width,
							height: isFullScreen ? '100vh' : height,
							'max-width': '100%',
							'max-height': '100%',
						} }
					/>
				</div>
			) : (
				<IframePlaceholder
					icon={ iframeIcon }
					label={ label }
					src={ src }
					onSelectURL={ embedURL }
					onSelectMedia={ setIframeArchiveFromMedia }
					isUploadingArchive={ isUploadingArchive }
					archiveFolder={ archiveFolder }
					uploadIframeArchive={ uploadIframeArchive }
					error={ error }
				/>
			) }

			<BlockControls>
				<Toolbar controls={ src && iframeControls } />
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Iframe Settings', 'newspack-blocks' ) } initialOpen={ true }>
					<Fragment>
						<ToggleControl
							label={ __( 'Fullscreen', 'newspack-blocks' ) }
							help={ __(
								'If enabled, the iframe will be full screen and hide all the post content.',
								'newspack-blocks'
							) }
							checked={ isFullScreen }
							onChange={ _isFullScreen => setAttributes( { isFullScreen: _isFullScreen } ) }
							required
						/>

						{ ! isFullScreen && (
							<div className="wp-block-newspack-blocks-iframe__unit-control">
								<UnitControl
									label={ __( 'Width', 'newspack-blocks' ) }
									onChange={ _width => setAttributes( { width: _width } ) }
									value={ width }
									units={ sizeUnits }
								/>
								<UnitControl
									label={ __( 'Height', 'newspack-blocks' ) }
									onChange={ _height => setAttributes( { height: _height } ) }
									value={ height }
									units={ sizeUnits }
								/>
							</div>
						) }
					</Fragment>
				</PanelBody>
			</InspectorControls>
		</Fragment>
	);
};

export default IframeEdit;
