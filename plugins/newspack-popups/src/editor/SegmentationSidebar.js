/**
 * Popup segmentation options.
 */

/**
 * WordPress dependencies
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { BaseControl, CheckboxControl, ExternalLink, FormTokenField } from '@wordpress/components';

const defaultSegment = [ { id: '', name: __( 'Everyone', 'newspack-popups' ) } ];

const segmentsList =
	( window &&
		window.newspack_popups_data &&
		window.newspack_popups_data.segments.concat( defaultSegment ) ) ||
	defaultSegment;

const SegmentationSidebar = ( { onMetaFieldChange, selected_segment_id } ) => {
	const [ assignedSegments, setAssignedSegments ] = useState( [] );

	useEffect( () => {
		if ( selected_segment_id ) {
			setAssignedSegments( selected_segment_id.split( ',' ) );
		} else {
			setAssignedSegments( [] );
		}
	}, [ selected_segment_id ] );

	return (
		<Fragment>
			<BaseControl
				className="newspack-popups__segmentation-sidebar"
				help={
					! selected_segment_id
						? __( 'The prompt will be shown to all readers.', 'newspack-popups' )
						: sprintf(
								// Translators: Plural modifier.
								__(
									'The prompt will be shown only to readers who match the selected segment%s.',
									'newspack-popups'
								),
								1 === assignedSegments.length
									? ''
									: _x( 's', 'plural modifier for segment', 'newspack-popups' )
						  )
				}
			>
				{ 0 < segmentsList.length && (
					<FormTokenField
						value={ [] }
						onChange={ _segment => {
							const segmentToAssign = segmentsList.find(
								segment => segment.name === _segment[ 0 ]
							);

							if ( ! segmentToAssign ) {
								return;
							}

							if ( ! segmentToAssign.id ) {
								return onMetaFieldChange( { selected_segment_id: '' } );
							}

							assignedSegments.push( segmentToAssign.id );
							return onMetaFieldChange( { selected_segment_id: assignedSegments.join( ',' ) } );
						} }
						suggestions={ segmentsList
							.filter( segment => -1 === assignedSegments.indexOf( segment.id ) )
							.map( segment => segment.name ) }
						label={ __( 'Search Segments', 'newspack-popups' ) }
					/>
				) }
				{ segmentsList.map( segment => {
					const segmentIndex = assignedSegments.indexOf( segment.id );
					const segmentIsAssigned = segmentIndex > -1;
					return (
						<CheckboxControl
							key={ segment.id }
							value={ segment.id }
							label={ segment.name }
							onChange={ () => {
								if ( ! segment.id ) {
									return onMetaFieldChange( { selected_segment_id: '' } );
								}

								if ( segmentIsAssigned ) {
									assignedSegments.splice( segmentIndex, 1 );
								} else {
									assignedSegments.push( segment.id );
								}

								return onMetaFieldChange( { selected_segment_id: assignedSegments.join( ',' ) } );
							} }
							checked={ segment.id ? segmentIsAssigned : 0 === assignedSegments.length }
						/>
					);
				} ) }
			</BaseControl>
			<ExternalLink
				href="/wp-admin/admin.php?page=newspack-popups-wizard#/segments"
				key="segmentation-link"
			>
				{ __( 'Manage segments', 'newspack-popups' ) }
			</ExternalLink>
		</Fragment>
	);
};

export default SegmentationSidebar;
