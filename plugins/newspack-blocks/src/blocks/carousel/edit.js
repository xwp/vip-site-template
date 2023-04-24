/* eslint-disable jsx-a11y/anchor-is-valid, jsx-a11y/anchor-has-content, jsx-a11y/click-events-have-key-events, jsx-a11y/interactive-supports-focus */

/**
 * External dependencies
 */
import { isEqual } from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { dateI18n, __experimentalGetSettings } from '@wordpress/date';
import { Component, createRef, Fragment } from '@wordpress/element';
import {
	BaseControl,
	Button,
	ButtonGroup,
	PanelBody,
	PanelRow,
	Placeholder,
	RangeControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { withDispatch, withSelect } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import QueryControls from '../../components/query-controls';
import { PostTypesPanel, PostStatusesPanel } from '../../components/editor-panels';
import createSwiper from './create-swiper';
import {
	formatAvatars,
	formatByline,
	formatSponsorLogos,
	formatSponsorByline,
	getPostStatusLabel,
} from '../../shared/js/utils';
// Use same posts store as Homepage Posts block.
import { postsBlockSelector, postsBlockDispatch, shouldReflow } from '../homepage-articles/utils';

// Max number of slides that can be shown at once.
const MAX_NUMBER_OF_SLIDES = 6;

class Edit extends Component {
	constructor( props ) {
		super( props );

		this.btnPlayRef = createRef();
		this.btnPauseRef = createRef();
		this.btnNextRef = createRef();
		this.btnPrevRef = createRef();
		this.carouselRef = createRef();
		this.paginationRef = createRef();

		this.state = {
			swiperInitialized: false,
		};
	}

	componentDidMount() {
		this.initializeSwiper( 0 );
		this.props.triggerReflow();
	}

	componentDidUpdate( prevProps ) {
		const isVisible =
			0 < this.carouselRef.current.offsetWidth && 0 < this.carouselRef.current.offsetHeight;

		// Bail early if the component is hidden.
		if ( ! isVisible ) {
			return false;
		}

		// If the swiper hasn't been initialized yet, initialize it.
		if ( ! this.state.swiperInitialized ) {
			return this.initializeSwiper( 0 );
		}

		if ( shouldReflow( prevProps, this.props ) ) {
			this.props.triggerReflow();
		}

		const { attributes, latestPosts } = this.props;

		if (
			! isEqual( prevProps.latestPosts, latestPosts ) ||
			! isEqual( prevProps.attributes, attributes )
		) {
			let initialSlide = 0;

			if ( this.swiperInstance ) {
				if ( latestPosts && this.swiperInstance.realIndex < latestPosts.length ) {
					initialSlide = this.swiperInstance.realIndex;
				}
				this.setState( { swiperInitialized: false } );
				this.swiperInstance.destroy( true, true );
			}

			this.initializeSwiper( initialSlide );
		}
	}

	componentWillUnmount() {
		this.props.triggerReflow();
	}

	initializeSwiper( initialSlide ) {
		const { latestPosts } = this.props;

		if ( latestPosts && latestPosts.length ) {
			const { aspectRatio, autoplay, delay, slidesPerView } = this.props.attributes;
			const swiperInstance = createSwiper(
				{
					block: this.carouselRef.current, // Editor uses the same wrapper for block and swiper container.
					container: this.carouselRef.current,
					next: this.btnNextRef.current,
					prev: this.btnPrevRef.current,
					play: this.btnPlayRef.current,
					pause: this.btnPauseRef.current,
					pagination: this.paginationRef.current,
				},
				{
					aspectRatio,
					autoplay,
					delay: delay * 1000,
					initialSlide,
					slidesPerView: slidesPerView <= latestPosts.length ? slidesPerView : latestPosts.length,
				}
			);

			// Swiper won't be initialized unless the component is visible in the viewport.
			if ( swiperInstance ) {
				this.swiperInstance = swiperInstance;
				this.setState( { swiperInitialized: true } );
			}
		}
	}

	render() {
		const { attributes, className, setAttributes, latestPosts, isUIDisabled } = this.props;
		const {
			aspectRatio,
			authors,
			autoplay,
			categories,
			delay,
			hideControls,
			imageFit,
			postsToShow,
			postType,
			showCategory,
			showDate,
			showAuthor,
			showAvatar,
			showTitle,
			slidesPerView,
			specificMode,
			specificPosts,
			tags,
		} = attributes;
		const classes = classnames(
			className,
			'wp-block-newspack-blocks-carousel', // Default to make styles work for third-party consumers.
			'wpnbpc', // Shortened version of the default classname.
			'slides-per-view-' + slidesPerView,
			'swiper',
			{
				'wp-block-newspack-blocks-carousel__autoplay-playing': autoplay,
				'newspack-block--disabled': isUIDisabled,
				'hide-controls': hideControls,
			}
		);
		const dateFormat = __experimentalGetSettings().formats.date;
		const hasNoPosts = latestPosts && ! latestPosts.length;
		const hasOnePost = latestPosts && latestPosts.length === 1;
		const maxPosts = latestPosts ? Math.min( postsToShow, latestPosts.length ) : postsToShow;
		const aspectRatioOptions = [
			{
				value: 1,
				label: /* translators: label for square aspect ratio option */ __(
					'Square',
					'newspack-blocks'
				),
				shortName: /* translators: abbreviation for 1:1 aspect ratio */ __(
					'1:1',
					'newspack-blocks'
				),
			},
			{
				value: 0.75,
				label: /* translators: label for 4:3 aspect ratio option */ __( '4:3', 'newspack-blocks' ),
				shortName: /* translators: abbreviation for 4:3 aspect ratio */ __(
					'4:3',
					'newspack-blocks'
				),
			},
			{
				value: 0.5625,
				label: /* translators: label for 16:9 aspect ratio option */ __(
					'16:9',
					'newspack-blocks'
				),
				shortName: /* translators: abbreviation for 16:9 aspect ratio */ __(
					'16:9',
					'newspack-blocks'
				),
			},
			{
				value: 4 / 3,
				label: /* translators: label for 3:4 aspect ratio option */ __( '3:4', 'newspack-blocks' ),
				shortName: /* translators: abbreviation for 3:4 aspect ratio */ __(
					'3:4',
					'newspack-blocks'
				),
			},
			{
				value: 16 / 9,
				label: /* translators: label for 9:16 aspect ratio option */ __(
					'9:16',
					'newspack-blocks'
				),
				shortName: /* translators: abbreviation for 9:16 aspect ratio */ __(
					'9:16',
					'newspack-blocks'
				),
			},
		];
		return (
			<Fragment>
				<div className={ classes } ref={ this.carouselRef }>
					{ hasNoPosts && (
						<Placeholder className="component-placeholder__align-center">
							<div style={ { margin: 'auto' } }>{ __( 'Sorry, no posts were found.' ) }</div>
						</Placeholder>
					) }
					{ ( ! this.state.swiperInitialized || ! latestPosts ) && (
						<Placeholder icon={ <Spinner /> } className="component-placeholder__align-center" />
					) }
					{ latestPosts && (
						<Fragment>
							{ autoplay && (
								<Fragment>
									<button
										className="amp-carousel-button-pause amp-carousel-button"
										ref={ this.btnPauseRef }
									/>
									<button
										className="amp-carousel-button-play amp-carousel-button"
										ref={ this.btnPlayRef }
									/>
								</Fragment>
							) }
							<div className="swiper-wrapper">
								{ latestPosts.map( post => (
									<article
										className={ `post-has-image swiper-slide ${ post.post_type } ${
											post.newspack_article_classes || ''
										}` }
										key={ post.id }
									>
										{ getPostStatusLabel( post ) }
										<figure className="post-thumbnail">
											<a href="#" rel="bookmark">
												{ post.newspack_featured_image_src ? (
													<img
														className={ `image-fit-${ imageFit }` }
														src={ post.newspack_featured_image_src.large }
														alt=""
													/>
												) : (
													<div className="wp-block-newspack-blocks-carousel__placeholder" />
												) }
											</a>
										</figure>
										{ ( post.newspack_post_sponsors ||
											showCategory ||
											showTitle ||
											showAuthor ||
											showDate ) && (
											<div className="entry-wrapper">
												{ ( post.newspack_post_sponsors ||
													( showCategory && 0 < post.newspack_category_info.length ) ) && (
													<div
														className={
															'cat-links' + ( post.newspack_post_sponsors ? ' sponsor-label' : '' )
														}
													>
														{ post.newspack_post_sponsors && (
															<span className="flag">
																{ post.newspack_post_sponsors[ 0 ].flag }
															</span>
														) }
														{ showCategory &&
															( ! post.newspack_post_sponsors ||
																post.newspack_sponsors_show_categories ) && (
																<a href="#">{ decodeEntities( post.newspack_category_info ) }</a>
															) }
													</div>
												) }
												{ showTitle && (
													<h3 className="entry-title">
														<a href="#">{ decodeEntities( post.title.rendered.trim() ) }</a>
													</h3>
												) }
												<div className="entry-meta">
													{ post.newspack_post_sponsors && (
														<span
															className={ `entry-sponsors ${
																post.newspack_sponsors_show_author ? 'plus-author' : ''
															}` }
														>
															{ formatSponsorLogos( post.newspack_post_sponsors ) }
															{ formatSponsorByline( post.newspack_post_sponsors ) }
														</span>
													) }
													{ showAuthor &&
														showAvatar &&
														( ! post.newspack_post_sponsors ||
															post.newspack_sponsors_show_author ) &&
														formatAvatars( post.newspack_author_info ) }
													{ showAuthor &&
														( ! post.newspack_post_sponsors ||
															post.newspack_sponsors_show_author ) &&
														formatByline( post.newspack_author_info ) }
													{ showDate && (
														<time className="entry-date published" key="pub-date">
															{ dateI18n( dateFormat, post.date ) }
														</time>
													) }
												</div>
											</div>
										) }
									</article>
								) ) }
							</div>
							{ ! hasNoPosts && ! hasOnePost && (
								<>
									<button
										className="amp-carousel-button amp-carousel-button-prev swiper-button-prev"
										ref={ this.btnPrevRef }
									/>
									<button
										className="amp-carousel-button amp-carousel-button-next swiper-button-next"
										ref={ this.btnNextRef }
									/>
									<div
										className="swiper-pagination swiper-pagination-bullets amp-pagination"
										ref={ this.paginationRef }
									/>
								</>
							) }
						</Fragment>
					) }
				</div>
				<InspectorControls>
					<PanelBody title={ __( 'Display Settings' ) } initialOpen={ true }>
						{ postsToShow && (
							<QueryControls
								numberOfItems={ postsToShow }
								onNumberOfItemsChange={ value =>
									setAttributes( { postsToShow: value ? value : 1 } )
								}
								authors={ authors }
								onAuthorsChange={ value => setAttributes( { authors: value } ) }
								categories={ categories }
								onCategoriesChange={ value => setAttributes( { categories: value } ) }
								tags={ tags }
								onTagsChange={ value => setAttributes( { tags: value } ) }
								specificMode={ specificMode }
								onSpecificModeChange={ _specificMode =>
									setAttributes( { specificMode: _specificMode } )
								}
								specificPosts={ specificPosts }
								onSpecificPostsChange={ _specificPosts =>
									setAttributes( { specificPosts: _specificPosts } )
								}
								postType={ postType }
							/>
						) }
					</PanelBody>
					<PanelBody title={ __( 'Slideshow Settings' ) } initialOpen={ true }>
						<BaseControl
							label={ __( 'Slide Aspect Ratio', 'newspack-blocks' ) }
							help={ __(
								'All slides will share the same aspect ratio, for consistency.',
								'newspack-popups'
							) }
							id="newspack-blocks__aspect-ratio-control"
						>
							<PanelRow>
								<ButtonGroup
									id="newspack-blocks__aspect-ratio-control-buttons"
									aria-label={ __( 'Slide Aspect Ratio', 'newspack-blocks' ) }
								>
									{ aspectRatioOptions.map( option => {
										const isCurrent = aspectRatio === option.value;
										return (
											<Button
												isPrimary={ isCurrent }
												aria-pressed={ isCurrent }
												aria-label={ option.label }
												key={ option.value }
												onClick={ () => setAttributes( { aspectRatio: option.value } ) }
											>
												{ option.shortName }
											</Button>
										);
									} ) }
								</ButtonGroup>
							</PanelRow>
						</BaseControl>
						<BaseControl
							label={ __( 'Image Fit', 'newspack-blocks' ) }
							help={
								'cover' === imageFit
									? __(
											'The image will fill the entire slide and will be cropped if necessary.',
											'newspack-popups'
									  )
									: __(
											'The image will be resized to fit inside the slide without being cropped.',
											'newspack-popups'
									  )
							}
							id="newspack-blocks__blocks__image-fit-control"
						>
							<PanelRow>
								<ButtonGroup
									id="newspack-blocks__image-fit-buttons"
									aria-label={ __( 'Image Fit', 'newspack-blocks' ) }
								>
									<Button
										isPrimary={ 'cover' === imageFit }
										aria-pressed={ 'cover' === imageFit }
										aria-label={ __( 'Cover', 'newspack-blocks' ) }
										onClick={ () => setAttributes( { imageFit: 'cover' } ) }
									>
										{ __( 'Cover', 'newspack-blocks' ) }
									</Button>
									<Button
										isPrimary={ 'contain' === imageFit }
										aria-pressed={ 'contain' === imageFit }
										aria-label={ __( 'Contain', 'newspack-blocks' ) }
										onClick={ () => setAttributes( { imageFit: 'contain' } ) }
									>
										{ __( 'Contain', 'newspack-blocks' ) }
									</Button>
								</ButtonGroup>
							</PanelRow>
						</BaseControl>
						<ToggleControl
							label={ __( 'Hide Controls' ) }
							help={ __( 'Hide the slideshow UI. Useful when used with Autoplay.' ) }
							checked={ hideControls }
							onChange={ _hideControls => {
								setAttributes( { hideControls: _hideControls } );
							} }
						/>
						<ToggleControl
							label={ __( 'Autoplay' ) }
							help={ __( 'Autoplay between slides' ) }
							checked={ autoplay }
							onChange={ _autoplay => {
								setAttributes( { autoplay: _autoplay } );
							} }
						/>
						{ autoplay && (
							<RangeControl
								label={ __( 'Delay between transitions (in seconds)' ) }
								value={ delay }
								onChange={ _delay => {
									setAttributes( { delay: _delay } );
								} }
								min={ 1 }
								max={ 20 }
							/>
						) }
						{ latestPosts && 1 < latestPosts.length && (
							<RangeControl
								label={ __( 'Number of slides to show at once' ) }
								value={ slidesPerView <= latestPosts.length ? slidesPerView : latestPosts.length }
								onChange={ _slidesPerView => {
									setAttributes( { slidesPerView: _slidesPerView } );
								} }
								min={ 1 }
								max={
									specificMode
										? Math.min( MAX_NUMBER_OF_SLIDES, latestPosts.length )
										: Math.min( MAX_NUMBER_OF_SLIDES, maxPosts )
								}
							/>
						) }
					</PanelBody>
					<PanelBody title={ __( 'Article Meta Settings', 'newspack-blocks' ) }>
						<PanelRow>
							<ToggleControl
								label={ __( 'Show Title', 'newspack-blocks' ) }
								checked={ showTitle }
								onChange={ () => setAttributes( { showTitle: ! showTitle } ) }
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={ __( 'Show Date', 'newspack-blocks' ) }
								checked={ showDate }
								onChange={ () => setAttributes( { showDate: ! showDate } ) }
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={ __( 'Show Category', 'newspack-blocks' ) }
								checked={ showCategory }
								onChange={ () => setAttributes( { showCategory: ! showCategory } ) }
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={ __( 'Show Author', 'newspack-blocks' ) }
								checked={ showAuthor }
								onChange={ () => setAttributes( { showAuthor: ! showAuthor } ) }
							/>
						</PanelRow>
						{ showAuthor && (
							<PanelRow>
								<ToggleControl
									label={ __( 'Show Author Avatar', 'newspack-blocks' ) }
									checked={ showAvatar }
									onChange={ () => setAttributes( { showAvatar: ! showAvatar } ) }
								/>
							</PanelRow>
						) }
					</PanelBody>
					<PostTypesPanel attributes={ attributes } setAttributes={ setAttributes } />
					<PostStatusesPanel attributes={ attributes } setAttributes={ setAttributes } />
				</InspectorControls>
			</Fragment>
		);
	}
}

export default compose( [ withSelect( postsBlockSelector ), withDispatch( postsBlockDispatch ) ] )(
	Edit
);
