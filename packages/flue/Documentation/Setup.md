# Setup & run — webconsulting/flue

Operational runbook to get a flow running end-to-end. For the design see
[Concept.md](Concept.md).

## Prerequisites

- **DDEV** (the lab's environment). Triggering is gated to local Development by
  default (`requireLocalEnvironment = 1`).
- **Node ≥ 22.19** for the Flue sidecar — provided by the `node:22-bookworm`
  service in `.ddev/docker-compose.flue.yaml`; no host Node needed.
- Optional but recommended: **skillflow** (skills), **hn/typo3-mcp-server**
  (the TYPO3 tools the agent reads through), **nr-vault** (the LLM key store).
  flue installs and the module loads without them (soft dependencies).

## 1. Install the extension

```bash
ddev composer require webconsulting/flue:@dev
ddev exec vendor/bin/typo3 extension:setup
ddev exec vendor/bin/typo3 cache:flush     # REQUIRED — rebuilds the backend route
                                           # registry so the flue_trigger/flue_stream
                                           # AJAX routes resolve (else the module throws
                                           # RouteNotFoundException "flue_trigger").
```

This symlinks `packages/flue` into `vendor/`, creates `tx_flue_flow` +
`tx_flue_run`, and registers the **Web → Flue** backend module and the
`flue:run` CLI command. Verify:

```bash
ddev mysql -N -e "SHOW TABLES LIKE 'tx_flue_%';"      # tx_flue_flow, tx_flue_run
ddev exec vendor/bin/typo3 list | grep flue:run        # the CLI command
```

## 2. Store the LLM API key in nr-vault

The agent calls an LLM; the control plane reads the key from nr-vault by
identifier and injects it **per request** to the sidecar as a header — never on
disk, in the run row, or the container env.

Vault identifiers allow only **letters, numbers, underscores** (start with a
letter), so use **`flue_anthropic_api_key`** (the default `apiKeyVaultId`):

```bash
ddev exec vendor/bin/typo3 vault:store flue_anthropic_api_key   # hidden prompt; paste the key
ddev exec vendor/bin/typo3 vault:list                           # confirm
```

Or via the backend module **Tools → Vault → Secrets → create**. To use a
different identifier, set it in **Admin Tools → Settings → Extension
Configuration → flue → `apiKeyVaultId`**. The secret is read as the logged-in
backend user, so it must be readable by whoever triggers the flow.

## 3. Create a flow

Create a **Flue flow** record (List module → "Flue flow", stored at root level):

| Field | Example |
|---|---|
| `title` | Page report |
| `identifier` | `page-report` (auto-slug from title) |
| `workflow_name` | `page-report` (the agent file on the sidecar) |
| `default_model` | `anthropic/claude-sonnet-4-6` |
| `mcp_tools` | `GetPage,ReadTable,RenderRecord,GetPageTree` (read-only allowlist) |
| `skills` | optional — skillflow skills to export to the sidecar |
| `instructions` | optional flow-level instructions (supports `{uid}` … tokens) |

> The MVP ships a `page-report` agent (`packages/flue-bridge/agents/page-report.ts`),
> a read-only page reporter. Keep `workflow_name`/`default_agent` = `page-report`
> unless you add more agents to the sidecar.

## 4. Start the Flue sidecar

In DDEV the sidecar runs as the `flue` service (`.ddev/docker-compose.flue.yaml`,
`node:22-bookworm`) — **no host Node needed**. `ddev restart` brings it up; on
first boot it `npm install`s, runs `flue init` (writes `flue.config.ts`), then
`flue dev`. (Running it on the macOS host fails — flue pulls Cloudflare `workerd`,
whose binary is Linux-only in this install; that's why it runs in the container.)

> ⚠️ This executes the third-party `@flue/runtime` beta inside the container.

`flue dev` serves on **port 3583** (flue's default — "FLUE" on a keypad) and binds
`0.0.0.0`, so the control plane reaches it intra-DDEV at
`http://<sitename>-flue:3583` (set as `sidecarBaseUrl`; **not** 3000). The agent
reaches TYPO3 by Docker service name (`http://web/mcp`). No host port is published.

The sidecar ships two files that define the flow:
- `agents/page-report.ts` — the read-only page-reporter agent (model + MCP tools).
- `workflows/page-report.ts` — the HTTP entry the control plane drives
  (`POST /workflows/page-report`). Its exported `route` is flue's admission
  boundary: it reads the per-request `X-Flue-Anthropic-Key` header and
  `registerProvider('anthropic', { apiKey })`. `run()` then `init(agent)`s
  (which passes the structured payload into the agent's `ctx.payload`), prompts,
  and returns `{ report }`. flue's `agents/` route only accepts chat `{ message }`,
  so the structured control-plane path needs this workflow.

**Prove the bridge without an LLM key** (pure MCP handshake + tools/list):

```bash
# mint a PAT: TYPO3 backend → User settings → "MCP Server" → create token
TYPO3_MCP_URL=https://<project>.ddev.site/mcp TYPO3_MCP_TOKEN=<pat> npm run probe:mcp
#   → "OK — adapted N TYPO3 tools …" incl. mcp__typo3__GetPage
```

## 5. Run a flow

**From the CLI (verified working):**

```bash
ddev exec vendor/bin/typo3 flue:run 1 99 --beuser=1
#   → "status settled"; prints the editorial report; mirrored into tx_flue_run
```

`--beuser` selects the backend user used to mint the MCP PAT **and** read the
vault key — so the CLI path triggers a full run on its own (the vault read
succeeds in the `--beuser` context). `flue:run <flowUid> <pageUid>`.

**From the module.** **Web → Flue** → pick the flow → enter a page id → **Run**.
Same path as a logged-in backend user; the run settles into `tx_flue_run` and the
run detail shows the report. (Live token streaming is a later upgrade — the
control plane currently polls the sidecar's run record to settlement.)

## Verify

- `npm run probe:mcp` → the sidecar reaches TYPO3 over MCP.
- After a run: the `tx_flue_run` row reaches `status=settled` with the report in
  `output`; the module's run detail shows the events.
- The persisted run row is the source of truth: `drainRun()` polls the sidecar's
  `GET /runs/<id>?meta` record to settlement, so a dropped browser just re-reads
  the stored `output`/`events`.
- Direct sidecar smoke test (from the web container):
  `ddev exec curl -s http://<sitename>-flue:3583/openapi.json` (lists the live API).

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Run settles `failed` immediately, "is the Flue sidecar running?" | Sidecar not up, or wrong port. `ddev restart` (boots the `flue` service); confirm `sidecarBaseUrl = http://<sitename>-flue:3583` (not 3000). |
| `{"error":{"type":"workflow_not_found"}}` | `workflows/page-report.ts` missing on the sidecar (only the *agent* exists). flue distinguishes `workflows/` (structured) from `agents/` (chat). Re-add the workflow file; `flue dev` hot-reloads it. |
| Run `errored`: "Missing authentication token" | The MCP PAT didn't reach the agent. Ensure typo3-mcp-server is installed and `--beuser` (CLI) / the logged-in user (module) can mint a token. |
| Run `errored` at the LLM (no provider key) | The vault key didn't reach the workflow's `route`. Confirm `flue_anthropic_api_key` is stored and readable by the triggering user; check `apiKeyVaultId`. |
| `probe:mcp` → 401 | PAT missing/expired, or wrong `TYPO3_MCP_URL`. Re-mint a token; from the host use `https://<project>.ddev.site/mcp`, inside DDEV `http://web/mcp`. |
| Agent writes/changes content | It shouldn't — the agent is pinned to the read-only allowlist. In DDEV, `localUnsafeMode` defaults MCP tools to the live workspace; keep `mcp_tools` read-only, and set User TSconfig `options.mcpServer.strictSandbox = 1` for write flows. |
| Flows blocked | `requireLocalEnvironment = 1` allows triggering only in Development + DDEV. |
