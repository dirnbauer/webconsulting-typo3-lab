# Concept — Integrate Flue (AI agents + workflows) into TYPO3

> This is the *as-built* concept: the original design reconciled with what is
> actually committed. Where the implementation diverged from the first design,
> the **As built** note records it.

## Context

The lab already had, in **PHP**, almost every primitive the **Flue framework**
(a TypeScript headless harness for durable AI agents + deterministic workflows,
by the Astro org) provides — just not unified into an Agent/Workflow/Session
runtime with durable execution:

| Flue primitive | Already in the lab (PHP) |
|---|---|
| Agent = LLM + harness | `netresearch/nr-llm` `LlmServiceManager::chat / chatWithTools / streamChat` |
| Agent loop | `netresearch/nr-mcp-agent` `ChatService::runAgentLoop()` (≤20 iters, persists `Conversation`) |
| Tools / MCP servers | `hn/typo3-mcp-server` `ToolRegistry` + `ToolInterface` (50+ tools, HTTP `/mcp`, OAuth **and PAT**) |
| Skills | `webconsulting/skillflow` `tx_skillflow_skill` + `SkillExecutionService` + `SkillRunnerInterface` |
| Secrets | `netresearch/nr-vault` `VaultService::retrieve()` (envelope-encrypted, audited) |
| Durable run record | `skillflow` `tx_skillflow_run` (audit only) |
| Headless UI substrate | visual-editor Lit components, a2ui playground, `Modules.php` + importmaps |

**Strategy (decided with the user): hybrid built around a bridge to *real* Flue.**
Run the actual Flue runtime as a Node sidecar; TYPO3 is the **control plane +
tool/skill/secret provider**. We do *not* reimplement the hard parts (durable
execution, sandboxes, the agent loop) — Flue already consumes MCP servers + skills,
and `typo3-mcp-server` already *is* an MCP server. `webconsulting/flue` defines/
triggers flows, injects page context, exports skills, stores durable runs, and
streams results into the backend. Clean seam to add PHP-native bits later.

## Architecture

```
 Editor (backend module web_flue)
        │  POST flue_trigger (page uid + per-run instructions)
        ▼
 webconsulting/flue (PHP control plane)
   ├─ ContextResolver   {uid}{table}{title}{pid}{workspace} → payload + instructions
   ├─ SkillExporter     skillflow skills → /skills/<id>/SKILL.md  (shared volume)
   ├─ VaultService      LLM key (per-request header, never on disk)
   ├─ OAuthService      mint typo3-mcp PAT for a flue-bridge BE user
   ├─ FlueClient        POST /workflows/<wf>  +  SSE event stream
   └─ RunStore          tx_flue_run (append-only events, CAS, resumable)
        │  HTTP (intra-DDEV: http://<sitename>-flue:3000)
        ▼
 Flue Node sidecar (.ddev/docker-compose.flue.yaml, node:22-bookworm)
   └─ packages/flue-bridge/  (@flue/runtime; agents/page-report.ts)
        │  connectMcpServer('typo3', { url: http://web/mcp, headers:{ Authorization: Bearer <PAT> } })
        ▼
 hn/typo3-mcp-server  /mcp  (Bearer PAT)  → reads TYPO3 (read-only allowlist for MVP)
```

The PHP side calls the sidecar over the internal Docker network; the Flue agent
calls back into TYPO3 through `/mcp` using a PAT. Every secret stays in PHP memory
and crosses as a per-request header.

## Part 1 — skillflow `{uid}` / per-run context feature  ✅ shipped

Independently shippable; `ContextResolver` is reused by flue.

- `Webconsulting\Skillflow\Service\ContextResolver`: closed token whitelist
  (`{uid}{table}{pid}{title}{workspace}`) applied via a single `strtr()` pass — no
  eval, no double-resolution.
- Insertion point `SkillExecutionService::runSkillOnRecord(..., string $instructions = '')`
  (not `PromptBuilder`): the only place holding `$table/$recordUid/$workspaceId` **and**
  the loaded `$skill`, so both runners get substitution for free. Substitutes into the
  skill body + prepends a `## Per-run instructions` block to the content.
- UI/DB: instructions `<textarea>` on the run form; `tx_skillflow_run.instructions`
  column + read-only TCA + run-detail display.

## Part 2 — webconsulting/flue extension (PHP control plane)  ✅ built

`packages/flue/` (symlinked like `a2ui_integration`). Models a2ui (module/JS/soft-deps),
skillflow (run table/TCA/commands), nr-mcp-agent (durable append-only + CAS).

- **DB:** `tx_flue_flow` (identifier, title, workflow_name, default_agent, default_model,
  skills→`tx_skillflow_skill`, mcp_tools, input_schema, instructions) and `tx_flue_run`
  (flow, be_user, run_key, flue_run_id, target_table/uid, workspace_uid, instructions,
  status, payload, **events** (append-only `{seq,ts,type,data}`), output, **usage_json**,
  error_message, started, finished; keys on (flow,status)/(be_user,status,deleted)/(run_key,status)).
- **Services:**
  - `FlueClientInterface` / `FlueClient` — **As built:** `trigger(string $workflowName,
    array $payload, array $headers = []): array{runId,status}`; `streamEvents(string $runId,
    callable $onEvent, array $headers = []): void` (reads `text/event-stream`, splits on
    `\n\n`); `resume(...)`. Uses `RequestFactory` (base URL from `ExtensionConfiguration`).
  - `FlowTriggerService::trigger(int $flowUid, string $table, int $uid, int $ws, string
    $instructions, int $beUser): array` — gate (`EnvironmentGuard`) → resolve context →
    export skills → retrieve LLM key (nr-vault) → mint PAT (typo3-mcp) → build payload →
    `FlueClient::trigger` → `RunStore::create`. Plus `drainRun()` (synchronous stream→store).
  - `ContextResolver` (mirrors Part 1), `RunStore` (create/appendEvent/markRunning(CAS)/
    markSettled/markFailed/findResumable/load — CAS via `UPDATE … WHERE status = expected`).
  - **As built:** `SkillExporter` lives **in `packages/flue`** and reads the skillflow
    tables directly (soft-coupled via `isset($GLOBALS['TCA']['tx_skillflow_skill'])`),
    copying `ClaudeCliRunner::materializeSkill()`. Export happens **at trigger time**
    inside `FlowTriggerService` — there is no separate `flue:export-skills` command and no
    `FlueSkillExporter` in skillflow (the original design moved).
- **Backend module `web_flue`** (parent `web`, `workspaces=*`): `list` (flows + recent
  runs + run form), `run` (no-JS synchronous fallback), `show` (run detail).
  AjaxRoutes: `flue_trigger` (POST→JSON), `flue_stream` (GET→SSE, each event
  `RunStore::appendEvent` + echoed `data: …\n\n`), `flue_resume`.
- **As built — JS:** `flue-run.js` is **plain ES** (fetch trigger → `EventSource` →
  append; close on settled). The Lit `<flue-run-stream>` component from the first design
  was *not* built — the live log is plain DOM.
- **CLI:** `flue:run <flowUid> <pageUid>` (`RunFlowCommand`).
- **DI/config:** `Services.yaml` (autowire, bind interface, public controllers +
  `FlowTriggerService`). `ext_conf_template.txt`: `sidecarBaseUrl`, `defaultModel`,
  `requestTimeout`, `apiKeyVaultId` (default **`flue_anthropic_api_key`**),
  `requireLocalEnvironment=1`. Soft deps (suggest + `class_exists` guards): skillflow,
  nr-llm, nr-vault, typo3-mcp-server — installs standalone.

## Part 3 — Flue Node sidecar + the bridge seams  🟡 authored (not run)

`.ddev/docker-compose.flue.yaml` (copies the working `docker-compose.typo3-solr.yaml`
pattern): `node:22-bookworm`, hostname `${DDEV_SITENAME}-flue`, env
`TYPO3_MCP_URL=http://web/mcp`, `HTTP_EXPOSE=3000`, mounts `../packages/flue-bridge:/app`
+ a shared `flue-skills` volume. **As built:** the command is
`(test -d node_modules || npm install) && npm run dev` — the project is scaffolded by
`npx flue init` (generates `app.ts`/`db.ts`) and run with `flue dev`; there is **no
hand-written `server.mjs`** adapter.

- **(a) MCP auth — PAT in headers (not static_bearer).** `/mcp` validates
  `Authorization: Bearer <token>` via `OAuthService::validateToken()`.
  `OAuthService::createDirectAccessToken(int $beUserId, string $clientName)` mints a PAT
  for a `flue-bridge` BE user. The agent calls
  `connectMcpServer('typo3', { url:'http://web/mcp', transport:'streamable-http',
  headers:{ Authorization:'Bearer <PAT>' } })` → adapted tools `mcp__typo3__<Tool>`. **No
  vault `static_bearer` credential is needed** (the first design's indirection was dropped).
- **(b) Secrets — per-request header.** `VaultService::retrieve('flue_anthropic_api_key')`
  decrypts in PHP memory at trigger time; passed to the sidecar as header
  `X-Flue-Anthropic-Key` (not body, env, or `output`). Operator stores the key once
  (`vault:store flue_anthropic_api_key`); the assistant never handles raw keys.
- **(c) Skill export.** `SkillExporter` writes `skills/<identifier>/SKILL.md` (+ attachments,
  `..`-guard) into the shared `flue-skills` volume the sidecar reads at `/app/skills`.
- **(d) Trigger / stream / persist.** PHP `POST /workflows/page-report` with the resolved
  payload; the run is mirrored into `tx_flue_run`; the module's `EventSource` proxies the
  SSE and appends each event to the `events` column (dropped socket replays by `seq`);
  terminal event → `markSettled`/`markFailed` + output.

## MVP vertical slice — `page-report`

Read-only TYPO3 page reporter (`agents/page-report.ts`, model `anthropic/claude-sonnet-4-6`,
allowlist `GetPage/ReadTable/RenderRecord/GetPageTree`). Operator: store the key, run the
sidecar, create a `tx_flue_flow` (workflow_name `page-report`), trigger from `web_flue` →
agent reads the page via `/mcp`, applies the skill, streams an `agent.message` report →
durable run settles in `tx_flue_run`.

## Verified Flue API (npm pack, no execution)

`@flue/runtime` 1.0.0-beta.1 (repo `withastro/flue`), ESM-only, Node ≥ 22.19, HTTP via
**Hono**, MCP via `@modelcontextprotocol/sdk`. Differs from the marketing blog:
- **No `createWorkflow`** — agents are `agents/<name>.ts` → `export default createAgent(ctx
  => ({ model, instructions, tools, skills, sandbox }))`. Runs via `dispatch` + `observe` +
  `getRun`/`listRuns`. HTTP = `flue(): Hono` (`@flue/runtime/routing`), scaffolded by
  `npx flue init --target node`.
- **MCP:** `connectMcpServer({ url, transport, headers })` → `{ tools, close() }`.
- **Durable store built in:** `@flue/runtime/node` `sqlite(path)`; sandbox `local()`.

## Implementation status

- **Part 1 (`{uid}`)** — shipped to skillflow `main`, PHPStan max, verified end-to-end.
- **Part 2 (control plane)** — built + committed (`packages/flue`, 35 files, PHPStan max),
  registration intentionally not in the lab composer yet.
- **Part 3 (sidecar)** — `package.json` / `probe-mcp.mjs` / `agents/page-report.ts` /
  `README.md` + the DDEV service authored; `app.ts`/`db.ts` are `flue init`-generated.

**Two external gates (operator-owned):** (1) installing/running the Flue beta executes
third-party code (the assistant is blocked from running it) — `npm run probe:mcp` proves
the MCP bridge with no LLM key; (2) the LLM API key in nr-vault.

## Roadmap

- **Phase 0** — `{uid}` feature ✅
- **Phase 1** — Flue bridge MVP (control plane built; sidecar authored; activation gated)
- **Phase 2** — visual flow editor (Lit graph over `tx_flue_flow`)
- **Phase 3** — channels / subagents / resume UI (child runs by `run_key`, `RunStore::findResumable()` + `FlueClient::resume()`)

## Risks & mitigations

- **Flue API drift** — keep the sidecar a thin `flue init` project; pin versions; the
  durable-event→`tx_flue_run` mapping is in one place (`FlowTriggerService::drainRun`).
- **DDEV `localUnsafeMode`** (MCP tools default to live workspace) — read-only tool
  allowlist for the MVP; `options.mcpServer.strictSandbox=1` TSconfig for write flows.
- **SSE through PHP-FPM** — the persisted `events` column is the source of truth; streaming
  is an optimization (poll fallback by `seq`).
- **Run-row concurrency** — CAS (`UPDATE … WHERE status = expected`) + append-by-`seq`.
- **Token-substitution scope** — closed 5-token whitelist via `strtr`; never an evaluator.
