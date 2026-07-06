# MCP Spec 2026-07-28 — Adoption Checklist for typo3-mcp-server

**Document date:** 2026-07-07 (spec final is 3 weeks out). **Audience:** maintainer of
`hn/typo3-mcp-server` (`/Users/dirnbauer/projects/typo3-mcp-server`, ext version 0.5.1).
**Spec status:** Release candidate, locked **2026-05-21**; final publication **2026-07-28**.
The RC is published as the [draft specification](https://modelcontextprotocol.io/specification/draft);
changes below are from the [official changelog vs 2025-11-25](https://modelcontextprotocol.io/specification/draft/changelog)
and the [RC announcement](https://blog.modelcontextprotocol.io/posts/2026-07-28-release-candidate/).
Governance: MCP sits under the Agentic AI Foundation / Linux Foundation
([AAIF, 2026-05-27](https://aaif.io/blog/mcp-is-growing-up/)). Anything not yet final is marked **[RC — could still shift]**.

---

## 1. What the new spec changes (verified)

### 1.1 Stateless core (breaking)

| Change | SEP | Impact |
|---|---|---|
| `initialize`/`notifications/initialized` handshake **removed**; every request carries `io.modelcontextprotocol/protocolVersion`, `.../clientInfo`, `.../clientCapabilities` in `_meta`; mismatch → `UnsupportedProtocolVersionError` (`-32022`) | [SEP-2575](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2575) | Transport rewrite |
| Protocol-level sessions and `Mcp-Session-Id` header **removed**; cross-call state via explicit server-minted handles passed as tool arguments | [SEP-2567](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2567) | Session store obsolete |
| New `server/discover` RPC — servers **MUST** implement (advertise versions, capabilities, identity) | SEP-2575 | New required method |
| `Mcp-Method` + `Mcp-Name` HTTP headers **required** on Streamable HTTP POST; custom headers via `x-mcp-header` | [SEP-2243](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2243) | Endpoint + CORS |
| HTTP GET endpoint and `resources/subscribe`/`unsubscribe` replaced by single `subscriptions/listen` POST stream (opt-in `toolsListChanged`, etc.) | SEP-2575 | Only if notifications needed |
| SSE resumability (`Last-Event-ID`) removed; broken stream → client re-issues request with new ID | SEP-2575 | Simplification |
| `ping`, `logging/setLevel`, `notifications/roots/list_changed` removed; log level per request via `io.modelcontextprotocol/logLevel` in `_meta` | SEP-2575 | Cleanup |
| Server-initiated requests (`sampling/createMessage`, `elicitation/create`, `roots/list`) replaced by **Multi Round-Trip Requests**: server returns `resultType: "input_required"` + `inputRequests` + `requestState`; client retries with `inputResponses`. All results now carry required `resultType` | [SEP-2322](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2322) | Schema-level break |

### 1.2 Extensions framework, Tasks, MCP Apps

- **Extensions** ([SEP-2133](https://modelcontextprotocol.io/seps/2133-extensions)): reverse-DNS IDs
  (`io.modelcontextprotocol/...` official; third parties use their own domain, e.g. `at.webconsulting/...`),
  negotiated via new `extensions` map on Client/ServerCapabilities, versioned independently in `ext-*` repos.
  Always opt-in. Docs: [Extensions overview](https://modelcontextprotocol.io/docs/extensions/overview).
- **Tasks** ([SEP-2663](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2663)) graduates from
  2025-11-25 experimental core to official extension `io.modelcontextprotocol/tasks`. Redesigned for the
  stateless model: `tools/call` may return a task handle (unsolicited, no per-request opt-in); client drives
  via `tasks/get` (polling), `tasks/update` (mid-flight input), `tasks/cancel`. Blocking `tasks/result` and
  `tasks/list` are **removed** — anyone on the experimental API must migrate.
- **MCP Apps** ([SEP-1865](https://github.com/modelcontextprotocol/ext-apps)): servers ship interactive HTML
  UIs rendered in a sandboxed iframe; tools pre-declare UI template resources (prefetch/cache); UI actions go
  through the same JSON-RPC audit/consent path as tool calls. No new RPC methods.

### 1.3 Authorization hardening

- Clients **MUST** validate `iss` in authorization responses (RFC 9207); auth servers **SHOULD** send it ([SEP-2468](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2468)).
- Clients declare OIDC `application_type` during Dynamic Client Registration ([SEP-837](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/837)).
- Credentials keyed by issuer; no reuse across auth servers; re-register on issuer change ([SEP-2352](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2352)).
- **RFC 7591 Dynamic Client Registration is deprecated** in favor of **Client ID Metadata Documents (CIMD)**
  ([PR #2858](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2858)); DCR stays for back-compat.
- Refresh-token guidance (SEP-2207), scope step-up accumulation (SEP-2350), `.well-known` suffix clarification (SEP-2351).

### 1.4 Caching, schemas, errors, deprecations

- `ttlMs` + `cacheScope` (`"public"`/`"private"`) **required** on `tools/list`, `prompts/list`, `resources/list`,
  `resources/read`, `resources/templates/list` ([SEP-2549](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2549));
  `tools/list` SHOULD be deterministic ordering (prompt-cache hits).
- Tool `inputSchema`/`outputSchema` = full JSON Schema 2020-12 (composition, conditionals, `$ref`; no auto-deref of external `$ref`) ([SEP-2106](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2106)).
- Resource-not-found error `-32002` → `-32602`; MCP reserves `-32020`…`-32099` (`HeaderMismatch` -32020, `MissingRequiredClientCapability` -32021, `UnsupportedProtocolVersion` -32022).
- OpenTelemetry trace context in `_meta`: `traceparent`, `tracestate`, `baggage` ([SEP-414](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/414)).
- **Deprecated** (≥12-month window, [SEP-2577](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2577)/[SEP-2596](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2596)): Roots, Sampling, Logging features; HTTP+SSE transport. Formal feature lifecycle (Active → Deprecated → Removed) + conformance suite ([modelcontextprotocol/conformance](https://github.com/modelcontextprotocol/conformance)).

**[RC — could still shift]** Exact error-code numbers were renumbered *within* the RC cycle already; re-verify
codes and required-header semantics against the published spec on 2026-07-28 before hard-coding.

---

## 2. Gap analysis — typo3-mcp-server today

### 2.1 SDK: logiscape/mcp-sdk-php

| Fact | Status |
|---|---|
| composer.json requires `^1.2`; **composer.lock pins v1.7.1** | Behind — v1.7.4 stable exists (2026-07-03) |
| v1.x line supports spec **2024-11-05 → 2025-11-25** (2025-11-25 landed in v1.7.0) | Cannot speak 2026-07-28 |
| **v2.0.0-beta3** (2026-07-05, `composer require logiscape/mcp-sdk-php:2.0.0-beta3`, PHP ≥ 8.1): day-one 2026-07-28 support — stateless `server/discover`, no `Mcp-Session-Id`, MRTR, `ttlMs`/`cacheScope`, JSON Schema 2020-12, Tasks ext (SEP-2663), Apps ext (SEP-1865), OAuth hardening; dual-era negotiation keeps 2024-11-05…2025-11-25 clients working per request | Target line; **breaking API vs v1** |
| v2.0.0 final is **gated on the 2026-07-28 spec publication** + conformance validation (100% pass on applicable conformance tests claimed) | Expect final late July/August 2026 |

Sources: [github.com/logiscape/mcp-sdk-php](https://github.com/logiscape/mcp-sdk-php) (README + CHANGELOG), [Packagist](https://packagist.org/packages/logiscape/mcp-sdk-php). The SDK is community-maintained (not an official Tier-1 SDK), but it is tracking the new spec ahead of publication — good position.

### 2.2 What the extension already satisfies

- **Per-request auth, no server-side conversational state**: `McpEndpoint` builds an anonymous BE-user session per request and discards it — the TYPO3 side is already stateless; only the *SDK transport* layer is session-bound (`Mcp\Server\Transport\Http\FileSessionStore` in `var/mcp_sessions`, `session_timeout` 1800, via `HttpServerRunner`) — `Classes/Http/McpEndpoint.php`.
- **Explicit-handle pattern**: workspace-aware writes already key state by TYPO3 workspace IDs/UIDs passed as tool arguments — exactly the SEP-2567 recommended pattern.
- **OAuth 2.1-shaped stack**: PKCE (`OAuthAuthorizeEndpoint`, `OAuthService`), RFC 8414 AS metadata, **RFC 9728 protected-resource metadata** (`OAuthResourceMetadataEndpoint`), token endpoint, DCR (`OAuthRegisterEndpoint`).
- **Tool annotations discipline**: capability manifest (`Configuration/Capabilities.yaml` subsystems + `requires:` chains) enforced in `ToolRegistry`/`CapabilityManifestService`; 45 bundled tools (README) via `#[AutowireIterator('mcp.tool')]` + `CompatibleToolAdapter`.
- **Abilities projection** (lab package `packages/abilities`, `ability_*` tools): already declares `idempotent`/`destructive`/`sideEffects`/JSON-Schema contracts — maps cleanly onto 2026-07-28 tool metadata.

### 2.3 What breaks / needs work

1. **Transport**: `FileSessionStore`/`HttpServerRunner` (v1 SDK) implement the session-era Streamable HTTP. Under 2026-07-28: no `initialize`, no session ID, `server/discover` required, `Mcp-Method`/`Mcp-Name` required, `resultType` on every result. All of this arrives with SDK v2 — but v2 is a **breaking API**, so `McpServerFactory`, `McpEndpoint`, `ToolInterface`/`CompatibleToolAdapter` (uses `Mcp\Types\CallToolResult`) need a migration pass.
2. **CORS**: `CorsHeadersTrait` must allow `Mcp-Method`, `Mcp-Name`, `x-mcp-header` (browser-based clients).
3. **Caching fields**: `tools/list` is per-user filtered (capability manifest, dev-site gating, workspace) → must emit `cacheScope: "private"` + a sane `ttlMs`; ensure deterministic tool ordering in `ToolRegistry::getTools()`.
4. **DCR deprecation**: `OAuthRegisterEndpoint` is RFC 7591; no CIMD support found in the codebase (grep `client_id_metadata` = 0 hits). Keep DCR, add CIMD (client_id = HTTPS URL to a metadata document).
5. **`iss` parameter**: verify `OAuthAuthorizeEndpoint` includes `iss` in authorization responses (RFC 9207 — SHOULD for the server side).
6. **Error codes**: audit for `-32002` (resource not found) and any codes in the newly reserved `-32020…-32099` band.
7. **No Tasks support**: long-running tools (`PublishWorkspace`, `RollbackWorkspace`, import/audit, `SolrIndexQueue`, `InstallExtension`) run synchronously inside one PHP-FPM request — timeout-prone; exactly what the Tasks extension solves.
8. **Logging/Sampling/Roots**: server doesn't rely on Roots/Sampling (good — both deprecated). If any tool emits `notifications/message`, gate it on `_meta` `io.modelcontextprotocol/logLevel`.

Note: the stateless core is a *net win* for PHP — one request = one process is PHP-FPM's native model; the session store was always the impedance mismatch.

---

## 3. Adoption tasks (priority order)

### P0 — before 2026-07-28 (safe now)
- [ ] `composer update logiscape/mcp-sdk-php` to **v1.7.4** (stays on 2025-11-25 spec; bugfixes only).
- [ ] Make `ToolRegistry::getTools()` return a deterministically sorted list (spec SHOULD; prompt-cache win today).
- [ ] Audit error codes for `-32002` and `-32020…-32099` collisions.
- [ ] Add `iss` to authorization responses in `OAuthAuthorizeEndpoint` (RFC 9207).
- [ ] Accept + store `application_type` in `OAuthRegisterEndpoint` (SEP-837).
- [ ] Spike branch: `logiscape/mcp-sdk-php:2.0.0-beta3` in the lab (`webconsulting-typo3-lab`) — inventory every compile break in `McpServerFactory`, `McpEndpoint`, `CompatibleToolAdapter`; run the conformance suite against the DDEV endpoint.

### P1 — stateless core migration (after v2.0.0 final, ~Aug 2026)
- [ ] Replace `HttpServerRunner` + `FileSessionStore` with the v2 stateless HTTP server; delete `var/mcp_sessions` handling.
- [ ] Implement/verify `server/discover` (SDK-provided) advertises correct identity + capabilities incl. `extensions` map.
- [ ] Enforce/relay `Mcp-Method`/`Mcp-Name`; extend `CorsHeadersTrait` allow-list; update `McpHttpLogRedactor` for new headers.
- [ ] Emit `resultType`, `ttlMs`, `cacheScope: "private"` on list/read results.
- [ ] Read `_meta` client info for logging/audit (replaces initialize-time clientInfo); optionally honor `traceparent`.
- [ ] Keep dual-era negotiation ON (SDK v2 does this per request) — Claude et al. may lag on older revisions.
- [ ] Update functional + `test:llm` suites and the CLI mirror for the new lifecycle.

### P2 — Tasks extension (strategy item 16, "durable runtime")
- [ ] Adopt `io.modelcontextprotocol/tasks` via SDK v2 for: `PublishWorkspace`, `RollbackWorkspace`, import/audit tools, `SolrIndexQueue`, `InstallExtension`.
- [ ] Persist task state in a TYPO3 table (uid/handle, owner BE user, state, affected records, retry policy, cost, next action — the item-16 schema in `docs/typo3-agentic-strategy-2026.md`); execute via Scheduler/CLI worker or symfony messenger-style command so `tasks/get` polls DB state statelessly.
- [ ] Wire `tasks/cancel` to workspace-safe abort; `tasks/update` for approval-gated publishes (mid-flight input).
- [ ] Mirror task lifecycle into the Flue durable-run view (lab) — one shared vocabulary.

### P3 — authorization modernization
- [ ] Implement **CIMD** client registration (fetch + validate HTTPS client-id metadata documents, cache them); keep RFC 7591 endpoint for legacy clients, mark deprecated in docs.
- [ ] Key stored client credentials by issuer (SEP-2352 semantics on the server side of the ledger).

### P4 — opportunities (post-adoption)
- [ ] **MCP Apps** for the editor verification loop: `RenderRecord`/`GetPreviewUrl` pre-declare an HTML preview template → hosts render backend preview inline in a sandboxed iframe. Experimental; gate behind capability + CSP review.
- [ ] Declare a third-party extension ID `at.webconsulting/capability-manifest` to advertise the manifest/subsystem vocabulary via the `extensions` capability map (also for the `abilities` registry projection).
- [ ] Use JSON Schema 2020-12 composition (`oneOf`) in `ToolSchemaOptimizer` where flattened enums are lossy today.

---

## 4. Official MCP Registry listing

Registry: `https://registry.modelcontextprotocol.io` — **preview status; breaking changes/data resets possible**
([quickstart](https://modelcontextprotocol.io/registry/quickstart), [package types](https://modelcontextprotocol.io/registry/package-types), [remote servers](https://modelcontextprotocol.io/registry/remote-servers)).

Facts that matter for a TYPO3/Composer extension:

1. Supported `registryType`s are **npm, pypi, nuget, oci, mcpb only — no Composer/Packagist** (verified 2026-07-07). A `packages` entry for `hn/typo3-mcp-server` is currently impossible; MCPB doesn't fit either (extension isn't a standalone runnable). **[Speculation]** watch the registry repo for a composer package type; consider filing/upvoting an issue.
2. **Remote listing is the viable route**: `remotes: [{ "type": "streamable-http", "url": ... }]`. URL "MUST be publicly accessible" — for a self-hosted product use **URL template variables**: `"url": "https://{typo3_host}/mcp"` with `variables: { typo3_host: { description, isRequired: true } }`; optionally list the public lab/demo endpoint as a concrete install.
3. `server.json`: `$schema` `https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json`; required: `name`, `description`, `version`; plus `title`, `repository { url, source: "github" }`, `remotes`/`packages`. Generate with `mcp-publisher init`.
4. **Namespace = auth method**: GitHub login (`mcp-publisher login github`) → name MUST start `io.github.dirnbauer/…` (e.g. `io.github.dirnbauer/typo3-mcp-server`); or DNS/HTTP domain verification for `at.webconsulting/typo3-mcp-server`.
5. Steps: install `mcp-publisher` (brew or release binary) → `mcp-publisher init` → edit `server.json` → `mcp-publisher login github` (device flow) → `mcp-publisher publish` → verify `curl "https://registry.modelcontextprotocol.io/v0.1/servers?search=<name>"`.
6. Version in `server.json` should track the extension release triple (0.5.x); republish per release.

---

## 5. Timeline recommendation (today = 2026-07-07)

| Window | Action |
|---|---|
| **Now → 2026-07-28** | P0 items; SDK v2-beta3 spike in the lab; run conformance suite; do NOT ship v2-beta to production endpoints |
| **2026-07-28** | Spec final publishes — re-verify §1 details (esp. error codes, header MUSTs) against the published text |
| **Aug 2026** | logiscape v2.0.0 final expected (gated on spec) → P1 stateless migration on a feature branch; keep dual-era negotiation for old clients |
| **Sep 2026** | P2 Tasks extension (aligns with strategy item 16); release as mcp_server 0.6.0 |
| **Q4 2026** | P3 CIMD; P4 Apps experiment; registry listing (remote + templated URL) once registry preview stabilizes / composer question answered |
| **Ongoing** | Deprecation window: Roots/Sampling/Logging removal is ≥12 months out (mid-2027 earliest) — no urgency, just don't add new usage |

*Compiled 2026-07-07 from modelcontextprotocol.io (draft spec + changelog + registry docs), blog.modelcontextprotocol.io, aaif.io, github.com/logiscape/mcp-sdk-php, and the extension source. Re-verify RC-marked items on 2026-07-28.*
