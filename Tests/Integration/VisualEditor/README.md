# Regular-runtime Visual Editor integration

Opt-in, serial, local DDEV checks of Visual Editor Enhancements against real
Core, FormEngine, MariaDB, backend sessions, Core modals and rendered
templates. Nothing is mocked. Only disposable fixtures are touched; no existing
user's password, preferences or workspace is used or changed.

Everything runs through `Build/Scripts/ve-runtime.sh` (from MAIN or any lab
worktree; it finds the DDEV project by name):

```sh
Build/Scripts/ve-runtime.sh install feature/1.7-integration   # candidate into vendor/ (git archive)
Build/Scripts/ve-runtime.sh fixture                           # page, users, sessions, workspace
Build/Scripts/ve-runtime.sh run [--grep …]                    # Playwright, MAIN's node_modules
Build/Scripts/ve-runtime.sh cleanup                           # remove everything, print the proof
Build/Scripts/ve-runtime.sh restore                           # release from composer.lock back into vendor/
```

`install` refuses while the package is lab-linked (`lab-link.sh`); it
archives a ref of `packages/visual_editor_enhancements` into vendor/, then
runs `ddev mutagen sync`, clears `/tmp/opcache`, dumps the autoloader, checks
`PackageArtifact.php` and flushes the caches. Always `restore` afterwards.

## Fixture (`fixture.php`)

The wrapper copies the helper into the web container as `/tmp/ve-runtime.php`
and runs its modes `fixture`, `extra`, `v17`, `visible`, `localize`, `ve_editor`
and `session <role>`. State lives in the container's `var/ve-integration/` (var/
is not Mutagen-synced) and is copied to MAIN's same ignored directory (0600).

- `fixture [run]`: a page below showcase 8430 (`firstVersion = 0` in its page
  TSconfig), a broad editor group with the page as mount, a disposable admin
  (English) and editor (German), a disposable workspace.
- `extra`: checklist (three entries), legal links, nested data table, hero,
  a never-versioned checklist for the first-version case and a checklist that
  `localize` translates (Core localize of the page and the element into
  language 1).
- `v17`: checklists for the on-page rail, Duplicate, Undo removal and the
  default `firstVersion` (never versioned), and a product comparison whose
  first product has two nested specs and a reference to a shared image
  (`sharedFile`; the cleanup proof checks `sharedFileKept`).
- `ve_editor`: the restricted editor (plan D12): `web_edit` only, mount = the
  fixture page, `tables_modify` tt_content + the fixture's child tables except
  legal links, the tested CTypes, the inline fields and every child table's
  `hidden` right except `tabs_items:hidden`, member of the disposable workspace.
- `session <admin|editor|veEditor>`: a fixated backend session written as a
  Playwright storageState. Users get a random password that is discarded at
  once; nothing secret is stored besides these session files.
- `settings <role> <chooser> <workspace> [labels] [layout] [scheme] [collectionRows]`,
  `firstversion <0|1|default>` (`default`: no line, the extension's default),
  `cascade <maxRecords|default>`, `version [recordKey]` (Core drafts of one
  checklist and its entries) are used by the spec.

`cleanup` discards the run's drafts, deletes the page (and its translation),
workspace, groups and users, removes the users' rows and sessions outright and
prints `remainingActivePageOwnedRows` (must be `{}`), `remainingDrafts`,
`disposableUsers`, `disposableGroups`, `sessions` (all 0) and the deletion
flags; then both state copies are removed and the lab health is checked.

## Scenarios (`runtime.spec.js`)

Action-bar labels (caption on hover/focus/click with a constant bar width,
1.4.13 hoverable, survival of the vendor hide-toggle re-render; inline,
stacked, icons), Add entry from first render (hidden, focus in the new
editable, eye + title staged, Save now), add after / move / Alt+Arrow with the
page order and a stale move, counted remove with Cancel / Escape / dismiss and
the translation cascade, Sync translations and a translation-only entry in the
German frame, the three panel layouts and the dark backend shadow, the
restricted editor (eye refused without the hidden right, unmodifiable child
table not disclosed, 403 outside the mount), workspace drafts for all three
roles with byte-identical live rows, `firstVersion` refused under 0 and
created under 1 with only sorting/tstamp live deltas, staged parent text, and
the 1.5 cases (FormEngine sheet, nested settings, German editor, showcase
8430 payload - elements found by CType at run time, library persistence).

1.7: the on-page row toolbar (`rows`) anchored to the hovered and the focused
entry on showcase 8430 (read-only), move down from the toolbar with the page
re-rendered in the new order and Escape back to the entry; `appender` adds
after the last entry without a toolbar, `panel` shows neither; Duplicate of a
leaf entry in live (hidden copy right after the source, SQL sorting) and
`copyUnsupported` for an entry with nested members; workspace remove -> listed
placeholder -> Undo removal back in place, live rows byte-identical,
`restoreLive` in live; the counted nested cascade (specs + image reference
deleted, `sys_file` kept) and the `cascade.maxRecords` refusal; the first add
in a workspace under the default `firstVersion` (only sorting/tstamp live
deltas). The payload timing budget of the 40-entry team grid is 1750 ms
(1.7.0's per-entry cascade preflight; A/B in the spec). axe-core (MAIN's `node_modules`, WCAG 2.0
/ 2.1 / 2.2 A and AA) runs on the hovered bar, the inspector (light and dark),
the restricted bar, the Core modal, the row toolbar and the add buttons; every state must have zero violations.
The run prints an `EVIDENCE` line (axe results, SQL deltas).

Elements are scrolled to 300 px below the frame top before hovering:
desiderio's sticky site header otherwise covers the action bar of tall
elements. After every operation the spec waits for the inspector to settle
(it re-renders asynchronously; a click fired earlier can hit the row above).
