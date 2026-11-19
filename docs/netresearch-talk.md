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
  - `lab-status.json`: the “In this lab” column (installed, decided later, not publicly available).
  - `image-slots.json`, `quoted-copy.json`: see above.
- `Classes/Command/SeedNetresearchTalkCommand.php` (`sitepackage:seed-netresearch-talk`),
  `Classes/NetresearchTalk/TalkPayload.php` (reads and resolves the payload),
  `Classes/NetresearchTalk/TalkOrdering.php` (what to move, without the database).
- Tests: `Tests/Unit/NetresearchTalk/*`, `Tests/Unit/Command/SeedNetresearchTalkCommandTest.php`.
- Translations: fragments `Resources/Private/Data/Translations/desiderio/<lang>.d/netresearch-talk.json`
  (de, zh, hu), merged with the shared memory by `TranslationMemory::fromFiles()`; a fragment that
  translates a source text differently than the shared memory stops the translate command.

## Running it

After `desiderio:styleguide:seed` and `sitepackage:films:seed` (a full styleguide seed removes the
two cards from `/ai` and `/resources`; the talk pages themselves survive it):

```bash
ddev exec php packages/site_package/Build/Scripts/lint-copy.php --site=desiderio packages/site_package/Resources/Private/Data/Content/netresearch-talk
ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk --dry-run
ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk
ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk     # second run: 0 changes, 0 moves
UIDS=$(ddev mysql -N -e "SELECT uid FROM pages WHERE (slug LIKE '/ai/netresearch-talk%' OR slug IN ('/ai','/resources')) AND sys_language_uid=0 AND deleted=0 AND t3ver_wsid=0" | paste -sd, -)
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$UIDS --dry-run --missing=-
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de,zh,hu --pages=$UIDS --fail-on-missing
ddev exec vendor/bin/typo3 cache:flush
```

Check that the `UIDS` query only returns pages below 505 (`/resources` exists in other sites too).
On production add `--allow-production` and run it from a runner (`var/prod-run.sh`), never at the
same time as another seeder or translate run.
