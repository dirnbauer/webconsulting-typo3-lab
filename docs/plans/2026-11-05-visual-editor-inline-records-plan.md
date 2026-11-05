# Visual Editor: inline records (create/delete collection children), field-chooser audit, login credits, test page

> Execution revision, 2026-11-05: Astra Ultra reviewed the source and superseded several implementation decisions.
> Read [the Astra execution concept](2026-11-05-visual-editor-astra-execution-concept.md) FIRST. It takes precedence
> over this research document, including its decision table, save protocol, publication behaviour, vendor patches,
> test-access assumptions and worker model. User instruction: implementation with **Sol, extra high (`xhigh`)**.
> The old Fable synthesis below is retained as research evidence, not as an approval gate.

Implementation plan, written 2026-11-05 by the planning thread (Fable 5.1, synthesizer of three concepts and three judge
verdicts). Execution: Opus 5.5 workers at effort high, in parallel where section 7 allows it. This file is the single
source of truth for the work; every worker reads sections 0–3 and 7 in full and the sections its work package names.
The winning concept was "Minimal-first" (two of three judges); the grafts and must-fixes of all three judges are folded
in and marked where they change the winner's design. Claims not verified in source are marked **assumed**.

Paths: `MAIN` = `/Users/dirnbauer/projects/webconsulting-typo3-lab` (DDEV project, read-only for planners),
`VEE` = `MAIN/packages/visual_editor_enhancements` (webconsulting/visual-editor-enhancements 1.4.4, GitHub
dirnbauer/typo3-visual-editor-enhancements), `VE` = `MAIN/vendor/friendsoftypo3/visual-editor` (1.10.4, Composer dist
zip ref 773dd591 — not a git checkout, no file newer than its composer.json, so "upstream" = these files),
`DES` = `MAIN/packages/desiderio` (4.30.0), `CORE` = `MAIN/vendor/typo3/cms-core`, `BE` = `MAIN/vendor/typo3/cms-backend`,
`WT` = this worktree (lab commits: site_package login, docs, Tests/E2E).

## 0. Summary

**Deutsch.** Das Zahnrad-Icon des Visual Editors (unsere Erweiterung, "Feldeinstellungen") lädt heute schon die richtigen
Felder: Kandidaten aus dem TCA-Typ, dann die echte FormEngine-Kompilierung mit allen Rechten (Seite, Sprache, authMode,
Sperren, TCEFORM). Vier Lücken werden geschlossen: übersetzte Entwürfe im Workspace werden von der Live-Zeile kompiliert,
Sperre der Seite und Workspace-Stufe fehlen, Kind-Felder (z. B. Changelog-Datum) öffnen beim Hover die Eltern-
Einstellungen, typlose Content-Blocks-Tabellen bekommen keine Gruppen. Beide Darstellungen (Tabs, Abschnitte) bleiben
und bekommen zwei kleine Fehlerbehebungen. Content Blocks kann im Frontend keine Kind-Einträge anlegen oder löschen –
das baut unsere Erweiterung: ein Block "Einträge" im bestehenden Feldeinstellungen-Popover (Lit, kein zweites Popover),
Anlegen und Löschen als sofortige, servergeprüfte DataHandler-Schreibvorgänge über die bestehende Save-Route, Löschen
immer mit dem TYPO3-Core-Modal "Sind Sie sicher?", neue Einträge in der Live-Umgebung versteckt bis zum ersten Speichern.
Rechte, Workspaces (Live-UID beim Löschen, Versions-UID beim Schreiben), Übersetzungen (nur Standardsprache bei
verbundenen Übersetzungen, Knopf "Übersetzungen synchronisieren") und Limits werden auf dem Server entschieden. Alles
hinter einer neuen Benutzereinstellung (Standard aus). Desiderio schreibt `minItems` statt `minitems` – kein
Desiderio-Element hat heute ein Minimum; das wird umbenannt. Login: Logo bleibt, Zeile "Proudly running on TYPO3.",
Markenhinweis nennt die webconsulting GmbH. Testseite `/content-types/visual-editor-inline` mit 23 Elementen, Seed und
Übersetzung nur für diese Seite. Nicht möglich: ungespeicherte Platzhalter-Einträge auf der Seite, Buttons direkt am
Eintrag ohne Template-Änderung, Sortieren per Drag.

**English.** The field chooser already loads the right fields (TCA sub-schema candidates, then a real FormEngine compile
with every permission); this round fixes the verified gaps (workspace uid of translated drafts, page editlock and
workspace stage, child outputs attributed to the parent, typeless tables without groups) and two display-mode bugs.
Content Blocks offers no frontend create/delete at all, so our extension adds an "Entries" block inside the existing
field-settings popover: create and delete are immediate, server-validated DataHandler writes through the existing save
route; delete always asks with the core TYPO3 modal; new live entries are hidden until the first save. Rights, workspaces,
translations and limits are decided on the server. Everything sits behind a new per-user setting (default off). Desiderio
gets its `minItems` → `minitems` rename, the login gets "Proudly running on TYPO3.", and a 23-block test page is seeded
and translated with `--pages`. Not possible: unsaved placeholder entries on the page, per-entry buttons without template
changes, drag reordering.

## 1. Findings of the field-chooser audit

Scope: `VEE/Classes/Service/FieldChooserConfigurationService.php`, `FieldOptionsService.php`,
`Classes/Middleware/FieldOptionsMiddleware.php`, `Resources/Public/JavaScript/Frontend/components/ve-field-chooser.js`,
`Frontend/element-context-affordance.js`, verified on the model page 1253 (`/content-types/rte-combinations`).

| # | Finding | Verdict | Evidence | Fix |
|---|---|---|---|---|
| F1 | Candidates = the record type's showitem-scoped sub-schema, auto-detect = static `selectSingle` (not multiple, maxitems ≤ 1), category, link, single check, opaque color; blocked by name: colPos, sorting, type field, sortby, hidden, editlock, language, l10n_parent, l10n_source | **Right** | `FieldChooserConfigurationService.php:210-273`; `CORE/Classes/Schema/TcaSchemaBuilder.php:168-206` | keep |
| F2 | Payload compiled by FormDataCompiler + TcaDatabaseRecord on a reduced TCA, filtered by `EditModeService::canEditField`, survival = SingleFieldContainer parity (TCEFORM disabled, readOnly, l10n_mode exclude, defaultAsReadonly) | **Right** | `FieldOptionsService.php:104-152, 163-172, 237-258`; `BE/Classes/Form/Container/SingleFieldContainer.php:76-106` | keep |
| F3 | Verified on page 1253: core text 194125 offers header_layout, frame_class (default, bg-10, bg-80, bg-100), categories (folders 987/1065); desiderio_notification 194038 adds variant, action_link, dismissible — because the Desiderio set's TCEFORM hides layout, space_*, header_position, header_link, sectionIndex, linkToTop, date | **Right** | `DES/Configuration/Sets/Desiderio/page.tsconfig:17-32`; `config/sites/desiderio/page.tsconfig:5-13`; lab DB rows | keep; non-Desiderio sites see layout/space_*/sectionIndex/linkToTop in addition (**assumed**, set is Desiderio-only) → D14 |
| F4 | Translated records in a workspace are compiled from the LIVE translation row: wrapper uid = localized (live) uid, the middleware passes it to FormDataCompiler, AbstractDatabaseRecordProvider does no overlay; the core edit controller rewrites to the version uid first | **Wrong** | `VE/Classes/Service/ContentElementWrapperService.php:76`; `FieldOptionsMiddleware.php:65-78`; `BE/Classes/Form/FormDataProvider/AbstractDatabaseRecordProvider.php:51-90`; `BE/Classes/Controller/EditDocumentController.php:1891-1931` | WP1 (algorithm step 3) |
| F5 | A version uid of another workspace is answered; delete placeholders fail only deep in DatabaseEditRow; no workspace stage check although DataHandler refuses at save | **Wrong** | `FieldOptionsMiddleware.php:65-78`; `CORE/Classes/DataHandling/DataHandler.php:9564-9586` | WP1 (steps 3, 5) |
| F6 | Page-level editlock is never checked before offering fields (FormEngine's providers do not either); DataHandler refuses at save | **Gap** | `DataHandler.php:7788-7796`; `BE/Classes/Form/FormDataProvider/DatabaseUserPermissionCheck.php` (no editlock) | WP1 (step 4, EditAccessService) |
| F7 | Button and chip are shown on `canModifyRecord` alone (tables_modify + explicit_allowdeny CType); editors without page CONTENT_EDIT get a bare "Could not load field options." 403 | **Gap** | `Frontend/index.js:104-110`; `ContentElementWrapperService.php:40-49`; `ve-field-chooser.js:498-500` | WP1 (403 carries a reason, popover shows it) |
| F8 | Hover chip resolves every output against the WRAPPER record; a child field (changelog_items.date) matches the parent's "headers" palette and opens the parent's Headers settings | **Wrong** | `element-context-affordance.js:53-67,112-131`; `ve-field-chooser/related-fields.js:18-27`; `DES/ContentBlocks/ContentElements/changelog/config.yaml:31-34` | WP1 (output's own table/uid) |
| F9 | Form groups use `types[recordType] ?? types['0']`; FormEngine falls back to `'1'`, which every Content Blocks child table uses | **Wrong** (invisible until child records are reachable) | `FieldOptionsService.php:334-336`; `BE/Classes/Form/FormDataProvider/DatabaseRecordTypeValue.php:119-133` | WP1 (step 10) |
| F10 | Select/check/color/link values come from the raw row while category and all conditions come from the compiled row | **Gap** | `FieldOptionsService.php:316,386,406,422` | WP1 (step 9) |
| F11 | Tabs mode: a save while open resets activeTab to 0; tabs keyed by label merge equal labels | **Bug** | `ve-field-chooser.js:70-75,237-244,390-407` | WP1 |
| F12 | A stored value outside the resolved items renders as if the first option were chosen | **Bug** | `ve-field-chooser.js:555-563`; `BE/.../TcaSelectItems.php:191-198` | WP1 (disabled placeholder option) |
| F13 | radio and country omitted although TcaRadioItems/TcaCountry resolve their items | **Gap** | `FieldChooserConfigurationService.php:218-223` | D13 (default: add both) |
| F14 | `Documentation/Configuration.rst:49-52` documents a removed `tx_visualeditor_showFieldChooser` fallback; `Index.rst` says 1.0.0 | **Docs** | grep in Classes/ → none | WP5 |
| F15 | Child records have NO UI entry point for their choice fields (no wrapper; chip misattributes) | **Gap** | `VE/Classes/EventListener/AfterStdWrapFunctionsExecutedEventListener.php:18-36` | WP4 ("Item settings" opens the chooser for the child) |
| F16 | Both display modes share one payload and are client-side only; "disabled" hides the button | **Right** | `Shared/config.js:23-31`; `ve-field-chooser.js:323-341,383-416` | keep |

## 2. The field-loading algorithm ("allowed to load")

One algorithm, implemented once, reused by the field chooser (GET `?veFieldOptions=1`) and by the inline-record payload
(section 3). Steps marked NEW are the WP1 changes; the rest exists and stays.

1. **Client gate** (unchanged): `Frontend/index.js` injects the settings button only on `<ve-content-element canModifyRecord>`
   whose `table` is in `window.visualEditorEnhancements.fieldChooserTables`. NEW: the hover chip
   (`element-context-affordance.js`) takes `table`/`uid` from the hovered OUTPUT element (`ve-editable-text|rich-text`
   attributes written by `VE/Classes/ViewHelpers/Render/TextViewHelper.php:196-199`) and resolves the child's own payload
   when they differ from the wrapper.
2. **Session gate** (unchanged): `Classes/Security/EditSessionGuard::check($request, $table)` — backend user logged in
   (Context aspect and `$GLOBALS['BE_USER']`), `X-Request-Token` valid for FormProtection scope `visual_editor/save`,
   `BackendUserAuthentication::check('tables_modify', $table)`. 401/403 JSON, `Cache-Control: private, no-store`.
3. **Record resolution** (NEW, `FieldOptionsMiddleware`): `$row = BackendUtility::getRecordWSOL($table, $uid)` → 404 when
   null; 404 when `(int)$row['t3ver_wsid']` is neither 0 nor `$beUser->workspace`; 404 when
   `VersionState::tryFrom((int)$row['t3ver_state']) === VersionState::DELETE_PLACEHOLDER` (explicit: `workspaceOL` returns
   the state-2 overlay as the row, `BE/Classes/Utility/BackendUtility.php:2760-2787`);
   `$compileUid = (int)($row['_ORIG_uid'] ?? $row['uid'])` — `EditDocumentController::fixWSversioningInEditConf` parity.
   The requested uid stays the payload's `uid` (the vendor store stages against it; DataHandler maps live → version).
4. **Edit access** (NEW, shared `Classes/Service/EditAccessService::forRecord(string $table, array $row): EditAccess`):
   `isInWebMount($pid)`; `BackendUtility::readPageAccess($pid, $beUser->getPagePermsClause(Permission::PAGE_SHOW))` +
   `$beUser->calcPerms($pageRow)->editContentPermissionIsGranted()` (`CORE/Classes/Type/Bitmask/Permission.php:113`);
   page `editlock` for non-admins (`DataHandler::hasPageContextPermission` parity, `DataHandler.php:7771-7797`);
   `$beUser->checkRecordEditAccess($table, $row)->isAllowed` (language, authMode of stored values, record editlock,
   `BackendUserAuthentication.php:723-822`); workspace: if `$beUser->workspace > 0` require
   `workspaceCheckStageForCurrent(0)` and, when the TcaSchema is not workspace-aware, `workspaceAllowsLiveEditingInTable`.
   Result `{allowed: bool, reason: null|AccessDenial}` with `Classes/Enum/AccessDenial { Webmount, Page, Record, Language,
   Workspace, Table }`. 403 JSON `{"error": "...", "reason": "page"}`; the popover shows the matching label
   `frontend.fieldChooser.denied.<reason>`.
5. **Scope + candidates** (unchanged): `BackendUtility::getPagesTSconfig($pageId)['tx_visualeditorenhancements.']['fieldChooser.']`
   → `FieldChooserConfigurationService::resolveFields($table, $recordType, $pageId)` over
   `TcaSchemaFactory::get($table)->getSubSchema($recordType)->getFields()`; then
   `EditModeService::canEditField(RecordFactory::createResolvedRecordFromDatabaseRow($table, $row), $field, $request)` per
   candidate (AccessReadOnly, readOnly, checkLanguageAccess, tables_modify, isInWebMount, explicit_allowdeny for tt_content
   CType, non_exclude_fields). D13 default adds `RadioFieldType` and `CountryFieldType` to the auto-detect.
   NEW: when inline records are enabled (section 3.1) the inline columns of the sub-schema (`type=inline` with
   `foreign_field`, not `sys_file_reference`) are appended to the compile column set by `InlineRecordOptionsService`
   (independent of the chooser's `fields`/`excludeFields` TSconfig), each gated by `canEditField` on the parent column
   (the lab's `tt_content.desiderio_featurecards_items` is `exclude=true`) **and** an explicit
   `check('tables_modify', $childTable)` — TcaInline does NOT remove the column for users without it, it keeps it with
   `children = []` and the raw count value (`BE/Classes/Form/FormDataProvider/TcaInline.php:55-67`, verified).
6. **FormEngine compile** (uid fixed, flags NEW): `FormDataCompiler::compile(['request', 'tableName' => $table,
   'vanillaUid' => $compileUid, 'command' => 'edit', 'processedTca' => <reduced TCA>,
   'inlineResolveExistingChildren' => true, 'inlineCompileExistingChildren' => false], TcaDatabaseRecord)` —
   DatabaseUserPermissionCheck (tables_modify, CONTENT_EDIT, checkRecordEditAccess), TcaColumnsProcessShowitem/RemoveUnused,
   TcaRadioItems, TcaCheckboxItems, TcaSelectItems (authMode per item, keepItems/removeItems/addItems/itemsProcFunc),
   TcaCountry, TcaSelectTreeItems/TcaCategory (second compile with TcaSelectTreeAjaxFieldData as today), TcaInline (child uid
   list into `databaseRow[column]`, workspace-substituted by `TcaInline::getSubstitutedWorkspacedUids`,
   `TcaInline.php:244-261,451-477`), EvaluateDisplayConditions. `AccessDeniedException` → 403 with reason,
   `DatabaseRecordException` → 404.
7. **Survival** (unchanged): offer a field only if `processedTca.columns[$field]` survived and
   `isEditableInBackendForm()` passes.
8. **Inline fields** (NEW, section 3.3): one payload field of `type: "inline"` per surviving inline column.
9. **Values** (NEW): every value from `$formData['databaseRow']` (select → `[0]`, check → int, link/color → string,
   category → uid list), never from the raw row.
10. **Groups** (NEW): parse showitem/palettes of `$GLOBALS['TCA'][$table]['types'][$formData['recordTypeValue']]`
    (FormEngine's resolved type: value, else `'0'`, else `'1'`); key tabs by `--div--` index, not label.
11. **Save** (unchanged for choice fields): `dataHandlerStore` → backend POST `/visual-editor/save` (our
    `PersistenceController`, route needs `web_edit`) → DataHandler (live uid in a workspace → existing version or
    auto-version; editlock, foreign workspace, stage refused with errorLog) → `element-refresh.js` swaps the wrapper and
    clears the options cache.

**Rights in force after WP1 (where):** tables_modify (guard, canEditField, DatabaseUserPermissionCheck, explicit child
check); explicit_allowdeny CType (wrapper + canEditField, tt_content only); non_exclude_fields (canEditField — every core
choice field and every Content Blocks field is `exclude=1`, `MAIN/vendor/friendsoftypo3/content-blocks/Classes/FieldType/WithCommonProperties.php:28`);
allowed_languages; webmount; page CONTENT_EDIT (EditAccessService + DatabaseUserPermissionCheck); page editlock (NEW);
record editlock + authMode of stored values (checkRecordEditAccess); per-item authMode (TcaSelectItems); TCEFORM;
workspace ownership, delete placeholder, stage (NEW); web_edit module (save route). Left to DataHandler at save (errorLog →
`Notification.error`): doktype table restriction, AdminOnly tables, hooks.

**Workspaces:** the edit frame renders in `be_users.workspace_id` (lab admin uid 1 is parked in workspace 4 "AI Review
Test"). Default-language versioned record → wrapper uid = version uid (compiled as is); translated versioned record →
wrapper uid = LIVE translation uid → step 3 compiles `_ORIG_uid`; foreign-workspace versions and delete placeholders → 404;
stage 0 required.

**Both display modes (unchanged + fixes):** tabs (default `tx_visualeditor_fieldChooserMode = tabs`): one tab per `--div--`,
untabbed fields join the first tab, ≤ 1 tab falls back to sections; sections: `h3` per group change; scoped mode (chip,
inline button) is always flat. Fixes: tabs keyed by showitem index, `activeTab` kept across `#load` for the same record,
`[ current value: x ]` disabled placeholder option. The inline field renders in both modes at the position Content Blocks
gives the collection and as a flat list in scoped mode. No third mode. The user setting stays as is; `disabled` hides the
settings button but not the inline button (3.1).

**Lab default field set:** keep "offer what the backend form offers"; no second blocklist in the extension; the site set
decides via TCEFORM (D14 for the non-Desiderio sites).

## 3. Inline records

### 3.1 Switches and capability model

Three layers, all must agree (the extension's existing rule, `Classes/Service/FrontendConfiguration.php:39-65`):

| Layer | Key | Default | Where |
|---|---|---|---|
| Install flag | `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['visual_editor_enhancements']['inlineRecordsEnabled']` | true | `FeatureFlags::isInlineRecordsEnabled()` (`FeatureFlags.php:18-41` pattern) |
| Install flag | `['deleteConfirmEnabled']` | true | `FeatureFlags::isDeleteConfirmEnabled()` — the only switch for the confirm (D5) |
| User setting | `be_users.uc tx_visualeditor_inlineRecords` | **0** (the owner's "extra option") | `Configuration/TCA/Overrides/be_users.php` after `tx_visualeditor_panelColumns`: `['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 0]`; labels `inlineRecords`, `inlineRecords.description` in `locallang_setup.xlf` + `de.` |
| Page TSconfig | `tx_visualeditorenhancements.inlineRecords { enabled = 1; tables.<childTable>.enabled = 1; createHidden = 1; requireWorkspace = 0; translatedParents = default-only; syncTranslations = button }` | as shown | new `Classes/Service/InlineRecordConfigurationService.php` reading `BackendUtility::getPagesTSconfig($pageId)['tx_visualeditorenhancements.']['inlineRecords.']` (`FieldChooserConfigurationService.php:200-205` shape); unit tests via `Tests/Unit/PageTsConfigPriming` |

`FrontendConfiguration::build` adds `inlineRecordsEnabled` (flag && uc && pageId && TSconfig enabled),
`deleteConfirmEnabled`, `workspaceId` (`$beUser->workspace`); `Shared/config.js` adds `isInlineRecordsEnabled()`,
`isDeleteConfirmEnabled()`, `workspaceId()`. `FrontendConfigurationTest::everythingIsOnByDefault` asserts the exact array
(`Tests/Unit/Service/FrontendConfigurationTest.php:57-70`) and must be extended; new tests
`inlineRecordsAreOffUntilTheUserTurnsThemOn`, `inlineRecordsFollowTheInstallFlag`, `workspaceIdIsExposed`;
`Tests/JavaScript/config.test.js` asserts the off-by-default degradation.

Core defaults for new children use the core mechanism, not ours: page TSconfig `TCAdefaults.<childTable>.<field> = ...`
is applied by DataHandler for new records (`DataHandler.php:611-651, 8293-8373`). The test page sets
`TCAdefaults.feature_cards_items.title = New card` as the documented example, so a new card is never an invisible empty row.

### 3.2 Where the controls live (Lit decision)

- **Lit: yes, but only inside the existing Lit component** `Frontend/components/ve-field-chooser.js` (singleton popover,
  `role=dialog`, focus on open, Escape, outside-pointerdown, focus return already implemented,
  `ve-field-chooser.js:79-97,139-181,343,788-794`). A new render branch `#renderInlineField(field)` shows per collection:
  heading "label · count / max", one row per child (label, state badge, **Item settings**, **Add after**, **Delete**), footer
  **Add item** and, when applicable, **Sync translations**; disabled buttons carry the reason sentence. A visible note
  "Changes to entries are saved at once" + a live/draft badge separates the immediate writes from the staged choice fields
  (judge 2's objection).
- **Entry points:** (a) the existing settings button → the unscoped popover shows the inline fields in their tab/section;
  (b) a new action-bar button `data-ve-enhancement="inline-records"` (`Frontend/index.js` `injectInlineRecordsAction`,
  gated by `isInlineRecordsEnabled()` + `canModifyRecord`, icon `Shared/icons.js listPlusIconSvg(16)`) → the scoped flat
  list (`openFieldChooser({table, uid, elementName, anchorRect, scopeFields: <inline field names>, scopeLabel: 'Entries'})`,
  `ve-field-chooser.js:113`); injected independently of `fieldChooserMode`.
- **Item settings** → `chooser.openFor({table: childTable, uid: child.uid})`: the first UI entry child records ever get for
  their choice fields; nested collections (pricing plans → features, data-table rows → cells) appear the same way because
  the service runs for the child table as parent. The popover remembers the root `elementTable:elementUid` so the wrapper
  to refresh is always the tt_content element.
- **Lit: no** for the confirm (core `Modal.confirm`, zero component code) and **no** for the API
  (`Frontend/inline-records.js`, `Shared/inline-record-commands.js`, `Shared/save-request.js`, `Shared/confirm.js` are
  plain ES modules so the builders run under `node --test`; Lit cannot run there, `composer.json test:js`).
- **No stunts:** no DOM-geometry guessing for per-child anchors, no re-implementation of the vendor editables for NEW rows,
  no fake placeholder rows, no new popover, no new endpoint, no postMessage round trip for the confirm.
- **Deferred v1.1 (per-user placement setting, design-alternatives rule):** actions on the existing hover chip at a child
  output ("Add after", "Delete") — requires a keyboard route into the chip (today it hides 150 ms after focusout,
  `element-context-affordance.js:143-145`) and is therefore not in 1.5.0.

### 3.3 Endpoint and payload

**No new endpoint.** `GET <page>?veFieldOptions=1&editMode=1&table=<table>&uid=<uid>` (header `X-Request-Token =
window.veInfo.token`, `FieldOptionsMiddleware` + `EditSessionGuard`) gains fields of `type: "inline"` built by the new
`Classes/Service/InlineRecordOptionsService::build(array $formData, string $table, array $row, int $compileUid, int $pageId,
ServerRequestInterface $request): list<array>` when `inlineRecordsEnabled`:

```json
{"name": "desiderio_featurecards_items", "label": "Feature Cards", "type": "inline", "group": "", "tab": "General",
 "childTable": "feature_cards_items", "labelField": "title",
 "parentUid": 34421, "pid": 1026, "languageId": 0, "connected": false,
 "minItems": 1, "maxItems": 0, "count": 3, "uids": "5330,5331,5332",
 "canCreate": true, "cannotCreateReason": null, "createHidden": true, "canSyncTranslations": false,
 "children": [
   {"uid": 5330, "liveUid": 5330, "state": "live", "label": "Add a card", "sorting": 1, "hidden": false,
    "hasTranslations": true, "canDelete": true, "cannotDeleteReason": null, "verb": "delete"}
 ]}
```

Field semantics: `parentUid` = compile uid (version uid when a version exists); `uids` = `$formData['databaseRow'][column]`
(form uids: version uid where a version exists, else live — `TcaInline::getSubstitutedWorkspacedUids` parity, the exact
list FormEngine posts); per child `BackendUtility::getRecordWSOL($childTable, $uid)`, DELETE_PLACEHOLDER skipped,
`state ∈ live|new|modified` (t3ver_state 1 → new; `_ORIG_uid`/t3ver_oid → modified), `liveUid` =
`BackendUtility::getLiveVersionIdOfRecord` ?: uid, `label` = `BackendUtility::getRecordTitle`, `hasTranslations` = COUNT of
child rows with `l10n_parent = liveUid`; `verb` = `discard` for state new (DataHandler discards a placeholder's own uid),
`markForDeletion` when workspace > 0, else `delete`. `minItems`/`maxItems` from the compiled column (lowercase TCA keys,
columnsOverrides applied; 0 = unlimited). Reasons: `Classes/Enum/InlineRecordReason { Min, Max, Rights, Page, Language,
Workspace, Translation, File, Hidden, ReadOnly, Controls }`.

**Write contract** (existing `POST /typo3/ajax/visual-editor/save`, our `PersistenceController`, callable from the edit
frame because the vendor inlines `TYPO3.settings.ajaxUrls` there, `VE/Classes/Middleware/EditModeMiddleware.php:118-137`,
and our element library already POSTs from the frame, `ve-element-library/drop-target.js:98-114` — no fallback endpoint):
the body gains a third top-level key `inlineRecord` (today unknown keys throw, `PersistenceController.php:36-38`).

```json
{"data": {"feature_cards_items": {"NEW6a1f": {"pid": 1026, "sys_language_uid": 0, "hidden": 1}},
          "tt_content": {"34421": {"desiderio_featurecards_items": "5330,5331,5332,NEW6a1f"}}},
 "cmdArray": [],
 "inlineRecord": {"action": "create", "parentTable": "tt_content", "parentUid": 34421,
                  "field": "desiderio_featurecards_items", "childTable": "feature_cards_items", "newId": "NEW6a1f"}}
```
→ `200 {"success": true, "substNEWwithIDs": {"NEW6a1f": 5333}}`

```json
{"data": {"tt_content": {"34421": {"desiderio_featurecards_items": "5330,5332"}}},
 "cmdArray": [{"feature_cards_items": {"5331": {"delete": 1}}}],
 "inlineRecord": {"action": "delete", "parentTable": "tt_content", "parentUid": 34421,
                  "field": "desiderio_featurecards_items", "childTable": "feature_cards_items", "childUid": 5331, "liveUid": 5331}}
```

```json
{"data": {}, "cmdArray": [{"tt_content": {"162614": {"inlineLocalizeSynchronize":
   {"field": "desiderio_featurecards_items", "language": 1, "action": "synchronize"}}}}],
 "inlineRecord": {"action": "sync", "parentTable": "tt_content", "parentUid": 162614, "field": "desiderio_featurecards_items", "language": 1}}
```

Refusals: `400` when a datamap sets a `type=inline` column, or a cmd deletes a row of a table that is an inline child
table (has `foreign_table_parent_uid`), or a cmd carries `inlineLocalizeSynchronize`, WITHOUT a matching envelope (the
vendor and our chooser never stage such operations, so this closes the forged-POST hole without touching vendor saves);
`409 {"success": false, "reason": "max"}` when the re-resolved capability says no; `500 {"success": false, "errorLog": [...]}`
when DataHandler says no.

**Server write guard** `Classes/Service/InlineRecordWriteGuard::check(array $data, array $cmdArray, ?array $envelope)`:
re-runs `InlineRecordOptionsService` for `(parentTable, parentUid, field)` with the current BE user, then — create: `canCreate`,
exactly one NEW id in the child table, the parent list equals the current form uids plus `newId` (any position), NEW row
fields ⊆ `{pid, sys_language_uid, hidden}` ∪ child fields passing `canEditField`, `pid` = parent pid, `sys_language_uid` =
parent language, `hidden` only when `createHidden`; delete: the child is listed for this parent/field, `canDelete`,
`liveUid` equals the listed child's `liveUid`, the parent list equals `uids` minus the child's form uid; sync:
`canSyncTranslations` and `language` is an existing translation of the parent. DataHandler enforces none of this for
foreign_field relations (`DataHandler.php:3171-3192`; `grep minitems DataHandler.php` → none) — the guard is the only
place. `DataHandlerService::run()` returns `array{errorLog: list<string>, substNEWwithIDs: array<string,int>}`;
`validateCmd` widens to `{move, copy, delete, inlineLocalizeSynchronize}` with shape validation (field string, language
int, action ∈ localize|synchronize); the controller keeps "data first, then each cmd" and returns `substNEWwithIDs`.

### 3.4 Create flow

1. Editor clicks **Add item** (appends) or **Add after** (row) → `Frontend/inline-records.js createChild(field, {afterUid})`.
2. `flushPendingChanges()`: if `dataHandlerStore.changesCount > 0` → `sendMessage('doSave', {}, 'parent')` and await
   `onMessage('saveEnded')` (vendor copy-on-drop pattern, `VE/.../Frontend/components/ve-drop-zone.js:215-219`); if the save
   failed the backend reloads all frames and nothing further happens. Reason: the wrapper swap in step 6 must not drop typed text.
3. `Shared/inline-record-commands.js buildCreate({parentTable, parentUid, field, childTable, pid, languageId, uids, afterUid,
   hidden, newId: newRecordId()})` builds the datamap above. Never sent: `foreign_table_parent_uid`, `tablenames`, `fieldname`,
   `sorting`, `l10n_parent`, `t3ver_*` — `RelationHandler::writeForeignField` writes them from the parent column's
   `foreign_field`/`foreign_table_field`/`foreign_match_fields`/sortby (`CORE/Classes/Database/RelationHandler.php:961-1085`),
   with the LIVE parent uid as `foreign_field` (`DataHandler.php:10000 setUseLiveParentIds`); DataHandler aligns the child
   language (`DataHandler.php:9841-9960`) and versions in a workspace (NEW_PLACEHOLDER, `DataHandler.php:804-868`).
   **Workspace addressing rule (must-fix):** `parentUid` and `uids` come from the payload, i.e. from the compile of the
   version row when one exists; an unversioned parent is addressed by live uid with live child uids (DataHandler
   auto-versions and remaps, `DataHandler.php:935-958`); a versioned parent by version uid with form uids — the
   existing-version branch does NOT remap (`DataHandler.php:923-926`, verified) and would otherwise write sorting/tstamp
   onto live child rows via `writeForeignField`.
4. `Shared/save-request.js postSave(data, cmdArray, inlineRecord)`: our own `fetch` to
   `TYPO3.settings.ajaxUrls.visual_editor_save` returning the parsed JSON `{success, substNEWwithIDs?, errorLog?, reason?}`;
   `errorLog` → one `Notification.error` per entry (`@typo3/backend/notification.js`, renders in the backend window);
   `409` → the reason label; never the vendor's `useDataHandler` (it returns only `true` and replaces the edit frame's
   `document.body` with error HTML on a non-JSON failure, `VE/.../Backend/use-data-handler.js:20-41`, verified).
5. `newUid = substNEWwithIDs[newId]`.
6. `refreshElement(elementTable, elementUid)` (new export of `Frontend/element-refresh.js` around the private
   `refreshWrappers`, keeps `reExecuteScripts` and the `ve:element-refreshed` event; `false` → `sendMessage('reloadFrames')`),
   `clearFieldOptionsCache()`, `chooser.refresh()` (new public method: reload the payload for the open record, keep
   `activeTab` and scope).
7. **Make the new entry visible and editable:** query `ve-editable-text|ve-editable-rich-text[table="<childTable>"][uid="<newUid>"]`
   inside the swapped wrapper, set the vendor's public property `output.showEmpty = true` on each (the vendor hides empty
   outputs otherwise, `ve-editable-text.js:134-144`; the property is re-synced only on a store change), focus the first,
   `scrollIntoView({block: 'center'})`. Outputs wrapped in `f:if` by the template (feature-cards `description`,
   `DES/ContentBlocks/ContentElements/feature-cards/templates/frontend.html:23`) are not rendered until they have a value —
   documented; `TCAdefaults` and **Item settings** are the way in.
8. **Hidden until saved (live only, must-fix):** with `createHidden = 1` the datamap carries `hidden: 1` and after the swap
   the client stages `dataHandlerStore.setInitialData(childTable, newUid, 'hidden', 1)` + `setData(childTable, newUid,
   'hidden', 0)` (setInitialData creates the nesting, so `markSaved()` cannot throw, `data-handler-store.js:49-62,110-127`);
   the editor's first save publishes text and visibility together. Hidden rows render in edit mode
   (`EditModeMiddleware.php:95-97` → `VisibilityAspect::includeHidden()` → `GreedyDatabaseBackend.php:144-145` drops the
   HiddenRestriction). `hidden` is `exclude=true` on every Content Blocks child table and `fillInFieldArray` drops exclude
   fields silently for users without `non_exclude_fields` (`DataHandler.php:1142-1144`), therefore `createHidden` requires
   `isAdmin() || check('non_exclude_fields', childTable . ':hidden')`; in live without that right `canCreate` is false with
   reason `hidden` ("Ask for the hidden field or work in a workspace"); in a workspace the draft is invisible to visitors
   anyway, so the entry is created visible when the right is missing. `createHidden = 0` disables both.
9. `Notification.success` `frontend.inlineRecords.added` (ICU select on workspace: live → "Entry added. It stays hidden until
   you save." / workspace → "Entry added to workspace {title}."); `document` CustomEvent `ve:inline-record-created`.
10. Nested collections: identical, `parentTable` is the child table, the refreshed wrapper is still the tt_content element.

### 3.5 Delete flow

1. **Delete** on a row → `deleteChild(field, child)`; the FIRST thing is the confirm — nothing is staged or sent before it
   resolves (autosave persists 300 ms after any store change in workspaces, `ve-auto-save-toggle.js:52,75-79`).
2. `Shared/confirm.js confirmDelete({title, content, okLabel})`: `title = lll('label.confirm.delete_record.title')`,
   `content` = ICU `frontend.inlineRecords.confirm` by `verb`: delete → "Delete "{label}"? It disappears from the live site
   at once and cannot be undone with Reset." / markForDeletion → "Mark "{label}" for deletion in workspace {workspace}? It
   stays live until the workspace is published." / discard → "Remove the unsaved entry "{label}"?" + (hasTranslations →
   ICU plural " Its # translation(s) are deleted as well."); buttons Cancel (active, `btn-default`) / Delete (`btn-danger`,
   `lll('buttons.confirm.delete_record.yes')`). Cancel, Escape, backdrop → `false`, focus returns to the Delete button.
3. `buildDelete({parentTable, parentUid, field, childTable, formUid: child.uid, liveUid: child.liveUid, uids})` → datamap with
   the parent list without the form uid + `cmd[childTable][liveUid][delete] = 1` (FormEngine's `memorizeRemoveRecord` +
   `cmd[...][delete]` recipe, `BE/.../inline-control-container.js`; datamap runs before cmdmap in our controller). ALWAYS
   the live uid: `DataHandler::deleteAction` DISCARDS any uid with `t3ver_wsid != 0` (`DataHandler.php:5328-5336`); with
   the live uid a workspace gets a DELETE_PLACEHOLDER (existing overlay flipped to state 2, else `versionizeRecord('DELETED!')`,
   `5349-5402`), live gets a soft delete cascading to nested children and connected translations
   (`deleteRecord_procFields`, `deleteL10nOverlayRecords`). For state `new` the placeholder's own uid IS the target and
   DataHandler discards it — the intended "Remove" result.
4. `flushPendingChanges()`, then `postSave(...)` with the envelope; `409`/`errorLog` → notification, the row stays.
5. `refreshElement(...)`, `clearFieldOptionsCache()`, `chooser.refresh()`, `Notification.success`
   `frontend.inlineRecords.deleted|discarded|markedForDeletion`; focus → next row's Delete, else previous, else Add item;
   `ve:inline-record-deleted`.
6. Reset: cannot undo an inline write (an immediate write, like the vendor's copy-on-drop); in a workspace the Workspaces
   module discards the placeholder; in live an admin uses the recycler. Stated in `Usage.rst` (not "Reset never could undo
   structure": the vendor's staged element delete IS cleared by `store.reset()`, `data-handler-store.js:122-127`).

**Vendor content-element delete (must-fix, same confirm):** `Frontend/visual-editor-patches.js` wraps
`VeContentElement.prototype._delete` after `customElements.whenDefined('ve-content-element')` with the existing self-detect
marker (`Frontend/index.js:62-75` pattern); skipped when `/Modal|confirm/.test(original.toString())` (upstream added one)
or when `deleteConfirmEnabled` is off. After "Delete": stage `addCmd(this.table, liveUid, 'delete', 1)`, send
`contentElementDeleted` and remove the node — the three statements of `ve-content-element.js:71-80` — where
`liveUid = window.veInfo.languageId === 0 ? <uid segment of this.scrollPositionId> : this.uid`. Rationale:
`scrollPositionId = table:$record->getUid()` is the DEFAULT-language live uid (`ContentElementWrapperService.php:79`,
`CORE/Classes/Domain/Repository/PageRepository.php:762-766` keep `uid` = default, translation in `_LOCALIZED_UID`), so it
is right only in the default-language frame; for translations `this.uid` (= `localizedUid`) is already the live
translation uid. Using `scrollPositionId` on a translation would delete the default record and every translation. This
fixes the vendor's workspace discard-instead-of-delete for modified default-language elements (own patch; no upstream
contributions).

### 3.6 Confirm dialog

Core TYPO3 `Modal.confirm`, imported directly in the edit frame — the vendor's own precedent
(`VE/.../Frontend/intercept-user-actions-guard.js:1-3,71-76`); `@typo3/backend/element/contextual-record-edit-trigger.js`
(loaded into the frame by `VE/Classes/Service/EditModeService.php:61-63`) proves `modal.js`, `notification.js` and
`~labels` resolve there; `modal.js` delegates to `topWindow.TYPO3.Modal`, so the `<dialog>` renders in the backend top
window with backend CSS, Escape/aria/focus-on-active-button by core. Labels `label.confirm.delete_record.title`,
`deleteWarning`, `deleteItem`, `buttons.confirm.delete_record.yes|no` are already inlined (`EditModeService.php:174-186`,
`BE/Resources/Private/Language/locallang_alt_doc.xlf:6-11,144-155`). `Shared/confirm.js` (~40 lines):
`export async function confirmDelete({title, content, okLabel}): Promise<boolean>` → `Modal.confirm(title, content,
Severity.warning, [cancel(active), ok])`, `true` on `confirm.button.ok` (+ `hideModal()`), `false` on
`confirm.button.cancel` and `typo3-modal-hidden`; after resolution the caller calls `focus()` on the triggering control
explicitly (focus across nested iframes is not restored reliably). Fallback `window.confirm(content)` when
`topWindow === window` (cross-origin site base; the local Modal would render an unstyled light-DOM dialog in the frontend
document). Scored alternatives: `window.confirm` 2/5 (unstyled, vendor TODO), own Lit `<dialog>` 3/5 (duplicate of
core), postMessage round trip 3/5 (redundant, broadcasts to all language frames), `ve-iframe-popup` 1/5 (no buttons).
Always on; `deleteConfirmEnabled` is the only switch (D5).

### 3.7 JS API (lightweight)

| Module | Exports |
|---|---|
| `Frontend/inline-records.js` (plain ESM) | `createChild(field, {afterUid = null}): Promise<number|null>`, `deleteChild(field, child): Promise<boolean>` (false = cancelled), `syncTranslations(field, languageId): Promise<boolean>`, `flushPendingChanges(): Promise<void>`, `focusNewChild(wrapper, childTable, uid): void` |
| `Shared/inline-record-commands.js` (pure, node-tested) | `buildCreate({...})`, `buildDelete({...})`, `buildSync({...})` → `{data, cmdArray, inlineRecord}`; `newRecordId()` (`/^NEW[a-zA-Z0-9]+$/`, `DataHandlerService.php:48-57`); `insertAfter(uids, afterUid, newId)`; `withoutUid(uids, uid)` |
| `Shared/save-request.js` (pure) | `postSave(data, cmdArray, inlineRecord?): Promise<{success, substNEWwithIDs?, errorLog?, reason?}>` |
| `Shared/confirm.js` | `confirmDelete({title, content, okLabel}): Promise<boolean>` |
| `Frontend/element-refresh.js` | new `refreshElement(table, uid): Promise<boolean>`; `handleSaved` maps child-table rows to the parent wrapper via the first child output's `closest('ve-content-element')` instead of forcing a full reload (needed for the staged unhide and for chooser edits of child records), full reload stays the fallback |
| `Frontend/components/ve-field-chooser.js` | new `refresh(): Promise<void>`, render branch `#renderInlineField(field)` |
| PHP | `InlineRecordOptionsService::build(...)`, `InlineRecordWriteGuard::check(...)`, `EditAccessService::forRecord(...)`, `InlineRecordConfigurationService`, `DataHandlerService::run(): array{errorLog, substNEWwithIDs}` |
| Labels (`locallang_library.xlf` + `de.`, inlined into `TYPO3.lang`) | `frontend.inlineRecords.open/.add/.addAfter/.delete/.discard/.settings/.sync/.empty/.countOf/.savedAtOnce/.live/.draft/.confirm (ICU)/.added (ICU)/.deleted/.discarded/.markedForDeletion/.synced/.reason.{min,max,rights,page,language,workspace,translation,file,hidden,readOnly,controls}`; `frontend.fieldChooser.denied.{webmount,page,record,language,workspace,table}`; `locallang_setup.xlf`: `inlineRecords`, `inlineRecords.description` |

### 3.8 Permissions (server decides, client renders)

| Check | Core API | Reason |
|---|---|---|
| Parent record editable | `EditAccessService::forRecord(parentTable, row)` (section 2 step 4) | `page`/`record`/`language`/`workspace`/`webmount` |
| Child table writable | `check('tables_modify', childTable)` — explicit (TcaInline keeps the column, verified) | `rights` |
| Parent inline column | `canEditField(parentRecord, column)` → `non_exclude_fields` on the column (lab: `exclude=true`) | field not offered |
| Column flags | not `readOnly`; `appearance.showNewRecordLink !== false`, `enabledControls.new/.delete !== false` (`TcaInlineConfiguration.php:122-135` defaults) | `readOnly`/`controls` |
| Create | `count < maxItems` (0 = unlimited); `checkRecordEditAccess(childTable, ['pid' => pid, <languageField> => languageId], true)->isAllowed` — the language field MUST be in the row (`BackendUserAuthentication.php:747-759`); workspace > 0: `workspaceCanCreateNewRecord(childTable)` && `workspaceCheckStageForCurrent(0)`; `requireWorkspace = 1` in live → denied; createHidden rule (3.4 step 8) | `max`/`language`/`workspace`/`hidden` |
| Delete per child | `count > minItems`; `checkRecordEditAccess(childTable, childRow, false, true)->isAllowed`; not DELETE_PLACEHOLDER; stage rule | `min`/`record`/`workspace` |
| Required File child field | child sub-schema has a `FileFieldType` with `minitems > 0` → `canCreate` false | `file` ("Add this item in the backend form") |
| Translations | see 3.10 | `translation` |
| Write time | the guard re-runs all of the above; DataHandler enforces tables_modify, AccessReadOnly, insert/update permission, checkRecordEditAccess, page editlock, workspace rules again → errorLog | — |

Editor groups must list every Content Blocks child table in `tables_modify`, the parent inline columns and the child
fields (and `<childTable>:hidden` for createHidden) in `non_exclude_fields`. The lab group 2 "Flue Agents (sandboxed)" has
none of it, no `web_edit`, no member — not a test persona (D11). Admins (lab uid 1, 9) bypass rights; limits, file and
translation rules still apply to them.

### 3.9 Workspaces

- Read: all uids workspace-correct (3.3); `workspaceId` in `window.visualEditorEnhancements` drives the wording.
- Create: live-uid addressing only for an unversioned parent (auto-version + remap); version uid + form uids otherwise
  (3.4 step 3). Child inserted as NEW_PLACEHOLDER (`t3ver_state 1`, `t3ver_wsid` = ws). All 189 lab child tables are
  `versioningWS true`, so `workspaceAllowsLiveEditingInTable` is theoretical.
- Delete: LIVE child uid → DELETE_PLACEHOLDER; state `new` → discard with its own uid; stage 0 required.
- Vendor element delete fixed on our side (3.5).
- Live (workspace 0): create/delete immediate and irreversible; confirm text says so; `requireWorkspace = 1` for installs
  that never want it (D3).
- Autosave (default on in workspaces, hidden in the lab by `tx_visualeditorenhancements.toolbar.autoSave = 0`,
  `MAIN/packages/site_package/Configuration/page.tsconfig:8-11`): irrelevant for the inline writes (own POST after the
  flush); the staged unhide is persisted 300 ms later, which is fine; the confirm-before-anything invariant is proven by a
  JS unit test, not by the lab.
- Functional tests (`Tests/Functional/Backend/Controller/PersistenceControllerTest.php`, `workspaces` in
  `coreExtensionsToLoad`, `sys_workspace.csv`, `$GLOBALS['BE_USER']->setWorkspace(1)`): `aNewInlineChildIsAttachedToItsParentWithSortingAndCountAndItsUidIsReturned`,
  `aNewChildOnASharedTableGetsTablenamesAndFieldname`, `aNewChildInAWorkspaceIsANewPlaceholderAndTheParentIsVersioned`,
  `aSecondCreateInAWorkspaceWritesOnlyVersionRows` (live child `sorting`/`tstamp` untouched),
  `aChildDeleteWithTheLiveUidCreatesADeletePlaceholderInAWorkspace`, `aChildDeleteWithTheVersionUidWouldDiscard`
  (documents the trap), `deletingANewPlaceholderChildDiscardsIt`, `aChildDeleteLiveCascadesToNestedChildren`,
  `aForeignChildUidIsRefusedWith409`, `aChildBeyondMaxitemsIsRefusedWith409`, `aStructuralSaveWithoutEnvelopeIsRefusedWith400`,
  `inlineLocalizeSynchronizeIsAccepted`.

### 3.10 Localization

- Default language: create/delete allowed; deleting a default child cascades to its connected translations
  (`deleteL10nOverlayRecords`) — the confirm says so (`hasTranslations`).
- Translated parent, free-mode children (`l18n_parent = 0`, e.g. the MTUG camp site): create/delete per language with
  `checkLanguageAccess`; new child = free-mode child (`sys_language_uid` = parent language, `l10n_parent 0`) — FormEngine
  "+" parity (`DatabaseRowInitializeNew.php:220-239`; `alignLanguageOfInlineChildren` resets `l10n_parent` only for
  language ≤ 0, `DataHandler.php:9928-9935`).
- Translated parent, connected children (Desiderio de/zh/hu: parent `l18n_parent > 0`, children `l10n_parent > 0`, lab
  example tt_content 162651 → children 6056-6061): `canCreate`/`canDelete` false, reason `translation`, label "Add or
  remove entries in the default language, then sync the translations" — a free child inside a connected list would be
  orphaned by the next synchronize. TSconfig `translatedParents = free` switches to FormEngine parity per site (D4).
- **Sync translations** (graft, D12 default yes): on a default-language parent with connected translations the popover
  offers one button; it posts one `inlineLocalizeSynchronize {field, language, action: 'synchronize'}` cmd per translated
  parent language (DataHandler resolves the translated parent, localizes missing children in default order, removes
  orphans, rewrites the list, `DataHandler.php:5130-5285`) after a confirm; the new child's translated text is empty until
  `sitepackage:content:translate` or an editor fills it. `validateCmd` widened with shape validation (3.3).
- Never gated on `veInfo.allowNewContent` (a tt_content rule).
- Test page: every new English string is appended to
  `MAIN/packages/site_package/Resources/Private/Data/Translations/desiderio/{de,zh,hu}.json` `strings` (append only; keys =
  stored text incl. RTE HTML) before `sitepackage:content:translate` runs (section 5).

### 3.11 Limits and edge cases

- `minItems`/`maxItems` from the compiled column (lowercase TCA keys). Desiderio has NONE today: its YAML writes `minItems`/
  `maxItems` (camelCase) in every collection (verified: feature-cards:32, tabs:42, pricing-three-tier:33-34, 67 …) and Content
  Blocks 2.4.10 reads only lowercase (`content-blocks/Classes/FieldType/CollectionFieldType.php:63-64,102-106`); only
  `maxitems: 1` on File fields is real. WP6 renames; until then `minItems` reads 0 and the last entry can be deleted
  (FormEngine parity). After the rename FormEngine enforces the minimum on existing records → SQL gate (WP6).
- Required text child fields (tabs `tab_label`/`tab_content`): DataHandler accepts an empty row; the editor types into it
  (showEmpty + focus); required File fields (gallery `image`) → reason `file`.
- Shared tables (12 Desiderio record types; `desiderio_qa_item` for faq + pricing-faq, `desiderio_label_value` for
  stats-inline + product-specs …): nothing special on the client; `writeForeignField` writes `tablenames`/`fieldname`
  (`RelationHandler.php:1020-1033`); the child list is per parent/field from the compile.
- Nested collections (14 Desiderio elements): via **Item settings**; depth unlimited by construction; test page covers two.
- Children without a text output (logo-cloud/logo-carousel, footer-social): list UI works (labels from `getRecordTitle`),
  the new entry cannot be typed into inline → **Item settings** / backend form.
- Hidden children listed (edit frame forces `includeHiddenContent`) with a muted badge.
- Position: Add item appends, Add after inserts after the row; reordering is NOT offered.
- Two collections on one element (compare, navbar-split, pricing-comparison): two inline fields.
- Elements inside EXT:container / nested content areas: `refreshElement` swaps by wrapper id; missing wrapper → `reloadFrames`
  (**assumed** sufficient; test page has no container case).
- Concurrency: the guard re-resolves children at write time; a stale `afterUid` appends at the end.
- Cross-origin site base: Modal falls back to `window.confirm`; the POST stays same-origin (vendor constraint too).
- Abandoned creates leave a hidden empty row in live (cleanup deferred; `KnownProblems.rst`).

### 3.12 Not possible or deferred (honest list)

| Item | Status | Why |
|---|---|---|
| Unsaved placeholder entry on the page (staged create) | **Not possible** | child markup is the server-side Content Blocks template; `ve-editable-text` declares `uid: Number` (NEW → NaN, `ve-editable-text.js:23`); `markSaved()` throws for un-seeded uids (`data-handler-store.js:110-118`) |
| Add/delete buttons next to each entry in the page | **Deferred (v1.1 chip actions, per-user placement setting)** | children have no wrapper element (vendor wraps tt_content cObjects only); nearest-ancestor guessing is geometry code (no-stunts rule); a marker ViewHelper touches up to 156 Desiderio templates; the chip needs a keyboard route first |
| Content Blocks frontend create/delete | **Does not exist** | only `HtmlTemplateCodeGenerator.php:78` mentions `render.text`; CB generates tables and templates, nothing else |
| Reordering children (move) | Deferred | backend form; payload carries `sorting` |
| Per-language create/delete on connected translations | Blocked by design (`translation`) | orphans; Sync translations covers Desiderio |
| Undo of a DELETE_PLACEHOLDER from the popover | Deferred | `validateCmd` would need `discard`; Workspaces module does it |
| Hiding the vendor's own edit/hide/delete for editors without page permission | Deferred | vendor-rendered bar; we only wrap `_delete` |
| `TCAdefaults.<childTable>.pid` (FormEngine-only) | Deferred | children get the parent's pid |
| `selectSingle` with `foreign_table` in the chooser | Deferred (TSconfig opt-in later) | unbounded item counts |
| Renaming the product "webconsulting TYPO3 Lab" | Owner decision (D8), not in this round | trademark policy forbids the mark in product names |
| `no_search` on the hidden test page | Deferred (D10) | needs a new `ShowcasePage` key |

## 4. Login credits wording

**Recommended:** EN `Proudly running on TYPO3.` / DE `Läuft aus Überzeugung auf TYPO3.` (lockup form: text "Proudly
running on" / "Läuft aus Überzeugung auf" + the unchanged Core logo as the name, announced once through the existing
`<title>TYPO3</title>`). **Runner-up:** `Powered by TYPO3 – inspiring people to share!` (identical in DE; the Core's own
slogan, `CORE/Classes/Information/Typo3Information.php:63-70`).

**Legal rationale.** The TYPO3 Association Trademark Usage Policy (docs.typo3.org, updated 19 May 2025) allows factual
descriptive use of the word mark by anyone, forbids implying endorsement/partnership/certification, forbids "TYPO3" inside
company/product/domain/social names regardless of membership, and names no credit phrase at all ("Powered by", "Made
with", "Built on" absent). The logo (shield + word mark) is not registered but governed by the brand guidelines (never the
word mark without the shield, clear space 2x, approved colours) — the lab layout already complies (unaltered Core SVG,
`Login.fluid.html:198-203`, `login.css:448-463`). "Built on"/"Basiert auf" is legally harmless but factually off (the login
IS TYPO3) and, under the lockup "webconsulting · TYPO3 Lab", reads like a derived product. "Proudly running on TYPO3." is a
true nominative statement, keeps the owner's "proudly", implies nothing about the Association; the owner's draft
"implemented in" is the idiom for programming languages. The GPL block (`Login.fluid.html:207-237`) and `credits.copyright`
must stay (GPL v2 §2(c), `CORE/LICENSE.txt:103-112`; `Typo3Information.php:72-116` "DO NOT prevent this notice"). The real
policy exposure is the product name "webconsulting TYPO3 Lab" (`LoginEnvironment.php:27`, `ext_localconf.php:90-96`,
`Login.fluid.html:50`, README, composer) — D8.

**Exact changes (WP9):**

| File | Change |
|---|---|
| `packages/site_package/Resources/Private/Language/backend_login.xlf:120-124` | unit `credits.builtOn` → `credits.tagline`, source `Proudly running on` |
| `backend_login.xlf:125-129` | `credits.trademark` → `TYPO3® is a registered trademark of the TYPO3 Association. webconsulting GmbH is an independent provider and is not affiliated with or endorsed by the TYPO3 Association.` (entity name **assumed** from memory — confirm against the Impressum, D7; if member: second sentence `webconsulting GmbH is a member of the TYPO3 Association.`) |
| `de.backend_login.xlf:142-159` | `credits.tagline` target `Läuft aus Überzeugung auf` (state final); `credits.trademark` target `TYPO3® ist eine eingetragene Marke der TYPO3 Association. webconsulting GmbH ist ein unabhängiger Anbieter und weder mit der TYPO3 Association verbunden noch von ihr empfohlen.` |
| `Resources/Private/TemplateOverrides/typo3/cms-backend/Layouts/Login.fluid.html:196` | key `credits.builtOn` → `credits.tagline`; lines 198-203 (logo), 205-206, 207-237 unchanged |
| `Resources/Public/Backend/Css/login.css:439` | comment "Built on TYPO3" → "tagline + TYPO3 logo lockup"; no rule changes |
| new `Tests/Unit/Backend/BackendLoginLabelsTest.php` | `everyLabelKeyUsedInTheLoginLayoutExistsInEnglishAndGerman` (parse the layout for `backend_login.xlf:<id>`, assert ids in both XLF files); `creditsTaglineDoesNotClaimDerivation` (no "Built on"/"Basiert auf"; trademark line contains "webconsulting GmbH") |
| memory `desiderio-4-26-menus-films-login.md` | resolve the open trademark point |

Do not use: "TYPO3 Partner", "Official TYPO3 …", "TYPO3 Certified …" (certificate holders only), "TYPO3 Association member"
(members only), the shield alone, membership badges without membership, paid ads with the mark below Gold.

## 5. The test page

**Slug** `/content-types/visual-editor-inline`, parent `content-types` (live uid 1093), `hideInNav` true, title and h1
"Inline records in the Visual Editor", navTitle "Inline records", abstract "A test page for adding and deleting collection
entries in the Visual Editor. Every list shape Desiderio has, with children that carry rich text, images, selects and nested
lists.", description (meta 110–155 chars) "Test page for the Visual Editor: add and delete collection entries in cards,
accordions, tables, lists and nested pricing plans.", pageTsConfig
`tx_visualeditorenhancements.inlineRecords.enabled = 1` + `TCAdefaults.feature_cards_items.title = New card` +
`RTE.config.tt_content.bodytext.types.text.preset = desiderio`. Definition: new `DES/Classes/Data/Showcase/ShowcaseVisualEditorInlinePage.php`
(final class, `page(): ShowcasePage`, `content(): list<ShowcaseBlock>`), registered in `StyleguideShowcasePages::subpages()`
right after `self::rteCombinationsPage()` (`StyleguideShowcasePages.php:58`; model `rteCombinationsPage()` 425-446). Opens
with a core text block so the h1 stays visible. Copy obeys `canon.yaml` (headline ≤ 60 chars/8 words, sentence ≤ 25 words,
eyebrow/button ≤ 3 words, British spelling, no banned words, no "test" as a whole heading, links only absolute or
`{{page:...}}`, card grids link all or none).

| # | CType (typeName) | Child table (from config.yaml) | Children | Shape / what it proves |
|---|---|---|---|---|
| B01 | `text` (core) | — | — | header "Inline records in the Visual Editor" (header_layout 2), subheader, 3 short paragraphs incl. `{{page:content-types/rte-combinations}}`; chooser basics (header_layout, frame_class, categories) |
| B02 | `desiderio_featurecards` | `feature_cards_items` (own; title + RTE description; minitems 1 after WP6) | 3 | card grid; TCAdefaults example; RTE child |
| B03 | `desiderio_accordion` | `accordion_items` (title, RTE content, Checkbox open_by_default) | 3 | checkbox child field via Item settings |
| B04 | `desiderio_faq` | SHARED `desiderio_qa_item` (tablenames + fieldname `desiderio_faq_items`) | 4 | shared table |
| B05 | `desiderio_pricingfaq` | the SAME `desiderio_qa_item` (fieldname `desiderio_pricingfaq_question_items`) | 3 | per-parent/field listing on a shared table |
| B06 | `desiderio_tabs` | `tabs_items` (minitems 2; tab_label + tab_content required; tab_icon Select) | 3 | required text fields; min guard |
| B07 | `desiderio_timeline` | `timeline_items` (step, title, RTE content, link_text, link Link) | 4 | link child field |
| B08 | `desiderio_stats` | `stats_stats` (value, label, RTE description; parent Select columns '4') | 4 | compact stat grid |
| B09 | `desiderio_statsinline` | SHARED `desiderio_label_value` (minitems 2) | 3 | compact strip on a shared table |
| B10 | `desiderio_productspecs` | the SAME `desiderio_label_value` + parent File (`ShowcaseBlocks::screenshot(...)`) | 5 | label/value table rows |
| B11 | `desiderio_definitionlist` | `definition_list_items` (term, definition) | 4 | compact term list, no RTE |
| B12 | `desiderio_featurechecklist` | `feature_checklist_items` (title, description_text) | 5 | plain list, no RTE |
| B13 | `desiderio_featurelist` | `feature_list_items` (icon Select, title, RTE description) | 3 | spacious list with icons |
| B14 | `desiderio_changelog` | `changelog_items` (version, date, title, RTE description, tag Select; tag values from config.yaml) | 3 | the chip-misattribution regression ("date") |
| B15 | `desiderio_datatable` | `data_table_columns` + `data_table_rows` → NESTED `data_table_cells` | 3 cols, 3 rows × 3 cells | two-level nesting, table-like |
| B16 | `desiderio_comparisontable` | `comparison_table_columns` (minitems 2) + `comparison_table_features` → NESTED `comparison_table_tier_values` | 2 cols, 3 features × 2 | nested + min 2 |
| B17 | `desiderio_pricingthreetier` | `pricing_three_tier_plans` (minitems 3 = maxitems 3) → NESTED `pricing_three_tier_plan_features` (1..8) | 3 plans (Starter €0, Studio €590/year, Agency €1,990/year — canon prices), 3/4/3 features | min = max: Add AND Delete denied; nested limits; is_recommended Checkbox + button_link Link |
| B18 | `desiderio_team` | `team_members` (image File auto-filled from the People cast, name, role, RTE bio, profile_link) | 3 (Lea Fischer, Mara Weiss, Arjun Mehta) | image children; a new member has no image until the backend form |
| B19 | `desiderio_testimonialgrid` | SHARED `desiderio_quote_item` (quote, author_name, author_title, author_image File) | 3 | shared table with File child |
| B20 | `desiderio_gallery` | `gallery_items` (image File REQUIRED, title, description) | 3, no links | reason `file` (Add denied) |
| B21 | `desiderio_logocloud` | SHARED `desiderio_logo_item` (image File, name, link) | 4 fictional logos (`Resources/Public/Styleguide/Logos/Fictional/*-colour.svg`) | negative control: no text output → list-only |
| B22 | `desiderio_legallinks` | `legal_links_link_items` (label, link Link, separator Select) | 3 (legal slugs from `ShowcaseLegalPages::pages()`) | compact link list |
| B23 | `desiderio_officehours` | `office_hours_hours_items` (day, time) | 5 (Mon–Thu 8:00–18:00, Fri 8:00–16:00) | most compact two-field list |

Coverage: card grid B02; accordion/FAQ B03–B05; tabs B06; timeline B07; stats B08–B09; team B18; testimonials B19;
table-like B10, B15–B16, B23; pricing B17; compact lists B09, B11–B12, B22–B23; spacious lists B13–B14; RTE children
B02–B08, B13–B14, B17–B19; images B18–B21; selects B06, B13–B14, B22; checkboxes B03, B16–B17; nested B15–B17; shared
B04–B05, B09–B10, B19, B21; limits B06/B09/B16 (min 2), B17 (3/3, 1..8), B20 (file).

**Commands** (the seeder refuses inside a workspace and writes live rows only; `desiderio:styleguide:seed --pages` reseeds
one planned page and ends with `Reseeded pages: <uid>`; every reseed deletes the page's translations):

```
ddev exec php packages/site_package/Build/Scripts/lint-copy.php --site=desiderio --warnings \
  --php='Webconsulting\Desiderio\Data\Showcase\ShowcaseVisualEditorInlinePage::content'   # read-only
ddev exec vendor/bin/typo3 desiderio:styleguide:seed --pages=/content-types/visual-editor-inline --dry-run
ddev exec vendor/bin/typo3 desiderio:styleguide:seed --pages=/content-types/visual-editor-inline     # → Reseeded pages: <uid>
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=<uid> --languages=de,zh,hu --dry-run --missing=-
#   → append every listed English string to .../Translations/desiderio/{de,zh,hu}.json "strings" (append only)
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=<uid> --languages=de,zh,hu --fail-on-missing
ddev exec vendor/bin/typo3 cache:flush
ddev exec vendor/bin/typo3 sitepackage:content:audit --site=desiderio --details --fail-on-errors   # site-wide, no page filter
ddev mysql -N -e "SELECT uid FROM pages WHERE slug='/content-types/visual-editor-inline' AND sys_language_uid=0 AND deleted=0"  # VEE_INLINE_PAGE_ID
```

Production (lean, after deploy, in the web container): the same seed and translate commands with `--allow-production`,
then `cache:flush` — seconds, far under the 30-minute rule; no full reseed, no full translate.

## 6. Verification

| Layer | What | Runs without the backend password |
|---|---|---|
| VEE PHP | `composer validate --strict`, `lint:php`, `cgl:check`, `phpstan` (level 8, no baseline), `test:unit` (8.4/8.5), `test:functional` (sqlite local, MariaDB CI; `/opt/homebrew/opt/php@8.5/bin` first in PATH) | yes |
| VEE functional, chooser (`FieldOptionsMiddlewareTest`) | `workspaceVersionIsCompiledInsteadOfTheLiveRecord`, `translatedRecordInAWorkspaceShowsTheDraftValues`, `versionOfAnotherWorkspaceIsNotFound`, `deletePlaceholderIsNotFound`, `editorWithoutContentEditPermissionOnThePageIsRefusedWithReasonPage`, `editLockedPageIsRefused`, `editLockedRecordIsRefused`, `editorOutsideTheEditingStageIsRefused`, `authModeRemovesSelectItemsForTheEditor`, `typelessChildTableGetsGroupsFromTypeOne`, `valuesComeFromTheCompiledRow`, `radioFieldIsOffered`, `countryFieldIsOffered` | yes |
| VEE functional, inline payload (same test class, fixture extension `Tests/Functional/Fixtures/Extensions/vee_test_collections` with PLAIN TCA: `tx_veetest_item` own child, minitems 1/maxitems 3; `tx_veetest_shared` with `foreign_table_field tablenames` + `foreign_match_fields fieldname`; editor group fixture; `sys_workspace.csv`) | `inlineFieldsAppearOnlyWhenTheUserSettingIsOn`, `aCollectionListsItsChildrenInSortingOrderWithLiveUids`, `editorWithoutTablesModifyOnTheChildTableGetsNoInlineField`, `editorWithoutNonExcludeFieldsOnTheParentColumnGetsNoInlineField`, `aCollectionAtMinitemsOffersNoDelete`, `aCollectionAtMaxitemsOffersNoCreate`, `aSharedChildTableListsOnlyTheChildrenOfThisParentAndField`, `workspaceChildrenCarryFormUidsAndLiveUids`, `aDeletePlaceholderChildIsNotListed`, `aNewPlaceholderChildGetsTheDiscardVerb`, `aConnectedTranslatedParentOffersNoCreateButSync`, `aChildTypeWithARequiredFileFieldOffersNoCreate`, `liveCreateWithoutTheHiddenRightIsDenied` | yes (plain TCA removes the "Content Blocks compiles under the testing framework" assumption) |
| VEE functional, writes (`PersistenceControllerTest`) | the 12 cases of 3.9 + `theResponseCarriesTheUidsOfNewRecords` | yes |
| VEE unit | `EditAccessServiceTest` (one case per reason, stubs), `InlineRecordConfigurationServiceTest` (defaults, per-table opt-out, requireWorkspace, translatedParents), `FrontendConfigurationTest` extensions, `DataHandlerServiceTest::runReturnsSubstNEWwithIDs`, `DataHandlerServiceTest::inlineLocalizeSynchronizeNeedsFieldLanguageAndAction` | yes |
| VEE JS (`node --test`) | `inline-record-commands.test.js` (insert after / append / parent column kept / delete removes the form uid and targets the live uid / newRecordId matches the server regex), `save-request.test.js` (JSON parse, errorLog, 409 reason, never touches document), `related-fields.test.js` ("outputs of another record yield no fields"), `config.test.js` (off by default), `inline-records.test.js` ("confirmDelete resolves false → no fetch, no addCmd") | yes |
| VEE Playwright, components project (login-free, import-map stubs) | `confirm-component.spec.js` (ok → true, cancel/Escape → false, native fallback when `topWindow === window`), `field-chooser-inline-component.spec.js` (mocked payload: list renders in tabs and sections and scoped; disabled reasons; Discard verb; Add after; keyboard: Tab reaches every row button, Escape returns focus to the opener) | yes |
| VEE Playwright, enhancements project (`Tests/E2E/inline-records.spec.js` @inline; `VEE_INLINE_PAGE_ID`, also `VEE_PAGE_ID`/`VEE_RTE_PAGE_ID` = the same uid; `support/db.js` runs `ddev mysql -N -e` read-only) | 'inline-record controls render only when the user setting is on', 'adding a card re-renders the element and focuses the new title (visible: height > 0)', 'typing into the new item and saving persists title and hidden=0', 'deleting asks for confirmation; cancel keeps the item and focus; confirm removes it after the refresh', 'the last two tabs cannot be deleted and the three-tier plans cannot be added or removed', 'a gallery item cannot be added inline (file reason)', 'a shared-table list shows only this element\'s items and the created row carries the owner column', 'item settings open the child record and show the nested features list', 'a child added in workspace 4 is a draft (t3ver_wsid 4, t3ver_state 1)', 'a second create in workspace 4 leaves live child rows untouched', 'the translated page offers Sync translations on the default language and no create on /de/', 'deleting a content element asks for confirmation; deleting a modified element in a workspace creates a delete placeholder; deleting a translated element keeps the default record', 'hovering a collection child output never opens the parent settings', 'the field chooser works in tabs and in sections mode on this page' (two runs, the owner switches the user setting) | **no** — the owner runs `VEE_INLINE_PAGE_ID=<uid> VEE_BACKEND_PASSWORD=… composer test:e2e -- --grep @inline` from `VEE`; `auth.setup.js` refuses without the password; the stored session file is a 2026-10-10 cookie |
| Restricted editor | 'a restricted user sees no controls and the endpoint answers 403 with a reason' | **no** — needs a be_user the owner creates (D11); otherwise functional-only |
| Lab Playwright (login-free, `WT/Tests/E2E/visual-editor-inline-page.spec.js`) | page 200, h1, item counts per block, `/de/ /zh/ /hu/` translated headings, axe; rerun warm after `cache:flush` | yes |
| Desiderio | `composer test` (`ShowcaseVisualEditorInlinePageTest`: `thePageIsSeededHiddenBelowContentTypes`, `everyBlockIsADesiderioCollectionElementWithDeclaredFields`, `itemCountsRespectTheLimits`, `everyShapeIsCovered`, `twoCTypesShareOneChildTable`, `everyMediaFileExists`; `ContentBlockLimitKeysTest::noCollectionUsesCamelCaseLimitKeys`), `composer test:functional` (`SeedVisualEditorInlinePageFunctionalTest`: `thePageSeedsEveryBlockWithItsChildren`, `sharedChildTablesCarryTablenamesAndFieldname`, `nestedChildrenHangOffTheirRowNotTheElement`, `aReseedLeavesNoOrphanChildren`), `ddev exec vendor/bin/typo3 content-blocks:lint` (read-only), lint-copy, `sitepackage:content:audit --fail-on-errors` | yes (audit and lint-copy need the running DDEV, not the password) |
| Desiderio SQL gate before the rename (read-only) | per collection with `minItems > 0`: count live parents with fewer children than the minimum; must be 0 (or the minimum lowered) before 4.31.0 | yes |
| Lab login | site_package unit tests incl. `BackendLoginLabelsTest`; manual `/typo3` with Accept-Language en and de: footer "Proudly running on [TYPO3 logo]" / "Läuft aus Überzeugung auf [TYPO3 logo]", trademark line names webconsulting GmbH, "More about TYPO3" expands with TYPO3.org + Donate; axe: logo announced once | yes except the manual look (owner or worker in a browser) |
| Post-deploy health | `curl -sI` the four language URLs of the page and `/typo3` (200), Coolify service healthy, last 3 versions kept | yes |

## 7. Work packages for Opus 5.5 workers

| WP | Title | Repo | Scope | Deliverables | Gates | Depends on | h |
|---|---|---|---|---|---|---|---|
| WP1 | Field chooser hardening + EditAccessService | VEE | Section 2 steps 3–6, 9, 10; `Classes/Service/EditAccessService.php` + `Enum/AccessDenial.php`; `FieldOptionsMiddleware` uid normalisation + 404 rules; `FieldOptionsService` gates, reasons, compiled-row values, recordTypeValue groups, compile flags `inlineResolveExistingChildren`/`inlineCompileExistingChildren`; radio + country (D13); `element-context-affordance.js` + `related-fields.js` use the output's own table/uid; `ve-field-chooser.js` keeps activeTab, keys tabs by index, placeholder option, reason sentences; `Configuration.rst:49-52` removed; `FrontendConfiguration` exposes `workspaceId` | changed files above; labels `frontend.fieldChooser.denied.*`; 13 functional cases (section 6), `EditAccessServiceTest`, `related-fields.test.js`; CHANGELOG "Fixed" | cgl, phpstan 8, test:unit, test:functional, test:js | — | 7 |
| WP2 | Confirm helper + vendor delete wrap | VEE | `Shared/confirm.js`; `FeatureFlags::isDeleteConfirmEnabled`; `visual-editor-patches.js` self-detecting `_delete` wrap (confirm + language-aware live uid, 3.5); `Compatibility.rst` audit row; `KnownProblems.rst` (cross-origin fallback) | confirm.js, patch, `confirm-component.spec.js`, functional `deletingAContentElementUsesTheLiveUidOfTheDefaultLanguage` + `deletingATranslatedElementKeepsTheDefaultLanguageRecord` (PersistenceController with version/translation rows) | cgl, phpstan, test:js, test:functional, components project green | — | 3 |
| WP3 | Inline records server side | VEE | switches (3.1), `InlineRecordConfigurationService`, `InlineRecordOptionsService` (payload type inline, 3.3), `InlineRecordWriteGuard` + `inlineRecord` envelope in `PersistenceController`, `DataHandlerService::run` result object + widened `validateCmd`, `Enum/InlineRecordReason`, fixture extension `vee_test_collections` (plain TCA), labels EN/DE, `locallang_setup` labels | 13 payload cases + 13 write cases (section 6, 3.9), `InlineRecordConfigurationServiceTest`, `FrontendConfigurationTest` extensions, `DataHandlerServiceTest` additions, `config.test.js` | cgl, phpstan 8, test:unit, test:functional (sqlite + MariaDB CI), test:js | WP1 | 9 |
| WP4 | Inline records client side | VEE | `ve-field-chooser.js` `#renderInlineField` + `refresh()`, action-bar button (`Frontend/index.js`), `Frontend/inline-records.js`, `Shared/inline-record-commands.js`, `Shared/save-request.js`, `element-refresh.js` `refreshElement` export + child→parent mapping, createHidden + staged unhide, showEmpty + focus, Sync translations button (D12), `Shared/icons.js`, notifications, `README.md` env table (`VEE_INLINE_PAGE_ID`) | modules above; `inline-record-commands.test.js`, `save-request.test.js`, `inline-records.test.js`; `field-chooser-inline-component.spec.js`; `Tests/E2E/inline-records.spec.js` (section 6); `support/db.js` | cgl, phpstan, test:js, components project green; E2E @inline green on the lab page (needs WP8 seed + owner password) | WP2, WP3 | 9 |
| WP5 | VEE docs + release 1.5.0 | VEE | `Usage.rst` ("Adding and deleting entries", Reset semantics, keyboard), `Configuration.rst` (confval `tx_visualeditor_inlineRecords`, TSconfig block, rights for editor groups, workspace behaviour), `Developer.rst` (payload type inline, envelope, JS API, confirmDelete; still two endpoints), `Installation.rst` (two flags), `KnownProblems.rst`, `Compatibility.rst`, `Index.rst` 1.5.0; CHANGELOG `## [1.5.0] — <date>` Added/Fixed/Changed; tag `v1.5.0`; GitLab mirror `Build/Scripts/mirror-to-gitlab.sh` | docs, changelog, tag on GitHub + GitLab | full CI green (validate, audit, lint, cgl, phpstan, unit 8.4/8.5, js, functional MariaDB); docs render (typo3-docs skill) | WP1–WP4 | 2 |
| WP6 | Desiderio `minItems`/`maxItems` → lowercase | DES | rename in every `ContentBlocks/ContentElements/*/config.yaml` and RecordTypes consumers (~145 collections; File `maxitems` already lowercase); keep `ContentBlockDefinitionRegistry.php:198-199` reading both; `Tests/Unit/ContentBlockLimitKeysTest::noCollectionUsesCamelCaseLimitKeys`; read-only SQL gate (section 6); CHANGELOG note "FormEngine now enforces minimums" | renamed configs, test, SQL report | `content-blocks:lint` clean, composer test, SQL gate = 0 violations | — | 3 |
| WP7 | Desiderio test page + tests + memories | DES + `MAIN/packages/site_package` (translation JSON only) | `ShowcaseVisualEditorInlinePage.php` (23 blocks, section 5), registration in `subpages()`, unit + functional tests, lint-copy run, `translate --dry-run --missing=-`, de/zh/hu entries appended, lab login-free spec `WT/Tests/E2E/visual-editor-inline-page.spec.js` | page definition, tests, memory entries, spec | composer test, test:functional, lint-copy clean, `StyleguideSeedCommandTest` + `ShowcaseCardLinksTest` still green | WP6 | 7 |
| WP8 | Desiderio 4.31.0, lab lock bump, seed, translate, audit, E2E | DES + lab | tag 4.31.0 (+ mirror); lab lock bump for VEE 1.5.0 + desiderio 4.31.0 per `lab-single-package-install.md` (`composer update --no-install` for the two packages, `git archive` the tags into vendor, `.lab-links` relink, `rm -rf /tmp/opcache/*` + `dump-autoload`, check `PackageArtifact.php`), `cache:flush`; seed + translate + audit (section 5 commands); run lab spec and VEE @inline suite; fix round | tag, lock commit, seeded + translated page (uid in the memory note), green E2E, memory note `vee-inline-records.md` | audit `--fail-on-errors`, @inline green, lab spec green, warm rerun after flush | WP5, WP7 | 5 |
| WP9 | Lab login credits | WT `packages/site_package` | section 4 changes; `BackendLoginLabelsTest`; memory update; entity name confirmed (D7) | XLF EN/DE, layout key, css comment, test | site_package unit tests, lab root phpstan 8, manual EN/DE look + axe | — | 2 |
| WP10 | Deploy, lean prod run, health, memory | lab root (Coolify via push to main) | list `origin/main..HEAD` as its own step (shared-worktree rule), merge, push GitHub + GitLab, Coolify deploy, prod web container: seed `--pages` + translate `--pages` with `--allow-production` + `cache:flush`; health; keep last 3 versions; sales-plan release log entry; memory notes | deployed lab (VEE 1.5.0, desiderio 4.31.0, new credits), prod page in 4 languages, health log | Coolify green, curl 200 on four URLs + `/typo3`, prod run < 5 min | WP8, WP9 | 2 |

Total ≈ 49 h. **Parallel schedule:** Wave A (parallel): WP1, WP2, WP6, WP9. Wave B: WP3 (after WP1), WP7 (after WP6).
Wave C: WP4 (after WP2, WP3). Wave D: WP5 (after WP1–4), WP8 (after WP5, WP7). Wave E: WP10 (after WP8, WP9). Critical
path WP1 → WP3 → WP4 → WP5 → WP8 → WP10 ≈ 34 h. VEE and Desiderio work on separate branches/worktrees; the lab is
touched only by WP8–WP10. Every commit ends with the attribution line from the system reminder; no PR/push to third-party
repos (no-upstream-contributions); workers on Opus 5.5 (subagents rule).

## 8. Release and deploy

- **Versions:** webconsulting/visual-editor-enhancements 1.4.4 → **1.5.0** (minor: new user setting, payload type
  `inline`, `inlineRecord` envelope, confirm, chooser fixes; response gains `substNEWwithIDs`, backwards compatible);
  webconsulting/desiderio 4.30.0 → **4.31.0** (lowercase limits = FormEngine behaviour change, test page); site_package
  login ships with the lab main branch.
- **Lab lock:** `lab-single-package-install.md` — never `composer install/update` in the lab while clones are linked;
  `composer update --no-install webconsulting/visual-editor-enhancements webconsulting/desiderio`, `git archive` both tags
  into `vendor/`, `.lab-links` relink hook, `rm -rf /tmp/opcache/*` + `composer dump-autoload`, verify `PackageArtifact.php`
  keeps both ext keys, `ddev exec vendor/bin/typo3 cache:flush`.
- **Push:** GitHub + GitLab mirror for both packages (`Build/Scripts/mirror-to-gitlab.sh`), lab main to GitHub + GitLab;
  `git log origin/main..HEAD` listed as its own step before the push (foreign commits in the shared worktree ship too).
- **Quality + Coolify:** package CI green (WP5 gates), lab pipeline, Coolify auto-deploy on main (web + solr per sha,
  `docker_images_to_keep=6`, keep the last 3 lab versions).
- **Lean prod run (< 5 min, section 5):** seed `--pages` + translate `--pages` with `--allow-production` inside the prod
  web container, `cache:flush`; commit the memory JSON first; no full reseed, no full translate.
- **Health:** `curl -sI` the four language URLs of the page and `/typo3` → 200; the login shows the tagline in EN and DE;
  Coolify healthy; disk free (prod-disk traps); 3 versions kept; `referenceindex:update` once if the owner wants
  seeded (raw-SQL) children indexed.
- Then: update memories `ve-enhancements-repo-and-field-chooser.md` (1.5.0), `desiderio-4-26-menus-films-login.md`
  (trademark point), new `vee-inline-records.md` (uids, commands, traps); log the release in section 12 and in the
  open-source sales plan.

## 9. Risks

| Risk | Mitigation |
|---|---|
| Desiderio lowercase limits make FormEngine refuse saves of records below the minimum | SQL gate in WP6 must be 0 before tagging; seeded content respects the minimums; prod content checked read-only before deploy |
| Second create/delete on an already versioned parent writes onto live child rows if addressed by live uid | hard rule 3.4 step 3 + functional test `aSecondCreateInAWorkspaceWritesOnlyVersionRows`; FormEngine parity (version uid + form uids) |
| `inlineRecord` envelope: a legitimate future client staging an inline column would be refused with 400 | documented in Developer.rst; the vendor and the chooser never do it today |
| Immediate create/delete in live is irreversible | confirm text, default-off setting, createHidden, `requireWorkspace` TSconfig (D3) |
| Abandoned creates leave hidden empty rows in live | documented; cleanup deferred |
| `refreshElement` inside EXT:container / nested content areas unverified | `reloadFrames` fallback keeps correctness, loses scroll position |
| Vendor upgrade drift: `_delete` wrap and `scrollPositionId`/`uid` semantics tied to 1.10.4 | self-detect skips the confirm when upstream adds one; `Compatibility.rst` audit row; two E2E cases guard the uid rule |
| E2E needs the owner's password and a restricted editor that does not exist | rights proven functionally; owner runs the suite once per mode (section 6) |
| ~150 new English strings × 3 languages must exist before `translate --fail-on-missing` | `--dry-run --missing=-` lists them; append-only; repeat after every reseed |
| Admin uid 1 is parked in workspace 4 | E2E runs there deliberately (draft cases) and once with workspace 0 for live cases (owner switches, or `VEE_BACKEND_USER` = claude uid 9) |
| Playwright timeouts right after `cache:flush` | rerun warm (known lab trap) |
| Trademark line names "webconsulting GmbH" from memory | confirm against the Impressum before WP9 ships (D7) |
| Cross-origin site bases break the core Modal in the frame and the same-origin POST | lab is same-origin; fallback `window.confirm`; documented |

## 10. "Habe ich etwas vergessen?" — the list for the owner

1. **Content Blocks kann das nicht.** Es erzeugt nur Kind-Tabellen, TCA und ein Template-Gerüst mit `f:render.text`;
   Anlegen/Löschen im Frontend gibt es dort nicht (einzige Fundstelle: `HtmlTemplateCodeGenerator.php:78`). Alles liegt
   in unserer Erweiterung.
2. **Desiderio hat heute kein einziges Minimum.** `minItems`/`maxItems` (camelCase) in ~145 Collections wird von Content
   Blocks ignoriert; nur `minitems` zählt (Astryx macht es richtig). DataHandler prüft Minimum nie. Erst nach der
   Umbenennung gibt es eine Untergrenze – dann aber auch im Backend-Formular (SQL-Gate vorher).
3. **Der Löschknopf des Herstellers fragt nicht nach**, und im Workspace schickt er die Versions-UID, die DataHandler
   als "Verwerfen" behandelt (Entwurf weg, Live-Element bleibt). Außerdem speichert Autosave im Workspace 300 ms später.
   Beides wird in unserer Erweiterung behandelt (Confirm + sprachabhängige Live-UID).
4. **Rechte sind zweiseitig:** Redakteursgruppen brauchen jede Kind-Tabelle in `tables_modify` und die Eltern-Spalte,
   die Kind-Felder und `hidden` in `non_exclude_fields` (Content-Blocks-Felder sind `exclude=1`). Die Gruppe "Flue Agents
   (sandboxed)" hat nichts davon, kein `web_edit` und kein Mitglied – ein Test-Redakteur muss angelegt werden.
5. **Übersetzungen:** Desiderio-Kinder sind verbunden (`l10n_parent`). Löschen in der Standardsprache löscht alle
   Übersetzungen; Anlegen erzeugt keine Übersetzungen (kein `allowLanguageSynchronization`). Plan: nur Standardsprache +
   Knopf "Übersetzungen synchronisieren" (Core-Kommando `inlineLocalizeSynchronize`).
6. **Workspaces sind die halbe Wahrheit:** Anlegen = NEW_PLACEHOLDER, Löschen = DELETE_PLACEHOLDER, Live = sofort und
   endgültig; der Admin-Benutzer steht in Workspace 4, Lab-Tests laufen also stillschweigend als Entwurf.
7. **Ein neuer leerer Eintrag ist im Editor unsichtbar** (der Hersteller blendet leere Felder aus) und in Live sofort
   für Besucher sichtbar. Lösung: `TCAdefaults` (Core), `showEmpty` + Fokus, versteckt anlegen + Einblenden beim ersten
   Speichern.
8. **Pflichtfelder** (Tabs, Galerie-Bild) prüft nur das Backend-Formular; Text tippt man inline, ein Pflicht-Bild geht
   nur im Formular – der Knopf ist dann aus und sagt warum.
9. **Feldeinstellungen-Fehler, nach denen nicht gefragt wurde:** Kind-Felder öffnen beim Hover die Eltern-Einstellungen
   (Changelog-Datum → "Headers"), übersetzte Entwürfe im Workspace werden von der Live-Zeile kompiliert, typlose Tabellen
   ohne Gruppen, aktiver Tab springt beim Speichern zurück, fremde Werte zeigen die erste Option, Seitensperre ungeprüft.
10. **Reset kann Einträge nicht zurückholen** (sofortiger Schreibvorgang) – steht im Confirm-Text und in der Doku.
11. **Sortieren** bleibt im Backend-Formular; "Danach einfügen" gibt es, Drag nicht.
12. **Marke:** Die Zeile ist unkritisch; der Produktname "webconsulting TYPO3 Lab" ist das, was die Policy verbietet.
    Außerdem: Rechtsform (GmbH?) und Association-Mitgliedschaft entscheiden den Hinweis-Satz; das TYPO3-Schild in
    Marketing-Screenshots/Filmen des Logins ist Gold/Platin/Partnern vorbehalten.
13. **Jedes Reseed der Testseite löscht ihre Übersetzungen**; die ~150 englischen Texte brauchen vorher Einträge in drei
    Memory-Dateien; der Content-Audit hat keinen Seitenfilter (läuft seitenweit).
14. **Die versteckte Testseite ist für Solr sichtbar**, solange `no_search` fehlt (neuer `ShowcasePage`-Schlüssel nötig).
15. **Doku-Drift:** `Documentation/Index.rst` sagt 1.0.0, `Configuration.rst` beschreibt einen entfernten Fallback.
16. **Tastatur und Screenreader:** Popover-Knöpfe sind erreichbar, der Confirm gibt den Fokus explizit zurück, der neue
    Eintrag bekommt den Fokus – Teil der Abnahme; die Chip-Variante (v1.1) braucht erst einen Tastaturweg.
17. **Zweite Darstellung als Benutzereinstellung** (Design-Alternativen-Regel): Release 1 liefert eine Liste; die
    Chip-Platzierung am Eintrag wird als v1.1 pro Benutzer schaltbar.

## 11. Decisions for the owner (defaults the workers use if the owner says "recommended")

| # | Question | Recommended default | Alternatives |
|---|---|---|---|
| D1 | Delete: immediate write after the confirm, or staged in the vendor store with a visible "pending delete" state until Save? | **Immediate after confirm** (one write path for create and delete, no pending-state lifecycle, no autosave race; the confirm is the gate) | staged + pending state (second gate = Save; needs Reset/saved lifecycle, cmd-skip changes) |
| D2 | Where the controls live in 1.5.0 | **"Entries" block inside the existing field-settings popover + a scoped action-bar button**; chip actions at the entry as v1.1 behind a per-user placement setting | separate popover; chips now (needs a keyboard route first) |
| D3 | Live-workspace creates | **`createHidden = 1`**: new entry hidden until the first save; needs the `hidden` right, otherwise denied in live with reason; `requireWorkspace = 0` | visible at once (`createHidden = 0`); `requireWorkspace = 1` (no live structural writes) |
| D4 | Connected translations (all Desiderio languages) | **default-language only + "Sync translations" button** (`translatedParents = default-only`) | FormEngine parity: free-mode child per language (`translatedParents = free`) |
| D5 | Confirm on the vendor's content-element delete | **always on**, install flag `deleteConfirmEnabled` is the only switch; live-uid fix always on | per-user setting (not recommended: safety, not design) |
| D6 | Login tagline | **"Proudly running on TYPO3." / "Läuft aus Überzeugung auf TYPO3."** | "Powered by TYPO3 – inspiring people to share!" (Core slogan, unlocalized); "Made with TYPO3" |
| D7 | Trademark line entity and membership | **"webconsulting GmbH is an independent provider and is not affiliated with or endorsed by the TYPO3 Association."** — confirm the entity against the Impressum | member sentence + membership badge if webconsulting is an Association member |
| D8 | Rename "webconsulting TYPO3 Lab" → "webconsulting Lab" (login header, `SYS.sitename`, README, composer, test) | **Not in this round; decide separately** (recommended to rename, or ask trademark@typo3.org for a one-line OK) | rename now in WP9 (+1 h) |
| D9 | Desiderio `minItems` → `minitems` in this round | **Yes (WP6) with the SQL gate** | leave camelCase (no limits anywhere; the test page cannot prove min/max) |
| D10 | Test page slug and search | **`/content-types/visual-editor-inline`, `hideInNav`, no `no_search`** | add a `noSearch` ShowcasePage key (+1 h) |
| D11 | Restricted test editor | **Owner creates `ve_editor` in a new group** (tables_modify tt_content + the child tables, explicit_allowdeny for the tested CTypes, non_exclude_fields for chooser fields + inline columns + `<child>:hidden`, groupMods web_edit, db_mountpoints = test page) | rights stay functional-only |
| D12 | "Sync translations" in 1.5.0 | **Yes** (one cmd + validateCmd widening, ~1 h inside WP3/WP4) | defer to 1.5.1 |
| D13 | Add radio and country to the field chooser | **Yes** (one branch each, items already resolved by FormEngine); `foreign_table` selects stay out | defer |
| D14 | Non-Desiderio lab sites: hide `sectionIndex`/`linkToTop` (and `layout`/`space_*` where the theme ignores them) via `TCEFORM` in `config/sites/<site>/page.tsconfig` | **Not in this round; documented recommendation** | add in WP10 (+1 h, per-theme check needed) |

## 12. Release log

(empty — filled by WP5, WP8 and WP10)

## 13. Sources

Local (all read 2026-11-05 in `MAIN`): `vendor/friendsoftypo3/visual-editor` 1.10.4 (`Classes/Service/EditModeService.php:58-186`,
`ContentElementWrapperService.php:40-99`, `ViewHelpers/Render/TextViewHelper.php:129-313`, `Middleware/EditModeMiddleware.php:51-138`,
`Backend/Controller/PersistenceController.php:46-58`, `Resources/Public/JavaScript/Frontend/components/ve-content-element.js:71-80,283-326`,
`ve-editable-text.js:23,134-144,350-352`, `ve-drop-zone.js:203-220`, `stores/data-handler-store.js:49-127`,
`intercept-user-actions-guard.js:1-3,71-76`, `Backend/use-data-handler.js:11-41`, `Backend/components/ve-backend-save-button.js:140-160`,
`ve-auto-save-toggle.js:28-80`, `Shared/iframe-messaging.js:53-160`, `Shared/local-stores.js:51`);
`vendor/typo3/cms-core` 14.3 (`Classes/DataHandling/DataHandler.php:611-651, 699-868, 900-964, 1138-1193, 2544-2620, 3171-3197,
5130-5285, 5325-5402, 5540-5620, 5954-5999, 7085-7110, 7203-7303, 7743-7805, 8293-8373, 9564-9591, 9841-9960, 10000`,
`Classes/Database/RelationHandler.php:324-411, 961-1085`, `Classes/Authentication/BackendUserAuthentication.php:548-580, 607-657, 723-836, 857-907`,
`Classes/Domain/Repository/PageRepository.php:752-766`, `Classes/Domain/RecordFactory.php:201-218`, `Classes/Type/Bitmask/Permission.php:30-113`,
`Classes/Context/VisibilityAspect.php:76-79`, `Classes/Domain/Persistence/GreedyDatabaseBackend.php:144-207`, `LICENSE.txt:103-112`,
`Classes/Information/Typo3Information.php:33-116`);
`vendor/typo3/cms-backend` (`Classes/Form/FormDataProvider/TcaInline.php:50-67, 150-261, 451-492`, `DatabaseUserPermissionCheck.php:59-179`,
`DatabaseRecordTypeValue.php:54-133`, `AbstractDatabaseRecordProvider.php:51-90`, `DatabaseRowInitializeNew.php:47-290`,
`TcaInlineConfiguration.php:71-193`, `Classes/Form/FormDataCompiler.php:272-275`, `Classes/Form/Container/SingleFieldContainer.php:76-106`,
`InlineControlContainer.php:89-396`, `InlineRecordContainer.php:119-490`, `Classes/Controller/EditDocumentController.php:434-633, 1891-1931`,
`Classes/Utility/BackendUtility.php:142-170, 2760-2812, 2911`, `Resources/Public/JavaScript/modal.js`, `notification.js`,
`form-engine/container/inline-control-container.js`, `Resources/Private/Language/locallang_alt_doc.xlf:6-11,144-155`,
`Resources/Private/Layouts/Login.fluid.html:18,127-164`);
`vendor/friendsoftypo3/content-blocks` 2.4.10 (`Classes/FieldType/CollectionFieldType.php:63-64,102-150`, `Classes/Definition/Factory/ContentBlockCompiler.php:729-746`,
`Classes/Generator/TcaGenerator.php:993-1145`, `Classes/Generator/HtmlTemplateCodeGenerator.php:64-171`, `Classes/FieldType/WithCommonProperties.php:28`,
`Classes/DataProcessing/ContentBlockData.php:56-176`);
`packages/visual_editor_enhancements` 1.4.4 (`Classes/Service/FieldOptionsService.php:73-258, 316-422`, `FieldChooserConfigurationService.php:42-273`,
`FrontendConfiguration.php:39-65`, `FeatureFlags.php:16-42`, `DataHandlerService.php:20-90`, `Classes/Middleware/FieldOptionsMiddleware.php:43-93`,
`Classes/Security/EditSessionGuard.php:26-66`, `Classes/Backend/Controller/PersistenceController.php:20-52`, `Configuration/Backend/AjaxRoutes.php:7-14`,
`Configuration/RequestMiddlewares.php:29-47`, `Configuration/TCA/Overrides/be_users.php:15-57`, `Resources/Public/JavaScript/Frontend/index.js:47-135`,
`element-refresh.js:57-187`, `element-context-affordance.js:43-145`, `components/ve-field-chooser.js:70-416, 480-602, 756-796`,
`ve-field-chooser/related-fields.js:18-27`, `ve-element-library/drop-target.js:87-124`, `Shared/config.js:23-31`, `Tests/**`, `Documentation/*.rst`, `CHANGELOG.md`);
`packages/desiderio` 4.30.0 (`Classes/Data/StyleguideShowcasePages.php:48-72, 425-447`, `Classes/Data/Showcase/ShowcaseBlocks.php:14-52, 163-204`,
`Classes/Command/SeedStyleguidePagesCommand.php:220-303, 342-403, 612-638, 804-826, 918-934`, `Classes/Seeding/CollectionRecordSeeder.php:24-87`,
`StarterContentBuilder.php:47-241`, `StyleguideFixtureResolver.php:400-403`, `DesiderioContentCleaner.php:29-62`, `CollectionCleanupService.php:24-177`,
`Classes/Data/ContentBlockDefinitionRegistry.php:159-346`, `ContentBlocks/ContentElements/{feature-cards,accordion,faq,pricing-faq,tabs,timeline,stats,stats-inline,product-specs,definition-list,feature-checklist,feature-list,changelog,data-table,comparison-table,pricing-three-tier,team,testimonial-grid,gallery,logo-cloud,legal-links,office-hours}/config.yaml`,
`ContentBlocks/RecordTypes/*/config.yaml`, `Configuration/Sets/Desiderio/page.tsconfig:17-32`, `Tests/Unit/RteCombinationFixturesTest.php:83-92`,
`Tests/Functional/Command/SeedStyleguidePagesCommandPageFilterFunctionalTest.php:17-60`);
`packages/site_package` (`Resources/Private/TemplateOverrides/typo3/cms-backend/Layouts/Login.fluid.html:21-23, 50, 194-238`,
`Resources/Private/Language/backend_login.xlf:120-134`, `de.backend_login.xlf:142-159`, `Resources/Public/Backend/Css/login.css:439-469`,
`Classes/Backend/LoginEnvironment.php:27, 67-78`, `ext_localconf.php:90-106`, `Classes/Command/ContentTranslateCommand.php:82-91`,
`Classes/Command/ContentAuditCommand.php:52-58`, `Classes/Translation/SiteTranslator.php:25-35, 337-370, 742-776`, `Build/Scripts/lint-copy.php:1-26`,
`Resources/Private/Data/Content/canon.yaml:15-213`, `Configuration/page.tsconfig:8-11`, `Tests/Unit/Backend/LoginEnvironmentTest.php:54-71`);
lab DB (read-only): `be_groups` 1/2/6, `be_users` 1/9/13, `sys_workspace` 1/4, `pages` 505/2228/1093/1253/1605/1666/1747/666,
`tt_content` 162614/162651 + children 6050-6061, `feature_cards_items`, `desiderio_qa_item`, `desiderio_label_value` columns;
cached TCA `var/cache/code/core/tca_base_476f07096f75b6ad.php` (189 CB child tables, 347 inline columns, 0 desiderio minitems).

Web (login section): TYPO3 Association Trademark Usage Policy
https://docs.typo3.org/m/typo3/guide-policy/main/en-us/Association/TrademarkUsagePolicy.html (updated 19 May 2025);
https://news.typo3.com/article/typo3-trademark-usage-whats-allowed-and-whats-not (20 May 2025; 307 target of
https://typo3.org/trademark); https://talk.typo3.org/t/typo3-trademark-usage-what-s-allowed-and-what-s-not/6303;
brand guidelines https://typo3.com/typo3-cms/the-brand/brand-guidelines; https://typo3.com/typo3-cms/the-brand/trademarks;
https://news.typo3.com/archive/new-logos-for-typo3-association-membership-and-certified-integrators (2012);
https://typo3.org/project/trademarks → 404. Memories: `ve-enhancements-repo-and-field-chooser.md`, `ve-toolbar-switches.md`,
`prefer-library-standard-mechanisms.md`, `design-alternatives-as-user-setting.md`, `desiderio-shared-collection-tables.md`,
`desiderio-cb-collection-translation.md`, `cross-site-link-language.md`, `desiderio-site-translations.md`,
`lab-single-package-install.md`, `finish-with-commit-push-merge-deploy.md`, `desiderio-4-26-menus-films-login.md`,
`prod-runner-under-30-minutes.md`, `no-upstream-contributions.md`, `subagents-opus-only.md`, `shared-worktree-push-check.md`.
