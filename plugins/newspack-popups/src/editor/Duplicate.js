/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Modal, Notice, TextControl } from '@wordpress/components';
import { compose } from '@wordpress/compose';
import { withSelect, withDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

const DuplicateButton = ( {
	autosave,
	campaignGroups,
	duplicateOf,
	isSavingPost,
	postId,
	title,
} ) => {
	const [ error, setError ] = useState( null );
	const [ modalVisible, setModalVisible ] = useState( false );
	const [ duplicateTitle, setDuplicateTitle ] = useState( null );
	const [ duplicated, setDuplicated ] = useState( null );

	useEffect( () => {
		setError( null );
		if ( modalVisible && ! duplicateTitle ) {
			getDefaultDupicateTitle();
		}
		if ( ! modalVisible ) {
			setDuplicated( null );
			setDuplicateTitle( null );
		}
	}, [ modalVisible ] );

	const getDefaultDupicateTitle = async () => {
		const promptToDuplicate = parseInt( duplicateOf || postId );
		try {
			const defaultTitle = await apiFetch( {
				path: `/newspack-popups/v1/${ promptToDuplicate }/${ postId }/duplicate`,
			} );

			setDuplicateTitle( defaultTitle );
		} catch ( e ) {
			setDuplicateTitle( title + __( ' copy', 'newspack-popups' ) );
		}
	};

	const duplicatePrompt = async ( popupId, titleForDuplicate ) => {
		setError( null );
		try {
			const newId = await apiFetch( {
				path: addQueryArgs( `/newspack-popups/v1/${ popupId }/duplicate`, {
					title: titleForDuplicate,
				} ),
				method: 'POST',
			} );

			if ( isNaN( newId ) ) {
				throw new Error( __( 'Error duplicating prompt.', 'newspack-popups' ) );
			}

			// Redirect to edit page for the copy.
			setDuplicated( newId );
		} catch ( e ) {
			setError( e?.message || __( 'Error duplicating prompt.', 'newspack-popups' ) );
		}
	};

	return (
		<>
			<Button
				isSecondary
				isBusy={ isSavingPost }
				disabled={ isSavingPost }
				onClick={ () => setModalVisible( true ) }
			>
				{ __( 'Duplicate', 'newspack-popups' ) }
			</Button>
			{ modalVisible && (
				<Modal
					className="newspack-popups__duplicate-modal"
					// Translators: Title of the duplicated popup.
					title={ sprintf( __( 'Duplicate “%s”', 'newspack-popups' ), title ) }
					onRequestClose={ () => setModalVisible( false ) }
				>
					{ error && (
						<Notice isDismissible={ false } status="error">
							{ error }
						</Notice>
					) }
					{ duplicated ? (
						<>
							<Notice status="success" isDismissible={ false }>
								{ sprintf(
									// Translators: Title of the duplicated popup.
									__( 'Duplicate of “%s” created as a draft.', 'newspack-popups' ),
									title
								) }
							</Notice>
							{ ( ! campaignGroups || 0 === campaignGroups.length ) && (
								<Notice status="warning" isDismissible={ false }>
									{ __(
										'This prompt is currently not assigned to any campaign.',
										'newspack-popups'
									) }
								</Notice>
							) }
							<Flex justify="flex-end">
								<Button isSecondary onClick={ () => setModalVisible( false ) }>
									{ __( 'Close', 'newspack-popups' ) }
								</Button>
								<Button isPrimary href={ `/wp-admin/post.php?post=${ duplicated }&action=edit` }>
									{ __( 'Edit', 'newspack-popups' ) }
								</Button>
							</Flex>
						</>
					) : (
						<>
							{ ( ! campaignGroups || 0 === campaignGroups.length ) && (
								<Notice status="warning" isDismissible={ false }>
									{ __( 'This prompt will not be assigned to any campaign.', 'newspack-popups' ) }
								</Notice>
							) }
							<TextControl
								disabled={ isSavingPost || null === duplicateTitle }
								label={ __( 'Title', 'newspack-popups' ) }
								value={ duplicateTitle }
								onChange={ value => setDuplicateTitle( value ) }
							/>
							<Flex justify="flex-end">
								<Button
									isBusy={ isSavingPost }
									isSecondary
									onClick={ () => {
										setModalVisible( false );
									} }
								>
									{ __( 'Cancel', 'newspack-popups' ) }
								</Button>
								<Button
									isBusy={ isSavingPost }
									disabled={ null === duplicateTitle }
									isPrimary
									onClick={ () => {
										const titleForDuplicate =
											duplicateTitle.trim() || title + __( ' copy', 'newspack-popups' );
										autosave().then( duplicatePrompt( postId, titleForDuplicate ) );
									} }
								>
									{ __( 'Duplicate', 'newspack-popups' ) }
								</Button>
							</Flex>
						</>
					) }
				</Modal>
			) }
		</>
	);
};

export default compose( [
	withSelect( select => {
		const { isSavingPost, getCurrentPostId, getEditedPostAttribute } = select( 'core/editor' );
		const { duplicate_of: duplicateOf } = getEditedPostAttribute( 'meta' );
		return {
			postId: getCurrentPostId(),
			isSavingPost: isSavingPost(),
			title: getEditedPostAttribute( 'title' ),
			campaignGroups: getEditedPostAttribute( 'newspack_popups_taxonomy' ),
			duplicateOf,
		};
	} ),
	withDispatch( dispatch => {
		const { autosave } = dispatch( 'core/editor' );
		const { createNotice } = dispatch( 'core/notices' );
		return {
			autosave,
			createNotice,
		};
	} ),
] )( DuplicateButton );
