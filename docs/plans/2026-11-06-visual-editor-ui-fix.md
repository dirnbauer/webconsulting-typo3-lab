# Visual Editor settings and entries — 2026-11-06

Visual Editor Enhancements **v1.5.1** (`0375d7c`) fixes the broken settings
button and replaces the oversized collection popover. The release and its
regression-first test commit (`79b0d5e`) are on GitHub and GitLab.

## Cause and behavior

The settings action imported the chooser through Core's versioned import map,
while the collection action used a relative URL. Browsers loaded two module
instances; the second custom-element registration failed. Both actions and
the shared options cache now use canonical import-map specifiers. Component
tests reproduce Core's explicit versioned imports, rather than just its prefix
fallback, so they detect this failure.

Field settings stay beside the element's pencil and keep an anchored panel.
A labelled list-and-add Entries action follows it, only on elements with
collections. It opens a compact panel at the right of the preview. Entry
titles open Core's contextual form; the overflow menu contains quick settings,
insertion and removal. Hidden badges, one add button, short collection
shortcuts, stable Back navigation and accurate save hints replace the raw
button list and contradictory footer.

## Validation

- Browser components: **16 passed**, including the real toolbar's
  settings → entries → settings sequence with versioned imports and staged
  field changes.
- JavaScript unit checks: **52 passed**.
- PHP unit checks: **75 tests / 140 assertions**.
- PHP functional checks: **50 tests / 260 assertions**; two existing vendor
  deprecations. PHPStan and code style pass.
- Regular DDEV runtime: all **8 cases passed**. The full run passed seven;
  the final form case passed after the test waited for Core's visible save
  acknowledgement before closing. It had previously clicked Close while the
  old, dirty form was still navigating, although the database write had finished.
- Real forms preserve hidden=1. German restricted editing, nested records,
  pending text, existing workspace drafts, live-row preservation and normal
  library persistence are covered. Browser inspection confirmed the compact
  dark panel and containment in a narrower preview.
- Disposable fixture page 8435 was cleaned up with no remaining active
  page-owned records. Both generated users were disabled/deleted and their
  sessions removed. No showcase content was edited.

The lab lock changes only `webconsulting/visual-editor-enhancements`, from
v1.5.0 to v1.5.1. The regular runtime uses the release archive; unrelated
development links are retained. Main's Quality workflow performs the code
deployment after its checks pass. Existing workspace and cascade restrictions
remain documented in the extension's Collections guide.
