import { getCookieValueFromLinker, substituteDynamicValue } from '.';

describe( 'amp-analytics linker handling', () => {
	beforeEach( () => {
		window.newspack_popups_view = { cid_cookie_name: 'newspack-cid' };
	} );

	const url =
		'https://example.com/lorem/?test=42&ref_newspack_cid=1*ab3otu*cid*T2ptZDdJNU9EYndELWV0TlM1N0FwSGVwNHE3S240VkVVU0o3YlNsaFc2YVVPRXJhSWFhaVlsOTgtQXNsc21Eeg..';
	const ampAnalyticsConfig = {
		linkers: { enabled: true, ref_newspack_cid: {} },
		cookies: { enabled: true, 'newspack-cid': {} },
	};
	const cookieValue =
		'newspack-cid=Ojmd7I5ODbwD-etNS57ApHep4q7Kn4VEUSJ7bSlhW6aUOEraIaaiYl98-AslsmDz';

	it( 'read linker param from URL and parse CID cookie value', () => {
		expect( getCookieValueFromLinker( ampAnalyticsConfig, url, '' ) ).toEqual( {
			cookieValue,
			cleanURL: 'https://example.com/lorem/?test=42',
		} );
	} );
	it( 'does not throw if malformed linker param is received', () => {
		expect(
			getCookieValueFromLinker(
				ampAnalyticsConfig,
				'https://example.com/lorem/?ref_newspack_cid=1*ab3otu*cid*,,,',
				''
			)
		).toEqual( { cookieValue: undefined, cleanURL: 'https://example.com/lorem/' } );
	} );
	it( 'return undefined if there is no linker param in the URL', () => {
		expect( getCookieValueFromLinker( ampAnalyticsConfig, 'https://example.com', '' ) ).toEqual( {
			cookieValue: undefined,
			cleanURL: 'https://example.com',
		} );
	} );
	it( 'return undefined if cookie is already set', () => {
		expect( getCookieValueFromLinker( ampAnalyticsConfig, url, 'newspack-cid=amp-123' ) ).toEqual( {
			cookieValue: undefined,
			cleanURL: 'https://example.com/lorem/?test=42',
		} );
	} );
	it( 'handles a URLs with hashes', () => {
		expect(
			getCookieValueFromLinker(
				ampAnalyticsConfig,
				'https://example.com/?ref_newspack_cid=123#hash-value',
				''
			).cleanURL
		).toEqual( 'https://example.com/#hash-value' );
		expect(
			getCookieValueFromLinker(
				ampAnalyticsConfig,
				'https://example.com/#hash-value?ref_newspack_cid=123',
				''
			).cleanURL
		).toEqual( 'https://example.com/#hash-value' );
		expect(
			getCookieValueFromLinker(
				ampAnalyticsConfig,
				'https://example.com/#hash-value?ref_newspack_cid=123&test=42',
				''
			).cleanURL
		).toEqual( 'https://example.com/?test=42#hash-value' );
	} );
} );

describe( 'dynamic value substitution', () => {
	beforeEach( () => {
		window.newspack_popups_view = { cid_cookie_name: 'newspack-cid' };
	} );

	it( 'replaces client id from cookie', () => {
		const clientId = 'id-42';
		global.document.cookie = '';
		expect( substituteDynamicValue( 'CLIENT_ID(newspack-cid)' ) ).toEqual( '' );
		global.document.cookie = `newspack-cid=${ clientId }`;
		expect( substituteDynamicValue( 'CLIENT_ID(newspack-cid)' ) ).toEqual( clientId );
		expect( substituteDynamicValue( 'CLIENT_ID( newspack-cid )' ) ).toEqual( clientId );
		expect( substituteDynamicValue( 'SOMETHING' ) ).toEqual( 'SOMETHING' );
	} );
} );
