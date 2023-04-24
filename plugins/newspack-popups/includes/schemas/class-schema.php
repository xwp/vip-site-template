<?php
/**
 * Abstract Schema class
 *
 * @package Newspack
 */

namespace Newspack\Campaigns;

/**
 * Abstract Schema class
 */
abstract class Schema {

	/**
	 * The input value to be handled.
	 *
	 * @var mixed
	 */
	protected $value;

	/**
	 * The validation errors found
	 *
	 * @var array
	 */
	protected $errors = [];

	/**
	 * Constructor.
	 *
	 * @param mixed $value The value to be validated.
	 */
	public function __construct( $value ) {
		$this->value = $value;
	}

	/**
	 * Gets the Schema. This method must be overridden by the extending class.
	 *
	 * @return array The Schema.
	 */
	abstract public static function get_schema();

	/**
	 * Validate the value against the schema.
	 *
	 * @return boolean
	 */
	public function is_valid() {
		$result = rest_validate_value_from_schema( $this->value, $this->get_schema() );
		if ( is_wp_error( $result ) ) {
			$this->errors = $result->get_error_messages();
			return false;
		}
		$this->errors = array();
		return true;
	}

	/**
	 * Get the validation errors.
	 *
	 * @return array
	 */
	public function get_errors() {
		return $this->errors;
	}
}
