# AI Content Agent – Developer Docs (v1.0.0)

## Install & Build

```bash
# Dev setup
npm install --prefix plugin
npm --prefix plugin run build:wp

# Translations
npm --prefix plugin run make-pot

# Assets (wp.org placeholders)
npm --prefix plugin run make-assets
# Output: /workspace/assets/placeholders/*.png
```

## Lint & Formatting
```bash
# PHP (WordPress Coding Standards)
composer run lint:php

# TypeScript/React
npm run lint:ts

# Prettier (root lint-staged de entegre)
npx --no prettier --write plugin
```

## Versioning
```bash
# Bump version in plugin header + plugin/package.json
npm run bump -- 1.0.1
```

## Architecture
- Frontend: React + TypeScript (Vite). Built bundle copied to `admin/assets/` and `admin/js/index.js`.
- Backend: PHP (WordPress APIs), REST endpoints in `includes/`.
- Automation: Unified WP‑Cron engine (`ACA_Simple_Automation`).
- i18n: `languages/ai-content-agent.pot`; textdomain `ai-content-agent`.

## Release
- Minimal zip: `node scripts/release.cjs 1.0.0 "Initial stable"` → `release/ai-content-agent-1.0.0-min.zip`
- WordPress.org staging: `./scripts/deploy-wporg.sh /path/to/svn 1.0.0`
  - Copies `/workspace/assets/final/*` if present; otherwise `/workspace/assets/placeholders/*`.
- `.distignore` controls files excluded from distribution.

## Troubleshooting
- REST API: ensure `/wp-json/` accessible.
- Nonce: refresh admin if 403 due to expired nonce.
- WP‑Cron: check Tools → Site Health → Scheduled Events or `wp cron event list | grep aca_simple`.
- Logs: enable in `wp-config.php`
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Notes
- Stable tag is `trunk` in `README.txt`.
- Text domain loaded on `plugins_loaded`; `Domain Path: /languages`.