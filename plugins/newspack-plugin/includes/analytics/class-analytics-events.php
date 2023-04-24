<?php
/**
 * Newspack Analytics events features.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Analytics Events integrations.
 */
class Analytics_Events {

	/**
	 * JS event handlers counts.
	 *
	 * @var array
	 */
	public static $js_events = [
		'click'    => 0,
		'scroll'   => 0,
		'submit'   => 0,
		'visible'  => 0,
		'ini-load' => 0,
	];

	/**
	 * Events accumulated by block render callbacks.
	 *
	 * @var array
	 */
	public static $ntg_block_events = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'render_block', [ __CLASS__, 'prepare_blocks_for_events' ], 10, 2 );
		add_action( 'comment_form', [ __CLASS__, 'prepare_comment_events' ] );

		// WooCommerce hooks. https://docs.woocommerce.com/wc-apidocs/hook-docs.html.
		add_action( 'woocommerce_login_form_end', [ __CLASS__, 'prepare_login_events' ] );
		add_action( 'woocommerce_register_form_end', [ __CLASS__, 'prepare_registration_events' ] );
		add_action( 'woocommerce_after_checkout_registration_form', [ __CLASS__, 'prepare_checkout_registration_events' ] );

		// Insert event tracking code.
		add_action( 'wp_footer', [ __CLASS__, 'insert_gtag_amp_analytics' ] );
		add_action( 'wp_footer', [ __CLASS__, 'inject_non_amp_events' ] );
	}

	/**
	 * Get a unique id.
	 *
	 * @return string Unique id.
	 */
	private static function get_uniqid() {
		return 'n' . substr( uniqid(), 10 );
	}

	/**
	 * Get data about all events.
	 *
	 * An event is largely based on the AMP analytics event spec and should contain the following fields:
	 *    'id'             => string Event ID (e.g. "popupFormDisplayed").
	 *    'on'             => string Type of action (e.g. "click" or "form-submit-success").
	 *    'amp_on'         => string Type of action for AMP if different (e.g. "amp-form-submit-success").
	 *    'element'        => string CSS selector for element (e.g. '#popup1').
	 *    'amp_element'    => string CSS selector for AMP version of element if different.
	 *    'event_name'     => string Name for event in GA (e.g. 'Clicked').
	 *    'event_label'    => string Label for event in GA (e.g. 'Popup 1')
	 *    'event_category' => string Category for event in GA (e.g. 'User Interaction').
	 * There can also be other fields specific for certain events (e.g. 'scrollSpec' for 'scroll' event listener).
	 */
	public static function get_events() {
		$events = [];
		if ( Analytics_Wizard::ntg_events_enabled() ) {
			$events = [
				[
					'id'             => self::get_uniqid(),
					'on'             => 'click',
					'element'        => 'a.share-facebook',
					'amp_element'    => 'amp-social-share[type="facebook"]',
					'event_name'     => 'social share',
					'event_label'    => 'facebook',
					'event_category' => 'NTG social',
				],
				[
					'id'             => self::get_uniqid(),
					'on'             => 'click',
					'element'        => 'a.share-twitter',
					'amp_element'    => 'amp-social-share[type="twitter"]',
					'event_name'     => 'social share',
					'event_label'    => 'twitter',
					'event_category' => 'NTG social',
				],
				[
					'id'             => self::get_uniqid(),
					'on'             => 'click',
					'element'        => 'a.share-jetpack-whatsapp',
					'amp_element'    => 'amp-social-share[type="whatsapp"]',
					'event_name'     => 'social share',
					'event_label'    => 'whatsapp',
					'event_category' => 'NTG social',
				],
				[
					'id'             => self::get_uniqid(),
					'on'             => 'click',
					'element'        => 'a.share-linkedin',
					'amp_element'    => 'amp-social-share[type="linkedin"]',
					'event_name'     => 'social share',
					'event_label'    => 'linkedin',
					'event_category' => 'NTG social',
				],
				[
					'id'             => self::get_uniqid(),
					'on'             => 'click',
					'element'        => 'a.share-reddit',
					'amp_element'    => 'amp-social-share[type="reddit"]',
					'event_name'     => 'social share',
					'event_label'    => 'reddit',
					'event_category' => 'NTG social',
				],
				[
					'id'             => self::get_uniqid(),
					'on'             => 'click',
					'element'        => 'a.share-telegram',
					'amp_element'    => 'amp-social-share[type="telegram"]',
					'event_name'     => 'social share',
					'event_label'    => 'telegram',
					'event_category' => 'NTG social',
				],
			];

			if ( ! is_front_page() && ! is_archive() ) {
				$events = array_merge(
					$events,
					[
						[
							'id'              => self::get_uniqid(),
							'on'              => 'scroll',
							'event_name'      => '25%',
							'event_value'     => 25,
							'event_label'     => get_the_title(),
							'event_category'  => 'NTG article milestone',
							'non_interaction' => true,
							'scrollSpec'      => [
								'verticalBoundaries' => [ 25 ],
							],
						],
						[
							'id'              => self::get_uniqid(),
							'on'              => 'scroll',
							'event_name'      => '50%',
							'event_value'     => 50,
							'event_label'     => get_the_title(),
							'event_category'  => 'NTG article milestone',
							'non_interaction' => true,
							'scrollSpec'      => [
								'verticalBoundaries' => [ 50 ],
							],
						],
						[
							'id'              => self::get_uniqid(),
							'on'              => 'scroll',
							'event_name'      => '100%',
							'event_value'     => 100,
							'event_label'     => get_the_title(),
							'event_category'  => 'NTG article milestone',
							'non_interaction' => true,
							'scrollSpec'      => [
								'verticalBoundaries' => [ 100 ],
							],
						],
					]
				);
			}
		}

		$custom_events = array_reduce(
			json_decode( get_option( Analytics_Wizard::$custom_events_option_name, '[]' ) ),
			function ( $all_custom_events, $event ) {
				$event = (array) $event;
				if ( $event['is_active'] ) {
					if ( 'submit' === $event['on'] ) {
						$event['amp_on'] = 'amp-form-submit-success';
					}
					if ( '' === $event['amp_element'] ) {
						unset( $event['amp_element'] );
					}
					$all_custom_events[] = $event;
				}
				return $all_custom_events;
			},
			[]
		);
		$events        = array_merge( $events, $custom_events );

		/**
		 * Other integrations can add events to track using this filter.
		 */
		return apply_filters( 'newspack_analytics_events', $events );
	}

	/**
	 * Alter block markup to support amp-analytics events.
	 *
	 * @param string $content The block content about to be appended.
	 * @param object $block The full block, including name and attributes.
	 */
	public static function prepare_blocks_for_events( $content, $block ) {
		if ( is_admin() ) {
			return $content;
		}

		switch ( $block['blockName'] ) {
			case 'jetpack/mailchimp':
				$content = self::prepare_jetpack_mailchimp_block( $content );
				break;
		}
		return $content;
	}

	/**
	 * Alter Jetpack Mailchimp block to support amp-analytics events.
	 *
	 * @param string $content The block content about to be appended.
	 */
	public static function prepare_jetpack_mailchimp_block( $content ) {
		$block_unique_id = self::get_uniqid();

		// Wrap the block in amp-layout to enable visibility tracking. Sugggested here: https://github.com/ampproject/amphtml/issues/11678.
		$content = sprintf( '<amp-layout id="%s">%s</amp-layout>', $block_unique_id, $content );

		self::$ntg_block_events[] = [
			'id'             => self::get_uniqid(),
			'amp_on'         => 'amp-form-submit-success',
			'on'             => 'submit',
			'element'        => '#' . $block_unique_id . ' form',
			'event_name'     => 'newsletter signup',
			'event_label'    => 'success',
			'event_category' => 'NTG newsletter',
			'visibilitySpec' => [
				'totalTimeMin' => 500,
			],
		];
		self::$ntg_block_events[] = [
			'id'              => self::get_uniqid(),
			'on'              => 'visible',
			'element'         => '#' . $block_unique_id,
			'event_name'      => 'newsletter modal impression ' . Analytics::$block_render_context,
			'event_label'     => get_the_title(),
			'event_category'  => 'NTG newsletter',
			'non_interaction' => true,
			'visibilitySpec'  => [
				'totalTimeMin' => 500,
			],
		];
		return $content;
	}

	/**
	 * Prepare event triggers on user commenting.
	 */
	public static function prepare_comment_events() {
		self::$ntg_block_events[] = [
			'id'             => self::get_uniqid(),
			'amp_on'         => 'amp-form-submit-success',
			'on'             => 'submit',
			'element'        => '#commentform',
			'event_name'     => 'comment added',
			'event_category' => 'NTG user',
			'event_label'    => get_the_title(),
		];
	}

	/**
	 * Add login event triggers for the WooCommerce My Account and Checkout pages.
	 */
	public static function prepare_login_events() {
		self::$ntg_block_events[] = [
			'id'             => self::get_uniqid(),
			'amp-on'         => 'amp-form-submit-success',
			'on'             => 'submit',
			'element'        => '.woocommerce-form-login',
			'event_category' => 'NTG account',
			'event_name'     => 'login',
			'event_label'    => 'success',
		];
	}

	/**
	 * Add registration event triggers for the WooCommerce My Account page.
	 */
	public static function prepare_registration_events() {
		self::$ntg_block_events[] = [
			'id'             => self::get_uniqid(),
			'amp-on'         => 'amp-form-submit-success',
			'on'             => 'submit',
			'element'        => '.woocommerce-form-register',
			'event_category' => 'NTG account',
			'event_name'     => 'registration',
			'event_label'    => 'success',
		];
	}

	/**
	 * Add a registration event trigger for the WooCommerce Checkout page.
	 */
	public static function prepare_checkout_registration_events() {
		self::$ntg_block_events[] = [
			'id'             => self::get_uniqid(),
			'amp-on'         => 'amp-form-submit-success',
			'on'             => 'submit',
			'element'        => '.woocommerce-checkout',
			'event_category' => 'NTG account',
			'event_name'     => 'registration',
			'event_label'    => 'success',
		];
	}

	/**
	 * Insert amp-analytics tag/s into the footer of the page.
	 * The tag/s will contain the custom events to be sent to GA.
	 * This is to avoid the gtag endpoint limitation occuring with a single large config.
	 * More: https://github.com/ampproject/amphtml/issues/32911.
	 */
	public static function insert_gtag_amp_analytics() {
		// If this is a request to verify the tag (from Site Kit), don't insert the amp-analytics tag.
		// Site Kit will only check if the tag is present, not if the send_page_view parameter is true.
		// In this case, the tag won't send page views – but only events. Thus it is not duplicating Site Kit's tag.
		if ( isset( $_GET['tagverify'] ) && $_GET['tagverify'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		$analytics = Google_Services_Connection::get_site_kit_analytics_module();
		if ( ! $analytics || ! $analytics->is_connected() ) {
			return;
		}
		$analytics_settings = $analytics->get_settings()->get();
		if ( ! isset( $analytics_settings['propertyID'] ) ) {
			return;
		}
		$tracking_id = $analytics_settings['propertyID'];

		// Config for amp-analytics.
		$config = [
			'optoutElementId' => '__gaOptOutExtension',
			'vars'            => [
				'gtag_id' => $tracking_id,
				'config'  => [
					$tracking_id => [
						'groups' => 'default',
						'linker' => [
							'domains' => [ wp_parse_url( home_url(), PHP_URL_HOST ) ],
						],
					],
				],
			],
		];

		// The Google Analytics User ID is used to associate multiple user sessions and activities with a unique ID.
		// See https://support.google.com/tagmanager/answer/4565987.
		if ( is_user_logged_in() ) {
			$config['vars']['user_id'] = get_current_user_id();
		}

		// Gather all custom events.
		$all_events = self::get_events();
		if ( Analytics_Wizard::ntg_events_enabled() ) {
			$all_events = array_merge( $all_events, self::$ntg_block_events );
		}
		$custom_events = [];
		foreach ( $all_events as $event ) {
			$event_config = [
				'request' => 'event',
				'on'      => isset( $event['amp_on'] ) ? $event['amp_on'] : $event['on'],
				'vars'    => [
					'event_name'     => $event['event_name'],
					'event_category' => $event['event_category'],
				],
			];
			if ( isset( $event['non_interaction'] ) && true === $event['non_interaction'] ) {
				$event_config['vars']['non_interaction'] = $event['non_interaction'];
			}
			if ( isset( $event['event_value'] ) ) {
				$event_config['vars']['value'] = $event['event_value'];
			}
			if ( isset( $event['event_label'] ) && ! empty( $event['event_label'] ) ) {
				$event_config['vars']['event_label'] = $event['event_label'];
			}

			if ( isset( $event['amp_element'] ) || isset( $event['element'] ) ) {
				$event_config['selector'] = isset( $event['amp_element'] ) ? $event['amp_element'] : $event['element'];
			}

			// Handle other config params e.g. 'scrollSpec'.
			foreach ( $event as $key => $val ) {
				if ( ! in_array( $key, [ 'id', 'on', 'amp_on', 'element', 'amp_element', 'event_name', 'event_label', 'event_category', 'is_active', 'non_interaction' ] ) ) {
					$event_config[ $key ] = $val;
				}
			}

			// Other integrations can use this filter if they need to modify the AMP-specific event config.
			$custom_events[] = [
				'id'     => $event['id'],
				'config' => apply_filters( 'newspack_analytics_amp_event_config', $event_config, $event ),
			];
		}

		if ( 0 === count( $custom_events ) ) {
			// Nothing to do here if no custom events are defined.
			return;
		}

		// Disable pageview reporting in this tag. Pageview is already handled by the tag
		// inserted by Site Kit.
		$tracking_id = $config['vars']['gtag_id'];
		$config['vars']['config'][ $tracking_id ]['send_page_view'] = false;

		// Divide the custom events into batches.
		$custom_events_batches = array_chunk( $custom_events, 10 );

		foreach ( $custom_events_batches as $events_batch ) {
			$config['triggers'] = [];
			foreach ( $events_batch as $event ) {
				$config['triggers'][ $event['id'] ] = $event['config'];
			}
			?>
				<amp-analytics type="gtag">
					<script type="application/json">
						<?php echo wp_json_encode( $config ); ?>
					</script>
				</amp-analytics>
			<?php
		}
	}

	/**
	 * Inject event listeners on non-AMP pages.
	 */
	public static function inject_non_amp_events() {
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return;
		}

		if ( ! Analytics::can_use_site_kits_analytics() ) {
			return;
		}

		// Discard events with duplicate ids.
		$all_events   = array_merge( self::get_events(), self::$ntg_block_events );
		$unique_array = [];
		foreach ( $all_events as $element ) {
			$hash                  = $element['id'];
			$unique_array[ $hash ] = $element;
		}

		foreach ( $unique_array as $event ) {
			ob_start();
			switch ( $event['on'] ) {
				case 'click':
					self::output_js_click_event( $event );
					break;
				case 'scroll':
					self::output_js_scroll_event( $event );
					break;
				case 'submit':
					self::output_js_submit_event( $event );
					break;
				case 'visible':
					self::output_js_visible_event( $event );
					break;
				case 'ini-load':
					self::output_js_ini_load_event( $event );
					break;
				default:
					break;
			}
			if ( isset( self::$js_events[ $event['on'] ] ) ) {
				self::$js_events[ $event['on'] ] += 1;
			}

			// Other integrations can use this filter if they need to add a custom JS event handler.
			$event_js = apply_filters( 'newspack_analytics_event_js', ob_get_clean(), $event );
			echo $event_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Output JS for a click-based event listener.
	 *
	 * @param array $event Event info. See 'get_events'.
	 */
	protected static function output_js_click_event( $event ) {
		if ( 0 === self::$js_events['click'] ) {
			?>
			<script>
				window.newspackHandleClickEvent = (elementSelector, name, spec) => {
					Array.prototype.slice.call( document.querySelectorAll( elementSelector ) ).forEach(element => {
						element.addEventListener( 'click', ( event ) => {
							<?php // Ensure the clicked element still matches the selector. For example an aria attribue might've changed. ?>
							if ( event.currentTarget.matches(elementSelector) ) {
								gtag( 'event', name, spec );
							};
						} );
					})
				};
			</script>
			<?php
		}

		?>
		<script>
			window.newspackHandleClickEvent(
				'<?php echo str_replace( '&quot;', '"', esc_attr( $event['element'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Allow quotes for CSS selectors validity. ?>',
				'<?php echo esc_attr( $event['event_name'] ); ?>',
				{
					event_category: '<?php echo esc_attr( $event['event_category'] ); ?>',
					<?php if ( isset( $event['event_label'] ) ) : ?>
						event_label: '<?php echo esc_attr( $event['event_label'] ); ?>',
					<?php endif; ?>
				}
			);
		</script>
		<?php
	}

	/**
	 * Output JS for a scroll-based event listener.
	 *
	 * @param array $event Event info. See 'get_events'.
	 */
	protected static function output_js_scroll_event( $event ) {
		if ( 0 === self::$js_events['scroll'] ) {
			?>
			<script>
				window.newspackHandleScrollEvent = (scrollPercent, name, spec) => {
					let eventSent = false;
					const reportEvent = () => {
						if ( eventSent ) {
							window.removeEventListener( 'scroll', reportEvent );
							return;
						};
						const scrollPos = ( window.pageYOffset || window.scrollY ) + window.innerHeight;
						if ( ( ( scrollPos / document.body.clientHeight ) * 100 ) >= scrollPercent ) {
							eventSent = true;
							gtag( 'event', name, spec );
						};
					};
					<?php // Fire initially - page might be loaded with scroll offset. ?>
					window.addEventListener( 'DOMContentLoaded', reportEvent );
					window.addEventListener( 'scroll', reportEvent );
				};
			</script>
			<?php
		}

		?>
		<script>
			window.newspackHandleScrollEvent(
				<?php echo (int) $event['scrollSpec']['verticalBoundaries'][0]; ?>,
				'<?php echo esc_attr( $event['event_name'] ); ?>',
				{
					event_category: '<?php echo esc_attr( $event['event_category'] ); ?>',
					<?php if ( isset( $event['event_label'] ) ) : ?>
						event_label: '<?php echo esc_attr( $event['event_label'] ); ?>',
					<?php endif; ?>
					value: <?php echo (int) $event['scrollSpec']['verticalBoundaries'][0]; ?>,
					non_interaction: <?php echo esc_attr( ! empty( $event['non_interaction'] ) && true === $event['non_interaction'] ? 'true' : 'false' ); ?>,
				}
			);
		</script>
		<?php
	}

	/**
	 * Output JS for a form submit-based event listener.
	 *
	 * @param array $event Event info. See 'get_events'.
	 */
	protected static function output_js_submit_event( $event ) {
		?>
		<script>
			( function() {
				Array.prototype.slice.call( document.querySelectorAll( '<?php echo esc_attr( $event['element'] ); ?>' ) ).forEach(element => {
					element.addEventListener( 'submit', (e) => {
						const eventInfo = {
							event_category: '<?php echo esc_attr( $event['event_category'] ); ?>'
						};
						<?php if ( isset( $event['event_label'] ) ) : ?>
							const form = e.currentTarget;
							let eventLabel = '<?php echo isset( $event['event_label'] ) ? esc_attr( $event['event_label'] ) : ''; ?>';
							<?php if ( preg_match( '/(\${formId}|\${formFields\[(.*?)\]})/', $event['event_label'] ) ) : ?>
								eventLabel = eventLabel.replace( '${formId}', form.id );
								eventLabel.match( /\${formFields\[(.*?)\]}/g ).forEach( ( field ) => {
									const fieldName = field.match( /\${formFields\[(.*?)\]}/ )[1];
									if ( form[ fieldName ] ) {
										const fieldValues = [];
										if ( form[ fieldName ].length ) {
											for ( let j = 0; j < form[ fieldName ].length; j++ ) {
												if ( form[ fieldName ][ j ].checked ) {
													fieldValues.push( form[ fieldName ][ j ].value );
												}
											}
										} else {
											fieldValues.push( form[ fieldName ].value );
										};
										eventLabel = eventLabel.replace( field, fieldValues.join( ',' ) );
									}
								} );
							<?php endif; ?>
							eventInfo.event_label = eventLabel;
						<?php endif; ?>

						gtag(
							'event',
							'<?php echo esc_attr( $event['event_name'] ); ?>',
							eventInfo
						);
					} );
				})
			} )();
		</script>
		<?php
	}

	/**
	 * Output JS for a form visibility-based event listener.
	 *
	 * @param array $event Event info. See 'get_events'.
	 */
	protected static function output_js_visible_event( $event ) {
		$delay = ! empty( $event['visibilitySpec'] ) && ! empty( $event['visibilitySpec']['totalTimeMin'] ) ? $event['visibilitySpec']['totalTimeMin'] : 0;

		if ( 0 === self::$js_events['visible'] ) {
			?>
			<script>
				( function() {
					const viewedElements = [];
					const checkVisibility = ( el ) => {
						const elementHeight = el.offsetHeight;
						const elementWidth = el.offsetWidth;
						if ( elementHeight === 0 || elementWidth === 0 ) {
							return false;
						};
						const rect = el.getBoundingClientRect();
						return (
							rect.top >= -elementHeight &&
							rect.left >= -elementWidth &&
							rect.right <= ( window.innerWidth || document.documentElement.clientWidth ) + elementWidth &&
							rect.bottom <= ( window.innerHeight || document.documentElement.clientHeight ) + elementHeight
						);
					};
					window.newspackHandleVisibleEvent = (selector, name, spec, delay) => {
						const elements = Array.prototype.slice.call( document.querySelectorAll( selector ) );
						const reportEvent = () => {
							elements.forEach(element => {
								<?php // Stop tracking scroll after visibility has been reported. ?>
								if ( -1 !== viewedElements.indexOf( element ) ) {
									window.removeEventListener( 'scroll', reportEvent );
									return;
								}
								if ( checkVisibility( element ) && -1 === viewedElements.indexOf( element ) ) {
									viewedElements.push( element );
									const totalTimeMin = window.setTimeout( () => {
										if ( checkVisibility( element ) ) {
											return gtag( 'event', name, spec );
										}
										window.clearTimeout( totalTimeMin );
									}, delay );
								}
							});
						};

						<?php // Fire initially - page might be loaded with scroll offset. ?>
						window.addEventListener( 'DOMContentLoaded', reportEvent );
						window.addEventListener( 'scroll', reportEvent );
					};
				} )();
			</script>
			<?php
		}

		?>
		<script>
			window.newspackHandleVisibleEvent(
				'<?php echo $event['element']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
				'<?php echo esc_attr( $event['event_name'] ); ?>',
				{
					event_category: '<?php echo esc_attr( $event['event_category'] ); ?>',
					<?php if ( isset( $event['event_label'] ) ) : ?>
						event_label: '<?php echo esc_attr( $event['event_label'] ); ?>',
					<?php endif; ?>
					non_interaction: <?php echo esc_attr( ! empty( $event['non_interaction'] ) && true === $event['non_interaction'] ? 'true' : 'false' ); ?>,
				}, <?php echo esc_attr( $delay ); ?>);
		</script>
		<?php
	}

	/**
	 * Output JS for a load event listener.
	 *
	 * @param array $event Event info. See 'get_events'.
	 */
	protected static function output_js_ini_load_event( $event ) {
		if ( 0 === self::$js_events['ini-load'] ) {
			?>
			<script>
				( function() {
					const getObserver = (handleEvent) => new MutationObserver((mutations) => {
						mutations.forEach((mutation) => {
							if (
								mutation.attributeName === 'amp-access-hide' &&
								mutation.type == "attributes" &&
								! mutation.target.hasAttribute('amp-access-hide')
							) {
								handleEvent()
							}
						});
					});
					window.newspackHandleIniLoadEvent = ( selector, name, spec, observe ) => {
						const handleEvent = () => gtag('event', name, spec);
						if (observe) {
							Array.prototype.slice.call( document.querySelectorAll( selector ) ).forEach(( element ) => {
								getObserver(handleEvent).observe(element, { attributes: true });
							});
						}else{
							window.addEventListener('DOMContentLoaded', handleEvent)
						}
					};
				} )();
			</script>
			<?php
		}

		$element_selector = isset( $event['element'] ) ? $event['element'] : '';

		?>
		<script>
			window.newspackHandleIniLoadEvent('<?php echo esc_attr( $element_selector ); ?>', '<?php echo esc_attr( $event['event_name'] ); ?>', {
				event_category: '<?php echo esc_attr( $event['event_category'] ); ?>',
				<?php if ( isset( $event['event_label'] ) ) : ?>
				event_label: '<?php echo esc_attr( $event['event_label'] ); ?>',
				<?php endif; ?>
				non_interaction: <?php echo esc_attr( ! empty( $event['non_interaction'] ) && true === $event['non_interaction'] ? 'true' : 'false' ); ?>,
			}, <?php echo isset( $event['element'] ) ? 'true' : 'false'; ?>);
		</script>
		<?php
	}
}
new Analytics_Events();
