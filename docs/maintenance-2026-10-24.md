# Maintenance: 24 October 2026

The deployed project's Composer graph was refreshed, including all 27 own
extensions and maintained forks, with private Templates now included. New
releases were pushed to their configured GitHub and GitLab remotes; private
Poppy stays on GitLab.
CI-only changes do not manufacture new package tags. Local development work is
kept separately from the release sources.

## Runtime and builds

| Tool | Version |
|---|---|
| TYPO3 | 14.3.10 |
| PHP | 8.5.12 |
| Composer | 2.10.3 |
| Node.js | 26.12.1 |
| npm | 12.2.1 |
| DDEV | 1.25.4 |
| Font build and Nexus lint Python / pip | 3.15.1 / 26.2.2 |
| Local MariaDB | 10.11.19 |
| Production MariaDB | 11.8.9, existing patched image |
| Solr | 10.0.0, paired with EXT:solr 14.0.2 |

Every active extension frontend pipeline was audited and rebuilt, including
nested tooling. Frozen third-party source snapshots remain provenance fixtures.
No active Ruby, Go or Rust application build was found in the used extension
repositories. Build defaults and CI use the current Node/npm versions. The
PHP base image default must match in Dockerfile and Compose; the quality suite
now rejects a mismatch.

## Own extension lock

| Composer package | Locked version |
|---|---|
| `hn/typo3-mcp-server` | 0.9.8 |
| `in2code/powermail` | 14.0.3.9 |
| `in2code/powermail_cond` | dev-typo3-v14 |
| `studiomitte/friendlycaptcha` | 2.3.0.4 |
| `studiomitte/solr-numbered-pagination` | dev-main |
| `webconsulting/agent-nexus` | 4.1.2 |
| `webconsulting/agentation` | 1.5.2 |
| `webconsulting/astryx-typo3` | 2.6.1 |
| `webconsulting/desiderio` | 4.24.4 |
| `webconsulting/docx-editor` | 2.5.3 |
| `webconsulting/image-workbench` | 0.3.3 |
| `webconsulting/innesto` | 2.3.4 |
| `webconsulting/records-list-examples` | 2.0.2 |
| `webconsulting/records-list-types` | 2.0.2 |
| `webconsulting/site-package` | 14.3.6 |
| `webconsulting/skillflow` | 1.9.5 |
| `webconsulting/skillspector` | 1.2.3 |
| `webconsulting/typo3-abilities` | 1.3.2 |
| `webconsulting/typo3-ai-assistant` | 2.0.5 |
| `webconsulting/typo3-llms-txt` | 1.2.2 |
| `webconsulting/typo3-poppy` | 0.2.3 |
| `webconsulting/typo3-x402-paywall` | 1.4.2 |
| `webconsulting/visual-editor-enhancements` | 1.4.3 |
| `webconsulting/webcon-easy-workspace` | 1.11.2 |
| `webconsulting/webcon-jev` | 0.3.1 |
| `webconsulting/webcon-templates` | dev-main (`89fe518`) |
| `webconsulting/workos-auth` | 2.3.5 |

Branch selections are intentional: Powermail Conditional uses the maintained
TYPO3 14 branch, and Solr pagination uses the maintained fork's main branch.
Their exact commits are recorded in `composer.lock`. The site package is a
local path package maintained in this repository.

## Fixes and verification

- Powermail 14 and Conditional were checked against the current upstream 13.x
  tips. The prior backports already include the relevant fixes; none were
  missing in this pass. Powermail 14.0.3.9 rebuilds its frontend with current
  compatible tools while preserving all 581 discovered unit cases.
- Friendly Captcha uses SDK 1.1.1. Its shipped bundle passes the browser
  lifecycle check. Version 2.3.0.4 removes an obsolete Composer test callback,
  verified against actual Testing Framework 8 and 9 and all supported PHP/TYPO3
  CI combinations. Both site SDK URLs point at the new bundled filename.
- Astryx imports upstream 0.7.4, regenerates its assets, and fixes accent text
  contrast on muted surfaces. All 2,350 audited color pairs pass.
- HugeIcons is current at 4.3.5: 6,782 mappings span two append-only font shards.
  The 6,156 frozen legacy mappings and raster compatibility checks pass. The
  retained 4.2.0 font is a compatibility test reference, not an active pin.
- Agent Nexus 4.1.2 updates Archify to stable 3.2.0 and regenerates all five
  protocol viewers. Thirty browser embed cases cover both screen sizes and
  themes; full viewer controls and SVG/PNG downloads pass. The lazy iframe
  load race has three regression cases. Python lint uses 3.15.1 and current
  pinned dependencies.
- Skillflow and MCP Server Fractor 1.1 tooling uses a maintained, tested
  contracts package for the upstream abandoned transitive dependency. Composer audits fail on
  abandoned packages and pass for both the root and embedded package graph.
- Root Agentation MCP tools use patched Zod 4.6.7 through an override. A real
  stdio MCP session verifies tool discovery, forwarding and invalid-input
  rejection. Node's native SQLite binding is also checked on the current ABI.
- Extension release CI covers actual PHP 8.4 and 8.5, plus older supported
  versions where applicable. The project quality suite passes Composer audit,
  PHP lint, YAML parsing, PHPStan level 8, 124 unit tests / 25,042 assertions,
  frontend build, npm audit and deployment pin checks.

Symfony remains on the latest compatible 7.4 release because TYPO3 Core 14.3
requires that series. Doctrine DBAL 4.5 is also excluded by Core's `~4.4.4`
constraint; the latest compatible DBAL 4.4.5 is installed. Existing Powermail
mock guidance and Solr deprecation
notices remain visible in passing upstream tests. Vault's historical audit
records and encryption keys are unchanged; recovering the original historical
key is a separate task.

## Integration limits

The private Templates extension is included at the tested commit `89fe518`.
The existing Composer deploy key is enabled on its private GitLab repository
with read-only access after explicit approval. Installation registers its
Content Blocks; individual template sites remain opt-in. The local theme work
and untracked site configurations are preserved separately. The schema rehearsal
uses a schema-only production copy: two new record tables and the two new
`tt_content` columns `webcon_items` / `webcon_facts`, with no changes to
existing field definitions. The deployment applies these additive changes.

Live browser acceptance passes 61 cases with one intentional skip. The local
DDEV full run passes 57 cases, skips three and times out in the two Poppy
playground cases at their unchanged five-second deadline. Both cases pass on
live. The local PHP 8.5.12 web container currently uses QEMU because the native
ARM package repository still offers 8.5.11; concurrent theme seed/import jobs
from other chats also occupy local PHP. Their processes and test assertions
were left unchanged.
