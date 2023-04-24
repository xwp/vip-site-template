// eslint-disable-next-line import/no-extraneous-dependencies
import debounce from 'lodash.debounce';

import type { Configuration } from './types';

const findClosestIndex = ( arr: number[], num: number ) => {
	const closest =
		arr.reduce( ( acc, val ) => {
			if ( Math.abs( val - num ) < Math.abs( acc ) ) {
				return val - num;
			}
			return acc;
		}, Infinity ) + num;
	return arr.indexOf( closest );
};

export const setupSlider = ( parentEl: HTMLElement, configuration: Configuration ) => {
	const optionsEl = parentEl.querySelector( '.wpbnbd__tiers__options' );
	const optionsDotsEl = parentEl.querySelector( '.wpbnbd__tiers__options__dots' );
	if ( ! optionsEl || ! optionsDotsEl || ! configuration ) {
		return;
	}
	const getOptions = () =>
		Array.from( optionsEl.children ).map( ( optionEl: Element ) => ( {
			el: optionEl,
			width: optionEl.clientWidth,
		} ) );
	let options: { el: Element; width: number }[] = getOptions();
	const initialOptionIndex = Math.round( options.length / 2 ) - 1;
	const getOptionWidth = () => options[ initialOptionIndex ].width;
	const getOptionsOffsets = () => options.map( ( option, index ) => optionWidth * index );

	let optionWidth = getOptionWidth();
	let optionsOffsets = getOptionsOffsets();
	const initialScrollOffset = initialOptionIndex * optionWidth;

	window.addEventListener( 'resize', () => {
		options = getOptions();
		optionWidth = getOptionWidth();
		optionsOffsets = getOptionsOffsets();
		optionsEl.scrollTo( initialScrollOffset, 0 );
	} );

	const optionsDots = Array.from( optionsDotsEl.children ) as HTMLElement[];

	const handleScroll = debounce( () => {
		const closestIndex = findClosestIndex( optionsOffsets, optionsEl.scrollLeft );
		optionsDots.forEach( ( dotEl, index ) => {
			dotEl.style.backgroundColor = index === closestIndex ? configuration.buttonColor : '';
		} );
	}, 100 );
	optionsEl.addEventListener( 'scroll', handleScroll );

	optionsDots.forEach( ( dotEl, index ) => {
		dotEl.addEventListener( 'click', () => {
			optionsEl.scrollTo( {
				left: index * optionWidth,
				behavior: 'smooth',
			} );
		} );
	} );

	optionsEl.scrollTo( initialScrollOffset, 0 );
};
