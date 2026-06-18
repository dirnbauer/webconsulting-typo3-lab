# Flue × TYPO3 — What We Can Do, and the Plan

> Produced 2026-06-18 by a multi-agent analysis (6 capability explorers reading
> Flue's shipped docs + the lab code, then synthesis + an adversarial critic).
> The load-bearing technical claims below were **verified against the lab**:
> the three editorial skills exist (`skills/{content-qa,tone-of-voice,seo-optimizer}/SKILL.md`),
> the skill-discovery path mismatch is real (Flue discovers `<cwd>/.agents/skills/`,
> the volume mounts to `/app/skills`), `ContentAuditTool` exists in typo3-mcp-server,
> `LocalModeService::enforcesStrictSandbox()` works as described, and `RunStore::markSettled`
> already accepts an unfed `usage_json`.

The `page-report` flow already proves the whole vertical seam: TYPO3 is the control
plane (durable, append-only `tx_flue_run` with CAS status), the real `@flue/runtime`
sidecar is the engine, and the read-only `typo3-mcp-server` allowlist + per-request
nr-vault key injection keep it safe. Everything below layers on that proven core — and
the honest baseline for "effort" is *"how far is this from cloning page-report?"*

---

## 1. What Flue lets us do

### Theme A — Editorial QA & content health (read-only, highest ROI)
No writes, just findings — the safest, fastest-to-ship class. Builds on
`agents/page-report.ts` + the three editorial skills already on disk.
- **Per-page QA gate against the style guide** — mount content-qa + tone-of-voice into page-report; return `{verdict: READY|NEEDS_WORK|BLOCKER, findings[]}`. *Turns "looks fine" into a structured editorial sign-off.*
- **Site-wide audit via subagent fan-out** — a coordinator enumerates a subtree (`GetPageTree`), delegates each page to a read-only `page-auditor` via `session.task(..., {result})`, rolls up N schema-validated `{pageUid, issues[], score}` with a live progress bar.
- **Deterministic content-health scan** — a `site-audit` workflow calling the shipped `ContentAudit` tool, then layering seo-optimizer on the worst offenders.
- **Broken-link & redirect hygiene sweep (propose-only v1)** — tree walk + one `defineTool` HTTP-HEAD checker; *propose* `sys_redirect` fixes (create path behind a flag).

### Theme B — Draft-authoring through TYPO3 Workspaces (HITL = the workspace)
The core unlock from read-only to **safe write**: the agent never touches live — it
stages a draft via `WriteTable`/`BulkWrite` into a non-live workspace, and the editor
reviews the native version diff and publishes. The human gate is TYPO3 Workspaces
itself; no new approval UI. `PublishWorkspace` stays a human click, never agent-callable.
- **Workspace-staged translation drafting** — `/agents/translate-page/<uid>` drafts a language into a draft workspace (MCP ships the `typo3-translate-page` procedure).
- **News/blog teaser + meta refresh** — cheap `claude-haiku-4-5` over an article folder; feeds Solr snippets.
- **Accessibility content pass with alt-text drafting** — `ContentAudit` flags `missing_alt_text`, a vision model drafts `sys_file_reference.alternative` into a workspace.
- **Continuing "page editor" assistant** — `/agents/page-editor/<uid>` an editor chats with; accepted changes stage to their workspace. *The natural surface for the visual-editor / a2ui Lit playground.*

### Theme C — Skills as a reusable capability library
The lab is ~90% wired: `tx_flue_flow.skills` multiselect, `SkillExporter` materializes
`SKILL.md` dirs, `FlowTriggerService` resolves `{uid}{title}{workspace}` tokens. **One
real blocker:** exported skills mount at `/app/skills` but Flue discovers them under
`<cwd>/.agents/skills/` — so today no exported skill is loaded. Fix that one path and
editors **compose** flows from version-controlled skills instead of one monolith prompt.

> **Important:** a SKILL's `allowed-tools` frontmatter is **accepted but not enforced**
> by Flue. Read-only safety comes *only* from the agent's tool filter.

### Theme D — Channels: bring the CMS into the tools editors already live in
Flue's verified inbound webhook boundary (`@flue/slack`, `@flue/resend`, `@flue/zendesk`,
`@flue/linear`, `@flue/notion`, `@flue/teams`) lets work originate *outside* TYPO3.
Outbound is just the provider SDK with nr-vault credentials.
- **Slack `/page-report <uid>`** — slash command fast-acks, POSTs the workflow, posts the report in-thread.
- **Outbound publish notifications & weekly content-health digest** — a PSR-14 publish listener / Scheduler loop pushes to Slack or Resend email.
- **Slack approval gate for staged changes (HITL)** — Approve/Reject buttons call back into a TYPO3 admission endpoint that publishes the workspace version. **No model on the publish path.**
- **Inbound email/Zendesk correction → workspace draft** — a reader's correction becomes a reviewed draft edit, with provenance.

### Theme E — Durable, supervised, multi-step pipelines
**The hard prerequisite is `db.ts`** — the sidecar runs in-memory SQLite today, so every
"survives restart" claim is false until a durable adapter + persistent volume land.
- **Multi-step pipeline that survives interruption** — research → draft → self-review (reviewer subagent), resuming from the last durable checkpoint.
- **Live event tailing** — replace `drainRun`'s `?meta` poll with SSE tailing real `tool`/`message_end`/`run_end` events, persisted by `eventIndex`.
- **Resumable runs after sidecar restart** — `db.ts` (sqlite→Postgres) + a TYPO3-owned recovery sweep over `RunStore::findResumable()`.

### Theme F — Control-plane UX: a TYPO3-native low-code AI workbench
- **Multi-flow library + seed-able templates** — page-report, seo-audit, translation-QA, accessibility-check as cloneable `tx_flue_flow` rows.
- **Run-history / cost dashboard** — filter `RunStore::recent()`; fill the empty `usage_json` with per-run token/cost.
- **Visual flow editor over `tx_flue_flow`** — a Lit canvas (modeled on `a2ui-playground.js` + visual-editor). *Constrained to what one row + one workflow file can run — Flue is one-agent-per-workflow, not a generic DAG engine.*

---

## 2. The plan (phased roadmap)

**Phase 0 — Per-run context — DONE.** `ContextResolver` closed-whitelist `strtr` of `{uid}{table}{pid}{title}{workspace}`.

**Phase 1 — Proven vertical seam — DONE & PROVEN.** Read-only `page-report` workflow + agent, vault key injection via `registerProvider`, durable `tx_flue_run`, `flue:run` CLI + Web→Flue module, poll-to-settlement.

*Each phase below depends only on phases above it.*

### Phase 2 — Skills library + read-only QA fleet — ✅ DONE (2026-06-18)
*Turn one hardcoded flow into a skill-driven QA platform — zero new write surface.*

> **Delivered:** skill-discovery path fixed (volume → `/app/.agents/skills`, agent `sandbox: local()`, volume chmod); structured QA via a shared valibot `QA_RESULT` schema rendered as a findings table (`lib/qa.ts`); the `site-audit` flow over `ContentAudit` (whole-subtree); all 3 editorial skills mounted + applied in one pass; runs now carry a queryable `verdict` + `result_json` + `usage_json` (token cost). Gotcha: `flue dev` hot-reload is unreliable for workflow edits — restart the flue container.
- **Fix the skill-discovery path first** — re-point the flue-skills volume from `/app/skills` to `<cwd>/.agents/skills/` in `docker-compose.flue.yaml` (+ `SkillExporter`). One line; unblocks all skill use-cases. **Verify with one real discovered skill before building on it.**
- **Adopt structured results** — switch page-report to a valibot `result:` schema so the module renders a findings table.
- **Mount the three editorial skills**; ship `qa-page` + `site-audit` (calling `ContentAudit`) as `tx_flue_flow` rows. Probe each new MCP tool name on the running sidecar before relying on it.
- **Author the first 3 reusable SKILLs** as `tx_skillflow_skill` rows.
- **Stamp provenance** — persist `payload.skills` + resolved tokens into `tx_flue_run`.

*Unlocks A + C. Effort: S → M.*

### Phase 3 — Read-only subagent fan-out — ✅ DONE (2026-06-18)
*Generalize the single-page flow into supervised whole-section audits — still zero writes.*

> **Delivered:** `tree-auditor` coordinator agent builds a `page_auditor` subagent (`defineAgentProfile`, per-run MCP read-only tools); `tree-audit` flow enumerates a subtree (GetPageTree) and fans out one `session.task('page_auditor', …, {result: QA_RESULT})` per page, then rolls up worst-verdict + mean-score into one structured result + per-page table (`lib/qa.ts` `rollupAudits`/`renderTreeAudit`). Capped at `MAX_PAGES=8` with a truncation note; drain timeout raised to 600s (an 8-page fan-out runs ~9 min — CLI/Scheduler, not the browser). Verified on page 99's subtree (8 pages → BLOCKER, mean 24/100). Storage reuses the Phase-2 verdict/result_json/usage_json path.
- **Coordinator + `page-auditor` profile** — `defineAgentProfile` reusing page-report's read-only config; `session.task(..., {result})` over a subtree returns N validated audits with progress events.
- **Drive from CLI/Scheduler, not the browser** — fan-out runs are long; let the module *tail*, never block PHP-FPM.

*Depends on Phase 2's result schema. Works on the current in-memory sidecar (one supervised run); only restart-survival needs Phase 5. Effort: M.*

### Phase 4 — Safe writes via Workspaces (the core unlock) — ✅ DONE (prototype, 2026-06-18)
*Agent becomes a draft-author; the editor publishes.*

> **Delivered:** the first write surface, proven draft-only. Scoping decision (per the operator): keep DDEV unrestricted globally, **scope the sandbox to a dedicated `_flue` backend user** (admin, TSconfig `options.mcpServer.strictSandbox = 1`). New ext config `agentBackendUser` makes the control plane mint the MCP PAT as `_flue` (separate from the editor who reads the vault key), so every Flue write is forced into a draft workspace via that user's strict-sandbox. New `page-editor` agent (read + single-record `WriteTable` + `WorkspaceReview`; no publish/rollback/bulk/delete) + `page-edit` flow apply ONE requested change. **Verified safe:** editing junk page 660 left the LIVE record untouched (`title='test'`, `t3ver_wsid=0`) and staged the change in the Staging draft workspace (`t3ver_oid=660`, new title) — verdict `STAGED`. HITL = the native Workspaces module. ⚠️ **Safety depends entirely on the agent acting as a strict-sandbox user** — never set `agentBackendUser` to an unsandboxed user. Hardening TODO: non-admin scoped `_flue` group + a control-plane guard that refuses write flows when the agent user isn't sandboxed.
- **Flip the strict-sandbox gate FIRST** — set `features['mcpServer.strictSandbox']=true` for the Flue MCP user so writes are forced draft-only even on DDEV (where `isLocalMode()` otherwise enables live writes). **Do this before any write tool exists.** *Effort: S — one config line.*
- **`page-edit` write workflow** — expose `WriteTable`/`BulkWrite`/`AttachImage`, each wrapped in a `defineTool` that **pins `workspace_id`** to a fresh draft (the model must never select workspace 0). Surface `WriteTable`'s destructive inline-relation semantics in the diff. Return the `WorkspaceReview` diff.
- **HITL = the native Workspaces module** — `run_end` = "draft ready for review."
- **Ship two write flows** — workspace-staged translation + news teaser/meta refresh.

*Depends on Phases 2–3. Unlocks B. Effort: S (gate) + M (flows).*

### Phase 5 — Durable execution + live run UX
- **Add `db.ts`** (`sqlite('./data/flue.db')`, later `@flue/postgres`) + a persistent volume — the prerequisite for every durability claim.
- **Rewrite the SSE frame parser, then tail live** — point `FlueClient::streamEvents` at `GET /runs/:id?live=sse&offset=<last>`. **Hidden cost:** `parseFrame` assumes one JSON object per frame, but Durable-Streams sends a JSON *array* per `event:data` frame plus `event:control` + heartbeats — it must be rewritten or events drop silently.
- **Cost/latency ledger** — `observe()` summing turn-leaf usage into `usage_json`.
- **Recovery sweep** — a Scheduler task over `RunStore::findResumable()`.

*Depends on Phases 3–4. Unlocks E. Effort: M + M (don't under-budget the parser).*

### Phase 6 — Control-plane UX: the AI workbench
- **Multi-flow library + "New flow from template"** clone over `FlowRepository`.
- **Run-history dashboard** — usage/cost from Phase 5.
- **Multi-model routing via nr-llm** — `default_model` picker + `thinkingLevel`; `registerProvider` baseUrl to the nr-llm gateway. Keys stay in nr-vault + per-request header.
- **Visual flow editor** — Lit canvas; **constrain to what one `tx_flue_flow` row + one workflow file can serialize** (one agent per workflow).

*Depends on Phase 5. Unlocks F. Effort: M + L (visual editor).*

### Phase 7 — Channels & production deploy
- **Outbound first** — Slack `/page-report` + digest; then the Slack approval gate; then inbound email/Zendesk → draft.
- **Provenance rule** — channel work calls back into a thin **authenticated TYPO3 admission** (gets a `tx_flue_run` row, reuses vault/PAT/EnvironmentGuard); the sidecar never writes to TYPO3 unmediated. Each handler **claims the provider delivery id** before effects (channels don't dedup; a retry would double-draft). Respect tight ack windows (Slack/Linear ~5s).
- **Production sidecar** — multi-stage `node:22-slim` (`flue build --target node`), `@flue/postgres`, `app.ts` `requireUser` gating **both** `/workflows/*` and `/runs/*`, a `/health` route, runtime-injected secrets.

*Depends on Phases 4–5. Unlocks D. Effort: M each.*

---

## 3. Quick wins (< 1 day each)
1. **Mount the three editorial skills** into page-report (after the Phase-2 discovery-path fix).
2. **Add a valibot `result:` schema** to page-report → `{verdict, score, findings[]}` renders a real table.
3. **Clone `site-audit.ts`** calling `ContentAudit` over a root page (probe the tool name first).
4. **"New flow from template"** clone action in `FlueModuleController::listAction`.
5. **Populate `usage_json`** — thread token/cost from the run record into `markSettled`.
6. **Add a `/health` route + plain `app.ts`** to flue-bridge (Flue ships none).

> #1 is gated on the Phase-2 one-line volume remount; the rest are shippable today.

---

## 4. Recommendation

**Do Phase 2 first — fix the skill-discovery path, add structured results, mount the
three editorial skills — then immediately flip the Phase 4 strict-sandbox gate.**

- **Highest value per unit of effort.** The lab already ships the skills, the exporter, the token resolver, the multiselect. Phase 2 is mostly a one-line remount plus `tx_skillflow_skill` records — it turns one hardcoded demo into a composable editorial QA platform with **zero new write surface**.
- **The one real blocker is small and known** — the `/app/skills` vs `<cwd>/.agents/skills/` mismatch means exported skills are written but never loaded. Fixing it (and verifying with one discovered skill) de-risks every downstream skill-driven flow.
- **The strict-sandbox flip is the cheapest, highest-leverage safety move** — under DDEV `isLocalMode()` returns `true`, which turns *on* live writes; today the only guard is the 5-tool read-only filter. Setting `mcpServer.strictSandbox=true` makes draft-only writes server-enforced, so a prompt-injected agent still can't touch live. Do it before any write flow exists.
