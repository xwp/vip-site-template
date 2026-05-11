# Example Theme

Custom WordPress theme built with a modular architecture for blocks, features, and assets.

## Architecture

The theme uses a component-based architecture with automatic registration and asset management:

- **Core**: Foundation classes and utilities
- **Blocks**: Custom Gutenberg blocks with automatic registration
- **Features**: Reusable theme features as self-contained modules
- **Assets**: Global static resources
- **Templates**: Full Site Editing templates and patterns

## Directory Structure

```
example-theme/
├── assets/         # Global static assets
│ ├── fonts/        # Web fonts
│ ├── images/       # Theme images
│ └── icons/        # SVG icons
├── build/          # Compiled assets (auto-generated)
├── parts/          # FSE template parts
├── patterns/       # Block patterns
├── src/            # Source code (development)
│ ├── blocks/       # Custom Gutenberg blocks
│ ├── core/         # Core theme functionality
│ └── features/     # Theme features/modules
├── templates/      # FSE block templates
├── tests/          # Unit and integration tests
├── functions.php   # Theme bootstrap
├── style.css       # Theme declaration
└── theme.json      # FSE configuration
```

## Development

### Creating New Blocks

1. Add block directory under `src/blocks/your-block-name/`
2. Include `block.json` for registration
3. Add PHP, JS, and CSS as needed
4. Block is automatically registered via `Block_Registry`

See [Blocks Documentation](src/blocks/README.md) for details.

### Creating New Features

1. Add feature directory under `src/features/your-feature/`
2. Create PHP class extending `Feature` base class
3. Add JS and CSS assets as needed
4. Feature is automatically loaded via `Feature_Registry`

See [Features Documentation](src/features/README.md) for details.

## Build System

The theme uses webpack with `@wordpress/scripts` for asset compilation:

- **JavaScript**: ES6+ transpiled for browser compatibility
- **Styles**: SCSS compiled to CSS with autoprefixer
- **Assets**: Optimized and versioned for production

## Theme Components

### Core Components

- `Theme`: Main theme orchestrator
- `Block_Registry`: Automatic block discovery and registration
- `Feature_Registry`: Automatic feature loading
- `Asset`: Asset management with versioning
- `Scripts/Styles`: Frontend asset enqueuing

### Utilities

- `Path`: File path management
- `Utils`: Common helper functions
- `Container`: Dependency injection container

## Standards

- WordPress VIP coding standards
- PHPDoc for all PHP classes and methods
- JSDoc for JavaScript modules
- Internationalization ready (`example-theme` text domain)
