/**
 * Prompt display settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import {
	RadioControl,
	RangeControl,
	SelectControl,
	ToggleControl,
	CheckboxControl,
} from '@wordpress/components';

/**
 * External dependencies
 */
import { without } from 'lodash';

/**
 * Internal dependencies
 */
import { getPlacementHelpMessage } from './utils';
import PositionPlacementControl from './PositionPlacementControl';

const Sidebar = props => {
	const {
		display_title,
		onMetaFieldChange,
		placement,
		overlay_size,
		trigger_type,
		trigger_delay,
		trigger_scroll_progress,
		trigger_blocks_count,
		archive_insertion_posts_count,
		archive_insertion_is_repeating,
		isOverlay,
		archive_page_types = [],
	} = props;
	const updatePlacement = value => {
		onMetaFieldChange( { placement: value } );
	};
	const updatePlacementWhenPopupIsFullWidth = () => {
		switch ( placement ) {
			case 'top_left':
			case 'top_right':
				onMetaFieldChange( { placement: 'top' } );
				break;
			case 'center_left':
			case 'center_right':
				onMetaFieldChange( { placement: 'center' } );
				break;
			case 'bottom_left':
			case 'bottom_right':
				onMetaFieldChange( { placement: 'bottom' } );
				break;
		}
	};
	const updateSize = size => {
		onMetaFieldChange( { overlay_size: size } );
		if ( 'full-width' === size ) {
			updatePlacementWhenPopupIsFullWidth();
		}
	};
	const customPlacements = window.newspack_popups_data?.custom_placements || {};
	const popupSizeOptions = window.newspack_popups_data?.popup_size_options || {};
	const availableArchivePageTypes = window.newspack_popups_data?.available_archive_page_types || [];

	const helpMessage = getPlacementHelpMessage( props );

	return (
		<>
			<RadioControl
				className="newspack-popups__prompt-type-control"
				label={ __( 'Prompt type', 'newspack-popups' ) }
				selected={ isOverlay ? 'center' : 'inline' }
				options={ [
					{ label: __( 'Inline', 'newspack-popups' ), value: 'inline' },
					{ label: __( 'Overlay', 'newspack-popups' ), value: 'center' },
				] }
				onChange={ updatePlacement }
			/>
			{ isOverlay ? (
				<>
					<SelectControl
						label={ __( 'Size', 'newspack-popups' ) }
						value={ overlay_size }
						onChange={ updateSize }
						options={ popupSizeOptions }
					/>
					<PositionPlacementControl
						layout={ placement }
						label={ __( 'Position', 'newspack-popups' ) }
						help={ helpMessage }
						value={ placement }
						onChange={ updatePlacement }
						size={ overlay_size }
					/>
				</>
			) : (
				<SelectControl
					label={ __( 'Placement' ) }
					help={ helpMessage }
					value={ placement }
					onChange={ updatePlacement }
					options={ [
						{ value: 'inline', label: __( 'In article content', 'newspack-popups' ) },
						{ value: 'archives', label: __( 'In archive pages' ) },
						{ value: 'above_header', label: __( 'Above site header', 'newspack-popups' ) },
						{ value: 'manual', label: __( 'Manual only', 'newspack-popups' ) },
					].concat(
						Object.keys( customPlacements ).map( key => ( {
							value: key,
							label: customPlacements[ key ],
						} ) )
					) }
				/>
			) }

			{ isOverlay && (
				<>
					<SelectControl
						label={ __( 'Trigger', 'newspack-popups' ) }
						help={ __( 'The event to trigger the prompt.', 'newspack-popups' ) }
						value={ trigger_type }
						options={ [
							{ label: __( 'Timer', 'newspack-popups' ), value: 'time' },
							{ label: __( 'Scroll Progress', 'newspack-popups' ), value: 'scroll' },
						] }
						onChange={ value => onMetaFieldChange( { trigger_type: value } ) }
					/>
					{ 'scroll' === trigger_type ? (
						<RangeControl
							label={ __( 'Scroll Progress (percent)', 'newspack-popups' ) }
							value={ trigger_scroll_progress }
							onChange={ value => onMetaFieldChange( { trigger_scroll_progress: value } ) }
							min={ 1 }
							max={ 100 }
						/>
					) : (
						<RangeControl
							label={ __( 'Delay (seconds)', 'newspack-popups' ) }
							value={ trigger_delay }
							onChange={ value => onMetaFieldChange( { trigger_delay: value } ) }
							min={ 0 }
							max={ 60 }
						/>
					) }
				</>
			) }
			{ placement === 'inline' && (
				<>
					<SelectControl
						label={ __( 'Insertion position', 'newspack-popups' ) }
						help={ __( 'The position at which to insert the prompt.', 'newspack-popups' ) }
						value={ trigger_type }
						options={ [
							{ label: __( 'Percentage', 'newspack-popups' ), value: 'scroll' },
							{ label: __( 'Blocks Count', 'newspack-popups' ), value: 'blocks_count' },
						] }
						onChange={ value => onMetaFieldChange( { trigger_type: value } ) }
					/>
					{ 'blocks_count' === trigger_type ? (
						<RangeControl
							label={ __( 'Number of blocks before the prompt', 'newspack-popups' ) }
							value={ trigger_blocks_count }
							onChange={ value => onMetaFieldChange( { trigger_blocks_count: value } ) }
							min={ 0 }
						/>
					) : (
						<RangeControl
							label={ __( 'Approximate Position (in percent)', 'newspack-popups' ) }
							value={ trigger_scroll_progress }
							onChange={ value => onMetaFieldChange( { trigger_scroll_progress: value } ) }
							min={ 0 }
							max={ 100 }
						/>
					) }
				</>
			) }
			{ placement === 'archives' && (
				<Fragment>
					<RangeControl
						label={ __( 'Number of articles before prompt', 'newspack-popups' ) }
						value={ archive_insertion_posts_count }
						onChange={ value => onMetaFieldChange( { archive_insertion_posts_count: value } ) }
						min={ 1 }
						max={ 20 }
					/>

					<div className="newspack-popups__prompt-type-control">
						<legend className="components-base-control__legend">
							{ __( 'Archive Page Types', 'newspack-popups' ) }
						</legend>
						{ availableArchivePageTypes.map( ( { name, label } ) => (
							<CheckboxControl
								key={ name }
								label={ label }
								checked={ archive_page_types.indexOf( name ) > -1 }
								onChange={ isIncluded => {
									onMetaFieldChange( {
										archive_page_types: isIncluded
											? [ ...archive_page_types, name ]
											: without( archive_page_types, name ),
									} );
								} }
							/>
						) ) }
					</div>

					<ToggleControl
						label={ __( 'Repeat prompt', 'newspack-popups' ) }
						checked={ archive_insertion_is_repeating }
						onChange={ value => onMetaFieldChange( { archive_insertion_is_repeating: value } ) }
					/>
				</Fragment>
			) }
			<ToggleControl
				label={ __( 'Display Prompt Title', 'newspack-popups' ) }
				checked={ display_title }
				onChange={ value => onMetaFieldChange( { display_title: value } ) }
			/>
		</>
	);
};

export default Sidebar;
