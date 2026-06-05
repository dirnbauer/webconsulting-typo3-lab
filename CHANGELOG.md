# Changelog

All notable changes to Webconsulting TYPO3 Lab are documented in this file.

## v1.1.0 - 2026-06-05

Minor release aligning documentation, release metadata, and site-package
maintainability with the current TYPO3 14.3+ lab state.

### Added

- Documented Desiderio corporate starter, corporate, and website-types demo
  sites in the root README and site package documentation.
- Documented `webconsulting/docx-editor`, `studiomitte/friendlycaptcha`, and
  current Desiderio Site Set dependencies in the public README.

### Changed

- Bumped `webconsulting/site-package` to `14.1.0` with aligned Composer
  metadata in the root project and path repository.
- Rewrote `packages/site_package/README.md` and
  `packages/site_package/Documentation/` against the implemented Site Sets,
  middleware, and configured lab sites.
- Updated the root README demo-site inventory, extension inventory, Site Set
  dependency table, local URLs, and smoke-check commands.
- Simplified MCP table registration in `packages/site_package/ext_localconf.php`
  by removing redundant nested array guards.

### Removed

- Removed stale documentation references to the non-existent
  `webconsulting/site-package-desiderio-corporate` wrapper Site Set.
- Removed outdated `blog/standalone` dependency references in favor of
  `webconsulting/desiderio-blog-standalone`.

## v1.0.0 - 2026-05-25

Initial public release of the Webconsulting TYPO3 Lab repository.

### Added

- DDEV-powered TYPO3 14.3+ lab setup for Visual Editor, workspace editing,
  API-driven content workflows, Desiderio rendering, Records List Types demos,
  Solr search, and News API Studio.
- TYPO3 14.3+ local site package with Composer release metadata, shared Site
  Sets, editor defaults, Solr defaults, Admin Panel defaults, and Visual Editor
  Cowriter integration.
- Project-level quality workflow for Composer validation, PHP linting, TYPO3
  YAML linting, and PHPStan.

### Changed

- Aligned public project metadata, README copy, package metadata, and GitHub
  repository identity with `webconsulting-typo3-lab`.
- Declared the repository license as GPL-2.0-or-later, the TYPO3-compatible
  license used by the project.

### Removed

- Removed the obsolete root screenshot asset and legacy promotional README
  footer.
- Removed legacy `ext_emconf.php` metadata from the local site package in favor
  of TYPO3 14.3+ Composer metadata.
