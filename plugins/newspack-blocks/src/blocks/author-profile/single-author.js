/* eslint-disable jsx-a11y/anchor-is-valid */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import classnames from 'classnames';

// Show a link to the author's post archive page, if available.
const MaybeLink = ( { author, children, showArchiveLink } ) =>
	showArchiveLink && author && author.url ? (
		<a href="#" className="no-op">
			{ children }
		</a>
	) : (
		<>{ children }</>
	);

export const SingleAuthor = ( { author, attributes } ) => {
	const {
		showBio,
		showSocial,
		showEmail,
		showArchiveLink,
		showAvatar,
		textSize,
		avatarAlignment,
		avatarBorderRadius,
		avatarSize,
	} = attributes;

	// Combine social links and email, which are shown together.
	const socialLinks = ( showSocial && author && author.social ) || {};
	if ( showEmail && author && author.email ) {
		socialLinks.email = author.email;
	} else {
		delete socialLinks.email;
	}
	if ( attributes.shownewspack_phone_number && author && author.newspack_phone_number ) {
		socialLinks.newspack_phone_number = author.newspack_phone_number;
	} else {
		delete socialLinks.newspack_phone_number;
	}

	const employment = [
		attributes.shownewspack_role && author.newspack_role,
		attributes.shownewspack_employer && author.newspack_employer,
	]
		.filter( Boolean )
		.join( ', ' );
	const socialLinksItems = Object.keys( socialLinks );

	return (
		<div
			className={ classnames(
				'wp-block-newspack-blocks-author-profile',
				'avatar-' + avatarAlignment,
				'text-size-' + textSize,
				{ 'is-style-center': 'is-style-center' === attributes.className }
			) }
		>
			{ showAvatar && author.avatar && (
				<div className="wp-block-newspack-blocks-author-profile__avatar">
					<figure
						style={ {
							borderRadius: avatarBorderRadius,
							height: `${ avatarSize }px`,
							width: `${ avatarSize }px`,
						} }
						dangerouslySetInnerHTML={ { __html: author.avatar } }
					/>
				</div>
			) }
			<div className="wp-block-newspack-blocks-author-profile__bio">
				<h3>
					<MaybeLink author={ author } showArchiveLink={ showArchiveLink }>
						{ author.name }
					</MaybeLink>
				</h3>
				{ attributes.shownewspack_job_title && author.newspack_job_title && (
					<p className="wp-block-newspack-blocks-author-profile__job-title">
						{ author.newspack_job_title }
					</p>
				) }
				{ employment && (
					<p className="wp-block-newspack-blocks-author-profile__employment">{ employment }</p>
				) }
				{ showBio && author.bio && (
					<p>
						{ author.bio }{ ' ' }
						{ showArchiveLink && (
							<a href="#" className="no-op">
								{ __( 'More by', 'newspack-blocks' ) + ' ' + author.name }
							</a>
						) }
					</p>
				) }
				{ socialLinksItems.length !== 0 && (
					<ul className="wp-block-newspack-blocks-author-profile__social-links">
						{ socialLinksItems.map( service => (
							<li key={ service }>
								<a href="#" className="no-op">
									{ socialLinks[ service ].svg && (
										<span dangerouslySetInnerHTML={ { __html: socialLinks[ service ].svg } } />
									) }
									<span className={ socialLinks[ service ].svg ? 'hidden' : 'visible' }>
										{ service }
									</span>
								</a>
							</li>
						) ) }
					</ul>
				) }
			</div>
		</div>
	);
};
