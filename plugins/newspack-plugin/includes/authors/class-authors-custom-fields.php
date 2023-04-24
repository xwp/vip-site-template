<?php
/**
 * Newspack Authors Custom Fields functionality.
 *
 * @package Newspack
 */

namespace Newspack;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Authors Custom Fields class.
 */
final class Authors_Custom_Fields {
	const USER_META_NAMES = [
		'job_title'    => 'newspack_job_title',
		'role'         => 'newspack_role',
		'employer'     => 'newspack_employer',
		'phone_number' => 'newspack_phone_number',
	];

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'edit_user_profile', [ __CLASS__, 'edit_user_profile' ] );
		\add_action( 'show_user_profile', [ __CLASS__, 'edit_user_profile' ] );
		\add_action( 'edit_user_profile_update', [ __CLASS__, 'edit_user_profile_update' ] );
		\add_action( 'personal_options_update', [ __CLASS__, 'edit_user_profile_update' ] );
		\add_filter( 'newspack_author_bio_name', [ __CLASS__, 'newspack_author_bio_name' ], 10, 2 );
		\add_filter( 'coauthors_guest_author_fields', [ __CLASS__, 'coauthors_guest_author_fields' ], 10, 2 );
	}

	/**
	 * Save custom fields.
	 *
	 * @param int $user_id User ID.
	 */
	public static function edit_user_profile_update( $user_id ) {
		foreach ( self::get_custom_fields() as $field ) {
			if ( isset( $_POST[ $field['name'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				\update_user_meta( $user_id, $field['name'], sanitize_text_field( $_POST[ $field['name'] ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
	}

	/**
	 * Get custom profile fields list.
	 */
	public static function get_custom_fields() {
		$default_fields = [
			[
				'name'  => self::USER_META_NAMES['job_title'],
				'label' => __( 'Job Title', 'newspack' ),
			],
			[
				'name'  => self::USER_META_NAMES['role'],
				'label' => __( 'Role', 'newspack' ),
			],
			[
				'name'  => self::USER_META_NAMES['employer'],
				'label' => __( 'Employer/Organization', 'newspack' ),
			],
			[
				'name'  => self::USER_META_NAMES['phone_number'],
				'label' => __( 'Phone number', 'newspack' ),
			],
		];
		return apply_filters( 'newspack_authors_custom_fields', $default_fields );
	}

	/**
	 * Add user profile fields.
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public static function edit_user_profile( $user ) {
		?>
		<div class="newspack-author-custom-fields">
			<h2><?php echo esc_html__( 'Additional user profile fields', 'newspack' ); ?></h2>
			<p><?php echo esc_html__( 'These fields will be displayed by Newspack.', 'newspack' ); ?></p>
			<table class="form-table" role="presentation">
				<?php foreach ( self::get_custom_fields() as $field ) : ?>
					<tr class="user-<?php echo esc_attr( $field['name'] ); ?>-wrap">
						<th>
							<label for="<?php echo esc_attr( $field['name'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						</th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( $field['name'] ); ?>"
								id="<?php echo esc_attr( $field['name'] ); ?>"
								value="<?php echo esc_attr( \get_user_meta( $user->ID, $field['name'], true ) ); ?>"
								class="regular-text"
							/>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
		<?php
	}

	/**
	 * Add custom fields to the author footer in the Newspack theme.
	 *
	 * @param string $author_name string The author name.
	 * @param int    $author_id string The author ID.
	 */
	public static function newspack_author_bio_name( $author_name, $author_id ) {
		$job_title = \get_user_meta( $author_id, self::USER_META_NAMES['job_title'], true );

		// Try a Co-Authors Plus field.
		if ( empty( $job_title ) ) {
			$job_title = \get_post_meta( $author_id, 'cap-' . self::USER_META_NAMES['job_title'], true );
		}

		if ( ! empty( $job_title ) ) {
			$author_name .= '<span class="author-job-title">' . $job_title . '</span>';
		}
		return $author_name;
	}

	/**
	 * Add custom fields to the Co-Authors Plus guest author form.
	 *
	 * @param array $fields Fields.
	 * @param array $groups Queried groups.
	 */
	public static function coauthors_guest_author_fields( $fields, $groups ) {
		if ( in_array( 'about', $groups ) || in_array( 'all', $groups ) ) {
			foreach ( self::get_custom_fields() as $custom_field ) {
				$fields[] = [
					'key'   => $custom_field['name'],
					'label' => $custom_field['label'],
					'group' => 'about',
				];
			}
		}
		return $fields;
	}
}
Authors_Custom_Fields::init();
