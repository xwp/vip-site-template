# Core

Foundation classes and utilities that power the theme architecture.

## Overview

The core module provides the theme's infrastructure, including component management, asset handling, and utility functions. All blocks and features build upon this foundation.

## Key Components

### Theme Orchestration

- **`class-theme.php`**: Main theme class that initializes all components and manages the theme lifecycle
- **`class-foundation.php`**: Base initialization and WordPress integration
- **`class-app.php`**: Application container and service management

### Registry Systems

- **`class-block-registry.php`**: Automatic block discovery and registration from `src/blocks/`
- **`class-feature-registry.php`**: Automatic feature module loading from `src/features/`
- **`class-feature.php`**: Base class for all feature modules with asset management

### Asset Management

- **`class-asset.php`**: Asset URL generation, versioning, and path resolution
- **`class-scripts.php`**: JavaScript enqueueing and dependency management
- **`class-styles.php`**: Stylesheet enqueueing and dependency management

### Traits

...

### Utilities

- **`class-utils.php`**: Common helper functions for theme operations
- **`class-path.php`**: File path management and resolution
- **`class-container.php`**: Dependency injection container
- **`class-components.php`**: Component registration and lifecycle management

### Content Management

- **`class-media.php`**: Media handling and image optimization

## Core Assets

### JavaScript

- **`frontend.js`**: Global frontend functionality and utilities
- **`editor.js`**: Block editor enhancements and customizations

### Styles

- **`frontend.scss`**: Base theme styles and CSS custom properties
- **`editor.scss`**: Block editor styling for consistent editing experience

## Architecture Patterns

### Component System

All major theme parts implement the `Component` interface:

- Consistent initialization via `init()` method
- Managed lifecycle through the Components registry
- Dependency injection support

### Service Container

The theme uses a service container pattern for:

- Singleton management
- Dependency resolution
- Service lazy-loading

### Path Management

The `Path` class provides:

- Relative and absolute path resolution
- URL generation for assets
- File existence checking

## Initialization Flow

1. **Bootstrap**: `functions.php` loads the theme
2. **Foundation**: Core services initialized
3. **Registries**: Block and Feature registries scan directories
4. **Components**: All components receive `init()` call
5. **Assets**: Scripts and styles enqueued based on context

## Usage

Core components are accessed through the theme helper:

```php
theme()->asset('path/to/asset.js');
theme()->components()->add($component);
theme()->path()->to('src/blocks');
```

## Best Practices

- Extend base classes rather than reimplementing functionality
- Use the component system for new features
- Leverage the asset management for proper versioning
- Follow the established naming conventions
- Keep core modifications minimal - extend via features instead
