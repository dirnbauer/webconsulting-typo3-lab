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

`studiomitte/friendlycaptcha` and `studiomitte/solr-numbered-pagination` are installed from
our own forks (`github.com/dirnbauer/*`), not from upstream.

FriendlyCaptcha: upstream 2.3.0 declares TYPO3 14.3 support, but its Powermail validator calls
`$mail->getForm()->getPages()` without resolving Extbase lazy proxies and reads the plugin
FlexForm without a null guard, while Powermail 14 annotates `Mail::$form`, `Form::$pages` and
`Page::$fields` as `@Lazy` and hands out the proxy unresolved. The fork carries both fixes.

Solr numbered pagination: upstream has no tagged release supporting TYPO3 14 — the newest tag,
1.0.4, caps at TYPO3 13.4 and EXT:solr 13. Support exists only on upstream `main`, and consuming
a moving branch would leave the lab unpinned, so the fork carries it with a guard for the
pagination type EXT:solr 14 hands to the event.

We do not contribute these changes upstream.

## Patched dependencies

`supseven/inline-page-module` 4.0.2 is the newest release and predates TYPO3
14.3.7, which appended a sixteenth constructor argument to
`PageLayoutController`. The extension's subclass still calls the parent with
fifteen, so the Page module answered every request with an `ArgumentCountError`.
The patch passes the argument through. Remove it once a release supports
14.3.7.

`typo3/cms-core` keeps the workspace move-pointer guard: 14.3.7 still reads the
live record without checking that the query returned one.

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
