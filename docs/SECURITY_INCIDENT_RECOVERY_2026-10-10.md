# Security recovery record — 2026-10-10

- Repository: `ademisler/contentagent` (public)
- Original GitHub ID: `1215047314`; private read-only forensic archive: `archive-contentagent-20261010-1215047314`
- New repository ID: `1413139970`, created empty without a template
- Verified rebuilt root commit: `4c1dfd232881c1d7cde4660b4496005be602e7bc`
- Independently calculated recovered source tree: `87b1c3db3e24cc60baf936dc2977d690cf758cc2`
- Source files: 224

## Incident and clean-room provenance

The original Git history, previous credentials and Git object database were **not** imported. Pinned sources were reviewed in disposable network-isolated sandboxes; editor auto-run tasks, malicious disguised font blobs and identified injected code loaders were removed. Clean one-root Git bundles passed Git integrity and SHA-256 verification. Known malicious historical Git blob SHAs did not resolve in the replacement repository; the original is private and archived for evidence.

Across the recovery set, 712 JavaScript/Python/Bash/JSON files and 917 TypeScript/TSX/JSX files passed static parsing checks (one symlink safely skipped). These results **do not** constitute complete functional, integration or build testing.

## Pending and safeguards

- Reissue external service credentials and review Actions and release workflows before re-enabling any non-Pages automation. Do not reuse old tokens or deployment keys.
- Keep the archived history private. Prior issues, pull requests, releases and tags are retained only in the forensic archive unless separately migrated.
- Verify dependent production releases and clean local source checkouts independently. Never restore old `.git` object databases or caches.
- Restricted source backup, independent tree hashes, Git bundle and review manifests are saved in the private recovery store.

This documents a verified GitHub history reset, not completion of all external provider or deployment tasks.
