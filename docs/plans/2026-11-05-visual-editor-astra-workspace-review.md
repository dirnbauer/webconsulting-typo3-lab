**Astra concept/security review — committed baseline, 2026-11-05**

Reviewed VEE **`252cdaa3cab22656783029fcf740ddfe88577fd2`**, not the integrator's changing working tree. Read the authoritative execution concept and both Sol handoffs. Package paths/line numbers below refer to that exact commit, retained at `/tmp/vee-astra-review/baseline/`. Core references are the installed MAIN `vendor/typo3/cms-core` / `cms-backend` sources, version 14.3.10; VE is 1.10.4. No package, runtime, editorial data, accounts, dependencies, links, branches or runtime caches were changed. Tests used copied dependencies and isolated SQLite installations under `/tmp`; showcase SQL was SELECT-only. No implementation or publication performed.

**Recommendation: hold integration acceptance for findings 1–4; obtain the parent's explicit invariant decision for 5.** Finding 6 is a substantial product limitation plus an unnecessarily broad refusal, not evidence that arbitrary cascading deletes should be enabled.

1. **Blocking: nested paste updates bypass the structural guard and can steal children.**

   `Classes/Service/InlineGenericSaveGuard.php:51–75` checks datamap fields but does not inspect command values. `Classes/Service/DataHandlerService.php:84–87` validates only command names. Core `DataHandler.php:3270–3287,3312–3330,3397–3400` accepts `move`/`copy` with `action=paste` and executes `update` as another datamap. Thus this is another write entrance, not just command metadata.

   Reproduction: with the collection preference **off**, a valid authenticated save containing the following returned HTTP 200/success and changed child 9's owner **2 → 1**, discriminator **shared → items**, sorting **100 → 1**, and timestamp. The collection revision, membership, feature, limits, and parent-relation checks were never reached:

   ```json
   {"cmdArray":[{"tt_content":{"1":{"move":{"action":"paste","target":666,"update":{"vee_items":"9"}}}}}]}
   ```

   Evidence: `/tmp/vee-astra-review/paste-bypass.json`, `paste-run.txt`, `ReviewProbeTest::probePasteBypass`. This proves bypass of VEE's relation policy; it is not a claim that an unauthenticated caller bypasses TYPO3 authentication.

   **Minimum fix:** validate the complete command shape and apply the same field/ownership checks to every allowed `paste.update` before executing any command. Reject unsupported nested shapes. Preserve legitimate paste fields such as `colPos` through an explicit, permission-checked path; do not blanket-disable ordinary move/copy.

   **Required tests:** both move and copy paste envelopes; cross-parent and shared-table child theft; limits/revision bypass; feature on/off; legitimate library copy/move; rejected payload leaves both original and prospective target rows unchanged.

2. **Blocking: direct nested parent IDs bypass ancestor authorization.**

   `InlineRecordService.php:338–358` compiles only the submitted parent. `EditorRecordContext.php:30–58` checks that record and its page, not its owning collection. In contrast, `Classes/Middleware/FieldOptionsMiddleware.php:80–107` walks the supplied context for nested reads. `InlineIntent.php:16–18` has no equivalent write context, and nothing derives a trusted ancestor chain from ownership columns.

   Reproduction: a real restricted fixture user with `tables_modify=tx_vee_item` and **no tt_content modification permission** successfully creates a nested entry under item 1, which belongs to `tt_content:1`. Response 200, new row persisted. Evidence: `nested-ancestor.json`, `probeNestedAncestor`. Existing test `InlineRecordTest.php:255–268` proves owner assignment only; it uses an admin and does not prove ancestor authorization.

   **Minimum fix:** for collection-owned parents, resolve actual owners from TCA metadata and stored foreign/match/table fields; authorize each edge and ancestor with resolved field configuration, record/page/language/workspace rights and collection policy. A client context can identify an intended path but must be verified against stored membership. Handle ambiguity and cycles by refusing. Apply corresponding owner authorization to generic child-field saves if those fields are exposed through the scoped collection UI. Keep independently editable records explicitly distinguished from collection-owned records.

   **Required tests:** root table denied, root field excluded/readOnly/disabled, root editlock, forbidden language, foreign/deleted workspace ancestor, wrong shared discriminator, cyclic/ambiguous owner, and permitted nested writes. Fetching a revision must not itself create an alternate path around the same ancestor checks.

3. **Blocking compatibility regression: one inline-to-tt_content relation breaks ordinary library creation globally, including feature OFF.**

   `InlineGenericSaveGuard.php:17–47` unions child ownership fields across all TCA types. As soon as any inline relation uses `foreign_table=tt_content`, it marks `tt_content.pid`, sorting and language fields as protected. The `tt_content` NEW exception at line 56 does **not** exempt its required `pid` from lines 59–61. The controller always invokes this guard (`PersistenceController.php:65–70`).

   Reproduction: add a legitimate inline relation with `foreign_table=tt_content, foreign_field=tx_container_parent`; disable the preference; ordinary `NEWlibrary={pid:666,CType:text,header:Library}` now returns HTTP 400, “Collection ownership is managed by the server.” Evidence: `container-guard.json`, `probeContainerGuard`. This is a configuration-dependent regression, not a claim that this relation exists in every installation. The committed compatibility test at `InlineRecordTest.php:89–99` never installs such a relation. All commands on other potential child tables are also denied table-wide at lines 68–69, including independently owned rows.

   **Minimum fix:** distinguish ordinary element creation/placement from a specific record's inline owner assignment. Preserve required NEW `pid` and supported container placement through validated normal CE operations. Derive actual relations and operation intent instead of treating every row in a table as owned by every possible parent. Do not simply turn off the whole guard with the preference, which would reopen structural bypasses.

   **Required tests:** new/copy/move/delete for normal CEs with an inline-to-tt_content TCA relation present, nested containers, ordinary child field edits, feature off, and rejected forged owner assignments. Root CE deletion/copy remains an ordinary operation; its Core-managed descendants are not by themselves a collection-guard bypass.

4. **Blocking for generic support: accepted `overrideChildTca` is ignored, permitting creation below required child limits.**

   `InlineRecordService.php:169–178` does not reject `overrideChildTca`. Existing child compilation at line 88 and defaults compilation at lines 248–280 are standalone; neither receives the parent relation's inline configuration. `EditorRecordContext.php:54–58` likewise has no inline parent context. Core explicitly supplies `inlineParentConfig` in `cms-backend/.../TcaInline.php:340–375`, and `InlineOverrideChildTca.php:79–117` applies the overrides.

   Reproduction: parent relation overrides child `title.default="Parent title"` and nested collection `minitems=1`. Proper Core inline compilation reports that title and minimum. VEE advertises `canCreate=true`, returns 200 and creates **title="New entry", nested=0**. Evidence: `child-overrides.json`, `extra-run.txt`, `probeChildOverrideDefaults`.

   **Minimum fix:** refuse this special relation configuration until existing-child discovery and new defaults compile with the verified inline parent context; alternatively implement that context throughout. Use resolved child configuration for writable/default/required decisions, not only global `$GLOBALS['TCA']` (`mayWrite`, lines 325–329). Do not fill mandatory nested/media values with invented data.

   **Required tests:** parent child-default overrides, required nested/file override, readOnly override, shared child table with two different parent configurations, and no write on fallback. This is not a request to reimplement all FormEngine constraints.

5. **Important concept decision: first workspace creation is possible through Core, but exact live-byte identity is not met; the scoped override is unjustified for the installed supported path.**

   The worker's deleted diagnosis is now reproduced and retained. With two source children sorted `10,30`, both stock Core `version=new` and the baseline scoped handler produce live sorting `1,2` and `tstamp=EXEC_TIME`. The parent row, child ownership/discriminators/content, and ordered live child UIDs remain unchanged in these fixtures. A connected parent/child translation fixture also preserves the translated live child. Artifacts: `core-version.json`, `scoped-version.json`, `core-version-translated.json`; the actual before/after rows are retained, not merely a denial assertion.

   **Source chain:** Core `DataHandler.php:4069–4080` deliberately keeps live child IDs while versioning children; `copyRecord_raw:3836` passes the copied parent through `insertNewCopyVersion:3865–3882`, whose `checkValue` registers relation writes. `process_cmdmap:3405–3406` processes remapping. `checkValue_inline_processDBdata:3173–3182` writes the supplied child list through `RelationHandler::writeForeignField`. In `RelationHandler.php:1040–1086`, this assigns consecutive sorting and writes timestamp along with relation values. It does not test whether those values were already equal before updating `tstamp`. These are real live database writes; they are not evidence that the visible order changed.

   A small **stock DataHandler datamap** also successfully performs first creation with no pre-existing drafts:

   ```php
   $handler->start([
       'tt_content' => [1 => ['vee_items' => '1,NEWprobe']],
       'tx_vee_item' => ['NEWprobe' => ['pid' => 666, 'title' => 'New probe', 'hidden' => 1]],
   ], []);
   $handler->process_datamap();
   ```

   Core's auto-version path (`DataHandler.php:921–959`) creates the parent/child drafts and remaps the incoming IDs. The diagnostic verifies two resulting workspace members, new child's workspace 1 and live-parent pointer 1, no errors, and unchanged live parent count. Evidence: `core-datamap.json`, `first-create-run.txt`, `probeOrdinaryWorkspaceDatamap`. It still normalizes live sorting/timestamp during initial versioning. This is a tested Core route, **not** a completed production patch or a guarantee for all nested/translated relations.

   No supported route avoiding those initial metadata writes was established. The public version command and normal auto-version datamap both take the same underlying path; invoking internal copy APIs, mutating DataHandler internals, SQL restoration, vendor patches or global XCLASS is not an acceptable substitute.

   **Decision for parent:** either retain the explicit first-create fallback under the original byte-identity contract, or amend the contract narrowly for Core's first-version bookkeeping. A defensible narrower invariant is: identical live parent content/relation values; identical active live membership and ordered UID sequence for every affected relation; identical child content, owner/table/match fields, language, visibility/deletion and PID; allow only schema-identified sort-value normalization that preserves that order and Core's UpdatedAt change during that first-version step. Keep later structural writes byte-identical on live rows. Scope the check over the actual affected dependency graph, not only the requested field. Tied sorts, shared tables, nested children, translated records and external consumers of timestamps require tests. Timestamp changes can invalidate caches or trigger integrations; therefore do not call them universally harmless or silently relax the invariant.

   **Scoped override:** `Classes/DataHandling/InlineDataHandler.php:10–24` claims Core rewrites submitted draft IDs to live IDs. Installed `RelationHandler.php:404–411` expressly returns before that conversion for **foreign_field** relations. Stock DataHandler with explicit draft child IDs leaves the live row byte-identical (`core-existing-draft.json`); the committed repeated create/discard/delete workspace test also passes with the override replaced by an empty subclass in `/tmp` (`stock-run.txt`). The endpoint's required improvement was resolving compiled child identities, not this factory override.

   Worse, using that override for first parent versioning when a child draft already exists changes relation reads to draft IDs: Core then reports **“Record tx_vee_item:2 ... was already a version in archive”**. Evidence: `partly-versioned.json`, `probePartlyVersionedScopedParent`; Core's version-of-version rejection is `DataHandler.php:6616–6621`. Baseline currently refuses first versioning, so this is a hazard when extending that path rather than a presently reachable first-create corruption.

   **Minimum change recommendation:** remove the override for the tested supported foreign_field path after keeping the draft-identity regression tests. Do not carry it into initial versioning. If older permitted Core versions differ, establish a tested version requirement or a narrowly justified compatibility adapter; `composer.json` currently permits Core ^14.3.7. Keep one outer handler for the tested auto-version datamap, validate the actual resolved writes and rollback before returning success. Final invariant/feature decision belongs to parent.

6. **Important functionality limit: all seeded source entries are undeletable; refusal also counts irrelevant translations.**

   `InlineRecordService.php:227–240` denies any translation pointer match and any populated inline/file field. `unsupported:190–196` separately disallows structure changes on connected translated parents. On page 8430, read-only SQL found **24 top-level collection fields across 22 collection blocks, 82 source entries, all 82 with active connected translations**. Thus baseline permits deletion of **zero existing source entries** there. The other three languages' connected collections cannot add/delete either. Source-language scalar editing and supported adds remain useful; freshly created untranslated leaf entries can be deleted above min, but first workspace create, required-media creation, populated nested-row deletion, and translated existing-item deletion fall back. Exact create-capability counts were not measured in the integrated runtime; do not claim them tested.

   Evidence: `showcase-relations.sql`, `showcase-relations.tsv`, `showcase-db.tsv`. The showcase has 23 CEs per language, including its explanatory text CE. Pricing has min=max 3 in the intended YAML; media/default limitations further constrain adds. The runtime's pre-integration YAML limitation remains as described in the showcase handoff.

   The fallback is justified until dependent-record authorization is proven: Core recursively deletes relations (`DataHandler.php:5848–5870`) and translations (`5595–5597`, `5954–5990`). However, VEE's raw translation count includes deleted rows and foreign-workspace rows, unlike Core's workspace/deleted restrictions (`5965–5988`); an irrelevant old translation can permanently disable deletion. It also treats populated fields as cascades without checking actual relation members or `enableCascadingDelete` (`5866`).

   **Minimum fix:** mirror Core's active workspace/deleted/placeholder selection for refusal; use actual resolved dependencies instead of raw counters. Keep unproven cascades refused. A bounded improvement can permit owned leaf translations or nested descendants only after preflighting every affected record/edge, ancestor and language, limiting depth/count, checking min constraints on affected relations, keeping all affected writes on the supported connection and using a lock covering the affected owner graph. FAL support must distinguish deletion of references from physical file removal and prove storage/reference permission behavior. Do not enable all cascades simply because the caller is admin.

   **Required tests:** deleted-only and foreign-workspace-only translations, existing delete placeholders, permitted/denied dependent language, nested shared ownership, bounded rollback after descendant failure, and live/draft/discard identities. Clearly document the zero-existing-delete state if broad fallbacks remain in the first release.

**Reviewed areas with no additional blocking finding**

- Strict intent/mixed-envelope validation and token checking: `InlineIntent.php:11–33`, `PersistenceController.php:30–46`. Server-derived child table, ownership match checks, foreign workspace rejection and delete-placeholder filtering are appropriate for the supported direct relation; the command-envelope bypass above is separate.
- Locking: `InlineRecordService.php:343–356,418–419` canonicalizes to live parent identity, acquires a nonblocking exclusive relation lock, recompiles afterward and releases in `finally`. Existing conflict test passed. This serializes this endpoint's same relation only; ordinary backend writes and independent machines without shared lock storage are outside that guarantee. No broader guarantee should be advertised.
- Stale revision/min/max: lines 115–143,357–385 hash parent/workspace identity plus ordered members, compare after lock and reject missing after/delete targets. Permissions and capabilities are freshly derived. No new defect found for the tested direct relations. A revision need not hash every child label when the structural target membership/order is unchanged.
- Transaction: lines 389–414 reject differing parent/child connections, roll back handler errors/exceptions and live snapshot differences, and do not run commands after a datamap error. The existing injected-after-child-write rollback test passed. This is a bounded database guarantee, not atomicity for hooks, filesystem/cache effects, separately mapped audit/refindex tables, or a future expanded cascade graph. First-version integration must broaden its affected-table/row preflight accordingly.
- Schema capabilities: custom `lang/origin/owner/owner_table/slot` fixture proves the basic language/owner logic is not hardcoded to Content Blocks column names; visibility is read from schema configuration. Global configuration lookups remain insufficient for the child-context overrides in finding 4. Hidden-rights and no-auto-unhide protections remain appropriate.
- Permission checks for direct records use FormEngine plus webmount, language, page/record edit access and Core stage semantics; no new blanket-stage denial was found. The missing owner-chain authorization is finding 2. The admin-only stage assertion is not evidence of all restricted-workspace scenarios.

**Reproduction and provenance**

All probe source is retained at `/tmp/vee-astra-review/baseline/Tests/Functional/Backend/Controller/ReviewProbeTest.php`; the original committed `InlineRecordTest.php` is unchanged in that snapshot. PHP 8.5.12, PHPUnit 13.4.5, SQLite. Dependencies were copied from the package's installed vendor directory; no Composer command ran. Core source hashes matched MAIN and package vendor:

```text
DataHandler.php    7ff4d7a2b54683ddae52374e95359f0c1b1e4508606b812ad61b0a3ee2ad1ff1
RelationHandler.php 9b8a38a28c6b1fc5ab081ab5b8bfad31192e429fd16dcb7b97dca1a580cac814
```

Rerun the probes, entirely inside the disposable snapshot:

```sh
cd /tmp/vee-astra-review/baseline
/opt/homebrew/opt/php@8.5/bin/php -r '$loader = require "vendor/autoload.php"; $loader->addPsr4("TYPO3\\CMS\\Workspaces\\", __DIR__ . "/vendor/typo3/cms-workspaces/Classes/"); require "vendor/phpunit/phpunit/phpunit";' -- -c Build/phpunit/FunctionalTests.xml --filter '::probe' Tests/Functional/Backend/Controller/ReviewProbeTest.php
```

To repeat the stock-handler comparison, require `/tmp/vee-astra-review/StockInlineDataHandler.php` after autoload and before PHPUnit, and select `::(repeatedWorkspaceWrites|probePlainHandlerExistingDraft)`. This changes only the disposable test process's class implementation.

The first probe run also selected the 15 existing inline tests because its class name matched the filter: **21 tests / 82 assertions**, no failures. The stock comparison run: **8 / 19**, no failures. Further focused runs cover the original scoped version command, child overrides/partial versioning (**2 / 2**), paste bypass (**1 / 1**) and initial datamap creation (**1 / 4**). Each reports the existing VE ext_emconf deprecation; none was represented as a clean deprecation-free suite. Diagnostic tests deliberately assert observed vulnerable behavior: a passing probe is evidence of the finding, not a product acceptance pass. No full suite or privileged browser journey was rerun.

Only this report and disposable `/tmp/vee-astra-review/` artifacts were written. Parent/Sol integration retains ownership of fixes, required additional tests, the invariant decision and release.
