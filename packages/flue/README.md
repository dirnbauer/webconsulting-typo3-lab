# webconsulting/flue

TYPO3 control plane for the [Flue](https://flueframework.com) agent framework.
Define and trigger durable AI **flows** on a page, inject page context (`{uid}`),
export skillflow **skills** to the sidecar, and mirror runs — all from a backend
module. The agents/workflows themselves run in a real Flue runtime (Node) sidecar
(`packages/flue-bridge`); this extension is the PHP control plane.

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
