# Vendor Patches

Vendor patches are applied by `cweagans/composer-patches` during Composer
install.

## Current Patches

| Package | Patch | Purpose |
|---|---|---|
| `typo3/cms-backend` | `typo3-cms-backend-workspace-query-overlay-search.patch` | Makes selected backend search paths include and overlay workspace-only record values consistently. |
| `typo3/cms-core` | `typo3-cms-core-workspace-query-overlay-api.patch` | Adds the workspace-aware query helper and normalizes directly selected workspace rows during overlay. |

The patch definitions are provided by
`webconsulting/typo3-workspace-overlay-patch`. The root patch files are tracked
so Composer installs are reproducible from a clean checkout.

Run this after changing patch definitions or patch files:

```bash
composer update --lock
composer install
```
