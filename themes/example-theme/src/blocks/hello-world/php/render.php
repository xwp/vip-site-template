<?php
/**
 * Hello World block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

?>
<p <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php esc_html_e( 'Hello World', 'example-theme' ); ?>
</p>
