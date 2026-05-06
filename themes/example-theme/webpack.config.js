/* eslint-disable @wordpress/dependency-group */
const path = require('path');
const glob = require('glob');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

/**
 * Get webpack entry points from glob patterns with proper naming.
 */
function getBlockEntries() {
	const entries = {};

	// Find all block directories
	const blockDirs = glob.sync('./src/blocks/*/', { onlyDirectories: true });

	blockDirs.forEach((blockDir) => {
		const blockName = path.basename(blockDir);

		// Dynamically find all JS files in each block
		const jsFiles = glob.sync(path.join(blockDir, 'js', '*.js'));
		jsFiles.forEach((jsFile) => {
			const fileName = path.parse(jsFile).name;
			const entryName = `blocks/${ blockName }/${ fileName }`;
			entries[ entryName ] = `./${ jsFile }`;
		});

		// CSS files should be imported by JS files, not separate entries
	});

	return entries;
}

function getFeatureEntries() {
	const entries = {};

	// Find all feature directories
	const featureDirs = glob.sync('./src/features/*/', { onlyDirectories: true });

	featureDirs.forEach((featureDir) => {
		const featureName = path.basename(featureDir);

		// Look for JS files
		const jsFiles = glob.sync(path.join(featureDir, 'js', '*.js'));
		jsFiles.forEach((jsFile) => {
			const fileName = path.parse(jsFile).name;
			const entryName = `features/${ featureName }/${ fileName }`;
			entries[ entryName ] = `./${ jsFile }`;
		});

		// CSS files should be imported by JS files, not separate entries
	});

	return entries;
}

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry,
		// Core assets
		frontend: path.resolve(process.cwd(), 'src/core/js/frontend.js'),
		editor: path.resolve(process.cwd(), 'src/core/js/editor.js'),
		// Block and feature entries
		...getBlockEntries(),
		...getFeatureEntries(),
	},
};
