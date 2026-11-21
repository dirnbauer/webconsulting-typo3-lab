# The Netresearch talk on the Desiderio site

Sebastian Mendel (Netresearch DTT GmbH) gave the talk **“KI in TYPO3 – Probleme, Fragen und
Anforderungen”** at **MTUG – TYPO3 User Group München on 9 November 2026**. The lab shows it as
TYPO3 content below `/ai`, in English (our translation) with Netresearch’s original German on
`/de/`, and Chinese and Hungarian translated from our English.

- Original slides: <https://netresearch.github.io/modularer-ki-stack-page/vortraege/mtug-2026-11-09/>
  (one slide: `…/mtug-2026-11-09/#/<n>`)
- PDF: <https://netresearch.github.io/modularer-ki-stack-page/vortraege/mtug-2026-11-09/MTUG-KI-in-TYPO3.pdf>
- Repository of the slides and the stack page: <https://github.com/netresearch/modularer-ki-stack-page>
- Modular AI stack: <https://netresearch.github.io/modularer-ki-stack-page/> (English: `/en/`)

The content is lab-owned (site_package), not part of the Desiderio product.

## Pages

| Page | Slug | Slides | What is on it |
|---|---|---|---|
| Hub | `/ai/netresearch-talk` (first below `/ai`) | 1–2, 34 | title, attribution, thank-you, the six parts as cards, the closing takeaway as a quote, Netresearch’s extensions in this lab, source, start of part 1 |
| 1 Possibilities | `…/possibilities` | 3–9 | Cowriter, AI Search, Repurpose, backend agent, Landingpage, more use cases |
| 2 Questions | `…/questions-before-you-start` | 10–12 | the question and the four fields |
| 3 Answers and limits | `…/answers-and-limits` | 13–28 | the eight topics with an on-page table of contents (`#topic-1` … `#topic-8`) |
| 4 Agent paths | `…/agent-paths` | 29–31 | backend vs. own assistant (MCP), A2A |
| 5 Context and exchange | `…/context-and-exchange` | 32–35 | other foundations, four answers, open questions |
| Technical details | `…/technical-details` | 38–65 | the 27 backup slides in three accordions (Development, Integration, Decision, as in the talk’s reserve menu, `#group-1` … `#group-3`) |
| Sources and links | `…/sources-and-links` | 36–37 | the two reference slides, every other link of the talk grouped by purpose, a note on the screenshots |
| Modular AI stack | `…/modular-ai-stack` | – | Netresearch’s stack page (`content/en.yaml`, `content/de.yaml`): tasks, browser AI, the ten modules with an “In this lab” column, operating models, who decides what, limits, FAQ |

Translations keep the English slug (`/de/ai/netresearch-talk/…`).

## Attribution policy

The owner asked for a big reference (“gib groß Referenz hin”). Every one of the nine pages:

1. opens with its heading and, directly below, the **attribution banner**: a muted band with the
   talk title, “A talk by Sebastian Mendel, Netresearch DTT GmbH, at MTUG – TYPO3 User Group
   München on 9 November 2026”, the statement that the page is our English translation of the
   German original, and four cards linking the original slides, the PDF, the repository and the
   modular AI stack page;
2. ends with a **source line** that names the exact slide range and links the first and last slide
   (`#/N`), then the link to the next page.

The hub also carries the thank-you note: content, structure and the screenshot concept are
Netresearch’s; the English translation and the page layout are webconsulting’s; the opinions are
the speaker’s.

Rules for the copy:

- English pages use our translation (`netresearch-talk.en.json` in the talk-author scratchpad); the
  stack page uses Netresearch’s own English. German is always Netresearch’s original wording, taken
  verbatim from the slides and from `content/de.yaml`.
- Statements of the talk are never shortened, improved or merged with our text. Our own sentences
  (attribution, intros, “In this lab” notes, the screenshot notes) are recognisably ours.
- Where a faithful quotation breaks a hard copy limit, it is listed in
  `Resources/Private/Data/Content/netresearch-talk/quoted-copy.json` (read through `quoted_copy` in
  `canon.yaml`): exactly that record, field and text is exempt from the length rules (`limits`), and
  the three German originals with “euch/euren/eurer” from the form-of-address rule (`address`).
  Facts, banned words and spelling still apply. A few English slide titles and buttons got a
  tighter translation instead of an exemption (the German is unchanged).
- Netresearch’s name appears in text only. Their logo and brand design are not used.
- Two links of the talk are dead and left out (`/extensions/ai-search`, `/?id=10114`). Links into
  Netresearch’s demo backend (`typo3-demo.netresearch.de/typo3/…`) always say that a login is
  required.
- Licence: the slides repository declares no licence. Reproducing the talk relies on the owner’s
  decision; ask Netresearch (Sebastian Mendel) for written consent before the pages go public.

## Screenshots

No file from the slides repository is copied. Screenshot slots are declared in
`Resources/Private/Data/Content/netresearch-talk/image-slots.json` and are captured in **this
lab** from our own installation of the same Netresearch extensions (light mode), saved to
`packages/site_package/Resources/Public/Images/NetresearchTalk/<file>`. Until a file exists the
seeder seeds the element without it and says so. A file is imported by name once
(`1:/user_upload/netresearch-talk/`): to replace a capture later, give it a new name in the payload
and in `image-slots.json`.

| File | Extension | Slides | Must show |
|---|---|---|---|
| `cowriter-editor.png` | t3_cowriter | 4 | The rich text editor (CKEditor) on a text element in this lab, Cowriter’s AI tools visible in the toolbar |
| `cowriter-seo.png` | t3_cowriter | 4 | Page properties with Cowriter’s suggestions for the SEO title open, several to choose from |
| `agent-chat.png` | nr_mcp_agent | 7 | The AI chat in this lab’s backend: a request for a page draft and the agent’s reply |
| `landingpage-backend.png` | nr_landingpage | 8 | The page properties (SEO fields) of a draft nr-landingpage generated from a briefing; visibly fictional demo values |
| `landingpage-preview.png` | nr_landingpage | 8 | The frontend preview (top section) of the same draft |
| `llm-configurations.png` | nr_llm | 15, 16 | The nr-llm configurations module: named configurations with model and parameters, no API key visible |
| `llm-analytics.png` | nr_llm | 22 | The nr-llm cost overview: requests, token usage and estimated costs over a period |
| `approval-card.png` | nr_mcp_agent | 26 | An open approval card in the AI chat for a planned page change (e.g. an SEO title) |
| `llm-tools.png` | nr_llm | 26, 28 | The nr-llm tools module: tool groups and single tools with their enabled state |
| `tool-trace.png` | nr_mcp_agent | 27 | The AI chat with the activity (tool trace) expanded: tool names, arguments and results |

AI Search and Repurpose are not installed here: their sections have no image and link the original
slide instead.

## Files

`packages/site_package/`:

- `Resources/Private/Data/Content/netresearch-talk/`
  - `00-hub.payload.json` … `08-modular-ai-stack.payload.json`, `09-hub-cards.payload.json`: one
    content payload per page (format: `docs/content/payload-brief.md`). Element markers in
    `rowDescription` are `nrtalk:<page>:<name>`; the cards are `nrtalk:hub-ai` and
    `nrtalk:hub-resources`. `pid` 0 is `/ai`; `{{page:/slug|/fallback}}` is a link to an existing
    page (first slug that exists); `{{labstatus:<id>}}` is a cell of the module table.
  - `lab-status.json`: the “In this lab” column. Installed: nr-llm, nr-vault, nr-mcp-agent,
    t3-cowriter, nr-landingpage, nr-browser-ai. Not installed, each with its reason (our
    installation’s choice, not a judgement of the module): nr-repurpose (needs ffmpeg and Chromium
    in the production image), nr-passkeys-be and nr-passkeys-fe (they add a passkey button to the
    production login by default), nr-http-guard (alpha; blocks outgoing HTTP by default).
    nr-ai-search: not publicly available. The hub text “Netresearch’s extensions in this lab”
    (`nrtalk:hub:lab` in `00-hub.payload.json`) says the same; change both together.
  - `image-slots.json`, `quoted-copy.json`: see above.
- `Classes/Command/SeedNetresearchTalkCommand.php` (`sitepackage:seed-netresearch-talk`),
  `Classes/NetresearchTalk/TalkPayload.php` (reads and resolves the payload),
  `Classes/NetresearchTalk/TalkOrdering.php` (what to move, without the database).
- Tests: `Tests/Unit/NetresearchTalk/*`, `Tests/Unit/Command/SeedNetresearchTalkCommandTest.php`.
- Translations: fragments `Resources/Private/Data/Translations/desiderio/<lang>.d/netresearch-talk.json`
  (de, zh, hu), merged with the shared memory by `TranslationMemory::fromFiles()`; a fragment that
  translates a source text differently than the shared memory stops the translate command.

## Payload rules learnt from the first database run (2026-11-21, lab-nr)

These hold for every edit of the payload and the fragments:

1. **Rich text in its stored form.** TYPO3’s RTE transformation stores a rich-text value that
   contains `<br>` with every `<br>` written as `<br />` and CRLF (`\r\n`) between its top-level
   blocks (`</p>\r\n<p>`, `</h3>\r\n<blockquote>` …); values without `<br>` are stored as they
   are. The payload holds that stored form (23 fields in 03, 04 and 06), otherwise every seed
   reports them as updated. The memory key of such a text is the stored English text, and its
   de/zh/hu values follow the same rule (the translate command writes translations without the
   RTE transformation, so the value is stored exactly as the fragment has it). The rule is
   applied by `packages/site_package/Build/Scripts/netresearch-talk-rte-form.py <worktree>`
   (`--check <stored.jsonl>` proves it against values exported from the database as JSON lines
   `{table, uid, field, value}`; `--apply` rewrites the payload and all three fragments in one
   go, matching keys with their `t3://page?uid=` links normalised; idempotent). Re-run it after adding
   rich text with `<br>`; then `sitepackage:seed-netresearch-talk` must report `0 updated`.
2. **Collection items are matched by position.** Each item’s `match` is
   `{pid, foreign_table_parent_uid, [tablenames, fieldname,] "_position": n, sys_language_uid: 0}`
   with `n` = its place in the parent’s `_collections` list (`TalkPayloadTest` checks it).
   Never match on `sorting`: DataHandler renumbers the items’ sorting when the translate command
   localizes them (1, 9730, 15875 …), and a sorting match then created all 278 items again.
3. **Translations keep the English slug.** `sitepackage:content:translate` gives a new page
   translation its source’s slug and tells EXT:redirects to stay out (correlation id with the
   redirects slug-update aspect, `SiteTranslator::correlationId()`). Before that fix the first
   translate run created 22 redirects, rewrote the sub-pages’ slugs to title-based ones
   (`/ai/netresearch-talk/questions-about-using-ai` …) and crashed in the CLI while EXT:redirects
   stored a flash message (“Call to a member function set() on null”).
4. **A key is the exact stored English text,** links included; `t3://page?uid=N` in a key may
   carry any uid (the lookup ignores uids), so the lab’s uids in the fragments are fine on
   production.

5. **Layout.** Every talk page sets `tx_desiderio_h1_sronly`: the page title stays the one `h1`
   (screen-reader only, as on the other `/ai` pages) and the header-page element right below is the
   visible heading, directly followed by the attribution band. The hub’s thank-you is centred and
   “Netresearch’s extensions in this lab” is a two-column element (installed | not installed with
   the reasons) on a muted band, so no row ends half empty. Elements without a screenshot (AI
   Search, Repurpose, and the slots until the captures exist) show their text column only.

## Running it (lab)

After `desiderio:styleguide:seed` and `sitepackage:films:seed` (a full styleguide seed removes the
two cards from `/ai` and `/resources`; the talk pages themselves survive it). Every writing command
runs alone: never next to another seeder or translate run.

```bash
ddev exec php packages/site_package/Build/Scripts/lint-copy.php --site=desiderio packages/site_package/Resources/Private/Data/Content/netresearch-talk
ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk --dry-run
ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk
ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk     # second run: 0 updated, 0 created, 0 linked, 0 moves
# The hub by slug below /ai of site 505, and the two pages that carry a card
HUB=$(ddev mysql -N -e "SELECT p.uid FROM pages p JOIN pages a ON a.uid=p.pid WHERE p.slug='/ai/netresearch-talk' AND a.slug='/ai' AND a.pid=505 AND p.sys_language_uid=0 AND p.deleted=0 AND p.t3ver_wsid=0")
CARDS=$(ddev mysql -N -e "SELECT GROUP_CONCAT(DISTINCT pid) FROM tt_content WHERE rowDescription IN ('nrtalk:hub-ai','nrtalk:hub-resources') AND sys_language_uid=0 AND deleted=0 AND t3ver_wsid=0")
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$HUB --recursive --dry-run --missing=- --fail-on-missing
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$HUB --recursive --fail-on-missing
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$CARDS --fail-on-missing
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$HUB --recursive --dry-run   # 0 / 0 everywhere
ddev exec vendor/bin/typo3 cache:flush
```

`--pages=$CARDS` has no `--recursive` on purpose: it translates `/ai` and `/resources` themselves
(their new card), not every page below them.

Measured on the rehearsal instance (lab-nr, 2026-11-21, from an empty talk, one run after the
other): lint 1 s, seed dry run 1 s, seed 46 s (460 records, 71 collections, 128 moves), second
seed 1 s (0 changes), translate dry run 4 s, translate of the nine pages in de, zh and hu 233 s
(27 page translations, 354 element translations, about 2,280 fields), translate of `/ai` and
`/resources` 5 s, both checks 9 s, seed after translate 1 s (0 changes), cache flush 11 s:
**about 5 minutes in all**.

## Production runbook

Production answers 401 to the public (basic auth in front of the whole site), so the pages are
not public after the run either. The data run belongs into the release that deploys this code
(lab commit with `SeedNetresearchTalkCommand`, the payload and the fragments); prepare it as a
runner folder `var/prod-netresearch-talk/` (template: `MAIN/var/prod-wp12-astryx-de/`) and start
it with `sh var/prod-run.sh var/prod-netresearch-talk` once the deploy is healthy. The runner
refuses to start while another seeder, translate or reindex runs on production; never start a
second one by hand.

Steps of `run.sh`, each `t3 <command>` as www-data in the web container with
`php -d memory_limit=3G vendor/bin/typo3 … --allow-production`, timed per step:

| # | Command | Lab | Production (≈ 4 × lab) |
|---|---|---|---|
| 0 | database backup of the touched tables (runner default) | – | ≈ 2 min |
| 1 | `sitepackage:seed-netresearch-talk --allow-production --dry-run` (must list `460 created` on the first run, `0 skipped`) | 1 s | 5 s |
| 2 | `sitepackage:seed-netresearch-talk --allow-production` | 46 s | ≈ 3 min |
| 3 | `sitepackage:seed-netresearch-talk --allow-production` (second run: `0 updated, 0 created`, `0 collection(s) linked, 0 move(s)`; anything else stops the run) | 1 s | 10 s |
| 4 | `HUB` and `CARDS` as above, with the runner’s SQL helper; check that `HUB` is one uid and `CARDS` two | – | – |
| 5 | `sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$HUB --recursive --dry-run --fail-on-missing --allow-production` | 5 s | 20 s |
| 6a–c | `sitepackage:content:translate --site=desiderio --languages=de --pages=$HUB --recursive --fail-on-missing --allow-production`, then the same with `zh`, then `hu` (one language per step, so a step can be resumed with `FROM=`) | 233 s together | ≈ 5 min each, ≈ 15 min together |
| 7 | `sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$CARDS --fail-on-missing --allow-production` | 5 s | 30 s |
| 8 | the dry run of 5 again, plus `--pages=$CARDS`: every count 0 | 9 s | 40 s |
| 9 | `cache:flush` | 11 s | ≈ 1 min |
| 10 | verify (below) | – | 1–2 min |

Expected: **about 22–25 minutes including backup and verification**, inside the 30-minute limit
for a production data run. The talk has no Solr step of its own: reindex `/ai` later with the
next planned `sitepackage:solr:reindex --site=505 --index` (translate switches the record monitor
off, so the new pages are not in the search before that).

**Verify** (`verify.php` of the runner, or by hand with the basic-auth credentials from
1Password, never in a public channel):

- in the database: 9 default pages below `/ai` with the slugs of the table above, 9 translations
  each in languages 1, 2 and 3 with the same slugs, no `sys_redirect` row created by the run
  (`createdon` ≥ the run’s start), 118 default `tt_content` rows on the nine pages and 118 per
  language, one `nrtalk:hub-ai` card on `/ai` and one `nrtalk:hub-resources` card on `/resources`
  of site 505;
- over HTTP (inside the container, `curl -s -o /dev/null -w '%{http_code}'` against
  `http://localhost/…` with the site’s host header): all nine pages in `/`, `/de/`, `/zh/`, `/hu/`
  answer 200 and contain exactly one `<h1`; `/ai/` and `/resources/` contain one link to
  `/ai/netresearch-talk/` in each language;
- the runner log ends with `ALL-DONE`.

**Roll back** (no data loss, seconds): hide the hub page — page properties of
`/ai/netresearch-talk` → “Page visible” off (or `sitepackage:content:apply` with a one-record
payload `{"table":"pages","uid":<HUB>,"action":"hide"}`), then hide the two cards
(`nrtalk:hub-ai`, `nrtalk:hub-resources`) the same way and `cache:flush`. Hiding the default page
hides its translations; the sub-pages are below a hidden page and drop out of the menu. Showing it
again later: unhide, or simply run the seeder again (it writes `hidden: 0`). A full removal is a
DataHandler delete of the hub page (with its sub-pages) and of the two cards; the runner’s
backup restores the touched tables if anything else went wrong.

**Later changes** (a payload text, `lab-status.json`, a new screenshot): rerun steps 1–3, then
translate only the page that changed (`--pages=<uid>` without `--recursive`, one language per
step); that takes under a minute in the lab.
