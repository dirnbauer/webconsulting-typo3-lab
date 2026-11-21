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
| nr-llm task `backend-ai-chat` | Category system, points at that configuration; the chat runs on it. Its prompt also says: when you answer in German, address the user with "Sie" (the task prompt reaches every conversation, also on a site with its own `nrllm.configuration`) |
| Cowriter tasks | `improve-text`, `summarize`, `extend`, `fix-grammar`, `translate-en`, `translate-de` (category content, on the existing `content-assistant` configuration, in this order), as in typo3-demo; t3-cowriter ships none. `translate-de` renders "you" as "Sie"; the others keep the address of the text they get |
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

## German wording: "Sie"

The lab's German copy addresses the reader with "Sie". nr-mcp-agent, nr-llm
and nr-landingpage ship German labels with "du" ("Wartet auf deine Freigabe",
"Wähle eine Seite …"). `site_package` overrides exactly those units with
TYPO3's label overrides (`$GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']`,
the v14 name of `locallangXMLOverride`), by file and by translation domain:
`Classes/Translation/FormalGermanLabels.php` lists the files, the units are in
`Resources/Private/Language/Overrides/de.nr_*.xlf`. The chat's web components
get their strings from `TYPO3.lang` (the backend hands them
`locallang_chat.xlf`), so the overrides reach them too; nothing is compiled
into the JavaScript. Changes need `cache:flush` (labels are cached).

`Tests/Unit/Translation/FormalGermanLabelsTest.php` reads every German label
the six Netresearch packages ship, applies the overrides and fails on an
informal address (canon.yaml's `address.sie` pattern, "dir", du-imperatives,
"Bitte jemanden …, …"). It also fails when an overridden unit disappears
upstream or its English source changes. After a Netresearch update, run the
unit tests: a new "du" label shows up there with its file and id.

The model's answers follow the task prompt (see the table above).

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

A booted, idle worker uses about 40 MB, but only with the CLI's OPcache file
cache (DDEV: `.ddev/php/performance.ini`; production image:
`docker/coolify/php-cli.ini`). Without it each boot compiles the ~250 MB TCA
schema file again and holds about 900 MB, above the worker's own
`--memory-limit=256`, so it ends after every poll. A setting saved in the
backend makes every worker stop before its next turn and start again with it.

## Container start (production)

In this order, each with a switch in `docker-compose.coolify.yml`; a failure is
logged and never stops the start:

1. `extension:setup` (`TYPO3_RUN_SETUP`): nr-mcp-agent's tables, nr-llm's
   `tx_nrllm_ai_written` columns on `pages`/`tt_content` (without them pages and
   content cannot be saved).
2. Upgrade wizards by name (`TYPO3_RUN_WIZARDS`): nr-llm's
   `nrLlm_stampProviderTrustZone`, `nrLlm_providerApiTimeout120` and
   `nrLlm_embeddingModelDimensions`, nr-mcp-agent's
   `nrMcpAgent_clearStoredApprovalNotice`, then
   `nrMcpAgent_migrateMessagesToTable` and, only when that succeeded,
   `nrMcpAgent_lastMessageTime` for the conversations of the earlier
   nr-mcp-agent install. All of them only fill or correct data. Not named:
   `nrLlm_dataClassEnforcementObserveForExisting` (it writes extension
   configuration into `settings.php`, which every image rebuilds) and the two
   dashboard widget wizards (they rearrange users' dashboards). Then
   `sitepackage:upgrade:mark-done
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
   April to July. Switching it on (`TYPO3_CHAT_CLEANUP=1` in Coolify) is the
   owner's decision.
5. `sitepackage:ai-chat:release-interrupted --before=<container start>`
   (`TYPO3_CHAT_RELEASE`, default 1): a container stop or an OOM kill never
   reaches a worker as SIGTERM (Apache is PID 1; when it exits, the kernel kills
   the rest), so a turn running at that moment keeps its conversation `locked`
   and the chat answers "conversation is processing" for good. This step marks
   `locked` and `tool_loop` rows last changed before the container started as
   `failed` (Retry button), as the cleanup's five-minute timeout would, and
   archives or deletes nothing. Queued turns (`processing`) stay for the
   workers.
6. The chat worker loops (`TYPO3_CHAT_WORKERS`, 0 switches them off).

The records themselves (step "seed-ai-stack" in
[content/seeding.md](content/seeding.md)) come from the production data run,
once, and again only when the command changes.

## Skin: shadcn/ui by CSS only

`packages/site_package/Resources/Public/Css/Backend/nr-chat-shadcn.css`,
registered in `packages/site_package/ext_localconf.php` as
`$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['site_package_nr_chat_shadcn']`.
TYPO3 adds every registered backend stylesheet to each backend document, so
the file reaches the module iframe (`<nr-chat-app>`) and the top frame with
the toolbar panel (`<ai-chat-panel>`). No JavaScript, no template override,
no patch: the extension stays as Netresearch ships it.

How it works: the chat is a Lit application in Shadow DOM without `::part()`
or slots. Its theme (`Resources/Public/JavaScript/theme.js`) declares about
forty `--nr-chat-*` colour properties on `:host`, derived from the backend's
`--typo3-*` tokens. A declaration on the host element from the outer document
beats the component's own `:host` rule, so the stylesheet replaces them on the
two hosts. It also sets the three `--typo3-*` tokens the shadow styles read
directly (font family, flyout shadow, the left rule of guided/proposal
blocks) and three inherited properties with no custom property behind them
(`scrollbar-color`, `accent-color`, font smoothing). Inter ships in
`Css/Backend/fonts/` (SIL OFL 1.1).

Values: shadcn/ui "new-york", base neutral, as Desiderio's default preset b0
(`EXT:desiderio/Resources/Public/Css/shadcn-theme.css`) including Desiderio's
AA adjustments (`--input` 0.63/0.61 as a 3:1 boundary, `--ring` 0.45/0.75,
`--muted-foreground` 0.542, `--destructive` 0.565/0.73). User bubble = primary
on primary-foreground, assistant bubble and tool traces = muted, sidebar and
composer bar = sidebar, the panel surface = popover. Success and warning keep
TYPO3's semantic tokens (shadcn has none); info is the neutral shadcn alert;
danger is destructive text on a 10% destructive tint.

Light and dark: every value is `light-dark()`. TYPO3 14.3 writes
`data-color-scheme="light|dark"` on `<html>` for an explicit user choice
(`color-scheme: only …`) and leaves it out for "auto", where
`:root { color-scheme: light dark }` follows the operating system.
`color-scheme` is inherited into the shadow roots, so the chat switches with
the backend in all three settings.

### What CSS cannot reach

Hard-coded inside the shadow roots, so they stay Netresearch's: radii (4, 6,
8, 12 and 20 px pills, circles), paddings and gaps, font sizes (11–14 px) and
weights, the monospace font of code and tool traces (`font-family: monospace`),
focus-ring width and offset, the browser's default focus ring on buttons (the
chat gives `.btn`, `.btn-primary` and `.btn-send` no focus style of its own),
button heights, icons, animations, the panel header gradient, the module's
host border (1 px, 4 px radius) and the panel's 12 px radius. Buttons use the
browser's own text colour (they do not inherit `color`). The sidebar keeps a
fixed 280 px width and does not collapse by itself on a phone. A panel popped
out into a Picture-in-Picture window is a new document without this
stylesheet and falls back to TYPO3's colours. Closer matches would need an
upstream theming API (`::part()`, more custom properties) or a fork.

### Contrast (measured)

Measured in lab-nr with Playwright on 2026-11-21: probe elements with the
chat's own class names in the real shadow root, computed colours composited
over their backgrounds, WCAG ratio. Text needs 4.5:1, boundaries and focus
indicators 3:1. "auto" with the OS in dark/light gives the same numbers as
the explicit dark/light setting. 0 failures in either scheme. (Netresearch's
stock theme on the lab's backend tokens fails five of these pairs, e.g. the
composer edge at 2.01:1.)

| Pair | Properties | Light | Ratio | Dark | Ratio | Needs |
|---|---|---|---|---|---|---|
| User bubble | on-accent / accent | #fafafa on #171717 | 17.18 | #171717 on #e5e5e5 | 14.23 | 4.5:1 |
| Assistant bubble | text / surface-high | #0a0a0a on #f5f5f5 | 18.16 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Link in assistant bubble | link / surface-high | #0a0a0a on #f5f5f5 | 18.16 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Quote in assistant bubble | text-variant / surface-high | #6f6f6f on #f5f5f5 | 4.61 | #a1a1a1 on #262626 | 5.86 | 4.5:1 |
| Message time | text-variant / surface | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #0a0a0a | 7.66 | 4.5:1 |
| Tool trace | text-variant / surface-base | #6f6f6f on #f5f5f5 | 4.61 | #a1a1a1 on #262626 | 5.86 | 4.5:1 |
| System line | text-variant / surface | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #0a0a0a | 7.66 | 4.5:1 |
| Empty state | text-variant / surface | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #0a0a0a | 7.66 | 4.5:1 |
| Sidebar row meta | text-variant / surface-low | #6f6f6f on #fafafa | 4.81 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Sidebar row title | text / surface-low | #0a0a0a on #fafafa | 18.97 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Active row | on-active / active | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Active row meta | text-variant / active | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Primary button | on-accent / accent | #fafafa on #171717 | 17.18 | #171717 on #e5e5e5 | 14.23 | 4.5:1 |
| Primary button hover | on-accent / accent-hover | #fafafa on #333333 | 12.1 | #171717 on #c7c7c7 | 10.61 | 4.5:1 |
| Outline button | text / surface | #000000 on #ffffff | 21 | #ffffff on #0a0a0a | 19.8 | 4.5:1 |
| Send button icon | on-accent / accent | #fafafa on #171717 | 17.18 | #171717 on #e5e5e5 | 14.23 | 4.5:1 |
| Approval header | info-text / info-bg | #0a0a0a on #f5f5f5 | 18.16 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Approval body | text / surface-low | #0a0a0a on #fafafa | 18.97 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Approval warning | status-warning / surface-low | #5b440b on #fafafa | 8.84 | #eabc48 on #171717 | 10.06 | 4.5:1 |
| Approval note | info-text / surface-low | #0a0a0a on #fafafa | 18.97 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Status neutral | neutral-text / neutral-bg | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Status info | info-text / info-bg | #0a0a0a on #f5f5f5 | 18.16 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Status warning | warning-text / warning-bg | #2e2205 on #faeed1 | 13.52 | #faeed1 on #2e2205 | 13.52 | 4.5:1 |
| Status danger | danger-text / danger-bg | #97162c on #ffebef | 7.45 | #ffa0a7 on #1d1416 | 9.31 | 4.5:1 |
| Status neutral in active row | neutral-text / neutral-bg | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Change badge | neutral-text / neutral-bg | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Change badge info | info-text / info-bg | #0a0a0a on #f5f5f5 | 18.16 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Change badge warning | warning-text / warning-bg | #2e2205 on #faeed1 | 13.52 | #faeed1 on #2e2205 | 13.52 | 4.5:1 |
| Change badge danger | danger-text / danger-bg | #97162c on #ffebef | 7.45 | #ffa0a7 on #1d1416 | 9.31 | 4.5:1 |
| Issues banner | warning-text / warning-bg | #2e2205 on #faeed1 | 13.52 | #faeed1 on #2e2205 | 13.52 | 4.5:1 |
| Changes view | text / surface-low | #0a0a0a on #fafafa | 18.97 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Changes note | text-variant / surface-low | #6f6f6f on #fafafa | 4.81 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Activity list | text / surface-low | #0a0a0a on #fafafa | 18.97 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Activity meta | text-variant / surface-low | #6f6f6f on #fafafa | 4.81 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Activity error | status-danger / surface-low | #dd0116 on #fafafa | 4.92 | #fe7877 on #171717 | 6.97 | 4.5:1 |
| Activity waiting | status-info / surface-low | #1c5c7d on #fafafa | 6.99 | #58adda on #171717 | 7.18 | 4.5:1 |
| Page chip | chip-page-text / chip-page-bg | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Element chip | chip-element-ink / chip-element-bg | #0a0a0a on #ffffff | 19.8 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Composer text | text / surface | #000000 on #ffffff | 21 | #ffffff on #0a0a0a | 19.8 | 4.5:1 |
| File badge | text / surface-low | #0a0a0a on #fafafa | 18.97 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Slash list | text / surface-high | #0a0a0a on #f5f5f5 | 18.16 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Slash list selected | on-active / active | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Guided read-only error | status-danger / surface | #dd0116 on #ffffff | 5.13 | #fe7877 on #0a0a0a | 7.7 | 4.5:1 |
| Outcome not applied | status-danger / surface | #dd0116 on #ffffff | 5.13 | #fe7877 on #0a0a0a | 7.7 | 4.5:1 |
| Outcome check | status-warning / surface | #5b440b on #ffffff | 9.22 | #eabc48 on #0a0a0a | 11.11 | 4.5:1 |
| Alt text saved | status-success / surface | #237651 on #ffffff | 5.55 | #3ac586 on #0a0a0a | 8.97 | 4.5:1 |
| Panel header title | text / surface-low | #0a0a0a on #ffffff | 19.8 | #fafafa on #0a0a0a | 18.97 | 4.5:1 |
| Panel sidebar row | text / surface-low | #0a0a0a on #ffffff | 19.8 | #fafafa on #0a0a0a | 18.97 | 4.5:1 |
| Panel active row | on-active / active | #0a0a0a on #ffffff | 19.8 | #fafafa on #0a0a0a | 18.97 | 4.5:1 |
| Panel tab | text-variant / surface-low | #000000 on #efefef | 18.26 | #ffffff on #6b6b6b | 5.33 | 4.5:1 |
| Panel active tab | text / surface | #000000 on #efefef | 18.26 | #ffffff on #6b6b6b | 5.33 | 4.5:1 |
| Panel column status | text-variant / surface | #0a0a0a on #ffffff | 19.8 | #fafafa on #0a0a0a | 18.97 | 4.5:1 |
| Panel status chip warning | warning-text / warning-bg | #0a0a0a on #ffffff | 19.8 | #fafafa on #0a0a0a | 18.97 | 4.5:1 |
| Panel status chip danger | danger-text / danger-bg | #0a0a0a on #ffffff | 19.8 | #fafafa on #0a0a0a | 18.97 | 4.5:1 |
| Panel column badge | on-accent / accent | #0a0a0a on #ffffff | 19.8 | #fafafa on #0a0a0a | 18.97 | 4.5:1 |
| Composer field edge | input-border / surface | #898989 on #fafafa | 3.35 | #838383 on #171717 | 4.73 | 3:1 |
| Focus ring on page | focus-ring / surface | #555555 on #ffffff | 7.46 | #aeaeae on #0a0a0a | 8.92 | 3:1 |
| Focus ring on sidebar | focus-ring / surface-low | #555555 on #fafafa | 7.14 | #aeaeae on #171717 | 8.08 | 3:1 |
| Focus ring on active row | focus-ring / active | #555555 on #f5f5f5 | 6.84 | #aeaeae on #262626 | 6.82 | 3:1 |
| Composer edge when focused | focus-ring / surface-low | #555555 on #ffffff | 7.46 | #aeaeae on #0a0a0a | 8.92 | 3:1 |
| Active row focus outline | on-active / active | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 3:1 |

In the toolbar panel the page surface is popover; the rows that differ from
the module (all pass):

| Pair (panel) | Properties | Light | Ratio | Dark | Ratio | Needs |
|---|---|---|---|---|---|---|
| Message time | text-variant / surface | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| System line | text-variant / surface | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Empty state | text-variant / surface | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Sidebar row meta | text-variant / surface-low | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Sidebar row title | text / surface-low | #0a0a0a on #ffffff | 19.8 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Active row | on-active / active | #0a0a0a on #ffffff | 19.8 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Active row meta | text-variant / active | #0a0a0a on #ffffff | 19.8 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Outline button | text / surface | #000000 on #ffffff | 21 | #ffffff on #171717 | 17.93 | 4.5:1 |
| Composer text | text / surface | #000000 on #ffffff | 21 | #ffffff on #171717 | 17.93 | 4.5:1 |
| Guided read-only error | status-danger / surface | #dd0116 on #ffffff | 5.13 | #fe7877 on #171717 | 6.97 | 4.5:1 |
| Outcome not applied | status-danger / surface | #dd0116 on #ffffff | 5.13 | #fe7877 on #171717 | 6.97 | 4.5:1 |
| Outcome check | status-warning / surface | #5b440b on #ffffff | 9.22 | #eabc48 on #171717 | 10.06 | 4.5:1 |
| Alt text saved | status-success / surface | #237651 on #ffffff | 5.55 | #3ac586 on #171717 | 8.12 | 4.5:1 |
| Panel header title | text / surface-low | #0a0a0a on #ffffff | 19.8 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Panel sidebar row | text / surface-low | #0a0a0a on #fafafa | 18.97 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Panel active row | on-active / active | #171717 on #f5f5f5 | 16.44 | #fafafa on #262626 | 14.5 | 4.5:1 |
| Panel tab | text-variant / surface-low | #6f6f6f on #fafafa | 4.81 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Panel active tab | text / surface | #0a0a0a on #ffffff | 19.8 | #fafafa on #171717 | 17.18 | 4.5:1 |
| Panel column status | text-variant / surface | #6f6f6f on #ffffff | 5.02 | #a1a1a1 on #171717 | 6.94 | 4.5:1 |
| Panel status chip warning | warning-text / warning-bg | #2e2205 on #faeed1 | 13.52 | #faeed1 on #2e2205 | 13.52 | 4.5:1 |
| Panel status chip danger | danger-text / danger-bg | #97162c on #ffebef | 7.45 | #ffa0a7 on #1d1416 | 9.31 | 4.5:1 |
| Panel column badge | on-accent / accent | #fafafa on #171717 | 17.18 | #171717 on #e5e5e5 | 14.23 | 4.5:1 |
| Composer field edge | input-border / surface | #898989 on #ffffff | 3.5 | #838383 on #171717 | 4.73 | 3:1 |
| Focus ring on page | focus-ring / surface | #555555 on #ffffff | 7.46 | #aeaeae on #171717 | 8.08 | 3:1 |
| Focus ring on sidebar | focus-ring / surface-low | #555555 on #ffffff | 7.46 | #aeaeae on #171717 | 8.08 | 3:1 |
| Focus ring on active row | focus-ring / active | #555555 on #ffffff | 7.46 | #aeaeae on #171717 | 8.08 | 3:1 |
| Composer edge when focused | focus-ring / surface-low | #555555 on #ffffff | 7.46 | #aeaeae on #171717 | 8.08 | 3:1 |
| Active row focus outline | on-active / active | #171717 on #ffffff | 17.93 | #fafafa on #171717 | 17.18 | 3:1 |

Guard: `Tests/E2E/nr-chat-shadcn-skin.spec.js` (needs
`PLAYWRIGHT_BACKEND_CREDENTIALS_FILE`, sends nothing to the chat).

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
