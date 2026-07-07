# TYPO3 Agentic Strategy 2026–2030

**The long-form strategy behind `/typo3-v14-strategy/`** — what exists, what the market did, what must change, what to add, what to delete. The lab page is the public condensation of this document; this file is the working strategy with implementation status and next steps per item. The page mirrors this document's shape: a reading map, three numbered parts (I platform 1–12, II operating layer 13–16, III agentic web 17–23), one fixed rhythm per pillar (Already here / Next / Why it sells) and the readiness check as the closing self-test.

Last updated: 2026-07-07. Sources: full code inventory of the lab and all `github.com/dirnbauer` repositories, plus web research on the mid-2026 CMS, agent-standard and EU-regulatory landscape (primary sources linked inline where they matter).

---

## 1. Where this strategy stands

The strategy page grew in two waves: items **1–12** (the platform layer: MCP, skills, identity, CLI, APIs, billing, annotation, chat, codebase discipline) and items **13–16** (the operating layer: AgentOps, context fabric, governance, durable runtime). Both waves were written from inside the lab.

This revision adds the third wave from the outside in: what the **agentic web** now demands of a CMS (items **17–23**), corrects the items that reality has overtaken, and states honestly which claims have code behind them and which are still direction.

The one-sentence verdict: **the lab is 12–24 months ahead of TYPO3 core, roughly even with the leading edge of the market — and the durable advantage is governance, not model features.**

---

## 2. Analysis A — project reality (code vs. claims)

### Shipped and demonstrable

| Capability | Evidence |
|---|---|
| MCP server, **50 registered tools** (verified 2026-07-06 via `mcp:tool:list`) across pages, content, records, files, schemas, workspaces, Solr, sites, logs, SafeCli, x402 | `typo3-mcp-server`, OAuth + PKCE, capability-manifest enforcement, FAL file sandbox, workspace-aware everything |
| Agent skills as governed records | `skillflow`: `tx_skillflow_skill` + `tx_skillflow_run` (status/verdict/score/result_json), workspace-stage auto-runs, EnvironmentGuard (Dev+DDEV only), three runners (Anthropic API, Claude Code CLI with tool whitelist, nr-llm) |
| Attribute-driven API layer | `sg_apicore`: capability-policy YAML (deny / review_required / max_risk_score), scoped tokens, CORS allowlist, auto-CRUD |
| Paid content / machine payments | `typo3-x402-paywall` + five x402 MCP tools |
| Design system agents can parse | `desiderio`: typed Content Blocks, schema.org JSON-LD (`StructuredDataViewHelper`), semantic markup, WCAG-checked presets |
| Workspace-safe writing | workspace overlay patches, `easy-workspace`, MCP publish/rollback/review tools |

### New since the page was written (the material for items 17–23)

| Extension | What it proves |
|---|---|
| `agent_nexus` (2026-07-02) | TYPO3 can speak the whole agent-protocol family: **A2UI, AG-UI, A2A, UCP, AP2** — backend field-guide hub, five playgrounds, five frontend plugins, nr-llm-backed with deterministic fallbacks, human authorization gates, provenance-labelled runs ("Live model" vs "Scripted demo") |
| `opentag_bridge` (2026-07-04) | Steering TYPO3 from **Slack/Discord/Telegram/WhatsApp** via AG-UI/CopilotKit OpenTag. Pipeline: `TokenGuard → RateLimiter → IdentityMapper → AgentRunner (nr_llm) → ToolRegistry (native + MCP) → PolicyGate → HITL approve gate → Ledger`. This is the embryonic reference implementation of items 13 (ledger) and 15 (policy + human-in-the-loop) |
| `flue` + `flue-bridge` (2026-07-02) | TYPO3 as **control plane** for a durable agent runtime (`@flue/runtime`, Node 22 sidecar): exports Skillflow skills, consumes the MCP tools, triggers workflows, mirrors durable runs back into the backend — the embryo of item 16 |
| `t3x-nr-mcp-agent` (2026-06-30) + `webcon-mcp-chat-bridge` | Backend AI chat module with MCP client, document extraction, conversation housekeeping — item 11 shipped |
| `typo3-capability-manifest` + `api-capability-bridge` | Policy checker + capability manifests — the seed of the abilities registry (item 19) |
| `typo3-deepfake-detection` (private) | Inbound media forensics — the trust/provenance lane (item 21) |
| `typo3-camino-vercel` (2026-07-06) | TYPO3 14.3 on Vercel Functions — deployment modernization |
| `typo3-docx`, `typo3-records-list-types`, `typo3-tiptap`, `webconsulting-skills` | Editor-experience modernization around the agentic core |

### Honestly missing (as platform layers)

- **No *unified* trace/eval store.** Skillflow has verdict+score per run; opentag has a ledger; since 2026-07-07 the abilities registry traces every execution attempt (`tx_abilities_trace`) — but nothing yet unifies tool calls, cost, diffs and rollback paths across all agent lanes.
- **No retrieval infrastructure.** Zero embeddings, zero vector storage, no permission-aware context builder, no SEAL usage yet. Item 14 has the least code of anything on the page.
- **No policy/consent records.** Policies are static YAML (capability policies and the abilities policy alike); there is no TCA-backed rule table linking policy decisions to executions.
- **No generic durable job runtime.** TYPO3 Scheduler is cron; only the Flue experiment shows pause/resume/mirrored runs. The 2026-07-28 MCP **Tasks** extension is the alignment target — see `docs/mcp-spec-2026-07-28-adoption.md`.
- ~~No `llms.txt`/`agents.md` generation~~ **Shipped 2026-07-07**: `webconsulting/typo3-llms-txt` serves both files per site, generated from the page tree; agents.md advertises the MCP endpoint, the abilities registry, sitemap and the x402 lane.

---

## 3. Analysis B — the CMS industry, mid-2026

- **TYPO3**: v14 LTS shipped 2026-04-21 (support to 2029, ELTS beyond). The official AI effort is an early community initiative focused on interfaces/standards, plus **SEAL** (search abstraction) as groundwork for semantic search. No shipped core AI. TYPO3 core is *behind* its peers; this lab is ahead of core.
- **Drupal** institutionalized: AI Initiative with ~28 backing orgs and ~23 FTE contributors; 2026 roadmap includes background agents and AI governance/audit trails; since June 2026 split into **"Inside AI"** (AI in the editorial UI) and **"Outside AI"** (making Drupal legible and operable to external agents). The closest strategic analogue to this lab — with institutional muscle behind it.
- **WordPress** shipped the architecture lesson: the **Abilities API** in core 6.9 (typed, permissioned capability registry) and an official **MCP Adapter** (Feb 2026) that exposes abilities as MCP tools. One registry, many protocol projections — not N hand-rolled endpoints.
- **SaaS/headless**: Sanity rebranded "Content Operating System for the AI era" (Content Agent, Agent API, MCP server); Storyblok sells governance-tiered agent execution; MCP support is now a baseline RFP checkbox. **Salesforce signed to acquire Contentful (2026-06-01)** as the content layer for Agentforce — the consolidation signal.
- **Enterprise DXP**: Adobe (Agent Orchestrator, five GA MCP servers), Sitecore ("SitecoreAI", Agentic Studio), Optimizely (Opal). Forrester's category name is now **"agentic DXP"** — agent operability is an RFP line item.
- **Buyer mood** (Gartner 2026): agentic AI sits at the **Peak of Inflated Expectations**; only ~17% of organizations have deployed agents. Buyers reward governance, audit, MCP connectivity, SSO and ROI evidence — and punish AI decoration.

## 4. Analysis C — AI standards, economics, regulation

- **MCP is the settled substrate.** Donated to the Linux Foundation (Dec 2025; Anthropic, OpenAI, Block; AWS/Google/Microsoft/Cloudflare platinum). The next spec lands **2026-07-28**: stateless core, **Extensions**, **Tasks** (long-running work — directly relevant to item 16), MCP Apps, hardened authorization. Official registry ~9,650 servers; ~28% of the Fortune 500 have deployments. Enterprise pain points are exactly this lab's strengths: audit trails, SSO, gateways, approval layers.
- **The agent-facing web is forming, unevenly.** `llms.txt` is at ~10% adoption and Google publicly won't consume it — but coding agents do, and **Shopify ships `llms.txt` + `agents.md` to every store by default**. Browser agents (ChatGPT Atlas, Gemini-in-Chrome, Perplexity Comet) went mainstream in Q1 2026: sites are now *operated* by agents, not just read.
- **Zero-click economics are measured, not hypothetical.** AI Overviews cut outbound organic clicks ~40%; only ~8% click any result when a summary is shown. Cloudflare blocks mixed-use crawlers by default from **2026-09-15** and is evolving Pay-Per-Crawl into usage-based Pay-Per-Use. Machine readership becomes a metered channel — which is precisely what x402 gating already does in this lab.
- **Agentic payments are plural.** x402 has the most production traction (V2, Stripe-integrated on Base, Cloudflare support); Google AP2 has the broadest coalition (60+ partners); Stripe MPP, OpenAI ACP and Google UCP drive retail agent checkout. Strategy: keep the metering core, stay rail-agnostic.
- **EU AI Act, Art. 50 applies 2026-08-02** — weeks away: disclose AI interaction, machine-readably mark generative output; penalties to €15M / 3% of turnover. The Digital Omnibus deferred *high-risk* obligations to Dec 2027, but transparency stays on schedule. **C2PA/Content Credentials** (>6,000 members; OpenAI, Google SynthID, Meta embed it; cameras sign at capture) is the de-facto marking path — and FAL is its natural CMS home.
- **EAA** (accessibility) enforceable since June 2025; first lawsuits filed, formal supervision programs running, fines €5k–€500k per country. Desiderio's WCAG discipline is billable compliance work.
- **EU Data Act** (applies since 2025-09; egress-fee ban Jan 2027; safeguards against non-EU government access) fuels demand for EU-sovereign hosting — a structural advantage against Salesforce/Adobe/US SaaS.

---

## 5. Strategic verdict

1. **Add a third layer — "the agentic web" (items 17–23).** The first twelve items made TYPO3 *operable by agents*; 13–16 made that operation *manageable*; 17–23 make the whole installation *a citizen of the agentic web*: multi-protocol, discoverable, monetizable, provable, sovereign, and upstreamed.
2. **Change what reality overtook.** MCP has a new spec and a foundation; the LLM abstraction (nr-llm/nr-vault) is shipped, not planned; payments went plural; the chatbot exists; items 13/15/16 have embryos in `opentag_bridge` and `flue`.
3. **Delete decoration, keep discipline.** Retire the repeated tool-count boast (state it once, verified); retire chat-first framing (Gartner-trough buyers punish gimmicks); retire "should formalize" claims for things that now exist. **Audit result: no whole item dies** — all sixteen earned their place; the corrections are about status honesty and emphasis.

---

## 6. The strategy, item by item (status + next step)

Legend: ✅ shipped · 🌱 embryo in the lab · ⭕ direction only

### Platform layer (1–12)

| # | Item | Status | Next concrete step |
|---|---|---|---|
| 1 | Dual-audience design | ✅ Desiderio | Keep; feeds item 20 |
| 2 | Identity for agents | ✅ PATs, OAuth+PKCE, capability manifests · ⭕ agent users/expiry/consent | Agent-user concept with scoped, expiring credentials + consent records |
| 3 | MCP toolbox discipline | ✅ 50 tools · 🌱 spec-adoption checklist ready | Execute `docs/mcp-spec-2026-07-28-adoption.md`: P0 hygiene before 07-28, SDK v2 transport migration in August, Tasks (→ 16), registry listing via `remotes` entry |
| 4 | Agentic skills | ✅ Skillflow | Eval sets on top of run verdicts (→ 13) |
| 5 | LLM-agnostic libraries | ✅ nr-llm + nr-vault | EU/local model profiles as a sovereignty option (→ 22) |
| 6 | Token billing & usage | ✅ x402 tools · ⭕ per-account metering | Keep metering core; stay rail-agnostic (x402/AP2/MPP/ACP/UCP); explore inbound pay-per-crawl (→ 20) |
| 7 | Deterministic CLI | ✅ `mcp:*` + seeders | Promote as official automation surface |
| 8 | Backend headless by design | ✅ Content Blocks, VE, workspaces | Keep |
| 9 | Contract-first APIs | ✅ sg_apicore | Fold into the capability registry (→ 19) |
| 10 | Annotation instead of chat | ✅/🌱 Agentation plumbing | Close the loop: annotation → agent run → workspace diff |
| 11 | MCP chatbot / assistant | ✅ nr-mcp-agent + chat bridge | Reframe: one command surface over governed tools, not the product |
| 12 | AI-optimized codebase | ✅ Desiderio discipline | Keep; CI guards are the moat |

### Operating layer (13–16)

| # | Item | Status | Next concrete step |
|---|---|---|---|
| 13 | AgentOps: traces, evals, rollback | 🌱 Skillflow runs (verdict/score) + opentag **Ledger** + abilities **`tx_abilities_trace`** (every execution attempt incl. denials: ability, surface, input, outcome, duration, BE user — shipped 2026-07-07) | Unify into one `agent_run` trace store (tool calls, diffs, cost, reviewer, rollback path); add eval sets + regression checks |
| 14 | Context fabric | ⭕ zero retrieval code | Build on **SEAL** when it matures: permission-aware semantic index over records/FAL/history; source-backed context, never raw scraping |
| 15 | Governance: policy, consent, review | 🌱 opentag **PolicyGate + HITL gate**, capability-policy YAML | Promote policies from YAML to TCA records; risk tiers per tool/table/page subtree; consent ledger |
| 16 | Durable runtime | 🌱 Flue durable runs mirrored into TYPO3 | Generalize: every long job exposes owner, state, affected records, retry policy, cost, next action; align with MCP **Tasks** (`io.modelcontextprotocol/tasks` — concrete mapping for Publish/Rollback/Import in `docs/mcp-spec-2026-07-28-adoption.md`) |

### The agentic web (17–23) — NEW

**17. Agent protocols beyond MCP: A2UI, AG-UI, A2A, UCP, AP2** — 🌱 `agent_nexus`
MCP connects tools; the agentic web also needs agent↔UI (A2UI), agent↔user streaming with approval gates (AG-UI), agent↔agent delegation (A2A), agent↔merchant discovery (UCP) and signed payment mandates (AP2). `agent_nexus` demos all five against a real LLM with deterministic fallbacks and human authorization gates. *Sales angle: TYPO3 as the CMS that can **receive** agents — inquiries, delegated tasks, shopping agents — not merely host content.*
*Next: pick the two lanes with buyer pull (AG-UI approvals, UCP/commerce discovery) and productize them beyond the playground.*

**18. Channel operations: steer TYPO3 from Slack & Co.** — 🌱 `opentag_bridge`
Editors delegate from the tools they already live in ("draft a teaser about the summer opening on page 12"); TYPO3 drafts, replies with a summary, and publishes only after an Approve tap. Self-hosted, permission-mapped, budgeted, fully ledgered — the opposite of per-seat cloud bots that are blind to the CMS. *Next: harden the identity mapping and ship Discord/Teams connectors; promote the Ledger/PolicyGate into the platform layer (13/15).*

**19. A capability registry, not hand-rolled endpoints** — ✅ registry core shipped (`packages/abilities`, 2026-07-07) · 🌱 REST projection, TCA policy records
The WordPress Abilities API proved the architecture: one typed, permissioned registry of what the CMS can do; MCP tools, REST routes, CLI commands and chat tools become *projections* of that registry. This is the architectural bet that outlives any single protocol.
*Shipped:* `webconsulting/typo3-abilities` — `#[AsAbility]` registry schema exactly as specified (name, JSON-Schema contract, `resource:operation` scopes, capability-manifest risk tiers and side-effect vocabulary), one governed execution pipeline (policy gate → input validation → scopes → permission → execute → output validation), `config/abilities-policy.yaml` with deny/review_required (HITL)/max_risk_tier, CLI projection (`abilities:list|describe|run`), and the MCP projection: a compiler pass generates one `mcp.tool` per exposed ability — verified live, `ability_system_site-info` appears in `mcp:tool:list` beside the native tools. 86 unit tests, PHPStan max.
Execution traces shipped 2026-07-07 (`tx_abilities_trace`, → 13). How this relates to (and completes) the capability-manifest security envelope: `docs/wordpress-abilities-vs-capability-manifests.md`. *Next: REST projection on sg_apicore tokens/scopes; promote policy YAML to TCA records (→ 15); ability-vs-manifest cross-check audit rule; propose to the TYPO3 AI initiative (→ 23).*

**20. The machine-readable, monetizable site** — ✅ JSON-LD + llms.txt/agents.md shipped · 🌱 x402 pairing
AI Overviews cut clicks ~40%; browser agents operate sites directly; Cloudflare meters crawlers from Sept 2026. The answer is not to hide but to publish deliberately: schema.org JSON-LD (shipped), `llms.txt`/`agents.md` (shipped 2026-07-07: `webconsulting/typo3-llms-txt` serves both per site from the page tree — visible/indexable pages only; agents.md advertises the MCP endpoint, abilities registry with risk tiers, sitemap and the x402 lane, each detected at runtime; per-site opt-out; `llmstxt:dump` CLI), question-shaped content elements (shipped in Desiderio) — and, where content has value, **charge machine readers** via the x402 lane. Turn lost clicks into a licensed channel. *Next: pair with x402 gating tiers for AI crawlers; per-site doktype/depth settings; `llms-full.txt` for high-value sections.*

**21. Trust, provenance and AI-Act compliance** — 🌱 `typo3-deepfake-detection`; C2PA missing
Art. 50 EU AI Act applies **2026-08-02**: disclose AI interaction, machine-readably mark generative output. FAL is the natural home for **C2PA Content Credentials** (sign on upload, preserve through processing, expose in meta); deepfake-detection covers the inbound direction (is this asset manipulated?); the agent trace store (13) doubles as the compliance log. EAA enforcement makes the accessibility discipline billable too. *Sales angle: compliance as a recurring service line, not a burden.* *Next: C2PA read/verify in FAL metadata + AI-disclosure content element + generative-output marking in the AI pipelines.*

**22. European sovereignty as a product** — ✅ ingredients exist · ✅ reference architecture written
Data Act obligations, CLOUD-Act conflict, Salesforce buying Contentful: European enterprises increasingly cannot put their content operations on US SaaS. The stack in this lab — TYPO3 + nr-llm/nr-vault by Netresearch DTT GmbH (choose or self-host models) + self-hosted agent bridges + own audit ledger — is a positioning US SaaS cannot copy: *"agentic CMS on EU-sovereign infrastructure, your models, your audit log, your data."* The full layer-by-layer architecture, trust tiers (T0 own inference / T1 EU API / T2 non-EU opt-in), compliance mapping and hosting story: `docs/eu-sovereign-reference-architecture.md` (2026-07-07). *Next: hosting partner story; EU/local model profiles in nr-llm configuration.*

**23. Standardize and upstream: shape TYPO3's AI initiative** — ⭕ strategic action
Drupal institutionalized its AI push (28 orgs, 23 FTEs); TYPO3's initiative is early and interface-stage — which is an opportunity: the patterns in this lab (MCP server, capability registry, skills, workspace-staged agent writes) can become the *de-facto TYPO3 standard* if contributed now. The 12–24-month lead is worth most as ecosystem leadership, not as a private fork. *Next: TER releases for the mature extensions, initiative participation, one public reference implementation write-up per quarter.*

---

## 7. Readiness check v2

An installation is ready for the agentic era when it can answer, without hand-waving:

1. Which agent changed which record, with which permission, and why? (13)
2. Can a failed operation be rolled back or replayed safely? (13, 16)
3. Does the agent receive context that is current, scoped and source-backed? (14)
4. Can humans review high-risk changes before publication? (15)
5. Are AI costs visible enough to price, cap and report? (6)
6. Can long-running work pause, resume and hand off between people and systems? (16)
7. **Does the site disclose AI interactions and mark generated output — Art. 50 compliant?** (21)
8. **Can media provenance be proven (C2PA) and manipulation be detected?** (21)
9. **Can an external agent discover what this site offers (llms.txt / agents.md / JSON-LD) — and can you charge it for access?** (20)
10. **Which agent protocols can the installation speak beyond MCP?** (17)
11. **Can the whole stack run on EU-sovereign infrastructure with exchangeable models?** (22)

## 8. Horizon

| When | What |
|---|---|
| **2026-07-28** | New MCP spec (Tasks, Extensions, Apps) — adopt in typo3-mcp-server |
| **2026-08-02** | EU AI Act Art. 50 transparency in force — disclosure + marking service line |
| **2026-09-15** | Cloudflare default-blocks mixed-use crawlers — pay-per-crawl consulting window opens |
| 2026 H2 | Items 13/15 consolidation (unify trace store — abilities lane ✅ — + policy records); ~~llms.txt generator~~ ✅ 2026-07-07; C2PA read in FAL |
| 2027 | Capability registry proposal to the TYPO3 AI initiative; durable-runtime generalization (MCP Tasks); next TYPO3 LTS (~late 2027) — target: land patterns upstream |
| 2027-12-02 | AI Act high-risk obligations (deferred) — governance layer becomes mandatory for some clients |
| 2028–2030 | v14 support runs to 2029: migration + agentic-replatforming wave; ecosystem leadership pays out |

## 9. What was deliberately deleted from the page

- The tool count repeated as a boast in three places — stated once, verified (50 on 2026-07-06).
- Chat-first framing — chat is one governed command surface, not the product.
- "What v14 should formalize" claims for things that now exist (PATs, OAuth, capability manifests) — moved to "already here".
- Nothing else. The audit found no dead item; the page grows by one layer and gains status honesty.
