/**
 * Replace parts of the default wp-scripts Webpack entrypoint logic
 * to remove `style` cache groups for CSS imports and
 *
 * @see https://github.com/WordPress/gutenberg/blob/c7c4858525e2ff2cca39bd68e4e2665c004b0826/packages/scripts/utils/config.js#L181-L303
 */

/**
 * External dependencies
 */
const path = require( 'path' );
const glob = require( 'glob' );

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// wp-scripts prefixes every stylesheet name which starts with `style` with
// a chunk name. E.g., if a `style.scss` file is imported in a `script.js` file,
// the CSS file in the `build` folder will be `script-style.css`. We need to
// remove the `style` cache group in order to prevent this odd behavior.
if ( defaultConfig?.optimization?.splitChunks?.cacheGroups?.style ) {
	delete defaultConfig.optimization.splitChunks.cacheGroups.style;
}

// Disables webpack's file hashes & resolving for CSS imports.
// Documentation: https://webpack.js.org/loaders/css-loader/#url
// Adds the url false to the css-loader options, with the following intention:
// cssLoaders = [
//	{
//		loader: MiniCSSExtractPlugin.loader,
//	},
//	{
//		loader: require.resolve( 'css-loader' ),
//		options: {
//	++		url: false
//			...
// Note for future self, look at the webpack.config.js from @wordpress/scripts
// in case the structure changes.
if ( defaultConfig?.module?.rules?.length > 0 ) {
	defaultConfig.module.rules.forEach( ( rule ) => {
		if ( ! rule?.use || ! Array.isArray( rule.use ) ) {
			return;
		}

		rule.use.forEach( ( step ) => {
			if ( step?.loader === require.resolve( 'css-loader' ) ) {
				step.options.url = false;
			}
		} );
	} );
}

/**
 * Get webpack entry points from a glob pattern.
 *
 * @param {string} pattern Glob pattern.
 * @param {Object} options Glob options.
 *
 * @return {Object} Entry points.
 */
function getEntryPathsFromGlob( pattern, options ) {
	return glob.sync( pattern, options ).reduce(
		( entries, filename ) => {
			const file = path.parse( filename );
			return {
				...entries,
				// In order to persist the source directory structure inside the
				// build folder (and not keep all the built files flat), we have
				// to use this special entry name structure.
				[ path.join( file.dir, file.name ) ]: `./${ path.join( file.dir, file.base ) }`,
			};
		},
		{}
	);
}

const webpackConfig = {
	...defaultConfig,
	entry: {
		...getEntryPathsFromGlob( './blocks/*/*.js' ),
		...getEntryPathsFromGlob( './js/*.js' ),
		...getEntryPathsFromGlob( './css/!(_)*.{sass,scss}' ),
		...getEntryPathsFromGlob( './css/color-scheme/**/!(_)*.{sass,scss}' ),
	},
	output: {
		path: path.join( __dirname, 'build' ),
		chunkFilename: 'webpack/chunks/[id].[fullhash].js',
		hotUpdateChunkFilename: 'webpack/hot-update/[id].[fullhash].js',
		hotUpdateMainFilename: 'webpack/hot-update/[runtime].[fullhash].json',
	},
	optimization: {
		...defaultConfig.optimization,
		runtimeChunk: {
			/**
			 * This is shared between all entrypoints to have a single instance of
			 * react-refresh entrypoint so it must be enqueued as a dependency
			 * for all other scripts.
			 *
			 * @see https://webpack.js.org/configuration/optimization/#optimizationruntimechunk
			 */
			name: 'webpack/runtime',
		},
	},
	devServer: {
		port: 'auto',
		allowedHosts: 'all',
		devMiddleware: {
			writeToDisk: true,
		},
	},
};

module.exports = webpackConfig;
