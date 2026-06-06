# Site Package

TYPO3 14.3+ provider extension for shared Site Sets, editor defaults, Solr
defaults, Admin Panel defaults, and Visual Editor Cowriter integration in
Webconsulting TYPO3 Lab.

The package consolidates the former `adminpanel_defaults` and
`visual_editor_defaults` local extensions into one Composer-managed extension.

## Composer Package

| Field | Value |
|---|---|
| Composer name | `webconsulting/site-package` |
| TYPO3 extension key | `site_package` |
| Extension version | `14.1.0` |
| TYPO3 support | `^14.3` |
| PHP support | `^8.2` |
| Package type | `typo3-cms-extension` |
| Namespace | `Webconsulting\SitePackage` |

Release metadata is Composer-only. TYPO3 13 support and `ext_emconf.php` have
been removed.

## What This Package Provides

- Five TYPO3 Site Sets for lab demo sites
- Frontend Admin Panel defaults through TypoScript and backend user TSconfig
- Global Cowriter RTE preset and Visual Editor import-map preload middleware
- Shared Desiderio partial paths for EXT:news and EXT:blog
- Shared Solr defaults through `webconsulting/solr-defaults`, numbered
  pagination partials, and removal of the stale EXT:solr 14 jQuery include
- MCP table metadata that exposes `tt_address` as `Addresses`

## Site Sets

| Site Set | Dependencies | Purpose |
|---|---|---|
| `webconsulting/site-package` | none | Base defaults, Admin Panel, RTE preset, MCP table config, Cowriter preload middleware. |
| `webconsulting/site-package-search` | `webconsulting/site-package`, `webconsulting/solr-defaults` | Shared Solr integration and numbered pagination partial path. |
| `webconsulting/site-package-blog` | `webconsulting/site-package-search`, `webconsulting/desiderio-blog-standalone` | Blog standalone rendering with Desiderio templates. |
| `webconsulting/site-package-blog-bootstrap` | `webconsulting/site-package-search`, `blog/bootstrap-53` | Blog Bootstrap 5.3 demo rendering. |
| `webconsulting/site-package-camino` | `webconsulting/site-package-search`, `typo3/theme-camino` | Camino theme demo rendering. |

Desiderio corporate, content-element, Powermail, and news presets are provided
by `webconsulting/desiderio` and are attached directly in site configuration
rather than through additional wrapper Site Sets in this package.

All Desiderio-facing lab sites must keep the shadcn/ui page templates from
`webconsulting/desiderio-shadcnui-templates`. In this project that Site Set is
included through `webconsulting/desiderio-preset-corporate` or
`webconsulting/desiderio-blog-standalone`. The site package only adds shared
EXT:news and EXT:blog partial paths; it does not replace the shadcn/ui template
stack.

## Feature Inventory

| Area | Implementation |
|---|---|
| TYPO3 version | TYPO3 `^14.3`, PHP `^8.2`. |
| Site Sets | Five Site Sets covering base defaults, search, Blog, Blog Bootstrap, and Camino. |
| Admin Panel | `config.admPanel` TypoScript plus `admPanel.enable.all` in user TSconfig. |
| RTE | Global `cowriter` RTE preset. |
| Visual Editor | PSR-15 middleware that preloads `netresearch/t3-cowriter` JavaScript modules before Visual Editor persistence middleware. |
| Solr | `webconsulting/solr-defaults` Site Set dependency, numbered pagination partial path, and cleanup of the stale EXT:solr 14 jQuery include. |
| Desiderio | Shared partial paths for EXT:news and EXT:blog. Blog sites use `webconsulting/desiderio-blog-standalone`. |
| MCP | `tt_address` table metadata exposed as `Addresses`. |

## File Map

| Path | Purpose |
|---|---|
| `Configuration/Sets/SitePackage/` | Base Site Set, Admin Panel TypoScript, and Cowriter RTE Page TSconfig. |
| `Configuration/Sets/Search/` | Search/Solr Site Set, numbered pagination settings, stale EXT:solr 14 jQuery cleanup. |
| `Configuration/Sets/Blog/` | Blog standalone Site Set depending on `webconsulting/desiderio-blog-standalone`. |
| `Configuration/Sets/BlogBootstrap/` | Blog Bootstrap Site Set wrapper. |
| `Configuration/Sets/Camino/` | Camino Site Set wrapper. |
| `Configuration/RequestMiddlewares.php` | Registers the Cowriter preload middleware in the frontend stack. |
| `Configuration/Services.yaml` | Autowires `Webconsulting\SitePackage` classes. |
| `Configuration/user.tsconfig` | Enables Admin Panel access for backend users. |
| `Classes/Middleware/CowriterPreloadMiddleware.php` | Loads t3-cowriter JavaScript modules for Visual Editor edit mode. |
| `Classes/Bootstrap/McpTableConfiguration.php` | Registers MCP table metadata for `tt_address`. |
| `ext_localconf.php` | Bootstraps MCP table configuration. |

## Lab Sites Using These Sets

| Site configuration | Site Set dependencies from this package |
|---|---|
| `config/sites/camino` | `webconsulting/site-package-camino` |
| `config/sites/blog` | `webconsulting/site-package-blog` |
| `config/sites/14lts` | `webconsulting/site-package-blog-bootstrap` |
| `config/sites/typo3-blog` | `webconsulting/site-package-blog` |
| `config/sites/mtug-camp-munich-2026` | `webconsulting/site-package-search` |
| `config/sites/desiderio-corporate-starter` | `webconsulting/site-package-search` |

## Related Desiderio Sites

These lab sites combine Desiderio presets from `webconsulting/desiderio` with
or without Site Sets from this package:

| Site configuration | Additional dependencies |
|---|---|
| `config/sites/blog` | `georgringer/news`, `webconsulting/desiderio-news` |
| `config/sites/mattersburg-sights` | `webconsulting/desiderio-preset-corporate` |
| `config/sites/mtug-camp-munich-2026` | `webconsulting/desiderio-preset-corporate`, `desiderio/content-blocks-bundle` |
| `config/sites/desiderio-corporate-starter` | `webconsulting/desiderio-preset-corporate` |
| `config/sites/desiderio-corporate` | `webconsulting/desiderio-preset-corporate` |
| `config/sites/desiderio-websites` | `webconsulting/desiderio-preset-corporate` |
| `config/sites/typo3-vienna-camp-2026` | `studiomitte/friendlycaptcha`, `webconsulting/desiderio-powermail`, `webconsulting/desiderio-content-elements`, `webconsulting/desiderio-preset-corporate` |
| `config/sites/eurovision2026` | `webconsulting/desiderio-preset-corporate` |

## Removed Packages

The following local packages were consolidated into this package and removed:

- `packages/adminpanel_defaults`
- `packages/visual_editor_defaults`

Composer now only needs `webconsulting/site-package` for these shared project
defaults.

## Thanks

Special thanks to Netresearch DTT GmbH for `netresearch/t3-cowriter`, which is
used by the Cowriter RTE preset and Visual Editor preload middleware. Thanks
also to the TYPO3 core team and the maintainers of Solr, Visual Editor, News,
Blog, Desiderio, Camino, and the other extensions listed in the root README.

## Documentation

Detailed TYPO3 documentation lives in `Documentation/`. Start with
`Documentation/Index.rst`.

## Verification

After changing this package, run the project-level checks from the repository
root:

```bash
composer validate --no-check-publish
Build/Scripts/runTests.sh -s phpstan
Build/Scripts/runTests.sh -s ci
ddev exec vendor/bin/typo3 lint:yaml config/sites packages/site_package/Configuration/Sets
ddev exec vendor/bin/typo3 extension:setup
ddev exec vendor/bin/typo3 cache:flush
```

PHPStan runs at `level: max` with the TYPO3-specific
`saschaegerer/phpstan-typo3` extension.
