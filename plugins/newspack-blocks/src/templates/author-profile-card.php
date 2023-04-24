<?php
/**
 * Template for a single author profile card.
 *
 * @global array $attributes Block attributes.
 * @package WordPress
 */

call_user_func(
	function( $data ) {
		if ( ! isset( $data['attributes'] ) || ! isset( $data['author'] ) ) {
			return;
		}

		$attributes = $data['attributes'];
		$author     = $data['author'];

		// Whether to render links to the author's archive page.
		$show_archive_link = $attributes['showArchiveLink'] && ! empty( $author['url'] );

		// Combine social links and email, which are shown together.
		$social_links = $attributes['showSocial'] && ! empty( $author['social'] ) ? $author['social'] : [];
		if ( $attributes['showEmail'] && ! empty( $author['email'] ) ) {
			$social_links['email'] = $author['email'];
		}
		if ( $attributes['shownewspack_phone_number'] && ! empty( $author['newspack_phone_number'] ) ) {
			$social_links['newspack_phone_number'] = $author['newspack_phone_number'];
		}

		$employment_values = [];
		if ( $attributes['shownewspack_role'] && ! empty( $author['newspack_role'] ) ) {
			$employment_values[] = $author['newspack_role'];
		}
		if ( $attributes['shownewspack_employer'] && ! empty( $author['newspack_employer'] ) ) {
			$employment_values[] = $author['newspack_employer'];
		}
		$employment = implode( ', ', $employment_values );

		$extra_classes = [
			'text-size-' . $attributes['textSize'],
			'avatar-' . $attributes['avatarAlignment'],
		];

		// Add classes to the block.
		$classes = Newspack_Blocks::block_classes(
			'author-profile',
			$attributes,
			$extra_classes
		);

		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<?php if ( $attributes['showAvatar'] && isset( $author['avatar'] ) ) : ?>
				<div class="wp-block-newspack-blocks-author-profile__avatar">
					<figure style="border-radius: <?php echo esc_attr( $attributes['avatarBorderRadius'] ); ?>; height: <?php echo esc_attr( $attributes['avatarSize'] ); ?>px; width: <?php echo esc_attr( $attributes['avatarSize'] ); ?>px;">
						<?php
						echo wp_kses(
							$author['avatar'],
							[
								'img' => [
									'alt'      => true,
									'class'    => true,
									'data-*'   => true,
									'decoding' => true,
									'height'   => true,
									'loading'  => true,
									'sizes'    => true,
									'src'      => true,
									'srcset'   => true,
									'width'    => true,
								],
							]
						);
						?>
					</figure>
				</div>
			<?php endif; ?>
			<div class="wp-block-newspack-blocks-author-profile__bio">
				<h3>
					<?php if ( $show_archive_link ) : ?>
					<a href="<?php echo esc_url( $author['url'] ); ?>">
					<?php endif; ?>
						<?php echo esc_html( $author['name'] ); ?>
					<?php if ( $show_archive_link ) : ?>
					</a>
					<?php endif; ?>
				</h3>

				<?php if ( $attributes['shownewspack_job_title'] && ! empty( $author['newspack_job_title'] ) ) : ?>
					<p class="wp-block-newspack-blocks-author-profile__job-title">
						<?php echo esc_html( $author['newspack_job_title'] ); ?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $employment ) ) : ?>
					<p class="wp-block-newspack-blocks-author-profile__employment">
						<?php echo esc_html( $employment ); ?>
					</p>
				<?php endif; ?>

				<?php if ( $attributes['showBio'] && ! empty( $author['bio'] ) ) : ?>
					<?php
					$bio = $author['bio'];

					if ( $show_archive_link ) {
						$bio .= sprintf(
							// Translators: "more by this author" link.
							__( ' <a href="%1$s">More by %2$s</a>', 'newspack-blocks' ),
							$author['url'],
							$author['name']
						);
					}

					echo wp_kses_post( wpautop( $bio ) );
					?>
				<?php endif; ?>

				<?php if ( ! empty( $social_links ) ) : ?>
					<ul class="wp-block-newspack-blocks-author-profile__social-links">
						<?php foreach ( $social_links as $service => $social_data ) : ?>
							<li>
								<a href="<?php echo esc_url( $social_data['url'] ); ?>">
									<?php if ( ! empty( $social_data['svg'] ) ) : ?>
										<span>
											<?php echo Newspack_Blocks::sanitize_svg( $social_data['svg'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</span>
									<?php endif; ?>
									<span class="<?php echo ! empty( $social_data['svg'] ) ? esc_attr( 'hidden' ) : esc_attr( 'visible' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
										<?php echo esc_html( $service ); ?>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	},
	$data // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
);
