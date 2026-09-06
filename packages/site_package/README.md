# Webconsulting TYPO3 Lab Site Package

Local TYPO3 14.3 provider extension for shared lab Site Sets, editor defaults,
Desiderio integration, Solr defaults, Admin Panel defaults, and Visual Editor
Cowriter integration. Package version: `14.3.4`; PHP requirement: `^8.4`.

## Site Sets

| Site Set | Dependencies | Purpose |
|---|---|---|
| `webconsulting/site-package` | — | Base defaults, Admin Panel, Cowriter RTE, middleware, and Blog plugin wrappers |
| `webconsulting/site-package-search` | base, `webconsulting/solr-defaults` | Solr defaults and numbered pagination |
| `webconsulting/site-package-blog` | search, `webconsulting/desiderio-blog-standalone` | Desiderio standalone Blog rendering |
| `webconsulting/site-package-agentstack` | base, `webconsulting/desiderio` | Agent Nexus frontend plugin wrappers |
| `webconsulting/site-package-camino` | search, `typo3/theme-camino` | Camino demo rendering |
| `webconsulting/site-package-workos` | base, `webconsulting/desiderio` | Lab-only WorkOS Fluid overrides, plugin bridge, and shadcn token CSS |

The WorkOS Site Set is hidden because it is specific to the Desiderio lab and
requires the optional `webconsulting/workos-auth` package. It is attached only
to `config/sites/desiderio/config.yaml`.

## Feature map

| Path | Purpose |
|---|---|
| `Configuration/Sets/SitePackage/` | Base Site Set, Admin Panel TypoScript, Cowriter RTE TSconfig |
| `Configuration/Sets/Search/` | Solr Site Set and numbered pagination defaults |
| `Configuration/Sets/Blog/` | Standalone Desiderio Blog wrapper for `blog`, `typo3-blog`, and `14lts` |
| `Configuration/Sets/AgentStack/` | Agent Nexus plugin rendering |
| `Configuration/Sets/Camino/` | Camino wrapper |
| `Configuration/Sets/Workos/` | WorkOS template paths, CType bridge, and CSS include |
| `Classes/Command/SeedWorkosFrontendDemoCommand.php` | Idempotent DataHandler page/content seeder |
| `Resources/Private/Extensions/WorkosAuth/` | Lab-owned WorkOS Fluid templates and partials |
| `Resources/Public/Css/workos-shadcn.css` | Semantic WorkOS component styling |
| `Classes/Middleware/CowriterPreloadMiddleware.php` | Visual Editor Cowriter module preload |
| `Configuration/TCA/Overrides/tt_content.php` | Address content-element icon, applied after the required `tt_address` package |

`ext_localconf.php` also configures the official Vite Asset Collector. Compiled
manifest assets are the default; setting `TYPO3_VITE_DEV_SERVER` explicitly
enables HMR. The package contains no manifest reader, dev-server detector or
asset ViewHelper.

WorkOS controllers, authentication, request tokens, routing, API calls, and
provisioning remain in `webconsulting/workos-auth`. The lab package changes
presentation only and never edits vendor files.

The current MCP server discovers addresses from TYPO3 TCA without project
registration. Cowriter is a required Composer dependency: loading its main
module includes all its imports in the Visual Editor iframe. The middleware
runs after page resolution and before Visual Editor persistence.

The base Site Set selects the Cowriter RTE preset and enables the Admin Panel
for authenticated backend users. `ext_localconf.php` extends Desiderio's own
RTE preset with Cowriter and selects compiled Vite assets by default.

## WorkOS demo data

```bash
ddev typo3 sitepackage:seed-workos-frontend
```

The command maintains the overview plus Login, Account, and Team pages, the
frontend-user storage sysfolder, group, descriptions, and plugin content
elements through TYPO3 DataHandler. Full details are in
[docs/workos-frontend-plugins.md](../../docs/workos-frontend-plugins.md).

## Development

```bash
ddev composer validate --strict --no-check-publish
ddev exec Build/Scripts/runTests.sh -s ci -p 8.4
ddev vite build
ddev npm run test:e2e
ddev typo3 lint:yaml config/sites packages/site_package/Configuration/Sets
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush
```

PHPStan runs at maximum level for PHP 8.4 with the TYPO3 extension. The root
quality runner owns linting, static analysis and PHPUnit tests for both local
packages; `-s unit` runs just the unit tests. This README replaces the duplicated
RST manual.

## Demo maintenance commands

These commands remain available for intentional demo-data maintenance:

- `sitepackage:seed-workos-frontend`: WorkOS pages and plugin records.
- `sitepackage:seed-utility-translations`: translations after a library reseed.
- `sitepackage:seed-ai-manuals`: nr-llm and Cowriter manual pages.
- `sitepackage:configure-ai-examples`: configured models and example records.
- `sitepackage:llm:generate-image`: optional provider-backed image generation.

Run commands through `ddev typo3` and inspect `--help` for their options. Take a
snapshot before changing demo data; these are not part of the quality suite.
