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
| `apache-solr-for-typo3/solr` | Align legacy backend typography, spacing, borders, and colors with TYPO3 v14 design tokens. This patch contains CSS only and adds no behavior. |
| `typo3/cms-core` | Guard workspace move-pointer overlays when no live record exists. |

## Forked dependencies

None. `studiomitte/friendlycaptcha` comes from the upstream 2.3.0 release (TYPO3 14 support;
desiderio 4.1.7 imports its Powermail TypoScript itself because upstream ships no Site Set) and
`studiomitte/solr-numbered-pagination` from the upstream repository's `main` branch, which
supports TYPO3 14 and EXT:solr 14 but has no tagged release yet. Our copies of both forks were
deleted after mirroring; we do not contribute changes upstream.

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
