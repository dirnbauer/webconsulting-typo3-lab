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

> ⚠️ Installing/running `@flue/runtime` executes third-party beta code on your
> machine. That is an operator action.

```bash
cd packages/flue-bridge
npm install            # once (executes npm lifecycle scripts)
npm run init           # once: flue init --target node → generates app.ts / db.ts
npm run dev            # starts the sidecar (Hono HTTP on :3000)
```

In DDEV the sidecar also boots via `.ddev/docker-compose.flue.yaml` — after
`npm run init` once, `ddev restart` brings the `flue` service up. It reaches
TYPO3 by Docker service name (`http://web/mcp`), and the control plane reaches
it at `http://<sitename>-flue:3000` (set as `sidecarBaseUrl`).

**Prove the bridge without an LLM key** (pure MCP handshake + tools/list):

```bash
# mint a PAT: TYPO3 backend → User settings → "MCP Server" → create token
TYPO3_MCP_URL=https://<project>.ddev.site/mcp TYPO3_MCP_TOKEN=<pat> npm run probe:mcp
#   → "OK — adapted N TYPO3 tools …" incl. mcp__typo3__GetPage
```

## 5. Run a flow

**From the module (recommended).** **Web → Flue** → pick the flow → enter a page
id → **Run**. Because you are a logged-in backend user, the control plane can
read the vault key and mint a typo3-mcp PAT; it POSTs to the sidecar, the agent
reads the page through `/mcp` (read-only), and the run streams live (SSE) and
settles into `tx_flue_run`. Re-opening the run replays its events by `seq`.

**From the CLI:**

```bash
ddev exec vendor/bin/typo3 flue:run <flowUid> <pageUid> --beuser=1
```

`--beuser` selects the backend user used to mint the MCP PAT. Note: nr-vault
gates the API-key secret by backend user, so a no-user CLI context may be denied
the key — the module path (logged-in user) is the reliable trigger.

## Verify

- `npm run probe:mcp` → the sidecar reaches TYPO3 over MCP.
- After a run: the `tx_flue_run` row reaches `status=settled` with the report in
  `output`; the module's run detail shows the events.
- Kill the browser mid-stream and reopen the run → it replays from the persisted
  `events` column (streaming is an optimization, not the source of truth).

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Run settles `failed` immediately, "is the Flue sidecar running?" | Sidecar not up. `npm run dev`, or `ddev restart` after `npm run init`. Check `sidecarBaseUrl`. |
| `probe:mcp` → 401 | PAT missing/expired, or wrong `TYPO3_MCP_URL`. Re-mint a token; from the host use `https://<project>.ddev.site/mcp`, inside DDEV `http://web/mcp`. |
| "Access denied to secret …" | The triggering backend user can't read the vault secret (or it's a no-user CLI context). Trigger from the module as the secret's owner, or grant the vault group. |
| Agent writes/changes content | It shouldn't — the agent is pinned to the read-only allowlist. In DDEV, `localUnsafeMode` defaults MCP tools to the live workspace; keep `mcp_tools` read-only, and set User TSconfig `options.mcpServer.strictSandbox = 1` for write flows. |
| Flows blocked | `requireLocalEnvironment = 1` allows triggering only in Development + DDEV. |
