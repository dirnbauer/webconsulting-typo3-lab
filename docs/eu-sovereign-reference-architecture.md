# EU-Sovereign Reference Architecture

**The reference architecture behind item 22 of the [TYPO3 Agentic Strategy 2026–2030](./typo3-agentic-strategy-2026.md)** — "European sovereignty as a product." The strategy compresses the positioning into one sentence: *"agentic CMS on EU-sovereign infrastructure, your models, your audit log, your data."* This document names the actual components, the trust boundaries, the compliance mapping and the hosting shapes behind that sentence — and marks honestly what is shipped, what is embryonic, and what is still direction.

Last updated: 2026-07-07. Status legend as in the strategy: ✅ shipped · 🌱 embryo in the lab · ⭕ direction only. Regulatory dates and market claims web-verified 2026-07-07; primary sources linked inline.

---

## 1. Why now

Five forces converged in 2025–2026. None is speculative; each has a date, a court order or a signed agreement behind it.

**The EU Data Act is in force and its teeth arrive in January 2027.** [Regulation (EU) 2023/2854](https://digital-strategy.ec.europa.eu/en/factpages/data-act-explained) applies since **2025-09-12**. It gives cloud customers a right to switch providers, caps switching charges at direct cost today, and [bans switching and data-egress charges entirely from **2027-01-12**](https://www.mccannfitzgerald.com/knowledge/data-privacy-and-cyber-risk/eu-data-act-switching-cloud-provider). Chapter VII additionally obliges providers to implement and publish safeguards against unlawful **non-EU governmental access** to non-personal data, including stating which jurisdiction their infrastructure is subject to. Portability and jurisdiction stop being nice-to-haves; they become contract-checklist items.

**The CLOUD Act conflict is no longer deniable.** On 2025-06-10, under oath before the French Senate's inquiry into digital sovereignty, Microsoft France's director of public and legal affairs answered "no" to the question whether Microsoft can guarantee that French data will never be handed to US authorities without French consent ([The Register](https://www.theregister.com/2025/07/25/microsoft_admits_it_cannot_guarantee/), [heise](https://www.heise.de/en/news/Not-sovereign-Microsoft-cannot-guarantee-the-security-of-EU-data-10494789.html)). That answer applies to every US-parented provider and every "sovereign region" they market: an EU datacenter of a US company is an EU *location*, not an EU *jurisdiction*.

**EU AI Act Article 50 transparency applies from 2026-08-02.** Deployers must disclose when people interact with AI, and providers must mark synthetic output in a machine-readable, detectable way ([Art. 50](https://artificialintelligenceact.eu/article/50/)); penalties reach €15M or 3% of worldwide turnover. The Digital Omnibus deferred *high-risk* obligations to December 2027, and the May 2026 provisional agreement grants generative systems already on the market until **2026-12-02** for the Art. 50(2) machine-readable marking — but the disclosure duty stays on schedule, and a [Code of Practice on marking and labelling](https://digital-strategy.ec.europa.eu/en/policies/code-practice-ai-generated-content) is being finalized. Any CMS that generates or serves AI content needs an answer.

**EAA enforcement moved from theory to court orders.** The European Accessibility Act is [enforceable since 2025-06-28](https://www.levelaccess.com/compliance-overview/european-accessibility-act-eaa/); the first lawsuits were filed against French retailers in November 2025, and in June 2026 the Caen Judicial Court [ordered Carrefour France to make carrefour.fr and its app fully accessible within six months under daily penalty](https://www.deque.com/blog/frances-major-court-decision-supporting-digital-accessibility-under-the-eaa/) — 71% RGAA conformance was ruled not enough. Accessibility is now a legal property of the delivery stack.

**Consolidation removed "neutral SaaS" as a category.** [Salesforce signed to acquire Contentful on 2026-06-01](https://www.salesforce.com/news/stories/salesforce-signs-definitive-agreement-to-acquire-contentful/) as the content layer for Agentforce. Even on the model side, Germany's sovereignty flagship Aleph Alpha [merged into Canada's Cohere in April 2026](https://techcrunch.com/2026/04/25/why-cohere-is-merging-with-aleph-alpha/). The lesson is not that these companies are bad; it is that **sovereignty pinned to a vendor's identity evaporates at acquisition**. Sovereignty has to live in the architecture: open source you can hold, models you can exchange, credentials and audit records that never leave.

Together: European enterprises are being pushed off US SaaS by law (Data Act, CLOUD Act exposure), pulled toward provable operations by regulation (AI Act, EAA, GDPR), and reminded by M&A that only architecture — not vendor promises — survives ownership changes. That is the demand this stack answers.

---

## 2. The reference stack

Six layers. Every component is open source or self-hostable; every layer is exchangeable without abandoning the stack.

| # | Layer | Component | Author / origin | Status |
|---|---|---|---|---|
| L1 | Content platform | TYPO3 v14.3 LTS + Desiderio design system | TYPO3 Association & community · webconsulting | ✅ |
| L2 | LLM abstraction | `nr-llm` | **Netresearch DTT GmbH** | ✅ core · ⭕ named EU profiles |
| L3 | Credential custody | `nr-vault` | **Netresearch DTT GmbH** | ✅ |
| L4 | MCP tool surface | `typo3-mcp-server` (lab fork) | Marco Pfeiffer / hauptsacheNet · lab fork | ✅ |
| L5 | Abilities registry | `typo3-abilities` + `typo3-capability-manifest` | webconsulting | ✅ core · 🌱 REST projection |
| L6 | Audit & trace | Skillflow runs · abilities traces · nr-vault audit log | webconsulting · Netresearch DTT GmbH | 🌱 partial — see items 13/15 |

**Credit where it is due:** the LLM foundation of this architecture — `nr-llm` and `nr-vault`, plus `nr-mcp-agent` and `t3-cowriter` used elsewhere in the lab — is the work of [Netresearch DTT GmbH](https://github.com/netresearch); the MCP server originates from Marco Pfeiffer (hauptsacheNet, [`hn/typo3-mcp-server`](https://github.com/hauptsacheNet/typo3-mcp-server)); TYPO3 itself is the product of the TYPO3 Association and its open-source community. The lab's contribution is selection, hardening, the registry and trace layers, and this composition.

### L1 — Content platform: TYPO3 v14 LTS

[TYPO3 14.3 LTS shipped 2026-04-21, community-supported into 2029, ELTS beyond](https://typo3.com/typo3-cms/development-roadmap/roadmap). GPL-licensed, self-hostable end to end — the property no SaaS can offer: the vendor cannot be acquired out from under you, because you hold the code and the data. Desiderio supplies the dual-audience layer on top: typed Content Blocks, schema.org JSON-LD, WCAG-checked presets (the EAA answer, see §4).

### L2 — LLM abstraction: nr-llm (Netresearch DTT GmbH)

[`netresearch/nr-llm`](https://github.com/netresearch/t3x-nr-llm) (v0.12.0 in the lab) centralizes provider management behind one interface: models are configuration, not architecture. This is the pivotal sovereignty property — the model becomes a *procurement decision revisable per task*, verified against the mid-2026 landscape:

| Tier (see §3) | Option | Notes (verified 2026-07) |
|---|---|---|
| T0 self-hosted | Mistral open-weight family (Apache 2.0) | The strongest EU open-weight line; runs on own GPUs via vLLM, or Ollama for dev |
| T0 self-hosted | [Teuken-7B / OpenGPT-X](https://huggingface.co/openGPT-X/Teuken-7B-instruct-commercial-v0.4) | Fraunhofer IAIS/IIS-led consortium; 24 EU languages, Apache 2.0; small but genuinely European |
| T0 self-hosted | Non-EU open weights (Llama, Qwen, …) | Not European, but jurisdiction-sovereign *when self-hosted*: weights on own metal answer to no foreign court |
| T1 EU API | Mistral La Plateforme (FR) | EU provider, EU hosting |
| T1 EU API | [OVHcloud AI Endpoints](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/) (FR) · IONOS AI Model Hub (DE) · [STACKIT AI Model Serving](https://european.cloud/2025/10/stackit-ai-model-serving/) (DE, Schwarz Digits) · Scaleway Generative APIs (FR) | Open-weight models served from EU infrastructure under EU jurisdiction, mostly OpenAI-compatible APIs |
| T2 non-EU API | OpenAI, Anthropic, Google — incl. their "EU regions" | Highest capability; CLOUD-Act-exposed regardless of region (§1). Explicit opt-in only |
| — caveat | Aleph Alpha → Cohere (April 2026) | Formerly the German T1 default; now a transatlantic entity (STACKIT-hosted). Reassess per contract — and the standing proof that models must stay exchangeable |

Honesty note: nr-llm ships provider management and encrypted key handling today (✅). **Named, switchable EU/local model *profiles*** — "profile: sovereign-strict → Teuken/Mistral on own vLLM" as first-class configuration — is the item-22 next step from the strategy, not yet built (⭕).

### L3 — Credential custody: nr-vault (Netresearch DTT GmbH)

[`netresearch/nr-vault`](https://github.com/netresearch/t3x-nr-vault) (v0.10.1): envelope encryption, access control and audit logging for every secret the agentic layer touches — model API keys, bridge tokens, payment credentials. Keys live encrypted in the institution's own database, never in a third-party secret manager; every access is logged in the same jurisdiction as the data (§3).

### L4 — MCP tool surface: typo3-mcp-server ✅

50 registered tools (verified 2026-07-06) across pages, content, records, files, schemas, workspaces, Solr, sites, logs, SafeCli and x402 — with **OAuth 2.1 + PKCE**, capability-manifest enforcement per tool, an FAL file sandbox, and workspace-aware writes (agents draft in workspaces; humans publish). The MCP server is self-hosted like everything else: an external agent connects to *your* endpoint under *your* token policy; no third-party gateway sits in the data path.

### L5 — Abilities registry: typed, permissioned, policy-gated ✅/🌱

`webconsulting/typo3-abilities` (registry core shipped 2026-07-07, strategy item 19): one `#[AsAbility]` registry of what the installation can do — JSON-Schema contracts, `resource:operation` scopes, risk tiers and side-effect vocabulary — executed through a single governed pipeline (policy gate → input validation → scopes → permission → execute → output validation), with `config/abilities-policy.yaml` supporting deny / review_required (HITL) / max_risk_tier. CLI and MCP projections are live; REST projection is 🌱. Sovereignty relevance: **the policy deciding what an agent may do is a file in your repository**, versioned and auditable — not a toggle in someone else's admin console.

### L6 — Audit ledger / trace store 🌱 — partially shipped

What exists: Skillflow run records (`tx_skillflow_run` with status/verdict/score/result_json), abilities execution traces and nr-vault's access audit log — all in the installation's own database. What does **not** exist yet: the unified `agent_run` trace store correlating tool calls, diffs, cost, reviewer and rollback path across all agents (strategy item 13), and policy/consent decisions as TCA records rather than static YAML (item 15). This layer is the architecture's stated destination, roughly one-third real.

---

## 3. Data flow and trust boundaries

```
┌─ Sovereign perimeter (own DC or EU-jurisdiction provider) ────────────────┐
│                                                                           │
│  Agent clients ─▶ nr-llm ─▶ abilities/MCP tools ─▶ TYPO3 DB + FAL  │
│                       │       (policy gate, HITL, workspace drafts) │
│                       └── nr-vault (keys, encrypted, local)          │
│                    T0 ──────────┼─▶ own vLLM/Ollama ... nothing leaves    │
└─────────────────────────────────┼─────────────────────────────────────────┘
                     T1 ──────────┼─▶ EU API (Mistral/OVH/IONOS/STACKIT/…)
                     T2 ──────────┴─▶ non-EU API (explicit opt-in, disclosed)
```

**What never leaves the perimeter:** content records and FAL assets, user PII, credentials (nr-vault), policy files, audit/ledger/run records, backend identities and permissions. **What crosses, and only on a model call:** the prompt context nr-llm assembles for that task — which is why the tier decision is made *per task*, not per installation.

| Tier | Model-call path | What leaves | Sovereignty property |
|---|---|---|---|
| **T0** | Own inference: vLLM/Ollama serving open weights (Mistral, Teuken, Llama, Qwen) on own or dedicated EU hardware | Nothing | Full — no external party in the data path |
| **T1** | EU API under EU jurisdiction (Mistral, OVHcloud, IONOS, STACKIT, Scaleway) | Prompt context per call | EU law governs the processor; Data-Act safeguard statements apply |
| **T2** | Non-EU API (OpenAI, Anthropic, Google — any region) | Prompt context per call | Capability over custody; CLOUD-Act-exposed; requires explicit policy opt-in and Art. 50-consistent disclosure |

The tiers are a *degradation path chosen deliberately per task*: T2 for a hard reasoning task over public content can be legitimate; T0/T1 for anything touching personal data or unpublished material. nr-llm makes the tier a configuration decision; the abilities policy (L5) is where it becomes enforceable per ability (⭕ — tier pinning per ability is direction, not yet code).

**Where audit records live:** in the TYPO3 database of the installation — `tx_skillflow_run`, abilities traces and nr-vault's audit log share the backup regime, retention policy and jurisdiction of the content itself. No compliance evidence sits in a foreign vendor's console.

### Proving it — the sovereignty demo

Readiness check #11 in the strategy asks: *"Can the whole stack run on EU-sovereign infrastructure with exchangeable models?"* The answer must be a demonstration, not a slide. The four-step proof, runnable in front of a client or auditor:

1. **Cut the cord.** Block all non-EU egress at the firewall; the installation keeps editing, serving, and running T0 agent tasks. (Works today on any T0 deployment.)
2. **Swap the model.** Switch the nr-llm provider from an EU API to local vLLM mid-session and rerun the same Skillflow skill — same tools, same policies, different model. (Works today via provider config; named profiles pending, §7.)
3. **Show the ledger.** For one agent-made change: which identity, which ability, which policy decision, who approved, what the workspace diff was. (Partial today — per subsystem, not unified; item 13.)
4. **Export everything.** Full DB dump + FAL sync to a second EU host, DNS flip, decommission the first. Total exit cost: hosting hours. (Works today; it is ordinary TYPO3 operations.)

Steps 1 and 4 are the Data-Act argument; step 2 is the model-sovereignty argument; step 3 is the governance argument. That two of four are fully demonstrable and two are partial is exactly the strategy's overall status — and worth saying out loud in sales conversations rather than discovering in procurement.

---

## 4. Compliance mapping

Which architectural element answers which regulation — with status, because compliance claims without status are marketing.

| Regulation / provision | Obligation | Architectural answer | Status |
|---|---|---|---|
| **Data Act** Ch. VI (switching, egress ban 2027-01) | Portability without penalty | GPL stack: full DB + FAL export via MCP/CLI/SQL; no proprietary formats, no per-seat hostage; hosting chosen for zero egress (§5) | ✅ by construction |
| **Data Act** Ch. VII | Safeguards against unlawful non-EU government access; jurisdiction transparency | Self-hosting (T0) removes the question; T1 providers must publish safeguard statements — a §5 checklist item | ✅ / contractual |
| **AI Act Art. 50(1)** (2026-08-02) | Disclose AI interaction | Provenance-labelled runs in agent_nexus ("Live model" vs "Scripted demo"); AI-disclosure content element planned | 🌱 — item 21 |
| **AI Act Art. 50(2)** (marking; legacy systems until 2026-12) | Machine-readable marking of synthetic output | C2PA Content Credentials in FAL (sign on upload, preserve through processing, expose in meta) | ⭕ **not implemented** — item 21 |
| **AI Act Art. 50** record-keeping (practical) | Show which system generated what, when, under whose approval | Run records + abilities traces (L6); unified trace store pending | 🌱 — item 13 |
| **EAA / EN 301 549** (enforced since 2025-06; Carrefour order 2026-06) | Accessible digital services, 100% of applicable criteria | Desiderio WCAG discipline: checked presets, semantic markup, CI guards | ✅ — billable service line |
| **GDPR** Art. 5/25 (minimization, by-design) | Least data leaves | Tiered model calls (§3); scoped context per task | ✅ pattern / 🌱 enforcement |
| **GDPR** Art. 28/44 ff. (processors, transfers) | Lawful processing chain | T0 has no processor; T1 processors under EU law; T2 requires transfer basis — made explicit instead of implicit | ✅ decision surface |
| **GDPR** Art. 30/32 (records, security) | Processing records, encryption | nr-vault envelope encryption + audit log; consent/policy records as TCA data **missing** | 🌱 — items 2/15 |

Reading of the table: portability, jurisdiction, accessibility and credential custody are answered today; **Art. 50 marking and the consent/trace layer are the honest gaps** (§7) — and they are gaps with named strategy items, not blind spots.

---

## 5. Hosting story

Three deployment shapes, one software stack — the stack does not change between them, which is itself the Data-Act switching answer.

| Shape | Where | Model tiers | Fits |
|---|---|---|---|
| **Own DC / on-prem** | Own hardware, own network; Ollama/vLLM on own GPUs | T0 (+T1/T2 by policy) | Public sector, defense-adjacent, works councils with hard data rules |
| **EU provider** | OVHcloud, IONOS, STACKIT, Scaleway, Hetzner + managed TYPO3 hosters; provider GPU or AI endpoints | T0/T1 | The default: sovereignty without running a datacenter |
| **EU region of US hyperscaler** | Azure/AWS/GCP "sovereign" EU offerings | T1-shaped, T2-exposed | Only where a corporate cloud mandate forces it — name the residual risk in writing: per Microsoft's own Senate testimony (§1), region ≠ jurisdiction |

**What to demand from a hosting partner** (the RFP checklist):

1. **EU legal entity without a non-EU parent** that can be compelled under the CLOUD Act or equivalents — the jurisdiction question, asked directly.
2. **Data Act Ch. VII statement**: published jurisdiction disclosure and safeguards against unlawful third-country access.
3. **Switching support per Data Act Ch. VI**: documented full-export path (DB dumps, FAL sync, DNS), contractual switching assistance, **zero egress fees now** — ahead of the 2027-01 ban, not trailing it.
4. **An inference lane**: GPU instances or EU-jurisdiction AI endpoints (OVHcloud/IONOS/STACKIT/Scaleway all offer one), so T0/T1 does not silently degrade to T2.
5. **Log and backup residency**: audit trails, backups and monitoring data stay in the EU — compliance evidence is data too.
6. **Sub-processor transparency**: the full list, with change notification; a sovereign primary with a US sub-processor in the path is not sovereign.

---

## 6. What US SaaS structurally cannot copy

Stated soberly — this is an argument about structure, not about quality.

1. **Jurisdiction.** A US-parented provider cannot contract its way out of the CLOUD Act; Microsoft confirmed that under oath (§1). Salesforce+Contentful, Adobe, Sitecore, Optimizely — every agentic-DXP contender is US-parented. A GPL CMS on EU infrastructure under an EU entity is outside that reach *by construction*, not by promise.
2. **Custody of the product itself.** SaaS cannot hand you its codebase and walk away; TYPO3 can, because it already did — it is open source. Contentful customers just learned their roadmap now serves Agentforce; TYPO3 v14 is supported into 2029 (ELTS beyond) by an association, not an acquirer.
3. **Model exchangeability.** Agentforce, Adobe and Sitecore are vertically integrated with their model stacks — the model is the product. Here the model is a config entry behind nr-llm (Netresearch DTT GmbH): Teuken on own vLLM today, Mistral over OVHcloud tomorrow, a T2 frontier model for one specific task — without touching the CMS. No US DXP can offer "bring your own sovereign model" as a first-class mode, because it would disintermediate them.
4. **Audit residency.** In SaaS, the evidence of what agents did lives in the vendor's console, under the vendor's retention and the vendor's jurisdiction. Here the ledger, run records and vault audit log sit in the same database as the content (§3) — subpoena-proof in the same way the content is.

What this argument does **not** claim: that EU models match US frontier capability everywhere (they don't — that is what T2 is for), that self-hosting is cheaper (it is a trade), or that US SaaS is unusable for EU firms (much of it is contractually manageable). The claim is narrower and harder: **for the class of European organizations that legally or politically cannot cede content operations to US jurisdiction, this stack exists and US SaaS structurally cannot follow it there.** That class is growing — by regulation (§1), not by sentiment.

---

## 7. Open gaps

The honesty section. This architecture is a positioning that ingredients support today (strategy verdict: "✅ ingredients exist") — it is not a finished product. The gaps, with their strategy items:

| Gap | Impact | Strategy item |
|---|---|---|
| **C2PA / Content Credentials not implemented** — no signing on upload, no preservation through processing, no marking of generative output | Art. 50(2) answer is a plan, not code; marking deadline 2026-08-02 (legacy marking 2026-12-02) | 21 |
| **Trace store partial** — Skillflow verdicts + abilities traces exist, but no unified `agent_run` record of tool calls/diffs/cost/reviewer/rollback | "Which agent changed what, and why" answerable per subsystem, not per installation | 13 |
| **Consent and policy records missing** — policies are YAML files, consent has no TCA table; no agent users with expiring scoped credentials | GDPR Art. 30-grade evidence for agent operations is manual today | 2 / 15 |
| **EU model profiles not first-class in nr-llm config** — tiers are practice, not named switchable profiles; no per-ability tier pinning | The §3 tier discipline relies on operator diligence rather than enforcement | 5 / 22 |
| **No retrieval layer** — zero embeddings/vector code; sovereign RAG story is direction only (SEAL when it matures) | "Your data grounds your models" is not yet demonstrable | 14 |
| **Protocol support is embryonic** — agent_nexus is a field guide with playgrounds rather than a production-certified integration | Multi-protocol agent operations still require productization | 17 / 18 |
| **llms.txt / agents.md generation missing** | Machine-readable self-description of the sovereign site is JSON-LD-only today | 20 |

Two closing cautions. First, this document is a reference *architecture*, not a certified compliance assessment — per-project legal review (Data Act contract terms, AI Act system classification, GDPR transfer analysis) remains mandatory. Second, the sovereignty market will attract decoration; the discipline that keeps this credible is the same one the strategy applies everywhere: **claim only what runs, date what is verified, and name what is missing.**

---

## 8. Executing item 22

The strategy names two next steps for item 22: *"a reference architecture doc + hosting partner story; EU/local model profiles in nr-llm configuration."* This document is the first. The rest, sequenced against the regulatory calendar:

| When | What | Why then |
|---|---|---|
| 2026-07 | This document; sovereignty demo (§3) scripted against the lab | Item 22 needs an artifact before it needs a pitch |
| 2026-08 | EU/local model profiles as named nr-llm configurations (coordinate upstream with Netresearch DTT GmbH); per-ability tier pinning in the abilities policy | Art. 50 in force 2026-08-02 — tier discipline becomes a client conversation |
| 2026 H2 | Hosting-partner story: validate the §5 checklist against 2–3 EU providers (one managed-TYPO3, one GPU-capable); C2PA read/verify in FAL (item 21); trace-store unification (item 13) | Egress-fee ban lands 2027-01-12 — partners advertising Data-Act readiness early win the switching wave |
| 2027 | Consent/policy TCA records (items 2/15); sovereign retrieval on SEAL when it matures (item 14); fold the pattern into the TYPO3 AI initiative proposal (item 23) | High-risk AI Act obligations 2027-12 make the governance layer mandatory for some clients |

The dependency to respect: **the compliance table (§4) only converts to revenue once steps 2–3 of the demo are fully green** — model profiles and the unified ledger are what turn "sovereign by architecture" from a diagram into a deliverable.
