<?php
/**
 * Newspack Ads GAM Ad Block Recovery Settings.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers;

use Newspack_Ads\Core;
use Newspack_Ads\Settings;

/**
 * Newspack Ads GAM Ad Block Recovery Class.
 */
final class GAM_Ad_Block_Recovery {

	const SECTION = 'gam_ad_block_recovery';
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'newspack_ads_settings_list', [ __CLASS__, 'register_settings' ] );
		add_action( 'newspack_ads_gtag_before_script', [ __CLASS__, 'render_script' ] );
	}

	/**
	 * Register GAM Ad Block Recovery settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public static function register_settings( $settings_list ) {
		if ( Core::is_amp() ) {
			return $settings_list;
		}
		return array_merge(
			[
				[
					'description' => __( 'Ad Block Recovery', 'newspack-ads' ),
					'help'        => __( 'Implement Ad Block Recovery functionality provided by Google Ad Manager', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => false,
					'public'      => true,
				],
				[
					'description' => __( 'Pub', 'newspack-ads' ),
					'help'        => __( 'Numeric value after "pub-", provided by GAM. E.g.: 1234567891011121', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'pub',
					'type'        => 'string',
					'public'      => true,
				],
				[
					'description' => __( 'Nonce', 'newspack-ads' ),
					'help'        => __( '"Nonce" value provided by GAM. E.g.: Ab3De6GhiJklMnoPqRstUV', 'newspack-ads' ),
					'section'     => self::SECTION,
					'key'         => 'nonce',
					'type'        => 'string',
					'public'      => true,
				],
			],
			$settings_list
		);
	}

	/**
	 * Render Ad Block Recovery script.
	 */
	public static function render_script() {
		if ( Core::is_amp() ) {
			return;
		}
		$settings = Settings::get_settings( self::SECTION, true );
		if ( empty( $settings ) ) {
			return;
		}
		if ( ! $settings['active'] || empty( $settings['pub'] ) || empty( $settings['nonce'] ) ) {
			return;
		}
		?>
		<script data-amp-plus-allowed async src="https://fundingchoicesmessages.google.com/i/pub-<?php echo \esc_attr( $settings['pub'] ); ?>?ers=1" nonce="<?php echo \esc_attr( $settings['nonce'] ); ?>"></script>
		<script data-amp-plus-allowed nonce="<?php echo \esc_attr( $settings['nonce'] ); ?>">
			( function() {
				function signalGooglefcPresent() {
					if ( !window.frames['googlefcPresent'] ) {
						if ( document.body ) {
							const iframe = document.createElement( 'iframe' );
							iframe.style = 'width: 0; height: 0; border: none; z-index: -1000; left: -1000px; top: -1000px;';
							iframe.style.display = 'none';
							iframe.name = 'googlefcPresent';
							document.body.appendChild( iframe );
						} else {
							setTimeout( signalGooglefcPresent, 0 );
						}
					}
				}
				signalGooglefcPresent();
			} )();
		</script>
		<?php
	}
}
GAM_Ad_Block_Recovery::init();
