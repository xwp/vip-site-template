# Template Parts

Reusable block template parts for headers, footers, sidebars, and other common sections.

## Purpose

Template parts are smaller, reusable pieces of templates that can be included in multiple templates. This directory contains both overrides of Twenty Twenty-Five parts and new custom parts.

## How It Works

- **Include in Templates**: Use `<!-- wp:template-part {"slug":"header"} /-->` in templates
- **Automatic Override**: Parts with same name as parent theme override automatically
- **Variations**: Create variations like `header-dark.html` for different contexts

## Current Parts

- `header.html` - Site header with navigation and search
- `footer.html` - Site footer
- `sidebar.html` - Default sidebar for staff posts and archives (displays "Trending Conversations")
- `sidebar-community.html` - Sidebar for community posts (displays "Trending Stories")

## Sidebar Parts

The sidebar template parts are used across multiple page templates. They are hidden on mobile viewports.

**Usage:**
- Staff posts and archives use `sidebar.html`
- Community posts (`dk_community_post`) use `sidebar-community.html`

**Note:** The sidebar content (Curated Content block with 8 posts) is configured in DK-183.

## Creating Parts

1. Add HTML file with block markup
2. Reference in templates using the template-part block
3. Parts are editable in Site Editor

## Note

Template parts support `theme.json` styling and can have their own block settings.
