# Features

Modular theme features that extend functionality beyond blocks.

## Architecture

Features follow a strict convention:

- Each feature MUST have a main class file: `php/class-{feature-name}-feature.php` that extends `Feature`
- CLI commands go in `php/cli/` and must extend `XWP_CLI_Command` or `WPCOM_VIP_CLI_Command`
- Both are auto-loaded and initialized by the Feature Registry

## Feature Structure

```
feature-name/
├── css/
│   ├── admin.scss                     # Admin styles
│   ├── editor.scss                    # Editor styles
│   └── frontend.scss                  # Frontend styles
├── js/
│   ├── admin.js                       # Admin JavaScript
│   ├── editor.js                      # Editor JavaScript
│   └── frontend.js                    # Frontend JavaScript
└── php/
    └── class-feature-name-feature.php # Main feature class
```

## How It Works

### Feature Registry (`class-feature-registry.php`)

1. **Auto-discovery**: Scans `src/features/` for directories with PHP files
2. **Class Loading**: Loads PHP classes following naming convention
3. **Component Registration**: Adds features to theme component system
4. **Initialization**: Calls feature `init()` method

### Base Feature Class (`class-feature.php`)

Provides:

- Automatic asset enqueueing based on feature name
- Conditional loading via `should_assets_load()` method
- Script localization support
- Consistent initialization pattern

## Creating a New Feature

1. Create directory: `src/features/your-feature/`
2. Create PHP class: `class-your-feature-feature.php`
3. Extend base `Feature` class
4. Implement `feature_init()` method
5. Add JS/CSS assets as needed

## Naming Convention

- Directory: `kebab-case` (e.g., `theme-protection`)
- PHP Main Class: `Pascal_Case_Feature` (e.g., `Theme_Protection_Feature`)
- CLI Classes: `Pascal_Case_CLI_Command` (e.g., `Theme_Cleanup_CLI_Command`)
- Namespace: `XWP\VIP_Site_Template\Theme\Features`

## Asset Loading

Features automatically enqueue:

- `build/features/{name}/admin.css` if exists
- `build/features/{name}/admin.js` if exists
- `build/features/{name}/editor.css` if exists
- `build/features/{name}/editor.js` if exists
- `build/features/{name}/frontend.css` if exists
- `build/features/{name}/frontend.js` if exists

Override these methods for custom behavior:

- `should_assets_load()`: Conditional loading
- `get_style_dependencies()`: CSS dependencies
- `get_script_dependencies()`: JS dependencies
- `get_script_data()`: Localized data
- `get_editor_style_dependencies`: CSS dependencies
- `get_editor_script_dependencies`: JS dependencies
- `get_editor_script_data`: Localized data

## Best Practices

- Keep features independent and reusable
- Use conditional loading for performance
- Follow single responsibility principle
- Document feature purpose and usage
