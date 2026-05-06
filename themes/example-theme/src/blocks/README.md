# Blocks

Custom Gutenberg blocks.

## Architecture

Blocks are automatically discovered and registered by the `Block_Registry` component. Each block is a self-contained module with its own assets and functionality.

## Block Structure

Each block follows this structure:

```
block-name/
├── css/
│   ├── editor.scss    # Editor styles
│   └── frontend.scss  # Frontend styles
├── js/
│   ├── edit.js        # Editor interface
│   ├── editor.js      # Editor-only functionality
│   ├── save.js        # Frontend output (static blocks)
│   └── frontend.js    # Frontend interactivity
├── php/
│   └── render.php     # Server-side rendering (dynamic blocks)
└── block.json         # Block metadata and registration
```

## How It Works

The `Block_Registry` class (`class-block-registry.php`):

1. **Auto-discovery**: Scans `src/blocks/` for directories containing `block.json`
2. **Asset Rewriting**: Maps source assets to built versions in `build/blocks/`
3. **PHP Loading**: Includes `functions.php` if present for custom registration logic
4. **Registration**: Registers blocks with WordPress using `register_block_type()`

## Creating a New Block

1. Create directory: `src/blocks/your-block-name/`
2. Add `block.json` with block metadata
3. Implement PHP rendering or JS save function
4. Add styles and scripts as needed

## Block Categories

Custom blocks use the `example-blocks` category for organization in the block editor.

## Asset Handling

- **Development**: Assets loaded from `src/blocks/`
- **Production**: Compiled assets served from `build/blocks/`
- **Automatic**: No manual registration needed - just follow the structure

## Best Practices

- Use dynamic blocks (PHP rendering) for content requiring server-side logic
- Keep blocks focused on a single purpose
- Follow WordPress block development standards
- Include proper attributes and supports in `block.json`
