# Redaktion software page

The source page `/software/redaktion` is owned by the lab's site package. It uses normal Desiderio elements, collections and FAL references. English source, German, Chinese and Hungarian translations are stored in the existing memories.

```sh
vendor/bin/typo3 sitepackage:seed-redaktion --dry-run
vendor/bin/typo3 sitepackage:seed-redaktion
vendor/bin/typo3 sitepackage:films:seed --film=redaktion
vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=<redaktion>,<software> --languages=de,zh,hu --fail-on-missing
```

Resolve both page IDs within the Desiderio root on the target installation. They differ between local and production databases. `seed-redaktion` resolves its parent itself and orders the owned content through DataHandler; repeated execution reuses the records. Collections are linked after real child UIDs exist.

The film uses real screenshots of the packaged macOS application and synthetic demo articles. Its seven-line narration, timing, selectable English WebVTT and MP4 use the existing film pipeline. Burned-in subtitles are disabled. The screenshot hashes change when a capture changes; generated fileadmin copies are ignored.

The app and connector source live in the **private** GitLab repository `webcon/desktop-news-typo3`. No private source is copied into this public repository. The connector is an optional local development dependency for the original DDEV target. The default Lab manifest, CI and production deployment do not require access to this private app repository and do not expose its beta editing API. The product page and film do not require it.

To evaluate the connector, use the private-repository credentials already configured on your development machine:

```sh
php Build/Scripts/redaktion-dev-composer.php
COMPOSER=.composer-redaktion-dev.json composer update kdirnbauer/typo3-desktop-connector --no-scripts
# Synchronize the checkout into DDEV, then use the same profile for autoloading:
ddev mutagen sync
ddev exec env COMPOSER=.composer-redaktion-dev.json composer dump-autoload --no-scripts
ddev exec vendor/bin/typo3 extension:setup
ddev exec vendor/bin/typo3 cache:flush
```

The generated manifest and lock are ignored local files. A standard Lab Composer install returns to the default dependency set; repeat the explicit opt-in to enable Redaktion again. Create this profile before making development links; preserve existing links as documented in `lab-link.sh` when updating dependencies.

Since 2.1.0 the "Seiten & Inhalte" view works like the TYPO3 page module: one tree node per default-language page (translations are attached to it), columns from the real backend layout (`BackendLayoutView`, so the Desiderio page TSconfig layouts appear), language tabs with translation counts, and translation through DataHandler `localize` (abilities `content/localize-element` and `pages/localize-page`). The editor offers the TCA fields of each element type, including Content Blocks fields and collection items. Without a selected workspace the app always writes to the live website, never into the backend user's stored workspace.

The current beta requires TYPO3 14.3, abilities 1.3 and an active administrator account. Record-level editor scopes are not implemented, so ordinary editor accounts are refused. Existing JWT/auth and sanitizer corrections remain enabled. This is a development evaluation, not a claim of a production deployment of the editing service.

The native app is Apple Silicon, unsigned and not notarized. The page says this and offers a demonstration; it does not promise a public binary or a public source repository. The locally built installer is delivered separately.

Local verification uses the private app's unit/browser/native tests, plus real API requests against the DDEV installation. The API test creates only a hidden fixture folder, hidden articles and a synthetic non-admin account, then removes them through DataHandler. It confirms actual CRUD, token renewal, refusal of a forged admin claim, and explicit review approval for deletion. It never publishes an existing workspace.
