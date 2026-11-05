# Visual Editor: executable concept after Astra Ultra review

Date: 2026-11-05. Concept: GPT-6 Astra, ultra. Implementation: GPT-6.1 Sol, extra high (`xhigh`),
as requested by the user. Status: ready for implementation; implementation and verification remain outstanding.

This document takes precedence over `2026-11-05-visual-editor-inline-records-plan.md` wherever they differ.
The earlier 802-line synthesis remains useful for source references, the field audit and the 23-block test matrix.
The user's instruction to continue authorizes implementation now. Its old owner-decision table is not a new approval gate.

## Outcome and boundaries

The settings icon must offer the editable fields of the actual record in the user's workspace and language,
using FormEngine's resolved TCA and TYPO3 permissions. Both existing modes, tabs and sections, remain supported.
Collection children get generic create/delete controls in the existing Lit popover and a separate action-bar entry,
enabled by a new user preference, initially off. Every inline deletion asks for confirmation before making any change.
A Desiderio seed page demonstrates simple, compact, spacious, shared and nested collections, RTE and media limitations.
The login keeps its TYPO3 logo and gets the credit “Proudly running on TYPO3.”

No app-wide redesign, standalone editor, DOM geometry inference, vendor edits or new monkey patches are necessary.
Scope is the Visual Editor request, not a repeat of the already completed software-content and Saferinternet work.

## What the review verified and changed

Local source was inspected in `/Users/dirnbauer/projects/webconsulting-typo3-lab`:

- VEE 1.4.4, commit `2874dcd`; Desiderio 4.30.0, commit `d0c72f38`; lab `7952f99`.
- `Classes/Backend/Controller/PersistenceController.php` is the actual VEE controller path. The earlier plan sometimes
  omits `Backend/`. It currently accepts generic `data` and `cmdArray` and runs separate DataHandler instances.
- `FieldOptionsService` still compiles the requested UID instead of the resolved workspace version, groups before
  FormEngine type resolution, and reads several values from the raw row. These are real implementation gaps.
- Vendor `Backend/components/ve-backend-save-button.js:143-151` sends `saveEnded` after `useDataHandler` regardless
  of its boolean result. It also returns without any completion message for invalid fields, no changes or an active save.
  **The proposed automatic flush/wait is not a valid success barrier and can hang or continue after failure.**
- Vendor `ve-auto-save-toggle.js` can persist staged changes after 300 ms. Staging `hidden=0` immediately after create
  does not guarantee that a person fills the entry before publication. **Remove this mechanism.**
- The proposed `_delete` override with `Function.toString()` inspection is a new vendor monkey patch. It is unnecessary
  for the user's requested confirmation on collection entries. **Do not implement it in this round.**
- The public imprint names `webconsulting business services gmbh`, not the guessed `webconsulting GmbH`.
  Membership/non-affiliation must not be invented. Use a neutral trademark credit without that claim.

## Field-loading contract

Retain the earlier section 2 algorithm, with these explicit requirements:

1. Authenticate the backend session and token before disclosing records, table structure or field choices.
2. Resolve the requested record into the active workspace; reject foreign workspace records and delete placeholders.
   Keep requested identity separate from compilation UID, localized live UID and workspace version UID.
3. Apply the appropriate page/table operation, webmount, language, record/page editlock and workspace checks.
   For a `pages` record, use page-edit permission; do not apply content-edit permission indiscriminately to all tables.
   Verify existing-record workspace stage semantics against Core; do not replace them with a blanket stage-0 guess.
4. Use the record type and FormEngine-resolved configuration, including columnsOverrides, TCEFORM, display conditions,
   readOnly, exclude fields, language restrictions and item-level authMode. Never leak an inaccessible child list/count.
5. Values come from the compiled row with per-type normalization. Preserve an unknown stored value as an explicit
   unavailable selection; never silently select the first option. Add radio/country only using Core-resolved items.
6. Use FormEngine's resolved type for groups (including typeless tables with type `1`). Tab identity uses stable indices,
   not translated labels. Preserve selected tab on a refresh of the same record. Ignore stale async responses when
   opening another record and key/invalidate caches with record, language and workspace identity.
7. A child output's table/UID determine its settings; the surrounding content-element identity determines refresh.
   A nested child chooser must be allowed through the authorized parent relation without globally enabling all tables.
8. The new collection preference is independent of field-chooser display mode. When ordinary field settings are off,
   the collection entry still works for an opted-in user. Enforce that independence on the server as well as in JS.

Test actual denied and permitted users, translated drafts, shared child tables, duplicate tab labels and both modes.
When reducing TCA for compilation, prove displayCond dependencies still resolve; keep necessary dependent columns
for compilation but never return them merely because they were dependencies.

## Collection UI and supported relations

Use the existing Lit field chooser. A collection shows its label, count/limits, entries and operation reasons.
Each entry provides Item settings, Open backend form, Add after and Delete where supported. A footer adds an entry.
Keep the root content-element identity for refresh and provide a Back action from nested child settings.
Lit is for rendering; API calls, action builders and confirmation are ordinary small ES modules.

Supported first-release relations: `type=inline` with `foreign_field`, including `foreign_table_field`,
`foreign_match_fields` and nested Content Blocks collections. Derive column names from TCA; never hard-code
`foreign_table_parent_uid`, `sys_language_uid` or `hidden` as requirements of a generic relation.
Read language and visibility fields from schema capabilities. Exclude FAL, MM relations and unsupported selectors,
foreign-type/default combinations or special relation configurations unless backed by explicit regression tests.
For unsupported cases show the reason and a normal backend-form link. Do not simulate Core support.

No drag reordering, unsaved NEW placeholders in frontend markup or per-entry wrapper guessing. Add after is enough.
The existing content-element delete implementation is outside this first-release change; its workspace issue stays
documented as a separate vendor concern. Collection deletion has its own correct confirmation and identity handling.

## Server-owned action protocol

Keep the existing authenticated `visual_editor_save` route, but add a **separate intent branch**, not the earlier
client-built datamap plus permission envelope. Example:

```json
{"inlineRecord":{"action":"create","parentTable":"tt_content","parentUid":34421,
 "field":"desiderio_featurecards_items","afterUid":5332,"revision":"opaque-current-collection-revision"}}
```

Delete sends `action: delete` and `childUid`, with the same parent/field/revision. No client-supplied child table,
PID, language, live UID, relationship CSV, arbitrary initial values or workspace fields. Reject unknown keys,
invalid scalar types and mixed intent plus `data`/`cmdArray`. Return the new UID and refreshed revision/capabilities
on success. Separate existing generic saves remain backward-compatible.

The server resolves everything from the authorized parent and field. It checks install flag, user preference,
page configuration, parent and child permissions, relation membership, controls, required defaults and min/max
again immediately before writing. A revision covers workspace/language identity and collection membership/order;
a stale target/revision returns 409 and refreshed state, never a silent append or overwrite.

Generate the DataHandler maps on the server. In a workspace use Core/FormEngine's version-parent and form-child
identities for relation writes; use the proper live identity for deletion and the correct own identity for a NEW
placeholder. Validate this with repeated create/delete tests and byte-for-byte checks that live relation fields,
sorting and timestamps remain unchanged. Do not infer placeholder behaviour from its label alone.

One operation uses one DataHandler instance, returns its errors and `substNEWwithIDs`, and must not run a command
after a failed datamap. A refusal before mutation changes nothing. Test failure after mutation starts: keep supported
database writes transactional where feasible and verify rollback of relation/child changes. Do not claim universal
atomicity for arbitrary third-party hooks, external effects or tables mapped to different connections.

Serialize this endpoint's writes to the same owning relation using TYPO3's existing locking facilities, re-read after
acquiring the lock and release in `finally`. Reject conflicting concurrent requests. This is not a promise that
unrelated backend extensions share the lock. Do not add a custom queue or distributed lock system.

Protect generic-save bypasses using TCA-derived relation metadata: no direct inline relation reassignment, direct child
deletion or child-owner reassignment that circumvents this guard. Preserve ordinary editable child fields and the
existing element-library create/copy paths. Cover bypasses and compatibility with functional tests before shipping.
Do not broaden generic command acceptance to `inlineLocalizeSynchronize` in this release.

## Save, visibility and translation behaviour

Structural actions require a clean, idle editor. When there are unsaved/invalid changes or an active save, disable them
with “Save or reset your changes first.” Observe the vendor's documented editor-state messages, including other language
frames where available. Do not trigger Save automatically and do not treat `saveEnded` as proof of success. On uncertainty
or a network failure keep the action blocked and offer a refresh; never discard pending text as part of a wrapper swap.
Recheck immediately before POST and disable repeated clicks while it is in flight. A failed POST must not trigger an
automatic retry that duplicates creation. Restore controls and report a useful error.

Creation/deletion is immediate, clearly labelled with live/workspace context. Reset does not undo the operation.
The core TYPO3 modal asks before deletion, with Cancel focused by default; Cancel, Escape and dismiss make **no writes**.
Use text-safe label interpolation, restore focus and handle same-origin iframe modal delegation. Native confirm is a
documented fallback if Core cannot render there. Delete copy distinguishes live removal, workspace deletion and draft
discard and mentions affected connected translations only when that was checked.

Live creates default to hidden where the child's schema has a visibility field and the user can write it. They stay
hidden while edited; **never stage automatic unhide**. Explain “Hidden until you enable it in the backend form” and supply
that form link. A table lacking such a field is not silently published under this policy: explain the limitation and
offer backend/workspace editing. Workspace creation must also prove the child and parent participate in versioning;
do not create a live-only child just because the parent is a draft. An explicit site opt-out of createHidden may permit
immediate visible creation with accurate wording, but is not the default.

Use Core TCAdefaults and safe, documented label defaults so new rows can be found. Do not expose unvalidated generic
field editing in the action API. Required file/media or complex required fields route to FormEngine. Optional fields
omitted by `f:if` also remain editable there. Test labels/defaults for each supported required-field shape.

Default-language collections and independent translations are supported when their TCA allows it. Connected translated
collections do not offer structural changes; link to the source language/backend form. Do not add the speculative
“Sync translations” command: Core synchronization can remove entries and spans additional permissions/workspaces.
It is explicitly deferred; ordinary existing translation editing and page-scoped seed translation remain supported.
If cascade deletion cannot be shown to respect dependent records' languages/permissions, deny that operation and explain.

## Desiderio test page and limits

Implement the earlier section 5 page, `/content-types/visual-editor-inline`, with its 23-block matrix. Use current content
and price sources rather than copying stale sample prices. Keep it out of navigation and search (`no_search`), adding
the small ShowcasePage/seeder option if needed. Include the setting, immediate-save semantics and form fallback in its
short explanation. No visitor should see editor-only controls.

Normalize collection `minItems`/`maxItems` YAML keys to supported lowercase keys after verifying the installed Content
Blocks parser. Audit affected existing collections before applying changed limits, including nested/shared relations
and translations. Report violations; do not silently weaken requirements or delete/fill user content to get a green gate.
A blocking existing-content violation must be resolved with a targeted migration or a narrowly deferred configuration
change, documented in the handoff. Do not let it prevent unrelated chooser/collection work from proceeding.

The page must reseed without orphaned descendants. Repeated seeding may not delete editors' unrelated pages. Translate
only this page to de/zh/hu using the existing translation memories and filters. Preserve JSON structure and existing
entries; synchronize the task's translation changes between the lab worktree and runtime checkout carefully.

## Login wording

Use `credits.tagline`: EN `Proudly running on`, DE `Läuft aus Überzeugung auf`, followed by the unchanged TYPO3 SVG.
The SVG supplies the product name once to assistive technology. Keep the existing copyright/GPL section.
The trademark line should state only `TYPO3 is a registered trademark of the TYPO3 Association.` and its German
translation. Do not assert non-membership, non-affiliation or endorsement and do not add an unnecessary legal entity.
No product rename or new badge is part of this task. Check EN/DE layout and accessible name; do not add a brittle test
that merely asserts an exact marketing sentence.

This is a cautious editorial choice supported by factual/descriptive use in the published policy, not a legal opinion
that every current use of the name/logo has been cleared. Sources checked on 2026-11-05:

- [TYPO3 Trademark Usage Policy](https://docs.typo3.org/m/typo3/guide-policy/main/en-us/Association/TrademarkUsagePolicy.html)
- [webconsulting imprint](https://www.webconsulting.at/impressum)

## Implementation ownership and acceptance

Two independent Sol xhigh tasks may run in parallel. Both read this document and the relevant old research sections.

1. **Editor task:** owns `MAIN/packages/visual_editor_enhancements` only. Complete chooser, collection server/client,
   setting, confirmation, docs and tests. No lab lock, Desiderio, global seed or production operations. Prepare a release
   candidate with tests and a precise handoff, but leave coordinated tags/deployment to integration.
2. **Showcase/login task:** owns `MAIN/packages/desiderio`, the lab worktree's login files, translation JSON and new lab
   E2E page spec. Build and test the page, key normalization and login. Can apply targeted local seeding after checking
   DDEV state; no lab lock, VEE edits, production writes or tags. Record the page UID and configuration/limit findings.

The parent then integrates, tests the actual user journey, releases and deploys the authorized changes in order.
Use no shell-created child worktree. Existing package clones are separate repos; use shared delegation with explicit
file ownership. Preserve all pre-existing changes and coordinate runtime files with the worktree.

Required evidence, without weakening checks:

- VEE PHP lint/style/static analysis, unit/JS and functional tests for real Core permissions and DataHandler results.
  Include forged mixed payloads, feature disabled, stale revisions, double clicks, denied child-owner writes, max/min,
  rollback/error, missing visibility rights, cross-parent/shared-table isolation, foreign workspace, repeated draft
  writes, and connected translations. Verify existing library/content saves still work.
- Component tests for tabs/sections, preference independent of mode, stable tabs, nested Back, keyboard and modal cancel,
  pending/invalid save blocking, HTTP failure and preserved hidden state under autosave.
- Desiderio existing and focused seed/limit tests, content lint, translation completeness and no-search persistence.
- Actual local browser journey with an admin and restricted editor in live and workspace; edits and deletes confined
  to disposable test-page records. Prefer existing valid test auth. Test configuration may reuse a valid storageState
  rather than insisting on a password every run. If a genuine credential is missing use `request_secret`; do not read
  unrelated secrets, reset a user's password, manufacture user approval or claim a skipped test passed.
- Local login EN/DE and anonymous test-page checks. Test page visible in four languages, no editor data exposed.

Use the relevant project skills for DDEV, Content Blocks, DataHandler, testing and documentation as needed.
Run DDEV from MAIN. Read `lab-single-package-install.md` before touching Composer: lock-only update of the two packages,
preserve linked clones, synchronize Mutagen before dump-autoload, clear stale OPCache and validate package artifacts.
Do not repeat the old project-wide update/reseed/translation cycle.

Release versions 1.5.0 and 4.31.0 are provisional: recheck existing tags first. Integrate only verified task changes;
list outgoing commits separately before pushing and push a reviewed SHA. Keep GitHub/GitLab and the local runtime in
sync. Production seed/translation is restricted to this test page, with a backup and measured runtime. Do not promise
“under five minutes” without measurement, and never launch a full translation to resolve this small change.

Completion means tested code, seeded translated page, accurate docs and coordinated integration. Report remaining
unsupported cases and any unavailable verification explicitly. Do not label the implementation complete because
the concept is finished or a worker has started.
