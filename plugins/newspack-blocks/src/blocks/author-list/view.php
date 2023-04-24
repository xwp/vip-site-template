<?php
/**
 * Server-side render functions for the Author List block.
 *
 * @package WordPress
 */

/**
 * Dynamic block registration.
 */
function newspack_blocks_register_author_list() {
	$block_json = json_decode(
		file_get_contents( __DIR__ . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		true
	);

	if ( class_exists( '\Newspack\Authors_Custom_Fields' ) ) {
		$author_custom_fields = \Newspack\Authors_Custom_Fields::get_custom_fields();
		foreach ( $author_custom_fields as $field ) {
			$block_json['attributes'][ 'show' . $field['name'] ] = [
				'type'    => 'boolean',
				'default' => true,
			];
		}
	}

	register_block_type(
		'newspack-blocks/' . $block_json['name'],
		[
			'attributes'      => $block_json['attributes'],
			'render_callback' => 'newspack_blocks_render_block_author_list',
		]
	);
}

/**
 * Block render callback.
 *
 * @param array $attributes Block attributes.
 */
function newspack_blocks_render_block_author_list( $attributes ) {
	if ( ! class_exists( 'WP_REST_Newspack_Author_List_Controller' ) ) {
		return;
	}

	// Gather attributes.
	$exclude_empty       = $attributes['excludeEmpty'];
	$author_roles        = $attributes['authorRoles'];
	$author_type         = $attributes['authorType'];
	$is_columns          = 'columns' === $attributes['layout'];
	$number_of_columns   = $attributes['columns'];
	$show_separators     = $attributes['showSeparators'];
	$separator_sections  = $attributes['separatorSections'];
	$avatar_hide_default = $attributes['avatarHideDefault'];
	$params              = [
		'author_type'  => $author_type,
		'author_roles' => $author_roles,
		'exclude'      => $attributes['exclude'], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
		'fields'       => [ 'id', 'name', 'bio', 'email', 'social', 'avatar', 'url' ],
	];

	if ( $exclude_empty ) {
		$params['exclude_empty'] = true;
	}

	if ( $avatar_hide_default ) {
		$params['avatar_hide_default'] = true;
	}

	$author_list_controller = new WP_REST_Newspack_Author_List_Controller();
	$authors                = $author_list_controller->get_all_authors( $params );

	$anchor_id = uniqid();

	if ( empty( $authors ) ) {
		return;
	}

	// Enqueue required front-end assets.
	Newspack_Blocks::enqueue_view_assets( 'author-list' );
	Newspack_Blocks::enqueue_view_assets( 'author-profile' );

	// Class names for the list container.
	$container_classes = [ 'newspack-blocks__author-list-container' ];
	if ( $is_columns ) {
		$container_classes[] = 'is-columns';
		$container_classes[] = 'columns-' . (string) $number_of_columns;
	}

	ob_start();
	?>
	<div class="wp-block-newspack-blocks-author-list <?php echo esc_attr( $attributes['className'] ); ?>">

	<?php
	// Columns WITH alphabetical separator sections.
	if ( $is_columns && $show_separators && $separator_sections ) :
		// Associative array to store authors chunked by alphabet, if separatorSections is true.
		$chunked_authors = [];
		foreach ( $authors as $author ) {
			$first_letter = strtoupper( substr( $author['last_name'], 0, 1 ) );
			if ( ! isset( $chunked_authors[ $first_letter ] ) ) {
				$chunked_authors[ $first_letter ] = [];
			}
			$chunked_authors[ $first_letter ][] = $author;
		}

		foreach ( $chunked_authors as $separator_letter => $separator_authors ) :
			?>
			<h2
				class="newspack-blocks__author-list-separator"
				id="newspack-blocks__author-list-separator__<?php echo esc_attr( $anchor_id ); ?>__<?php echo esc_attr( $separator_letter ); ?>"
			>
				<?php echo esc_html( $separator_letter ); ?>
			</h2>
			<ul class="<?php echo esc_attr( implode( ' ', $container_classes ) ); ?>">
				<?php
				foreach ( $separator_authors as $author ) :
					$author_card = Newspack_Blocks::template_include(
						'author-profile-card',
						[
							'attributes' => $attributes,
							'author'     => $author,
						]
					);
					?>
					<li class="newspack-blocks__author-list-item">
						<?php echo $author_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
		<?php
		// List view or columns without alphabetical separator sections.
	else :
		$separators = [];
		?>

		<ul class="<?php echo esc_attr( implode( ' ', $container_classes ) ); ?>">
			<?php
			foreach ( $authors as $author ) :
				$first_letter   = strtoupper( substr( $author['last_name'], 0, 1 ) );
				$show_separator = ! in_array( $first_letter, $separators, true );

				if ( $show_separator ) {
					$separators[] = $first_letter;
				}

				$author_card = Newspack_Blocks::template_include(
					'author-profile-card',
					[
						'attributes' => $attributes,
						'author'     => $author,
					]
				);

				if ( $show_separators && $show_separator ) :
					?>
					<li class="newspack-blocks__author-list-item">
						<h2
							class="newspack-blocks__author-list-separator"
							id="newspack-blocks__author-list-separator__<?php echo esc_attr( $anchor_id ); ?>__<?php echo esc_attr( $first_letter ); ?>"
						>
							<?php echo esc_html( $first_letter ); ?>
						</h2>
					</li>
				<?php endif; ?>
				<li class="newspack-blocks__author-list-item">
					<?php echo $author_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

add_action( 'init', 'newspack_blocks_register_author_list' );
