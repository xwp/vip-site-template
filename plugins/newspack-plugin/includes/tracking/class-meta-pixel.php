<?php
/**
 * Newspack Meta.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * The Meta Pixel class
 */
class Meta_Pixel extends Pixel {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'newspack_meta_pixel' );
	}

	/**
	 * Print the pixels' codes.
	 */
	public function print_code_snippets() {
		add_action( 'wp_head', [ $this, 'print_head_snippet' ], 100 );
		add_action( 'wp_footer', [ $this, 'print_footer_snippet' ] );
	}

	/**
	 * Gets the event parameters based on the current request
	 *
	 * @return array
	 */
	private static function get_event_params() {
		global $wp;
		$current_user = wp_get_current_user();
		$event_params = [
			'page_title' => get_the_title(),
			'user_role'  => empty( $current_user->roles ) ? 'guest' : $current_user->roles[0],
			'event_url'  => home_url( $wp->request ),
		];

		if ( is_singular() ) {
			$event_params['post_type'] = get_post_type();
			$event_params['post_id']   = get_the_ID();
		}

		/**
		 * Filters the event parameters that will be sent added to the Meta Pixel code
		 *
		 * @param array $event_params The event parameters based on the current request.
		 */
		return apply_filters( 'newspack_meta_pixel_event_params', $event_params );
	}

	/**
	 * Prints the template for the img tag snippet
	 */
	public function print_footer_snippet() {
		$event_params = self::get_event_params();
		$url_params   = http_build_query( [ 'cd' => $event_params ] );
		$snippet      = '<img height="1" width="1" style="display: none;" src="https://www.facebook.com/tr?id=__PIXEL_ID__&ev=PageView&noscript=1&' . $url_params . '">';
		$this->create_noscript_snippet( $snippet );
	}

	/**
	 * Prints the template for the script tag snippet
	 */
	public function print_head_snippet() {
		$snippet = sprintf(
			"<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];
		s.parentNode.insertBefore(t,s)}(window, document,'script',
		'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '__PIXEL_ID__');
		fbq('track', 'PageView', %s);
		</script>",
			wp_json_encode( self::get_event_params() )
		);
		$this->create_js_snippet( $snippet );
	}

	/**
	 * Validates the pixel_id argument
	 *
	 * @param mixed $value The value to be validated.
	 * @return boolean
	 */
	public function validate_pixel_id( $value ) {
		return '' === $value || ctype_digit( $value ) || is_int( $value );
	}
}

$pixel = new Meta_Pixel();
$pixel->print_code_snippets();
