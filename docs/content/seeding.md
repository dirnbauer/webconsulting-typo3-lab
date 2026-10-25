# Seeding the sites

Most of what the lab's sites say comes from seed sources in the extension
repositories. The database is the result. To change a seeded text, edit the
source, reseed, and commit the source; an edit made only in the backend is
gone after the next reseed. The style guide for the text itself is
[style-guide.md](style-guide.md).

## Where each site's content comes from

| Site | Source | Command |
|---|---|---|
| Desiderio (505) | `packages/desiderio`: `Classes/Data/StyleguideShowcasePages.php`, `Classes/Data/Showcase/*.php`, `StyleguideContentGroups.php`, `Resources/Private/Data/styleguide-*.json`, `NewsDemoSeeder.php`, `PowermailDemoFormDefinitions.php`; per element `ContentBlocks/ContentElements/*/fixture.json` | `desiderio:styleguide:seed` |
| Agent Nexus overview, Poppy/Pi Durable/help, navigation and legacy redirects | `packages/site_package/Resources/Private/Data/Content/poppy/poppy.payload.json`, diagrams in `Resources/Public/Images/Poppy` | `sitepackage:seed-poppy` |
| Desiderio translations (de/zh/hu): every page, element, collection item, image text, news article, tag, category and Powermail form the seeders create in English | translation memories in `packages/site_package/Resources/Private/Data/Translations/desiderio/` (`de.json`, `zh.json`, `hu.json`: English text → translation; `scope.json`: what other commands own) | `sitepackage:content:translate --site=desiderio` |
| Element library folders 1065, 1026, 1064, 1306 | per element `library.json` (and `library.de.json` for 1064) in desiderio, innesto and astryx_typo3 | `desiderio:library:seed` |
| The ten template sites (roots 7000–7090, `/templates/<slug>/`) | `packages/webcon_templates/Resources/Private/Data/Sites/<slug>.json`, written by `webcon-templates:import <slug> --filter=text` from `Build/Templates/<slug>/mapping.yaml` (never edit the JSON by hand) | `webcon-templates:site:seed --template=<slug>` (or `--all`), twice: the second run reports 0 changes |
| Template form definitions (`form_definition` records in the Form data folders 7920–7929) | `packages/webcon_templates/Resources/Private/Forms/<Theme>/*.form.yaml` (the seed source; editors change the records in the Forms module, and a reseed skips what they saved) | the same `webcon-templates:site:seed` (its form pass, `form:<name>` in the report) |
| Scheduler task "Delete old form submissions" (one record: the ten Form data folders, 3 months, daily 03:30, group "Privacy & retention") | `packages/webcon_templates/Classes/Command/ScheduleCleanupTaskCommand.php` | `webcon-templates:forms:schedule-cleanup` (once; it leaves an existing task alone) |
| Template element libraries (folders 7900–7909, one per template site; 98 records in all) | the `library` list of the same site JSON (from `mapping.yaml` `library:`), picker labels in `packages/webcon_templates/Resources/Private/Language/Library/<snake>.xlf` + de | the same `webcon-templates:site:seed`; then `desiderio:library:warm --site=template-<slug>`. After editing a labels file: `webcon-templates:library:refresh` (flushes the `l10n` and `desiderio_library` caches that keep the old texts), then warm again. Not `desiderio:library:seed`, which would fill the folder with Desiderio's generated demo content |
| Templates overview on Desiderio (`/templates/`, below Features): copy, structure and links; screenshots of the ten template sites; the templates film once `film/film.json` exists | `packages/webcon_templates/Resources/Private/Data/Overview/overview.json`, files in `packages/webcon_templates/Resources/Private/Fixtures/Overview/` | `webcon-templates:overview:seed` |
| Powermail Lab forms 07–11 on Desiderio (below 712) and their section of the lab's overview | `packages/webcon_jev/Classes/Data/JevExampleDefinitions.php` | `webcon-jev:examples:seed` |
| Corporate starter (740) | `packages/desiderio/Classes/Data/StarterSiteDefinitions.php` | `desiderio:starter:seed` |
| Astryx (1290) | `packages/astryx_typo3`: `Classes/Data/AstryxSiteDefinitions.php`, per element `fixture.json` | `astryx-typo3:site:seed --content` |
| Agent Nexus (1400) | `packages/agent_nexus`: `SeedSiteCommand.php`, FlexForm defaults, catalogs, XLIFF | `agentnexus:seed-site` |
| Demo posts on the TYPO3 v14 blog (69) and The TYPO3 blog (390) | `packages/desiderio/Classes/Data/BlogDemoPostDefinitions.php` | `desiderio:blog:seed-pages --root=<uid>` |
| The 20 extension posts on the TYPO3 v14 blog (69): categories, tags, texts, screenshots | `packages/site_package/Build/Data/v14-blog/extension-posts.json`, turned into `v14-blog/extension-posts.payload.json` by `packages/site_package/Build/Scripts/build-blog-posts-payload.php` | `sitepackage:content:apply` |
| Blog page templates: Blog classico (15) on "Blog – Classic", the TYPO3 v14 blog (69) on "Blog – Modern" | `blog-classico/page-template.payload.json`, `v14-blog/page-template.payload.json` | `sitepackage:content:apply` |
| Camp (933, German), Blog classico (15), the rest of 69 and 390, Camino (99) | payloads in `packages/site_package/Resources/Private/Data/Content/<site>/` | `sitepackage:content:apply` |
| Camp translations (en/zh/hu) | `packages/site_package/Resources/Private/Data/mtug-camp-translations.json` | `sitepackage:apply-camp-translations` |
| Blog classico translations (en/zh) | `blog-classico/translations-*.payload.json` in the payload folder (posts, news, and in `translations-records-*` the tags, categories and author) | `sitepackage:content:apply` |

## Order

Link the development clones first, so the seeders read what you edited:

```bash
Build/Scripts/lab-link.sh webconsulting/desiderio
```

Then, after a `ddev snapshot`:

0. `ddev exec vendor/bin/typo3 webcon-templates:site:seed --all`, twice: the second run must report
   0 changes on all ten template sites. One run writes pages, content (each home ends with the
   thank-you note to the template's author), files, the element library records (folders
   7900–7909) and the form definitions (Form data folders 7920–7929, `form:<name>` in the report);
   it never touches the Desiderio sites, so it can run before or after steps 1–14. Then
   `ddev exec vendor/bin/typo3 desiderio:library:warm --site=template-<slug>` for each of the ten.
   Never two seed runs at the same time; production: `--allow-production` in the web container.
1. `ddev exec vendor/bin/typo3 desiderio:styleguide:seed`
2. `ddev exec vendor/bin/typo3 sitepackage:seed-utility-translations` (always after 1: the styleguide seed replaces content in every language)
3. `ddev exec vendor/bin/typo3 webcon-jev:examples:seed` (always after 1: the styleguide seed replaces the content of the Powermail Lab page, including the overview section that lists forms 07–11)
4. `ddev exec vendor/bin/typo3 webcon-templates:overview:seed` (after 1, which creates its parent page /features: the Templates overview with screenshots of the ten template sites, Desiderio content elements only; DataHandler only, a second run reports 0 changes, `--allow-production` on production; the film is added once the film pipeline has written `Fixtures/Overview/film/film.json`)
5. `ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio` (always after 1–4: it translates what they created into German, Chinese and Hungarian and leaves the translations of steps 2 and 3 alone)
6. `ddev exec vendor/bin/typo3 desiderio:starter:seed --preset=corporate --root-map=corporate:740` (without `--hide-unmanaged-children`, which would hide the element library folder)
7. `ddev exec vendor/bin/typo3 desiderio:library:seed --parent=505 --hosts=desiderio,innesto,core`, then `--parent=740`, `--parent=1290 --hosts=astryx_typo3,core`, and `--parent=933 --locale=de`
8. `ddev exec vendor/bin/typo3 desiderio:blog:seed-pages --root=69` and `--root=390`. Never without `--root`: it would seed English demo posts into every blog. A blog keeps its page template ("Blog – Classic" or "Blog – Modern"); one that has neither gets Blog – Classic, and `--layout=classic` or `--layout=modern` picks one. Only the seeder's own demo posts get an example comment.
9. `ddev exec vendor/bin/typo3 astryx-typo3:site:seed --content`
10. `ddev exec vendor/bin/typo3 agentnexus:seed-site --base=https://webconsulting-typo3-lab.ddev.site/agent-nexus/`, then `git checkout config/sites/agent-nexus/` (it rewrites the site configuration and drops its comments)
11. `ddev exec vendor/bin/typo3 sitepackage:content:apply EXT:site_package/Resources/Private/Data/Content/<site>/<file>.payload.json` for each payload (after 8, because the blog seeder writes SEO fields). After editing `Build/Data/v14-blog/extension-posts.json`, write its payload again first:

    ```bash
    php packages/site_package/Build/Scripts/build-blog-posts-payload.php --definitions=packages/site_package/Build/Data/v14-blog/extension-posts.json --out=packages/site_package/Resources/Private/Data/Content/v14-blog/extension-posts.payload.json --site=v14-blog --root=69 --folder=70 --language=en --layout=pagets__DesiderioBlogModern --author="Webconsulting TYPO3 Team" --author-email=office@webconsulting.at
    ```
12. `ddev exec vendor/bin/typo3 webcon-templates:forms:schedule-cleanup` (once per installation, after the template sites are seeded: the scheduler task that deletes stored form submissions after 3 months; `--dry-run` shows what it would create, an existing task is left alone; production: the same command with no option in the web container). A schedule is not a runner: neither DDEV (no cron add-on) nor the Coolify container (no cron daemon) calls `scheduler:run`, so run `vendor/bin/typo3 scheduler:execute --task=<uid>` or `webcon-templates:forms:cleanup` until a runner is decided.
13. `ddev exec vendor/bin/typo3 sitepackage:seed-poppy` (after Desiderio reseeding: applies the lab-owned feature/help payload, imports its native FAL images and orders content through DataHandler move commands).
14. `ddev exec vendor/bin/typo3 cache:flush`, then `ddev exec vendor/bin/typo3 sitepackage:solr:reindex --index` (clears each Solr site's documents, queues every indexing configuration again and indexes it; on production run the same command in the web container after a database push)

`ddev lab-update <package>` runs steps 1–3 and 5 and the legacy redirects after a
Composer update. It refuses to run while development clones are linked. The
styleguide seed leaves the Templates overview (step 4) alone, so run step 4
only after its source changed, then step 5.

## Checking the result

```bash
ddev exec vendor/bin/typo3 sitepackage:content:audit --site=all
ddev exec vendor/bin/typo3 sitepackage:content:audit --site=all --baseline=var/content-audit/baseline.json
```

The audit reads what the sites show. Before seeding, check the source files:

```bash
ddev exec php packages/site_package/Build/Scripts/lint-copy.php --site=desiderio packages/desiderio/ContentBlocks/ContentElements
ddev exec php packages/site_package/Build/Scripts/lint-copy.php --site=desiderio --php='Webconsulting\Desiderio\Data\StyleguideShowcasePages::homeContent'
```

## Traps

- **New image, new file name.** The FAL seeders import by file name and never
  re-import a name they already know.
- **Never blank a field in a fixture.** The resolver fills empty fields with
  generated filler, and pads collections to three items.
- **Change a page's title or its slug, not both.** Seeders find pages by
  parent plus title or slug; changing both creates a second page.
- **Payloads are uid-keyed.** `expect` guards against overwriting rows that
  changed after the export. After a reseed of seeded sites, their uids are
  new, which is why seeded content lives in seed sources and not in payloads.
- **A changed English text needs a new translation.** The Desiderio
  translation memories are keyed by the English text, so they survive a
  reseed, but a rewritten headline has no translation until someone adds one;
  until then the language pages show the English text. After changing seed
  copy, run `sitepackage:content:translate --site=desiderio --dry-run --missing=-`:
  it lists every English text without a translation, per language, with the
  page and field it belongs to. Add the translations to the memories and run
  the command again. `--fail-on-missing` makes that check fail a script.
- **Solr doesn't notice seeders.** They write through SQL, so run
  `sitepackage:solr:reindex --index` afterwards.
