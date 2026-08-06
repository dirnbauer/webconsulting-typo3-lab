# Site configuration

Canonical inventory for `config/sites/`. A directory is valid only when its
`config.yaml` points to an existing live siteroot. Never recreate historical
site identifiers merely because a stale document or database workspace record
mentions one.

## Active sites

| Identifier | Path | Local URL | Root page | Languages | Frontend owner |
|---|---|---|---:|---|---|
| `desiderio` | `config/sites/desiderio` | `/` | `505` | EN, DE, ZH, HU | Desiderio |
| `astryx-typo3` | `config/sites/astryx-typo3` | `/astryx-typo3/` | `1290` | EN, DE | Astryx for TYPO3 |
| `camino` | `config/sites/camino` | `/camino/` | `99` | EN | TYPO3 Camino |
| `blog` | `config/sites/blog` | `/blog/` | `15` | DE, EN, ZH | Desiderio Blog |
| `14lts` | `config/sites/14lts` | `/14/` | `69` | EN | Blog Bootstrap demo |
| `typo3-blog` | `config/sites/typo3-blog` | `/typo3-blog/` | `390` | EN | Desiderio Blog |
| `desiderio-corporate-starter` | `config/sites/desiderio-corporate-starter` | `/desiderio-corporate-starter/` | `740` | EN | Desiderio |
| `mtug-camp-munich-2026` | `config/sites/mtug-camp-munich-2026` | `/mtug-camp-munich-2026/` | `933` | DE, EN, ZH, HU | Desiderio |

The page tree at root 1290 is served exclusively by
`webconsulting/astryx-typo3` under the `astryx-typo3` identifier. No alias site
configuration or embedded copy of that extension remains in the lab.

## Separate template trees

Desiderio and Astryx are sibling frontends. They share TYPO3, Solr and selected
element-library services, but never a page-template or component tree.

The Desiderio root loads, among other optional integrations:

```yaml
dependencies:
  - webconsulting/desiderio-content-elements
  - webconsulting/desiderio-blog
  - webconsulting/desiderio-preset-corporate
  - webconsulting/innesto
```

Its editor picker is restricted in `settings.yaml`:

```yaml
elementLibrary.hosts: 'desiderio,innesto,core'
```

The Astryx site instead loads only its own frontend sets:

```yaml
dependencies:
  - webconsulting/astryx-typo3
  - webconsulting/astryx-typo3-content-elements
  - webconsulting/astryx-typo3-search
```

```yaml
elementLibrary.hosts: 'astryx_typo3,core'
```

Do not add both frontend base sets to one site. Do not copy templates between
the two extensions to share a visual implementation.

## Classic content and Blog

The lab does not install `typo3/cms-fluid-styled-content`. TYPO3 Core provides
the classic content record definitions, while Desiderio's `ClassicContent`
tree provides their frontend rendering.

The Desiderio root therefore uses `webconsulting/desiderio-blog`, whose optional
dependency is `blog/integration`. Do not replace it with
`webconsulting/desiderio-blog-standalone` unless the installation intentionally
adds Fluid Styled Content and accepts Blog's standalone page-rendering stack.

## Content seeding

Desiderio library records are scoped to the Desiderio root and providers:

```bash
ddev typo3 desiderio:library:seed \
  --parent=505 \
  --hosts=desiderio,innesto,core
```

Astryx owns its root, components and content seeder:

```bash
ddev typo3 astryx-typo3:site:seed --dry-run
ddev typo3 astryx-typo3:site:seed --content
```

Default seed commands exclude video-capable records. Use an explicit
`--include-video` option only when a generated video demonstration is actually
required; never add generated media to the normal lab snapshot.

The WorkOS-specific Desiderio pages remain idempotently maintained through:

```bash
ddev typo3 sitepackage:seed-workos-frontend
```

See [workos-frontend-plugins.md](workos-frontend-plugins.md) for their page and
plugin inventory.

## Solr connections

Each enabled language selects one of the shared language cores (`core_en`,
`core_de`, `core_zh`, `core_hu`, and so on). Sites do not create a core per
theme. DDEV exposes Solr to TYPO3 at `typo3-solr:8983`; the browser-facing admin
URL is listed by `ddev describe`.

```yaml
solr_enabled_read: true
solr_host_read: typo3-solr
solr_port_read: '8983'
solr_scheme_read: http
solr_path_read: /
solr_use_write_connection: false
```

Apply the package-owned configset and verify cores with:

```bash
ddev solrctl apply
ddev solrctl list
```

## Workspaces and MCP

Workspace versions of a siteroot (`t3ver_wsid > 0`) are not extra live sites.
Discard or publish the workspace change instead of creating a second site
configuration for the placeholder.

MCP tools should inspect capabilities at session start. Live writes are allowed
only when the local policy explicitly reports them; otherwise use a workspace.
Client setup is documented in [mcp-clients.md](mcp-clients.md).

## Adding or renaming a site

1. Confirm that the target page is live and has `is_siteroot=1`.
2. Create or rename one directory below `config/sites/`.
3. Set the canonical DDEV base URL, `rootPageId`, languages and Site Set
   dependencies.
4. Add language-specific Solr cores only when search is enabled.
5. Keep frontend ownership unambiguous through the provider filter.
6. Flush caches and run the validation below.

Do not leave autogenerated site stubs, empty directories or two configs for the
same root page.

## Validation

```bash
ddev typo3 lint:yaml config/sites
ddev typo3 site:list
ddev typo3 cache:flush
ddev exec Build/Scripts/runTests.sh -s e2e
```

The expected `site:list` result contains the eight identifiers in the table
above. The Playwright suite covers the Desiderio Records List, Powermail, Blog
and Astryx surfaces on desktop and mobile.

For database inspection:

```sql
SELECT uid, pid, title, is_siteroot, t3ver_oid, t3ver_wsid
FROM pages
WHERE is_siteroot = 1 AND deleted = 0
ORDER BY uid;
```

Live translation overlays are expected. Workspace placeholders must not receive
their own site config.
