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
| `t3g/blog` | Replace the 28 legacy extension, module, plugin, record, and action icons with theme-aware TYPO3 v14 SVGs. |
| `typo3/cms-core` | Guard workspace move-pointer overlays when no live record exists. |

## Forked dependencies

Four third-party extensions are installed from our own forks
(`github.com/dirnbauer/*`, mirrored to gitlab.webconsulting.at), because upstream
has no release that works on our TYPO3 14 line. We pull upstream changes in; we
never push, open pull requests or comment upstream.

| Package | Fork, constraint | Why |
| --- | --- | --- |
| `in2code/powermail` | `dirnbauer/powermail`, `~14.0.3.3` | in2code's TYPO3 14 line is a paid early-access programme; the fork is our v14 port (no v14.3 deprecations since 14.0.3.2, lazy form relations again since 14.0.3.3). |
| `in2code/powermail_cond` | `dirnbauer/powermail_cond`, `dev-typo3-v14` | Same situation; the fork adds the `EvaluateRuleEvent` seam webcon_jev uses. |
| `studiomitte/friendlycaptcha` | `dirnbauer/friendlycaptcha-typo3`, `~2.3.0.1` | Upstream 2.3.0 declares TYPO3 14.3, but its Powermail validator never resolves the Extbase lazy proxies Powermail 14 returns and reads the plugin FlexForm without a null guard. Since 2026-09-23 the fork is upstream 2.3.0 plus that fix (16 files differ, down from 68). |
| `studiomitte/solr-numbered-pagination` | `dirnbauer/solr_numbered_pagination`, `dev-main` | Upstream supports TYPO3 14 only on an untagged `main`; the fork carries it with a guard for the pagination type EXT:solr 14 hands to the event. |

**Tags.** Our fork releases are four-part tags, `<upstream line>.<our revision>`
(`14.0.3.3`, `2.3.0.1`), consumed with `~` so Composer can only pick our revisions
(`~14.0.3.3` = `>=14.0.3.3 <14.0.4`). A `+build` suffix does not work: Composer
drops it and then prefers an upstream tag without our fixes. Every clone's
`upstream` remote is fetched with `--no-tags`.

## Patched dependencies

`typo3/cms-core` keeps the workspace move-pointer guard: 14.3.7 still reads the
live record without checking that the query returned one.

`supseven/inline-page-module` needed a constructor patch for 14.3.7 until 4.0.3,
which passes the new `RecordIdentityRenderer` argument itself; the patch is gone.

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
