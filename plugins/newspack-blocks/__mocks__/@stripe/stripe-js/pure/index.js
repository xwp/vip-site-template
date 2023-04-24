import 'regenerator-runtime';

export const loadStripe = async () => {
	return {
		elements: () => ( {
			create: () => ( {
				mount: () => {},
			} ),
		} ),
		createToken: () => ( {
			token: 'abc',
			error: null,
		} ),
		createSource: () => ( {
			source: { id: 'src_123' },
			error: null,
		} ),
		paymentRequest: () => ( { canMakePayment: () => false, on: () => {}, update: () => {} } ),
		confirmCardPayment: () => ( {
			paymentIntent: { status: 'succeeded' },
		} ),
	};
};
