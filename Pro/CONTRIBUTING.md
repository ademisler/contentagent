# Contributing

- Use root scripts: `npm run build`, `npm run pot`, `npm run assets`, `npm run release:min --version=X.Y.Z`.
- Code style:
  - PHP: WordPress Coding Standards via PHPCS (`composer run lint:php`).
  - TS/React: ESLint + Prettier (`npm run lint:ts`).
- Versioning: `npm run bump -- X.Y.Z` updates plugin header and plugin/package.json.
- Branching: feature branches → pull request to `main`.
- CI: Pushing `vX.Y.Z` tag triggers build + artifact upload.