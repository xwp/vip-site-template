import { __, sprintf, _n } from '@wordpress/i18n';

/**
 * Data selector for popup options (stored in post meta)
 *
 * @param {Function} select Select function
 */
export const optionsFieldsSelector = select => {
	const { getEditedPostAttribute } = select( 'core/editor' );
	const meta = getEditedPostAttribute( 'meta' );
	const {
		background_color,
		frequency,
		frequency_max,
		frequency_start,
		frequency_between,
		frequency_reset,
		display_title,
		hide_border,
		large_border,
		overlay_color,
		overlay_opacity,
		overlay_size,
		no_overlay_background,
		placement,
		trigger_type,
		trigger_delay,
		trigger_scroll_progress,
		trigger_blocks_count,
		archive_insertion_posts_count,
		archive_insertion_is_repeating,
		utm_suppression,
		selected_segment_id,
		post_types,
		archive_page_types,
		additional_classes,
		excluded_categories,
		excluded_tags,
	} = meta || {};

	const isOverlay = isOverlayPlacement( placement );

	return {
		background_color,
		display_title,
		hide_border,
		large_border,
		frequency,
		frequency_max,
		frequency_start,
		frequency_between,
		frequency_reset,
		overlay_color,
		overlay_opacity,
		overlay_size: 'full' === overlay_size ? 'full-width' : overlay_size,
		no_overlay_background,
		placement,
		trigger_type,
		trigger_delay,
		trigger_scroll_progress,
		trigger_blocks_count,
		archive_insertion_posts_count,
		archive_insertion_is_repeating,
		utm_suppression,
		selected_segment_id,
		isOverlay,
		post_types,
		archive_page_types,
		additional_classes,
		excluded_categories,
		excluded_tags,
	};
};

/**
 * Convert hex color to RGB.
 * From https://stackoverflow.com/questions/5623838/rgb-to-hex-and-hex-to-rgb
 *
 * @param {string} hex Color in HEX format
 */
const hexToRGB = hex =>
	hex
		.replace( /^#?([a-f\d])([a-f\d])([a-f\d])$/i, ( m, r, g, b ) => '#' + r + r + g + g + b + b )
		.substring( 1 )
		.match( /.{2}/g )
		.map( x => parseInt( x, 16 ) );

/**
 * Set the background color meta field.
 * Based on https://github.com/Automattic/newspack-theme/blob/master/newspack-theme/inc/template-functions.php#L401-L431
 *
 * @param {string} backgroundColor color string
 */
export const updateEditorColors = backgroundColor => {
	if ( ! backgroundColor ) {
		return;
	}
	const blackColor = '#000000';
	const whiteColor = '#ffffff';

	const backgroundColorRGB = hexToRGB( backgroundColor );
	const blackRGB = hexToRGB( blackColor );

	const l1 =
		0.2126 * Math.pow( backgroundColorRGB[ 0 ] / 255, 2.2 ) +
		0.7152 * Math.pow( backgroundColorRGB[ 1 ] / 255, 2.2 ) +
		0.0722 * Math.pow( backgroundColorRGB[ 2 ] / 255, 2.2 );
	const l2 =
		0.2126 * Math.pow( blackRGB[ 0 ] / 255, 2.2 ) +
		0.7152 * Math.pow( blackRGB[ 1 ] / 255, 2.2 ) +
		0.0722 * Math.pow( blackRGB[ 2 ] / 255, 2.2 );

	const contrastRatio =
		l1 > l2 ? parseInt( ( l1 + 0.05 ) / ( l2 + 0.05 ) ) : parseInt( ( l2 + 0.05 ) / ( l1 + 0.05 ) );

	const foregroundColor = contrastRatio > 5 ? blackColor : whiteColor;

	const editorStylesEl = document.querySelector( '.editor-styles-wrapper' );
	const editorPostTitleEl = document.querySelector(
		'.wp-block.editor-post-title__block .editor-post-title__input'
	);
	if ( editorStylesEl ) {
		editorStylesEl.style.backgroundColor = backgroundColor;
		editorStylesEl.style.color = foregroundColor;
	}
	if ( editorPostTitleEl ) {
		editorPostTitleEl.style.color = foregroundColor;
		editorPostTitleEl.style.setProperty(
			'--newspack-popups-editor-placeholder-color',
			`${ foregroundColor }80`
		);
	}
};

/**
 * Is the given placement value an overlay placement?
 *
 * @param {string} placementValue Placement of the prompt.
 * @return {boolean} Whether or not the prompt has an overlay placement.
 */
export const isOverlayPlacement = placementValue => {
	const overlayPlacements = window.newspack_popups_data?.overlay_placements || [];
	return -1 < overlayPlacements.indexOf( placementValue );
};

/**
 * Is the placement inline?
 * Not including Custom Placement or Manual-Only prompts.
 *
 * @param {string} placementValue Placement value of the prompt.
 * @return {boolean} True if placementValue is an inline placement.
 */
export const isInlinePlacement = placementValue =>
	-1 < [ 'inline', 'above_header', 'archives' ].indexOf( placementValue );

/**
 * Is the given placement value a custom placement?
 *
 * @param {string} placementValue Placement of the prompt.
 * @return {boolean} Whether or not the prompt has a custom placement.
 */
export const isCustomPlacement = placementValue => {
	const customPlacements = window.newspack_popups_data?.custom_placements || {};
	return -1 < Object.keys( customPlacements ).indexOf( placementValue );
};

/**
 * Is the given placement value a manual-only placement?
 *
 * @param {string} placementValue Placement of the prompt.
 * @return {boolean} Whether or not the prompt is manual-only.
 */
export const isManualOnlyPlacement = placementValue => 'manual' === placementValue;

/**
 * Given a placement value, construct a context-sensitive help message to display in the editor sidebar.
 *
 * @return {string} An appropriate help message.
 */
export const getPlacementHelpMessage = props => {
	if ( isCustomPlacement( props.placement ) ) {
		const customPlacements = window.newspack_popups_data?.custom_placements || {};
		return sprintf(
			// Translators: Custom placement name.
			__(
				'The prompt will appear where %s is inserted using the Custom Placement block.',
				'newspack-popups'
			),
			customPlacements[ props.placement ] || __( 'this custom placement', 'newspack-popups' )
		);
	}

	switch ( props.placement ) {
		case 'center':
			return __(
				'The prompt will be displayed as an overlay at the center of the viewport.',
				'newspack-popups'
			);
		case 'center_left':
			return __(
				'The prompt will be displayed as an overlay at the center left of the viewport.',
				'newspack-popups'
			);
		case 'center_right':
			return __(
				'The prompt will be displayed as an overlay at the center right of the viewport.',
				'newspack-popups'
			);
		case 'top':
			return __(
				'The prompt will be displayed as an overlay at the top of the viewport.',
				'newspack-popups'
			);
		case 'top_left':
			return __(
				'The prompt will be displayed as an overlay at the top left of the viewport.',
				'newspack-popups'
			);
		case 'top_right':
			return __(
				'The prompt will be displayed as an overlay at the top right of the viewport.',
				'newspack-popups'
			);
		case 'bottom':
			return __(
				'The prompt will be displayed as an overlay at the bottom of the viewport.',
				'newspack-popups'
			);
		case 'bottom_left':
			return __(
				'The prompt will be displayed as an overlay at the bottom left of the viewport.',
				'newspack-popups'
			);
		case 'bottom_right':
			return __(
				'The prompt will be displayed as an overlay at the bottom right of the viewport.',
				'newspack-popups'
			);
		case 'above_header':
			return __(
				'The prompt will be automatically inserted at the very top of the page, above the header.',
				'newspack-popups'
			);
		case 'inline':
			return props.trigger_type === 'blocks_count'
				? sprintf(
						// Translators: Blocks count until insertion.
						_n(
							'The prompt will be automatically inserted after %s block of content.',
							'The prompt will be automatically inserted after %s blocks of content.',
							props.trigger_blocks_count,
							'newspack-popups'
						),
						props.trigger_blocks_count
				  )
				: sprintf(
						// Translators: Article percentage count until insertion.
						__(
							'The prompt will be automatically inserted about %s into article content.',
							'newspack-popups'
						),
						props.trigger_scroll_progress + '%'
				  );
		case 'archives':
			return props.archive_insertion_is_repeating
				? sprintf(
						// Translators: Insertion period.
						__(
							'The prompt will be automatically inserted every %d articles in the archive pages.',
							'newspack-popups'
						),
						props.archive_insertion_posts_count
				  )
				: sprintf(
						// Translators: Insertion period articles count.
						__(
							'The prompt will be automatically inserted after %d articles in the archive pages.',
							'newspack-popups'
						),
						props.archive_insertion_posts_count
				  );
		case 'manual':
			return __(
				'The prompt will appear only where inserted using the Single Prompt block or a shortcode.',
				'newspack-popups'
			);
		default:
			return __( 'The placement where the prompt can appear.', 'newspack-popups' );
	}
};
