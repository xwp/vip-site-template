module.exports = {
	plugins: [
		require( 'postcss-preset-env' )( {
			stage: 2,
			features: {
				'custom-properties': {
					preserve: true, // Do not remove :root selector.
				},
			},
			autoprefixer: {
				grid: true,
			},
		} ),
	],
};
