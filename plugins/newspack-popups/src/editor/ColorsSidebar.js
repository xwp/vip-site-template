/**
 * Popup color options.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { RangeControl, ToggleControl } from '@wordpress/components';
import { ColorPaletteControl } from '@wordpress/block-editor';

const ColorsSidebar = ( {
	background_color,
	onMetaFieldChange,
	overlay_opacity,
	overlay_color,
	no_overlay_background,
	isOverlay,
} ) => (
	<Fragment>
		<ColorPaletteControl
			value={ background_color }
			onChange={ value => onMetaFieldChange( { background_color: value || '#FFFFFF' } ) }
			label={ __( 'Content Background Color', 'newspack-popups' ) }
		/>
		{ isOverlay && (
			<Fragment>
				<ToggleControl
					label={ __( 'Display overlay background', 'newspack-popups' ) }
					checked={ ! no_overlay_background }
					value={ ! no_overlay_background }
					onChange={ value => onMetaFieldChange( { no_overlay_background: ! value } ) }
				/>

				{ ! no_overlay_background && (
					<>
						<ColorPaletteControl
							value={ overlay_color }
							onChange={ value => onMetaFieldChange( { overlay_color: value || '#000000' } ) }
							label={ __( 'Overlay Background Color', 'newspack-popups' ) }
						/>
						<RangeControl
							label={ __( 'Overlay Background Opacity', 'newspack-popups' ) }
							value={ overlay_opacity }
							onChange={ value => onMetaFieldChange( { overlay_opacity: value } ) }
							min={ 0 }
							max={ 100 }
						/>
					</>
				) }
			</Fragment>
		) }
	</Fragment>
);

export default ColorsSidebar;
