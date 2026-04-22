# Contributing

## Quick start

### Prerequisites

- PHP 8.3
- [Composer](https://getcomposer.org)
- [Node.js](https://nodejs.org) 20 (pinned via `.nvmrc`)
- npm 10.2.3+
- [Docker with Docker Compose](https://docs.docker.com/compose/install/)
- [mkcert](https://github.com/FiloSottile/mkcert) (for local SSL)
- [rsync](https://rsync.samba.org) (for deployments)

macOS quick install:

```bash
brew install git php@8.3 composer node@20 mkcert nss
brew install --cask docker
```

### Setup

```bash
# 1) Clone and enter the project
git clone git@github.com:xwp/vip-site-template.git vip-site-template
cd vip-site-template

# 2) Install all dependencies (runs composer install via preinstall hook)
npm install

# 3) Trust the local SSL certificate
npm run install-cert

# 4) Start Docker containers
npm run start

# 5) Install the local WordPress environment
npm run setup
```

### Local URLs

| URL | Purpose |
| - | - |
| [main.local.wpenv.net](https://main.local.wpenv.net) | Main site |
| [network.local.wpenv.net](https://network.local.wpenv.net) | Network admin |
| [mail.local.wpenv.net](https://mail.local.wpenv.net) | Mail catcher (MailHog) |

Default credentials: `devgo` / `devgo`.

## Repository layout

| Path | Contents |
| - | - |
| `themes/example-theme/` | Primary theme (Composer workspace) |
| `plugins/` | Project plugins (manual or Composer) |
| `client-mu-plugins/` | Must-use plugins; `plugin-loader.php` force-enables plugins |
| `vip-config/` | VIP configuration (`vip-config.php`, Composer autoloader) |
| `local/` | Docker setup, scripts, dev data |
| `.github/workflows/` | CI/CD pipelines |

## Development workflow

### Branching and PRs

| Branch | Environment | Auto-deploy |
| - | - | - |
| `develop` | Dev | Yes |
| `main` | Test / Staging | Yes |
| `release` | Pre-prod (UAT) | Yes |
| `production` | Production | Yes |

- Branch from `main` using `feature/<ticket>-short-desc` or `fix/<ticket>-short-desc`.
- Open a PR to `main` for code review.
- Squash and merge after approval.

### Build assets

```bash
# Production build (all workspaces)
npm run build

# Watch mode (all workspaces)
npm run dev
```

Theme-only equivalents run automatically via the workspace setup.

### Linting and formatting

| Tool | Command |
| - | - |
| All linters | `npm run lint` |
| PHPCS (project) | `npm run lint-php` (wraps `composer lint`) |
| PHPCS (theme only) | `composer lint:theme` |
| PHPStan | `composer lint:phpstan` |
| PHPCBF (PHP fix) | `npm run format-php` (wraps `composer format`) |
| ESLint (JS) | `npm run lint-js` |
| Stylelint (CSS) | Theme: `npm run lint:css -w themes/example-theme` |
| Prettier (JS format) | `npm run format-js` |

### Tests

```bash
# All PHP tests (project + migration tools + theme)
npm run test-php

# All JS tests
npm run test -w themes/example-theme

# Run a specific PHP test class
npm run cli -- composer test -- --filter Environment --working-dir=wp-content
```

PHP tests run inside the Docker container via `npm run cli`.

### Full "pre-push" gate (matches CI)

```bash
npm run lint && npm run test
```

This mirrors the `lint` and `test` jobs in `.github/workflows/test-deploy.yml`, which run `npm run lint` and `npm run test` in parallel on every PR.

## Docker commands

| Task | Command |
| - | - |
| Start containers | `npm run start` |
| Start (debug output) | `npm run start-debug` |
| Stop containers | `npm run stop` |
| Stop all Docker containers | `npm run stop-all` |
| Stop and delete DB volume | `npm run stop -- --volumes` |
| Run WP-CLI | `npm run cli -- wp <command>` |
| Run VIP CLI | `npm run vip` |
| View logs | `npm run logs` |

## WordPress engineering standards

This is a **WordPress VIP** project. All code must comply with VIP coding standards.

### Security

- **Sanitize early, escape late:** Use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_*` for all output.
- **Nonces and capabilities:** `wp_create_nonce()` / `wp_verify_nonce()` for forms/AJAX; `current_user_can()` for privileged actions.
- **Database:** `$wpdb->prepare()` for all direct SQL. Prefer `WP_Query` and core APIs.
- **Input validation:** Sanitize all external input (`sanitize_text_field()`, `absint()`, etc.). Assume hostile input.
- **No secrets in code:** Never expose or log API keys, credentials, or tokens.

### Prohibited patterns

- `query_posts()` — use `WP_Query`
- `extract()` in templates
- `mysql_*` functions
- `create_function()`
- Hardcoded filesystem paths — use `plugin_dir_path()`, `get_template_directory()`, etc.
- Anonymous functions in hook callbacks — use named functions or static methods

### VIP-specific

- No arbitrary filesystem writes.
- No disallowed VIP APIs.
- Respect VIP edge cache and the personalization API for auth scenarios.
- Use `switch_to_blog()` / `restore_current_blog()` safely in multisite contexts.

### Architecture

- Prefer hooks (`add_action()` / `add_filter()`) over direct modifications.
- Use `block.json` for block registration.
- Follow existing file patterns, naming conventions, and framework choices.

## Multisite

This is a **multisite network** with 2 sites. The public-facing site is `site_id = 2`. Be mindful of:

- Using `switch_to_blog()` / `restore_current_blog()` safely; avoid cross-site bleed.
- Preferring network options vs site options appropriately.
- Ensuring capability checks respect multisite roles (network vs site admin).

## Third-party plugin patches

Non-Composer plugins may contain manual security or compatibility patches. All patches are registered in `PATCHES.md` at the repo root.

- Patched files are marked with `// XWP-PATCH:` at the file header and inline.
- **Never modify or remove `XWP-PATCH` code** without checking `PATCHES.md` and the linked PR/ticket.
- When updating a patched plugin, re-apply all listed patches and verify whether they are fixed upstream.

## Importing and exporting data

```bash
# Export from VIP
npm run vip -- export sql --output=local/public/wp/vip-export.sql

# Export local DB
npm run cli -- wp db export

# Import into local
npm run cli -- wp db import vip-export.sql

# Import large file with progress
npm run cli -- bash -c "pv import.sql | wp db query"
```

## CI/CD

**Platform:** GitHub Actions

| Workflow | Trigger | Purpose |
| - | - | - |
| `test-deploy.yml` | PR + push to protected branches | Lint and Test (parallel), deploy per environment |
| `create-release-branch.yml` | Manual | Create `release` from `main` for UAT |
| `create-production-pr.yml` | Manual | PR `release` → `production` with checklist |
| `cleanup-release-branch.yml` | PR merged to `production` | Delete `release` branch, finalize GitHub release |
| `docker-images.yml` | PR with `docker-image-build` label | Build and push Docker images |

Tests are **skipped entirely** (no runner allocated) for `release` and `production` deploy paths — code has already been tested upstream.

## Troubleshooting

- **Port conflicts:** Run `npm run stop-all` to stop all Docker containers, or adjust port mappings in `docker-compose.yml`.
- **SSL certificate errors:** Run `npm run install-cert` with Docker stopped. Restart your browser after installing.
- **PHP extension errors:** The Docker container bundles all required extensions. If running Composer on the host, ensure `ext-libxml` and `ext-dom` are installed.
- **Node version mismatch:** Run `nvm install && nvm use` to switch to the version in `.nvmrc`.
- **Stale containers:** Run `npm run stop -- --volumes` to remove the DB volume and `npm run setup` to re-install.
