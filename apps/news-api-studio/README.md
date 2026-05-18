# TYPO3 News API Studio

Native-feeling desktop client for editing TYPO3 EXT:news records through `sg_apicore`. Built with Next.js + Electron, distributed as a macOS universal app and a Windows x64 installer.

![Studio screenshot placeholder — capture from running app](./build/icon.png)

## Highlights

- **Two-column workspace** — records list on the left, TCA-driven form editor on the right. The records column is drag-resizable (persisted).
- **Light · Dark · System** themes — `next-themes` driven; mirrored to Electron's native title bar via IPC so window chrome matches the app.
- **Multiple connection profiles** — switch between Live / Staging / Local TYPO3 instances from the header dropdown. Tokens stored encrypted via the OS keychain (`safeStorage`) on Mac/Windows.
- **First-run onboarding** — no profile? a centered card walks you through one before the rest of the UI appears.
- **Safety nets** — confirmation dialog before delete, "discard unsaved changes?" prompt when switching records, draft restored from local storage with an explicit dismiss.
- **Toast feedback** — `sonner` toasts for save / publish / submit / delete / errors. No more in-band status string.
- **Native menu + shortcuts** — `⌘S` save · `⌘N` new · `⌘,` settings · `⌘K` focus search · `Esc` close drawer (Ctrl on Windows).
- **Single-instance lock + remembered window state** — double-clicking the dock icon focuses the existing window; the app reopens at the size and position you left it.

## Quick start

```sh
cd apps/news-api-studio
npm install

# 1. Browser-only dev (Next.js at http://localhost:3000)
npm run dev

# 2. Full Electron dev (Next dev server + Electron window)
npm run electron:dev

# 3. Production builds
npm run electron:pack:mac   # macOS universal .dmg + .zip into dist/
npm run electron:pack:win   # Windows x64 .exe (NSIS installer) into dist/
npm run electron:pack:all   # both

# 4. Static-only build (the renderer)
npm run build               # next build → out/
```

The first launch shows the onboarding card. Enter:

- **Profile name** (e.g. "Local DDEV")
- **TYPO3 base URL** (e.g. `https://ddev-demo-setup-visual-editor.ddev.site`)
- **API id** (typically `news`)
- **Tenant** (optional `X-Tenant-ID` header, e.g. `camino`)
- **Personal BE-user token** — generated in TYPO3 backend module *Tools › User Settings › Access tokens*

The token is encrypted with the OS keychain when running in Electron. Manage profiles afterwards from the gear icon (⌘,).

## Local demo target

Use the repository's DDEV host as the default local profile:

| Field | Value |
| --- | --- |
| TYPO3 base URL | `https://ddev-demo-setup-visual-editor.ddev.site` |
| API id | `news` |
| Tenant | leave empty unless a site-specific tenant header is required |
| Backend | `https://ddev-demo-setup-visual-editor.ddev.site/typo3/` |

The app talks to the TYPO3 API layer, so Composer package refreshes in the root
project can change available fields, workspaces, and records without requiring
an app rebuild.

## Documentation

- This README: getting started, build commands, scope of v1.
- [`ARCHITECTURE.md`](./ARCHITECTURE.md): full architecture reference — module layout, data flow, Electron security model, deferred items, decisions log.

## Keyboard shortcuts

| Action               | macOS  | Windows / Linux |
| -------------------- | ------ | --------------- |
| Save record          | `⌘S`   | `Ctrl+S`        |
| New record           | `⌘N`   | `Ctrl+N`        |
| Open settings        | `⌘,`   | `Ctrl+,`        |
| Focus records search | `⌘K`   | `Ctrl+K`        |
| Close drawer/sheet   | `Esc`  | `Esc`           |
| Toggle DevTools      | `⌥⌘I`  | `Ctrl+Shift+I`  |
| Reload renderer      | `⌘R`   | `Ctrl+R`        |

## Requirements

- Node.js 20+ (Next 16 / React 19 / Tailwind v4).
- macOS 12+ for runtime; Xcode CLI tools (provides `iconutil`/`sips`) only if you regenerate `build/icon.icns`.
- Windows 10+ for the NSIS installer.

## Tech stack

| Layer                | Tooling                                                                |
| -------------------- | ---------------------------------------------------------------------- |
| Renderer             | Next.js 16 (`output: "export"`) · React 19 · Tailwind v4 · shadcn/radix |
| State / theming      | `next-themes`, plain React hooks, `sonner` toasts                       |
| Rich text            | TipTap 3                                                                |
| Desktop shell        | Electron 39 (sandboxed renderer · contextIsolation · safeStorage IPC)   |
| Packaging            | electron-builder 26 — universal mac `.dmg`/`.zip` + windows NSIS        |

## Project status

v0.2.0 — see `ARCHITECTURE.md` § "Deferred (post-v1)" for the explicit follow-up list (code signing / notarization, auto-updater, tests, custom `app://` protocol, etc.).
