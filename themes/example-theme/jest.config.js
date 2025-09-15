const defaultConfig = require('@wordpress/scripts/config/jest-unit.config.js');

module.exports = {
	...defaultConfig,
	testMatch: [
		'**/tests/js/**/*.[jt]s?(x)',
		'**/tests/js/**/?(*.)test.[jt]s?(x)'
	],
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'/build/',
		'/tests/phpunit/'
	]
};
