/**
 * External dependencies
 */
import { Path, SVG } from '@wordpress/components';
import classNames from 'classnames';

const NewspackLogo = ( { size = 24, className } ) => (
	<SVG
		className={ classNames( 'newspack-logo', className ) }
		width={ size }
		height={ size }
		viewBox="0 0 32 32"
	>
		<Path
			d="M16 32c8.836 0 16-7.164 16-16S24.836 0 16 0 0 7.164 0 16s7.163 16 16 16z"
			fill="#36F"
		/>
		<Path
			d="M22.988 16.622h-1.72l-1.103-1.124h2.823v1.124zm0-3.31H18.02l-1.102-1.124h6.071v1.124zm0-3.31h-8.217l-1.103-1.125h9.32v1.125zm0 13.12L9.012 8.878v4.749l.069.071h-.07v9.426h3.451v-5.98l5.867 5.98h4.66z"
			fill="#fff"
		/>
	</SVG>
);

export default NewspackLogo;
