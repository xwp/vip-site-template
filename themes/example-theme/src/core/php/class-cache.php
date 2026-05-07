<?php
/**
 * Cache helpers.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme;

/**
 * Object cache helpers.
 */
class Cache {

	/**
	 * Get cached results.
	 *
	 * @param string          $key               Globally unique cache key.
	 * @param callable|string $group_or_callback Cache group or callback for a fresh response.
	 * @param callable|null   $callback          Callback for a fresh response.
	 * @param integer         $expire            Cache response expiry.
	 *
	 * @return mixed
	 */
	public static function with_cache( string $key, callable|string $group_or_callback, ?callable $callback = null, int $expire = 0 ): mixed {
		$group = '';

		if ( is_string( $group_or_callback ) ) {
			$group = $group_or_callback;
		} elseif ( is_callable( $group_or_callback ) ) {
			$callback = $group_or_callback;
		}

		// Skip getting cache value during migration.
		if ( Request_Context::is_importing() ) {
			return call_user_func( $callback );
		}

		$value = wp_cache_get( $key, $group );

		if ( false === $value ) {
			$value = call_user_func( $callback );
			wp_cache_set( $key, $value, $group, $expire ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		}

		return $value;
	}

	/**
	 * Clear cache item if supported.
	 *
	 * @param string $key   Globally unique cache key.
	 * @param string $group Cache group. Defaults to empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_cache_item( string $key, string $group = '' ): bool {
		// Skip clearing cache item during migration.
		if ( Request_Context::is_importing() ) {
			return false;
		}

		return wp_cache_delete( $key, $group );
	}

	/**
	 * Flush cache group if supported.
	 *
	 * @param string $group Group name.
	 */
	public static function flush_cache_group( string $group ): void {
		// Skip flushing cache group during migration.
		if ( Request_Context::is_importing() ) {
			return;
		}

		if ( wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( $group );
		}
	}
}
