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
| Poppy feature, Pi Durable example, help and hub links | `packages/site_package/Resources/Private/Data/Content/poppy/poppy.payload.json`, diagrams in `Resources/Public/Images/Poppy` | `sitepackage:seed-poppy` |
| Desiderio translations (de/zh/hu): every page, element, collection item, image text, news article, tag, category and Powermail form the seeders create in English | translation memories in `packages/site_package/Resources/Private/Data/Translations/desiderio/` (`de.json`, `zh.json`, `hu.json`: English text → translation; `scope.json`: what other commands own) | `sitepackage:content:translate --site=desiderio` |
| Element library folders 1065, 1026, 1064, 1306 | per element `library.json` (and `library.de.json` for 1064) in desiderio, innesto and astryx_typo3 | `desiderio:library:seed` |
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

1. `ddev exec vendor/bin/typo3 desiderio:styleguide:seed`
2. `ddev exec vendor/bin/typo3 sitepackage:seed-utility-translations` (always after 1: the styleguide seed replaces content in every language)
3. `ddev exec vendor/bin/typo3 webcon-jev:examples:seed` (always after 1: the styleguide seed replaces the content of the Powermail Lab page, including the overview section that lists forms 07–11)
4. `ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio` (always after 1–3: it translates what they created into German, Chinese and Hungarian and leaves the translations of steps 2 and 3 alone)
5. `ddev exec vendor/bin/typo3 desiderio:starter:seed --preset=corporate --root-map=corporate:740` (without `--hide-unmanaged-children`, which would hide the element library folder)
6. `ddev exec vendor/bin/typo3 desiderio:library:seed --parent=505 --hosts=desiderio,innesto,core`, then `--parent=740`, `--parent=1290 --hosts=astryx_typo3,core`, and `--parent=933 --locale=de`
7. `ddev exec vendor/bin/typo3 desiderio:blog:seed-pages --root=69` and `--root=390`. Never without `--root`: it would seed English demo posts into every blog. A blog keeps its page template ("Blog – Classic" or "Blog – Modern"); one that has neither gets Blog – Classic, and `--layout=classic` or `--layout=modern` picks one. Only the seeder's own demo posts get an example comment.
8. `ddev exec vendor/bin/typo3 astryx-typo3:site:seed --content`
9. `ddev exec vendor/bin/typo3 agentnexus:seed-site --base=https://webconsulting-typo3-lab.ddev.site/agent-nexus/`, then `git checkout config/sites/agent-nexus/` (it rewrites the site configuration and drops its comments)
10. `ddev exec vendor/bin/typo3 sitepackage:content:apply EXT:site_package/Resources/Private/Data/Content/<site>/<file>.payload.json` for each payload (after 7, because the blog seeder writes SEO fields). After editing `Build/Data/v14-blog/extension-posts.json`, write its payload again first:

    ```bash
    php packages/site_package/Build/Scripts/build-blog-posts-payload.php --definitions=packages/site_package/Build/Data/v14-blog/extension-posts.json --out=packages/site_package/Resources/Private/Data/Content/v14-blog/extension-posts.payload.json --site=v14-blog --root=69 --folder=70 --language=en --layout=pagets__DesiderioBlogModern --author="Webconsulting TYPO3 Team" --author-email=office@webconsulting.at
    ```
11. `ddev exec vendor/bin/typo3 sitepackage:seed-poppy` (after Desiderio reseeding: applies the lab-owned feature/help payload, imports its native FAL images and orders content through DataHandler move commands).
12. `ddev exec vendor/bin/typo3 cache:flush`, then `ddev exec vendor/bin/typo3 sitepackage:solr:reindex --index` (clears each Solr site's documents, queues every indexing configuration again and indexes it; on production run the same command in the web container after a database push)

`ddev lab-update <package>` runs steps 1–4 and the legacy redirects after a
Composer update. It refuses to run while development clones are linked.

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
