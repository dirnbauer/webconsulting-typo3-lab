# AI Assistant (backend chat)

The backend chat of this lab is
[`webconsulting/typo3-ai-assistant`](https://github.com/dirnbauer/typo3-ai-assistant)
(extension key `webcon_ai_assistant`). It succeeded `webconsulting/typo3-shadcn-ui`
on 2026-09-23: the same chat, rebuilt from TYPO3's own backend components (Fluid,
small Lit elements without Shadow DOM, core Modal/Notification/AJAX APIs) instead
of React, Tailwind and shadcn/ui. The shadcn/ui look stays where it belongs, in
the Desiderio frontend.

## Where it appears

| Surface | Where |
|---|---|
| Chat | **Admin Tools → AI Assistant → Chat** (`/module/tools/ai-assistant/chat`) |
| Instructions | **Admin Tools → AI Assistant → Instructions** (admins only), a record list edited through the normal record editor |
| Toolbar item | Popover in the backend toolbar; shows a badge while a turn waits for you |

Editors need module access to `tools_webconaiassistant` and
`tools_webconaiassistant_chat`.

## How a turn works

The chat runs on nr-llm's agent runtime, so provider, model, budget and
guardrails are configured there, not here. It calls the MCP tools of
[`hn/typo3-mcp-server`](https://github.com/dirnbauer/typo3-mcp-server) in
process, as the signed-in backend user, so page permissions, workspaces and the
capability manifest apply exactly as they do for an external MCP client.

Reads run. A write stops the turn and shows a card naming the tool and its
arguments; nothing changes until it is approved. The model may ask one
clarifying question of its own, and everything it changed is listed with the
conversation.

## Configuration in this lab

Extension configuration `webcon_ai_assistant` keeps its defaults:

| Key | Value here | Meaning |
|---|---|---|
| `llmConfiguration` | `backend-assistant` | nr-llm configuration the chat runs under |
| `allowWrites` | `0` | Every write asks, even with auto-approve switched on |
| `maxTurnsPerHour` | `60` | Per backend user |
| `attachmentStorage` | `1:/webcon_ai_assistant/` | FAL folder for new attachments |
| `panelEnabled` | `1` | Toolbar item offered |

The chat's own tool is `ask_user`, in nr-llm tool group `webcon_ai_assistant`;
enable that group in nr-llm's Tools module next to the `typo3_*` groups the
assistant should reach. A backend group can be narrowed further in user
TSconfig, which intersects with nr-llm's policy and can never widen it:

```typoscript
tx_webconaiassistant.tools.deny = typo3_WriteTable, typo3_SafeCli
```

Instruction records, created as ordinary records at root level, are merged into
every conversation's system prompt and can be limited to backend groups.

## Stuck conversations and retention

A turn runs inside the request that started it, so a request that dies leaves
its conversation claimed. One command releases those and applies retention:

```bash
ddev typo3 ai-assistant:chat:cleanup --archive-after=30 --delete-after=90
```

The deployed container has no cron daemon, so its entrypoint runs the same
command on every start, when nothing can be mid-turn. Set
`TYPO3_CHAT_CLEANUP=0` in Coolify to skip it.

## Moving from typo3-shadcn-ui

The upgrade wizard `webconAiAssistantMigrateFromShadcnUi` copies conversations,
messages and instructions from the `tx_shadcnui_*` tables, the `shadcn_ui`
extension configuration, module permissions, the `tx_shadcnui.tools` TSconfig,
bookmarks and the nr-llm tool-group switch. The deployed container runs it on
start (`TYPO3_RUN_WIZARDS=0` to skip); once it has run it is marked done. In the
local lab it ran on 2026-09-23, and the old tables were renamed to
`zzz_deleted_tx_shadcnui_*`.

## When the chat says it is unavailable

The usual causes are an nr-llm configuration identifier that does not exist, a
provider without a usable key in the vault, or every tool group disabled. The
chat names the resolved configuration and the tools the current user can reach.
