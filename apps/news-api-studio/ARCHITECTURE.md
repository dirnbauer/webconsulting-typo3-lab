# News API Studio — Architecture

A long-form companion to [`README.md`](./README.md). This document explains *how* the v0.2.0 redesign is wired together: module boundaries, data flow, Electron security model, build pipeline, and the decisions-log so future maintenance has the context.

The audience is a developer joining the codebase cold, or someone re-evaluating one of the v1 trade-offs.

---

## 1. What changed in v0.2.0

The previous iteration was a **single 988-line `page.tsx`** that did everything: connect, list, edit, render fields, browse files, manage status. v0.2.0 replaces that with:

- A **modular component tree** (`src/components/{header,records,editor,files,settings,onboarding}`) plus pure-function utilities (`src/lib/*`) and stateful hooks (`src/hooks/*`).
- **A real two-column layout** — records left, editor right — with files and global state moved into transient surfaces (sheet drawers, header dropdowns, toasts).
- **Light/Dark/System theming** via `next-themes`, mirrored to Electron `nativeTheme.themeSource` so the macOS title bar follows.
- **Multi-profile credentials**, with tokens encrypted by `safeStorage` (OS keychain) inside Electron and a localStorage fallback in the browser. The hardcoded fallback token that was sitting in `page.tsx:128` has been removed.
- **A polished Electron shell** — sandboxed renderer, preload-bridged IPC, native application menu, single-instance lock, remembered window bounds, generated `.icns`, universal mac binary, hardened runtime.
- **Safety / correctness fixes** — delete confirmation, dirty-switch guard, draft-restore banner, AbortController on `apiFetch`, top-level Error Boundary, validated relation searchUrls.

---

## 2. Source tree

```
apps/news-api-studio/
├── electron/
│   ├── main.cjs               # main process: window, security, IPC, native menu
│   ├── menu.cjs               # menu template + dispatch to renderer
│   └── preload.cjs            # contextBridge surface (window.studioBridge)
├── build/
│   ├── icon.icns              # macOS app icon (generated from icon.png)
│   ├── icon.ico               # Windows installer icon
│   ├── icon.png               # 512x512 source
│   └── icon.svg               # source vector
├── src/
│   ├── app/
│   │   ├── globals.css        # Tailwind v4 layer + Linear-clean light/dark tokens
│   │   ├── layout.tsx         # ThemeProvider + ErrorBoundary + Toaster + TooltipProvider
│   │   └── page.tsx           # thin orchestrator — wires hooks + components
│   ├── components/
│   │   ├── ui/                # shadcn primitives (alert, button, card, sheet, dialog…)
│   │   ├── header/            # AppHeader, ProfileSwitcher, WorkspaceSwitcher,
│   │   │                      # ConnectionIndicator, ThemeToggle
│   │   ├── records/           # RecordsPanel
│   │   ├── editor/            # EditorPanel, FieldEditor, RichTextEditor
│   │   ├── files/             # FileBrowserSheet
│   │   ├── settings/          # SettingsSheet, ProfileForm
│   │   ├── onboarding/        # FirstRun
│   │   ├── theme-provider.tsx # next-themes wrapper + Electron native theme bridge
│   │   └── error-boundary.tsx # top-level React error boundary
│   ├── hooks/
│   │   ├── use-api.ts                # apiFetch with abort + auth + tenant + workspace
│   │   ├── use-keyboard-shortcuts.ts # global ⌘S/⌘N/⌘,/⌘K/Esc handler
│   │   └── use-resizable-column.ts   # records column width persistence
│   └── lib/
│       ├── electron-bridge.ts   # safe wrapper over window.studioBridge
│       ├── format.ts            # unwrap, toArray, displayDateTime, fromDateTime, assetUrl
│       ├── normalize.ts         # TYPO3 value coercion + relation URL validator
│       ├── profiles.ts          # profile CRUD via safeStorage / localStorage
│       ├── types.ts             # ApiEnvelope, FieldSchema, NewsRecord, Profile, …
│       └── utils.ts             # cn() (shadcn)
├── next.config.ts               # output: "export" — produces static `out/` for Electron
├── package.json                 # scripts + electron-builder config
├── README.md                    # short docs
└── ARCHITECTURE.md              # this file
```

### Why this shape

- **`app/page.tsx` is a thin orchestrator** (~470 lines, mostly handler wiring). All visual sub-trees live in their own files, so the React tree reads top-down: header, records, editor, sheets, dialogs.
- **`hooks/`** is for stateful logic with React lifecycle. `lib/` is for pure functions (no `useState`, no JSX). This split makes everything in `lib/` trivially unit-testable when tests land.
- **`components/ui/`** is reserved for shadcn-style primitives (style + a11y wrappers around Radix). Anything app-specific lives one folder deeper, named by domain.

---

## 3. Data flow

```
┌────────────────────────────────────────────────────────────────────┐
│                           page.tsx                                 │
│  state: profile, workspace, records, schema, selected, dirty,      │
│         dirtyFields, files, search, filter, …                      │
│                                                                    │
│  useApi(profile, workspace) ──► apiFetch with AbortController      │
│  useKeyboardShortcuts() ──► save / new / settings / search / esc   │
│  useResizableColumn() ──► left column drag handle                  │
│                                                                    │
│  on profile change → connect() → me, schema, files, records        │
│  on workspace change → reload schema/files/records                 │
│  on render error → ErrorBoundary catches                           │
└────────────────────────────────────────────────────────────────────┘
       │                              │                          │
       ▼                              ▼                          ▼
   AppHeader                    RecordsPanel                EditorPanel
   - profile/workspace         - search + pills            - tabs (TCA)
     dropdowns                 - skeleton + empty          - dirty pill
   - connection dot            - workspace overlay         - draft banner
   - theme toggle              - load more                 - Save/Preview/
   - settings gear                                           Submit/Publish/
                                                             Delete/Discard
                                                          - FieldEditor[]
       │                                                       │
       ▼                                                       ▼
   SettingsSheet                                       FieldEditor
   - profile CRUD                                      - input/textarea
   - safeStorage                                       - RichTextEditor (TipTap)
   - delete confirm                                    - relation search
                                                       - file field → opens
                                                         FileBrowserSheet

   Sonner toasts ◄──── all save/publish/submit/delete/error feedback
   AlertDialog   ◄──── delete confirm + discard-on-switch confirm
```

### Save flow

1. User edits a field → `updateField(name, value)` → `selected[name] = value`, `dirtyFields.add(name)`, `dirty = true`.
2. The dirty effect persists `{ profileId, dirtyFields, record }` to `localStorage` under `typo3-news-api-studio:draft`.
3. User presses `⌘S` (or clicks Save). `saveRecord()` builds a payload of *only the dirty fields* (or all default values if creating), sends `POST /news` or `PATCH /news/{uid}`, then refetches the saved record and reloads the list.
4. On success, the draft is cleared and a sonner toast shows. On failure, dirty state is preserved and a toast shows the API error.

### Workspace + profile switching

- **Profile switch** (`handleSelectProfile`): aborts any in-flight requests, clears records/schema/selected/me, persists active id, then a `useEffect` keyed on `activeProfileId` re-runs `connect()`.
- **Workspace switch**: mutates `workspace`, then a `useEffect` keyed on `workspace` re-runs `reloadAfterWorkspaceChange()`. Only the schema, files, and records reload — the profile/me state stays.

### Why an explicit dirty model (no autosave)

TYPO3 DataHandler is the authoritative writer. Autosave would mean spurious revisions in workspaces and partial publishes. The model matches the TYPO3 backend's expectations: the user explicitly Saves, and the studio mirrors that exactly.

---

## 4. Theming

### Tokens

`globals.css` defines two CSS-variable palettes (`:root` and `.dark`), wired into Tailwind v4 via `@theme inline { --color-foo: var(--foo) }`. The Linear-clean palette is intentionally narrow:

| Token              | Light          | Dark            |
| ------------------ | -------------- | --------------- |
| background         | `#fafafa`      | `#0a0a0a`       |
| card               | `#ffffff`      | `#0f0f0f`       |
| border             | `#e5e7eb`      | `#262626`       |
| muted              | `#f4f4f5`      | `#1a1a1a`       |
| muted-foreground   | `#71717a`      | `#a1a1aa`       |
| primary            | `#1b7a95`      | `#27a5c4`       |
| destructive        | `#dc2626`      | `#f87171`       |

The primary teal is brightened in dark mode (`#27a5c4`) so the AA contrast ratio against `#0a0a0a` is comfortably above 4.5:1.

### Mode resolution

`next-themes` writes `class="dark"` (or removes it) on `<html>` based on user preference + system preference. We start with `defaultTheme="system"` and `enableSystem`. The `<NativeThemeBridge>` component inside the provider mirrors the chosen mode into Electron via `studioBridge.setNativeTheme()` so the native macOS title bar follows.

`html` also gets `color-scheme: light` / `dark` so form controls (date pickers, native dropdowns, scroll bars) match.

---

## 5. Electron architecture

```
                ┌──────────────── Main Process ────────────────┐
                │ electron/main.cjs                            │
                │  • single-instance lock                      │
                │  • createWindow (hiddenInset on macOS)       │
                │  • IPC handlers (theme / safeStorage / open) │
                │  • native menu (menu.cjs)                    │
                │  • window state load/persist                 │
                │  • will-navigate guard                       │
                └────────────┬─────────────────────────────────┘
                             │
                  contextBridge (preload.cjs)
                  exposes window.studioBridge
                             │
                ┌────────────▼─────────────────────────────────┐
                │              Renderer Process                │
                │  out/index.html (Next.js static export)      │
                │  contextIsolation: true · sandbox: true      │
                │  nodeIntegration: false                      │
                │  webSecurity: false (see § 5.4)              │
                └──────────────────────────────────────────────┘
```

### 5.1 Preload bridge

Whitelisted via `contextBridge.exposeInMainWorld("studioBridge", …)`:

| Method                       | Purpose                                                  |
| ---------------------------- | -------------------------------------------------------- |
| `setNativeTheme(theme)`      | Set `nativeTheme.themeSource` so window chrome follows.  |
| `encryptString(plaintext)`   | `safeStorage.encryptString` → base64.                    |
| `decryptString(ciphertext)`  | `safeStorage.decryptString`.                             |
| `isEncryptionAvailable()`    | Check OS keychain availability.                          |
| `openExternal(url)`          | Validated `shell.openExternal` (https only).             |
| `onMenuAction(handler)`      | Subscribe to `studio:menu-action` IPC events.            |
| `appVersion`, `platform`     | Static metadata.                                         |

`src/lib/electron-bridge.ts` is the renderer-side wrapper. When the bridge is missing (browser), each method has a sensible fallback: `setNativeTheme` is a no-op, `encryptString` returns plaintext, `openExternal` calls `window.open`. This means **the same renderer code runs in browser and Electron unchanged.**

### 5.2 Token storage

`src/lib/profiles.ts` implements profile CRUD. Tokens are persisted with an `enc:v1:` prefix when encryption is available; without the prefix they're treated as plaintext. The active profile id is a separate localStorage key. Because `safeStorage` is keyed to the user's OS account, tokens are **per-machine, per-user** — they don't roam.

### 5.3 Native menu

`electron/menu.cjs` builds a standard App / File / Edit / View / Window / Help menu and dispatches custom actions (`save` / `new` / `settings` / `search`) to the focused web contents via `webContents.send("studio:menu-action", action)`. The renderer subscribes via `studioBridge.onMenuAction(handler)` and routes them to the same handlers used by `useKeyboardShortcuts`. This means `⌘S` works whether focus is in an `<input>` or on the title bar.

### 5.4 Why `webSecurity: false` (still)

`sg_apicore` is hosted on a different origin than the packaged Electron app (`file://` or `app://` vs your TYPO3 host). Disabling `webSecurity` avoids CORS preflight + same-origin-policy blocks on the cross-origin API calls the renderer makes.

This is **the single biggest open hardening item.** The deferred plan (see § 8) is:

1. Register a custom `app://` protocol in main, serve `out/` through it.
2. Add `Access-Control-Allow-Origin: app://news-api-studio` to the `sg_apicore` response headers (allowlisted).
3. Re-enable `webSecurity: true` and remove the cross-origin asterisk.

We kept `contextIsolation: true`, `sandbox: true`, `nodeIntegration: false` even with `webSecurity: false`, so renderer code can't reach Node APIs even if it loads a malicious external resource.

### 5.5 Window state

Bounds (x, y, width, height) are written to `userData/window-state.json` on `close`/`resized`/`moved`. On launch, those bounds are restored if valid; otherwise `1280 × 860` defaults.

### 5.6 Single-instance lock

`app.requestSingleInstanceLock()` ensures double-clicking the dock/Start-menu icon focuses the existing window instead of spawning a duplicate.

---

## 6. Build pipeline

```
  src/                          electron/                build/
   │                              │                        │
   └── npx next build             │                        │
       output: "export"           │                        │
   ┌──── out/ (HTML/CSS/JS) ──────┘                        │
   │                                                       │
   └──── npx electron-builder --mac --universal ───────────┘
                       │
                       ▼
              dist/
              ├── mac-universal/TYPO3 News API Studio.app
              ├── TYPO3 News API Studio-0.2.0-universal.dmg
              └── TYPO3 News API Studio-0.2.0-universal-mac.zip
```

### 6.1 What goes into the app bundle

```jsonc
"files": [
  "electron/**/*",   // main + preload + menu
  "out/**/*",        // static renderer
  "package.json",
  "!node_modules/**" // ← important
]
```

`node_modules/` is **excluded entirely**. Why it works:

- The renderer is a static-exported HTML/JS bundle in `out/`. Once Next has built it, no Node modules are needed at runtime.
- The main process (`electron/main.cjs`) only `require()`s Electron itself and Node built-ins (`fs`, `path`).

This avoids the architecture-mismatch failures we hit during the universal merge — `sharp` and `@next/swc-darwin-*` are *build-time* tools (only used by `next build`), not runtime deps. Excluding `node_modules/` gives a smaller bundle, a faster build, and a clean universal merge.

### 6.2 Mac universal

```
"mac": {
  "target": [
    { "target": "dmg", "arch": ["universal"] },
    { "target": "zip", "arch": ["universal"] }
  ],
  "icon": "build/icon.icns",
  "darkModeSupport": true,
  "hardenedRuntime": true,
  "gatekeeperAssess": false,
  "identity": null  ← unsigned for dev; pass --config.mac.identity=<id> for signed
}
```

Universal binary verified with `file Contents/MacOS/TYPO3\ News\ API\ Studio` →
`Mach-O universal binary with 2 architectures: [x86_64] [arm64]`.

### 6.3 Windows x64

NSIS installer with custom artifact name, desktop + start-menu shortcuts, no auto-elevation. `build/icon.ico` already exists.

### 6.4 Generating `build/icon.icns`

```sh
ICONSET=$(mktemp -d)/icon.iconset && mkdir -p "$ICONSET"
for size in 16 32 64 128 256 512; do
  sips -z $size $size build/icon.png --out "$ICONSET/icon_${size}x${size}.png"
  doubled=$((size * 2))
  [ $doubled -le 1024 ] && sips -z $doubled $doubled build/icon.png --out "$ICONSET/icon_${size}x${size}@2x.png"
done
sips -z 1024 1024 build/icon.png --out "$ICONSET/icon_512x512@2x.png"
iconutil -c icns "$ICONSET" -o build/icon.icns
```

Re-run if you replace `build/icon.png`. macOS-only tooling; for Linux/Windows hosts use `png2icns` or run on a Mac CI runner.

---

## 7. Decisions log

### Two columns, not three

The previous layout was `320 / 1fr / 360` with workspace+files always visible. Verdict: most of the time, files are accessed *in context* of a file field. Workspace metadata is glanceable from the header. So the global panel was always-visible clutter. v0.2.0:

- Files → opened on demand from each file field via `FileBrowserSheet`.
- Workspace + connection meta → header dropdown + status dot popover.
- Submit/Publish/Delete → editor action bar (where the record lives).
- Status string → `sonner` toasts.

Result: focus stays on records ↔ editor, with everything else one click away when needed.

### `next-themes` over a custom hook

`next-themes` ships with built-in flash-of-incorrect-theme protection (a tiny inline script in `<head>`), `system` mode wired to `prefers-color-scheme`, and a `resolvedTheme` for downstream components. Rolling our own would re-implement all three; the lib is ~1KB. Easy call.

### Sheet drawer over a `/settings` route

`output: "export"` makes routing more painful (every route is a static HTML file), and the credentials section is a *transient* concern, not a destination. A right-side `Sheet` (Radix Dialog) is the right primitive: keyboard-accessible, focus-trapped, stops the world while you edit.

### Per-machine keychain over cloud sync

Tokens are sensitive. `safeStorage` ties them to the OS user keychain — they can't be exported to backups, can't be read by another user on the same machine. We accept that tokens don't roam between machines: that's a feature for a credential, not a bug.

### Removing the hardcoded fallback token

The previous `page.tsx` had `const localToken = "REDACTED-LEAKED-TOKEN-ROTATED-2026-05-08"` baked in. That's a real-shaped 64-char hex BE token. Even if it was a dummy, baking secrets into committed source teaches the wrong reflex. Removed entirely; first-run onboarding now requires the user to enter one. **The token must be rotated, and git history scrubbed with `git filter-repo` (separate task — see § 8).**

### `safeStorage`'s base64 envelope + `enc:v1:` prefix

The prefix is a versioning hook. If we ever migrate to a different cipher, decryption can dispatch on prefix and the existing `enc:v1:` blobs keep working until they're rewritten on next save.

### Why we kept TipTap

Switching the rich-text editor mid-redesign would balloon scope. TipTap 3 is fine, well-documented, and the existing field schema doesn't need anything fancier. Toolbar reduced to bold / italic / H2 / list to match the Linear-clean density.

---

## 8. Deferred (post-v1) — explicit follow-ups

These are real backlog items, not aspirations. Each can be picked up independently.

| # | Item                                       | Why deferred                                                        |
| - | ------------------------------------------ | ------------------------------------------------------------------- |
| 1 | **Rotate leaked TYPO3 BE token**           | High priority. Strip from history with `git filter-repo` *and* rotate the token in TYPO3 backend; otherwise every `git clone` still has it. |
| 2 | **Code signing + notarization**            | Needs Apple Developer ID Application cert + an app-specific password for `notarytool`; Windows needs a `.pfx` + password. Without these, users see "damaged" / SmartScreen warnings; with `--config.mac.identity=null` we ship unsigned for now. |
| 3 | **Auto-updater**                           | `electron-updater` + a release host (GitHub Releases easiest). Also needs CI publishing the `latest-mac.yml` / `latest.yml` artifacts. |
| 4 | **Tests**                                  | `vitest` + RTL on `lib/normalize.ts`, `lib/format.ts`, `lib/profiles.ts`, plus a Playwright happy-path smoke against a running DDEV stack. |
| 5 | **Server-side records search/filter**      | We do client-side filter on a 50-record window today. Server-side requires confirming `sg_apicore`'s actual query semantics (`?q=` vs `?filter[title]=`). |
| 6 | **Remove `webSecurity: false`**            | Custom `app://` protocol for the renderer + add `Access-Control-Allow-Origin: app://news-api-studio` to the `sg_apicore` response headers. |
| 7 | **Windows arm64 + Linux build targets**    | Add to `build.win.target[*].arch` once there's demand. Linux: AppImage or deb.                                                       |
| 8 | **i18n (German UI)**                       | Add `next-intl` with a toggle in settings. The current English-only choice is documented in the grill log but hasn't been implemented. |

---

## 9. Quick reference — common tasks

### Point the app at this DDEV demo

Create a profile with base URL
`https://ddev-demo-setup-visual-editor.ddev.site` and API id `news`. Leave the
tenant empty for the shared local setup unless a TYPO3 API endpoint explicitly
requires an `X-Tenant-ID` header.

### Add a new shadcn primitive

The project uses the `radix-ui` umbrella package (re-exports of all the individual `@radix-ui/react-*` packages). Add the wrapper file under `src/components/ui/<name>.tsx` and use `data-slot` + `cn()` for class-merging. See [`sheet.tsx`](src/components/ui/sheet.tsx) and [`alert-dialog.tsx`](src/components/ui/alert-dialog.tsx) for the pattern.

### Add a new connection profile field

1. Extend `Profile` in [`src/lib/types.ts`](src/lib/types.ts).
2. Add the form field in [`src/components/settings/profile-form.tsx`](src/components/settings/profile-form.tsx).
3. Persist in [`src/lib/profiles.ts`](src/lib/profiles.ts) (already shape-agnostic).
4. Use in [`src/hooks/use-api.ts`](src/hooks/use-api.ts) when constructing requests.

### Add a new keyboard shortcut

Wire the handler in [`src/app/page.tsx`](src/app/page.tsx)'s `useKeyboardShortcuts({...})` call, then mirror it in [`electron/menu.cjs`](electron/menu.cjs) so it works through the native menu too. The renderer subscribes to menu actions in `useEffect` via `onMenuAction`.

### Add a new TYPO3 API call

Use `apiFetch` from `useApi`. It handles base URL, tenant header, bearer token, workspace param, abort signal, and envelope unwrapping. Errors throw and should be caught at the call site (and sent to `toast.error(...)`).

### Update icons

Replace `build/icon.png` (512×512), then re-run the iconutil snippet from § 6.4 to regenerate `build/icon.icns`. Replace `build/icon.ico` for Windows (use [icoconvert.com](https://icoconvert.com) or `magick`).

---

## 10. Performance notes

- **Records list filter is client-side** on the loaded window (max 50 + load-more increments). Filtering 200+ records this way is still imperceptible; the bottleneck is the network round-trip, not the filter.
- **`AbortController` on `apiFetch`** prevents the classic React data-fetch race: switching profile/workspace mid-request no longer overwrites fresh state with stale.
- **`next-themes` with `disableTransitionOnChange`** avoids the awkward color-fade when toggling modes; transitions resume on the next frame.
- **Sheet/Dialog content is portaled** so it doesn't compete for stacking context with the resizable column.

---

## 11. Where to look first when something breaks

| Symptom                                                  | Look here                                          |
| -------------------------------------------------------- | -------------------------------------------------- |
| Connection fails, no toast                               | `useApi.fetch` in [`src/hooks/use-api.ts`](src/hooks/use-api.ts) — auth headers, abort, envelope unwrap |
| Theme doesn't follow OS                                  | `<ThemeProvider>` in [`src/components/theme-provider.tsx`](src/components/theme-provider.tsx) + `setNativeTheme` IPC in `main.cjs` |
| Token disappears between sessions                        | [`src/lib/profiles.ts`](src/lib/profiles.ts) + `safeStorage` IPC handlers in [`electron/main.cjs`](electron/main.cjs) |
| Title bar doesn't show traffic lights at the right place | `trafficLightPosition` + `titleBarStyle` in `main.cjs`; header padding in `AppHeader` |
| `⌘S` works in Electron menu but not from input focus     | `useKeyboardShortcuts` skips when target is editable; check the `editingShortcut` guard |
| Dirty marker stuck on after save                         | `saveRecord` in `page.tsx` — confirm it clears `dirtyFields` and removes the draft from localStorage |
| Universal mac build fails on native binary               | Add the path to `files` exclusion array in `package.json`; almost always a build-time-only native module |
