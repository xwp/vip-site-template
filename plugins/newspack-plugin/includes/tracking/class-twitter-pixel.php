<?php
/**
 * Newspack Magic Links functionality.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * The Twitter Pixel class
 */
class Twitter_Pixel extends Pixel {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'newspack_twitter_pixel' );
	}


	/**
	 * Print the pixels' codes.
	 */
	public function print_code_snippets() {
		add_action( 'wp_head', [ $this, 'print_head_snippet' ], 100 );
		add_action( 'wp_footer', [ $this, 'print_footer_snippet' ] );
	}

	/**
	 * Gets the template for the img tag snippet
	 */
	public function print_footer_snippet() {
		parent::create_noscript_snippet( '<img height="1" width="1" style="display: none;" src="//t.co/i/adsct?txn_id=__PIXEL_ID__&amp;p_id=Twitter">' );
	}

	/**
	 * Prints the template for the script tag snippet
	 */
	public function print_head_snippet() {
		$snippet = "<!-- Twitter conversion tracking base code -->
			<script>
			!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);
			},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',
			a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');
			twq('config','__PIXEL_ID__');
			</script>
			<!-- End Twitter conversion tracking base code -->";
		parent::create_js_snippet( $snippet );
	}

	/**
	 * Validates the pixel id
	 *
	 * @param mixed $value The value to be validated.
	 * @return boolean
	 */
	public function validate_pixel_id( $value ) {
		return '' === $value || is_string( $value );
	}
}

$pixel = new Twitter_Pixel();
$pixel->print_code_snippets();
