# Webconsulting TYPO3 Lab

A DDEV-powered TYPO3 14.3 integration lab for server-rendered design systems,
content editing, search, forms, APIs and governed agent workflows.

[Documentation](docs/README.md) ·
[Site inventory](docs/site-configuration.md) ·
[MCP clients](docs/mcp-clients.md) ·
[GitHub](https://github.com/dirnbauer/webconsulting-typo3-lab)

## Runtime

| Component | Version or policy |
|---|---|
| TYPO3 | `14.3.6` (minimum `^14.3.6`) |
| PHP | `8.4` minimum |
| DDEV | `>=1.25.3`, Apache FPM, Mutagen |
| Database | MariaDB `10.11` |
| Node.js | `24` inside DDEV |
| Frontend | Vite `8`, official TYPO3 plugin and asset collector |
| Search | Apache Solr `10` through `ddev/ddev-typo3-solr` |
| Browser regression | Playwright + axe-core, desktop and mobile Chromium |

The Composer platform is PHP 8.4. Run Composer, npm and Vite inside DDEV so
native Node packages are installed for Linux rather than the host platform.

## Quick start

Requirements: Docker or OrbStack, DDEV `>=1.25.3`, Git and the two demo-data
artifacts described in [DDEV bootstrap](docs/ddev-bootstrap.md).

```bash
git clone https://github.com/dirnbauer/webconsulting-typo3-lab.git
cd webconsulting-typo3-lab
cp config/system/settings.php.example config/system/settings.php

ddev start
ddev composer install
ddev npm ci
ddev import-db --file=dump.sql.gz

# Use the matching Fileadmin archive supplied with the database dump.
ddev import-files --source=.tarballs/fileadmin.tar.gz

ddev typo3 extension:setup
ddev vite build
ddev typo3 cache:flush
```

Open `https://webconsulting-typo3-lab.ddev.site/typo3/`. The imported demo
account (`admin` / `Demo123*`) is for this local DDEV installation only.

Never commit `config/system/settings.php`, `.ddev/config.local.yaml`, Vault
keys, API tokens or generated DDEV compose files.

## Design-system ownership

The two frontend systems share TYPO3 infrastructure but own separate template
and asset trees:

- [Desiderio](https://github.com/dirnbauer/desiderio) provides its shadcn/ui-
  inspired Fluid 5 components, ClassicContent renderers, page shell and
  Content Blocks.
- [Astryx for TYPO3](https://github.com/dirnbauer/astryx-typo3) provides its own
  Astryx page shell, Fluid components, templates, CSS and Content Blocks.
  It does not import React or StyleX into the browser.
- [Innesto](https://github.com/dirnbauer/innesto) extends the Desiderio element
  library under the `Webconsulting` PHP namespace.

Sites select one frontend tree through Site Set dependencies. Provider filters
such as `elementLibrary.hosts: 'desiderio,innesto,core'` prevent the two
catalogues from being mixed in an editor's element picker.

`typo3/cms-fluid-styled-content` is installed for the standalone Blog and
Camino demos. Desiderio renders classic content through its own
`Resources/Private/ClassicContent` tree. The Blog embedded in the main
Desiderio site uses `blog/integration`; the separate Blog sites use the
standalone integration.

## Frontend assets

The project uses only the official Simon Praetorius toolchain:

- `vite-plugin-typo3` discovers each extension's
  `Configuration/ViteEntrypoints.json`;
- `praetorius/vite-asset-collector` resolves the generated manifest;
- `s2b/ddev-vite-sidecar` exposes the optional development server.

There is no custom manifest reader, asset ViewHelper or server detector. The
compiled manifest is the reliable default, so pages keep their CSS when no
development process is running.

```bash
ddev npm ci
ddev vite build
```

For HMR, add the explicit URL to the ignored `.ddev/config.local.yaml`, restart
DDEV, and start Vite in a second terminal:

```yaml
web_environment:
  - TYPO3_VITE_DEV_SERVER=https://vite-webconsulting-typo3-lab.ddev.site
```

```bash
ddev restart
ddev vite dev
```

Removing the variable returns the site to manifest mode.

## Sites

| Site | Local path | Root page |
|---|---|---:|
| Desiderio | `/` | `505` |
| Astryx for TYPO3 | `/astryx-typo3/` | `1290` |
| Camino | `/camino/` | `99` |
| Blog | `/blog/` | `15` |
| Blog demo | `/14/` | `69` |
| TYPO3 Blog | `/typo3-blog/` | `390` |
| Desiderio corporate starter | `/desiderio-corporate-starter/` | `740` |
| MTUG Camp Munich 2026 | `/mtug-camp-munich-2026/` | `933` |

The complete language and Site Set inventory lives in
[Site configuration](docs/site-configuration.md).

## Content and video policy

The shipped database, Fileadmin and default element-library seeds contain no
video content elements and no video files. Video-capable components and
generation commands remain available only as explicit, opt-in tooling. Do not
commit generated captures, audio, transcripts or browser recordings.

## MCP and agent clients

The local MCP server uses stdio and therefore needs no dedicated network port.
The committed `.mcp.json` starts it through the named DDEV project, so commands
also work when a client was opened from another directory. Codex, Claude Code
and Cursor setup and health checks are documented in
[MCP clients](docs/mcp-clients.md).

## Verification

The canonical test entrypoint supports suites through `-s` and validates a
requested PHP minor through `-p`:

```bash
ddev exec Build/Scripts/runTests.sh -s ci -p 8.4
```

Individual checks:

```bash
ddev composer validate --strict --no-check-publish
ddev composer audit
ddev exec Build/Scripts/runTests.sh -s phpstan -p 8.4
ddev exec Build/Scripts/runTests.sh -s unit -p 8.4
ddev exec Build/Scripts/runTests.sh -s frontend
ddev exec Build/Scripts/runTests.sh -s e2e
ddev typo3 lint:yaml config/sites packages/site_package/Configuration/Sets
ddev typo3 site:list
ddev solrctl list
```

The quality suite checks both local PHP packages at PHPStan maximum level on
PHP 8.4 and runs their PHPUnit tests. It also validates Composer and YAML,
builds the frontend, and audits Composer/npm dependencies.

The Playwright suite checks Records List, Powermail, Blog and Astryx at desktop
and mobile widths. It rejects missing or 4xx/5xx stylesheets, accidental Vite
development URLs, missing/multiple H1 elements, horizontal overflow, video
markup, console failures and serious or critical WCAG violations.

## Maintenance

After dependency changes:

```bash
ddev composer update <package> --with-all-dependencies
ddev npm install
ddev typo3 extension:setup
ddev vite build
ddev typo3 cache:flush
ddev exec Build/Scripts/runTests.sh -s ci -p 8.4
```

Do not use `git clean -fdX` in this project: ignored paths include local DDEV
configuration and database snapshots. Inspect cleanup candidates first and
remove only reproducible caches or generated outputs.

## Documentation

- [Documentation index](docs/README.md)
- [DDEV bootstrap and backups](docs/ddev-bootstrap.md)
- [Site configuration](docs/site-configuration.md)
- [MCP clients](docs/mcp-clients.md)
- [WorkOS frontend plugins](docs/workos-frontend-plugins.md)
- [Site-package internals](packages/site_package/README.md)

## Credits and licences

Thank you to the TYPO3 community; Simon Praetorius and the Vite integration
contributors; the DDEV and TYPO3-Solr teams; the Content Blocks, Visual Editor,
News, Blog and Powermail maintainers; Netresearch; and every extension author
represented in `composer.lock`.

Special thanks go to the Astryx team, Meta Open Source, the Facebook design-
systems community and all Astryx contributors. The pinned upstream Astryx
release is MIT-licensed, copyright 2026 Meta Platforms, Inc.; the exact licence
text and pinned source commit are retained in
[Astryx for TYPO3's third-party notice](https://github.com/dirnbauer/astryx-typo3/blob/main/THIRD_PARTY_NOTICES.md).
