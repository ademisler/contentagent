# AI Content Agent (ACA)

Version: 1.0.0
WordPress plugin for AI‑assisted content planning and drafting with Google Gemini.

## Overview
- Plan and create content faster in a WordPress‑native workflow
- Idea generation, draft creation, and visual calendar scheduling
- Works with major SEO plugins (Yoast SEO, Rank Math, AIOSEO)
- Translation‑ready; `.pot` included and text domain loaded on `plugins_loaded`

## Requirements
- WordPress 5.0+
- PHP 7.4+

## Key Features
- AI‑Powered Ideas: Context‑aware topic suggestions
- Draft & Post Management: Review and publish with featured images and meta
- Calendar Scheduling: WordPress‑native reliability
- SEO Integration: Safe fallbacks with popular plugins

## Install (Dev)
```bash
npm install
npm run build:wp
```

## Build Artifacts
- Admin JS: `admin/assets/index-*.js` and fallback `admin/js/index.js`
- Translations: `languages/ai-content-agent.pot`

## Assets (WordPress.org)
- Placeholders: `/workspace/assets/placeholders/*.png` (generate with `npm --prefix plugin run make-assets`)
- Final designs: `/workspace/assets/final/*.png` (deploy prefers this if present)

## Distribution
- WordPress.org package excludes dev files via `.distignore`
- Deploy helper: `scripts/deploy-wporg.sh` (uses final → placeholders fallback)

## License
GPL v2 or later. See `license.txt`.

## Privacy
- No telemetry. External requests only to configured AI/image services.

## Changelog
- 1.0.0: Initial release on WordPress.org
