<?php
/**
 * Include the object cache from the VIP mu-plugin bundle.
 *
 * Used on local only.
 *
 * @package XWP\Vip_Site_Template
 */

if ( file_exists( __DIR__ . '/mu-plugins/drop-ins/object-cache/object-cache-stable.php' ) ) {
	require_once __DIR__ . '/mu-plugins/drop-ins/object-cache/object-cache-stable.php';
}
