# A2UI Integration (a2ui_integration)

Local path package: [`packages/a2ui_integration`](../packages/a2ui_integration/) ·
GitHub: [dirnbauer/typo3-a2ui-integration](https://github.com/dirnbauer/typo3-a2ui-integration) ·
package README: [../packages/a2ui_integration/README.md](../packages/a2ui_integration/README.md)

A2UI Protocol **v1.0** for TYPO3 v14: an AI agent describes a UI as declarative
JSON (a *surface*) and a trusted client renders it with native components. Drives
generation through `netresearch/nr-llm` (vault-backed OpenAI) with a deterministic
offline fallback.

## Where it is used in this lab

| Surface | Location | Details |
|---|---|---|
| **Backend playground** | **Web → A2UI Playground** (module `web_a2ui`) | Generate → render → inspect JSON → bidirectional loop; cost panel (today + last 3 months). |
| **Frontend Smart Inquiry plugin** | **desiderio** site → **Contact** page | **Page uid 747**, slug `/contact`. Content element `a2ui_inquiry` (content uid **36764**, colPos 0, default language). |

**Frontend demo URL:** <https://webconsulting-typo3-lab.ddev.site/desiderio-corporate-starter/contact>

> The frontend instance is a `tt_content` record created for the demo (CType
> `a2ui_inquiry` on page 747); it is **not** in version control, so a fresh
> `dump.sql.gz` import would not include it. Re-add the element on any page to
> reproduce.

## Tables

| Table | Purpose |
|---|---|
| `tx_a2uiintegration_usage` | LLM spend ledger (backend module + frontend plugin); feeds the cost panel. |
| `tx_a2uiintegration_inquiry` | Submissions from the frontend Smart Inquiry form. |

## Notes

- Real LLM on the **frontend** requires the nr-vault secret to be marked
  `frontend_accessible` (otherwise the plugin uses the deterministic generator).
  Enabled in this lab on the OpenAI key secret.
- Full usage and A2UI v1.0 conformance details:
  [package README](../packages/a2ui_integration/README.md) and
  [Documentation/Index.rst](../packages/a2ui_integration/Documentation/Index.rst).
