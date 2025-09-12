/**
 * Basic test setup to verify the testing environment works
 */

describe('Theme Test Environment', () => {
	it('should be able to run tests', () => {
		expect(true).toBe(true);
	});

	it('should have access to WordPress element', () => {
		// This test will pass if @wordpress/element is available
		const { createElement } = require('@wordpress/element');
		expect(typeof createElement).toBe('function');
	});
});
