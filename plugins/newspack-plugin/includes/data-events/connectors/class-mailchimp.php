<?php
/**
 * Newspack Data Events Mailchimp Connector
 *
 * @package Newspack
 */

namespace Newspack\Data_Events\Connectors;

use \Newspack\Data_Events;
use \Newspack\Mailchimp_API;
use \Newspack\Newspack_Newsletters;
use \Newspack\Reader_Activation;
use \Newspack\WooCommerce_Connection;
use \Newspack\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * Main Class.
 */
class Mailchimp {
	/**
	 * Constructor.
	 */
	public function __construct() {
		if (
			defined( 'NEWSPACK_DATA_EVENTS_MAILCHIMP' ) && NEWSPACK_DATA_EVENTS_MAILCHIMP &&
			Reader_Activation::is_enabled() &&
			true === Reader_Activation::get_setting( 'sync_esp' )
		) {
			Data_Events::register_handler( [ __CLASS__, 'reader_registered' ], 'reader_registered' );
			Data_Events::register_handler( [ __CLASS__, 'donation_new' ], 'donation_new' );
			Data_Events::register_handler( [ __CLASS__, 'donation_subscription_new' ], 'donation_subscription_new' );
		}
	}

	/**
	 * Get audience ID.
	 *
	 * @return string|bool Audience ID or false if not set.
	 */
	private static function get_audience_id() {
		/** TODO: UI for handling Mailchimp's master list in RAS. */
		$audience_id = Reader_Activation::get_setting( 'mailchimp_audience_id' );
		/** Attempt to use list ID from "Mailchimp for WooCommerce" */
		if ( ! $audience_id && function_exists( 'mailchimp_get_list_id' ) ) {
			$audience_id = mailchimp_get_list_id();
		}
		return ! empty( $audience_id ) ? $audience_id : false;
	}

	/**
	 * Get merge field type.
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return string Merge field type.
	 */
	private static function get_merge_field_type( $value ) {
		if ( is_numeric( $value ) ) {
			return 'number';
		}
		if ( is_bool( $value ) ) {
			return 'boolean';
		}
		return 'text';
	}

	/**
	 * Get merge fields given data.
	 *
	 * @param string $audience_id Audience ID.
	 * @param array  $data        Data to check.
	 *
	 * @return array Merge fields.
	 */
	private static function get_merge_fields( $audience_id, $data ) {
		$merge_fields       = [];
		$fields_option_name = sprintf( 'newspack_data_mailchimp_%s_fields', $audience_id );

		// Strip arrays.
		$data = array_filter(
			$data,
			function( $value ) {
				return ! is_array( $value );
			}
		);

		// Get and match existing merge fields.
		$fields_ids      = \get_option( $fields_option_name, [] );
		$existing_fields = Mailchimp_API::get( "lists/$audience_id/merge-fields?count=1000" );
		foreach ( $existing_fields['merge_fields'] as $field ) {
			$field_name = '';
			if ( isset( $fields_ids[ $field['merge_id'] ] ) ) {
				// Match locally stored merge field ID.
				$field_name = $fields_ids[ $field['merge_id'] ];
			} elseif ( isset( $data[ $field['name'] ] ) ) {
				// Match by merge field name.
				$field_name                       = $field['name'];
				$fields_ids[ $field['merge_id'] ] = $field_name;
			}
			// If field name is found, add it to the payload.
			if ( ! empty( $field_name ) && isset( $data[ $field_name ] ) ) {
				$merge_fields[ $field['tag'] ] = $data[ $field_name ];
				unset( $data[ $field_name ] );
			}
		}

		// Create remaining fields.
		$remaining_fields = array_keys( $data );
		foreach ( $remaining_fields as $field_name ) {
			$created_field                            = Mailchimp_API::post(
				"lists/$audience_id/merge-fields",
				[
					'name' => $field_name,
					'type' => self::get_merge_field_type( $data[ $field_name ] ),
				]
			);
			$merge_fields[ $created_field['tag'] ]    = $data[ $field_name ];
			$fields_ids[ $created_field['merge_id'] ] = $field_name;
		}

		// Store fields IDs for future use.
		\update_option( $fields_option_name, $fields_ids );
		return $merge_fields;
	}

	/**
	 * Update a Mailchimp contact
	 *
	 * @param string $email Email address.
	 * @param array  $data  Data to update.
	 */
	private static function put( $email, $data = [] ) {
		$audience_id = self::get_audience_id();
		if ( ! $audience_id ) {
			return;
		}
		$hash    = md5( strtolower( $email ) );
		$payload = [
			'email_address' => $email,
			'status_if_new' => 'transactional',
		];

		$merge_fields = self::get_merge_fields( $audience_id, $data );
		if ( ! empty( $merge_fields ) ) {
			$payload['merge_fields'] = $merge_fields;
		}

		// Upsert the contact.
		Mailchimp_API::put(
			"lists/$audience_id/members/$hash",
			$payload
		);
	}

	/**
	 * Handle a reader registering.
	 *
	 * @param int   $timestamp Timestamp of the event.
	 * @param array $data      Data associated with the event.
	 * @param int   $client_id ID of the client that triggered the event.
	 */
	public static function reader_registered( $timestamp, $data, $client_id ) {
		$prefix      = Newspack_Newsletters::get_metadata_prefix();
		$account_key = Newspack_Newsletters::get_metadata_key( 'account' );
		$metadata    = [
			$account_key => $data['user_id'],
		];
		if ( isset( $data['metadata']['current_page_url'] ) ) {
			$metadata[ $prefix . 'Registration Page' ] = $data['metadata']['current_page_url'];
		}
		if ( isset( $data['metadata']['registration_method'] ) ) {
			$metadata[ $prefix . 'Registration Method' ] = $data['metadata']['registration_method'];
		}
		self::put( $data['email'], $metadata );
	}

	/**
	 * Handle a donation being made.
	 *
	 * @param int   $timestamp Timestamp of the event.
	 * @param array $data      Data associated with the event.
	 * @param int   $client_id ID of the client that triggered the event.
	 */
	public static function donation_new( $timestamp, $data, $client_id ) {
		if ( ! isset( $data['platform_data']['order_id'] ) ) {
			return;
		}

		$order_id = $data['platform_data']['order_id'];
		$contact  = WooCommerce_Connection::get_contact_from_order( $order_id );

		if ( ! $contact ) {
			return;
		}

		$email         = $contact['email'];
		$metadata      = $contact['metadata'];
		$keys          = Newspack_Newsletters::$metadata_keys;
		$prefixed_keys = array_map(
			function( $key ) {
				return Newspack_Newsletters::get_metadata_key( $key );
			},
			array_values( array_flip( $keys ) )
		);

		// Only use metadata defined in 'Newspack_Newsletters'.
		$metadata = array_intersect_key( $metadata, array_flip( $prefixed_keys ) );

		// Remove "product name" from metadata, we'll use
		// 'donation_subscription_new' action for this data.
		unset( $metadata[ Newspack_Newsletters::get_metadata_key( 'product_name' ) ] );

		self::put( $email, $metadata );
	}

	/**
	 * Handle a new subscription.
	 *
	 * @param int   $timestamp Timestamp of the event.
	 * @param array $data      Data associated with the event.
	 * @param int   $client_id ID of the client that triggered the event.
	 */
	public static function donation_subscription_new( $timestamp, $data, $client_id ) {
		if ( empty( $data['platform_data']['order_id'] ) ) {
			return;
		}
		$account_key  = Newspack_Newsletters::get_metadata_key( 'account' );
		$metadata     = [
			$account_key => $data['user_id'],
		];
		$order_id     = $data['platform_data']['order_id'];
		$product_id   = Donations::get_order_donation_product_id( $order_id );
		$product_name = get_the_title( $product_id );

		$key              = Newspack_Newsletters::get_metadata_key( 'product_name' );
		$metadata[ $key ] = $product_name;

		self::put( $data['email'], $metadata );
	}
}
new Mailchimp();
