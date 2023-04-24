/**
 * Popup style options.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const StylesSidebar = props => {
	const { onMetaFieldChange, hide_border, large_border, isOverlay } = props;

	return (
		<div className="newspack-popups-style-selector">
			<Button
				variant={ ! hide_border && ! large_border ? 'primary' : 'secondary' }
				isPressed={ ! hide_border && ! large_border }
				onClick={ () => onMetaFieldChange( { hide_border: false, large_border: false } ) }
				aria-current={ ! hide_border && ! large_border }
			>
				{ __( 'Default', 'newspack-popups' ) }
			</Button>
			<Button
				variant={ hide_border ? 'primary' : 'secondary' }
				isPressed={ hide_border }
				onClick={ () => onMetaFieldChange( { hide_border: true, large_border: false } ) }
				aria-current={ hide_border }
			>
				{ sprintf(
					// Translators: %s is "padding" if the prompt is inline, or "border" if not
					__( 'Hide %s', 'newspack-popups' ),
					isOverlay ? __( 'Padding', 'newspack-popups' ) : __( 'Border', 'newspack-popups' )
				) }
			</Button>
			<Button
				variant={ large_border ? 'primary' : 'secondary' }
				isPressed={ large_border }
				onClick={ () => onMetaFieldChange( { hide_border: false, large_border: true } ) }
				aria-current={ large_border }
			>
				{ sprintf(
					// Translators: %s is "padding" if the prompt is inline, or "border" if not
					__( 'Large %s', 'newspack-popups' ),
					isOverlay ? __( 'Padding', 'newspack-popups' ) : __( 'Border', 'newspack-popups' )
				) }
			</Button>
		</div>
	);
};

export default StylesSidebar;
