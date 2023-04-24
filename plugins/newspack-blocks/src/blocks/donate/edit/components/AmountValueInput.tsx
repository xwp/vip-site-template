import type { DonateBlockAttributes, ComponentProps, DonationFrequencySlug } from '../../types';

const AmountValueInput = ( {
	frequencySlug,
	tierIndex,
	id,
	label,
	attributes,
	settings,
	amounts,
	setAttributes,
	setSettings,
	disabled,
	ignoreMinimumAmount,
}: ComponentProps & {
	frequencySlug: DonationFrequencySlug;
	tierIndex: number;
	id: string;
	label?: string;
	disabled?: boolean;
	ignoreMinimumAmount?: boolean;
} ) => {
	const onChange = ( {
		value,
		frequency,
	}: {
		value: string;
		frequency: DonationFrequencySlug;
	} ) => {
		const subject = attributes.manual ? attributes : settings;
		subject.amounts[ frequency ][ tierIndex ] = parseFloat( value );
		const update: Partial< DonateBlockAttributes > = {
			amounts: {
				[ frequency ]: subject.amounts[ frequency ],
				...subject.amounts,
			},
		};

		if ( attributes.manual ) {
			setAttributes( update );
		} else {
			setSettings( update );
		}
	};
	const amount = amounts[ frequencySlug ][ tierIndex ];
	const value =
		settings.minimumDonation && ! ignoreMinimumAmount
			? Math.max( amount, settings.minimumDonation )
			: amount;

	return (
		<span key={ `${ frequencySlug }-${ tierIndex }` }>
			{ label && <label htmlFor={ id }>{ label }</label> }
			<input
				type="number"
				min={ ignoreMinimumAmount ? undefined : attributes.minimumDonation }
				onChange={ evt =>
					onChange( {
						value: evt.target.value,
						frequency: frequencySlug,
					} )
				}
				value={ value }
				id={ id }
				disabled={ disabled }
			/>
		</span>
	);
};

export default AmountValueInput;
