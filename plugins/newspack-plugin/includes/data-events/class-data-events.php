<?php
/**
 * Newspack Data Events.
 *
 * @package Newspack
 */

namespace Newspack;

use WP_Error;

/**
 * Main class.
 */
final class Data_Events {
	/**
	 * Asynchronous action name.
	 */
	const ACTION = 'newspack_data_event';

	/**
	 * Header to be used while logging.
	 */
	const LOGGER_HEADER = 'NEWSPACK-DATA-EVENTS';

	/**
	 * Registered callable handlers, keyed by their action name.
	 *
	 * @var callable[]
	 */
	private static $actions = [];

	/**
	 * Registered global callable handlers to be executed on all actions.
	 *
	 * @var callable[]
	 */
	private static $global_handlers = [];

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'wp_ajax_' . self::ACTION, [ __CLASS__, 'maybe_handle' ] );
		\add_action( 'wp_ajax_nopriv_' . self::ACTION, [ __CLASS__, 'maybe_handle' ] );
		\add_action( 'newspack_data_events_as_dispatch', [ __CLASS__, 'handle' ], 10, 4 );
	}

	/**
	 * Whether to use Action Scheduler if available.
	 *
	 * @return bool
	 */
	public static function use_action_scheduler() {
		$use_action_scheduler = false;
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$use_action_scheduler = true;
		}
		/**
		 * Filters whether to use the Action Scheduler if available.
		 *
		 * @param bool $use_action_scheduler
		 */
		return \apply_filters( 'newspack_data_events_use_action_scheduler', $use_action_scheduler );
	}

	/**
	 * Maybe handle an event.
	 */
	public static function maybe_handle() {
		// Don't lock up other requests while processing.
		session_write_close(); // phpcs:ignore

		if ( ! isset( $_REQUEST['nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( $_REQUEST['nonce'] ), self::ACTION ) ) {
			\wp_die();
		}

		$action_name = isset( $_POST['action_name'] ) ? \sanitize_text_field( \wp_unslash( $_POST['action_name'] ) ) : null;
		if ( empty( $action_name ) || ! isset( self::$actions[ $action_name ] ) ) {
			\wp_die();
		}

		$timestamp = isset( $_POST['timestamp'] ) ? \sanitize_text_field( \wp_unslash( $_POST['timestamp'] ) ) : null;
		$data      = isset( $_POST['data'] ) ? $_POST['data'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$client_id = isset( $_POST['client_id'] ) ? \sanitize_text_field( \wp_unslash( $_POST['client_id'] ) ) : null;

		self::handle( $action_name, $timestamp, $data, $client_id );

		\wp_die();
	}

	/**
	 * Handle an event.
	 *
	 * @param string $action_name Action name.
	 * @param int    $timestamp   Timestamp.
	 * @param array  $data        Data.
	 * @param string $client_id   Client ID.
	 */
	public static function handle( $action_name, $timestamp, $data, $client_id ) {
		// Execute global handlers.
		Logger::log(
			sprintf( 'Executing global action handlers for "%s".', $action_name ),
			self::LOGGER_HEADER
		);
		foreach ( self::$global_handlers as $handler ) {
			try {
				call_user_func( $handler, $action_name, $timestamp, $data, $client_id );
			} catch ( \Throwable $e ) {
				// Catch fatal errors so it doesn't disrupt other handlers.
				Logger::error( $e->getMessage(), self::LOGGER_HEADER );
			}
		}

		// Execute action handlers.
		Logger::log(
			sprintf( 'Executing action handlers for "%s".', $action_name ),
			self::LOGGER_HEADER
		);
		$handlers = self::get_action_handlers( $action_name );
		foreach ( $handlers as $handler ) {
			try {
				call_user_func( $handler, $timestamp, $data, $client_id );
			} catch ( \Throwable $e ) {
				// Catch fatal errors so it doesn't disrupt other handlers.
				Logger::error( $e->getMessage(), self::LOGGER_HEADER );
			}
		}

		/**
		 * Fires after all global and action-specific handlers have been executed.
		 *
		 * The dynamic portion of the hook name, `$action_name`, refers to the name
		 * of the action being fired.
		 *
		 * @param int    $timestamp   Timestamp.
		 * @param array  $data        Data.
		 * @param string $client_id   Client ID.
		 */
		\do_action( "newspack_data_event_{$action_name}", $timestamp, $data, $client_id );

		/**
		 * Fires after all global and action-specific handlers have been executed.
		 *
		 * @param string $action_name Action name.
		 * @param int    $timestamp   Timestamp.
		 * @param array  $data        Data.
		 * @param string $client_id   Client ID.
		 */
		\do_action( 'newspack_data_event', $action_name, $timestamp, $data, $client_id );
	}

	/**
	 * Register a triggerable action.
	 *
	 * @param string $action_name Action name.
	 *
	 * @return void|WP_Error Error if action already registered.
	 */
	public static function register_action( $action_name ) {
		if ( isset( self::$actions[ $action_name ] ) ) {
			return new WP_Error( 'action_already_registered', __( 'Action already registered.', 'newspack' ) );
		}
		self::$actions[ $action_name ] = [];
	}

	/**
	 * Register a handler for a triggerable action.
	 *
	 * @param callable $handler     Action handler.
	 * @param string   $action_name Action name.
	 *
	 * @return void|WP_Error Error if action not registered, handler already registered or is not callable.
	 */
	public static function register_handler( $handler, $action_name = null ) {
		/** If there's no action name, treat as a global handler. */
		if ( empty( $action_name ) ) {
			self::$global_handlers[] = $handler;
			return;
		}
		if ( ! isset( self::$actions[ $action_name ] ) ) {
			return new WP_Error( 'action_not_registered', __( 'Action not registered.', 'newspack' ) );
		}
		if ( ! is_callable( $handler ) ) {
			return new WP_Error( 'handler_not_callable', __( 'Handler is not callable.', 'newspack' ) );
		}
		if ( in_array( $handler, self::$actions[ $action_name ], true ) ) {
			return new WP_Error( 'handler_already_registered', __( 'Handler already registered.', 'newspack' ) );
		}
		self::$actions[ $action_name ][] = $handler;
	}

	/**
	 * Register a listener so it dispatches an action when a WordPress hook is
	 * fired.
	 *
	 * @param string         $hook_name   WordPress hook name.
	 * @param string         $action_name Action name.
	 * @param callable|array $callable    Optional callable to filter the data
	 *                                    passed to dispatch or an array of
	 *                                    strings to map argument names.
	 */
	public static function register_listener( $hook_name, $action_name, $callable = null ) {
		self::register_action( $action_name );
		\add_action(
			$hook_name,
			function() use ( $action_name, $callable ) {
				$args = func_get_args();
				if ( is_callable( $callable ) ) {
					$data = call_user_func_array( $callable, $args );
				} elseif ( is_array( $callable ) ) {
					$data = [];
					foreach ( $callable as $i => $key ) {
						if ( isset( $args[ $i ] ) ) {
							$data[ $key ] = $args[ $i ];
						}
					}
				} else {
					$data = $args;
				}
				if ( ! empty( $data ) ) {
					self::dispatch( $action_name, $data );
				}
				return $args[0];
			},
			PHP_INT_MAX, // We want dispatches to be executed last so that any modified data is available.
			PHP_INT_MAX // The handler should receive all arguments of a hook.
		);
	}

	/**
	 * Get a list of all registered actions.
	 *
	 * @return string[] Registered actions.
	 */
	public static function get_actions() {
		return array_keys( self::$actions );
	}

	/**
	 * Get a list of all registered action handlers.
	 *
	 * @param string $action_name Action name.
	 *
	 * @return callable[] Registered action handlers.
	 */
	public static function get_action_handlers( $action_name ) {
		if ( ! isset( self::$actions[ $action_name ] ) ) {
			return [];
		}
		return self::$actions[ $action_name ];
	}

	/**
	 * Whether an action is registered.
	 *
	 * @param string $action_name The action name.
	 *
	 * @return bool
	 */
	public static function is_action_registered( $action_name ) {
		return isset( self::$actions[ $action_name ] );
	}

	/**
	 * Dispatch an action event.
	 *
	 * @param string  $action_name   Action name.
	 * @param array   $data          Data to pass to the action.
	 * @param boolean $use_client_id Whether to use the session's client ID. Default is true.
	 *
	 * @return void|WP_Error Error if action not registered.
	 */
	public static function dispatch( $action_name, $data, $use_client_id = true ) {
		if ( ! self::is_action_registered( $action_name ) ) {
			return new WP_Error( 'newspack_data_events_action_not_registered', __( 'Action not registered.', 'newspack' ) );
		}

		$timestamp = time();
		$client_id = null;
		if ( $use_client_id ) {
			$client_id = Reader_Activation::get_client_id();
		}

		/**
		 * Fires when an action is dispatched. This occurs before any handlers are
		 * executed.
		 *
		 * The dynamic portion of the hook name, `$action_name`, refers to the name
		 * of the action being fired.
		 *
		 * @param string $action_name Action name.
		 * @param int    $timestamp   Timestamp.
		 * @param array  $data        Data.
		 * @param string $client_id   Client ID.
		 */
		\do_action( "newspack_data_event_dispatch_{$action_name}", $timestamp, $data, $client_id );

		/**
		 * Fires when an action is dispatched. This occurs before any handlers are
		 * executed.
		 *
		 * @param string $action_name Action name.
		 * @param int    $timestamp   Timestamp.
		 * @param array  $data        Data.
		 * @param string $client_id   Client ID.
		 */
		\do_action( 'newspack_data_event_dispatch', $action_name, $timestamp, $data, $client_id );

		$body = [
			'action_name' => $action_name,
			'timestamp'   => $timestamp,
			'data'        => $data,
			'client_id'   => $client_id,
		];

		/**
		 * Filters the body of the action dispatch request.
		 *
		 * @param array  $body        Body.
		 * @param string $action_name The action name.
		 */
		$body = apply_filters( 'newspack_data_events_dispatch_body', $body, $action_name );

		if ( self::use_action_scheduler() ) {
			Logger::log(
				sprintf( 'Dispatching action "%s" via Action Scheduler.', $action_name ),
				self::LOGGER_HEADER
			);

			\as_enqueue_async_action( 'newspack_data_events_as_dispatch', array_values( $body ), 'newspack-data-events' );

		} else {
			Logger::log(
				sprintf( 'Dispatching action "%s" via HTTP request.', $action_name ),
				self::LOGGER_HEADER
			);

			$url = \add_query_arg(
				[
					'action' => self::ACTION,
					'nonce'  => \wp_create_nonce( self::ACTION ),
				],
				\admin_url( 'admin-ajax.php' )
			);

			return \wp_remote_post(
				$url,
				[
					'timeout'   => 0.01,
					'blocking'  => false,
					'body'      => $body,
					'cookies'   => $_COOKIE, // phpcs:ignore
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				]
			);
		}
	}
}
Data_Events::init();
