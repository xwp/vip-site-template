/**
 * External dependencies
 */
import { without } from 'lodash';

/**
 * WordPress dependencies
 */
import { PanelRow, CheckboxControl } from '@wordpress/components';

const PostTypesPanel = ( { post_types = [], onMetaFieldChange } ) => {
	const availablePostTypes = [
		{ name: 'post', label: 'Posts' },
		{ name: 'page', label: 'Pages' },
		...window.newspack_popups_data.available_post_types,
	];

	return availablePostTypes.map( ( { name, label } ) => (
		<PanelRow key={ name }>
			<CheckboxControl
				label={ label }
				checked={ post_types.indexOf( name ) > -1 }
				onChange={ isIncluded => {
					onMetaFieldChange( {
						post_types: isIncluded ? [ ...post_types, name ] : without( post_types, name ),
					} );
				} }
			/>
		</PanelRow>
	) );
};

export default PostTypesPanel;
