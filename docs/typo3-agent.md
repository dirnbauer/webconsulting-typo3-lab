# TYPO3 Agent

The backend chatbot is the upstream [`hn/typo3-agent`](https://github.com/hauptsacheNet/typo3-agent)
extension, published as [`agent` on TER](https://extensions.typo3.org/extension/agent).
It replaces `webconsulting/typo3-ai-chat`; the old Composer repository,
package, extension configuration, and setup integration have been removed.
Historical chat tables are retained so existing conversations can be recovered
from the database or the pre-migration snapshot.

Open **Content > AI Tasks** (German: **KI-Aufgaben**) in the backend and select
a page to start a chat. The extension calls the installed MCP ToolRegistry
directly. Its configuration is independent of nr-llm, which remains installed
for Cowriter and the other lab integrations.

## Model configuration

The lab uses the existing OpenAI provider and `gpt-5-mini`. Coolify supplies:

| Variable | Purpose |
|---|---|
| `TYPO3_AGENT_API_KEY` | OpenAI credential, stored only in Coolify environment configuration |
| `TYPO3_AGENT_API_URL` | OpenAI-compatible endpoint; defaults to `https://api.openai.com/v1/` |
| `TYPO3_AGENT_MODEL` | Model identifier; defaults to `gpt-5-mini` |

`config/system/settings.php.example` maps these variables to `EXTENSIONS.agent`.
Local settings live in ignored `config/system/settings.php`. Never commit the
API key. Changing Coolify variables requires recreating the web container or
deploying the application.

The upstream `reasoningEffort` and `webFetch` settings send OpenRouter-specific
request fields. They are disabled for the direct OpenAI endpoint.

## Spreadsheet compatibility

Agent 0.0.3 declares PhpSpreadsheet `^3.10`, while the installed Powermail
requires `^5.0`. A scoped package repository in the root `composer.json` adds
PhpSpreadsheet 5 support to the dependency metadata for Agent 0.0.3 and its
PhpPresentation 1.2.0 dependency. Both use their original upstream distributions
and Git commits; their code is unmodified, and the existing PhpSpreadsheet
5.9.0 implementation remains installed.

`AgentSpreadsheetCompatibilityTest` exercises the upstream Agent extractor with
real XLSX, ODS, CSV, and PPTX files against the installed libraries. Remove this metadata override
when an upstream Agent release permits PhpSpreadsheet 5 directly. Recheck these
tests when changing these packages.

## OpenAI tool schemas

The lab MCP server has tools with root-level schema alternatives, such as
`GetPage` accepting a UID or a URL. OpenAI rejects these at the root of a
function's parameters. The site's `AgentToolConverterService` nests the complete
original schema under `arguments` and unwraps it before native tool execution.
It also serializes empty schema maps as JSON objects, including tools without
parameters, while preserving array-valued defaults and enums.
Validation constraints and MCP access checks are preserved. This is registered
as a TYPO3 service alias; no upstream Agent or MCP file is modified.

```bash
ddev exec vendor/bin/phpunit --configuration=Build/UnitTests.xml \
  --filter AgentSpreadsheetCompatibilityTest
ddev exec vendor/bin/typo3 extension:setup --extension=agent
```
