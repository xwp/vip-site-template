/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import { Button } from '@wordpress/components';
import { withDispatch } from '@wordpress/data';
import { Icon, pullquote } from '@wordpress/icons';

const placeholderBlocks = {
	'newspack-ads/ad-unit': {
		title: __( 'Ad Unit', 'newspack-blocks' ),
		description: __( 'Render an ad unit from your inventory.', 'newspack-blocks' ),
		icon: pullquote,
		message: __( 'Place ad units inside your page by installing Newspack Ads.', 'newspack-blocks' ),
		url: 'https://help.newspack.com/revenue/advertising/',
	},
};

function registerPlaceholderBlock( blockName, { title, description, icon, message, url } ) {
	if ( getBlockType( blockName ) ) {
		return;
	}
	title = title || blockName.split( '/' ).pop();
	const edit = ( { clientId, removeBlocks } ) => {
		const blockProps = useBlockProps(); // eslint-disable-line
		return (
			<div { ...blockProps }>
				<div className="newspack-blocks-placeholder-block">
					<div className="newspack-blocks-placeholder-block__label">
						{ icon && <Icon icon={ icon } /> }
						{ title }
					</div>
					<p>
						<strong>
							{ sprintf(
								// translators: %s is the block name.
								__( 'The "%s" block is currently not available.', 'newspack-blocks' ),
								title
							) }
						</strong>
					</p>
					{ message && <p>{ message }</p> }
					<div className="newspack-blocks-placeholder-block__buttons">
						{ url && (
							<Button variant="primary" target="_blank" rel="external" href={ url }>
								{ __( 'Visit Plugin Page', 'newspack-blocks' ) }
							</Button>
						) }
						<Button variant="secondary" isDestructive onClick={ () => removeBlocks( clientId ) }>
							{ __( 'Remove Block', 'newspack-blocks' ) }
						</Button>
					</div>
				</div>
			</div>
		);
	};
	registerBlockType( blockName, {
		title,
		description,
		icon,
		edit: withDispatch( dispatch => ( {
			removeBlocks: dispatch( 'core/block-editor' ).removeBlocks,
		} ) )( edit ),
		supports: {
			html: false,
			lock: false,
			reusable: false,
			inserter: false,
			defaultStylePicker: false,
			customClassName: false,
			className: false,
			alignWide: false,
			align: false,
			anchor: false,
		},
	} );
}

for ( const blockName in placeholderBlocks ) {
	registerPlaceholderBlock( blockName, placeholderBlocks[ blockName ] );
}
