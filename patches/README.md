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
| `in2code/powermail` | Two behaviours the lab relied on in our former fork: a translated plugin accepts the translated form it names (`translated-plugin-form`; otherwise every DE/ZH/HU submission is silently lost), and the Google Ads snippet renders only when the Marketing set enables it (`adwords-constant`). |
| `in2code/powermail_cond` | Three behaviours from our former fork: the `EvaluateRuleEvent` rule-operator seam webcon_jev's operators 100-104 use, the `in2code/powermail-cond` site set with the `prerender` flag Desiderio's form template reads, and the frontend that waits for a pending condition request and dispatches `powermailcond:processed`. |
| `netresearch/nr-vault` | Report a secret that is absent after a later successful delete as a warning, not as lost data (`inventory-lifecycle`, rebased on 1.10.0). The bounded-audit-range patch was dropped: 1.10.0 seeds a bounded verification from the nearest earlier row itself. |

## Forked dependencies

Two third-party extensions are installed from our own forks
(`github.com/dirnbauer/*`, mirrored to gitlab.webconsulting.at), because upstream
has no release that works on our TYPO3 14 line. We pull upstream changes in; we
never push, open pull requests or comment upstream.

| Package | Fork, constraint | Why |
| --- | --- | --- |
| `studiomitte/friendlycaptcha` | `dirnbauer/friendlycaptcha-typo3`, `~2.3.0.1` | Upstream 2.3.0 declares TYPO3 14.3, but its Powermail validator never resolves the Extbase lazy proxies Powermail 14 returns and reads the plugin FlexForm without a null guard. Since 2026-09-23 the fork is upstream 2.3.0 plus that fix (16 files differ, down from 68). |
| `studiomitte/solr-numbered-pagination` | `dirnbauer/solr_numbered_pagination`, `dev-main` | Upstream supports TYPO3 14 only on an untagged `main`; the fork carries it with a guard for the pagination type EXT:solr 14 hands to the event. |

Powermail and Powermail Conditions come from in2code's Early Access Packagist
(`https://in2code.repo.packagist.com/webconsulting/`, repository `in2code` in
`composer.json`, restricted to those two packages) since TYPO3 14.3.12; the lab
carries the behaviours it needs as the patches above. The token lives only in
Coolify (build-only variable `IN2CODE_PACKAGIST_TOKEN`) and on the developer's
machine, reaching Composer as `COMPOSER_AUTH`; GitHub has no copy, so CI drops
these two packages and their patches, and the Coolify build and the local run
before each merge check that the patches apply (`docs/coolify-deployment.md`). Our former forks
keep their GitLab mirrors so that older lab commits can still be built.

**Tags.** Our fork releases are four-part tags, `<upstream line>.<our revision>`
(`14.0.3.3`, `2.3.0.1`), consumed with `~` so Composer can only pick our revisions
(`~14.0.3.3` = `>=14.0.3.3 <14.0.4`). A `+build` suffix does not work: Composer
drops it and then prefers an upstream tag without our fixes. Every clone's
`upstream` remote is fetched with `--no-tags`.

## Patched dependencies

TYPO3 14.3.8 includes the workspace move-pointer guard and logs a warning when
the live record is missing. The local `typo3/cms-core` patch was removed after
verifying the upstream guard in `PageRepository::versionOL()`.

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
