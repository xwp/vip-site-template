<?php
/**
 * Gravity Forms integration class.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
class GravityForms {
	/**
	 * Has custom CSS been enqueued already?
	 *
	 * @var bool
	 */
	private static $has_enqueued_css = false;

	/**
	 * AJAX call result payload.
	 *
	 * @var array
	 */
	private static $ajax_result_payload = [
		'status'       => 'success',
		'confirmation' => '',
		'errors'       => [],
	];

	/**
	 * Classnames used by GF polls plugin are used.
	 *
	 * @var string[]
	 */
	private static $gf_polls_classnames = [
		'gpoll_value_selected',
		'gpoll_button',
		'gpoll_bar',
		'gpoll_bar_juice',
		'gpoll_bar_count',
		'gpoll_wrapper',
		'gpoll_ratio_box',
		'gpoll_ratio_label',
		'gpoll_field_label',
		'gpoll_choice_label',
		'gpoll_container',
		'gpoll_field',
		'gpoll_field_number',
		'gfield',
		'blue',
		'green',
		'red',
		'orange',
	];

	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		add_filter( 'newspack_amp_plus_sanitized', [ __CLASS__, 'filter_scripts_for_amp_plus' ], 10, 2 );

		add_filter( 'the_content', [ __CLASS__, 'ensure_gf_styles_are_not_stripped' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'add_custom_css' ] );

		// The following code makes GravityForms work nicely with AMP.
		// It's not enough for forms which have conditional logic – these need JS. For this,
		// AMP Plus is used to allow GF's JS to be sent to the client.
		//
		// Adapted from: https://gist.github.com/swissspidy/86e3a50ec5c0f7d46ec4de43824e23a0 .
		// By Pascal Birchler (https://pascalbirchler.com).
		add_filter( 'gform_form_args', [ __CLASS__, 'gform_form_args' ] );
		add_filter( 'gform_field_content', [ __CLASS__, 'gform_field_content' ], 10, 2 );
		add_filter( 'gform_submit_button', [ __CLASS__, 'gform_submit_button' ], 10, 2 );
		add_filter( 'gform_footer_init_scripts_filter', [ __CLASS__, 'gform_footer_init_scripts_filter' ] );
		add_filter( 'widget_display_callback', [ __CLASS__, 'widget_display_callback' ], 10, 3 );
		add_filter( 'gform_get_form_filter', [ __CLASS__, 'gform_get_form_filter' ], 10, 2 );
		add_action( 'gform_enqueue_scripts', [ __CLASS__, 'gform_enqueue_scripts' ], 100 );
		add_action( 'gform_form_tag', [ __CLASS__, 'gform_form_tag' ], 100 );
		add_action( 'wp_ajax_gravity_form_submission', [ __CLASS__, 'send_ajax_result' ] );
		add_action( 'wp_ajax_nopriv_gravity_form_submission', [ __CLASS__, 'send_ajax_result' ] );
		add_filter( 'gform_confirmation', [ __CLASS__, 'gform_confirmation' ], 10, 3 );
		add_action( 'gform_post_process', [ __CLASS__, 'gform_post_process' ] );
		add_filter( 'gform_validation', [ __CLASS__, 'gform_validation' ] );
		add_filter( 'gform_suppress_confirmation_redirect', [ __CLASS__, 'gform_suppress_confirmation_redirect' ] );
	}

	/**
	 * Is this an AMP endpoint?
	 */
	public static function is_amp_endpoint() {
		if ( function_exists( 'is_amp_endpoint' ) ) {
			return is_amp_endpoint();
		}
		return false;
	}

	/**
	 * Filter arguments.
	 *
	 * @param array $args Arguments.
	 */
	public static function gform_form_args( array $args ) {
		if ( ! self::is_amp_endpoint() ) {
			return $args;
		}
		$args['ajax'] = false;
		return $args;
	}

	/**
	 * Filters form content for 'vanilla' AMP handling.
	 *
	 * @param string $content Content.
	 * @param object $field Field.
	 */
	public static function gform_field_content( $content, $field ) {
		if ( ! self::should_disable_scripts() ) {
			return $content;
		}

		if ( $field->isRequired ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$content = str_replace( 'aria-required=', 'required=', $content );
		}

		$attr = esc_attr( sprintf( 'change:AMP.setState({gravityForm_%1$s_1: {field_%2$s: event.value}})', $field->formId, $field->id ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$content = preg_replace( '/(onchange=\'([^\']*)\')/', '', $content );
		$content = str_replace( '<input', '<input on="' . $attr . '"', $content );

		return $content;
	}

	/**
	 * Filters form submit button for 'vanilla' AMP handling.
	 *
	 * @param string $content Content.
	 * @param array  $form Form.
	 */
	public static function gform_submit_button( $content, $form ) {
		if ( ! self::should_disable_scripts() ) {
			return $content;
		}

		$content = preg_replace( '/(onclick=\'([^\']*)\')/', '', $content );
		$content = preg_replace( '/(onkeypress=\'([^\']*)\')/', '', $content );

		return $content;
	}

	/**
	 * Filter form's footer script.
	 *
	 * @param string $content Script content.
	 */
	public static function gform_footer_init_scripts_filter( $content ) {
		if ( self::should_disable_scripts() ) {
			return '';
		}
		return $content;
	}

	/**
	 * Filter form widget callback.
	 *
	 * @param array  $instance The widget instance.
	 * @param object $widget Widget.
	 * @param array  $args Arguments.
	 */
	public static function widget_display_callback( $instance, $widget, $args ) {
		if ( ! $widget instanceof GFWidget ) {
			return $instance;
		}

		if ( ! isset( $instance['title'] ) ) {
			$instance['title'] = '';
		}

		if ( self::should_disable_scripts() ) {
			$instance['disable_scripts'] = true;
		}

		return $instance;
	}

	/**
	 * Filter form messages template.
	 *
	 * @param string $content Content.
	 * @param array  $form Form.
	 */
	public static function gform_get_form_filter( $content, $form ) {
		if ( ! self::is_amp_endpoint() ) {
			return $content;
		}

		wp_dequeue_script( 'jquery' );

		$error_message        = apply_filters( 'newspack_gf_message_error', __( 'There is a mistake in the form!', 'newspack' ) );
		$confirmation_message = apply_filters( 'newspack_gf_message_confirmation', __( 'Form successfully submitted.', 'newspack' ) );
		$submitting           = apply_filters( 'newspack_gf_message_submitting', __( 'Submitting…', 'newspack' ) );
		$try_again_later      = apply_filters( 'newspack_gf_message_try_again_later', __( 'Something went wrong. Please try again later.', 'newspack' ) );

		// phpcs:disable WordPressVIPMinimum.Security.Mustache.OutputNotation
		$amp_html = <<<TEMPLATE
<div verify-error>
	<template type="amp-mustache">
		$error_message
		{{#verifyErrors}}{{message}}{{/verifyErrors}}
	</template>
</div>
<div submitting>
	<template type="amp-mustache">
		$submitting
	</template>
</div>
<div submit-success>
<template type="amp-mustache">
	{{#confirmation}}
		{{{confirmation}}}
	{{/confirmation}}
	{{^confirmation}}
		$confirmation_message
	{{/confirmation}}
</template>
</div>
<div submit-error>
	<template type="amp-mustache">
		{{#errors}}
			<p>{{#label}}{{label}}: {{/label}}{{message}}</p>
		{{/errors}}
		{{^errors}}
			<p>$try_again_later</p>
		{{/errors}}
	</template>
</div>
TEMPLATE;
		// phpcs:enable WordPressVIPMinimum.Security.Mustache.OutputNotation

		$content = str_replace( '</form>', $amp_html . '</form>', $content );

		return $content;
	}

	/**
	 * Add CSS for making the form body disappear after a submission.
	 */
	public static function add_custom_css() {
		if ( false === self::$has_enqueued_css ) {
			$inline_css = '
				.gform_body,
				.gform_footer { transition: opacity 200ms; }
				.amp-form-submitting .gform_body,
				.amp-form-submitting .gform_footer { opacity: 0.5; }
				.amp-form-submit-success .gform_body,
				.amp-form-submit-success .gform_footer { display: none !important; }
			';
			wp_add_inline_style( 'gform_basic', $inline_css );
			self::$has_enqueued_css = true;
		}
	}

	/**
	 * Dequeue GF scripts if needed.
	 */
	public static function gform_enqueue_scripts() {
		if ( ! self::should_disable_scripts() ) {
			return;
		}

		wp_dequeue_script( 'gform_gravityforms' );
		wp_dequeue_script( 'gform_placeholder' );
		wp_dequeue_script( 'gform_json' );
		wp_dequeue_script( 'jquery' );
	}

	/**
	 * Filter the form element.
	 *
	 * @param string $content Content.
	 */
	public static function gform_form_tag( $content ) {
		if ( ! self::is_amp_endpoint() ) {
			return $content;
		}

		$ajax_url = add_query_arg( 'action', 'gravity_form_submission', admin_url( 'admin-ajax.php' ) );

		$content = preg_replace( '/(action=\'([^\']*)\')/', '', $content );
		$content = str_replace( '>', 'target="_top">', $content );
		$content = str_replace( '>', 'action-xhr="' . $ajax_url . '">', $content );

		return $content;
	}

	/**
	 * Filter form confirmation.
	 *
	 * @param array $confirmation Confirmation.
	 * @param array $form Form.
	 * @param array $lead Lead.
	 */
	public static function gform_confirmation( $confirmation, $form, $lead ) {
		if ( ! self::is_ajax_form_submission() ) {
			return $confirmation;
		}

		$is_verify_request = isset( $_POST['__amp_form_verify'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $is_verify_request ) {
			\GFFormsModel::delete_entry( $lead['id'] );
			return $confirmation;
		}

		if ( \is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
			header( 'AMP-Redirect-To: ' . $confirmation['redirect'] );
			header( 'AMP-Access-Control-Allow-Source-Origin: ' . home_url() );
			header( 'Access-Control-Expose-Headers: AMP-Redirect-To, AMP-Access-Control-Allow-Source-Origin' );
		}

		return $confirmation;
	}

	/**
	 * Send a result as JSON.
	 */
	public static function send_ajax_result() {
		wp_send_json( self::$ajax_result_payload, 'success' === self::$ajax_result_payload['status'] ? 200 : 400 );
	}

	/**
	 * Process AJAX reponse.
	 *
	 * @param array $form Form.
	 */
	public static function gform_post_process( $form ) {
		if ( ! self::is_ajax_form_submission() ) {
			return;
		}

		$submission_info = \GFFormDisplay::$submission[ $form['id'] ];

		self::$ajax_result_payload['confirmation'] = $submission_info['confirmation_message'];
	}

	/**
	 * Process validation result.
	 *
	 * @param array $validation_result Validation result.
	 */
	public static function gform_validation( $validation_result ) {
		if ( ! self::is_ajax_form_submission() ) {
			return $validation_result;
		}

		if ( ! $validation_result['is_valid'] ) {
			self::$ajax_result_payload['status'] = 'error';
		}

		$is_form_empty = \GFFormDisplay::is_form_empty( $validation_result['form'] );

		foreach ( $validation_result['form']['fields'] as $field ) {
			if ( $is_form_empty ) {
				self::$ajax_result_payload['errors'][] = [
					'label'   => __( 'Error', 'amp-gf' ),
					'message' => $field->validation_message,
				];

				break;
			}

			if ( $field->failed_validation ) {
				self::$ajax_result_payload['errors'][] = [
					'label'   => $field->label,
					'message' => $field->validation_message,
				];
			}
		}

		return $validation_result;
	}

	/**
	 * Suppress confirmation redirect if using AJAX.
	 *
	 * @param bool $suppress Whether to suppress.
	 */
	public static function gform_suppress_confirmation_redirect( $suppress ) {
		return self::is_ajax_form_submission() ? true : $suppress;
	}

	/**
	 * Determines whether we're in the middle of an Ajax form submission.
	 *
	 * @return bool
	 */
	private static function is_ajax_form_submission() {
		return wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'gravity_form_submission' === $_REQUEST['action']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Allow Gravity Forms scripts to be loaded in AMP Plus mode.
	 *
	 * @param bool|null $is_sanitized If null, the error will be handled. If false, rejected.
	 * @param object    $error        The AMP sanitisation error.
	 *
	 * @return bool Whether the error should be rejected.
	 */
	public static function filter_scripts_for_amp_plus( $is_sanitized, $error ) {
		if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
			return $is_sanitized;
		}
		if ( ! self::is_amp_plus_handling_enabled() ) {
			return $is_sanitized;
		}

		if ( AMP_Enhancements::is_script_attribute_matching_strings(
			[
				'gform_',
				'gpoll_', // GravityForms Polls (gravityformspolls) plugin.
				'jquery-core-js',
				'regenerator-runtime-js',
			],
			$error
		) ) {
			$is_sanitized = false;
		}

		// Match sanitized element attributes.
		if (
			AMP_Enhancements::is_error_attribute_matching_string( 'action-xhr', 'gravity_form', $error ) ||
			AMP_Enhancements::is_error_attribute_matching_string( 'class', 'gfield', $error ) ||
			AMP_Enhancements::is_error_attribute_matching_string( 'onchange', 'gform', $error )
		) {
			$is_sanitized = false;
		}

		// Match inline scripts by script text since they don't have IDs.
		if ( AMP_Enhancements::is_script_body_matching_strings( [ 'gform' ], $error ) ) {
			$is_sanitized = false;
		}

		return $is_sanitized;
	}

	/**
	 * Append a hidden div with all the classes used by GF's Polls plugin, so the CSS is not stripped off by AMP.
	 * The stripping happens because these classes are added by JS (not initially in the document).
	 * There are no conditions, since a poll can be placed anywhere on the site (e.g. in a prompt, sidebar, etc).
	 *
	 * @param string $content The post content.
	 */
	public static function ensure_gf_styles_are_not_stripped( $content ) {
		if ( self::is_amp_endpoint() && is_plugin_active( 'gravityforms/gravityforms.php' ) && is_plugin_active( 'gravityformspolls/polls.php' ) ) {
			return $content . '<div style="display:none;" class="' . implode( ' ', self::$gf_polls_classnames ) . '"></div>';
		}
		return $content;
	}

	/**
	 * Whether front-end scripts should be disabled.
	 * If using AMP Plus, the front-end scripts should be loaded. Otherwise,
	 * 'vanilla' AMP should handle the interactions.
	 */
	public static function should_disable_scripts() {
		if ( self::is_amp_plus_handling_enabled() && AMP_Enhancements::should_use_amp_plus() ) {
			return false;
		}
		return self::is_amp_endpoint();
	}

	/**
	 * Whether Gravity Forms should be handled in AMP Plus.
	 *
	 * @return @bool Whether to use this feature.
	 */
	private static function is_amp_plus_handling_enabled() {
		if ( defined( 'NEWSPACK_AMP_PLUS_GRAVITYFORMS' ) ) {
			return true === NEWSPACK_AMP_PLUS_GRAVITYFORMS;
		}
		return false;
	}
}
GravityForms::init();
