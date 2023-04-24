/**
 **** WARNING: No ES6 modules here. Not transpiled! ****
 */
/* eslint-disable import/no-nodejs-modules */
/* eslint-disable @typescript-eslint/no-var-requires */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const getBaseWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );
const path = require( 'path' );
const isDevelopment = process.env.NODE_ENV !== 'production';
const blockListFile = process.env.npm_config_block_list || 'block-list.json';
const blockList = JSON.parse( fs.readFileSync( blockListFile ) );

/**
 * Internal variables
 */
const editorSetup = path.join( __dirname, 'src', 'setup', 'editor' );

function blockScripts( type, inputDir, blocks ) {
	return blocks
		.map( block => path.join( inputDir, 'blocks', block, `${ type }.js` ) )
		.filter( fs.existsSync );
}

const blocksDir = path.join( __dirname, 'src', 'blocks' );
const blocks = fs
	.readdirSync( blocksDir )
	.filter( block => isDevelopment || blockList.production.includes( block ) )
	.filter( block => fs.existsSync( path.join( __dirname, 'src', 'blocks', block, 'editor.js' ) ) );

// Helps split up each block into its own folder view script
const viewBlocksScripts = blocks.reduce( ( viewBlocks, block ) => {
	const viewScriptPath = path.join( __dirname, 'src', 'blocks', block, 'view.js' );
	if ( fs.existsSync( viewScriptPath ) ) {
		viewBlocks[ block + '/view' ] = viewScriptPath;
	}
	return viewBlocks;
}, {} );

// Combines all the different blocks into one editor.js script
const editorScript = [
	editorSetup,
	...blockScripts( 'editor', path.join( __dirname, 'src' ), blocks ),
];

const placeholderBlocksScript = path.join( __dirname, 'src', 'setup', 'placeholder-blocks' );

const blockStylesScript = [ path.join( __dirname, 'src', 'block-styles', 'view' ) ];

const webpackConfig = getBaseWebpackConfig(
	{ WP: true },
	{
		entry: {
			placeholder_blocks: placeholderBlocksScript,
			editor: editorScript,
			block_styles: blockStylesScript,
			donateCheckoutModal: path.join( __dirname, 'src/blocks/donate/checkout-modal' ),
			donateCheckoutBlock: path.join( __dirname, 'src/blocks/donate/checkout-modal/block.js' ),
			donateStreamlined: path.join( __dirname, 'src/blocks/donate/streamlined' ),
			frequencyBased: path.join( __dirname, 'src/blocks/donate/frequency-based' ),
			tiersBased: path.join( __dirname, 'src/blocks/donate/tiers-based' ),
			...viewBlocksScripts,
		},
		'output-path': path.join( __dirname, 'dist' ),
	}
);

module.exports = webpackConfig;
