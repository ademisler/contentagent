# AI Content Agent (ACA) – Repo Overview

- Plugin directory: `plugin/`
- Automation scripts: `scripts/`
- WordPress.org assets:
  - Placeholders: `/workspace/assets/placeholders/` (otomatik üretilir)
  - Final (tasarım): `/workspace/assets/final/` (varsa deploy bunu kullanır)
- Minimal distribution ZIPs: `/workspace/release/`

## Quick Runbook (root scripts)

- Build + copy admin assets
  ```bash
  npm run build
  ```
- Generate translation template (.pot)
  ```bash
  npm run pot
  ```
- Generate WordPress.org assets (icons, banners, screenshots)
  ```bash
  npm run assets
  # Output: /workspace/assets/placeholders/*.png
  ```
- Create minimal release zip (build + pot + zip)
  ```bash
  npm run release:min --version=1.0.1
  # Output: release/ai-content-agent-1.0.1-min.zip
  ```
- Stage to WordPress.org SVN (manual commit)
  ```bash
  SVN_DIR=/path/to/svn/checkout npm run deploy:svn
  # deploy final/ if exists, else placeholders/
  ```
- Lint & version
  ```bash
  composer run lint:php
  npm run lint:ts
  npm run bump -- 1.0.1
  ```

## CI
- Tag push (`vX.Y.Z`) triggers GitHub Actions to build, generate `.pot`, and upload minimal zip artifact.

## Structure

- `plugin/` – WordPress plugin (stable tag: trunk)
  - `ai-content-agent.php` – main plugin file
  - `admin/` – compiled admin assets copied to `admin/assets/` and `admin/js/index.js`
  - `includes/` – PHP classes and REST endpoints
  - `languages/` – i18n files; `ai-content-agent.pot`
  - `docs/README.md` – combined developer docs (install, build, automation, troubleshooting)
  - `README.txt` – WordPress.org readme
- `assets/placeholders/` – otomatik üretilen placeholder görseller
- `assets/final/` – yerleşecek nihai tasarımlar (aynı dosya adlarıyla)
- `scripts/` – repo-level helpers
  - `release.cjs` – build + pot + minimal ZIP
  - `create-release.sh` – legacy full ZIP creator
  - `deploy-wporg.sh` – stage trunk/tags/assets to SVN (final → placeholders fallback)
  - `generate-changelog.cjs` – append to `plugin/CHANGELOG.md`

## Links
- Contributing: `CONTRIBUTING.md`
- Security Policy: `SECURITY.md`

## Agent Notes

- Follow `AGENTS.md` for change methodology.
- Always prefer `plugin/` paths; old `ai-content-agent-plugin/` was removed.
- Distribution excludes dev files via `plugin/.distignore`.