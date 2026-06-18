# webconsulting/flue

TYPO3 control plane for the [Flue](https://flueframework.com) agent framework.
Define and trigger durable AI **flows** on a page, inject page context (`{uid}`),
export skillflow **skills** to the sidecar, and mirror runs — all from a backend
module. The agents/workflows themselves run in a real Flue runtime (Node) sidecar
(`packages/flue-bridge`); this extension is the PHP control plane.

## Concept (short)

**Hybrid bridge to real Flue.** The lab already has the AI primitives in PHP
(nr-llm = model, nr-mcp-agent = agent loop, typo3-mcp-server = an MCP tool server,
skillflow = skills, nr-vault = secrets) — they just weren't unified into a durable
agent/workflow runtime. Rather than reimplement the hard parts (durable execution,
sandboxes, the agent loop), we run the **real Flue runtime as a Node sidecar** and
make TYPO3 the **control plane**: define/trigger flows, inject `{uid}` page context,
export skillflow skills, mint a read-only typo3-mcp PAT, inject the LLM key per
request from nr-vault, and mirror runs into `tx_flue_run`. The Flue agent calls back
into TYPO3 over `/mcp` (`connectMcpServer({ headers:{ Authorization: Bearer <PAT> } })`).
Secrets stay in PHP memory and cross as per-request headers. Clean seam to add
PHP-native bits later.

Full design + as-built corrections + roadmap: **[Documentation/Concept.md](Documentation/Concept.md)**.

## What it does

- Backend module **Web → Flue**: list flows, trigger a flow on a page, watch the
  live run (SSE) and inspect durable run reports.
- `tx_flue_flow` (flow definitions) + `tx_flue_run` (durable, append-only event
  log with compare-and-set status).
- Triggers a flow on the Flue sidecar, injecting resolved page context, the
  selected skills, a typo3-mcp-server PAT (read-only tool allowlist) and — per
  request — the LLM API key from nr-vault.
- Soft dependencies (each guarded by `class_exists`): skillflow (skills),
  typo3-mcp-server (tools), nr-vault (secrets), nr-llm. Installs standalone.

## Run a flow (end-to-end)

Five steps, once. Full runbook: **[Documentation/Setup.md](Documentation/Setup.md)**.

1. **Install** — `ddev composer require webconsulting/flue:@dev && ddev exec vendor/bin/typo3 extension:setup` (creates `tx_flue_flow`/`tx_flue_run` + the module).
2. **Store the LLM key** in nr-vault as `flue_anthropic_api_key` (see *Add the LLM API key* below).
3. **Create a flow** — a *Flue flow* record (List module) with `workflow_name = page-report`, a model, and a read-only MCP tool allowlist; attach skills if wanted.
4. **Start the Flue sidecar** (Node, third-party beta — executes on your machine):
   `cd packages/flue-bridge && npm install && npm run init && npm run dev` (or `ddev restart` after `npm run init`). No-LLM bridge check: `npm run probe:mcp`.
5. **Run** — **Web → Flue** → pick the flow, enter a page id, **Run**. The control plane injects the key + a read-only typo3-mcp PAT and streams the report into `tx_flue_run`.

## Activate

```bash
ddev composer require webconsulting/flue:@dev
ddev exec vendor/bin/typo3 extension:setup     # creates tx_flue_flow / tx_flue_run, registers the module
```

Then create a **Flue flow** record (List module → "Flue flow") with a sidecar
`workflow_name` (e.g. `page-report`) and attach skills, and run it from the
**Web → Flue** module or the CLI:

```bash
ddev exec vendor/bin/typo3 flue:run <flowUid> <pageUid>
```

## Add the LLM API key

The Flue agent calls an LLM, so the sidecar needs an API key. This extension
reads it from **nr-vault** by identifier and injects it **per request** to the
sidecar as a header — never written to disk, the run row, or the container env.

Vault identifiers allow only **letters, numbers and underscores** and must start
with a letter, so use **`flue_anthropic_api_key`** (this extension's default
`apiKeyVaultId`), *not* a dotted name.

```bash
# CLI — hidden prompt; key never hits shell history (ddev exec, NOT -it):
ddev exec vendor/bin/typo3 vault:store flue_anthropic_api_key
#   → "Enter secret value" (hidden); paste the sk-ant-… key
ddev exec vendor/bin/typo3 vault:list          # confirm
```

Or via the backend module **Tools → Vault → Secrets → create** (identifier
`flue_anthropic_api_key`, value = the key).

Point flue at a different identifier in **Admin Tools → Settings → Extension
Configuration → flue → `apiKeyVaultId`**. The secret is read as the logged-in
backend user, so it must be readable by whoever triggers the flow.

> Standalone sidecar test (no control plane / vault): put `ANTHROPIC_API_KEY` in
> the sidecar's env — see `packages/flue-bridge/README.md`.

## Extension configuration (Admin Tools → Settings)

| Setting | Default | Purpose |
|---|---|---|
| `sidecarBaseUrl` | `http://localhost:3000` | Flue sidecar URL (in DDEV: `http://<sitename>-flue:3000`) |
| `defaultModel` | `anthropic/claude-sonnet-4-6` | Model passed to flows without their own |
| `requestTimeout` | `30` | HTTP timeout (s) when triggering a flow |
| `apiKeyVaultId` | `flue_anthropic_api_key` | nr-vault identifier of the LLM API key |
| `requireLocalEnvironment` | `1` | Only allow triggering on a local DDEV/Development install |

## Security

- LLM key + MCP token cross to the sidecar as per-request headers only; never
  persisted, logged, or placed in the container env.
- The agent is restricted to a read-only typo3-mcp tool allowlist
  (`GetPage`/`ReadTable`/`RenderRecord`/`GetPageTree`).
- `requireLocalEnvironment` gates execution to local DDEV/Development (matches
  skillflow).
