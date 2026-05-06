# Assets

Global static assets used throughout the theme.

## Structure

assets/
├── fonts/ # Web fonts and font files
├── images/ # Theme images and graphics
└── icons/ # SVG icons and icon sets

## Usage

### Fonts

Custom web fonts loaded globally. Place font files here and reference them in core styles.

### Images

Theme-specific images like logos, backgrounds, and decorative elements. Optimize images before adding.

### Icons

SVG icons for UI elements. Can be referenced directly or included inline for styling flexibility.

## Best Practices

- **Optimize**: Compress images and use appropriate formats
- **SVG Icons**: Prefer SVG for icons (scalable and styleable)
- **Naming**: Use descriptive, kebab-case filenames
- **Organization**: Group related assets in subdirectories
- **Licensing**: Document any third-party asset licenses

## Accessing Assets

Use the theme's `Asset` class for proper URL generation:

```php
$logo = theme()->asset('assets/images/logo.svg');
```

This ensures correct paths and versioning in production.
