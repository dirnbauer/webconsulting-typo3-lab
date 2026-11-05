# Sol integration handoff — 2026-11-05

Implementation worker: GPT-6.1 Sol, extra high. No children, tags, pushes, external deployment or production writes. This is a local integration candidate, not a release authorization.

## Source and runtime

- WT lab base carried `458a598`, `f5c52b0`, `cb19cb2`; parent commit `84ad3b7` is preserved.
- MAIN lab stays `7952f99`. Its seven previously synchronized site_package files remain uncommitted and were not reset or added to MAIN commits.
- VEE: reviewed `252cdaa3cab22656783029fcf740ddfe88577fd2` plus local fix **`dce1e28d0da99eaa0c527e93a808847df3b4e296`**.
- Desiderio: reviewed `f39b3b3d` plus local fix **`4091ffdcab69aa404ff38b1a036be012d41eb9b7`**.
- Both package repositories were initially clean and are clean after these scoped commits.
- Regular MAIN vendor links now resolve to those two package clones through `Build/Scripts/lab-link.sh`. Existing `webconsulting/webcon-templates` link is preserved (`fe4b9c1dab73707046ff60762f42123de862b003`). `.lab-links` contains these three packages.
- Composer installed metadata deliberately still says Desiderio **v4.29.0**, VEE **v1.4.4**, templates **v1.0.3**; these are development links, not released lock updates. Desiderio clone metadata remains 4.30.0; VEE documentation remains an unreleased candidate.
- Root composer.lock SHA256 before/after: `cfc0e94d9daa6d3ed7ef65de2194ede8f9ad1052406a82501a9b7fc839dce912`. No root install/update or lock edit.
- Activation used lab-link, Mutagen synchronization before the container Composer autoload rebuild, container `/tmp/opcache/*` clearing and TYPO3 cache flush. Reflection resolves the showcase class to `/var/www/html/packages/desiderio/...`; browser markup and middleware load the linked VEE assets. No auto_prepend_file, temporary class mapping, vendor patch or global XCLASS was used.
- VEE's ignored package-local development lock/vendor were resolved normally with `composer update typo3/cms-workspaces --with-dependencies --no-interaction`: one install, Core Workspaces 14.3.10, no unrelated updates. `composer validate --strict` passes. The root package installation was not touched.

## Defensive fixes and product corrections

Read the provisional Astra workspace report and binding parent decisions. Its provider-blocked final response was not restarted or treated as an accepted independent review. Diagnostic probes were not counted as acceptance tests.

1. The generic guard validates every command envelope before any datamap runs. Move/copy accepts integer targets or the explicit paste/action/target/update shape; nested updates pass the same relation/ownership checks. Unknown commands/shapes/tables are refused. Tests cover move/copy, feature on/off, cross-parent/shared reassignment and byte-identical source/target/child rows on rejection.
2. Existing collection-owned records resolve actual ancestor edges from stored TCA ownership/table/match fields, then compile and authorize each resolved owning relation and ancestor. Ambiguity, cycles, wrong discriminators, inaccessible/deleted/foreign-workspace/language/editlocked ancestors, excluded/readonly/disabled owning fields and excessive depth refuse access. Generic child-field saves use this same boundary. Positive nested and ordinary child writes remain covered.
3. Ordinary tt_content NEW placement and normal copy/move/paste/delete remain usable when TCA contains inline-to-tt_content relations. Explicit owner reassignment still requires the backend/structural path. Independently owned existing custom-table rows are not denied commands merely because their table can also be an inline child. Arbitrary new custom collection-table creation remains outside the generic route.
4. `overrideChildTca` is explicitly unsupported, with a backend fallback and no mutation. Child-default, nested-required and readonly override cases are tested. Existing-child context access also refuses such overrides rather than silently compiling standalone global defaults.
5. Removed the relation factory override. `InlineDataHandler` is now an empty scoped stock-Core subclass for the existing guarded instance/test boundary. Maintained repeated-draft tests pass with strict live-byte preservation, including MariaDB. First frontend auto-versioning remains refused; no live metadata normalization exception or SQL restoration was introduced.
6. Translation refusal follows Core's deleted/workspace restrictions and workspace overlay, ignoring irrelevant deleted/foreign-workspace/delete-placeholder rows. Active connected translations and unverified populated cascades remain refused. Newly created untranslated leaves can be removed above min; this does not imply broad translated/cascading delete support.

Operational labels now use the existing middleware-exported TYPO3 EN/DE catalogue: busy/pending/visibility, all refusal reasons, immediate-save context, count/limits, status/recovery, confirmation consequences/buttons, index fallback, new-entry default and unknown stored selects. Both catalogues have 88 unique matching units. Unknown select values remain disabled and explicitly unavailable.

Real browser integration exposed the wrong Severity import and frontend-only imports of backend label modules. Confirmation now uses the same-origin backend's initialized Core Modal/Severity API, with a supported dynamic import/native fallback. The adapter settles on `typo3-modal-hidden`. The chooser restores focus after its busy controls are enabled again. A newly created hidden entry receives focus on its Item settings control after refresh. No automatic unhide or automatic save was added.

Definitive 403/404/409 responses refresh available choices and restore controls without retrying writes or reloading the page. Unknown/network/server failures remain blocked until refresh. A manually exercised stale revision refreshed choices and a deliberate second click created exactly one row. Wrapper refresh still checks pending/invalid changes before replacement.

Desiderio's shared `logo-item` retained one ignored `File.required`; changed to `minitems: 1` after the activated audit found 187 shared logo parents with no missing files. Added RecordTypes schema coverage so the previous ContentElements-only schema test cannot miss this family again.

## Runtime content and TCA

Showcase page **8430**, translations **8431–8433**, parent 1093: preserved, no full seed or translation pass. There remain 23 CEs per language, nav_hide=1/no_search=1. No targeted reseed was needed.

Normal runtime CLI/TCA evidence:

- Tabs `desiderio_tabs_items`: minitems **2**.
- Three-tier `desiderio_pricingthreetier_plans`: minitems=maxitems **3**.
- Gallery item `image`: minitems **1**, maxitems **1**.
- Shared logo image: minitems **1**.
- `content-blocks:lint` now reports **19 existing Innesto `keywords` schema errors**, no Desiderio errors. The project-wide command is red and is not reported as passed.
- Initial activated limits audit: 184 groups, no violations. The final audit/cleanup result is recorded below.
- 603 unrelated tt_content PID aggregates (COUNT, SUM(uid), SUM(tstamp), SUM(deleted)) are byte-identical to the previous showcase worker's snapshot when excluding 8430 and the disposable test PID 8434. Source showcase children were only read; structural browser writes targeted disposable PID 8434.

## Checks and raw evidence

Raw evidence directory: `/tmp/ve-integration-evidence/`. Use final named runs, not earlier failing harness/debug runs. Testing used PHP 8.5.12, Core 14.3.10, Content Blocks 2.4.10, MariaDB 10.11.

- VEE unit: **75 tests / 140 assertions**, passed.
- VEE JavaScript: **52 tests**, no failures/skips.
- VEE functional final, MariaDB: **50 tests / 260 assertions**, no failures/skips, two existing Core/vendor deprecations. Includes stock-Core repeated workspace writes and all new defensive regressions. Normal SQLite run also passed before the final assertion-only extension; no autoload injection was required.
- VEE PHPStan: no errors; PHP lint passed; CGL: 0/56 files; `git diff --check` clean; Composer strict validation passed.
- VEE browser component suite: **12 passed**, real Lit/store but isolated backend/modal adapters. These are explicitly separate from actual Core Modal proof.
- Desiderio unit: **1,104 / 55,109 assertions**, passed; PHPStan no errors; CGL 0/239 files.
- Desiderio functional final: **141 / 3,639 assertions**, no failures, **3 existing skips**, one existing PHPUnit configuration deprecation. No existing test was weakened or newly skipped.
- Anonymous/language/login regression after real package activation: **12 passed**, four language pages on desktop/mobile and EN/DE login checks including Axe. `anonymous.txt`.
- Actual privileged browser results and precise remaining coverage are recorded below after the final run.

Reproducible opt-in real-runtime suite and Core fixture helper are in `Tests/Integration/VisualEditor/`. They require MAIN's normal installed runtime and a disposable backend session, and are outside the anonymous E2E project's discovery. The worktree has no node_modules; the source was run from a temporary copy linked to MAIN's existing Playwright dependencies, with no dependency installation in MAIN.

Representative commands (all DDEV commands from MAIN):

```sh
Build/Scripts/lab-link.sh webconsulting/visual-editor-enhancements
Build/Scripts/lab-link.sh webconsulting/desiderio
ddev mutagen sync
ddev exec bash -c 'rm -rf /tmp/opcache/*'
ddev composer dump-autoload
ddev exec vendor/bin/typo3 cache:flush
ddev exec vendor/bin/typo3 content-blocks:lint
ddev exec php packages/desiderio/Build/Scripts/audit-collection-limits.php

# VEE package, host PHP 8.5
Build/Scripts/runTests.sh -s unit
Build/Scripts/runTests.sh -s js
Build/Scripts/runTests.sh -s phpstan
Build/Scripts/runTests.sh -s cgl
typo3DatabaseDriver=mysqli typo3DatabaseHost=127.0.0.1 \
 typo3DatabasePort=32877 typo3DatabaseName=db typo3DatabaseUsername=root \
 typo3DatabasePassword=root Build/Scripts/runTests.sh -s functional
# Testing framework creates isolated db_ft... databases; these are DDEV defaults.

# Desiderio package
DESIDERIO_RUN_ON_HOST=1 Build/Scripts/runTests.sh -s unit
DESIDERIO_RUN_ON_HOST=1 Build/Scripts/runTests.sh -s phpstan
DESIDERIO_RUN_ON_HOST=1 Build/Scripts/runTests.sh -s functional
```

## Release boundary

No release yet. Parent must assess these fixes and the provisional-review disposition before tagging/updating the lab lock/publishing. First workspace auto-versioning, connected translation structure, media/MM/special relations, child overrides and unproven cascades retain explicit backend fallbacks. Existing translated source showcase items mostly demonstrate refusal messaging; supported direct deletes are demonstrated on fresh untranslated disposable leaves.

The regular-runtime suite's library compatibility case exercises real authenticated create/copy/paste-move persistence and anonymous rendering. It does not establish catalog-tile drag/drop behavior. Translated workspace-draft editing across multiple simultaneous language frames, every Content Block variant and full media editing are not exhaustively demonstrated. Do not market this as universal collection/workspace coverage.

## Final real-browser acceptance and cleanup

Final run: **8/8 passed in 2.5 minutes** (`browser-final.txt`, trace/screenshots directory `browser-final/`). It used actual Core routes, MariaDB and normal linked packages:

1. Live hidden create; new Item settings focus; ordinary child text persistence keeps hidden=1; Cancel/Escape/header-dismiss produce zero POSTs and unchanged deleted flag; Cancel default focus and return focus; confirmed Core removal.
2. Nested Item settings/Back; create and delete a new leaf with the correct row owner; parent membership preserved.
3. Restricted German editor, ordinary chooser disabled and independent collection preference on; actual create stays hidden; out-of-mount field discovery returns 403.
4. Admin **and** restricted editor, sections mode in workspace 5: two create/discard cycles each on complete Core-provisioned drafts; all fixture live tt_content/child rows byte-identical after the cycles. An unversioned tab parent remains refused with the explicit first-version backend fallback. Core fixture provisioning occurs before this snapshot and is not counted as first-version frontend acceptance.
5. Actual tabs and sections; real contenteditable typing stages pending header text; structural actions stay disabled before Save; the real backend Save persists it, rendering retains it, then the test restores the original header.
6. Activated source showcase returns the connected-translation deletion fallback, three-plan min=max=3 limit and required-gallery-media fallback.
7. Ordinary authenticated library persistence: NEW text CE, copy, paste move with colPos, anonymous rendered text, then normal root CE deletion. Catalog tile/drag UI is not asserted.
8. A newly created hidden entry is opened in **real Core FormEngine**, its textarea edited and Save pressed; DB remains hidden=1 with the changed title. This is in addition to the endpoint-level child save case.

Structural controls were activated with actual keyboard events after waiting for the enabled state. Chooser entry points were invoked through their rendered DOM buttons in the automated suite; shared preview interaction additionally exercised real backend UI, DE nested settings, Core confirmation and a stale refusal. Component mocks are not used as Core Modal evidence. Initial red integration runs exposed modal/focus bugs and several test-harness issues (offscreen synthetic anchors, custom-element visibility, Save being the host button and distinguishing a preceding delete response from create); only the final corrected run is acceptance evidence. No mutation request was automatically retried.

Disposable records: page **8434**; admin **17**, editor **18**, group **7**, workspace **5**. Initial tt_content **200301–200306**; Core parent draft **200307**; library fixture CEs **200308–200311**. Tabs **941,942**; feature_list_items **1685,1686**; checklist live/draft/new IDs were within **4634–4669** (discarded NEW workspace rows can be physically absent); legal link **1202**; data column **1690**, data row **1686**, nested cells **5150–5157**. Full surviving-row inventory before cleanup is `fixture-records-before-cleanup.tsv`; response traces retain discarded action identities.

Cleanup used stock Core discard on test drafts, then Core page/fixture deletions. Page, group, workspace and both users are deleted; both user disable flags were explicitly verified/set to 1 for the disposable identities after deletion. Workspace IDs are restored to 0, their sessions are removed, and private generated credential/auth-state files were deleted. Core cleanup reports **zero active page-owned rows**. The seven previously synchronized MAIN site files remain the only tracked MAIN modifications. Both package repos are clean. The root lock hash is unchanged. Both shared preview tabs opened for this task were closed.

The final limit-audit counts and lab evidence commit follow in the final verification note. The candidate retains the release boundaries above; no tag/push/deploy occurred.

Final verification note: post-cleanup activated audit **184 groups / 5,063 parent checks / 0 violations** (`limits-final.json`); zero active fixture page-owned rows and zero fixture sessions; original showcase language counts **23/23/23/23**. Unrelated PID aggregates remain byte-identical after cleanup. The full lab evidence is committed in the WT commit containing this handoff; MAIN remains at its original lab HEAD. Release readiness is **reduced-scope local candidate verified, full-concept/release acceptance held for the parent**, with explicit unsupported paths and the 19 unrelated Innesto lint errors retained above.
