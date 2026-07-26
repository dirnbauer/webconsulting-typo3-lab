# Composer patches

This directory contains temporary fixes applied by
`cweagans/composer-patches`. The authoritative declarations live in
`composer.json` under `extra.patches`; resolved checksums and patch depths live
in `patches.lock.json`.

Do not edit installed files under `vendor/`. Change the patch here, relock it,
and reinstall the affected dependency instead.

## Active patches

| Package | Purpose |
| --- | --- |
| `apache-solr-for-typo3/solr` | Normalize sparse filter arrays before enhanced-route processing. |
| `typo3/cms-core` | Guard workspace move-pointer overlays when no live record exists. |
| `webconsulting/agentation` | Respect explicit backend-user toolbar settings and the configured opt-in default. |

## Workflow

```bash
composer patches-doctor
composer patches-relock
composer patches-repatch
composer validate --strict
composer install --dry-run
```

Commit the patch, `composer.json`, and `patches.lock.json` together whenever a
patch declaration or payload changes. Remove a patch as soon as the pinned
upstream release contains the fix, then relock and reinstall to prove the
package works without the local delta.
