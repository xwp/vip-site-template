<?php
/**
 * Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

// The only singleton in this codebase.
$app = new App();

$theme = new Theme( __DIR__ );

// Let the theme be resolved from the container.
$app->bind( Theme::class, $theme );

/**
 * Retrieves an instance of the theme.
 *
 * @return Theme
 */
function theme() {
	return App::resolve( Theme::class );
}

/**
 * Register app components.
 */
$components = [
	new Components\Foundation(),
	new Components\Styles(),
	new Components\Scripts(),
	new Components\Blocks_Settings(),
	new Components\Block_Registry(
		new Path( __DIR__ . '/src/blocks' ),
		new Path( __DIR__ . '/build/blocks' )
	),
	new Components\Feature_Registry(
		new Path( __DIR__ . '/src/features' ),
		new Path( __DIR__ . '/build/features' )
	),
	new Components\Media(),
];

foreach ( $components as $component ) {
	$theme->components()->add( $component );
	$component->init();
}
