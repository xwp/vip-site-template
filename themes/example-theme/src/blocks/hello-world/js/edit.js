/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import metadata from '../block.json';

const Edit = () => {
	const blockProps = useBlockProps();
	return <p { ...blockProps }>{ __( 'Hello World', 'example-theme' ) }</p>;
};

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
