# Vendor Patches

## Do Not Remove Without A Replacement Fix

These patches are part of the runtime contract of this TYPO3 lab. Do not remove,
skip, rename, or "simplify" them during dependency updates unless the same fix is
already present upstream, verified locally, and the corresponding patch lock has
been regenerated. Removing a patch without a proven upstream replacement can
reintroduce production-facing editor, workspace, or rendering failures that may
only appear after deployment.

Vendor patches are applied by `cweagans/composer-patches` during Composer
install.

## Current Patches

| Package | Patch | Purpose |
|---|---|---|
| `friendsoftypo3/visual-editor` | `friendsoftypo3-visual-editor-edit-mode-post-content-type.patch` | Treats only JSON edit-mode POSTs as Visual Editor saves and accepts JSON content types with parameters. |
| `typo3/cms-backend` | `typo3-cms-backend-workspace-query-overlay-search.patch` | Makes selected backend search paths include and overlay workspace-only record values consistently. |
| `typo3/cms-core` | `typo3-cms-core-workspace-query-overlay-api.patch` | Adds the workspace-aware query helper and normalizes directly selected workspace rows during overlay. |

The Visual Editor patch is defined in the root `composer.json`. Workspace
overlay patch definitions are provided by
`webconsulting/typo3-workspace-overlay-patch`. Root patch files are tracked so
Composer installs are reproducible from a clean checkout.

Run this after changing patch definitions or patch files:

```bash
composer patches-relock
composer update --lock
composer install
```
