# Changelog

All notable changes to Webconsulting TYPO3 Lab are documented in this file.

## Unreleased

### Overhaul — 2026-09-19

Platform

- nr-llm 0.35, nr-vault 0.16, EXT:solr 14.0.2 against Apache Solr 10.0.0
  (the production Solr image follows the lock), Cowriter 3.6.8,
  sg-apicore 3.1.2, Powermail 14.0.3, Visual Editor 1.10.2, PHPUnit 13.3.4.
- `hn/typo3-agent` is gone together with the site_package tool-converter glue,
  its settings, its Coolify variables and its documentation. The backend chat
  is `webconsulting/typo3-shadcn-ui`, which is also the shadcn/ui base other
  backend modules build on: the chat rail sits left, the module's own React
  app right, and the model reaches this installation's MCP tools in-process
  through nr-llm's agent runtime, with every write stopping for approval.
  `typo3-ai-chat` is archived.
- The FriendlyCaptcha and Solr-pagination forks stay. Upstream pagination has
  no TYPO3 14 release at all, and upstream FriendlyCaptcha 2.3.0 declares 14.3
  but its Powermail validator never resolves the Extbase lazy proxies
  Powermail 14 hands it. Both were switched to upstream, measured and switched
  back the same day.
- The Page module worked again only after a patch: `inline-page-module` 4.0.2
  is the newest release and predates the core patch level that appended a
  sixteenth constructor argument to `PageLayoutController`.
- Every repository we maintain now has a GitLab mirror; the German core
  language pack is installed; skills come from `dirnbauer/typo3-skills` alone
  and are indexed into Solr on the six sites that carry the skillflow set.

Own extensions, all reviewed against the thermo-nuclear standard, PHPStan
level 8 with no baseline, tests and one CI workflow each

- **typo3-shadcn-ui 1.0.1** — new. The shadcn/ui runtime, the AI chat in three
  places and a components module that proves the contract by being built as an
  external app. Its review found admin-only third-party MCP tools being offered
  to every backend user.
- **typo3-abilities 1.2.0** — the registry became the catalogue of everything
  this installation can do: 342 entries across 202 console commands, 64 skills,
  57 MCP tools, 13 abilities and 6 REST endpoints, each with its input schema,
  its annotations and how to call it from every surface. Reaction and Fluid
  surfaces joined CLI, REST, MCP, the module and the JavaScript client. The
  vocabulary now follows the WordPress Abilities API: *ability* for a unit of
  functionality, *capability* only for the MCP manifest's permission gating.
- **desiderio 4.2.0** — outline buttons and badges had rendered a transparent
  border everywhere, because the shadcn base class beat the variant in every
  Fluid render; 112px of nothing above the fold on mobile is gone; the atomic
  design allowlist shrank from 16 entries to 8.
- **astryx-typo3 2.1.1** — upstream refresh to v0.6.2, 91 `a:` components grew
  to 189, and 30 content elements stopped rendering a second `h1` because the
  heading contract only ever implemented half of itself.
- **agent-nexus 3.1.0** — a real protocol hub, reading order fixed on every
  protocol page, and six backend modules that had been invisible to anyone
  working in a draft workspace.
- **records-list-types 1.2.0** — labels that rendered raw references because
  two referenced core label ids do not exist; TYPO3 v14 system columns
  localised; a real empty state with a way out.
- **webcon-easy-workspace 1.5.0** — the change counter follows a save made
  inside a module iframe within seconds instead of waiting out a 45-second
  poll.
- Also released: records-list-examples 1.4.0 (its partials had been shadowing
  the parent extension's own views), visual-editor-enhancements 1.1.0,
  innesto 2.2.0, llms-txt 1.1.1, skillspector 1.1.0, mcp-server 0.8.0,
  agentation 1.3.0, docx-editor 1.4.0, workos-auth 2.2.0, x402-paywall 1.3.0,
  skillflow 1.6.2, image-workbench 0.2.1, and outside the lab pw_teaser 8.1.0
  and typo3-camino-vercel.

### After the overhaul — 2026-09-19 to 2026-09-20

New in the lab

- **Jev decisions in forms.** `webconsulting/webcon-jev` (0.1.11) routes and
  gates Powermail submissions on typed answers from the Jev API, with
  `in2code/powermail_cond` from our TYPO3 14 fork driving the conditions. The
  first runs against the live service found three faults the fallback path
  could never show, among them a confidence threshold that withheld a note
  from exactly the applicants it was written for. The desiderio site carries
  the powermail-cond set and its `condition.json` route type.
- **Downloads page.** A sanitised database and Fileadmin snapshot is published
  behind the lab's basic auth, as zip archives with an installer.
- easy workspace 1.6.1: the toolbar badge counts the current page or article,
  and `/has-changes` answers truthfully; on v14 it had answered `false` for
  every context, because its guard asked TCA for columns v14 no longer lists.
- `apps/news-api-studio` is gone, together with its CI step and documentation.

Deployment

- Both new packages resolve from their repositories. They had entered as path
  repositories under `packages/`, which is untracked, so a fresh clone, CI and
  the Coolify build could none of them have resolved either one.
- An unscoped StaticFileCache rule in `public/.htaccess` stamped
  `Content-Encoding: gzip` on every `.gz` in the document root, so clients
  silently decompressed downloads into files their names no longer described.
  It is anchored to the two filenames StaticFileCache writes.
- Coolify injects only what the compose file declares. `TYPESAFE_API_KEY` and
  the entrypoint's own switches (`TYPO3_RUN_SETUP`, `TYPO3_FLUSH_PAGE_CACHE`,
  `TYPO3_LOG_MAX_BYTES`) were documented but never reached the container; all
  are declared now.
- nr-vault's provisioning user is set in `settings.php.example`, because the
  image regenerates `settings.php` from it on every deploy and `config/` is
  not a persistent volume. The Jev token installs through that user while CLI
  vault access stays off.
- The container releases stuck chat conversations and applies retention on
  every start; there is no cron daemon to run a scheduler task.
- The lab is sized for the host it shares with Coolify: bounded Apache, MariaDB
  and Solr memory, keep-alive off, security headers on every response.
- The CI deploy job no longer reports a deployment that never happened.
  Coolify's IP allowlist refuses GitHub's runners, so deployments stay manual
  through `Build/Scripts/sync-coolify.sh deploy`.
- `docs/shadcn-ui.md` documents the backend chat; two documents had linked to
  it before it existed.

### Maintenance — 2026-09-06

- Set TYPO3 14.3.6 as the minimum core version; the lab was already running
  that latest stable release.
- Simplified Cowriter import loading and moved the address icon override into
  TCA configuration. Removed obsolete MCP registration, an unused skill hash,
  the unselected Blog Bootstrap Site Set, legacy Skillspector metadata, retired
  patch experiments and their unused functional-test framework.
- Removed the unused root shadcn CLI and its 210 npm packages. Updated the
  vulnerable URI/query parsers in all three npm locks and aligned News API
  Studio's Tiptap packages on 3.31.3.
- Added both local packages to PHP linting and PHPStan on PHP 8.4, wired their
  PHPUnit tests into the quality runner, and added the Composer audit gate.
- Consolidated the site-package manual into its README and corrected the
  bootstrap, database distribution, active Site Sets and Fluid Styled Content
  documentation.
- Applied Cursor's thermo-nuclear code-quality review: removed Skills
  Inspector's unused import-refresh and attachment paths, file classifier and
  report helpers. Fixed scanner directory isolation and cleanup after failed
  preparation, with regressions exercised through a local process fixture.
- Aligned the skills-only Solr filter with the nr_llm record type used by the
  current index queue.

## v2.0.0 - 2026-08-06

### Added

- Added the external Astryx for TYPO3 showcase and its own site configuration,
  template tree and seed command.
- Added the official `vite-plugin-typo3` and Vite Asset Collector build,
  together with the official `s2b/ddev-vite-sidecar` add-on.
- Added a Playwright and axe-core regression harness for Desiderio, Powermail,
  Blog and Astryx on desktop and mobile viewports.
- Added shared stdio MCP configuration and setup documentation for Codex,
  Claude Code and Cursor.
- Added a Desiderio WorkOS frontend plugin lab with semantic shadcn token CSS
  and idempotent demo-data seeding.

### Changed

- Updated the lab to TYPO3 14.3.5, PHP 8.4, DDEV 1.25.3, Node.js 24 and Solr
  10.0.0; both installed DDEV add-ons are pinned to their current releases.
- Updated Desiderio to 4.x, Innesto to 2.x, Astryx to 1.x, Agentation to
  1.1.5, Flue to 0.2, WorkOS Auth to 2.0 and the remaining direct Composer and
  npm dependencies to their supported current constraints.
- Normalized owned Composer packages, PHP namespaces and GitHub sources to
  `webconsulting`, `Webconsulting` and `github.com/dirnbauer` respectively.
- Split Desiderio, Astryx and lab-specific Classic Content wrappers into
  independent template trees while retaining shadcn-inspired styling.
- Made compiled Vite assets the reliable default and HMR an explicit local
  opt-in, without custom manifest readers, asset ViewHelpers or server probes.
- Updated all seed workflows to exclude video content by default while keeping
  the explicit video-generation capability.
- Rewrote the root README and operational documentation around the current
  architecture, Site Sets, DDEV services, MCP clients and validation workflow.

### Removed

- Removed the embedded predecessor theme package and its obsolete site
  configuration after extracting Astryx into its own versioned repository.
- Removed the root Fluid Styled Content dependency and renamed the remaining
  lab-owned compatibility wrappers to Classic Content.
- Removed the local Agentation Composer patch after publishing the fix
  upstream.
- Removed all seeded video content elements, database file records and physical
  video files from the lab.

## v1.3.0 - 2026-06-06

Minor release documenting curt.at-hosted fileadmin bootstrap and tightening DDEV
install documentation.

### Added

- Documented curt.at as the public host for the fileadmin archive
  (`https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz`, ~122 MB) with
  server path, `curl` download, and SCP upload instructions in
  [docs/ddev-bootstrap.md](docs/ddev-bootstrap.md).

### Changed

- Integrated curt.at download into first-time DDEV setup in README and
  `docs/ddev-bootstrap.md` (database from git, fileadmin from curt.at).
- Updated `docs/README.md` and `docs/site-configuration.md` to reference the
  hosted fileadmin URL.

## v1.2.0 - 2026-06-06

Minor release cleaning up site configurations and database state, and documenting
reproducible DDEV bootstrap with database and fileadmin exports.

### Added

- Added [docs/site-configuration.md](docs/site-configuration.md) as the
  canonical guide for `config/sites/` inventory, conventions, and
  troubleshooting (duplicate root pages, orphaned configs, autogenerated stubs,
  workspace staging, MCP `localUnsafeMode`, Site Set memory exhaustion).
- Added [docs/ddev-bootstrap.md](docs/ddev-bootstrap.md) for database and
  fileadmin export/import (`dump.sql.gz`, `.tarballs/fileadmin.tar.gz`).
- Refreshed `dump.sql.gz` database snapshot after TYPO3 record cleanup.
- Added `.tarballs/README.md` for local fileadmin archive conventions.

### Removed

- Removed the time-stamped Site Set memory exhaustion report
  (`docs/reports/typo3-site-set-memory-exhaustion-20260601.md`).
- Removed orphaned site configurations (`desiderio-corporate`,
  `desiderio-websites`, `eurovision2026`, `mattersburg-sights`, `main`) whose
  root pages no longer exist.
- Purged soft-deleted pages, content elements, and orphan records from the lab
  database (`cleanup:deletedrecords`, `cleanup:orphanrecords`).

### Changed

- Renamed `autogenerated-390-*` to `typo3-blog` and gave `blog` and
  `typo3-blog` clean base URLs (`/blog/`, `/typo3-blog/`).
- Updated `mtug-camp-munich-2026` to root page `933` with four languages,
  error handling, and Desiderio dependencies; moved Desiderio tokens to
  `settings.yaml` only.
- Updated `typo3-vienna-camp-2026` Desiderio preset tokens (`lucide`, `lagoon`).
- Aligned root README, `docs/README.md`, and site package documentation with
  the seven active demo sites.
- Updated first-time DDEV setup to import fileadmin via `ddev import-files`.

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
