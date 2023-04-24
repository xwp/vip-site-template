/**
 * WordPress dependencies
 */
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const authorCustomFields = window.newspack_blocks_data?.author_custom_fields || [];

export const AuthorDisplaySettings = ( { attributes, setAttributes } ) => {
	const fields = [
		...[
			{
				name: 'Bio',
				label: __( 'Biographical info', 'newspack-blocks' ),
			},
			{
				name: 'Social',
				label: __( 'Social links', 'newspack-blocks' ),
			},
			{
				name: 'Email',
				label: __( 'Email address', 'newspack-blocks' ),
			},
			{
				name: 'ArchiveLink',
				label: __( 'Link to author archive', 'newspack-blocks' ),
			},
		],
		...authorCustomFields,
	];
	return (
		<>
			<p>{ __( 'Display the following fields:', 'newspack-blocks' ) }</p>
			{ fields.map( field => {
				const attributeName = `show${ field.name }`;
				return (
					<ToggleControl
						key={ field.name }
						label={ field.label }
						checked={ Boolean( attributes[ attributeName ] ) }
						onChange={ () => setAttributes( { [ attributeName ]: ! attributes[ attributeName ] } ) }
					/>
				);
			} ) }
		</>
	);
};
