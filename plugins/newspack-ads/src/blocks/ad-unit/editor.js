/**
 * Internal dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { name, settings } from '.';

registerBlockType( `newspack-ads/${ name }`, settings );
