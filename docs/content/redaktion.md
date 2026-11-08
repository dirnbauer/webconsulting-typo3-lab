# Typewright software page (formerly Redaktion)

The source page `/software/typewright` (until 2026-11-08 `/software/redaktion`) is owned by the lab's site package. The app was renamed Typewright in 2.4.0; the command, the payload path, the `redaktion:*` markers and the film id keep the old name. The first `seed-redaktion` run after the rename finds the page by its old slug, moves it and its translations to `/software/typewright` and turns the redirects TYPO3 makes into 301s. It uses normal Desiderio elements, collections and FAL references. English source, German, Chinese and Hungarian translations are stored in the existing memories.

```sh
vendor/bin/typo3 sitepackage:seed-redaktion --dry-run
vendor/bin/typo3 sitepackage:seed-redaktion
vendor/bin/typo3 sitepackage:films:seed --film=redaktion
vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=<redaktion>,<software> --languages=de,zh,hu --fail-on-missing
```

Resolve both page IDs within the Desiderio root on the target installation. They differ between local and production databases. `seed-redaktion` resolves its parent itself and orders the owned content through DataHandler; repeated execution reuses the records. Collections are linked after real child UIDs exist.

The page shows the fifteen features in three tab groups (Build pages the TYPO3 way, Write, edit and link, Media, languages and publishing), one screenshot each: `typewright-fNN-<hash>.webp`, captured from the packaged macOS application (2.4.0) with demo content from the TYPO3 lab; feature 9 (news) comes from the app's news lab test at the same size. The 2.2 tour and single shots stay on the page, hidden. The film (`films/redaktion.mjs`, id `redaktion`) has one shot per feature in the look of the home page film and sits right after the hero (`placements[].after.header`). Captions are WebVTT in English, German, Chinese and Hungarian (`translate-film-captions.mjs` from the translation memories); the page language is selected by default. Burned-in subtitles are disabled. The screenshot hashes change when a capture changes; generated fileadmin copies are ignored.

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

Since 2.2.0 the app adds a page assistant (position, type, URL path, translations, first element), page properties per language, TYPO3's own new content element wizard (page TSconfig, Content Blocks, column restrictions), a link browser that writes TYPO3's link formats (`t3://page?uid=…#…`, `t3://file?uid=…`, `t3://folder?…`, `t3://record?identifier=…&uid=…`, mail, phone, web) from the page's `TCEMAIN.linkHandler`, and a media library on FAL (`sys_file_metadata` per language, `sys_file_reference` overrides incl. decorative images). No lab site configures a record link handler yet; news links appear once a site adds the `georgringer/news-recordlinks` set with `news.recordLinks.detail`. After a cache flush the first wizard request builds all Content Blocks labels and takes about a minute in the lab; the app loads it in the background when a page opens.

Since 2.3.0 a save confirms itself in the editor's save bar (state with time, "Gespeichert" on the button) instead of a floating box, and notices are message cards with a kind. Rich text keeps what TYPO3 stores (abbr, sub, sup, del, ins, kbd, mark, small, span lang, block class/id/lang/style, RTE image attributes; `<li>`/`<td>` without the editor's `<p>`); a field with markup the editor cannot keep opens as HTML with a note instead of losing it. Workspaces use TYPO3's own commands: the change list is the Workspaces module's selection (new records included), "publish all" is `getCmdArrayForPublishWS` (2.2 published nothing: it looked for a stage TYPO3 14 does not have), discard and stage run through DataHandler. The connector passes PHPStan level 8 without a baseline.

Since 2.4.0 the app is **Typewright**: settings at the foot of the sidebar (Sprache, Berechtigungen, Verbindung, Arbeitsbereich, Lokale Entwürfe, Tastenkürzel), Hell/Dunkel/System, a connection dialog in the T3 Ops sign-in look, the original webconsulting logos. News are edited per language with every TCA field of the news type: media with alt text per use and online videos, related news and links, categories and tags, SEO and publishing fields, content elements; all writes in one DataHandler run. Profile, drafts and keychain of 2.3 carry over. To switch the local connector, check out the tag in `vendor/kdirnbauer/typo3-desktop-connector` (a source install) and run the dump-autoload, `extension:setup` and `cache:flush` lines above.

The current beta requires TYPO3 14.3, abilities 1.3 and an active administrator account. Record-level editor scopes are not implemented, so ordinary editor accounts are refused. Existing JWT/auth and sanitizer corrections remain enabled. This is a development evaluation, not a claim of a production deployment of the editing service.

The native app is Apple Silicon, unsigned and not notarized. The page says this and offers a demonstration; it does not promise a public binary or a public source repository. The locally built installer is delivered separately.

Local verification uses the private app's unit/browser/native tests, plus real API requests against the DDEV installation. The API test creates only a hidden fixture folder, hidden articles and a synthetic non-admin account, then removes them through DataHandler. It confirms actual CRUD, token renewal, refusal of a forged admin claim, and explicit review approval for deletion. It never publishes an existing workspace.
