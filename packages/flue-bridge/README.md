# flue-bridge — real Flue (Node) sidecar for TYPO3

Runs the **real** [Flue](https://flueframework.com) runtime (`@flue/runtime` 1.0.0-beta.1)
as a Node 22 sidecar. TYPO3 is the control plane: the `webconsulting/flue`
extension exports skillflow **skills**, exposes TYPO3 **tools** via
`typo3-mcp-server` (MCP), injects page context (`{uid}` …), triggers flows, and
mirrors Flue's durable runs into the backend.

## ⚠️ This executes third-party beta code

Running `@flue/runtime` executes third-party beta code (npm lifecycle scripts +
the Flue runtime). In DDEV it runs **inside the `flue` container** (node:22), not
your host — `flue` pulls Cloudflare `workerd`, whose binary is Linux-only here, so
a host `flue dev` fails the platform check. The LLM-backed flow needs an Anthropic
key, injected **per request** by the TYPO3 control plane from nr-vault.

## Real API (verified against the installed 1.0.0-beta.1 + its shipped docs)

- ESM-only, `engines.node >= 22.19`, HTTP via **Hono**, MCP via `@modelcontextprotocol/sdk`.
- `createAgent(ctx => ({ model, instructions, tools, … }))` — agents in `agents/`, reached at `POST /agents/<name>/<id>` with a **chat** body `{ message }`.
- **Workflows** are files in `workflows/` exporting `async run({ init, payload })` (+ optional `route`), reached at `POST /workflows/<name>` with the **structured** payload. `init(agent)` threads the workflow payload into the agent's `ctx.payload`.
- `connectMcpServer('typo3', { url, transport, headers })` → `{ tools, close() }`; tool names `mcp__typo3__<Tool>`.
- LLM keys via `registerProvider('<id>', { apiKey })` (catalog providers also read `ANTHROPIC_API_KEY` from env). Run record: `GET /runs/<id>?meta`; live events: `GET /runs/<id>` (Durable Streams).
- `flue dev` serves on **port 3583** (default), binds `0.0.0.0`. Authoritative docs ship in `node_modules/@flue/cli/docs/`.

## Step 1 — prove the MCP bridge (no LLM key needed)

This is the riskiest seam and verifies on its own:

```bash
cd packages/flue-bridge
npm install                      # installs @flue/runtime (executes lifecycle scripts)
# Mint a PAT: TYPO3 backend → User settings → "MCP Server" → create access token
TYPO3_MCP_URL=https://<project>.ddev.site/mcp TYPO3_MCP_TOKEN=<pat> npm run probe:mcp
```

Expected: `OK — adapted N TYPO3 tools …` and a list including `mcp__typo3__GetPage`.
That proves Flue can authenticate to TYPO3 and call its tools.

## Step 2 — scaffold + run the sidecar

In DDEV this is automatic: `ddev restart` brings up the `flue` service
(`.ddev/docker-compose.flue.yaml`), which on first boot runs
`(npm install) && (flue init) && flue dev`. `flue init` writes **`flue.config.ts`**;
`agents/page-report.ts` + `workflows/page-report.ts` are already provided and
discovered under `agents/` / `workflows/`. To drive it by hand inside the
container: `ddev exec -s flue sh -c 'cd /app && npm run dev'`.

The sidecar is reachable from the web container at `http://<sitename>-flue:3583`
(`flue dev`'s default port; no host port is published).

> ⚠️ **`flue dev` hot-reload is unreliable for `workflows/` (and `agents/`/`lib/`)
> edits** — it logs "rebuilt" but keeps serving a stale build. After editing a
> workflow/agent, restart the sidecar: `docker restart ddev-<sitename>-flue`.

## Skills

The `webconsulting/flue` control plane exports a flow's selected skillflow skills
into `.agents/skills/<id>/SKILL.md` (the `flue-skills` volume mounts at
`/app/.agents/skills`, which Flue auto-discovers at context init). For discovery
to see the real files, the page-report agent uses the **`local()` sandbox**
(`@flue/runtime/node`, cwd `/app`) — the default *virtual* sandbox has an isolated
FS and never sees them. Discovered skills are auto-available by name; the flow
workflows (page-report & co.) just instruct the agent to apply them, while the
generic skill workflows below invoke ONE skill explicitly via `session.skill()`.

## Generic skill workflows (skillflow engine bridge)

`workflows/skill-run.ts` and `workflows/skill-batch.ts` make **any** skillflow
skill agent-runnable without a bespoke workflow: skillflow's Skills module (or
stage auto-run) routes a run through the `webconsulting/flue` extension's
`FlueSkillRunner`, which exports the skill and POSTs here. The workflow
validates the payload (valibot), preflights `.agents/skills/<skill>/SKILL.md`
(missing → structured `skill_not_found` error, no model call), then calls
`session.skill(<skill>, { args: {table, uid, workspace, instructions},
result: QA_RESULT | EDIT_RESULT | none })` on `agents/skill-runner.ts` — a
generic agent whose MCP tool allowlist and model come from the payload
(`lib/typo3-mcp.ts` policy: write tools only honored in `edit` result mode).
Batch = sequential `session.skill` calls, one **named session per skill**
(isolation ⇒ clean per-skill attribution, stamped structurally in
`lib/contracts.ts`, never model-asserted), rolled up worst-verdict/mean-score.
Contract details: flue extension `Documentation/SkillflowIntegration.md`.

## Step 3 — add the LLM API key

The Flue agent calls Claude, so it needs an Anthropic API key (`sk-ant-…`). The
`webconsulting/flue` TYPO3 module reads it from **nr-vault** by identifier and
injects it **per request** to this sidecar as a header — never written to disk
or this container's env. `agents/page-report.ts` restricts the agent to
read-only TYPO3 tools.

**Store the key in nr-vault** (the same encrypted store nr_llm uses). Vault
identifiers allow only letters, numbers and underscores and must start with a
letter — so use **`flue_anthropic_api_key`** (the flue extension's default
`apiKeyVaultId`), *not* a dotted name like `flue.anthropic.apiKey`.

CLI — hidden prompt, so the key never hits your shell history (note: `ddev exec`,
**not** `-it`, which is a `docker` flag):

```bash
ddev exec vendor/bin/typo3 vault:store flue_anthropic_api_key
#   → "Enter secret value" (hidden); paste the sk-ant-… key
ddev exec vendor/bin/typo3 vault:list        # confirm: lists flue_anthropic_api_key
```

Or via the backend module: **Tools → Vault → Secrets → create**, identifier
`flue_anthropic_api_key`, value = the key. To point flue at a different
identifier, set `apiKeyVaultId` in Admin Tools → Settings → Extension
Configuration → flue.

**Standalone sidecar (no TYPO3 control plane):** skip the vault and give the key
straight to this container — add `ANTHROPIC_API_KEY=sk-ant-…` to a gitignored
`.env` here (or the `flue` service env). Flue reads it natively, so `npm run dev`
+ a `page-report` run works without the module.

## Files

- `probe-mcp.mjs` — MCP-bridge proof (bare Node, no build).
- `agents/page-report.ts` — the read-only page-reporter agent (model + MCP tools).
- `workflows/page-report.ts` — the structured HTTP workflow the control plane drives;
  its `route` injects the per-request LLM key, `run()` dispatches the agent.
- `agents/skill-runner.ts` + `workflows/skill-{run,batch}.ts` — the generic
  skillflow engine bridge (one/many skills × one record, explicit `session.skill`).
- `lib/contracts.ts` — valibot payload/result contracts for the skill workflows;
  `lib/typo3-mcp.ts` — shared MCP connect + tool-allowlist policy.
- `flue.config.ts` — **generated by `flue init`** (`{ target: 'node' }`).
