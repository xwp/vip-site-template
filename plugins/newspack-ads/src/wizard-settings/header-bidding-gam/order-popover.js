/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MenuItem, ExternalLink } from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import { ESCAPE } from '@wordpress/keycodes';

/**
 * Newspack dependencies
 */
import { Button, Popover } from 'newspack-components';

const OrderPopover = ( { disabled = false, isDraft, onEdit, onArchive, gamLink } ) => {
	const [ isVisible, setIsVisible ] = useState( false );
	const toggleVisible = () => setIsVisible( state => ! state );
	return (
		<>
			<Button
				isQuaternary
				isSmall
				disabled={ disabled }
				className={ isVisible && 'popover-active' }
				onClick={ toggleVisible }
				icon={ moreVertical }
				label={ __( 'More options', 'newspack' ) }
				tooltipPosition="bottom center"
			/>
			{ ! disabled && isVisible && (
				<Popover
					position="bottom left"
					onFocusOutside={ toggleVisible }
					onKeyDown={ event => ESCAPE === event.keyCode && toggleVisible }
				>
					<MenuItem onClick={ toggleVisible } className="screen-reader-text">
						{ __( 'Close Popover', 'newspack-ads' ) }
					</MenuItem>
					<MenuItem
						onClick={ () => {
							if ( typeof onEdit === 'function' ) onEdit();
						} }
						className="newspack-button"
					>
						{ __( 'Edit', 'newspack-ads' ) }
					</MenuItem>
					{ isDraft && (
						<MenuItem
							onClick={ () => {
								if ( typeof onArchive === 'function' ) onArchive();
							} }
							className="newspack-button"
						>
							{ __( 'Archive', 'newspack-ads' ) }
						</MenuItem>
					) }
					<MenuItem className="newspack-button">
						<ExternalLink href={ gamLink }>{ __( 'GAM Dashboard', 'newspack-ads' ) }</ExternalLink>
					</MenuItem>
				</Popover>
			) }
		</>
	);
};

export default OrderPopover;
