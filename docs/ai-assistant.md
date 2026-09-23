# shadcn UI and backend chat

The backend chat of this lab is
[`webconsulting/typo3-shadcn-ui`](https://github.com/dirnbauer/typo3-shadcn-ui).
It replaced two earlier chats, the Webconsulting fork `typo3-ai-chat` and the
upstream `hn/typo3-agent`, and it is also the base other backend modules build
on: React 19, Tailwind v4 and shadcn/ui inside a Shadow DOM, loaded once through
TYPO3's import map.

## Where it appears

| Surface | Where |
|---|---|
| AI Chat module | **Admin Tools → AI Chat** |
| Components module | **Admin Tools → shadcn/ui Components**, every shipped component in a real module |
| Floating panel | Toolbar button; survives module navigation |
| Chat rail | Left side of every module built on the base |

## How a turn works

The chat runs on nr-llm's agent runtime, so provider, model, budget and
guardrails are configured there, not here. It calls the MCP tools of
[`hn/typo3-mcp-server`](https://github.com/dirnbauer/typo3-mcp-server) in
process, as the signed-in backend user, so page permissions, workspaces and the
capability manifest apply exactly as they do for an external MCP client.

Reads run. A write stops the turn and shows a card naming the tool and its
arguments; nothing changes until it is approved. The model may ask one
clarifying question of its own, and everything it changed is listed behind
**Changes** in the conversation header.

## Configuration in this lab

Extension configuration `shadcn_ui` keeps its defaults:

| Key | Value here | Meaning |
|---|---|---|
| `llmConfiguration` | `backend-assistant` | nr-llm configuration the chat runs under |
| `allowWrites` | `0` | Every write asks, even with auto-approve switched on |
| `maxTurnsPerHour` | `60` | Per backend user |
| `attachmentStorage` | `1:/shadcn_ui/` | FAL folder for attachments |
| `panelEnabled` | `1` | Toolbar panel offered |

The chat's own tool is `ask_user`, in nr-llm tool group `shadcn_ui`; enable that
group in nr-llm's Tools module next to the `typo3_*` groups the assistant should
reach. A backend group can be narrowed further in user TSconfig, which
intersects with nr-llm's policy and can never widen it:

```typoscript
tx_shadcnui.tools.deny = typo3_WriteTable, typo3_SafeCli
```

Instruction records, created as ordinary records at root level, are merged into
every conversation's system prompt and can be limited to backend groups.

## Stuck conversations and retention

A turn runs inside the request that started it, so a request that dies leaves
its conversation claimed. One command releases those and applies retention:

```bash
ddev typo3 shadcn-ui:chat:cleanup --archive-after=30 --delete-after=90
```

The deployed container has no cron daemon, so its entrypoint runs the same
command on every start, when nothing can be mid-turn. Set
`TYPO3_CHAT_CLEANUP=0` in Coolify to skip it.

## When the chat says it is unavailable

Open the **Setup** tab of the chat's activity rail first: it names the resolved nr-llm
configuration, the tools the current user can reach, and anything missing. The
usual causes are an nr-llm configuration identifier that does not exist, a
provider without a usable key in the vault, or every tool group disabled.

## Building a module on the base

One PHP controller returns
`ShadcnModuleRenderer::render($request, new ShadcnApp(...))`, and one TSX file
registers the app with `defineShadcnApp()`. Build the TSX with `react`,
`react-dom` and `@webconsulting/shadcn-ui/runtime.js` marked external, so the
module shares the runtime's single copy of React. The Components module is
built exactly this way and is the reference; the extension's
`Documentation/Developer.rst` has the full example.
