/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { ExternalLink, Notice, Placeholder, SelectControl, Spinner } from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { megaphone } from '@wordpress/icons';

export const CustomPlacementEditor = ( { attributes, setAttributes } ) => {
	const [ loading, setLoading ] = useState( false );
	const [ prompts, setPrompts ] = useState( null );
	const [ error, setError ] = useState( null );
	const { customPlacement } = attributes;
	const customPlacements = window.newspack_popups_blocks_data?.custom_placements || {};
	const customPlacementOptions = [
		{
			label: __( 'Choose a custom placement', 'newspack-popups' ),
			value: '',
			disabled: true,
		},
	].concat(
		Object.keys( customPlacements ).map( key => ( {
			value: key,
			label: customPlacements[ key ],
		} ) )
	);

	const getPrompts = async () => {
		setError( null );
		setLoading( true );

		try {
			const response = await apiFetch( {
				path: addQueryArgs( '/newspack-popups/v1/custom-placement/', {
					custom_placement: customPlacement,
				} ),
				method: 'GET',
			} );

			setPrompts( response );
			setLoading( false );
		} catch ( e ) {
			setError(
				e.message ||
					__( 'There was an error fetching prompts for this custom placement.', 'newspack-popups' )
			);
			setLoading( false );
		}
	};

	useEffect( () => {
		if ( customPlacement ) {
			getPrompts();
		}
	}, [ customPlacement ] );

	const segments = {};

	if ( prompts ) {
		prompts.forEach( prompt => {
			const assignedSegments = prompt.segments || [];

			assignedSegments.forEach( segment => {
				const segmentName = segment?.name || 'Everyone else';

				if ( ! segments[ segmentName ] ) {
					segments[ segmentName ] = {
						id: segment.id || null,
						prompts: [],
					};
				}

				segments[ segmentName ].prompts.push( { id: prompt.id, title: prompt.title } );
			} );
		} );
	}

	return (
		<Placeholder
			className="newspack-popups__custom-placement-placeholder"
			label={ __( 'Custom Placement', 'newspack-popups' ) }
			icon={ megaphone }
		>
			<SelectControl
				id="newspack-popups__custom-placement-select"
				onChange={ _customPlacement => setAttributes( { customPlacement: _customPlacement } ) }
				value={
					-1 < Object.keys( customPlacements ).indexOf( customPlacement ) ? customPlacement : ''
				}
				options={ customPlacementOptions }
			/>

			{ loading && <Spinner /> }

			{ error && (
				<div className="newspack-popups__custom-placement-prompts">
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				</div>
			) }

			{ ! loading && ! error && Array.isArray( prompts ) && (
				<div className="newspack-popups__custom-placement-prompts">
					{ 0 === prompts.length && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'No active prompts found for this custom placement.', 'newspack-popups' ) }
						</Notice>
					) }
					{ 0 < prompts.length && (
						<>
							<p>
								{ sprintf(
									// Translators: Max. number of popups displayed; plural modifier.
									__(
										'This custom placement will display at most %1$sthe following active prompt%2$s, depending on the reader’s top-priority segment:',
										'newspack-popups'
									),
									1 < prompts.length ? 'one of ' : '',
									1 < prompts.length ? 's' : ''
								) }
							</p>
							{ Object.keys( segments ).map( segmentName => {
								const segmentId = segments[ segmentName ].id;
								return (
									<Fragment key={ segmentId }>
										<strong>
											{ segmentId ? (
												<ExternalLink
													href={ `/wp-admin/admin.php?page=newspack-popups-wizard#/segments/${ segmentId }` }
												>
													{ sprintf(
														// Translators: Segment name.
														__( 'Segment: %s', 'newspack-popups' ),
														segmentName
													) }
												</ExternalLink>
											) : (
												[
													'Everyone' !== segmentName ? __( 'Segment: ', 'newspack-popups' ) : '',
													segmentName || '',
													'Everyone' === segmentName && 1 < Object.keys( segments ).length
														? __( ' else', 'newspack-popups' )
														: '',
												]
											) }
										</strong>
										<ul>
											{ segments[ segmentName ].prompts.map( prompt => (
												<li key={ prompt.id }>
													<ExternalLink
														href={ `/wp-admin/post.php?post=${ prompt.id }&action=edit` }
													>
														{ prompt.title }
													</ExternalLink>
												</li>
											) ) }
										</ul>
									</Fragment>
								);
							} ) }
						</>
					) }
				</div>
			) }
		</Placeholder>
	);
};
