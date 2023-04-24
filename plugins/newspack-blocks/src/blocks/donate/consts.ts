/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { DonationFrequencySlug, DonateBlockAttributes } from './types';

export const FREQUENCIES: { [ Key in DonationFrequencySlug as string ]: string } = {
	once: __( 'One-time', 'newspack-blocks' ),
	month: __( 'Monthly', 'newspack-blocks' ),
	year: __( 'Annually', 'newspack-blocks' ),
};

export const FREQUENCY_SLUGS: DonationFrequencySlug[] = Object.keys(
	FREQUENCIES
) as DonationFrequencySlug[];

export const LAYOUT_OPTIONS: { label: string; key: DonateBlockAttributes[ 'layoutOption' ] }[] = [
	{ label: __( 'Frequency', 'newspack-blocks' ), key: 'frequency' },
	{ label: __( 'Tiers', 'newspack-blocks' ), key: 'tiers' },
];

// The item at fourth position is the "other" value, it is not a tier.
export const DISABLED_IN_TIERS_BASED_LAYOUT_TIER_INDEX = 3;
