import * as testingLibrary from '@testing-library/dom';
import userEvent from '@testing-library/user-event';
import { encode } from 'html-entities';
import 'regenerator-runtime';

import { processTiersBasedElements } from '.';

const frequencies = { once: 'Once', month: 'Monthly', year: 'Annually' };
const stripeSettings = [ 'USD', '$', 'Testing Site', false, 'US', frequencies, '2.9', '0.3' ];
// These amounts contain the "once" frequency on last index, which is not used in the tiers-based block.
const amounts = { once: [ 1, 2, 3, 4 ], month: [ 5, 6, 7, 8 ], year: [ 9, 10, 11, 12 ] };
const TIERS = [
	{
		heading: 'Small',
	},
	{
		heading: 'Medium',
	},
	{
		heading: 'Large',
	},
];
const MONTH_RENEWAL_DATE = 'December 16, 2022';
const YEAR_RENEWAL_DATE = 'December 16, 2022';
const COLOR = '#3366ff';
const rawTiersBasedConfig = [
	'donation_frequency',
	'donation_value_',
	'month',
	TIERS,
	amounts,
	MONTH_RENEWAL_DATE,
	YEAR_RENEWAL_DATE,
	true,
	COLOR,
];

const createDOM = ( settings, tiersConfig ) => {
	const parentElement = document.createElement( 'div' );
	parentElement.innerHTML = `
    <style>.wpbnbd__tiers__view--hidden {display:none;}</style>

    <div
        class="wp-block-newspack-blocks-donate wpbnbd wpbnbd--tiers-based is-style-alternate wpbnbd-frequencies--2"
        data-streamlined-config="${ encode( JSON.stringify( settings ) ) }"
        data-tiers-based-config="${ encode( JSON.stringify( tiersConfig ) ) }"
    >
        <form data-is-init-form="" onsubmit="return false;">
            <div class="wpbnbd__tiers__view">
                <input type="hidden" name="newspack_donate" value="1">
                <input type="hidden" name="donation_frequency" value="month">
                <div class="wpbnbd__tiers">
                    <div class="wpbnbd__tiers__selection">
                        <button type="button" data-frequency-slug="month" data-frequency-label=" per month" class="wpbnbd__button wpbnbd__button--active">Monthly</button>
                        <button type="button" data-frequency-slug="year" data-frequency-label=" per year" class="wpbnbd__button ">Annually</button>
                    </div>
                    <div class="wpbnbd__tiers__options">
                        ${ TIERS.map(
													( tier, index ) => `
                            <div class="wpbnbd__tiers__tier ">
                                <div class="wpbnbd__tiers__top">
                                    <h2 class="wpbnbd__tiers__heading">${ tier.heading }</h2>
                                </div>
                                <div class="wpbnbd__tiers__amount">
                                    <span>
                                        <span data-frequency-slug="month" data-amount="${ amounts.month[ index ] }" data-tier-index="${ index }">
                                            <h3 class="wpbnbd__tiers__amount__number">$${ amounts.month[ index ] }</h3>
                                            <span class="wpbnbd__tiers__amount__frequency"> per month</span>
                                        </span>
                                        <span style="display:none;" data-frequency-slug="year" data-amount="${ amounts.year[ index ] }" data-tier-index="${ index }">
                                            <h3 class="wpbnbd__tiers__amount__number">$${ amounts.year[ index ] }</h3>
                                            <span class="wpbnbd__tiers__amount__frequency"> per year</span>
                                        </span>
                                    </span>
                                </div>
                                <button type="submit" name="donation_value_month" value="${ amounts.month[ index ] }" data-tier-index="${ index }" style="background-color: ${ COLOR }; color: white;">Select </button>
                                <div class="wpbnbd__tiers__description">This is the description of the plan.</div>
                            </div>
                        `
												) }
                    </div>
                    <ul class="wpbnbd__tiers__options__dots"><li></li><li></li><li></li></ul>
                </div>
            </div>
        </form>
        <div class="wpbnbd__tiers__view wpbnbd__tiers__view--hidden">
            <form data-is-streamlined-form="" onsubmit="return false;">
                <button class="wpbnbd__tiers__back-button">← <span>Back</span></button>
                <div class="wpbnbd__tiers__tier-tile">
                    <h2>Small</h2>
                    <div>
                        <div>
                            <h3>
                                <span>$</span>
                                <span data-amount="">${ amounts.month[ 0 ] }</span>
                            </h3>
                            <span data-frequency=""> per month</span>
                        </div>
                        <div class="wpbnbd__tiers__tier-tile__note">
                            Renews on <span data-renews-date="">–</span>
                        </div>
                    </div>
                </div>
                <div class="wp-block-newspack-blocks-donate__stripe stripe-payment"></div>
                <input data-is-streamlined-input-amount="" type="hidden" name="donation_value_month" value="${
									amounts.month[ 0 ]
								}">
                <input type="hidden" name="donation_frequency" value="month">
            </form>
        </div>
    </div>
	`;
	document.body.appendChild( parentElement );
	return document.body;
};

describe( 'Tiers-based Donate block processing', () => {
	// Handle the API missing on JSDOM.
	Element.prototype.scrollTo = jest.fn();

	const container = createDOM( stripeSettings, rawTiersBasedConfig );
	processTiersBasedElements( container );

	it( 'initially only montly amounts are visible', () => {
		testingLibrary
			.getAllByText( container, 'per month' )
			// Only first three are visible, the last "per month" is in the Stripe payment form.
			.slice( 0, 3 )
			.forEach( el => expect( el ).toBeVisible() );
		testingLibrary
			.getAllByText( container, 'per year' )
			.forEach( el => expect( el ).not.toBeVisible() );

		amounts.month.slice( 0, 3 ).forEach( amount => {
			expect( testingLibrary.getByText( container, `$${ amount }` ) ).toBeVisible();
		} );
		amounts.year.slice( 0, 3 ).forEach( amount => {
			expect( testingLibrary.getByText( container, `$${ amount }` ) ).not.toBeVisible();
		} );
	} );

	it( 'selecting the tier switches the view', async () => {
		const selectedTierIndex = 1;
		const tierSelectButton = testingLibrary.getAllByText( container, 'Select' )[
			selectedTierIndex
		];
		await userEvent.click( tierSelectButton );

		const expectedAmount = amounts.month[ selectedTierIndex ];
		expect(
			// Second heading – on the tile, the first is hidden in tiers options list.
			testingLibrary.getAllByText( container, TIERS[ selectedTierIndex ].heading )[ 1 ]
		).toBeVisible();
		expect( testingLibrary.getByText( container, expectedAmount ) ).toBeVisible();
		expect( testingLibrary.getByText( container, MONTH_RENEWAL_DATE ) ).toBeVisible();
		expect(
			parseInt( container.querySelector( 'input[name="donation_value_month"]' ).value )
		).toEqual( expectedAmount );
		expect( container.querySelector( 'input[name="donation_frequency"]' ).value ).toEqual(
			'month'
		);
	} );

	it( 'clicking the back button switches the view back to tier selection', async () => {
		const backButton = testingLibrary.getByText( container, 'Back' );
		await userEvent.click( backButton );
		expect( testingLibrary.getByText( container, 'Back' ) ).not.toBeVisible();
	} );

	it( 'after switching to annual payments, only yearly amounts are visible', async () => {
		const annualPaymentsButton = testingLibrary.getByText( container, 'Annually' );
		await userEvent.click( annualPaymentsButton );
		testingLibrary
			.getAllByText( container, 'per year' )
			// Only first three are visible, the last "per year" is in the Stripe payment form.
			.slice( 0, 3 )
			.forEach( el => expect( el ).toBeVisible() );
		testingLibrary
			.getAllByText( container, 'per month' )
			.forEach( el => expect( el ).not.toBeVisible() );

		amounts.year.slice( 0, 3 ).forEach( amount => {
			expect( testingLibrary.getByText( container, `$${ amount }` ) ).toBeVisible();
		} );
		amounts.month.slice( 0, 3 ).forEach( amount => {
			expect( testingLibrary.getByText( container, `$${ amount }` ) ).not.toBeVisible();
		} );
	} );

	it( 'selecting the tier after choosing annual payment switches the view', async () => {
		const selectedTierIndex = 2;
		const tierSelectButton = testingLibrary.getAllByText( container, 'Select' )[
			selectedTierIndex
		];
		await userEvent.click( tierSelectButton );

		const expectedAmount = amounts.year[ selectedTierIndex ];
		expect(
			// Second heading – on the tile, the first is hidden in tiers options list.
			testingLibrary.getAllByText( container, TIERS[ selectedTierIndex ].heading )[ 1 ]
		).toBeVisible();
		expect( testingLibrary.getByText( container, expectedAmount ) ).toBeVisible();
		expect( testingLibrary.getByText( container, MONTH_RENEWAL_DATE ) ).toBeVisible();
		expect(
			parseInt( container.querySelector( 'input[name="donation_value_year"]' ).value )
		).toEqual( expectedAmount );
		expect( container.querySelector( 'input[name="donation_frequency"]' ).value ).toEqual( 'year' );
	} );
} );
