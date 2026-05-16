# News API Studio Spec

## Goal

Build a shared Next.js and Electron editorial workstation for TYPO3 EXT:news. The app edits news records through sg_apicore, uses shadcn/ui and Tiptap, respects TYPO3 backend-user permissions, supports FAL file workflows, and makes workspace-based publishing easy.

## Product Decisions

- The Next.js web app and Electron app share one UI codebase.
- Electron adds only thin desktop enhancements: native menu, Finder drag/drop upload, native file picker, local draft recovery, installer packaging, and a future auto-update path.
- The first version is online-only with local unsaved-change recovery.
- UI language follows TYPO3/backend-user settings.
- Visual design uses webconsulting branding: restrained enterprise UI, Hanken Grotesk, primary `#1b7a95`, accent `#66c4e1`, strong neutral surfaces, and the webconsulting logo.
- AI image generation is used for visual mockup/moodboard only. The shipped app is implemented with real shadcn/ui, Tailwind, and Tiptap components.

## Authentication And Token Ownership

- API tokens are personal backend-user tokens.
- Extend sg_apicore's existing `tx_apicore_token` table with `be_user_uid`.
- Keep `tx_apicore_token.user_id` as the existing frontend-user binding to `fe_users`; do not overload it for backend users.
- Add TCA for `be_user_uid` as a `be_users` relation and include it in token management views.
- Keep existing frontend-user token behavior separate from backend-user ownership.
- Editors can create, regenerate, and revoke their own app token.
- Admins can create, regenerate, and revoke tokens for any backend user.
- Every token records backend user, creator, label, scopes, tenant/site, expiry, last-used timestamp, and revoked state.
- Token plaintext is shown only once on create/regenerate; only the hash is stored.
- The API resolves the bearer token to a TYPO3 backend user and runs record writes, file operations, workspace actions, and permission checks as that user.
- Authorization requires both token scope and TYPO3 backend-user permission. The token defines the API capability boundary; the backend user defines page, record, file, workspace, and publish permissions.
- A powerful backend user can still receive a narrow app token, and that token must not exceed its configured scopes.
- Token scopes are coarse API scopes such as `news:read`, `news:write`, `files:read`, `files:write`, `workspace:read`, and `workspace:publish`.
- Fine-grained field and record access comes from TYPO3 permissions, TCA `exclude`, page permissions, filemounts, and workspace permissions instead of duplicated per-field token scopes.
- The app shows the current backend user and workspace context after token validation.

## News Editing

- The news edit form is generated from the effective TYPO3 TCA for `tx_news_domain_model_news`.
- Only fields the current backend user may read/write are exposed.
- Extension-added fields appear automatically.
- Known EXT:news fields get curated controls; unknown fields use generic TCA-type renderers.
- TCA tabs and palettes inform grouping, but the app may curate layout for usability.
- The API needs a form schema endpoint, not only CRUD records.
- The TCA form schema is cached client-side and invalidated by a server-provided schema hash.
- The schema hash must account for effective TCA, installed extensions, backend-user permissions, site/language, workspace context, and relevant TSconfig/RTE settings.
- The schema endpoint exposes a normalized FormEngine-like schema, not raw TCA.
- The normalized schema preserves tabs, palettes, display conditions, render types, validation, default values, localized labels, relation endpoints, RTE config, file constraints, and field-level permissions.

## Rich Text

- Use Tiptap for rich text editing.
- Preserve TYPO3 RTE behavior instead of saving arbitrary raw HTML.
- Load/save must respect TYPO3 RTE processing, allowed tags, link handling, file links, paste cleanup, and site-specific transformations.
- The API should expose enough editor configuration for the app to configure Tiptap consistently.

## Relations

- Relation fields use searchable record pickers generated from TCA relation config.
- Results respect backend-user permissions, page access, storage PIDs, language/workspace context, and allowed tables.
- Known relation fields get tailored UI:
  - categories: tree or grouped picker
  - tags: searchable multi-select with create-if-allowed
  - related news: searchable news picker
  - media: FAL picker
  - content elements: page/record-aware picker
- UID inputs are developer/debug fallback only.
- V1 allows tag creation from the app when the backend user has permission.
- V1 only selects existing categories. Category creation is deferred because categories are structural taxonomy.

## FAL And File Handling

- The app includes a full TYPO3 FAL browser/uploader.
- File access is enforced through the token's backend user.
- Users can only see/write folders allowed by their TYPO3 filemounts and permissions.
- Upload, replace, rename, metadata edit, image selection, and news media attachment go through TYPO3 FAL APIs.
- News image relations use proper `sys_file_reference` handling via DataHandler/FAL.
- V1 FAL operations are non-destructive: browse, upload, metadata edit, rename when TYPO3 allows it, attach, and detach from news.
- V1 does not physically delete or replace files. Destructive file operations require a later dependency-aware UX and stronger safeguards.
- Uploads use the user's last selected allowed folder, falling back to a configured default such as `fileadmin/news/` when the backend user has access.
- The UI always shows the target folder before upload and lets the user change it through the FAL browser.
- Per-reference crop editing remains available for images attached to a news record by editing `sys_file_reference.crop`.
- A full-featured image editor is planned after v1.
- Later image editor scope: crop, rotate, flip, resize, focal point, alt/title/description metadata, basic adjustments, and format/export quality.
- Image editor saves create a new edited FAL asset by default.
- Overwrite/replace is an advanced action only when the backend user has permission and the UI can show dependency warnings.

## Workspaces And Publishing

- Workspace editing is the default when the backend user has workspace access.
- Live editing is available only when the user has permission and the product explicitly exposes that choice.
- The UI shows workspace state: live/workspace name, draft state, publish permission, and related changes.
- Saving news in a workspace creates or updates TYPO3 workspace versions through DataHandler.
- Publishing uses TYPO3 workspace APIs, not raw database changes.
- Main actions are `Save draft`, `Preview`, `Submit for review`, and `Publish this news`.
- Preview requests a real TYPO3 frontend preview URL from the API and opens it in the system browser.
- TYPO3 owns routing, language, site config, workspace preview, preview tokens, and frontend rendering.
- Embedded preview is deferred.
- If no frontend preview URL can be resolved, the app shows `Preview unavailable` with diagnostics such as missing detail page, route enhancer, storage PID, site config, or access rights.
- V1 does not invent a fake backend-style preview.
- Publishing is record-focused first, with dependency awareness for media references, translations, categories/MM relations, and related workspace versions.
- Full workspace administration remains in TYPO3 backend.

## Implementation Phases

1. Backend-user token binding and authenticated context.
2. TCA schema endpoint and dynamic field renderer.
3. Tiptap editor with TYPO3 RTE transformation support.
4. FAL browser/uploader with filemount permissions.
5. Workspace editing, preview, submit, and publish actions.
6. webconsulting-branded shadcn UI redesign.
7. Electron desktop enhancements and installer polish.
8. Full-featured image editor.
