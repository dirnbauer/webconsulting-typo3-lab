# Webconsulting TYPO3 Lab Site Package

Local TYPO3 14.3 provider extension for shared lab Site Sets, editor defaults,
Desiderio integration, Solr defaults, Admin Panel defaults, and Visual Editor
Cowriter integration. Package version: `14.3.4`; PHP requirement: `^8.4`.

## Site Sets

| Site Set | Dependencies | Purpose |
|---|---|---|
| `webconsulting/site-package` | — | Base defaults, Admin Panel, Cowriter RTE, middleware, and MCP table metadata |
| `webconsulting/site-package-search` | base, `webconsulting/solr-defaults` | Solr defaults and numbered pagination |
| `webconsulting/site-package-blog` | search, `webconsulting/desiderio-blog-standalone` | Desiderio standalone Blog rendering |
| `webconsulting/site-package-blog-bootstrap` | search, `blog/bootstrap-53` | Bootstrap Blog demo rendering |
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
| `Configuration/Sets/Blog*/` | Desiderio and Bootstrap Blog wrappers |
| `Configuration/Sets/Camino/` | Camino wrapper |
| `Configuration/Sets/Workos/` | WorkOS template paths, CType bridge, and CSS include |
| `Classes/Command/SeedWorkosFrontendDemoCommand.php` | Idempotent DataHandler page/content seeder |
| `Resources/Private/Extensions/WorkosAuth/` | Lab-owned WorkOS Fluid templates and partials |
| `Resources/Public/Css/workos-shadcn.css` | Semantic WorkOS component styling |
| `Classes/Middleware/CowriterPreloadMiddleware.php` | Visual Editor Cowriter module preload |
| `Classes/Bootstrap/McpTableConfiguration.php` | `tt_address` MCP table metadata |

`ext_localconf.php` also configures the official Vite Asset Collector. Compiled
manifest assets are the default; setting `TYPO3_VITE_DEV_SERVER` explicitly
enables HMR. The package contains no manifest reader, dev-server detector or
asset ViewHelper.

WorkOS controllers, authentication, request tokens, routing, API calls, and
provisioning remain in `webconsulting/workos-auth`. The lab package changes
presentation only and never edits vendor files.

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
Build/Scripts/runTests.sh -s ci
ddev vite build
ddev npm run test:e2e
ddev typo3 lint:yaml config/sites packages/site_package/Configuration/Sets
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush
```

PHPStan runs at maximum level with the TYPO3 extension.
