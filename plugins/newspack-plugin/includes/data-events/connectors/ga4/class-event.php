<?php
/**
 * Newspack GA4 Event
 *
 * @package Newspack
 */

namespace Newspack\Data_Events\Connectors\GA4;

defined( 'ABSPATH' ) || exit;

/**
 * Main Class.
 */
class Event {

	/**
	 * The maximum number of parameters an event can have, according to the documentation.
	 *
	 * @var int
	 */
	const MAX_PARAMS = 25;

	/**
	 * Event name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Event parameters.
	 *
	 * @var array
	 */
	private $params;

	/**
	 * Constructor.
	 *
	 * @throws \Exception If event name or params are invalid.
	 * @param string $name Event name.
	 * @param array  $params Event parameters.
	 */
	public function __construct( $name, $params = [] ) {
		$this->set_name( $name );
		$this->set_params( $params );
	}

	/**
	 * Validates the event parameters names and values
	 *
	 * @param array $params An array of event parameters.
	 * @return bool
	 */
	public static function validate_params( $params ) {
		if ( count( $params ) > self::MAX_PARAMS ) {
			return false;
		}

		foreach ( $params as $name => $value ) {
			if ( ! self::validate_name( $name ) ) {
				return false;
			}
			if ( ! self::validate_param_value( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether a event or parameter name is valid
	 *
	 * Event and parameter names must be 40 characters or fewer, may only contain alpha-numeric characters and underscores, and must start with an alphabetic character.
	 *
	 * @param string $name The event or parameter name.
	 * @return bool
	 */
	public static function validate_name( $name ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-zA-Z]{1}[a-zA-Z0-9_]{1,39}$/', $name ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks whether a event or parameter name is valid
	 *
	 * Parameter values must be 100 characters or fewer
	 *
	 * @param string $value The parameter value.
	 * @return bool
	 */
	public static function validate_param_value( $value ) {
		$value = self::sanitize_value( $value );
		return is_string( $value ) && strlen( $value ) <= 100;
	}

	/**
	 * Tries to santize a value to a string in cases where it's possible. It will not act on object or arrays, which will still produce a validation error
	 *
	 * @param mixed $value The input value.
	 * @return mixed The sanitized value, or the original value if it can't be sanitized
	 */
	public static function sanitize_value( $value ) {
		switch ( gettype( $value ) ) {
			case 'integer':
			case 'double':
			case 'NULL':
			case 'string':
				return substr( (string) $value, 0, 100 );
			case 'boolean':
				return $value ? 'yes' : 'no';
			default:
				return $value;
		}
	}

	/**
	 * Get event name.
	 *
	 * @return string Event name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get event parameters.
	 *
	 * @return array Event parameters.
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Sets the event parameters if they are valid. Throws exception if not.
	 *
	 * @param array $params The event parameters.
	 * @throws \Exception If the event parameters are invalid.
	 * @return void
	 */
	public function set_params( $params ) {
		if ( ! $this->validate_params( $params ) ) {
			throw new \Exception( 'Invalid event parameters. Limit is 25 parameters, included the default parameters. Values must have a max of 100 characters.' );
		}
		$this->params = $params;
		foreach ( $this->params as $param_name => $param_value ) {
			$this->params[ $param_name ] = self::sanitize_value( $param_value );
		}
	}

	/**
	 * Sets the event name if it is valid. Throws exception if not.
	 *
	 * @param string $name The event name.
	 * @throws \Exception If the event name is invalid.
	 * @return void
	 */
	public function set_name( $name ) {
		if ( ! $this->validate_name( $name ) ) {
			throw new \Exception( 'Event and parameter names must be 40 characters or fewer, may only contain alpha-numeric characters and underscores, and must start with an alphabetic character.' );
		}
		$this->name = $name;
	}

	/**
	 * Get event as an array.
	 *
	 * @return array Event as an array.
	 */
	public function to_array() {
		return [
			'name'   => $this->get_name(),
			'params' => $this->get_params(),
		];
	}
}
