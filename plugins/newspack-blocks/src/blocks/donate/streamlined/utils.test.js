import 'regenerator-runtime';

import { computeFeeAmount } from './utils';

describe( 'Fee computation', () => {
	it( 'computes fee with default values', () => {
		expect( computeFeeAmount( 1, 2.9, 0.3 ) ).toBe( 0.34 );
		expect( computeFeeAmount( 15, 2.9, 0.3 ) ).toBe( 0.76 );
		expect( computeFeeAmount( 100, 2.9, 0.3 ) ).toBe( 3.3 );
	} );
	it( 'computes fee with other values', () => {
		expect( computeFeeAmount( 15, 0, 0 ) ).toBe( 0 );
		expect( computeFeeAmount( 15, 2.3, 0.3 ) ).toBe( 0.66 );
		expect( computeFeeAmount( 15, 2.3, 0 ) ).toBe( 0.35 );
		expect( computeFeeAmount( 15, 50, 0 ) ).toBe( 15 );
		expect( computeFeeAmount( 15, 50, 10 ) ).toBe( 35 );
	} );
} );
