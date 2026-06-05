# Archived Composer Patches

These patches were removed from the active lab setup on 2026-06-05 and kept here
for later reuse.

## Contents

| Package | Patch | Purpose |
|---|---|---|
| `friendsoftypo3/visual-editor` | `friendsoftypo3-visual-editor-edit-mode-post-content-type.patch` | Treats only JSON edit-mode POSTs as Visual Editor saves and accepts JSON content types with parameters. |
| `typo3/cms-backend` | `typo3-cms-backend-workspace-query-overlay-search.patch` | Makes selected backend search paths include and overlay workspace-only record values consistently. |
| `typo3/cms-core` | `typo3-cms-core-workspace-query-overlay-api.patch` | Adds the workspace-aware query helper and normalizes directly selected workspace rows during overlay. |

`patches.lock.json` is the last lock snapshot from `cweagans/composer-patches`.

## Restore Later

1. Copy patch files back to a root `patches/` directory.
2. Re-add `cweagans/composer-patches` to `require-dev` and `config.allow-plugins`.
3. Restore this `extra` block in `composer.json`:

```json
"extra": {
  "composer-exit-on-patch-failure": true,
  "patches": {
    "friendsoftypo3/visual-editor": {
      "Handle non-save edit-mode POST requests without unauthorized errors": "patches/friendsoftypo3-visual-editor-edit-mode-post-content-type.patch"
    },
    "typo3/cms-backend": {
      "Search and overlay workspace records consistently in backend field-filtered queries": "patches/typo3-cms-backend-workspace-query-overlay-search.patch"
    },
    "typo3/cms-core": {
      "Add workspace-aware query helper and normalize directly selected workspace records": "patches/typo3-cms-core-workspace-query-overlay-api.patch"
    }
  }
}
```

4. Run:

```bash
composer update cweagans/composer-patches --with-all-dependencies
composer patches-relock
composer install
```

See `README.md` and `UPSTREAM_ISSUE.md` in this folder for patch rationale.
