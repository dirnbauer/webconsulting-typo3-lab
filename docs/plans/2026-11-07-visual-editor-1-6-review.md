# Adversarial review of Visual Editor Enhancements 1.6.0 — 2026-11-07

Review of the released tag `v1.6.0` (cbba30b) against the plan `2026-11-07-visual-editor-repeatables-ux-plan.md`, run as a Fable workflow: five finders (server security, Core semantics, action-bar labels, inspector/store/a11y, tests+docs honesty), two independent refuters per finding (a finding survives only when both keep it), one completeness critic. 28 raw findings, **23 confirmed**, 5 refuted. The feature is off by default (`tx_visualeditor_inlineRecords = 0`), production runs 1.6.0 with the setting off; nothing here changes production data. The confirmed findings are the backlog of **1.6.1**.

## 1. Confirmed findings

| # | Severity | Area | File:line | Title |
|---|---|---|---|---|
| 1 | high | SERVER SECURITY AND OWNERSHIP | `InlineRecordService.php:470` | Parity on connected translated parents ignores allowLanguageSynchronization: create writes an orphan child, move is a silent no-op reported as success |
| 2 | high | TYPO3 CORE SEMANTICS | `LiveSnapshot.php:47` | Workspace live-row guard misses translations Core reaches via l10n_source: structural action on a connected translation versions its sibling translation and renumbers its live entries undetected (200, firstVersion=false) |
| 3 | high | CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y | `element-refresh.js:102` | Deferred entry never reaches the page after Save when the element's only staged data is inline-covered text |
| 4 | high | CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y | `collections.js:97` | Textarea label fields are edited in a single-line <input>: line breaks are silently dropped and staged on the first keystroke |
| 5 | medium | TYPO3 CORE SEMANTICS | `InlineRecordService.php:174` | Staged visibility/title save in a workspace is not 'the child alone': Core also versions the child's nested inline/file members and renumbers their live sorting + tstamp, unguarded and regardless of firstVersion = 0 |
| 6 | medium | TYPO3 CORE SEMANTICS | `InlineRecordService.php:465` | firstVersion pre-check assumes Core synchronizes translations for every parent table; for child tables without l10n_mode=exclude/allowLanguageSynchronization (all desiderio Content Blocks) nested collections are refused 'firstVersion' and report a draft Core never creates |
| 7 | medium | CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY | `add-entry.js:75` | Add entry opens the entries panel without a trigger: in the anchored layout the panel detaches from the element and the Entries button's aria-expanded stays false |
| 8 | medium | CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY | `element-field-actions.js:136` | Entries click does nothing when the capability fetch fails (no panel, no reason, no aria-disabled) |
| 9 | medium | CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y | `ve-field-chooser.js:267` | Docked layouts (inspector/tray) lose the trigger after every structural action: Escape returns focus nowhere and the new Entries button says aria-expanded=false while open |
| 10 | medium | CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y | `add-entry.js:75` | Anchored layout opened via the bar's Add entry has no trigger: it does not scroll with the element and leaves aria-expanded/focus return undefined |
| 11 | medium | TESTS, DOCS AND CLAIMS HONESTY | `Configuration.rst:94` | Documented install-wide defaults and the labels 'restore 1.5' recipe (setup.default.tx_visualeditor_*) never reach existing users |
| 12 | low | TYPO3 CORE SEMANTICS | `InlineRecordService.php:625` | mayWrite() ignores displayCond HIDE_FOR_NON_ADMINS, which DataHandler drops silently like an exclude field — canHide/canEditLabel and create defaults can report success for a write that never persists |
| 13 | low | CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY | `action-bar-labels.js:106` | Caption follows a stale pin instead of the keyboard focus after Tab inside the bar |
| 14 | low | CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY | `FrontendConfiguration.php:75` | An empty collection index is serialised as [] and read as 'no index', so the hover discovery requests of 1.5 come back |
| 15 | low | CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY | `action-bar-labels.js:183` | The vendor-renders-labels self-check also drops the base rules that make [hidden] and aria-disabled work |
| 16 | low | CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y | `ve-field-chooser.js:521` | Contradictory workspace copy with the shipped default firstVersion = 0: the strip promises a first draft while every structural control is refused for exactly that reason |
| 17 | low | CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y | `ve-field-chooser.js:827` | Title baseline is not byte-identical to the page editable's when the stored label contains a literal <br> |
| 18 | low | CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y | `field-chooser-component.spec.js:384` | Component spec does not cover the paths that fail above |
| 19 | low | TESTS, DOCS AND CLAIMS HONESTY | `guides.xml:15` | Documentation does not render warning-free: guides.xml lacks interlink-shortcode; Index.rst version marker is stale |
| 20 | low | TESTS, DOCS AND CLAIMS HONESTY | `Installation.rst:66` | Installation and README list four install-wide flags; the fifth (inlineRecordsEnabled) is documented only inside Collections.rst |
| 21 | low | TESTS, DOCS AND CLAIMS HONESTY | `Compatibility.rst:258` | Compatibility version-support table and README requirements stop at 1.4.x / Visual Editor 1.10.3 |
| 22 | low | TESTS, DOCS AND CLAIMS HONESTY | `CHANGELOG.md:165` | CHANGELOG says the components CI job runs on every push; the workflow runs on pushes to main and tags only |
| 23 | low | TESTS, DOCS AND CLAIMS HONESTY | `KnownProblems.rst:116` | KnownProblems understates the first-version refusal: move and remove are refused in the payload, not only after a 409 |

### 1. Parity on connected translated parents ignores allowLanguageSynchronization: create writes an orphan child, move is a silent no-op reported as success

**Severity:** high · **Area:** SERVER SECURITY AND OWNERSHIP · **Where:** `Classes/Service/InlineRecordService.php:470`

**Claim.** The parity path (translatedParents = parity, the 1.6.0 default) only refuses connected translated parents for l10n_mode = exclude (lines 469-477); it never looks at the inline column's behaviour.allowLanguageSynchronization or the translation row's l10n_state. Core's DataMapProcessor removes every submitted value of a field whose state is 'parent' (the default when l10n_state is unset) from the datamap of a translation row and re-synchronizes the relation from the default-language parent, so the parent-list write that create/move rely on (CreateOperation.php:30, MoveOperation.php:38) is dropped, while the NEW child row is still inserted and the endpoint reports success.

**Failure scenario.** TCA tt_content.<collection>.config.behaviour.allowLanguageSynchronization = true (Content Blocks exposes this as behaviour.allowLanguageSynchronization), connected German translation tt_content 2 of tt_content 1, l10n_state unset. Payload for tt_content 2: createReason '' and sortReason '' (both offered). move {childUid: 4, afterUid: null} -> HTTP 200 {moved: {uid: 4, position: 1}}, but the stored order stays 2,4,5 (sorting 1,2,3), tt_content 2's relation count is rewritten from 3 to 2 and l10n_state {"vee_items":"parent"} is written. create -> HTTP 200 {created: {uid: 6, ...}}, but row 6 is stored with owner 0, slot '', sorting 0: an orphan never listed by any collection; the inspector then tries to focus an entry that is nowhere. FormEngine renders such a field read-only with the localization-state selector instead, so this is not parity.

**Suggested fix.** In InlineRecordService::unsupported() (and the per-child canHide/canEditLabel path is unaffected) refuse structure on a translation row when the parent column has behaviour.allowLanguageSynchronization and \TYPO3\CMS\Core\DataHandling\Localization\State::fromJSON($table, (string)$form['resolvedRow']['l10n_state'])->isParentState($field) || isSourceState($field) (i.e. not 'custom'), with a reason such as 'translation' or a new 'synchronized' that the inspector explains ('follows the default language; switch the field to custom in the backend form'); mirror the same rule in InlineTranslations::syncPreflight (reason 'translation') and FieldOptionsService::isEditableInBackendForm. Add a functional test that sets behaviour.allowLanguageSynchronization = true on vee_items and asserts 409 for create/move/delete on tt_content 2 with every row unchanged. Document the rule next to the l10n_mode = exclude sentence in Collections.rst.

### 2. Workspace live-row guard misses translations Core reaches via l10n_source: structural action on a connected translation versions its sibling translation and renumbers its live entries undetected (200, firstVersion=false)

**Severity:** high · **Area:** TYPO3 CORE SEMANTICS (workspaces, translations, DataHandler) · **Where:** `/Users/dirnbauer/projects/_wt/vee-integration/Classes/Service/LiveSnapshot.php:47`

**Claim.** LiveSnapshot::capture() takes as roots only the live parent and rows whose transOrigPointerField points at it (LiveSnapshot.php:47, 241), and the firstVersion flag / refusal is only evaluated for translationMode 'default' (InlineRecordService.php:415, 465). Core's DataMapProcessor, however, pushes a datamap entry into every dependent translation reached via l10n_parent OR l10n_source (DataMapProcessor.php:1146-1171 'or(...$ancestorPredicates)', grand-child → STATE_SOURCE at 1009-1011, populateTranslationItem iterates SCOPE_PARENT and SCOPE_SOURCE at 386-408, plain exclude-field values are written unconditionally at 445-446; tt_content has 2 l10n_mode=exclude columns so isApplicable() at 1519-1526 is true). In a workspace that datamap entry auto-versions the sibling translated parent (DataHandler.php:927) together with its entries, whose live sorting/tstamp Core renumbers (copyRecord_processRelation 4074 → insertNewCopyVersion 3879 → writeForeignField RelationHandler.php:1060, 1073). None of those live rows is in the snapshot, so the strict comparison passes.

**Failure scenario.** Element en(1) with connected de(2, l18n_parent 1) and hu(3, l18n_parent 1, l10n_source 2 — translated from German, a standard TYPO3 workflow). Workspace 1, firstVersion = 0, de and its entry already drafted, hu has no draft. Editor adds an entry on the de element: payload says translationMode connected, createReason '', workspace.firstVersion false; endpoint returns 200 with firstVersion false. Reproduced on SQLite (disposable test extending InlineRecordTestCase, PHP 8.5): hu parent got a draft (t3ver_oid 3, ws 1), both hu entries got drafts, live hu entry sorting 5,9 → 1,2 and tstamp = EXEC_TIME, no rollback. The documented strict contract (Collections.rst:118-122 'rolls back with workspaceLiveChange if a single byte differs') and the firstVersion = 0 promise (Collections.rst:134-135 'refuses these actions before any write') are both broken; the inspector never announces the draft.

**Suggested fix.** Compute the dependency set the way DataMapProcessor does: starting from the acting live record, collect live rows whose transOrigPointerField OR translationSource field points at any collected record (transitively, per table, only for tables where DataMapProcessor::isApplicable() would be true: l10n_mode=exclude or allowLanguageSynchronization columns), and (a) use that set as LiveSnapshot roots, (b) feed it into translatedParentsNeedFirstVersion() for connected parents too (drop the translationMode === 'default' gate at InlineRecordService.php:415/465) so firstVersion = 0 refuses and firstVersion = 1 masks/announces correctly. Add a functional test with a l10n_source chain (en → de → hu) acting on de; update Collections.rst:124-132 to name 'translations Core synchronizes (via l10n_parent or l10n_source)'.

### 3. Deferred entry never reaches the page after Save when the element's only staged data is inline-covered text

**Severity:** high · **Area:** CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y · **Where:** `Resources/Public/JavaScript/Frontend/element-refresh.js:102`

**Claim.** The scoped pending rule defers the element refresh with the promise "Saved. The page shows it after you save your text.", but the post-save refresh (handleSaved) skips every record whose staged fields are all covered by a page editable, so the structural change written on the server is never rendered after Save/Save now.

**Failure scenario.** Inspector open, editor types into row 2's title (which is a ve-editable-text on the page), then clicks "Add feature card" (or the bar's Add entry / Add after). The create is posted and listed; refreshElement() is skipped because items:2 has staged data; the status says the page shows it after Save. Editor presses Save now -> doSave -> saveEnded -> markSaved(). handleSaved({items:{2:{title}}}) finds isInlineCovered('items','2','title') true, continues, staleWrappers is empty and returns at line 115: no fetch, no swap, no reload. The new entry (uid 8) is still absent from the page; the only way out is a manual frame reload. The same deferral with a staged eye (hidden is not inline-covered) does refresh, which shows the asymmetry.

**Suggested fix.** Record the deferred root when refreshElement()/the bar path skip the swap (e.g. `deferElementRefresh(table, uid)` in element-refresh.js, a Set of wrapper selectors) and make handleSaved() add those wrappers to staleWrappers regardless of inline coverage (clear the set on success). Alternatively let the inspector call refreshElement(rootRecord) on the store 'saved' event when its last action was deferred. Add a spec step after `Save now`/markSaved asserting the new entry is on the page.

### 4. Textarea label fields are edited in a single-line <input>: line breaks are silently dropped and staged on the first keystroke

**Severity:** high · **Area:** CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y · **Where:** `Resources/Public/JavaScript/Frontend/components/ve-field-chooser/collections.js:97`

**Claim.** The server offers TCA type=text (Content Blocks Textarea) columns as labelField, the vendor renders them on the page as multi-line editables (allowNewlines), but the inspector's title control is <input type="text">, whose value sanitization strips CR/LF; the stripped value is then staged as the new title.

**Failure scenario.** A pricing feature (`pricing_plan_features.text`, Textarea labelField) stored as "Line one\nLine two" (entered on the page, where Enter is allowed). Editor opens Entries and appends "!" to that row's title: live(ctx.title) sets .value -> browser stores "Line oneLine two"; @input stages event.target.value = "Line oneLine two!"; the page editable mirrors it; Save writes the flattened text. The line break is lost without any indication. Also the input cannot show or enter a line break at all, so the row misrepresents the stored value from the first render.

**Suggested fix.** Expose the label column's type in the payload (e.g. `labelMultiline: true` for type=text) and render a one-row auto-growing <textarea> (Enter commits only with Ctrl/Cmd, Shift+Enter inserts a break) for it; or restrict labelField to type=input server-side so Textarea labels fall back to the contextual form. Add a spec with a multi-line labelValue asserting the staged value keeps "\n".

### 5. Staged visibility/title save in a workspace is not 'the child alone': Core also versions the child's nested inline/file members and renumbers their live sorting + tstamp, unguarded and regardless of firstVersion = 0

**Severity:** medium · **Area:** TYPO3 CORE SEMANTICS (workspaces, translations, DataHandler) · **Where:** `/Users/dirnbauer/projects/_wt/vee-integration/Classes/Service/InlineRecordService.php:174`

**Claim.** canHide/canEditLabel come from writeReason() (InlineRecordService.php:373-390) which knows nothing about workspaces, first drafts or nested members, and the generic /visual-editor/save path (InlineGenericSaveGuard + DataHandlerService) has no live snapshot. Core's auto-versioning of the child (DataHandler.php:927 → versionizeRecord 6595 → copyRecord_raw 3783) recurses into the child's own inline/file relations (copyRecord_processRelation, DataHandler.php:4060-4080: versionizeRecord($v['table'], $v['id'], …) with workspaceOptions) and the copied inline value of live ids is pushed through checkValue (3879) → remap stack → RelationHandler::writeForeignField, which renumbers the LIVE nested rows (RelationHandler.php:1060 '$updateValues[$sortby] = ++$c', 1073 tstamp). The documentation states the opposite: 'Core versions the entry alone and needs no parent draft, whatever firstVersion says' (Collections.rst:151-152) and 'In a workspace Core versions the child alone' (Collections.rst:515-516); the plan (4.1, 4.4) relies on the same claim.

**Failure scenario.** Site keeps the default firstVersion = 0 (no live bookkeeping wanted). Workspace editor opens a pricing element whose plan row owns feature rows (nested collection) and toggles the plan's eye in the inspector, then saves. Reproduced on SQLite: createReason 'firstVersion' (structural actions refused) but canHide true; the generic save returns 200, the parent gets no draft, the plan and BOTH nested features get drafts, and the live features' sorting 5,7 is rewritten to 1,2 with tstamp = EXEC_TIME. The site opted out of exactly this class of live write and the inspector reports a plain staged change.

**Suggested fix.** Treat a staged write on a live child as a first version of that child: in collection(), when workspaceId > 0, the child row is live, and LiveSnapshot::needsFirstVersion($childTable, liveUid, ws) is true, set hideReason/labelReason = 'firstVersion' unless TSconfig firstVersion = 1 (and announce the draft); alternatively run the generic save of owned children through the same transaction + LiveSnapshot guard. Correct Collections.rst:151-152 and 515-516 to 'Core versions the entry together with its own nested entries and media (renumbering their live sorting and tstamp)'. Add the functional test above (child with nested members, firstVersion = 0, staged hidden).

### 6. firstVersion pre-check assumes Core synchronizes translations for every parent table; for child tables without l10n_mode=exclude/allowLanguageSynchronization (all desiderio Content Blocks) nested collections are refused 'firstVersion' and report a draft Core never creates

**Severity:** medium · **Area:** TYPO3 CORE SEMANTICS (workspaces, translations, DataHandler) · **Where:** `/Users/dirnbauer/projects/_wt/vee-integration/Classes/Service/InlineRecordService.php:465`

**Claim.** unsupported() refuses with 'firstVersion' when the parent already has a draft but a connected translation has none and has live members (InlineRecordService.php:464-466 via translatedParentsNeedFirstVersion 429-437 → LiveSnapshot::needsFirstVersion 76-89), and context() reports workspace.firstVersion = true on the same condition (415), which execute() echoes as 'this action created a first draft' (683, 729). Core only pushes a datamap entry into translations when DataMapProcessor::isApplicable($table) holds, i.e. the table has l10n_mode=exclude columns or allowLanguageSynchronization columns (DataMapProcessor.php:1519-1526, State.php:71-77). tt_content qualifies (2 exclude columns in cms-frontend/Configuration/TCA/tt_content.php), but Content Blocks child tables only get those options when a block declares them (content-blocks TcaGenerator.php:449, 454) and desiderio declares none (grep over packages/desiderio/ContentBlocks: 0 hits). For such nested parents the translation is never versioned, so the refusal and the announcement are wrong.

**Failure scenario.** Workspace, firstVersion = 0. A pricing card (child row, nested parent of 'features') has a draft and so do its features; its German translation card has features but no draft. Editor opens Item settings → features of the English card: every structural action is refused with 'Your first change to this element would create a workspace draft…' although the element has a draft and Core would not touch the German card. With firstVersion = 1 the action succeeds and the response says workspace.firstVersion = true ('Draft created') while no draft exists. Reproduced on SQLite with the fixture child table tx_vee_item (no exclude/sync columns): createReason 'firstVersion', workspace.firstVersion true; after the create under firstVersion = 1: response firstVersion true, drafts of the translated nested parent = 0, translation row byte-identical.

**Suggested fix.** Gate translatedParentsNeedFirstVersion() (and the translation roots used for the firstVersion flag) on Core's applicability: only consider translations of $table when $table has a column with l10n_mode = exclude or behaviour.allowLanguageSynchronization (mirror DataMapProcessor::isApplicable / State::isApplicable, or call State::isApplicable($table) plus an exclude-column scan). Keep the translations as strict snapshot roots regardless (harmless). Add a functional test on the nested relation (parent drafted, translation undrafted) asserting createReason '' and workspace.firstVersion false.

### 7. Add entry opens the entries panel without a trigger: in the anchored layout the panel detaches from the element and the Entries button's aria-expanded stays false

**Severity:** medium · **Area:** CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY · **Where:** `Resources/Public/JavaScript/Frontend/add-entry.js:75`

**Claim.** addEntryFromBar() builds its openFieldChooser() options without `trigger` (only `anchorRect`), so VeFieldChooser.openFor() sets this.trigger = null (ve-field-chooser.js:169-172) and #position() falls back to the stale anchorRect captured at click time (ve-field-chooser.js:283). With tx_visualeditor_entriesLayout = anchored the panel is fixed at the old viewport coordinates instead of scrolling with the element as Usage.rst promises ('It scrolls with the element'), and the Entries button, whose dialog is open, keeps aria-expanded="false" (the previous trigger is even reset to false if the panel was already open via Entries).

**Failure scenario.** User setting Entries panel = anchored. Hover an element with a collection, click Add entry. The entry is created, the panel opens under the bar, focus goes to the new entry (scrollIntoView). Scroll the page by 120 px: the action bar moves by -120 px, the panel stays at top 85 px (reproduced: barDelta -120, popoverDelta 0, trigger null, entriesExpanded 'false'). Control: opening the same panel with the Entries button -> trigger 'inline-records', aria-expanded 'true', panel follows the scroll (popoverDelta -65, clamped at the viewport edge). When the new entry is below the fold the panel ends up covering unrelated content while the editor types.

**Suggested fix.** Pass the element's Entries button as `trigger` from addEntryFromBar (bar.querySelector('[data-ve-enhancement="inline-records"]'), re-queried after refreshElement because the wrapper was replaced) so #position() tracks a live element and aria-expanded is kept in sync; keep focus:false. Add a component-spec case for layout 'anchored' + Add entry + scroll.

### 8. Entries click does nothing when the capability fetch fails (no panel, no reason, no aria-disabled)

**Severity:** medium · **Area:** CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY · **Where:** `Resources/Public/JavaScript/Frontend/element-field-actions.js:136`

**Claim.** The Entries handler returns silently when refreshCollectionCapability() resolves null (fetch rejected: 401/403 expired token, 404, 5xx, network). Nothing is opened and the button is neither refused nor explained, violating 'every refused control explains why'. 1.5.1 opened the panel directly, whose own load path shows an error; add-entry.js handles the same case by opening the inspector with inlineLabel('loadError').

**Failure scenario.** The ?veFieldOptions=1 request answers 403 {error: 'Forbidden'} (e.g. expired backend session). Click Entries: reproduced state after 800 ms -> dialogs 0, open false, aria-disabled null, title null, aria-expanded 'false', no status text. The editor sees a button that ignores clicks.

**Suggested fix.** On a null capability open the inspector with message inlineLabel('loadError') (as Add entry does) or setRefused(entries, inlineLabel('loadError')) so the reason is visible; cover it in action-bar-labels-component.spec.js.

### 9. Docked layouts (inspector/tray) lose the trigger after every structural action: Escape returns focus nowhere and the new Entries button says aria-expanded=false while open

**Severity:** medium · **Area:** CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y · **Where:** `Resources/Public/JavaScript/Frontend/components/ve-field-chooser.js:267`

**Claim.** Add/Add after/Move/Remove refresh the element, which replaces the <ve-content-element> wrapper and thus the action-bar trigger; the re-binding to the replacement lives inside #position(), which returns early for docked layouts, so trigger and returnFocus stay pointed at the disconnected node in the default layout.

**Failure scenario.** Default layout (inspector). Editor opens Entries, clicks "Add after" on a row; the wrapper is swapped (new editable uid 8 appears). The new Entries button in the bar carries aria-expanded="false" although the dialog is open; pressing Escape closes the dialog but focus lands on <body> instead of returning to the Entries button (documented promise: "the focus returns to the button that opened it").

**Suggested fix.** Move the replacement lookup into a `#rebindTrigger()` that runs for every layout after a successful refreshElement()/on the bubbling `ve:element-refreshed` event (or at the end of #collectionAction), set aria-expanded on the new button and `this.returnFocus = this.trigger`. Extend the Escape spec to run after an Add after.

### 10. Anchored layout opened via the bar's Add entry has no trigger: it does not scroll with the element and leaves aria-expanded/focus return undefined

**Severity:** medium · **Area:** CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y · **Where:** `Resources/Public/JavaScript/Frontend/add-entry.js:75`

**Claim.** addEntryFromBar() opens the inspector with a static anchorRect and no `trigger`, so #position() falls back to the stale rect on every scroll, the Entries button never gets aria-expanded="true" and Escape has nothing to return focus to.

**Failure scenario.** User setting Entries panel = anchored. Editor clicks the bar's "Add entry": the entry is created and the inspector opens under the bar. Scrolling the page by 60 px leaves the panel at the same viewport position (y 85 -> 85) while the element moves away, contradicting Usage.rst:141 ("It scrolls with the element"); the bar's Entries button reads aria-expanded="false" the whole time; Escape closes without restoring focus.

**Suggested fix.** In openInspector()/addEntryFromBar(), re-query the element's `[data-ve-enhancement="inline-records"]` button after the refresh and pass it as `trigger` (set `returnFocus` to it when focus was moved to the page). Add an anchored-layout scroll assertion for the Add entry path.

### 11. Documented install-wide defaults and the labels 'restore 1.5' recipe (setup.default.tx_visualeditor_*) never reach existing users

**Severity:** medium · **Area:** TESTS, DOCS AND CLAIMS HONESTY (Visual Editor Enhancements v1.6.0) · **Where:** `Documentation/Configuration.rst:94`

**Claim.** Configuration.rst:88-98 ('Install-wide defaults for these settings are Core User TSconfig ... setup.default.tx_visualeditor_actionLabels = inline / inlineRecords = 1 / entriesLayout = tray ... A user's own choice still wins'), CHANGELOG.md:26 (restore 1.5: 'User TSconfig setup.default.tx_visualeditor_actionLabels = icons') and the plan (§2.7, §4.7, D11: setup.default.tx_visualeditor_inlineRecords = 1 for desiderio editor groups) promise an install-wide default that Core only applies to users whose uc is empty (first login ever). For every existing backend user Core 14.3.10 backfills the TCA default of each user setting into uc at the next request and never consults setup.default again.

**Failure scenario.** Integrator adds `setup.default.tx_visualeditor_actionLabels = icons` to the editor group's User TSconfig to restore the 1.5 look (or `setup.default.tx_visualeditor_inlineRecords = 1` to roll out entries editing). Every editor who has logged in before keeps `caption` (resp. entries editing stays off) because FrontendConfiguration reads be_users.uc only and uc already holds the backfilled TCA default; the extension docs give no working install-wide recipe (setup.override would work but forces the value, contradicting 'the user's own choice still wins').

**Suggested fix.** Document honestly: `setup.default.*` seeds only users who have never logged in (or who reset their settings); for an install-wide value that applies to existing editors use `setup.override.tx_visualeditor_actionLabels = icons` (which then wins over the user's own choice). Change the CHANGELOG 'Restore 1.5' cell and the plan D11 recommendation accordingly, or add a documented fallback in FrontendConfiguration/InlineRecordConfiguration that reads a tx_visualeditorenhancements default from User TSconfig when the uc value equals the TCA default.

### 12. mayWrite() ignores displayCond HIDE_FOR_NON_ADMINS, which DataHandler drops silently like an exclude field — canHide/canEditLabel and create defaults can report success for a write that never persists

**Severity:** low · **Area:** TYPO3 CORE SEMANTICS (workspaces, translations, DataHandler) · **Where:** `/Users/dirnbauer/projects/_wt/vee-integration/Classes/Service/InlineRecordService.php:625`

**Claim.** mayWrite() (InlineRecordService.php:625-630) checks readOnly, exclude and non_exclude_fields only. DataHandler::fillInFieldArray additionally skips every field whose displayCond is 'HIDE_FOR_NON_ADMINS' for non-admins (DataHandler.php:1138) before the exclude check (1141-1143), without an error-log entry. The plan's security promise (§4.8: 'DataHandler's silent exclude-field drops never masquerade as success') and the canHide definition in §4.1 are therefore incomplete.

**Failure scenario.** A child table whose hidden (or label) column carries displayCond = HIDE_FOR_NON_ADMINS (used by some sitepackages to keep editors away from visibility). A non-admin editor gets canHide = true / canEditLabel = true, stages the eye or a title, saves: HTTP 200, no errorLog, nothing written; the inspector shows the new state until the next refresh. For create in live with createHidden = 1, defaults() (598-604) sets hidden = 1 and the new row is created visible instead of hidden.

**Suggested fix.** In mayWrite(), also return false for non-admins when $GLOBALS['TCA'][$table]['columns'][$field]['displayCond'] === 'HIDE_FOR_NON_ADMINS' (string form, as DataHandler checks it); add a unit/functional case with such a column asserting hideReason 'rights' and createReason 'hidden'.

### 13. Caption follows a stale pin instead of the keyboard focus after Tab inside the bar

**Severity:** low · **Area:** CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY · **Where:** `Resources/Public/JavaScript/Frontend/action-bar-labels.js:106`

**Claim.** A click pins a button (pinned WeakMap); focus moving to another button inside the bar never unpins (focusout returns early when relatedTarget is inside the bar, focusin only sets current). When the pointer then leaves the bar, pointerleave restores the pinned button as current, so the visible caption names a button other than the focused one.

**Failure scenario.** Caption mode. Click Show (pinned, caption 'Show'), press Tab (focus-visible on Delete, caption 'Delete'), move the pointer out of the bar but inside the element: reproduced -> focused 'Delete content element' (focus-visible true), data-ve-label-current 'Show content element', shown ['Show content element'], pinned true. The caption no longer reflects the focused control (2.4.7 / 1.3.1 expectations for a label that claims to follow focus).

**Suggested fix.** In the focusin handler unpin when the focused button differs from the pinned one (or make pointerleave prefer focusVisibleButton(bar) over the pin).

### 14. An empty collection index is serialised as [] and read as 'no index', so the hover discovery requests of 1.5 come back

**Severity:** low · **Area:** CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY · **Where:** `Classes/Service/FrontendConfiguration.php:75`

**Claim.** CollectionFieldIndex::build() returns [] when no CType has an offerable collection, and the disabled branch returns [] too; PHP json_encode([]) is `[]`, which config.js collectionTypes() rejects (Array.isArray -> null) and the client then treats as 'older server, discover on interaction'. The documented 1.6 behaviour ('render from the first paint ... instead of a hover request', CHANGELOG; config.js comment 'null when the server did not send the index') does not hold for a legitimately empty index: every hovered element fires a ?veFieldOptions request, and the buttons can appear for unsupported relations that the index would have excluded (then refused on click), so two pages of the same install behave differently.

**Failure scenario.** Editor group whose allowed CTypes have no supported collections (or no modify right on the child tables), inlineRecords on: window.visualEditorEnhancements.collectionTypes === [] (reproduced: sent '[]'); hovering element 5 then element 1 issued veFieldOptions requests for uid 5 and uid 1 (reproduced: requests ['5','1']) - one request per hovered element, as in 1.5, instead of zero.

**Suggested fix.** Encode the index as a JSON object (`(object)[]` / new \stdClass when empty, or `{tt_content: {}}`) and let collectionFieldsFor() return [] for it; keep null strictly for a missing key. Adjust the PHP and node tests and the config.js doc comment.

### 15. The vendor-renders-labels self-check also drops the base rules that make [hidden] and aria-disabled work

**Severity:** low · **Area:** CLIENT - ACTION-BAR LABELS AND VENDOR INTERPLAY · **Where:** `Resources/Public/JavaScript/Frontend/action-bar-labels.js:183`

**Claim.** installActionBarLabels() returns before adopting the shared sheet when veInfo.actionBarLabels is truthy or the bar contains a .label element. The sheet's baseRules() (`.button[hidden]{display:none}`, `.button[aria-disabled="true"]{opacity:.5;cursor:not-allowed}`) are the only thing beating the vendor's `.button{display:inline-flex}`, so in that case the Entries/Add entry buttons hidden via `entries.hidden = add.hidden = true` (element-field-actions.js:148,155) render anyway and refused buttons look enabled. Latent today (1.10.4 renders no labels) but it is exactly the future the check is written for.

**Failure scenario.** A Visual Editor release sets window.veInfo.actionBarLabels = true (or renders a .label in the bar). An element of CType 'text' (no collection) with the index present: Entries and Add entry get hidden=true but are displayed because no stylesheet carries the [hidden] rule; clicking them yields aria-disabled with no visual difference.

**Suggested fix.** Adopt a base-only sheet (buildActionBarLabelCss('icons')) whenever the vendor renders labels, and skip only the label rules; or toggle visibility with a data attribute the base sheet targets.

### 16. Contradictory workspace copy with the shipped default firstVersion = 0: the strip promises a first draft while every structural control is refused for exactly that reason

**Severity:** low · **Area:** CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y · **Where:** `Resources/Public/JavaScript/Frontend/components/ve-field-chooser.js:521`

**Claim.** The context-strip hint "Your next add, move or remove creates this element's first draft in this workspace." is shown whenever the payload's workspace.firstVersion is true, which the server computes without consulting the TSconfig switch; in the default configuration the same payload carries createReason/sortReason/deleteReason = 'firstVersion', so the hint and the refusal "...this site has not enabled that here" are displayed together.

**Failure scenario.** Workspace user opens Entries on an element that has no draft yet (default firstVersion = 0). The strip says the next add/move/remove creates the first draft; directly below, Add entry, every Move and every Remove are aria-disabled with the opposite sentence, and the footer notice repeats it. The editor is told the action will happen and that it is forbidden at once. (Hint text is correct only when TSconfig firstVersion = 1.)

**Suggested fix.** Suppress the firstVersionNext hint when `field.createReason === 'firstVersion' || field.sortReason === 'firstVersion'` (or have the server send `workspace.firstVersion` only when the site allows it / add `firstVersionAllowed`), and correct the KnownProblems paragraph to say all structural controls are refused up front. Assert the hint's absence in the 409 fixture.

### 17. Title baseline is not byte-identical to the page editable's when the stored label contains a literal <br>

**Severity:** low · **Area:** CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y · **Where:** `Resources/Public/JavaScript/Frontend/components/ve-field-chooser.js:827`

**Claim.** The inspector seeds setInitialData with the raw stored value while the vendor editable seeds str_replace('<br>', "\n", value); a differing seed overwrites the store baseline, dispatches an 'initial' change and the editable rewrites its slot text to the raw form.

**Failure scenario.** A plain input label stored as "Summer<br>Sale" renders on the page as two lines (editable seeded "Summer\nSale"). Opening Entries calls setInitialData(items, uid, 'title', "Summer<br>Sale"); the value differs, initialData is replaced and ve-editable-text's change handler sets the slot text to the literal "Summer<br>Sale" (ve-editable-text.js:354-368). No phantom change is staged, but the page text changes on open and later edits stage the <br> form.

**Suggested fix.** Treat the page editable as authoritative: in #seedEntries skip setInitialData for a record/field the store already has a baseline for, and/or normalise labelValue server-side exactly like the vendor (str_replace('<br>', "\n", ...)).

### 18. Component spec does not cover the paths that fail above

**Severity:** low · **Area:** CLIENT - ENTRIES INSPECTOR, STORE USAGE, A11Y · **Where:** `Tests/E2E/field-chooser-component.spec.js:384`

**Claim.** The entries-inspector spec proves the message and listing after a deferred add, Escape before any action, anchored geometry via the Entries button only, and single-line labels; the post-Save render, Escape/aria-expanded after a wrapper refresh, the Add-entry anchored path and multi-line labels have no test.

**Failure scenario.** Each of the four reproduced defects (R1, R3, R4, R5 in /tmp/vee-repro/review.spec.js, run against the v1.6.0 tree with the extension's own action-bar harness) passes the current suite unnoticed; a future refactor of element-refresh.js or add-entry.js cannot be caught either.

**Suggested fix.** Add the four scenarios to the component spec (the harness already supports state.created, layout and field overrides): after Add + store.markSaved() expect ve-editable-text[uid=8]; Escape after Add after expects focus on the inline-records button and aria-expanded true while open; anchored via the bar's Add entry follows scroll; a multi-line labelValue keeps its line break.

### 19. Documentation does not render warning-free: guides.xml lacks interlink-shortcode; Index.rst version marker is stale

**Severity:** low · **Area:** TESTS, DOCS AND CLAIMS HONESTY (Visual Editor Enhancements v1.6.0) · **Where:** `Documentation/guides.xml:15`

**Claim.** Plan §7 lists 'docs render (render-guides Docker, 0 warnings)' as a release gate and §9 'docs render 0 warnings'; Index.rst states `:Version: 1.0.0` while guides.xml says release 1.6.0.

**Failure scenario.** Rendering with ghcr.io/typo3-documentation/render-guides (local image) and --fail-on-log fails: 'app.WARNING: The manual declares no interlink shortcode ... Add interlink-shortcode="..." to the <extension> element of guides.xml'. A docs.typo3.org-style build with fail-on-log is red, and the rendered start page shows Version 1.0.0 for the 1.6.0 manual.

**Suggested fix.** Add `interlink-shortcode="webconsulting/visual-editor-enhancements"` to the <extension> element, bump Index.rst `:Version:` to 1.6.0 (or drop the field), and keep the renderer run with --fail-on-log in the release checklist.

### 20. Installation and README list four install-wide flags; the fifth (inlineRecordsEnabled) is documented only inside Collections.rst

**Severity:** low · **Area:** TESTS, DOCS AND CLAIMS HONESTY (Visual Editor Enhancements v1.6.0) · **Where:** `Documentation/Installation.rst:66`

**Claim.** Installation.rst:66 'All four features default to on' and README.md:51-60 'All features default to on' list elementLibraryEnabled, fieldChooserEnabled, editableLinksEnabled, elementRefreshEnabled only; the code has a fifth flag `inlineRecordsEnabled` (default true) that gates all collection editing.

**Failure scenario.** An integrator who wants to disable collection editing install-wide (e.g. until editors are trained) reads Installation/README, finds no flag, and leaves it enabled; the switch exists only as one sentence in Collections.rst:399.

**Suggested fix.** Add `['inlineRecordsEnabled'] = true;` to the Installation.rst and README flag blocks and change 'All four features' to the actual count.

### 21. Compatibility version-support table and README requirements stop at 1.4.x / Visual Editor 1.10.3

**Severity:** low · **Area:** TESTS, DOCS AND CLAIMS HONESTY (Visual Editor Enhancements v1.6.0) · **Where:** `Documentation/Compatibility.rst:258`

**Claim.** Compatibility.rst:13-16 and :203-231 state 1.6.0 is tested/audited against friendsoftypo3/visual-editor 1.10.4, but the 'Version support' table (:250-271) has no 1.5.x/1.6.x row and README.md:37 still says '1.10.2+ (audited against 1.10.3)'.

**Failure scenario.** A reader of the support table or the README requirements concludes 1.6.0 is unaudited/unsupported or audited only against 1.10.3, contradicting the Compatibility prose.

**Suggested fix.** Add a '1.5.x - 1.6.x | 14.3.7+ | 8.4, 8.5 | 1.10.2+ (audited: 1.10.4)' row and update README.md:37.

### 22. CHANGELOG says the components CI job runs on every push; the workflow runs on pushes to main and tags only

**Severity:** low · **Area:** TESTS, DOCS AND CLAIMS HONESTY (Visual Editor Enhancements v1.6.0) · **Where:** `CHANGELOG.md:165`

**Claim.** CHANGELOG.md 'Tests and CI': 'New CI job components: the login-free Playwright component specs (Chromium) run on every push and pull request.'

**Failure scenario.** A contributor pushes a feature branch without opening a PR and expects the component specs to run; nothing runs (only quality/unit/javascript/functional are equally gated, so the job is not special, but the sentence is false).

**Suggested fix.** Write 'on pushes to main, tags and every pull request'.

### 23. KnownProblems understates the first-version refusal: move and remove are refused in the payload, not only after a 409

**Severity:** low · **Area:** TESTS, DOCS AND CLAIMS HONESTY (Visual Editor Enhancements v1.6.0) · **Where:** `Documentation/KnownProblems.rst:116`

**Claim.** KnownProblems.rst:115-119: 'Add is refused up front with the reason. Other structural actions show the reason after the server answered 409 firstVersion'.

**Failure scenario.** An integrator debugging why Move/Remove are already aria-disabled (no request in the network tab) in a workspace under firstVersion = 0 does not find that behaviour described; the docs claim a server round trip that does not happen.

**Suggested fix.** State that Add, Move and Remove are all refused up front in the payload (aria-disabled with the reason) and that a forged intent additionally gets 409 firstVersion.

## 2. Refuted (not defects)

- Refusal status/reason mapping is inconsistent: rights-class refusals are 409, while workspace/preference refusals surface as 403 'rights'
- Sync preflight publishes the uid and counts of a translated parent the editor cannot access
- Add entry paths claimed in the plan are not covered by the component spec
- Two Playwright component cases are screenshot-only and inflate the component inventory
- Plan section 13 (Release log) is still empty although 1.6.0 was tagged and released

## 3. Unexamined areas (critic)

- PERFORMANCE / LATENCY at desiderio scale: nobody measured or budgeted the capability payload or an intent. Structure: per child one FormDataCompiler compile (InlineRecordService.php:153) plus, in deleteReason, one compile per translation of every child (:536-545), plus syncPreflight compiles per language and getRecordWSOL per translated child (InlineTranslations.php:127,141); execute() runs fields() twice (:658, :718). Every compile runs authorizeOwners -> InlineOwnership::relations() (EditorRecordContext.php:125), a full $GLOBALS['TCA'] scan with md5(serialize()) per inline column and no memo (InlineOwnership.php:11-29), on a lab TCA of ~254 MB. Lab: largest live collection has 40 children (team_grid_minimal_members, parent 194005) on a 4-language site -> roughly 300+ compiles and 300+ TCA scans per move. No timing exists in any test or doc.
- CONCURRENCY beyond the single lock: the lock is vee-inline:<table>:<default live uid>:<field> (InlineRecordService.php:647). sync writes the translated parents' lists and children (SyncOperation.php:58-61) and the delete cascade writes translated child rows, while a parity move on the translated parent takes a different lock; the generic staged hidden/title save (PersistenceController.php:67-77) takes no lock, no revision and no transaction. No test interleaves two intents or an intent with a vendor autosave; Collections.rst:570-581 only says backend extensions may not share the lock.
- BROWSER / INPUT MATRIX: every proof is Chromium (Tests/E2E/playwright.config.js:11 Desktop Chrome; lab playwright.config.js chromium-desktop/mobile). Untested in Safari/Firefox: native Popover API row menu (row-menu.js:55,64,153), constructable adoptedStyleSheets, ::after on the contextual-trigger shadow host, dvh tray height, :focus-visible captions; real touch devices only emulated (hasTouch/isMobile). Compatibility.rst:222 limits the Chromium statement to ::after.
- REFUSAL REASON 'stage' (plan section 5 'Element is in review; editing stage closed'): the server never produces it. EditorRecordContext.php:85-91 turns workspaceCheckStageForCurrent(0) === false into AccessDeniedEditInternalsException -> 403/'rights' (PersistenceController.php:44-45, InlineRecordService.php:239); the client sentence inline-record-actions.js:179 is dead code. Editors in a reviewed stage read 'Access to this relation ... is restricted'. No test covers stages.
- OPERATIONAL ROLLOUT PATH: nobody checked how the feature is actually switched on for a group. Core 14.3.10 backendSetUC() (cms-core BackendUserAuthentication.php:1948-1970) backfills UserSettingsSchema defaults into uc for existing users; lab be_users 1 and 2 already hold tx_visualeditor_inlineRecords;i:0 and tx_visualeditor_actionLabels 'caption', so plan D11 (setup.default) is inert. Also every human lab user is admin (uids 1,2,6,9; 13 is a never-logged-in service account), so no human restricted-editor acceptance has happened - only disposable fixture users.
- KEYBOARD CONFLICTS inside the title input: rowKeydown (collections.js:147-153) posts a move on Alt+ArrowUp/Down with no inField guard (only Delete has one, :155); on macOS Option+Up/Down is caret navigation in a text field, so typing a title can trigger a server write and a list re-render. Collections.rst:28 documents 'anywhere in the row' as intended; nobody weighed the conflict.
- f:mark.item CONTRACT in production: zero desiderio templates use it (grep packages/desiderio/ContentBlocks = 0), so the 2.4 consumer (collection-rows.js highlight/dimming) is proven only in the harness (action-bar-labels-component.spec.js:316-325); and ext_localconf.php:17 appends the extension namespace to the global 'f' Fluid namespace - a future vendor/Core f:mark.item collides. Not examined.
- CREATE DEFAULTS AND TITLE WRITE EDGE CASES: defaults() collapses multi-value select defaults to '' (InlineRecordService.php:572-574), refuses required slug/uuid/email columns as 'required' although Core fills slug/uuid (:565-569); the inspector <input> has no maxlength/required (collections.js:97) while DataHandler silently truncates type=input 'max' - for rows without a page editable nothing validates. No test for any of these.
- TIME-RESTRICTED CHILDREN: entries with a future starttime/past endtime are not rendered in the edit frame (vendor EditModeMiddleware.php:95-97 only sets includeHiddenContent), so the 'focus the new editable' and page-marks paths silently fall back; untested and undocumented.

## 4. Plan claims still unverified

- 2.1 'the discovery fetch on pointerenter/focusin is deleted': the fallback discovery still exists (config.js collectionTypes() null -> discover on interaction) and a legitimately empty index re-enables it (confirmed finding on FrontendConfiguration.php:75); the CHANGELOG first-paint claim therefore does not hold install-wide.
- 2.4 'mirror highlight: hovering an inspector row outlines the page row and vice versa' and render-time dimming via data-ve-item-hidden: only harness-proven (action-bar-labels-component.spec.js:316-325); no real page in the lab carries f:mark.item (0 desiderio templates), no runtime proof.
- 2.5 'Workspace autosave (300 ms) is covered by saving + the server lock/revision': no test interleaves an autosave with a structural action; the inspector reload on store 'saved' (ve-field-chooser.js:105) exists, but a stale 409 while autosave finishes between payload and click is unmeasured.
- 2.7 / 4.7 / D11 'Install-wide defaults via setup.default.tx_visualeditor_*': contradicted - Core backfills the TCA default into every existing user's uc (BackendUserAuthentication.php:1958-1968; lab users 1 and 2 already carry the keys).
- 3.5 'on coarse pointers a tap acts at once and labels are visible whenever the bar is visible': proven only under Chromium emulation (hasTouch/isMobile), no device run.
- 4.1 sync in workspaces 'Core writes the live translated parent count and live child sorting/tstamp (read, not executed -> assumed)': verified by reading RelationHandler keepLiveIds only; never executed as a test, and D4's alternative ('exempt sync after a MariaDB test') was never attempted.
- 4.4 rationale 'FormEngine saves and the vendor's own text edits of an unversioned parent already run exactly this bookkeeping': never demonstrated by a test (finding 3 proves the mechanism for a staged child write, which supports it, but the parent-text-edit case has no assertion) - the D1 decision rests on it.
- 4.4 / 5 'Staged hide/title need no parent draft, Core versions the child alone' and 'hide/unhide in a workspace allowed regardless of firstVersion': contradicted for children with nested members (confirmed finding; Collections.rst:151-152, 515-516); only the leaf case is tested (childHiddenToggleInWorkspaceCreatesChildDraftOnly).
- 4.5(b) 'Page 8430: 0 -> all 82 leaf entries removable for admins': the runtime scenario (runtime.spec.js:569-581) asserts canDelete for one element's cards only and is skipped whenever the showcase uids change after a reseed; the number 82 is asserted nowhere.
- 4.8 'DataHandler's silent exclude-field drops never masquerade as success': incomplete - displayCond HIDE_FOR_NON_ADMINS (confirmed finding) and 'max' truncation of inspector titles (no maxlength, collections.js:97) are not covered.
- 5 'context | stage > 0, non-owner editor -> reason stage': not implemented; the server answers 'rights' (EditorRecordContext.php:85-91), the client string is dead (inline-record-actions.js:179); no test.
- 5 'add | required media / child type selector -> Add in backend form: image link': the populated requiredBackend case exists only in the conditionally skipped 8430 scenario (createReason 'required' for 200228); InlineCapabilityPayloadTest.php:31 asserts the empty list only.
- 4.3 'n / max when maxItems > 0': Core's TcaInlineConfiguration default maxitems 99999 reaches the payload, so 147 of 175 desiderio collections (no maxitems) show 'n / 99999' - acknowledged as open in plan section 13, no test pins the intended behaviour.
- 2.5 'the page shows it after you save your text' (Collections.rst:74-76): false when the element's only staged data is inline-covered text (confirmed finding on element-refresh.js:102-116); runtime scenario 491 asserts SQL only, never the rendered entry.

## 5. Must fix before the feature is enabled for editors

- Deferred entry never reaches the page after Save (element-refresh.js:102-116, ve-field-chooser.js:922-925) - the default 'type a title, then Add' flow leaves the new entry invisible until a manual reload; both suites missed it (runtime.spec.js:491 asserts SQL only).
- Textarea label fields edited in a single-line <input> (collections.js:97, labelField accepts type=text at InlineRecordService.php:356-364) - 122 of 175 desiderio collections have a plain Textarea label; one keystroke flattens stored line breaks (data loss).
- Docked layouts lose the trigger after every structural action (ve-field-chooser.js:267) - default inspector layout; Escape returns focus to <body> and the Entries button says aria-expanded=false while open, after each add/move/remove.
- Entries click does nothing when the capability fetch fails (element-field-actions.js:136) - expired sessions are routine in long editing sessions; violates 'every refused control explains why'.
- The enabling recipe itself: setup.default.tx_visualeditor_inlineRecords = 1 (plan D11, Configuration.rst:88-98, CHANGELOG restore cell) does not reach existing users because Core backfills the TCA default into uc (lab users 1 and 2 already hold i:0). Switch the docs/recipe to setup.override or a per-user toggle, or ship a User-TSconfig fallback in FrontendConfiguration/InlineRecordConfiguration, before telling editor groups to use it.
- Workspace behaviour the lab demo context (admin parked in workspace 4, Staging) hits at once under the shipped firstVersion = 0: (a) contradictory strip hint vs. refused controls (ve-field-chooser.js:521); (b) staged eye/title on a child with nested members (13 nested desiderio collections, e.g. pricing plans -> features) silently renumbers live nested sorting/tstamp (InlineRecordService.php:174-175, 373-390); (c) nested collections refused 'firstVersion' and 'Draft created' announced for a draft Core never makes (:429-437, :465).
- Inspector header 'n / 99999' for 147 of 175 desiderio collections (collections.js:245; Core default maxitems 99999) - cosmetic but on 84 percent of collections and in every screenshot; already open in plan section 13.
- Not lab-blocking (lab has 0 l10n_source chains and no allowLanguageSynchronization on tt_content collections) but blocking for any other install before parity is left on: connected translated parents ignoring allowLanguageSynchronization/l10n_state (InlineRecordService.php:469-477) and LiveSnapshot/firstVersion missing l10n_source dependents (LiveSnapshot.php:47,239-256; InlineRecordService.php:415,465).

## 6. Fix list for 1.6.1 (ordered by severity)

| Severity | Fix | Test |
|---|---|---|
| high | element-refresh.js: record the root deferred by refreshElement()/the bar path (Set of wrapper selectors, deferElementRefresh(table, uid)); handleSaved() adds those wrappers to staleWrappers regardless of isInlineCovered and clears the set on success (or the inspector calls refreshElement(rootRecord) on store 'saved' when its last action was deferred). | field-chooser-component.spec.js: stage an inline-covered title on row 2, Add entry, store.markSaved() -> expect ve-editable-text[uid=<new>] on the page; extend lab runtime.spec.js:491 to assert the new entry renders after Save. |
| high | Multi-line labels: publish labelMultiline (TCA type=text) in the child/field payload (InlineRecordService.php:356-364) and render a one-row auto-growing <textarea> in collections.js (Enter commits, Shift+Enter inserts a break, Escape restores); alternatively restrict labelField to type=input so Textarea labels fall back to the contextual trigger. | InlineCapabilityPayloadTest: text column -> labelMultiline true; component spec with labelValue 'Line one\nLine two' asserting the staged value keeps '\n' and the rendered control shows two lines; node test for the commit/newline key handling. |
| high | InlineRecordService::unsupported(): on a translation row refuse structure with a new reason 'synchronized' when the inline column has behaviour.allowLanguageSynchronization and State::fromJSON($table, l10n_state) is parent/source state for that field; mirror in InlineTranslations::syncPreflight ('translation') and FieldOptionsService::isEditableInBackendForm; sentence in the catalogue + Collections.rst next to the l10n_mode = exclude rule. | Functional: vee_items with allowLanguageSynchronization = true, connected tt_content 2 -> payload createReason/sortReason/deleteReason 'synchronized', create/move/delete 409, every row byte-identical; l10n_state custom -> allowed. |
| high | LiveSnapshot: compute the dependency set like DataMapProcessor (rows whose transOrigPointerField OR translationSource field points at a collected record, transitively, only for tables where DataMapProcessor::isApplicable() holds) and use it as roots; feed the same set into translatedParentsNeedFirstVersion() for connected parents too (drop the translationMode === 'default' gate at InlineRecordService.php:415/465). Update Collections.rst:124-132 wording. | Functional: en(1) -> de(2, l18n_parent 1) -> hu(3, l10n_source 2) in a workspace, acting on de: firstVersion = 0 -> 409 firstVersion with hu rows unchanged; firstVersion = 1 -> only hu entry sorting/tstamp deltas, response workspace.firstVersion true. |
| medium | Staged child writes in a workspace: in collection(), when workspaceId > 0, the child row is live and LiveSnapshot::needsFirstVersion($childTable, liveUid, ws) is true, set hideReason/labelReason = 'firstVersion' unless TSconfig firstVersion = 1 (then announce the draft); or run owned-child generic saves through the transaction + LiveSnapshot guard. Correct Collections.rst:151-152 and 515-516. | Functional: child with two nested features, firstVersion = 0, generic save of hidden -> canHide false/hideReason 'firstVersion' in the payload and the save refused; live feature sorting/tstamp byte-identical; with firstVersion = 1 the save succeeds and only sorting/tstamp of the nested live rows change. |
| medium | Gate translatedParentsNeedFirstVersion() and the translation roots used for workspace.firstVersion on Core applicability (table has an l10n_mode = exclude column or an allowLanguageSynchronization column, as DataMapProcessor::isApplicable/State::isApplicable); keep the translations as strict snapshot roots. | Functional on the nested relation (tx_vee_item has no such columns): nested parent drafted, its translation undrafted with members -> createReason '' and workspace.firstVersion false; create under firstVersion = 1 reports firstVersion false and the translation row is byte-identical; tt_content root keeps the current behaviour. |
| medium | ve-field-chooser.js: move the replacement-trigger lookup out of #position() into #rebindTrigger(), run it for every layout on the bubbling ve:element-refreshed event / at the end of #collectionAction; set aria-expanded on the new Entries button and this.returnFocus = this.trigger. | Component spec (inspector layout): Add after -> new wrapper -> Entries button aria-expanded 'true' while open; Escape -> document.activeElement is that button. |
| medium | add-entry.js addEntryFromBar(): after refreshElement re-query bar.querySelector('[data-ve-enhancement="inline-records"]') and pass it as trigger (keep focus: false), so #position() tracks a live element and aria-expanded/returnFocus are defined. | Component spec: layout anchored, click Add entry, scroll 120 px -> popover delta equals bar delta; Entries button aria-expanded 'true'; Escape returns focus to it. |
| medium | element-field-actions.js Entries handler: when refreshCollectionCapability() resolves null, open the inspector with inlineLabel('loadError') (as Add entry does) or setRefused(entries, inlineLabel('loadError')). | action-bar-labels-component.spec.js: route ?veFieldOptions=1 to 403 -> clicking Entries shows the loadError message or aria-disabled + title within 800 ms. |
| medium | Install-wide defaults: document that setup.default.* seeds only users who have never logged in (Core backfills UserSettingsSchema defaults) and give setup.override as the working install-wide recipe; fix CHANGELOG 'Restore 1.5' and plan D11; optionally read tx_visualeditorenhancements.userDefaults.* from User TSconfig in FrontendConfiguration/InlineRecordConfiguration when the uc value equals the TCA default. | FrontendConfigurationTest: uc holding the TCA default + User TSconfig default -> TSconfig value wins; uc holding a user choice -> user wins (if the fallback ships); docs render job green. |
| medium | Unlimited collections: publish maxItems 0 when the resolved maxitems >= 99999 (Core's TcaInlineConfiguration default) or let collections.js:245 treat >= 99999 as unlimited; apply the same rule to SyncOperation/syncPreflight 'max'. | InlineCapabilityPayloadTest: column without maxitems -> maxItems 0; component spec header shows the count only; recapture the desiderio screenshots. |
| medium | Performance: memoize InlineOwnership::relations() per request (static cache invalidated by TCA identity) and avoid compiling every child's translations in deleteReason up front (compile lazily when Remove is requested, or memo per translation uid); measure before/after. | Lab runtime suite: timing budget for the payload of the 40-entry element (parent 194005) and one move (e.g. < 1.5 s / < 3 s) recorded as evidence; functional test counting FormDataCompiler compiles for a 10-child, 3-language fixture. |
| low | Suppress the firstVersionNext strip hint when field.createReason === 'firstVersion' \|\| field.sortReason === 'firstVersion' (or send workspace.firstVersionAllowed from the server) and reword KnownProblems.rst:115-119 (Add, Move and Remove are refused up front). | Component spec with the 409 fixture (createReason firstVersion) asserting the hint is absent and the refusal sentence present. |
| low | Emit reason 'stage': check workspaceCheckStageForCurrent(0) separately (before or alongside compile) and return createReason/sortReason/deleteReason 'stage' (403 or 409 as documented) instead of folding it into AccessDenied -> 'rights'; keep the existing client sentence. | Functional with EXT:workspaces: element at stage 1, non-owner editor -> every reason 'stage'; owner/admin unaffected. |
| low | mayWrite(): return false for non-admins when $GLOBALS['TCA'][$table]['columns'][$field]['displayCond'] === 'HIDE_FOR_NON_ADMINS' (string form as DataHandler checks it). | Functional: hidden column with that displayCond -> hideReason 'rights', createReason 'hidden' for a non-admin; admin unaffected. |
| low | action-bar-labels.js focusin: unpin when the focused button differs from the pinned one (or let pointerleave prefer focusVisibleButton(bar) over the pin). | Component spec: click Show, Tab to Delete, move pointer out of the bar -> data-ve-label-current on Delete. |
| low | FrontendConfiguration: encode an empty collection index as a JSON object ((object)[] / {tt_content: {}}) and keep null strictly for a missing key; collectionFieldsFor({}) returns []. | PHP unit asserting '{}' in the serialized config; node config.test.js: {} -> no discovery, [] never produced; component spec: empty index -> zero ?veFieldOptions requests on hover. |
| low | installActionBarLabels(): when the vendor renders labels (veInfo.actionBarLabels or a .label in the bar) still adopt buildActionBarLabelCss('icons') (base rules for [hidden] and aria-disabled) and skip only the label rules. | Component spec with window.veInfo.actionBarLabels = true: Entries/Add entry with hidden=true are display none; aria-disabled buttons at opacity .5. |
| low | #seedEntries: skip setInitialData for a record/field the store already holds a baseline for, and/or normalise labelValue server-side like the vendor (str_replace('<br>', "\n", ...)). | Component spec with labelValue 'Summer<br>Sale' and a page editable seeded 'Summer\nSale': opening Entries dispatches no 'initial' change and the page text is unchanged. |
| low | Alt+ArrowUp/Down: require focus outside inputs (reuse the inField guard of Delete at collections.js:155) or move the shortcut to the row toolbar; adjust Collections.rst:28. | Component spec: focus the title input, press Alt+ArrowUp -> no move intent posted and the caret moves; on the row/toolbar the move still fires. |
| low | Lock scope: for sync and translation cascades acquire the nonblocking lock of every translated parent touched (sorted, released in finally) in addition to the default parent's, or document precisely which rows are written outside the lock (Collections.rst:570-581). | Functional: hold vee-inline:tt_content:<translated live uid>:vee_items and assert sync -> 409 busy; parity move on the translation while a sync holds the locks -> 409 busy. |
| low | Docs: add interlink-shortcode to guides.xml and bump Index.rst :Version: to 1.6.0; add inlineRecordsEnabled to Installation.rst/README flag blocks ('five'); add the 1.5.x-1.6.x support row and README 1.10.4; CHANGELOG 'on pushes to main, tags and every pull request'; KnownProblems first-version wording; Collections.rst 'translations Core synchronizes (via l10n_parent or l10n_source)'. | CI job rendering the manual with render-guides --fail-on-log (0 warnings) plus a grep-style unit test that every flag in FeatureFlags appears in Installation.rst and README. |
| low | Close the spec gaps: add the four reproduced scenarios (post-Save render, Escape/aria-expanded after a wrapper refresh, anchored via the bar's Add entry, multi-line label) to field-chooser-component.spec.js and assert the rendered entry after Save in runtime.spec.js:491. | The four component cases plus the runtime assertion fail against the v1.6.0 tree and pass after the fixes above. |

## 7. Release log

(empty — filled by the 1.6.1 release)
