import * as testingLibrary from '@testing-library/dom';
import userEvent from '@testing-library/user-event';
import fetchMock from 'fetch-mock-jest';
import { encode } from 'html-entities';

import { processStreamlinedElements } from '.';

const MONTHLY_AMOUNT = 7;

const createDOM = settings => {
	const parentElement = document.createElement( 'div' );
	parentElement.innerHTML = `
		<style>.stripe-payment--hidden {display:none;}</style>
		<form data-streamlined-config="${ encode( JSON.stringify( settings ) ) }">
			<div class='frequencies'>
				<div class='frequency'>
					<input type="radio" value="once" id="once" name="donation_frequency">
					<label for="once">Once</label>
					<input type="number" name="donation_value_once" value="${ MONTHLY_AMOUNT * 12 }" />
				</div>
				<div class='frequency'>
					<input type="radio" value="month" id="month" name="donation_frequency" checked>
					<label for="month">Monthly</label>
					<input type="number" name="donation_value_month" value="${ MONTHLY_AMOUNT }" />
				</div>
			</div>
			<div class="stripe-payment">
				<div class="stripe-payment__inputs stripe-payment--hidden">
					<input required="" placeholder="Email" type="email" name="email" value="">
					<input required="" placeholder="Full Name" type="text" name="full_name" value="">
				</div>
				<label>
					<input type="checkbox" name="agree_to_pay_fees" checked value="true">Agree to pay fees?
					<span id="stripe-fees-amount">($0)</span>
				</label>
				<div class="stripe-payment__messages"></div>
				<button type="submit">Donate</button>
			</div>
			<input name="cid" type="hidden" value="amp-123" />
		</form>
	`;
	document.body.appendChild( parentElement );
	return document.body;
};

fetchMock.post(
	'/wp-json/newspack-blocks/v1/donate',
	{ status: 'success', client_secret: 'sec_123' },
	// A slight delay, necessitated by the async behavior of @testing-library/user-event.
	// See https://github.com/testing-library/user-event/pull/952
	{ delay: 100 }
);

const frequencies = { once: 'Once', month: 'Monthly', year: 'Annually' };
const feeMultiplier = '2.9';
const feeStatic = '0.3';
const settings = [ 'USD', '$', 'Testing Site', false, 'US', frequencies, feeMultiplier, feeStatic ];

describe( 'Streamlined Donate block processing', () => {
	const container = createDOM( settings );
	processStreamlinedElements( container );

	const button = testingLibrary.getByText( container, 'Donate' );
	const emailInput = testingLibrary.getByPlaceholderText( container, 'Email' );
	const nameInput = testingLibrary.getByPlaceholderText( container, 'Full Name' );

	it( 'additional inputs are initially hidden and displayed after user clicks the button', async () => {
		expect( emailInput ).not.toBeVisible();
		await userEvent.click( button );
		expect( emailInput ).toBeVisible();
	} );

	it( 'form submission with invalid values triggers validation errors', async () => {
		await userEvent.click( button );
		expect(
			testingLibrary.getByText( container, 'Email address is invalid.' )
		).toBeInTheDocument();
		expect(
			testingLibrary.getByText( container, 'Full name should be provided.' )
		).toBeInTheDocument();

		await userEvent.type( emailInput, 'foo@bar.com' );
		await userEvent.click( button );
		expect(
			testingLibrary.queryByText( container, 'Email address is invalid.' )
		).not.toBeInTheDocument();
	} );

	it( 'the fee amount is updated', () => {
		expect( testingLibrary.getByText( container, '($0.52 monthly)' ) ).toBeInTheDocument();
	} );

	it( 'form can be submitted after validation passes', async () => {
		await userEvent.type( nameInput, 'Bax' );
		await userEvent.click( button );
		expect(
			testingLibrary.queryByText( container, 'Full name should be provided.' )
		).not.toBeInTheDocument();
		expect( testingLibrary.getByText( container, 'Processing payment…' ) ).toBeInTheDocument();
	} );

	it( 'final success message is displayed', async () => {
		await testingLibrary.waitFor( () => {
			expect(
				testingLibrary.getByText(
					container,
					'Your payment has been processed. Thank you for your contribution! You will receive a confirmation email at foo@bar.com.'
				)
			).toBeInTheDocument();
		} );
	} );

	it( 'correct payload was sent to the API', () => {
		expect( fetchMock ).toHaveLastFetched(
			'/wp-json/newspack-blocks/v1/donate',
			{
				body: {
					stripe_source_id: 'src_123',
					stripe_tokenization_method: 'card',
					amount: 7.52,
					email: 'foo@bar.com',
					full_name: 'Bax',
					frequency: 'month',
					newsletter_opt_in: false,
					clientId: 'amp-123',
					origin: null,
					additional_fields: [],
				},
			},
			'post'
		);
	} );
} );
