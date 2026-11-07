# Visual Editor: repeatable rows (collection entries) UX rework — Entries inspector, Add-entry icon, action-bar labels, workspace and translation completeness

Implementation plan, written 2026-11-07 by the planning thread (Fable 5.1, synthesizer of three concepts and three judge
verdicts). Execution: Opus 5.5 workers, in parallel where section 8 allows it. This file is the single source of truth
for the work; every worker reads sections 0–5 and 8 in full and the sections its work package names.

The winning concept is **"Entries Inspector 1.6"** (two of three judges: editor-experience lens and implementability
lens; the Core-correctness judge preferred "Quiet Rows"). The grafts of "Quiet Rows" and "Rows in Place" and every
must-fix of all three judges are folded in and marked **(graft)** / **(must-fix)** where they change the winner's design.
Claims not verified in source are marked **assumed**. The owner's request, verbatim: *"rethink and recreate the interface
for the editor regarding repeatable elements. think of an icon to add new 'row' or element for repeatable. this must be a
generic solution for all typo3 options (mehrsprachigkeit, workspace, etc.). make it easier to use. the buttons for the
icon list per content element should have labels (text) too, not only icons. BUT very small text, the text comes only if
i hover or click the icon."*

Paths: `MAIN` = `/Users/dirnbauer/projects/webconsulting-typo3-lab` (DDEV project; read-only SQL via `ddev mysql -N -e`),
`VEE` = `MAIN/packages/visual_editor_enhancements` (webconsulting/visual-editor-enhancements **1.5.1**, commit 0375d7c,
GitHub dirnbauer/typo3-visual-editor-enhancements + GitLab mirror; composer-only, no `ext_emconf.php`, no `version` key —
the version is the git tag + CHANGELOG), `VE` = `MAIN/vendor/friendsoftypo3/visual-editor` (**1.10.4**, Composer dist zip
ref 773dd591, no `.git`; upstream FriendsOfTYPO3/visual_editor has no newer tag — verified 2026-11-07, so "upstream" = these
files), `DES` = `MAIN/packages/desiderio` (4.33.0), `CORE` = `MAIN/vendor/typo3/cms-core`, `BE` = `MAIN/vendor/typo3/cms-backend`,
`CB` = `MAIN/vendor/friendsoftypo3/content-blocks` (2.4.10), `WT` = this worktree (lab commits: docs, Tests/Integration).
Lab lock today: visual-editor-enhancements v1.5.1, desiderio v4.33.0 (`MAIN/composer.lock`).

## 0. Summary

**Deutsch.** Heute kostet eine neue, sichtbare Zeile in einer Wiederholung (z. B. eine Feature-Karte) sieben Schritte,
zwei Kontextwechsel und einen kompletten Modul-Reload: Hover → „Entries" → „Add entry" → Titel anklicken → Core-Formular
→ Reiter „Zugriff" → „Sichtbar" → Speichern → Schließen. Auf der Schauseite 8430 lässt sich kein einziger vorhandener
Eintrag entfernen (alle 82 haben verbundene Übersetzungen), Umsortieren und Ein-/Ausblenden gibt es nicht, und das Panel
schließt sich bei jedem Klick auf die Seite. Der Plan baut das Panel zu einem schnellen „Einträge-Inspektor" um: ein
eigenes Icon „Eintrag hinzufügen" in der Aktionsleiste (ab dem ersten Rendern sichtbar, ein Klick legt den Eintrag an und
setzt den Fokus in den neuen Text auf der Seite), pro Zeile sichtbare Aktionen (nach oben/unten, Auge = ein-/ausblenden,
danach einfügen, entfernen, Menü mit Einstellungen/Formular/Übersetzen), ein Titel-Eingabefeld direkt in der Zeile,
Zustands-Badges (versteckt, neu im Entwurf, zum Löschen markiert, nur in dieser Sprache, hier nicht übersetzt), eine
Kontextzeile (Live/Workspace · Sprache · Übersetzungsmodus), „Übersetzungen synchronisieren" und ein Panel, das beim
Tippen auf der Seite offen bleibt. Drei Panel-Positionen (rechts, unten, unter der Leiste) sind eine Benutzereinstellung.
Alle Leisten-Buttons bekommen sehr kleine Beschriftungen (11 px), die nur bei Hover, Fokus oder Klick erscheinen – über
ein einziges per `adoptedStyleSheets` eingehängtes Stylesheet, ohne Patch am Hersteller-Code; vier Darstellungen als
Benutzereinstellung (Standard: Beschriftung des berührten Buttons in einer reservierten Zeile, die Leiste zuckt nicht).
Server: Core-Standardmechanismen für alles – Umsortieren = Reihenfolge im Eltern-Feld (wie FormEngine), Ein-/Ausblenden
= das `hidden`-Feld des Kindes über den Hersteller-Store (wie der Hersteller beim Element), Übersetzungen =
`inlineLocalizeSynchronize`, Löschen mit Übersetzungen = Core-Kaskade nach gezähltem Bestätigen, erste Workspace-Version
= Core-Auto-Versionierung unter einer verengten Live-Zeilen-Garantie – diese und die Übersetzungs-Parität sind
TSconfig-Schalter mit Ihrer Entscheidung (Abschnitt 12). **Klicks vorher/nachher:** neue sichtbare Zeile 7 Schritte +
Reload → 3 Klicks (Hinzufügen, Auge, Speichern) + Tippen, kein Kontextwechsel; Entfernen 5 Klicks + Modal (auf 8430
unmöglich) → 2 Klicks + Modal (auf 8430 möglich, Bestätigung nennt „auch 3 Übersetzungen"); Umsortieren unmöglich → 1
Klick je Schritt; Umbenennen/Einblenden Core-Formular + Reload → Tippen bzw. 1 Klick + Speichern. Nicht möglich bleibt:
ungespeicherte Platzhalter-Zeilen auf der Seite, Zeilen-Buttons direkt auf der Seite ohne Template-Marker (der Marker-
ViewHelper kommt in 1.6, die Seiten-Ebene in 1.7), Duplizieren/Kaskaden/Wiederherstellen (1.7), Sync im Workspace.

**English.** Today a visible new row costs 7 steps, 2 context switches, 3 waits and a full module reload
(`element-field-actions.js:46-75`, `collections.js:16-18,55-58`, `InlineRecordService.php:302-308`; Core's contextual
trigger calls `ContentContainer.refresh()` on close). No existing entry on showcase page 8430 can be removed (all 82 source
entries have connected de/zh/hu translations; `InlineRecordService.php:229-256` returns `translations`). Reorder, hide,
rename-in-place and sync do not exist (`InlineIntent.php:13-17` accepts `create|delete`). After **VEE 1.6.0**: an
**Add entry** icon in the action bar (visible from first render through a TcaSchema CType index, no hover round trip;
one click creates, refreshes the element, opens the inspector and focuses the new row's first editable on the page); the
docked panel becomes an **Entries inspector** with visible row actions (move up/down, eye, add after, remove, menu), an
inline **title input** (staged through the vendor store like the vendor's own editables), state badges, a context strip,
**Sync translations**, three per-user placements (inspector | tray | anchored), and it stays open while the editor types
on the page. **Every action-bar button** gets a very small label (11 px) revealed on hover, focus or click via one shared
constructable stylesheet adopted into each wrapper's shadow root (no vendor patch; four per-user modes, default
`caption`). Server: `move` and `sync` intents, capability payload (controls map, canHide, labelField, states, translation
languages, workspace strip), FormEngine parity on connected translated parents and a counted Core cascade for
translations — both behind TSconfig with the owner's decision — and first workspace versioning through Core's stock
auto-version datamap under a narrowed live-row invariant, behind TSconfig `firstVersion` (default 0 until the owner's
written decision, section 12 D1). A marker ViewHelper `f:mark.item` ships in 1.6.0 so templates can announce rows; the
on-page row layer (rail, appender, user setting `collectionRows`) and the desiderio codemod are the **1.7.0** train in this
same plan. **Clicks before/after:** add visible row 7 steps + reload → 3 clicks + typing, 0 context switches; remove 5
clicks + modal (impossible on 8430) → 2 clicks + modal (possible, confirm names "also removes 3 translations"); reorder
impossible → 1 click per step; hide/unhide 7 steps + reload → 1 click + Save; rename Core sheet + reload → type + Save.

## 1. What exists in 1.5.1 and its friction (evidence)

| # | Fact / friction | Evidence |
|---|---|---|
| 1 | Only `create` and `delete` intents exist; any other action or key is 400 before record resolution | `VEE/Classes/Service/InlineIntent.php:13-17`; `PersistenceController` |
| 2 | Live creations are hidden by default (`createHidden`, live only) and refuse without a writable `hidden` field; un-hiding requires the Core sheet because the field chooser blocks the disabled field | `InlineRecordService.php:149,302-308`; `FieldChooserConfigurationService.php:236-258` |
| 3 | Closing Core's contextual sheet reloads the whole Visual Editor module (all language frames) | `BE/Resources/Public/JavaScript/element/contextual-record-edit-trigger.js` (`ContentContainer.refresh()` in the hidden handler) |
| 4 | Hidden children render in the edit frame like live ones (vendor forces `includeHiddenContent`), nothing marks them | `VE/Classes/Middleware/EditModeMiddleware.php:88-97`; `CORE/Classes/Domain/Persistence/GreedyDatabaseBackend.php:141-146` |
| 5 | Zero existing entries removable on page 8430: every source child has active connected translations → `translations`; `cascade` for any populated inline/file column; `min` for minitems 1 collections | `InlineRecordService.php:229-256`; read-only SQL (feature_cards_items 8684-8686 with 8687-8695; 579/579 desiderio_qa_item translated rows connected) |
| 6 | Connected translated parents are refused entirely (`translation`), although FormEngine lets editors add translation-only rows and remove/hide/reorder translated rows | `InlineRecordService.php:192-199`; `BE/Classes/Form/FormDataProvider/DatabaseRowInitializeNew.php:220-237` |
| 7 | Workspace: refused until parent and every child are drafts of this workspace; delete placeholders are silently skipped from the list | `InlineRecordService.php:84-87,93-95,186-191` |
| 8 | Structural actions blocked while ANY store change or any frame's pending count > 0 ("Save or reset your changes first"); the vendor has no global Reset; the element refresh is also skipped on any global change | `Shared/inline-record-actions.js:13-19`; `Frontend/inline-records.js:8-14`; `Frontend/element-refresh.js:177,209` |
| 9 | The Entries button is the only text-labelled bar button (permanent 12 px span, inline `cssText`), hidden until a hover-triggered FormEngine round trip; `makeButton` gives every button `aria-haspopup="dialog"` | `Frontend/element-field-actions.js:6-17,46-50,58-75` |
| 10 | The panel closes on any outside pointerdown (except the two trigger buttons), so clicking the new row's text on the page closes it | `Frontend/components/ve-field-chooser.js:104-115` |
| 11 | Row actions hide in a `<details>` ⋯ menu (no outside close, no arrow keys); text glyphs ⋯/＋ next to SVG icons; a duplicate pencil; per-row backend link opens a new tab without returnUrl | `ve-field-chooser/collections.js:38-51`; `InlineRecordService.php` `backendUrl()` |
| 12 | The vendor bar is a light-DOM `<ve-drag-handle class="action-bar">` inside the wrapper's shadow root: `#171717`, 14 px, icon-only `.button` with `title` + `aria-label`; the wrapper carries `CType`, `table`, `uid`, `hiddenFieldName`, `canModifyRecord`; `firstUpdated` requires exactly one child, warns on custom-element children, hoists and sets `was`, seeds the hidden field with `setInitialData` | `VE/.../ve-content-element.js:17-30,210-258,265-330,393-466` |
| 13 | The vendor's own hide toggle is a staged store write (`setData` after `setInitialData`); editables mirror the store to the page; `setData` without prior `setInitialData` records a change that no toggle-back can clear | `ve-content-element.js:64-69,215-217`; `ve-editable-text.js:354-368`; `stores/data-handler-store.js:49-85` |
| 14 | The vendor exposes a frame-side save trigger: `sendMessage('doSave')` (used by the drop zone and the unsaved-changes guard), handled by `ve-backend-save-button` | `ve-drop-zone.js:215`; `intercept-user-actions-guard.js:62`; `initialize-save-handling.js:29`; `Backend/components/ve-backend-save-button.js:56,113` |
| 15 | Child editables carry `table` + `uid` (localizedUid ?: versionedUid ?: uid) + `fieldPositionId`; children have no wrapper; Content Blocks has no hook/ViewHelper to mark rows | `VE/Classes/ViewHelpers/Render/TextViewHelper.php:197,297`; `ContentElementWrapperService.php:70-99`; `CB/Classes/DataProcessing/ContentBlockDataDecorator.php:276-291` |
| 16 | The vendor ships its own `Mark` ViewHelper namespace; our extension extends the `f` namespace, so `f:mark.item` and `f:render.*` resolve without touching the vendor | `VE/Classes/ViewHelpers/Mark/ContentAreaViewHelper.php`; `VEE/ext_localconf.php:17` |
| 17 | Per-user settings: `tx_visualeditor_inlineRecords` (default 0), `showLibrary`, `showContextButtons`, `fieldChooserMode`, `panelColumns`; the lab admin (be_users 1) has `inlineRecords = 0` and sits in workspace 4 | `Configuration/TCA/Overrides/be_users.php:19-37`; `FrontendConfiguration.php:41-69`; read-only SQL |
| 18 | The recorded owner decision keeps the strict first-version live-byte contract and forbids relaxing it silently; initial workspace creation is a documented backend fallback | `WT/docs/plans/2026-11-05-visual-editor-review-decisions.md` |
| 19 | Test inventory: 23 functional (`InlineRecordTest.php`), 12 + 12 Playwright (components harness login-free; enhancements needs `VEE_BACKEND_PASSWORD`, stored session stale), node tests, CI jobs quality/unit/javascript/functional — no browser job; lab runtime recipe with disposable users exists | `VEE/Tests/**`; `VEE/.github/workflows/ci.yml`; `MAIN/Tests/Integration/VisualEditor/{fixture.php,runtime.spec.js}` |
| 20 | Desiderio row anatomy: 156 templates, 189 collection loops (176 top-level, 13 nested); 171 top-level loops have one HTML (142) or `d:` component (29) root, 1 has a Fluid-tag root, 3 multi-root, 1 zero-root; 84 roots are li/tr/th/td; 6 molecules lack an `attributes` argument | `/tmp/ve-row-anatomy/des.json` (scratch, to be ported in WP-N) |

## 2. The new rows UI

### 2.1 Add-row icon (bar), placement and behaviour

- **Button:** `data-ve-enhancement="inline-add"`, injected by a new `injectInlineAddAction(element)` in
  `Frontend/element-field-actions.js` right after the Entries button. Icon = the existing list-with-plus glyph
  (`Shared/icons.js` `entriesIconSvg` → renamed `addEntryIconSvg`), so the bar never shows two bare plus icons beside the
  vendor's `actions-document-add` **(graft, Quiet Rows)**. The Entries button switches to Core `actions-list-alternative`
  (path copied into `Shared/icons.js` as `listIconSvg`). Short accessible name `frontend.actionBar.addEntry` =
  "Add entry" / "Eintrag hinzufügen"; **no** `aria-haspopup`/`aria-expanded` on this button — it performs an action
  **(must-fix, J2)**; `makeButton` gains a `popup` flag so only the two panel openers carry `aria-haspopup="dialog"`.
- **Visible from first render, no hover round trip:** `FrontendConfiguration::build()` adds
  `collectionTypes: {<table>: {<recordType>: [inlineFieldNames]}}` built per request by a new
  `Classes/Service/CollectionFieldIndex` (TcaSchemaFactory → `getSubSchema($cType)->getFieldsOfType(TableColumnType::INLINE)`,
  filtered by the record-free part of `unsupported()` and Page TSconfig `tables.<t>.enabled`); the wrapper exposes
  `CType` (`ve-content-element.js:21`). Entries + Add entry render when the element's CType is listed; the discovery
  fetch on `pointerenter`/`focusin` (`element-field-actions.js:58-75`) is **deleted**. Rights are still decided by the
  FormEngine capability fetch **on click** (`fetchFieldOptions`), after which the buttons get `aria-disabled="true"` +
  the reason as `title`, never `hidden` again **(graft, J2; must-fix, J1: no counts or labels for tables the user cannot
  modify — the index lists field names only, the payload rule `InlineRecordService.php:57-59` is unchanged)**.
- **Click, exactly one collection can create:** `changeCollection(field, 'create')` (append at end; `createHidden`
  applies in live) → `refreshElement(root)` → open the inspector in view `entries` **without stealing focus** → focus
  the new row's first editable on the page (`ve-editable-text|ve-editable-rich-text[table="<child.table>"][uid="<created.uid>"]`,
  `scrollIntoView({block:'nearest'})`); the row is dimmed as hidden and the inspector row shows the eye + hint
  "New entries start hidden. Show it with the eye, then Save." Fallback when the child has no editable on the page: focus
  the row's title input in the inspector.
- **Click, 0 or several collections can create** (page 8430: data table, comparison table): open the inspector with the
  first collection's footer Add button focused (one extra click, documented).
- **Add after** (per row, `actions-form-insert-after` path) posts `create {afterUid: row.uid}`. **Add before** is not
  offered (Core has no such control; "Add after" on the previous row covers it).
- **At max / controls.new = false / createReason set:** Add entry and footer Add are `aria-disabled` with the reason
  sentence; header shows `n / max`.

### 2.2 Per-row actions (the `<details>` menu is deleted)

Each inspector row (`<li class="entryItem" data-ve-row="<uid>">`) renders, in order:
`[#]` number · `[↑][↓]` Move up/down (`actions-caret-up/-down` paths; `move {childUid, afterUid}`; also Alt+ArrowUp/Down
on the row) · **title input** `<input class="entryTitle">` (staged: `setInitialData(child.table, child.uid, labelField,
labelValue)` on render, `setData(...)` on input; Enter/blur commit, Escape restores; rendered as the Core contextual
trigger when `canEditLabel=false`) · badges · quiet row actions (opacity .35 at rest, 1 on row hover/focus-within, always
1 under `(hover: none)`): **eye** (`<ve-icon name="actions-toggle-on|off">`, `role=switch aria-checked`, staged
`setInitialData` then `setData(child.table, child.uid, hiddenField, value)` — the vendor's own element pattern
**(must-fix, J1/J3: `setInitialData` first)**; `aria-disabled` + reason when `canHide=false`), **Add after**, **Remove**
(`<ve-icon name="actions-delete">`, Core modal confirm; text names cascade counts), **⋯ menu**
(`<ve-icon name="actions-menu-alternative">`, new Lit `ve-field-chooser/row-menu.js`, `role=menu`, arrow keys, Home/End,
Escape returns focus, closes on outside pointerdown and when another row's menu opens): Item settings (existing
`#childSettings`), Edit in form (Core contextual trigger), Open backend form ↗ (parent-level stays in the footer), and on
default-language frames "Localize to <language>" per missing language (`sync {language, mode:'localize', ids:[uid]}`).
Every refused control uses **`aria-disabled="true"`** (not `disabled`) with the reason as `title` and
`aria-describedby` of a row `.actionReason` line **(must-fix, J2)**. Duplicate appears in 1.7 only (section 11).

### 2.3 States and badges

- **Context strip** (top of the inspector body; keys `frontend.inlineRecords.contextLive|contextWorkspace|modeDefault|
  modeConnected|modeFree`): "Live site" or "Workspace draft «title»" (payload `workspace.title`; icon `actions-workspace`),
  language chip (`languageId` + veInfo), mode chip "Default language — translated into de, zh, hu" / "Connected
  translation — structure also lives in the source language" / "Free translation".
- **Collection header:** `{count} / {max}` when `maxItems > 0`; "min {min}" hint only when `count <= minItems`;
  `childTitle` for the Add label ("Add feature card", Core `cm.createnew.link` pattern).
- **Row badges** (11 px, `--ve-border` outline like today's `.entryBadge`): Hidden (`actions-eye-slash`), New in draft
  (`state:'new'`), Changed in draft (`state:'modified'`), Marked for deletion (`state:'deleted'`, listed **read-only** in
  1.6 — restore is 1.7), Only in this language (`translationOnly`), Not translated here: zh, hu (`untranslated`), Pending
  (amber dot when `dataHandlerStore.hasChangedData(child.table, child.uid, field)` for the title or hidden field).
- **On the page, hidden rows never look live:** `Frontend/entry-page-marks.js` sets `data-ve-entry-hidden` on every
  `ve-editable-text|ve-editable-rich-text[table][uid]` of a hidden child (identities from the field payload, available
  after open/create/click) and `data-ve-entry-active` for the row whose input has focus; `Resources/Public/Css/editable-overrides.css`
  adds `[data-ve-entry-hidden], [data-ve-item-hidden] { opacity: .55; outline: 1px dashed var(--ve-border); outline-offset: 2px }`
  + `title` "Hidden on the live site". The marks **follow the store, not only the server**: a staged un-hide removes the
  attribute at once, Reset/`saved` store events re-derive it **(must-fix, J2)**. Templates carrying `f:mark.item` (2.4)
  get `data-ve-item-hidden` at render time, before any fetch.

### 2.4 Row location: marker ViewHelper, codemod, fallback

- **1.6.0 ships the contract, 1.7.0 ships the on-page chrome.** No DOM-geometry inference, ever (plan 3.2 no-stunts
  rule; fails for 7 editable-less loops, tables, dl groups, tabs).
- **ViewHelper** `Webconsulting\VisualEditorEnhancements\ViewHelpers\Mark\ItemViewHelper`, reachable as **`f:mark.item`**
  (vendor's own `Mark` namespace idiom; `ext_localconf.php:17`) **(graft, Rows in Place/J1)**. Arguments: `record`
  (content argument; RecordInterface | DomainObjectInterface | PageInformation as in `LinkViewHelper`), `collection:string`
  required (parent column name), `format: 'attributes'|'array'` (default `attributes`). Edit mode
  (`EditModeService::isEditMode($request)`) → ` data-ve-item="{mainType}:{localizedUid ?: versionedUid ?: uid}"
  data-ve-item-live="{getUid()}" data-ve-collection="{collection}"` + ` data-ve-item-hidden=""` when
  `getSystemProperties()?->isDisabled()` (ContentBlockData implements RecordInterface, `ContentBlockData.php:56,152,160`);
  the uid expression is the vendor's verbatim (`TextViewHelper.php:197`); every value `htmlspecialchars`-escaped;
  **outside edit mode `''` / `[]`** **(must-fix, J1)**. `escapeOutput=false`. Throws a developer exception when
  `collection` is not an inline column pointing at the record's table.
- **1.6 consumers** (`Frontend/collection-rows.js`, pure functions, node-tested): group markers by
  `data-ve-collection` + parent (`closest('[data-ve-item], ve-content-element')` — covers nested collections), primary
  marker = first occurrence containing an editable, else first (10 desiderio collections render children in 2–4 loops);
  join inspector children by `uid`, then `data-ve-item-live` ↔ `child.liveUid` **(graft, J1: translated-and-versioned
  children)**; **mirror highlight**: hovering an inspector row outlines the page row and vice versa **(graft, J2)**;
  render-time dimming via `[data-ve-item-hidden]`. Without markers: dimming via editables (2.3), no highlight of row
  bounds — documented.
- **1.7 codemod** (`DES/Build/Scripts/codemod-collection-markers.php`, PHP + Symfony Yaml, the `/tmp/ve-row-anatomy/analyze.js`
  tokenizer ported — the lint test becomes the source of truth for counts **(must-fix, J3)**): every
  `<f:for each="{(data|<alias>).<field>}" as="<as>">` whose field is a Collection and whose body has exactly one root tag
  (f:if/then/else/spaceless/switch transparent; f:variable/f:asset/f:comment no output): HTML root → insert
  ` {<as> -> f:mark.item(collection: '<field>')}` before the open tag's `>` (desiderio's Card already spreads attributes
  this way, `Card.fluid.html:20`); `d:` component root → `attributes="{<as> -> f:mark.item(collection: '<field>', format: 'array')}"`,
  MERGE TODO where `attributes=` exists (content-carousel, product-gallery, use-case-grid); **third rule** for the one
  Fluid-tag-root loop (wrap or list as exception after inspection) **(must-fix, J3)**; exceptions: `chart.data_points`
  (zero root), `chart-contribution.points`, `chart-donut.segments` (JSON text), `gallery.items` (multi-root). Six
  molecules gain `<f:argument name="attributes" type="array" optional="{true}" default="{}" />` + the Card spread loop
  (CheckedListItem, TableRow, TableCell, FeatureItem, TabsTrigger, TabsContent). Idempotent; `--check` fails on unmarked,
  unlisted loops; gate: anonymous render byte-identical before/after (no `data-ve-*` outside edit mode).
- **1.7 on-page layer** (user setting `tx_visualeditor_collectionRows = panel | appender | rows`, default `panel`):
  `ve-row-actions` = a **body-level singleton** Lit component (pattern of `ve-context-chip.js`: `showFor`/`scheduleHide`
  150 ms, cancelled while pointer or focus is inside), anchored to the hovered/focused primary `[data-ve-item]` rect,
  same five buttons + Item settings; `appender` = one dashed ghost "Add {childTitle}" after the last primary marker
  (60 % → 100 % opacity), respects `levelLinksPosition`. **No light-DOM insertion into the wrapper and no Tab keydown
  interception** **(must-fix, J1/J2)**: the inspector remains the keyboard-complete path; the rail is pointer/focusin
  chrome with Escape returning focus to the row's editable.

### 2.5 Panel role, the pending rule and the refresh rule

- **Role:** non-modal `role="dialog" aria-labelledby`, opened by Entries or Add entry; in view `entries` the
  outside-pointerdown close (`ve-field-chooser.js:104-115`) gets an exception — it closes only on Escape, ×, trigger
  toggle, or another element's trigger; the `settings` view keeps today's behaviour. Messages in `role="status"`.
- **Pending rule (honest definition, must-fix J2):** `recordTree(element)` = root `{table, uid}` + every
  `ve-editable-*[table][uid]` inside the wrapper + the inspector's listed children. `structuralBlockReason` keeps
  blocking **everything** on `backend.saving`, `uncertain`, or `invalidCount > 0` anywhere (`pending`); staged changes on
  **other** elements no longer block; staged changes on a row of **this** element block only that row's Remove
  (`pendingRow`: "Save this entry's text first"); create/add-after/move/sync proceed. `refreshElement` /
  `refreshWrappers` (`element-refresh.js:177,209`) skip only when the **replaced wrapper's own tree** has staged data
  (today: any global change). Consequence, stated in the UI: a second row added while the first still has unsaved text
  or a staged eye is written on the server and listed in the inspector at once; **its page markup appears after the next
  Save** (the existing `handleSaved` refresh); the status reads "Saved. The page shows it after you save your text."
  with a **[Save now]** button = `sendMessage('doSave')` from `@typo3/visual-editor/Shared/iframe-messaging`
  (`ve-backend-save-button.js:56` already honours `isInteractionDisabled`/invalid fields) **(must-fix, J1/J3 — not a
  click into the parent document)**. Workspace autosave (300 ms) is covered by `saving` + the server lock/revision.
- **After create:** inspector focuses nothing when a page editable took focus; otherwise the new row's title input
  (today the title button, `ve-field-chooser.js:659-663`).

### 2.6 Keyboard and accessibility

Inspector rows are composites with roving tabindex (title input first, then actions); Alt+ArrowUp/Down moves and
announces "Moved to position n" in the status region; Delete on a focused row opens the Remove confirm (Cancel default,
focus restore as today, `Shared/confirm-delete.js`); Escape in the title input restores the stored value; the ⋯ menu is a
`role=menu` with arrow keys; `aria-disabled` keeps every refusal reason reachable; eye = `role=switch aria-checked`;
badges carry text, not colour alone; contrast through the existing `--ve-*` tokens (AA in light and dark);
`prefers-reduced-motion` removes opacity transitions. Page marks are decorative; the inspector is the accessible source
of truth. Touch: quiet actions always visible under `(hover: none)`. axe (wcag2a/aa/21aa/22aa) runs in three states
(section 7).

### 2.7 Per-user settings

| Setting (be_users.uc) | Values | Default | Exposed as | Release |
|---|---|---|---|---|
| `tx_visualeditor_inlineRecords` (exists) | check | 0 (unchanged); recommended User TSconfig `setup.default.tx_visualeditor_inlineRecords = 1` for desiderio editor groups **(must-fix, J2)** | `inlineRecordsEnabled` | — |
| `tx_visualeditor_entriesLayout` (new; enum `Classes/Enum/EntriesLayout`) | `inspector` (dock right, today's geometry), `tray` (fixed bottom, full width, `min(42dvh, 420px)`, compact table — for side-by-side language frames), `anchored` (480 px popover under the element's bar, scrolls with it, `#position()` reuse) | `inspector` | `entriesLayout` | 1.6.0 |
| `tx_visualeditor_actionLabels` (new; enum `Classes/Enum/ActionLabelMode`) | `caption`, `inline`, `stacked`, `icons` (section 3) | `caption` | `actionLabelMode` | 1.6.0 |
| `tx_visualeditor_collectionRows` (new; enum `Classes/Enum/CollectionRowsMode`) | `panel`, `appender`, `rows` | `panel` | `collectionRows` | 1.7.0 |

Labels in `locallang_setup.xlf` + `de.`: `entriesLayout(.description|.inspector|.tray|.anchored)`,
`actionLabels(.description|.caption|.inline|.stacked|.icons)`, `collectionRows(.description|.panel|.appender|.rows)`.
Install-wide defaults via Core User TSconfig `setup.default.tx_visualeditor_*` (documented as confval; no new
`tx_visualeditorenhancements` key for user defaults).

### 2.8 Sketches

```
ACTION BAR, caption mode (default). At rest: icons only; a 14 px caption row is reserved, nothing moves.
┃ Feature Cards Grid  [✎] [◐] [🗑] [⊕]  [≡] [☰] [☰+] ┃   vendor: edit · show · delete · add element — ours: library · Entries · Add entry
┃                                                   ┃   reserved caption row (empty)
Hover / focus / click on [☰+]:
┃ Feature Cards Grid  [✎] [◐] [🗑] [⊕]  [≡] [☰] [☰+] ┃   bar width unchanged
┃                                        Add entry  ┃   11 px / 500 label of the current button; stays while the pointer is inside the bar
inline mode  ┃ [✎ Edit] [◐ Show] [🗑 Delete] [⊕ Add] [≡ Library] [☰ Entries] [☰+ Add entry] ┃   (short vendor words only when contained in the accessible name)
stacked mode ┃ [ ✎ ] [ ◐ ] [ 🗑 ] [ ⊕ ] [ ≡ ] [ ☰ ] [ ☰+ ] ┃  10 px words under each icon
```

```
INSPECTOR layout (default), live site, default language:
┌ Entries · Feature Cards Grid ─────────────────────────────── [×] ┐
│ Live site · English · translated into de, zh, hu                 │
│ Feature cards                                            3 / 6   │
│──────────────────────────────────────────────────────────────────│
│ 1 [↑][↓] [Add a card___________________] ·Hidden·   [◐][＋][×][⋯] │  ← eye = staged show/hide, row actions at full opacity on hover
│ 2 [↑][↓] [Export anywhere______________]            [◐][＋][×][⋯] │
│ 3 [↑][↓] [Translate once_______________] ·not in zh·[◐][＋][×][⋯] │
│──────────────────────────────────────────────────────────────────│
│ [☰+ Add feature card]          Sync translations ▾   Backend form ↗│
│ New entries start hidden. Show them with the eye, then Save.     │
└──────────────────────────────────────────────────────────────────┘
Row menu (⋯): Item settings · Edit in form · Localize to: zh · Localize to: hu · Open backend form ↗
Pending notice: (!) Unsaved text on entry 3 — Remove waits. The page shows new entries after you save.  [Save now]
```

```
TRAY layout (bottom, side-by-side language frames), connected translation de:
┌ Entries · Feature Cards Grid · Live · Deutsch (connected) ─────────────────────── [×] ┐
│ #  Title                        State                   Actions                       │
│ 1  [Karte hinzufügen_________]  only in this language   [↑][↓][◐][＋][×][⋯]            │
│ 2  [Überall exportieren______]                          [↑][↓][◐][＋][×][⋯]            │
│ 3  [Einmal übersetzen________]  hidden                  [↑][↓][◐][＋][×][⋯]            │
│ [☰+ Feature-Karte hinzufügen]   Structure also lives in the source language.  Backend ↗│
└────────────────────────────────────────────────────────────────────────────────────────┘
```

```
ANCHORED layout: same rows in a 480 px popover directly under the element's bar, scrolls with the element.
WORKSPACE strip and badges:
│ Workspace draft «Staging» · English · translated into de, zh, hu   │
│ 1 [↑][↓] [Add a card_______]  new in draft          [◐][＋][×][⋯]    │
│ 2 [↑][↓] [Export anywhere__]  marked for deletion   (read-only in 1.6; Undo removal in 1.7) │
│ Draft created. Core renumbered the stored order values; the visible order is unchanged.  │  (only when firstVersion = 1)
```

```
1.7 ON-PAGE LAYER (collectionRows = rows), feature cards with f:mark.item markers:
┌ Feature Cards Grid ──────────────────────────────────────────────────┐
│ ┌ card 1 ───────────────────┐  ┌ card 2 ───────[↑][↓][◐][＋][×][⋯]─┐ │  rail = body-level singleton anchored to the hovered/focused row
│ │ Add a card                │  │ Export anywhere                   │ │
│ └───────────────────────────┘  └───────────────────────────────────┘ │
│ ┌ card 3 (dimmed · dashed · "Hidden on the live site") ───────────┐  │  data-ve-item-hidden from the marker, at render time
│ └──────────────────────────────────────────────────────────────────┘  │
│ ┌╌╌╌╌╌╌╌╌╌╌╌╌╌ ☰+ Add feature card ╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌┐    │  appender (also the whole of collectionRows = appender)
└──────────────────────────────────────────────────────────────────────┘
```

## 3. Labels on every action-bar button

### 3.1 Mechanism

One shared constructable `CSSStyleSheet` (module-level, `sheet.replaceSync(buildActionBarLabelCss(mode))`) is added to
each wrapper's `shadowRoot.adoptedStyleSheets` once (WeakSet) from the existing injection pass (`Frontend/index.js:52-76`).
This is the API Lit itself uses for component styles and lies outside Lit's root ChildPart range, so the vendor template
is never patched and re-renders cannot drop it. Labels are generated content: `.action-bar .button::after { content:
attr(aria-label) }` and `.action-bar .button[data-ve-label]::after { content: attr(data-ve-label) }` — visible text equals
or is contained in the accessible name by construction (WCAG 2.5.3). New files: `Frontend/action-bar-labels.js`
(`installActionBarLabels(element)`, pointer/click state) and `Shared/action-bar-label-css.js` (`buildActionBarLabelCss(mode)`,
pure, node-tested). The `.action-bar` is light DOM inside the wrapper's shadow root (`ve-drag-handle.js` renders in light
DOM), so `.action-bar .button` (0,2,0) beats the vendor's `.button` (0,1,0) regardless of order. The contextual edit
trigger is a shadow host with `<slot>` (`BE/.../element/pseudo-button.js`); `::after` on that host is **assumed** to render
per CSS Scoping — the component spec proves it **before caption becomes the default**, fallback = one slotted
`<span class="ve-label" aria-hidden="true">` injected into that trigger only **(must-fix, J3)**. Self-check: adoption is
skipped when the vendor template already contains a `.label` element or `veInfo` announces labels (future upstream).

Our own buttons (library, settings, Entries, Add entry): short `aria-label` (`frontend.actionBar.library|settings|
entries|addEntry` = "Library"/"Settings"/"Entries"/"Add entry"; de "Bibliothek"/"Einstellungen"/"Einträge"/"Eintrag
hinzufügen"), **no `title` outside `icons` mode** (no duplicate native tooltip) **(graft, Quiet Rows/J2)**; the Entries
button loses its permanent `<span>` and inline `cssText` (`element-field-actions.js:47-50`); visibility toggles use the
`hidden` attribute with our sheet's `.action-bar .button[hidden]{display:none}` (the vendor's `.button{display:inline-flex}`
would otherwise beat the UA rule). Vendor buttons keep their `title`/`aria-label` bindings untouched and show their full
accessible name by default; **short vendor words are opt-in and containment-checked (D5b)**: `Shared/action-labels.js`
maps the vendor `title` sentence → key `frontend.actionBar.short.edit|show|delete|add` ("Edit"/"Show"/"Delete"/"Add";
de "Bearbeiten"/"Anzeigen"/"Löschen"/"Hinzufügen") and applies `data-ve-label` **only** when `TYPO3.lang` has the key for
the user's language **and** the short word is a case-insensitive substring of the button's current `aria-label` — so
zh/hu users see the verbatim name and 2.5.3 holds in every language **(graft, J1)**.

### 3.2 Reveal modes (per-user setting `tx_visualeditor_actionLabels`)

| Mode | Behaviour |
|---|---|
| `caption` (default — the owner's words) | A permanently reserved 14 px caption row below the icons; the **current** button's name appears there at 11 px; the bar never changes width while hovered. Current = the button last entered by the pointer **while the pointer is anywhere inside the bar** (delegated `pointerover` on the bar sets `data-ve-label-current`, cleared on the bar's `pointerleave`), or the `:focus-visible` button, or the clicked button (pinned). Moving the pointer onto the caption keeps it → WCAG 1.4.13 hoverable **(must-fix, J2)**. |
| `inline` | Whenever the bar is visible (it is hover/focus-gated by the vendor), every button shows its name beside its icon (`inline-grid`, two columns, 6 px gap, 11 px). The bar grows once when it appears, never while hovered. |
| `stacked` | Whenever the bar is visible, a 10 px name under each icon (`inline-grid`, rows 14 px / 10 px). |
| `icons` | Vendor look; only the base sheet (`[hidden]` rule) is adopted; our buttons keep `title`. |
| Not offered | "All labels while the pointer is over the bar": inline growth moves the button under the pointer and loops (bar is a flex row without reserved width, `ve-content-element.js:393-411`). Explained in `Usage.rst`. |

### 3.3 CSS and DOM

Base (all modes): `.action-bar .button[hidden]{display:none} .action-bar .button[aria-disabled="true"]{opacity:.5;cursor:not-allowed}`.
Caption: `.action-bar{flex-wrap:wrap} .action-bar::after{content:'';flex-basis:100%;height:14px}
.action-bar .button::after{content:attr(aria-label);position:absolute;left:4px;right:4px;bottom:calc(var(--auto-no-overlap-padding,0px) + 4px);
height:14px;font-family:inherit;font-size:11px;line-height:14px;font-weight:500;letter-spacing:.01em;color:inherit;text-align:start;
white-space:nowrap;overflow:hidden;text-overflow:ellipsis;opacity:0;transition:opacity .12s ease;pointer-events:none;user-select:none}
.action-bar .button[data-ve-label-current]::after,.action-bar .button:focus-visible::after{opacity:1}` — the vendor `.button`
has no `position`, so the absolutely positioned `::after` uses the absolutely positioned `.action-bar` as containing block;
`--auto-no-overlap-padding` is the vendor's variable (`ve-drag-handle.js firstUpdated`). Common blocks in every non-icons
sheet: **`@media (hover: none)` switches caption to the inline rules** (opacity 1 on all absolutely positioned captions
would overlap in one row) **(must-fix, J2)**; `@media (prefers-reduced-motion: reduce){.action-bar .button::after{transition:none}}`.
DOM: no nodes inside vendor buttons (except the proven-fallback span on the trigger); attributes only —
`data-ve-labels="<mode>"` on the bar, `data-ve-label` on our/short-labelled buttons, `data-ve-label-current`,
`data-ve-labels-pinned`. `pointer-events:none` + `user-select:none` keep the whole bar the vendor's drag handle.
Selectors rely on `.action-bar`/`.button` only (audited against 1.10.4; `Compatibility.rst` note).

### 3.4 Sizes and contrast

Caption/inline 11 px, weight 500, letter-spacing .01em, line-height 14 px; stacked 10 px/10 px, .02em. Colour inherits
`#d9d9d9` on `#171717` (12.7:1) and on the `#212121` hover background (11.4:1) — far above AA 4.5:1 for small text; the
site font is inherited like the vendor headline (no system-font mismatch); the vendor focus ring (2 px `#5432fe`) stays.
Caption adds exactly 14 px height, no width; inline/stacked change size only when the bar appears. Component spec asserts
`getBoundingClientRect().width` identical before/after hover in caption mode and
`getComputedStyle(btn,'::after').content === '"Add entry"'`.

### 3.5 Click and touch

Click pins: a delegated `click` on the bar sets `data-ve-labels-pinned` on the bar and `data-ve-label-current` on the
clicked button; removed on the wrapper's `mouseleave` and on `focusout` whose `relatedTarget` is outside the bar — no
vendor click handler is intercepted. Coarse pointers: the vendor shows the bar on tap (emulated `mouseover`) and a tap
acts at once, so `(hover: none)` shows labels whenever the bar is visible; a "first tap reveals, second acts" behaviour
would need intercepting vendor clicks and is not done. The vendor's native `title` tooltips on its four buttons still
appear after ~1 s next to our caption (a Lit attribute binding; removing it would be a stunt) — documented.

### 3.6 WCAG summary

1.4.13: caption is not an overlay and stays hoverable/persistent while the pointer is in the bar; Escape not needed
(nothing to dismiss). 2.5.3: `content: attr(aria-label)` or a containment-checked short word. 1.4.3: 12.7:1 / 11.4:1.
2.1.1: focus-visible reveals exactly like hover; `aria-disabled` keeps refused controls in the tab order with their
reason. 1.4.12: no fixed heights that clip at 200 % text spacing (caption row is `height:14px` with `overflow:hidden` +
ellipsis — documented limitation; inline/stacked wrap).

## 4. Server

### 4.1 Operations

| Operation | 1.6.0 | Core mechanism | Notes |
|---|---|---|---|
| `create` | unchanged protocol (`afterUid?`) | parent inline CSV + NEW child datamap (`CORE/.../DataHandler.php:3171-3195`) | response adds `created {uid, table, hidden, hiddenField}` |
| `delete` | unchanged + counted translation cascade (4.5) | cmd `delete`; `deleteL10nOverlayRecords` (`DataHandler.php:5954-5990`) | nested/media stay refused `cascade` |
| `move` **new** | `{childUid, afterUid: int\|null}` | datamap `[$table][$compileUid][$field] = implode(',', $reordered)` → `checkValue_inline_processDBdata` → `RelationHandler::writeForeignField` renumbers sorting + tstamp of the members (`RelationHandler.php:1040-1077`) — what FormEngine posts for sort/drag | 409 `stale` unless childUid/afterUid ∈ compiled uids (set equality); 200 no-op on unchanged order; new field keys `canSort`/`sortReason` (`unsorted` = child schema without sortby; `controls` = enabledControls.sort AND dragdrop false); per child `canMoveUp/Down`; in workspaces only drafts renumbered **(must-fix, J1: identities only from the server)** |
| `sync` **new** | `{language: int, mode: 'localize'\|'synchronize', ids?: list<int>}` | cmd `[$parentTable][$liveDefaultParentUid]['inlineLocalizeSynchronize' => ['field','language','action']]` (`DataHandler.php:5130-5283`; same shape as `FormInlineAjaxController::synchronizeLocalizeAction`) | preflight per language: translated parent exists (else `syncNone`), `EditorRecordContext::compile` of it (403 `rights`), `mayEditField` on the translated form, `checkLanguageAccess` (`language`), target count ≤ maxitems (`max`); **refused in workspaces (`syncWorkspace`)** — Core writes the live translated parent count and live child sorting/tstamp (`DataHandler.php:5262-5281`, read, not executed → **assumed**); `synchronize` only behind a confirm naming orphan removals; `localize` default **(must-fix, J1/J3)** |
| staged hide/unhide | **not an intent** | child `hidden` datamap via the vendor store + ordinary `/visual-editor/save`; `InlineGenericSaveGuard` already allows it (hidden is neither owner nor placement field, `InlineGenericSaveGuard.php:42-58`) after `compile()`→`authorizeOwners` | server publishes `hiddenField`, `canHide`, `hideReason`; **`canHide` = field exists ∧ `mayWrite($childTable, $hiddenField)` (= not exclude, or `non_exclude_fields` right — the vendor wrapper's own rule) ∧ `appearance.enabledControls.hide` ∧ compile succeeded (page content-edit)**; DataHandler silently drops exclude fields the user may not write, so the control is never rendered where the write would vanish **(must-fix, J1)** |
| staged title | **not an intent** | child `labelField` datamap via the vendor store (`ve-editable-text.js:354-368` mirrors it to the page) | `labelField` = `LabelCapability::getPrimaryFieldName()` only for input/text (not RTE); `canEditLabel` = `mayWrite` ∧ compile; `labelValue` raw |
| copy / duplicate, restore (discard), bounded cascade preflight | **1.7.0** (section 11) | cmd `copy` paste envelope `target: -uid` (`DataHandler.php:3319-3350`); cmd `discard` (`:3373`); `deleteRecord_procFields` semantics | deferred per J3 must-fix; hard-gated by TSconfig when they ship |

### 4.2 Intent and protocol changes

`InlineIntent::parse` becomes a **per-action key table** (anything else → 400 before record resolution; `PersistenceController` unchanged):

```php
create: ['action','parentTable','parentUid','field','revision','afterUid'?]          // afterUid int>0
delete: [...common, 'childUid']                                                      // int>0
move:   [...common, 'childUid', 'afterUid']                                          // afterUid int>0|null, key required
sync:   [...common, 'language', 'mode', 'ids'?]                                      // language 1..65535, mode enum, ids list<int> ≤1000
```

`InlineRecordService::execute` is split into per-action operations (`Classes/Service/Intent/{Create,Delete,Move,Sync}Operation.php`,
one interface `apply(array $field, array $intent): array{data: array, cmd: array, cmdFirst: bool}`) because `sync` runs the
cmdmap before any datamap; lock, revision, connection check, transaction, liveSnapshot and the 422 errorLog rollback stay in
`execute` (`InlineRecordService.php:347-430`) **(graft, winner)**. Client: `collectionIntent(field, action, options = {})`
in `Shared/inline-record-actions.js` builds only the documented keys (`{childUid}`, `{childUid, afterUid}`, `{language,
mode, ids}`); `inline-record-actions.test.js` pins each envelope and that no child table/pid/sorting/language identity
leaves the browser **(must-fix, J3: signature + tests together)**.

### 4.3 Response and payload changes

Field (public): `+ childTitle, labelField, controls {new, delete, hide, sort, dragdrop, localize, info}` (resolved
`appearance.enabledControls`, so a disabled control always has reason `controls` — `delete` lacks one today,
`InlineRecordService.php:106-108`), `canSort/sortReason`, `canSync/syncReason`, `translationLanguages [{id, title,
parentUid, missing, orphaned, canSync, syncReason}]`, `translationMode ('default'|'connected'|'free')`, `languageId`,
`workspace {id, title, firstVersion: bool}`, `hiddenField`, `requiredBackend [fieldNames]`, `typeField`. Child: `+ state
('live'|'new'|'modified'|'deleted')`, `liveUid` (display/join only — never accepted by any intent), `hiddenField`,
`canHide/hideReason`, `canEditLabel/labelValue`, `canMoveUp/canMoveDown`, `translationOnly`, `untranslated [languageIds]`,
`cascade {translations}`. Delete placeholders are **listed** (`state:'deleted'`, `canDelete=false`, no verb) instead of
skipped (`InlineRecordService.php:84-87`). Execute response: `{success, uid?, created?, moved? {uid, position}, synced?
{language, localized, removed}, workspace {id, firstVersion}, collections: [publicField…]}`. `publicField()` keeps
stripping `deleteUid`, `_defaults`, `_config` and strips placeholder version uids **(must-fix, J1)**.
`FrontendConfiguration::build()` adds `collectionTypes`, `workspaceTitle`, `actionLabelMode`, `entriesLayout`.
`EditorRecordContext::compile` gets a per-request memo (`table:uid:workspace`) so hover discovery, translated-parent
preflight and sync preflight never recompile the same rows.

### 4.4 Workspace: first creation (decision, narrowed invariant, tests)

**Recommendation to the owner (astra finding 5): allow first workspace versioning through Core's stock auto-version
datamap** (`DataHandler::process_datamap` → `workspaceAllowAutoCreation` → version new, `DataHandler.php:921-959`; the
astra probe proved the route) **under a narrowed invariant**, verified inside the transaction by `liveSnapshot(v2)`:
live parent row byte-identical; for the requested relation and every nested inline/file relation Core versions with it
(depth ≤ 3) identical live member set, identical `(sortby, uid)`-ordered sequence, identical content/owner/table/match/
language/visibility/deletion/pid columns; the **only** permitted deltas are the schema sortby column (new values
consecutive, same order, strictly increasing) and the schema tstamp column of live rows in that subtree, and only when
the parent had no draft before the request (`resolvedRow.t3ver_wsid === 0`); translations and unrelated rows
byte-identical; later writes on drafts stay fully byte-identical (existing `repeatedWorkspaceWritesNeverChangeLiveRows`).
Any other delta → rollback + 409 `workspaceLiveChange`. The "parent must already be a draft" refusal
(`InlineRecordService.php:189-191`) is dropped **only when** TSconfig `firstVersion = 1`; the "any child from another
workspace" rule narrows to children of **foreign** workspaces (Core reuses existing child drafts, `DataHandler.php:6672-6675`);
versioningWS, stage (`workspaceCheckStageForCurrent`) and foreign-workspace checks stay.

**Shipping rule (must-fix J1/J3 — the recorded decision note keeps the strict contract):** 1.6.0 code ships
`tx_visualeditorenhancements.inlineRecords.firstVersion = 0` (today's refusal with the new reason `firstVersion` and the
sentence "Your first change to this element would create a workspace draft; this site has not enabled that here. Open the
element in the backend workspace first."). The owner's approval of D1 is the written decision; the default flips to 1 in a
**dedicated commit** only after the eight MariaDB functional tests and the runtime workspace scenario are green (else 1.6.1).
Rationale for the owner: FormEngine saves and the vendor's own text edits of an unversioned parent already run exactly
this bookkeeping on the same page; refusing it only for row operations protects nothing. Caveats stated in docs, not
called harmless: live child tstamp changes are real writes that caches, refindex or integrations (Solr queue on child
tables, webhooks) may observe; stored sorting values are renumbered (visible order unchanged). Staged hide/title need no
parent draft (Core auto-versions the child alone, `DataHandler.php:921-959, 9525-9540`).

Functional tests (MariaDB mandatory, SQLite too): `firstWorkspaceCreateVersionsParentAndChildrenAndOnlyRenumbersLiveSortingAndTstamp`
(fixture sorting 10,30 → 1,2), `firstWorkspaceCreateWithNestedChildrenRenumbersOnlyAffectedNestedRelations`,
`firstWorkspaceCreateWithConnectedChildTranslationsLeavesTranslationRowsUntouched`,
`firstWorkspaceCreateOnPartiallyVersionedChildrenReusesExistingDrafts`, `firstWorkspaceMoveFollowsTheSameInvariant`,
`firstWorkspaceDeleteCreatesDeletePlaceholderWithoutLiveContentChange`, `liveSnapshotMaskRejectsAnyOtherLiveChange`,
`firstVersionRefusedWhenTsconfigDisablesIt`; `repeatedWorkspaceWritesNeverChangeLiveRows` kept.

### 4.5 Translations (connected/free, `inlineLocalizeSynchronize`)

**Recommendation (astra finding 6): translations follow FormEngine, each part behind TSconfig with the 1.5 behaviour
one key away (must-fix J2); defaults are the owner's decision (D2, D3).**

- (a) **`translatedParents = parity | default-only`** (recommended `parity`): connected translated parents
  (`transOrigPointer > 0`) are no longer refused `translation`: create makes a translation-only row in the parent's
  language with `l10n_parent 0` (what FormEngine's "+" does, `DatabaseRowInitializeNew.php:220-237`); delete/hide/move act
  on the translated rows; Core's `inlineLocalizeSynchronize` keeps translation-only rows anchored (`DataHandler.php:5186-5241`).
  Badges "Only in this language" / "Not translated here"; strip note; hint "Order follows the default language after a
  sync". Free-mode parents (MTUG camp, pointer 0) already pass. `l10n_mode = exclude` check stays.
- (b) **`deleteTranslations = confirm | refuse`** (recommended `confirm`): `translations` stops being a refusal when
  **every active translated child row** (DeletedRestriction + WorkspaceRestriction, placeholders skipped — Core-accurate
  since 1.5.0, `InlineRecordService.php:235-248`) compiles for this user **and** passes
  `checkRecordEditAccess($table, $row, false, true)` **(must-fix, J1)**; Core cascades them (`deleteL10nOverlayRecords`);
  the Core modal reads "Remove «x»? Also removes 3 translations (de, zh, hu). It disappears from the live site
  immediately." / workspace "…marks 3 translations for deletion." Page 8430: 0 → all 82 leaf entries removable for admins.
- (c) **`syncTranslations = 1`**, **`syncDefaultMode = localize`**: the `sync` intent (4.1) on default-language parents;
  the footer menu lists languages with missing/orphan counts; `synchronize` confirms orphan removals; refused in
  workspaces (`syncWorkspace`). Content Blocks never sets `showSynchronizationLink`, so this is more than FormEngine parity
  for desiderio — documented.
- Hidden is per record (Core): the eye on a default row hides only that row; strip hint "Visibility is per language" when
  connected translations exist. Title edits are per language by construction (child identity = translated uid).

### 4.6 Cascade and delete preflight

1.6.0 keeps `cascade` refused for children with populated inline/file columns (`InlineRecordService.php:250-254`): pricing
plans with features, gallery/logo items with images — the reason names the blocker and deep-links the contextual form.
1.7.0 adds `Classes/Service/InlineCascadePreflight`: RelationHandler members per inline/file field honouring
`behaviour.enableCascadingDelete !== false`, translations as Core selects them, per dependent `checkModifyAccessList` +
`checkRecordEditAccess($t, $row, false, true)` + page CONTENT_EDIT + same DB connection; TSconfig `cascade.maxDepth = 3`,
`cascade.maxRecords = 50`; `child.cascade {translations, children, files, truncated}`; FAL = `sys_file_reference` rows only
("removes 1 image reference, the file stays"); workspace = Core's delete-placeholder cascade; restore of listed placeholders =
cmd `discard` on the placeholder version uid found by the server (scoped intent only). Live undo after removal is not
offered (D1/D5 of the 2026-11-05 plan stand). The relation lock stays `vee-inline:<table>:<live>:<field>`; nested
relations are Core-level concurrency (documented).

### 4.7 Defaults

`createHidden` stays 1 in live (new rows start hidden; workspaces create visible; `InlineRecordService.php:149,302-308`)
— the staged eye makes "Add → type → eye → Save" the one-save flow; `createHidden = 0` remains the documented site switch.
TSconfig block after 1.6.0:

```
tx_visualeditorenhancements.inlineRecords {
  enabled = 1            createHidden = 1        requireWorkspace = 0      # existing
  firstVersion = 0       # 1 after D1 evidence (dedicated commit)
  translatedParents = parity          # D2 (default-only restores 1.5)
  deleteTranslations = confirm        # D3 (refuse restores 1.5)
  syncTranslations = 1   syncDefaultMode = localize
  tables.<child>.enabled = 1
}
```

User TSconfig recommendations for desiderio editors: `setup.default.tx_visualeditor_inlineRecords = 1`,
`setup.default.tx_visualeditor_actionLabels = caption`, `setup.default.tx_visualeditor_entriesLayout = inspector`.
`TCAdefaults.<childTable>.<field>` (Core page TSconfig) remains the way to avoid "New entry" placeholders (8430 sets
`TCAdefaults.feature_cards_items.title = New card`).

### 4.8 Security kept (astra findings 1–4 stay fixed)

- Intents only; no DataHandler maps from the browser; per-action key tables reject any unknown key with 400 before record
  resolution; mixed envelopes 400.
- `InlineGenericSaveGuard` unchanged: NEW owned children, parent inline fields, owner/match/placement/`t3ver_*` fields and
  child commands stay refused in ordinary saves; `tt_content` exemption preserved; paste `update` validated recursively.
  **New regression test:** client-sent `inlineLocalizeSynchronize`/`copy`/`discard` on an owned child table is still 400
  **(must-fix, J1)**.
- `EditorRecordContext::compile` → `authorizeOwners` reused for every translated parent, translated child, sync target and
  generic staged child write (title, hidden); depth ≤ 16, cycles refused; `overrideChildTca`, FAL/MM/selector/unique/type
  selectors stay `unsupported`/`required`.
- Nonblocking Core relation lock, revision sha256 over parent identity + workspace + ordered compile uids (a move changes
  it) → 409 `stale`; same-connection check; transaction + rollback on errorLog or snapshot mismatch; liveSnapshot masked
  only for the first-version step and only when `firstVersion = 1`.
- Rights-gated controls computed server-side (`canHide`, `canEditLabel`, `canSort`, `canSync` per language) so DataHandler's
  silent exclude-field drops never masquerade as success; no count/label disclosure for tables the user cannot modify;
  one inaccessible child keeps the list private.
- Core modal confirm (Cancel default, focus restore) for every delete and for `synchronize`; creations stay reversible
  (hidden row → Remove/discard).
- `deleteUid`, `_defaults`, `_config`, placeholder version uids stripped at the public boundary; `liveUid` is display-only
  and never accepted by any intent.

## 5. The matrix: operation × context

| Operation | Context | Core mechanism | 1.6.0 server | UI |
|---|---|---|---|---|
| add (end) | live, default language | parent CSV + NEW child, `hidden=1` (createHidden) | allowed; `created {uid, hidden, hiddenField}` | bar **Add entry** (1 click) → row dimmed on page, focus in its editable; eye + hint in the inspector |
| add (end) | live, `createHidden = 0` or workspace | same, visible | allowed | no hidden hint |
| add (after row) | all | `afterUid` (existing) | 409 `stale` if afterUid unknown | row **＋** |
| add | connected translated parent (desiderio de/zh/hu) | translation-only child, `l10n_parent 0`, language = parent | allowed when `translatedParents = parity`; else `translation` | badge "Only in this language"; strip "Connected translation" |
| add | free translation (MTUG camp) | unchanged (pointer 0) | allowed | strip "Free translation" |
| add | workspace, no parent draft | Core auto-version of parent + children; live sorting renumbered 1..n + tstamp | `firstVersion = 0` → reason `firstVersion`; `= 1` → allowed under the narrowed invariant | reason sentence; or "Draft created…" |
| add | workspace, parent draft exists | NEW_PLACEHOLDER child, live rows byte-identical | allowed | badge "New in draft" |
| add | workspace, children drafted, parent live | `versionizeRecord` reuses child drafts | as "no parent draft" | same |
| add | restricted editor | compile + tables_modify + non_exclude_fields (+ `<child>:hidden` in live) + checkLanguageAccess | reasons `rights`/`hidden`/`language`; list hidden when child table not modifiable | `aria-disabled` + reason; no count leak |
| add | max / `enabledControls.new = false` / `showNewRecordLink = false` | Core enforces nothing for foreign_field | `max` / `controls` | `n / max`; disabled with reason |
| add | required media / child type selector | FormEngine only | `required` + `requiredBackend` / `unsupported` + `typeField` | "Add in backend form: image" link |
| add | shared child table (tablenames/fieldname) | `writeForeignField` writes discriminators | unchanged | invisible |
| add | nested collection (plan → features) | same on the nested parent | unchanged (Item settings → nested list) | breadcrumb "Pricing › Plan 2 › Features" |
| remove | live, default, leaf, no translations | cmd `delete` | allowed | **×** → Core modal → immediate |
| remove | live, default, connected translations | Core cascade `deleteL10nOverlayRecords` | `deleteTranslations = confirm` and every translated row compiles + delete access → allowed with `cascade.translations = n`; else `translations` | modal "Also removes 3 translations" |
| remove | translated parent's row | deletes only that row | allowed (parity) | modal "Remove the German entry only?"; source row shows "Not translated here: de" |
| remove | nested/media child | Core cascade of members/references | refused `cascade` (1.7 preflight) | disabled + backend link |
| remove | workspace, no draft | delete placeholder + parent CSV → first version | as `firstVersion` | modal "staged in this workspace" |
| remove | workspace, draft / NEW child | DELETE_PLACEHOLDER / discard | verbs `markForDeletion` / `discard` (existing); placeholders listed read-only | badge "Marked for deletion" (Undo = 1.7) |
| remove | `count <= minitems` / `enabledControls.delete = false` | — | `min` / `controls` | disabled with reason (desiderio: 110 collections minitems 1, 20 ×2, 7 ×3) |
| remove | row with staged (unsaved) text or eye | — | client `pendingRow` | Remove waits; **Save now** |
| reorder | live, child table with sortby | parent CSV order → `writeForeignField` renumbers | `move`; `unsorted`/`controls` | **↑↓**, Alt+Arrow; immediate |
| reorder | connected translation | translated parent's CSV; next `synchronize` rebuilds default order | allowed (parity) | hint "Order follows the default language after a sync" |
| reorder | workspace | drafts only renumbered; first version as above | as add | strip shows workspace |
| hide / unhide | live | staged child `hidden` via vendor store, saved by the vendor Save; Reset undoes | `canHide` (mayWrite ∧ controls.hide ∧ compile) | **eye** (`role=switch`); page marks follow the store |
| hide / unhide | connected translations exist | Core hides this record only | `hiddenPerLanguage` hint | strip "Visibility is per language" |
| hide / unhide | workspace, no parent draft | child auto-versioned alone | allowed regardless of `firstVersion` | badge "Changed in draft" after Save |
| hide / unhide | restricted editor without `<child>:hidden` | DataHandler would drop silently | `canHide = false`, `hideReason = rights` | eye `aria-disabled` "needs the Hidden field right" |
| title edit | all | staged child `labelField` via vendor store; page editable mirrors | `canEditLabel` | inline input; Pending dot; Enter/blur/Escape |
| edit texts | all | vendor editables (unchanged) | owner-chain guard | unchanged |
| sync | live, default parent, translations missing | `inlineLocalizeSynchronize localize\|synchronize` | per-language compile + language access; `syncNone` when complete | footer "Sync translations ▾ de · zh (1 missing) · hu"; `synchronize` confirms orphans |
| sync | workspace | Core writes live translated metadata (assumed) | `syncWorkspace` | disabled with reason |
| sync | free-mode parent | Core returns early | not offered | — |
| sync | restricted editor / max | `checkLanguageAccess`; count check | `language` / `max` | languages greyed with reason |
| labels | all users | adopted CSSStyleSheet, `attr(aria-label)` | — | `caption\|inline\|stacked\|icons` |
| context | admin parked in workspace 4 | Core auto-versioning everywhere | `workspace {id, title}` | strip always "Workspace draft «AI Review Test»"; docs: leave the workspace for demos |
| context | stage > 0, non-owner editor | `workspaceCheckStageForCurrent(0)` false | reason `stage` | "Element is in review; editing stage closed" |
| rows without markers / unmarked themes (all of desiderio in 1.6) | all | — | — | inspector only (by contract); dimming via editables after fetch |

## 6. Desiderio changes

**4.34.0 (with VEE 1.6.0 — copy and screenshots only, no template change):**
- `Build/Scripts/capture-visual-editor-entries.mjs`: resolve the accordion by CType `desiderio_accordion` on page 8430
  (uid 200211 changes on reseed); hooks `[data-ve-enhancement="inline-records"]`, `[data-ve-enhancement="inline-add"]`,
  `.entryItem`, `.entryTitle`, `[data-child-edit]`, `[data-ve-labels-pinned]`; recapture
  `feature-visual-editor-entries-*`, `-entry-form-*`, `-collection-settings-*` (16:10, DSF 2, dark) + two new shots
  (bar with caption label, tray layout); paste the printed hashed names into `Classes/Data/Showcase/ShowcaseFeatureDefinitions.php`
  (`visualEditor()`), `ShowcaseAudiencePages.php:76`, `ShowcaseSuiteCompanionPage.php:81`,
  `site_package/Build/Film/films/features.mjs:88-91`. `backend-visual-editor-7f9bffd8.webp` stays valid (labels hidden
  at rest in caption mode).
- Copy within canon limits: `visualEditor()` abstract, hero subheadline, problem, tour shots 1/2/4, benefits 1–2,
  FAQ 1–3/5, CTA ("Add entry from the toolbar, rename, reorder, show or hide entries in the inspector; one Save");
  `ShowcaseVisualEditorInlinePage.php` bodytext ("New entries start hidden; show them with the eye, then Save"; "Adding,
  removing and moving save immediately; renaming and visibility save with the page"); keep the words **"Entries"**,
  **"Add entry"**, **"New entries start hidden"** so the film stays picture-only **(must-fix, J3)**; `lint-copy.php`.
- Translation memories: append de/zh/hu entries for every changed English string
  (`sitepackage:content:translate --site=desiderio --pages=… --dry-run --missing=-`).
- Film: `render-films.mjs --film=visual-editor --check-picture` → `--refresh-picture` → `sitepackage:films:seed
  --film=visual-editor --pictures-only`; no re-voicing (narration lines 3/5/6/10 stay true).
- `Build/Scripts/refresh-feature-screenshots.php`: add the `feature-visual-editor-*` names to `ALLOWED_SHOTS` so pages
  1094/8436 swap the file in place without a reseed.
- CHANGELOG; version 4.34.0 in `composer.json` + `ext_emconf.php`.

**4.35.0 (with VEE 1.7.0 — markers):** codemod + six molecule `attributes` arguments + exception list (section 2.4);
`Tests/Unit/ContentBlockCollectionMarkerTest.php` (`testEveryCollectionLoopCarriesRowMarkerOrIsListedException`,
`testNoMarkerOutsideCollectionLoops`, `testRowRootComponentsAcceptAttributes`, `testMarkerCollectionNameMatchesTheLoopedField`);
`ComponentStructureTest::testRowRootMoleculesDeclareAttributes`; `SeedVisualEditorInlinePageFunctionalTest` extension
(edit-mode render of 8430 has one `data-ve-item` per source child of feature-cards/product-specs/definition-list/data-table,
none for gallery; anonymous render has no `data-ve-*`); desiderio content-block skill note.

**Test page 8430** stays the showcase (23 elements, 82 source entries, all connected-translated; tt_content 200210
feature cards, 200211 accordion, 200223 data table nested, 200224 comparison table nested, 200225 pricing three-tier
min=max 3 nested, 200228 gallery required image). Expected after 1.6.0 for the lab admin in **live**: every leaf entry
removable with the counted confirm; nested rows still `cascade`; reorder/hide/title on every collection; Add entry on 20
of 22 collection elements (gallery/logo cloud → "Add in backend form: image"); Sync offered only after a new English row.
Owner prerequisites before a manual look: enable `tx_visualeditor_inlineRecords` for be_users 1 and **leave workspace 4**
(otherwise every edit lands in "AI Review Test" and the strip says so). Prod showcase uid is 8158 (resolve by slug).
Untranslated, workspace and restricted-editor proof runs on the disposable fixture page below 8430
(`Tests/Integration/VisualEditor/fixture.php` modes fixture/extra/visible/settings/version/cleanup + new `localize`,
`session`, `ve_editor`), never with the owner's password.

## 7. Verification

- **PHP unit** (`composer test:unit`): `InlineIntentTest` `validActions` provider (move with afterUid null/int, sync
  localize/synchronize/ids) + forged variants (afterUid == childUid, mode `copy`, language string, extra key, `hidden`
  key on any action); `ActionLabelModeTest::fromUserSettingFallsBackToCaption`; `EntriesLayoutTest::fromUserSettingFallsBackToInspector`;
  `FrontendConfigurationTest::buildExposesActionLabelModeEntriesLayoutWorkspaceTitleAndCollectionTypes` (provider
  null/garbage → defaults); `LibraryLabelParityTest::everyEnglishUnitHasAGermanUnitAndNoOrphans`.
- **PHP functional** (`Build/Scripts/runTests.sh -s functional` on SQLite, then once on MariaDB with the port from
  `ddev describe -j`): 23 existing cases + `reorderRewritesChildSortingInSubmittedOrderAndLeavesParentRowUntouched`,
  `reorderWithForeignSetOrDuplicateUidsIsStaleWithoutWrites`, `reorderInWorkspaceTouchesOnlyDraftRows`,
  `reorderRespectsDisabledSortControlAndUnsortedChildTables`, `childHiddenToggleThroughGenericSaveRequiresNonExcludeRight`
  (restricted user: 200 but row unchanged → payload `canHide=false`), `childHiddenToggleInWorkspaceCreatesChildDraftOnly`,
  `childLabelEditThroughGenericSaveIsAuthorizedByOwnerChain`, `controlsMapChildTitleAndStatesArePublished`,
  `deletePlaceholdersAreListedReadOnly`, `translatedParentAddsTranslationOnlyRowInParentLanguage`,
  `defaultOnlyTsconfigRestoresTranslationRefusal`, `defaultDeleteCascadesToEditableTranslationsAndIsRefusedForInaccessibleOnes`,
  `deleteTranslationsRefuseTsconfigRestoresBlanketRefusal`, `syncLocalizesMissingChildrenInDefaultOrderAndKeepsTranslationOnlyRows`,
  `syncSynchronizeRemovesOrphansOnlyInThatMode`, `syncRequiresEditAccessOnEveryTranslatedParentAndLanguage`,
  `syncInWorkspaceIsRefused`, the eight `firstWorkspace*`/`liveSnapshot*` tests (4.4),
  `clientSentLocalizeSynchronizeCopyOrDiscardOnChildTablesIsStillRejected`, `nestedPasteUpdatesAreStillValidated`,
  `newOwnedChildrenInOrdinarySavesAreStillRefused`, `publicFieldStripsInternalIdentitiesForNewCapabilities`,
  `collectionFieldIndexListsInlineFieldsPerRecordTypeAndHonoursDisabledTables`; `Tests/Functional/ViewHelpers/Mark/ItemViewHelperTest`
  (`rendersNothingOutsideEditMode`, `rendersDataAttributesWithLocalizedUidInEditMode`, `rendersVersionedUidInWorkspace`,
  `rendersHiddenFlagForDisabledRecords`, `rendersArrayFormatForComponents`, `throwsForUnknownCollectionColumn`).
- **Node** (`composer test:js`): `action-bar-label-css.test.js` ("icons mode produces only the base rules", "caption
  reserves a row and reveals only the current, focused or pinned label", "inline and stacked have no hover selectors",
  "every mode uses attr(aria-label) and honours data-ve-label", "hover:none switches caption to inline and reduced-motion
  removes transitions"); `action-labels.test.js` ("short label applied only when contained in the accessible name, per
  language"); `inline-record-actions.test.js` (envelopes for move/sync, no identity leak; `structuralBlockReason`:
  saving/uncertain/invalid block all, other-element changes block nothing, same-row changes block Remove only);
  `collection-rows.test.js` (`groupsMarkersByCollectionAndParent`, `picksPrimaryMarkerWhenChildRenderedTwice`,
  `resolvesNestedParentFromEnclosingMarker`, `joinsPanelChildrenByUidThenLiveUid`, `fallsBackToEditablesWhenNoMarkers`);
  `entry-page-marks.test.js` (marks every editable of a hidden child, clears on staged un-hide, re-derives on `saved`);
  `config.test.js` (new keys, defaults, unknown values).
- **Playwright components** (`node_modules/.bin/playwright test --config Tests/E2E --project=components`, login-free,
  real Lit + real vendor store): `action-bar-labels-component.spec.js` ("adopts one shared stylesheet per element and
  keeps vendor buttons intact", "caption shows the current button's accessible name without changing the bar width",
  "the caption stays while the pointer moves onto it inside the bar", "the contextual edit trigger host renders its label
  or the slotted fallback", "a click pins the caption until the pointer leaves", "keyboard focus reveals the label",
  "a Lit re-render after toggling hidden keeps labels and injected buttons", "hover:none shows inline labels");
  `field-chooser-component.spec.js` extended ("Add entry is present from first render for CTypes with collections and
  aria-disabled with reason after discovery", "Add entry creates at the end, refreshes and focuses the new editable",
  "title input stages the label field and the page editable follows", "toggling the eye twice leaves the store without
  changes", "move up/down are disabled at the ends and post one move intent; Alt+Arrow does the same", "remove asks the
  Core modal with cascade counts", "a row with staged text disables Remove only and offers Save now which sends doSave",
  "a second add while text is pending lists the row and says the page updates after Save", "panel stays open on page
  pointerdown and closes on Escape and ×", "translated parent shows parity badges and the sync menu lists only languages
  with missing entries", "marked-for-deletion rows are listed read-only", "inspector, tray and anchored render the same
  actions at 1280 and 390 px, light and dark", "Escape returns focus to the trigger").
- **Runtime E2E on DDEV** (`MAIN/Tests/Integration/VisualEditor/runtime.spec.js`, disposable users via `fixture.php`;
  new modes `localize` — cmd localize of the fixture checklist into language 1 —, `session` — fixated BE cookie like
  `var/film-ve151/bootstrap.php` for one storageState —, `ve_editor` restricted group with Staging membership; wrapper
  `Build/Scripts/ve-runtime.sh fixture|run|cleanup` copying `var/ve-integration/fixture.json` out of the container):
  "Add entry from the bar ends visible on the anonymous page after eye + Save", "add after inserts between rows (sorting via
  ddev mysql)", "move re-renders the page in the new order", "remove with translations cascades like FormEngine (counted
  modal)", "translated disposable element allows a translation-only row and sync localizes the missing one", "workspace:
  existing drafts create/discard with live-byte snapshot; unversioned parent refused under firstVersion = 0 and
  versioned with only sorting/tstamp deltas under 1 (SQL diff)", "restricted editor sees reasons, 403 outside the mount,
  no eye without the hidden right", "labels reveal on hover/focus/click in the real bar and survive the vendor hide-toggle
  re-render"; axe (`node_modules/axe-core/axe.min.js` injected into the edit frame, wcag2a/aa/21aa/22aa) in three states
  (bar hovered with caption, inspector open, Core modal open) + explicit 1.4.13 checks; cleanup asserts
  `remainingActivePageOwnedRows == {}` and `be_sessions` 0 for the disposable users.
- **Static/quality:** `composer cgl:check`, `composer phpstan` (level 8, no baseline), `composer lint:php`, docs render
  (render-guides Docker, 0 warnings), `git diff --check`; **CI gains a `components` Playwright job** (chromium, login-free)
  **(must-fix, J3)**.
- **Desiderio/lab gates** (WP-M): `lint-copy.php --site=desiderio`, `sitepackage:content:audit --site=desiderio` (0 errors),
  `sitepackage:content:translate --dry-run --fail-on-missing` = 0 missing before and after, `render-films.mjs --check-picture`,
  `SeedFilmsCaptionsTest` (burnedInCaptions false), `SeedStyleguidePagesCommandFunctionalTest`, four showcase pages 200 in
  en/de/zh/hu, lab `Tests/E2E/visual-editor-inline-page.spec.js` (anonymous pages unchanged, no editor markup).
- **What the owner runs:** nothing mandatory. Optional: the `enhancements` Playwright project with `VEE_BACKEND_PASSWORD`
  on page 8430; a manual look in live after enabling the setting and leaving workspace 4; approval of the screenshots of
  three layouts × four label modes.

## 8. Work packages for Opus 5.5 workers

Estimates already carry the ×1.5 realism factor the implementability judge required. One PR per work package with its
gates green; every commit ends with the attribution line from the system reminder; no PR/push to third-party repos; workers
on Opus 5.5. Train 1 = VEE 1.6.0 + desiderio 4.34.0; Train 2 = VEE 1.7.0 + desiderio 4.35.0.

| WP | Title | Repo | Scope | Deliverables | Gates | Depends on | h |
|---|---|---|---|---|---|---|---|
| WP-A | Capability payload, Operation split, CollectionFieldIndex, compile memo | VEE | 4.3: `controls`, `childTitle`, `labelField/canEditLabel/labelValue`, `hiddenField/canHide/hideReason` (mayWrite ∧ controls.hide ∧ compile), `state`, `liveUid`, `translationOnly/untranslated`, `translationMode/languageId`, `workspace {id,title,firstVersion}`, `requiredBackend/typeField`, listed placeholders; `Classes/Service/Intent/*Operation.php` split; `Classes/Service/CollectionFieldIndex.php` (TcaSchema) → `FrontendConfiguration` `collectionTypes` + `workspaceTitle`; per-request compile memo in `EditorRecordContext` | changed classes; functional `controlsMapChildTitleAndStatesArePublished`, `deletePlaceholdersAreListedReadOnly`, `childHiddenToggleThroughGenericSaveRequiresNonExcludeRight`, `childLabelEditThroughGenericSaveIsAuthorizedByOwnerChain`, `childHiddenToggleInWorkspaceCreatesChildDraftOnly`, `collectionFieldIndex…`, `publicFieldStrips…`; `Collections.rst` payload table | phpstan 8, cgl, unit, functional SQLite + MariaDB | — | 12 |
| WP-B | `move` intent + per-action protocol | VEE | 4.2 key tables; `MoveOperation` (set equality, no-op, `unsorted`/`controls`, lock/revision/transaction/snapshot); `collectionIntent(field, action, options)`; labels `moveUp/moveDown/unsorted` EN/DE | `InlineIntent.php`, `MoveOperation.php`, `Shared/inline-record-actions.js`; `InlineIntentTest` providers; functional `reorder*` ×4; node envelope tests | phpstan 8, cgl, unit, functional MariaDB, node | WP-A | 8 |
| WP-C | Translations: parity, counted cascade, `sync` | VEE | 4.5 TSconfig keys (`InlineRecordConfiguration`), conditional `translation` refusal, `deleteReason` per-translated-row compile + delete access + counts, `translationLanguages`, `SyncOperation` + preflight, `syncWorkspace`; labels `syncNone/syncWorkspace/orderFollowsDefault/confirmTranslations(+Stage)/badgeTranslationOnly/badgeUntranslated/hiddenPerLanguage` EN/DE | classes; functional `translatedParent…`, `defaultOnlyTsconfig…`, `defaultDeleteCascades…`, `deleteTranslationsRefuse…`, `sync*` ×4, `freeModeTranslatedParentCreatesOwnChildRows`; `Collections.rst` "Translations" | phpstan 8, cgl, functional SQLite + MariaDB, XLF parity | WP-A | 12 |
| WP-D | First workspace versioning under the narrowed invariant | VEE | 4.4: `liveSnapshot v2` (dependency walk depth ≤ 3, masked sortby/tstamp with order + monotonic check, only when the parent had no draft), TSconfig `firstVersion` (default 0, reason `firstVersion`), foreign-workspace-only child rule, response `workspace.firstVersion`; **flip commit prepared separately, applied only on D1 + green evidence** | classes; eight functional tests on MariaDB; `Collections.rst` "Workspace boundary" with the tstamp caveat; release-note text | phpstan 8, cgl, functional MariaDB mandatory, `repeatedWorkspaceWritesNeverChangeLiveRows` still byte-identical | WP-A, WP-B | 12 |
| WP-E | Astra 1–4 regression pins | VEE | 4.8 tests: `clientSentLocalizeSynchronizeCopyOrDiscardOnChildTablesIsStillRejected`, `nestedPasteUpdatesAreStillValidated`, `newOwnedChildrenInOrdinarySavesAreStillRefused`, `ttContentAsInlineChildKeepsOrdinaryOperations`, `overrideChildTcaStaysUnsupported`, unknown-key 400 for every action | tests only (no production code) | functional SQLite + MariaDB | WP-B | 4 |
| WP-F | Action bar: labels, `ActionLabelMode`, Add entry, instant discovery | VEE | 3.1–3.5; `Frontend/action-bar-labels.js`, `Shared/action-bar-label-css.js`, `Shared/action-labels.js` (containment check); `Classes/Enum/ActionLabelMode.php`, `be_users.php`, `FrontendConfiguration` `actionLabelMode`, `Shared/config.js`; `element-field-actions.js` refactor (`makeButton` popup flag, short aria-labels, no title outside icons, `hidden`/`aria-disabled`, discovery via `collectionTypes`, delete hover fetch, `injectInlineAddAction`), `Frontend/add-entry.js` (create → refresh → open inspector → focus editable; ambiguous → open panel); `Shared/icons.js` (`addEntryIconSvg`, `listIconSvg`, `caretUp/Down`, `eyeSlash`, `documentSynchronize`, `documentLocalize`, `formInsertAfter`, `workspace` paths from Core T3Icons with identifier comments); XLF `actionLabels.*`, `frontend.actionBar.*` EN/DE | modules; node `action-bar-label-css.test.js`, `action-labels.test.js`, `config.test.js`; `action-bar-labels-component.spec.js`; `ActionLabelModeTest`, `FrontendConfigurationTest`; `Usage.rst` "Action bar labels", `Configuration.rst` confval + `setup.default`, `Compatibility.rst` | node, components, phpstan 8, unit, cgl, docs | WP-A (for `collectionTypes`) | 12 |
| WP-G | Entries inspector rows | VEE | 2.2, 2.3, 2.5: `collections.js` rewrite + `row-menu.js`, context strip, badges, count/min, title input + eye (setInitialData → setData), move, add after, remove with counted confirm, `aria-disabled` reasons; `ve-field-chooser.js` (entries view ignores outside pointerdown; focus rules; Alt+Arrow; Delete key); `inline-record-actions.js` + `inline-records.js` (`recordTree`, scoped `structuralBlockReason`, `pendingRow`, **Save now** via `sendMessage('doSave')`); `element-refresh.js` (skip only on the wrapper's own staged tree); `Frontend/entry-page-marks.js` + `editable-overrides.css`; `styles.js`; XLF `frontend.inlineRecords.{moveUp,moveDown,hide,unhide,addEntry,pendingRow,saveNow,savedPageLater,contextLive,contextWorkspace,modeDefault,modeConnected,modeFree,badgeNew,badgeModified,badgeDeleted,badgePending,movedTo,hiddenHint,hiddenOnPage,draftCreated,firstVersion,stage}` EN/DE | modules + CSS; node tests (`inline-record-actions`, `entry-page-marks`); `field-chooser-component.spec.js` 13 new cases; `Collections.rst` "Entries inspector" (what saves immediately vs with the page, pending rule) | node, components, cgl, docs | WP-A, WP-B, WP-F | 14 |
| WP-H | Three panel layouts | VEE | 2.7: `Classes/Enum/EntriesLayout.php`, `be_users.php`, `FrontendConfiguration` `entriesLayout`, `config.js`, `#position()` per layout, `styles.js` variants (tray table, anchored popover), light/dark | enum + styles; `EntriesLayoutTest`; component spec "inspector, tray and anchored…"; screenshots for the owner; `Configuration.rst` confval, `Usage.rst` "Where the entries panel lives" | unit, components, phpstan 8, cgl, visual check light/dark 1280 + 390 px | WP-G | 8 |
| WP-I | Translations + workspace UI | VEE | footer "Sync translations ▾" (localize default, synchronize confirm), row menu "Localize to …", parity badges, strip modes, counted confirm texts (`Shared/confirm-delete.js`), `firstVersion`/`stage`/"Draft created" messages, placeholders listed read-only | modules; component cases (sync menu, synchronize confirm, counted cascade, badges, first-version message); `Collections.rst` user sections "Translations", "Workspaces" | components, node, cgl, docs | WP-C, WP-D, WP-G | 7 |
| WP-J | `f:mark.item` ViewHelper + `collection-rows.js` consumers | VEE | 2.4: `Classes/ViewHelpers/Mark/ItemViewHelper.php`; `Frontend/collection-rows.js` (grouping, primary marker, parent resolution, uid/liveUid join, mirror highlight hook, render-time dimming CSS) wired into the injection sweep (attribute scan only, no DOM insertion) | ViewHelper; `ItemViewHelperTest` (6); `collection-rows.test.js` (5); `Collections.rst` "Row markers in templates" with the one-line adoption recipe | functional, node, phpstan 8, cgl, docs | — | 8 |
| WP-K | Runtime E2E, fixture extension, axe, CI components job | lab (`Tests/Integration/VisualEditor`, `Build/Scripts/ve-runtime.sh`) + VEE (`.github/workflows/ci.yml`, `Tests/E2E/README.md`) | section 7 runtime scenarios; `fixture.php` modes `localize`, `session`, `ve_editor`; wrapper script; axe injection; cleanup assertions; CI `components` job | scripts, spec, README recipe, evidence log with SQL diffs (workspace, cascade) | runtime green with admin + restricted editor, axe clean, cleanup verified, CI green | WP-F, WP-G, WP-H, WP-I, WP-E | 12 |
| WP-L | Docs, XLF parity, CHANGELOG, release 1.6.0, lab lock bump, deploy | VEE + lab | `README`, `Collections/Usage/Configuration/Compatibility/KnownProblems.rst` final pass (honest limits list = section 11), CHANGELOG `## [1.6.0]` (behaviour changes named with their "restore 1.5" keys), tag `v1.6.0`, GitHub + GitLab mirror (`Build/Scripts/mirror-to-gitlab.sh`), lab lock bump (section 9), lab CHANGELOG, push main (list `origin/main..HEAD` as its own step), Coolify health | tag, lock commit, deploy verified | all WP gates green, CI green, docs render 0 warnings, deploy health | WP-A…WP-K | 6 |
| WP-M | Desiderio showcase follow-up 4.34.0 + lean prod run | DES + site_package + lab | section 6 (4.34.0 part): capture script, copy, memories, film picture refresh, `ALLOWED_SHOTS`, release 4.34.0 (tag + mirror), lab reseed of the four pages + `films:seed --pictures-only` + translate `--pages` + audit, prod runner copied from `var/prod-ve-product-151/run.sh` (backup, version guards 1.6.0/4.34.0, seed by slug, films:seed, translate by slug-resolved uids `--fail-on-missing`, in-place swap for 1094/8436, `cache:flush`, verify, health) | copy + memories, screenshots + references, film, lab + prod run log | lint-copy, content:audit 0 errors, translate dry-run 0 missing twice, `--check-picture`, `SeedFilmsCaptionsTest`, pages 200 ×4 languages lab + prod, prod run < 30 min (expected ≈ 5 min) | WP-L | 9 |
| WP-N | Desiderio codemod, component attributes, marker lint (4.35.0) | DES | 2.4 codemod (port tokenizer from `/tmp` into `Build/Scripts`, `--check`, third rule/exception for the Fluid-root loop), six molecules, exception list, `ContentBlockCollectionMarkerTest`, `ComponentStructureTest`, `SeedVisualEditorInlinePageFunctionalTest` extension, version 4.35.0, CHANGELOG | marked templates (≈171 + 13 loops), components, tests | desiderio unit + functional, `codemod --check` exit 0, anonymous render byte-identical, visual QA of 8430 ×4 languages in edit mode, cgl/phpstan | WP-J shipped (1.6.0) | 10 |
| WP-O | On-page row layer: `ve-row-actions` singleton, appender, `CollectionRowsMode` | VEE 1.7.0 | 2.4 "1.7 on-page layer": body-level singleton (chip pattern), appender with `levelLinksPosition`, user setting + enum + `FrontendConfiguration` + `config.js`, per-mode rendering, no wrapper insertion, no Tab interception; XLF `collectionRows.*` | components; `CollectionRowsModeTest`; `collection-rows-component.spec.js` (rail on hover/focus, Escape, add after posts afterUid, appender follows levelLinksPosition and disables at max, unmarked templates get no rail, mirrors outline only) | components, node, phpstan 8, unit, cgl, a11y review | WP-N, WP-G | 14 |
| WP-P | 1.7 server: `copy`, `restore`, bounded cascade preflight | VEE 1.7.0 | 4.6 + section 11 items: `CopyOperation` (paste envelope `-uid`, `update hidden` when createHidden, parent CSV re-registration, leaf-only, `copyUnsupported` on translated parents, TSconfig `duplicate`), `RestoreOperation` (discard of server-found placeholder), `InlineCascadePreflight` (bounds, rights per dependent, counts); labels | classes; functional `duplicate*` ×4, `deletePlaceholdersAreListedAndRestoreDiscardsThem`, `deleteCascade*` ×5, `deleteRespectsEnableCascadingDeleteFalse` | phpstan 8, cgl, functional SQLite + MariaDB | WP-D | 16 |
| WP-Q | 1.7 UI (Duplicate, Undo removal, cascade counts), docs, release 1.7.0 + 4.35.0, runtime scenarios, deploy | VEE + DES + lab | Duplicate in the row menu, "Undo removal" on placeholders, cascade counts in the confirm; docs; CHANGELOG; tags + mirrors; lab lock bump; runtime scenarios (rail, duplicate, restore, nested cascade); deploy; health | release pair, runtime evidence | all gates, CI, docs, deploy health | WP-O, WP-P | 8 |

Totals: Train 1 (WP-A…WP-M) ≈ 124 h; Train 2 (WP-N…WP-Q) ≈ 48 h.

**Parallel schedule (Train 1).** Wave A (parallel): WP-A, WP-J, and WP-F's label half (3.x; the Add-entry half waits for
`collectionTypes` from WP-A). Wave B: WP-B, WP-C (after WP-A); WP-F completion. Wave C: WP-G (after WP-A, WP-B, WP-F),
WP-D (after WP-B), WP-E (after WP-B). Wave D: WP-H, WP-I (after WP-G; WP-I also after WP-C, WP-D). Wave E: WP-K.
Wave F: WP-L. Wave G: WP-M. Critical path WP-A → WP-B → WP-G → WP-I → WP-K → WP-L → WP-M ≈ 70 h. 1.6.0 is shippable
without WP-D's default flip (TSconfig 0) and without WP-C's defaults being decided (both modes implemented; the default
value is one line per key). **Train 2** starts after 1.6.0 ships: WP-N and WP-P in parallel, then WP-O, then WP-Q.
VEE and desiderio work on separate branches/worktrees; the lab is touched only by WP-K, WP-L, WP-M, WP-Q.

## 9. Release and deploy

- **Versions:** webconsulting/visual-editor-enhancements 1.5.1 → **1.6.0** (minor: intents `move`/`sync`, user settings
  `tx_visualeditor_actionLabels` + `tx_visualeditor_entriesLayout`, `f:mark.item`, payload additions, TSconfig keys;
  behaviour changes — `translatedParents`, `deleteTranslations` defaults per D2/D3 — named in the CHANGELOG with their
  "restore 1.5" values; `firstVersion` default 0 unless the D1 evidence commit lands); webconsulting/desiderio 4.33.0 →
  **4.34.0** (copy, screenshots, capture script; no template change). Train 2: **1.7.0** + **4.35.0** (markers, on-page
  layer, copy/restore/cascade).
- **Lab lock:** `lab-single-package-install.md` — never `composer install/update` in the lab while clones are linked;
  `composer update --no-install webconsulting/visual-editor-enhancements webconsulting/desiderio`, `git archive` both tags
  into `vendor/`, `.lab-links` relink hook, `rm -rf /tmp/opcache/*` + `composer dump-autoload`, verify `PackageArtifact.php`
  keeps both ext keys, `ddev exec vendor/bin/typo3 cache:flush`.
- **Push:** GitHub + GitLab mirror for both packages (`Build/Scripts/mirror-to-gitlab.sh`), lab main to GitHub + GitLab;
  `git log origin/main..HEAD` listed as its own step before the push (shared-worktree rule); never chain check + push.
- **Quality + Coolify:** package CI green (incl. the new `components` job), lab Quality pipeline, Coolify auto-deploy on
  main (web + solr per sha, keep the last 3 lab versions, `docker_images_to_keep=6`).
- **Lean prod run (only because page copy changes):** runner copied from `var/prod-ve-product-151/run.sh`: backup, version
  guards, `desiderio:styleguide:seed --pages=features/visual-editor,content-types/visual-editor-inline --allow-production`
  (dry-run first), `sitepackage:films:seed --film=visual-editor --pictures-only --allow-production`,
  `sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=<uids by slug> --allow-production --fail-on-missing`
  (dry-run before and after), in-place screenshot swap for `/target-groups` and `/target-groups/for-typo3-suite-buyers`
  via `refresh-feature-screenshots.php --apply`, `cache:flush`, `verify.php`, `health.sh`; expected ≈ 5 min (1.5.0 one-page
  run: 243 s), hard cap 30 min; no full translate run.
- **Health:** `curl -sI` the four language URLs of pages 2080 and 8158 (prod uid) and `/typo3` → 200 (401 behind basic
  auth = healthy); Coolify healthy; disk free; 3 versions kept.
- Then: update memories `ve-enhancements-repo-and-field-chooser.md` (1.6.0), new `vee-repeatables-1-6.md` (settings,
  TSconfig keys, traps: admin in workspace 4, inlineRecords default 0, fixture.json in the container); log the release in
  section 13.

## 10. Risks

| Risk | Mitigation |
|---|---|
| First-version invariant writes live child tstamp/sorting (Core bookkeeping); Solr queue, refindex consumers or webhooks may react | default `firstVersion = 0`; flip only on D1 + MariaDB evidence; documented caveat; TSconfig escape |
| Parity on connected translated parents lets editors create language-only rows (structural divergence) | badges "Only in this language"/"Not translated here"; `default-only` one key away; sync restores identity |
| Counted translation cascade is irreversible in live (same as FormEngine) | Core modal with count, Cancel default; nested/media still refused |
| Scoped pending rule: a structural write while this element has staged edits leaves the page stale until Save; in a workspace staged datamaps on live child uids are redirected by Core to existing drafts (**assumed**, standard auto-version path) | status text + Save now; runtime scenario "workspace: first add then Save staged title" pins it; `saving`/`uncertain` still block everything |
| Title input and page editable share one store entry; whitespace normalisation could make `setInitialData` values differ | component test "title input and page editable share one store entry"; initial value taken from the payload `labelValue` raw |
| `::after` on the contextual edit trigger host may not render in one engine | component spec decides before caption is default; slotted span fallback for that one button |
| `collectionTypes` index grows with record types (desiderio ≈ 244 CTypes, Astryx more) | field names only, per table, memoized per request; measured in WP-A (< 10 KB expected) |
| Panel stays open on page clicks: z-index with CKEditor balloons (2147483001) and drag overlays | verified in runtime E2E; Escape always closes; z-index unchanged (100002) |
| Three layouts × four label modes multiply surfaces | component specs per variant; screenshots for the owner before release |
| Hover-discovery replacement must not disclose collections the user cannot modify | index lists field names of the CType only; the capability fetch on click decides rights; payload rule unchanged |
| Codemod (1.7) touches ≈160 templates; a mis-tokenised loop could break rendering | deterministic script, `--check`, lint test, anonymous HTML byte-identical gate, visual QA ×4 languages before 4.35.0 |
| Fixed-position rail (1.7) over carousels/transforms misaligns | viewportTracker re-positioning, hide when off-viewport, component spec with transformed containers; appender is container-anchored |
| Owner-side state: admin in workspace 4, preference off → demos silently become drafts | strip shows the workspace from the first open; owner note in section 12 (D11) |
| Fixture.json lives in the container (var/ not synced); DDEV MariaDB port changes | `ve-runtime.sh` copies/deletes it; port read from `ddev describe -j` |
| Translation memories: every changed English sentence needs three entries before `--fail-on-missing` passes | keep "Entries"/"Add entry" wording; `--dry-run --missing=-` lists; append-only |
| Prod data run must stay under 30 minutes | only two (max four) pages reseeded/translated; film picture-only |

## 11. Not possible or deferred (honest list)

| Item | Status | Why |
|---|---|---|
| Unsaved placeholder rows on the page (staged create) | not possible | child markup is the server-rendered template; `ve-editable-text` needs a numeric uid; creation stays an immediate server write (hidden in live) |
| Per-row controls on the page without a template marker | not possible | children have no wrapper; Content Blocks has no hook; inference rejected (no-stunts) |
| On-page rail/appender + desiderio markers | **1.7.0** (WP-N, WP-O) | ViewHelper ships in 1.6.0; the layer is opt-in per user, default `panel` |
| Duplicate an entry | **1.7.0** (WP-P) | no FormEngine control; Core `copy` + parent CSV specified, leaf-only, refused on translated parents |
| Removing entries with nested children or media | **1.7.0** (bounded preflight) | dependent rights, `enableCascadingDelete`, lock scope not yet proven; backend form path |
| Undo removal of a workspace delete placeholder | **1.7.0** (`restore` = Core discard) | listed read-only in 1.6.0; Workspaces module does it meanwhile |
| Undo after a live removal / undo toast instead of confirm | not offered | Core `undelete` is live-only and relation restoration unverified; D1/D5 (confirm always) stand |
| Sync translations inside a workspace | refused `syncWorkspace` | Core writes live translated parent count and child sorting/tstamp (read, not executed) |
| Hiding a default-language entry together with its translations | not done | Core hides per record; hint shown; N staged writes deferred |
| Required media / child type selectors on create (gallery, logo cloud) | backend form | named in the panel with a deep link |
| First workspace versioning on by default in 1.6.0 | TSconfig 0 until D1 + evidence | recorded decision note; dedicated flip commit |
| Suppressing the vendor's native `title` tooltips next to captions | not possible | Lit attribute binding; only our own buttons drop `title` |
| "First tap reveals, second tap acts" on touch | not done | would intercept vendor clicks; labels always visible under `(hover: none)` |
| Label mode "all labels while the bar is hovered" | not offered | pointer-shift loop |
| Core icon identifiers by name in the edit frame | not possible | `ve-icon` knows nine glyphs, `typo3-backend-icon` needs `TYPO3.settings.ajaxUrls.icons`; paths copied into `Shared/icons.js` |
| Drag-and-drop reordering | deferred | Move up/down + Alt+Arrow cover it; `move` already carries `afterUid` |
| Elements with several collections: one-click add from the bar | one extra click | the bar opens the inspector instead (two elements on 8430) |
| Rows without any editable output (7 desiderio loops) | panel only | no page dimming/focus target; badge is the indicator |
| Astryx (164 elements), Innesto (19), webcon_templates | later codemod runs / out of scope | Astryx needs `attributes` on ≈20 `a:` components; webcon_templates has no `f:render.text` |
| Proof that live tstamp changes are harmless for every consumer | cannot be proven | documented caveat + TSconfig switch |
| Core sheet still reloads the whole module on close | Core behaviour | now needed only for media/complex fields |
| Restricted lab editor for manual acceptance (D11 `ve_editor`) | owner work | runtime proof uses the disposable fixture users |

## 12. Decisions for the owner (defaults the workers use if the owner says "recommended")

| # | Question | Recommended default | Alternatives |
|---|---|---|---|
| D1 | First workspace versioning (astra 5): allow Core's stock auto-version under the narrowed invariant (live child sortby renumbered in the same order + tstamp on the affected owned subtree, everything else byte-identical)? **Your "yes" is the written decision the review note requires.** | **Approve A1.** Workers ship `firstVersion = 0` in 1.6.0 code and flip the default to 1 in a dedicated commit only after the eight MariaDB tests + the runtime workspace scenario are green (else the flip is 1.6.1) | keep the refusal (backend fallback stays; "generic for workspaces" not met) |
| D2 | Connected translated parents (astra 6a) | **`translatedParents = parity`** (FormEngine behaviour: translation-only rows, remove/hide/move translated rows; badges) | `default-only` (structure only in the default language + Sync) |
| D3 | Removing a default-language entry with translations (astra 6b) | **`deleteTranslations = confirm`** (Core cascade after a counted Core modal, only when every translated row compiles + delete access) | `refuse` (today; 0 removable on 8430) |
| D4 | Sync default mode and workspace behaviour | **`localize`** default (add-only), `synchronize` as second item with its own confirm; **refused in workspaces** in 1.6 | `synchronize` default; exempt sync from the live check after a MariaDB test |
| D5a | Default label mode | **`caption`** (your words: text only on hover/click) | `inline` for users who enable inline records |
| D5b | Short words on the vendor's four buttons ("Delete" instead of "Delete content element") | **Yes, containment-checked** (en/de get short words; zh/hu see the verbatim name) | verbatim everywhere |
| D5c | Drop `title` on our own bar buttons outside `icons` mode | **Yes** (no duplicate native tooltip; the long sentence stays in docs and in `icons` mode) | keep `title` |
| D6 | Default panel placement | **`inspector`** (right dock) | `tray` (bottom, best for side-by-side language frames), `anchored` |
| D7 | Action word | **keep "Entries", verb "Add entry", hint "New entries start hidden"** (film stays picture-only, ≈45 memory keys survive) | rename to "Rows" (re-voice film, ≈45 memory entries, manual) |
| D8 | Live creation visibility | **`createHidden = 1`** (one-save flow via the staged eye) | `createHidden = 0` per site TSconfig |
| D9 | On-page row layer timing | **1.7.0** (ViewHelper in 1.6.0, rail/appender/codemod in Train 2, default `panel`) | pull WP-N/WP-O into 1.6.0 (+ ≈24 h, template coupling on the critical path) |
| D10 | Duplicate, Undo removal, bounded cascade preflight | **1.7.0** (WP-P) | hard-gate them in 1.6.0 behind TSconfig 0 (+ ≈16 h) |
| D11 | Visibility of the feature for editors and for you | **User TSconfig `setup.default.tx_visualeditor_inlineRecords = 1` for desiderio editor groups; you enable it for be_users 1 and leave workspace 4 before manual acceptance** | leave default 0 everywhere |
| D12 | Restricted lab editor `ve_editor` (D11 of the 2026-11-05 plan, never created) | **Fixture-only** (disposable `ve_editor` group created and removed by `fixture.php`) | you create a persistent group for manual acceptance |
| D13 | Persistent untranslated playground page in desiderio | **No** (disposable fixture page is enough; 8430 proves the translation path) | add `/content-types/visual-editor-inline/live` with a no-translate flag (+ seed, + prod) |
| D14 | CI browser job for the component specs | **Yes** (`components` job, chromium, login-free) | keep local-only |
| D15 | Staged inline title edit in the inspector row | **Yes** (vendor store path, mirrored to the page) | title via Core sheet only |
| D16 | Film | **picture-only refresh** (narration unchanged under D7) | re-voice changed lines (+ ≈2 h, fal.ai credits) |
| D17 | Effort plan | **Train 1 ≈ 124 h, Train 2 ≈ 48 h as in section 8** | drop WP-H (layouts) from 1.6.0 (−8 h) against the design-alternatives rule |

## 13. Release log

(empty — filled by WP-L, WP-M and WP-Q)

## 14. Sources

Local (all read 2026-11-07 in `MAIN` unless stated): `VE` 1.10.4 — `Resources/Public/JavaScript/Frontend/components/ve-content-element.js:17-30,64-69,210-258,265-330,393-466`,
`ve-drag-handle.js` (light DOM, `--auto-no-overlap-padding`), `ve-icon.js` (nine glyphs), `ve-editable-text.js:354-368`,
`ve-drop-zone.js:215`, `ve-context-chip` pattern (ours), `Frontend/intercept-user-actions-guard.js:62`,
`Frontend/initialize-save-handling.js:29`, `Frontend/stores/data-handler-store.js:49-85`,
`Backend/components/ve-backend-save-button.js:56,113`, `Shared/iframe-messaging.js:7` (`doSave`),
`Classes/ViewHelpers/Render/TextViewHelper.php:197,297`, `Classes/ViewHelpers/Mark/ContentAreaViewHelper.php`,
`Classes/Service/ContentElementWrapperService.php:70-99`, `Classes/Middleware/EditModeMiddleware.php:88-97`,
`Resources/Private/Language/locallang.xlf:85-98` + `de.locallang.xlf`.
`VEE` 1.5.1 — `Classes/Service/InlineIntent.php:13-33`, `InlineRecordService.php:80-150,171-200,229-256,298-310,347-430,459-467`,
`InlineGenericSaveGuard.php:40-60`, `EditorRecordContext.php:32,78`, `FrontendConfiguration.php:41-69`,
`FieldChooserConfigurationService.php:236-258`, `Configuration/TCA/Overrides/be_users.php:19-37`, `ext_localconf.php:17`,
`Resources/Public/JavaScript/Frontend/index.js:47-100`, `element-field-actions.js` (whole), `inline-records.js` (whole),
`element-refresh.js:160-214`, `components/ve-field-chooser.js:98-120,628-668`, `components/ve-field-chooser/collections.js`,
`Shared/inline-record-actions.js:1-40`, `Shared/icons.js:16-35`, `Resources/Private/Language/locallang_library.xlf`
(frontend.inlineRecords.* ids), `locallang_setup.xlf` (ids), `Tests/**` inventory, `.github/workflows/ci.yml` (jobs
quality/unit/javascript/functional), `CHANGELOG.md` (1.5.1), `composer.json` (no version key, no `ext_emconf.php`).
`CORE` 14.3.10 — `Classes/DataHandling/DataHandler.php:921-959,3171-3195,3319-3350,3364-3373,5130-5283,5954-5990,6672-6675,7203-7260,9525-9540`
(per the server research, verified there), `Classes/Database/RelationHandler.php:1040-1077`,
`Classes/Domain/Persistence/GreedyDatabaseBackend.php:141-146`, `Resources/Public/Icons/T3Icons/svgs/actions/{actions-list-alternative,
actions-placeholder-add,actions-form-insert-after,actions-caret-up,actions-caret-down,actions-eye-slash,actions-document-synchronize,
actions-document-localize,actions-duplicate,actions-workspace,actions-plus,actions-translate}.svg` (all present).
`BE` — `Classes/Form/FormDataProvider/DatabaseRowInitializeNew.php:220-237`, `Controller/FormInlineAjaxController.php:282-306`,
`Resources/Public/JavaScript/element/{contextual-record-edit-trigger,pseudo-button}.js`.
`CB` 2.4.10 — `Classes/DataProcessing/ContentBlockData.php:56,152,160`, `ContentBlockDataDecorator.php:276-291`,
`Classes/Generator/TcaGenerator.php:440-449,507-533`. `DES` 4.33.0 — `Classes/Data/Showcase/{ShowcaseFeatureDefinitions,
ShowcaseVisualEditorInlinePage,ShowcaseAudiencePages,ShowcaseSuiteCompanionPage}.php`, `Build/Scripts/{capture-visual-editor-entries.mjs,
refresh-feature-screenshots.php}`, `Resources/Private/Components/Molecule/Card/Card.fluid.html:15-25`, `ContentBlocks/ContentElements/*`
(156 collection templates). Lab — `composer.lock` (v1.5.1, v4.33.0), `Tests/Integration/VisualEditor/{fixture.php,runtime.spec.js,README.md}`,
`var/film-ve151/bootstrap.php`, `var/prod-ve-product-151/run.sh`, `.ddev/mutagen/mutagen.yml`, read-only SQL (tt_content on pid 8430,
feature_cards_items 8684-8695, be_users 1 workspace_id 4 + uc, sys_workspace 1/4), `/tmp/ve-row-anatomy/{analyze.js,des.json}`.
Plans — `WT/docs/plans/2026-11-05-visual-editor-inline-records-plan.md` (§3, §10, §11), `2026-11-05-visual-editor-sol-editor-handoff.md`,
`2026-11-05-visual-editor-astra-workspace-review.md` (findings 5, 6), `2026-11-05-visual-editor-review-decisions.md`,
`2026-11-05-visual-editor-release.md`, `2026-11-06-visual-editor-ui-fix.md`. Memories — `prefer-library-standard-mechanisms.md`,
`design-alternatives-as-user-setting.md`, `ve-enhancements-repo-and-field-chooser.md`, `desiderio-shared-collection-tables.md`,
`desiderio-cb-collection-translation.md`, `cross-site-link-language.md`, `finish-with-commit-push-merge-deploy.md`,
`prod-runner-under-30-minutes.md`, `lab-single-package-install.md`, `shared-worktree-push-check.md`.
Research and verdicts of this round (concepts "Rows in Place", "Entries Inspector 1.6", "Quiet Rows"; judges: Core
correctness, editor experience, implementability) — folded in above; upstream check `git ls-remote --tags
https://github.com/FriendsOfTYPO3/visual_editor.git` → 1.10.4 newest.
