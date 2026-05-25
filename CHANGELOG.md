# Changelog

All notable changes to Webconsulting TYPO3 Lab are documented in this file.

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
