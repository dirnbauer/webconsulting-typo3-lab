# Astryx German themes overview

The live `/astryx-typo3/de/themes/` URL was checked on 12 October 2026. It rendered a generic page with no theme cards and English footer text. The English overview worked.

## Cause and repair

The Astryx seeder inserted German page overlays directly, leaving the source layout fields and theme selection empty. The German overview therefore selected the generic page template. Once its layout was repaired, the generated overview still contained hard-coded English text, and its links were missing because the German theme pages had no theme selection.

The Composer patch `patches/webconsulting-astryx-german-theme-overview.patch` fixes translation upserts through DataHandler, translates the generated overview through English/German XLIFF catalogs, and lets footer settings resolve localized labels while preserving literal text settings. The generator produces the English catalog from the theme registry; German contains all 83 matching labels. Theme names, font names and identifiers stay unchanged.

Lab-owned footer catalogs and site settings supply the German tagline and copyright text. The scoped payload `packages/site_package/Resources/Private/Data/Content/astryx-themes/translations-de.payload.json` restores layout/theme fields on the 26 German overview/detail records and adds the overview's German description. It preserves content, IDs and ordering and checks record identity before applying.

## Verification

- Local EN and DE: 25 theme cards, 25 correctly localized links, all 83 translated labels resolved, including placeholders and accessible names.
- German page inspected in the browser; no console errors. Screenshots: `var/qa/astryx-themes-20261012/german-overview.png` and `german-theme-cards.png`.
- Translation consistency: 2 unit tests, 559 assertions; deterministic generator rebuild.
- Seeder regression: failure reproduced before the fix; 2 functional tests, 358 assertions pass afterward. Checks reread the database after repairs. Scoped PHPStan, formatting and PHP 8.4 syntax pass. Functional tests used the extension's isolated SQLite fixture under host PHP 8.5 because the DDEV root installation lacks the testing framework.
- Local repair applied through DataHandler; a repeated dry run reports 26 unchanged records and no writes.
- Composer patch checked against the exact locked Astryx v2.5.0 source and verified to recreate the fixed files. Composer validation passes; dependency versions are unchanged.

The normal Composer lock refresh encountered an unrelated private GitLab repository authentication failure. Only the content hash was refreshed with Composer's own `Locker::getContentHash()` API; no package metadata or versions changed.

## Deployment procedure

Local implementation and data repair passed the checks above before deployment. Deploy the lab changes together, then dry-run and apply the scoped repair in the production container:

```sh
vendor/bin/typo3 sitepackage:content:apply EXT:site_package/Resources/Private/Data/Content/astryx-themes/translations-de.payload.json --allow-production --dry-run
vendor/bin/typo3 sitepackage:content:apply EXT:site_package/Resources/Private/Data/Content/astryx-themes/translations-de.payload.json --allow-production
vendor/bin/typo3 cache:flush
```

Verify both overview languages after deployment. The seeder's pre-existing duplicate cleanup is unchanged; converting it to a page deletion command would cascade into translated content and was outside this repair.
