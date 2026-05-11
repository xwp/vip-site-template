# Block Patterns

Predefined block layouts and design patterns for content creators.

## Purpose

Block patterns are pre-configured block layouts that users can insert into their content. They provide consistent design patterns and accelerate content creation.

## How It Works

- **Auto-registration**: PHP files with proper headers are automatically registered
- **Pattern Inserter**: Available in the block editor pattern library
- **Reusable**: Same pattern can be used across multiple pages/posts

## Pattern File Structure

Each pattern file must include:

```php
<?php
/**
 * Title: Pattern Name
 * Slug: example/pattern-slug
 * Categories: example-patterns
 * Keywords: relevant, search, terms
 * Viewport Width: 1280
 * Block Types: core/post-content
 * Post Types: post, page
 * Inserter: true
 */
?>
<!-- Block markup here -->
```

## Categories

- `example-patterns` - All custom patterns
- `featured` - Highlighted patterns
- `headers` - Header sections
- `footers` - Footer sections
- `gallery` - Gallery layouts
- `call-to-action` - CTA sections

## Best Practices

- Use translatable strings with `esc_html_e()` and text domain
- Include placeholder images from `assets/images/`
- Test patterns at different screen sizes
- Keep patterns focused on single use cases
- Document any required plugins or blocks
