# AI Chat (Netresearch, backend)

The backend chat of this lab is the **AI Chat by
[Netresearch DTT GmbH](https://www.netresearch.de/)**:
[`netresearch/nr-mcp-agent`](https://github.com/netresearch/t3x-nr-mcp-agent)
(extension key `nr_mcp_agent`), on Netresearch's
[`nr-llm`](https://github.com/netresearch/t3x-nr-llm) agent runtime and
[`nr-vault`](https://github.com/netresearch/t3x-nr-vault) for the provider key.
It replaced our own `webconsulting/typo3-ai-assistant` on 2026-11-20. That
extension had grown out of nr-mcp-agent in the first place; the original is
now maintained far beyond it, so the lab runs the original. Cowriter
([`netresearch/t3-cowriter`](https://github.com/netresearch/t3x-cowriter)) adds
the same stack to the rich text editor.

## Where it appears

| Surface | Where |
|---|---|
| Overview | **AI → AI Chat** (`nr_mcp_agent_chat`): setup state, recent conversations, missing alternative texts |
| Full-page chat | **AI → AI Chat → conversation** (`/typo3/module/nr/mcp/agent/chat/conversation`) |
| Panel | Toolbar button; the panel floats, docks beside the page tree or pops out into its own window |
| Dashboard | AI Chat, Prompt, Recent chats, Quick tasks and Improve-a-page widgets |
| Rich text | Cowriter buttons (Cowriter, image description, translate, task templates) in the Desiderio preset and TYPO3's default preset |

The old addresses `/typo3/module/tools/ai-assistant/…` are gone. TYPO3 14.3
can give a module old identifiers (`aliases` in the module's own
`Configuration/Backend/Modules.php`), but only the extension that registers the
module can declare them, and an alias maps identifiers, not URL paths. Without
patching nr-mcp-agent there is no supported way, and only administrators used
the old module, so there is no redirect.

## How a turn works

A message starts a run of nr-llm's agent loop with the task the chat is
configured for. The model calls nr-llm's built-in tools as the signed-in
backend user, so page permissions, workspaces and language rights apply as they
do in the backend. Reading tools run directly. Every call of a writing tool
stops the run and shows an approval card in the chat; nothing changes until a
person approves it, and nr-llm has no switch to skip that. The chat lists what a
conversation changed.

The chat uses nr-llm's built-in tools only. The tools of our MCP server
(`hn/typo3-mcp-server`) stay available to external MCP clients; they are not
bridged into this chat.

## What `sitepackage:seed-ai-stack` sets

Everything the chat needs that lives in the database comes from one idempotent
command in `site_package`, so local and production end up identical:

```bash
ddev exec vendor/bin/typo3 sitepackage:seed-ai-stack --dry-run   # lists the changes
ddev exec vendor/bin/typo3 sitepackage:seed-ai-stack             # writes them
ddev exec vendor/bin/typo3 sitepackage:seed-ai-stack             # reports "0 changes"
```

It finds every record by its identifier, never by uid, writes through
DataHandler (nr-llm's own repositories for the tool switches), and writes only
fields that differ.

| What | Value |
|---|---|
| nr-llm configuration `backend-ai-chat` | The lab's tool model `gpt-5-mini` (found by identifier; without it the default model if it can call tools, else the first active tool-capable one). The default GPT-5.6 Terra cannot: OpenAI refuses function tools with `reasoning_effort` on Chat Completions for it; temperature 0.3 (dropped by nr-llm for reasoning models); 4096 tokens; an identity and tool-use system prompt adapted from [netresearch/typo3-demo](https://github.com/netresearch/typo3-demo) that names this lab |
| nr-llm task `backend-ai-chat` | Category system, points at that configuration; the chat runs on it |
| Cowriter tasks | `improve-text`, `summarize`, `extend`, `fix-grammar`, `translate-en`, `translate-de` (category content, on the existing `content-assistant` configuration, in this order), as in typo3-demo; t3-cowriter ships none |
| Reading tools | As nr-llm ships them (nearly all on), plus `ask_choice`, `highlight_record` and `check_page_metadata`. The `*_raw` readers and `send_fal_file` stay off |
| Writing tools | Only the editorial writers typo3-demo enables (drafts, metadata, alternative texts, file references, translations, moves, publish, delete, copy, redirects, cache, `fetch_external_url`), and each only while nr-llm still makes every call wait for approval. Any other writer is switched off. List: `packages/site_package/Classes/AiStack/ToolPolicy.php` |
| Tool groups | The group of every tool that should be on is switched on |
| Backend group `main` | Modules `netresearch_ai`, `nr_mcp_agent_chat`, `cowriter_status`, `nr_landingpage`, `nr_landingpage_workspace` and the option `tx_nrmcpagent:approve_own_changes` (approve the chat's changes in your own conversation), added to what the group has |
| `settings.php` | `EXTENSIONS/nr_mcp_agent/llmTaskUid` = uid of the task `backend-ai-chat` |

Groups are not managed in code in this lab, and the seed never creates one.
The old chat was open to every backend user through its toolbar panel and no
group held its module; `main` is the lab's one user-facing group (today only
administrators are in it, who need no grants). Add another group to
`AiStackSeeder::EDITOR_GROUPS` once real editor groups exist.

It needs the models and the `content-assistant` configuration of
`sitepackage:configure-ai-examples`; without them it stops before writing
anything.

### The task uid differs per database

nr-mcp-agent reads its task as a uid (`llmTaskUid`, or `groupTaskMapping`
uids); it has no identifier setting. The seed resolves the uid from the task's
identifier and writes it with TYPO3's own settings API (`ConfigurationManager`,
the file the backend's Extension Configuration writes), and only that one key.
Locally `config/system/settings.php` keeps it. In production the image builds
`settings.php` from `settings.php.example` on every deploy, so the container
start runs `sitepackage:seed-ai-stack --settings-only`, which reads the task by
identifier and writes the key again, nothing else. A value in
`additional.php` read from the database at bootstrap would cost a query on
every request and fail when the database is down; this costs one query per
container start.

## Processing: workers, in both environments

`processingStrategy` is `worker` (set in `packages/site_package/ext_localconf.php`,
so the backend's Extension Configuration cannot switch it back). The
extension's default, `exec`, starts `php vendor/bin/typo3 ai-chat:process`
from the web request with `PHP_BINARY`. Under the production image's mod_php
(`php:8.5.13-apache-trixie`) that constant is an empty string (measured
2026-11-20: SAPI `apache2handler`, `PHP_BINARY` `''`), so no turn would ever
start there. Under DDEV's php-fpm `exec` works, which is why it did locally.

| | Production (Coolify) | DDEV |
|---|---|---|
| Who keeps workers alive | `docker/coolify/entrypoint.sh` starts `TYPO3_CHAT_WORKERS` (default 2) loops of `docker/coolify/ai-chat-worker.sh` as `www-data`, after the schema update | a `post-start` hook in `.ddev/config.yaml` starts one loop of the same script in the background (`flock` on `var/lock/ai-chat-worker.lock` keeps it to one). Not `web_extra_daemons`: a change to those rebuilds the web image |
| Worker | `ai-chat:worker --max-turns=1 --time-limit=3600 --memory-limit=256 --turn-limit=600`, PHP `memory_limit=2G` (a boot on cold caches needs more than 1 GB); restarted after every turn, after an idle hour, above 256 MB; 30 s back-off after a quick failure | the same |
| Signals, turn limit | `pcntl` is installed in the image (`Dockerfile.coolify`) | DDEV's PHP has `pcntl` |
| No free worker | the extension falls back to `exec`, which cannot start under mod_php: the turn waits for the next free worker | the fallback works |
| Logs | container log (`docker logs`) | `var/log/ai-chat-worker.log` |

A booted, idle worker uses about 35 MB. A setting saved in the backend makes
every worker stop before its next turn and start again with it.

## Container start (production)

In this order, each with a switch in `docker-compose.coolify.yml`; a failure is
logged and never stops the start:

1. `extension:setup` (`TYPO3_RUN_SETUP`): nr-mcp-agent's tables, nr-llm's
   `tx_nrllm_ai_written` columns on `pages`/`tt_content` (without them pages and
   content cannot be saved).
2. Upgrade wizards by name (`TYPO3_RUN_WIZARDS`): `nrMcpAgent_migrateMessagesToTable`
   and `nrMcpAgent_lastMessageTime` for the conversations of the earlier
   nr-mcp-agent install, then `sitepackage:upgrade:mark-done
   'Netresearch\NrLlm\Updates\SkillVersionDigestUpdateWizard'`: nr-llm's
   `nrllmAdr214SkillVersionDigest` would disable every skill whose stored
   frontmatter does not verify, so it is marked done and never run, as in
   typo3-demo. Never run `upgrade:run` without a wizard name.
3. `sitepackage:seed-ai-stack --settings-only` (`TYPO3_CHAT_SETTINGS`).
4. `ai-chat:cleanup --delete-after-days=90` (`TYPO3_CHAT_CLEANUP`): fails
   conversations stuck for more than five minutes, archives conversations idle
   for `autoArchiveDays` (30), deletes archived ones after 90 days. The same
   retention the old chat had (`--archive-after=30 --delete-after=90`), and
   like the old chat's step it stays pinned off in `docker-compose.coolify.yml`
   (`'0'`, since 2026-10-15). Its first run would delete the archived
   conversations older than 90 days: in the rehearsal database it archived 101
   and deleted 104 conversations of the earlier nr-mcp-agent install from
   April to July. Switching it on (`${TYPO3_CHAT_CLEANUP:-1}`) is the owner's
   decision. Without it, a turn whose process is killed mid-run (a container
   stop) stays locked until the cleanup runs once.
5. The chat worker loops (`TYPO3_CHAT_WORKERS`, 0 switches them off).

The records themselves (step "seed-ai-stack" in
[content/seeding.md](content/seeding.md)) come from the production data run,
once, and again only when the command changes.

## Skin

The chat is a Lit application in Shadow DOM. Its colours, borders, surfaces,
fonts and focus rings come from inherited CSS custom properties (`--nr-chat-*`
and the `--typo3-*` tokens it reads), so a backend stylesheet can give it the
shadcn/ui look of the Desiderio frontend without touching its code or
templates. Radii, spacing and font sizes inside the shadow roots are not
reachable from outside. The lab's skin is CSS only; until it ships, the chat
uses TYPO3's backend tokens.

## When the chat says it is not configured

- "No nr-llm Task configured": `llmTaskUid` is 0. Run
  `sitepackage:seed-ai-stack` (or, in production after a deploy,
  `sitepackage:seed-ai-stack --settings-only`).
- "nr-llm Task with uid … not found": the database was replaced; run
  `--settings-only` again.
- A turn that never answers in production: no worker runs; the container log
  shows `entrypoint: starting AI chat worker loop` and each worker's start
  and stop. Locally the fallback answers anyway; `ddev exec pgrep -af
  ai-chat:worker` shows the worker, `var/log/ai-chat-worker.log` its output.
- Tools missing: AI → Tools shows each tool and group switch; the seed is the
  last word on the ones it manages.

## Removed with the old chat

`webconsulting/typo3-ai-assistant` (extension key `webcon_ai_assistant`) and its
repository entry are gone from `composer.json`; its upgrade wizard and its
cleanup command (`ai-assistant:chat:cleanup`) are gone from the entrypoint. Its
tables (`tx_webconaiassistant_*`, two conversations locally) and its cache
tables stay until a deliberate schema cleanup; the schema analyser lists them
for renaming to `zzz_deleted_*`.
