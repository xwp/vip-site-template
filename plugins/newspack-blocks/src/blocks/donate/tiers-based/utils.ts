export const parseTiersBasedConfig = ( rawConfig = '' ) => {
	try {
		const items = JSON.parse( rawConfig );
		return {
			params: {
				frequency: items[ 0 ],
				tierPrefix: items[ 1 ],
			},
			initialFrequency: items[ 2 ],
			tiersBasedOptions: items[ 3 ],
			amounts: items[ 4 ],
			renewsAt: {
				month: items[ 5 ] as string,
				year: items[ 6 ] as string,
			},
			isRenderingStripePaymentForm: items[ 7 ],
			buttonColor: items[ 8 ],
		};
	} catch ( error ) {
		return false;
	}
};
