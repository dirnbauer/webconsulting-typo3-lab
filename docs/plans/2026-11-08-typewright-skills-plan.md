# Typewright, TYPO3 trademark notice, agentic skills regrouped, skills search

Plan written 2026-11-08 by the coordinating thread (Opus 5.5). Workers read sections 1, 2, 3 and their own work
package. Research notes: `/tmp/redjs/research-web.md` (skills.sh, typo3.org redesign, Dev Companion, trademark,
names). Older plan with the full lab ground truth: `docs/plans/2026-11-04-software-ai-features-plan.md` (sections
1.2 and 1.3 still apply).

## 1. What the user asked (2026-11-08)

1. A better, English, "cool" brand name for the Redaktion app, with TYPO3 affinity. TYPO3 is always uppercase.
2. A TYPO3 trademark/copyright notice for the whole site.
3. /software/redaktion: add the new features (top 15: editing, upload, adding content, linking, switching
   content/languages …), translate. Update its film to the same top 15.
4. /ai/agentic-skills and subpages:
   - films in the styling of the home page product film;
   - hub: what skills are, why, how to install (install script, manual, call directly), difference between calling
     with `/` and adding a skill URL to the chat; short first, details further down with links to the subpages;
   - prominent, professional and appreciative block about the TYPO3 Dev Companion (news.typo3.com article), with the
     article's diagram recreated in TYPO3 CI with gpt-image-2.6, plus a "typo3-skills" box on the right under OUTSIDE;
     add this to the film too;
   - group the skills, recreate the subpages: 3–4 skills per page, each with film and explanation; group overview,
     per skill "what you can do, what is cool, why use it"; ask others to help improve the skills;
   - skill search (shadcn style, Solr) as the first subpage, with detail pages; the existing pages on site 740 may be
     moved; a prominent "superfeature skills" box as the hub's second element linking to the search;
   - search and results with every skills.sh feature (our own design, not a clone);
   - "the other page" (the typo3.org-style skills page, 740/1063) in the new typo3.org design, also with the
     skills.sh features;
   - finally translate everything (de/zh/hu) with Codex gpt-6.1-sol, reasoning high.
5. As always: commit, push GitHub + GitLab, deploy, production data run (< 30 min per run), verify.

## 2. Decisions

| # | Decision |
|---|---|
| D1 | Product name **Typewright** (a wright of type; starts like TYPO3). Subtitle "A calm Mac workspace for TYPO3". Never "TYPO3 Typewright" (TYPO3 trademark policy forbids TYPO3 in product names). Runners-up for the report: Pagewright, Endmark. |
| D2 | Visible rename only: page title, copy, film, app name and UI. Internal ids stay (`redaktion` film id, payload path, markers `redaktion:*`, command `sitepackage:seed-redaktion`, image folder). The slug changes to `/software/typewright` (DataHandler adds the 307 redirect). The app keeps its old userData folder so local drafts and sessions survive. |
| D3 | Trademark line, all Desiderio sites (505, 740, 933) via a Desiderio setting, plus other themes where the footer allows it: "TYPO3 is a registered trademark of the TYPO3 Association. This site is independent and not affiliated with or endorsed by the TYPO3 Association or TYPO3 GmbH." (de: "TYPO3 ist eine eingetragene Marke der TYPO3 Association. Diese Website ist unabhängig und weder mit der TYPO3 Association noch mit der TYPO3 GmbH verbunden oder von ihnen unterstützt.") No logo claim. |
| D4 | Skills: 12 group pages with 3–4 skills each (table in section 3), the other 14 skills in an "Also in the collection" strip on the hub. The 10 old detail pages go away; their slugs redirect to the group page anchor of the skill. |
| D5 | Search: page 740/1030 (shadcn listing) moves under /ai/agentic-skills as the FIRST subpage "Find a skill" (`/ai/agentic-skills/find`), detail page 740/1066 moves under it (`/ai/agentic-skills/find/skill/<name>`, nav_hide). 740/1063 (typo3.org look) stays on site 740 and gets the new typo3.org design. Both lists get the skills.sh features (section 4). Uids stay, so the uid conditions in TypoScript keep working. |
| D6 | Films: a "home" style in the lab film pipeline that matches the home page product film (Desiderio `Build/Film/film.html`): mesh background with more gain, violet stage (#060608, brand #7b51da, accent #b9a4ff), Inter Variable + JetBrains Mono, sheen. Used by the hub film, the 12 group films and the Typewright film. Captions stay selectable WebVTT (en/de/zh/hu), not burned in. |
| D7 | Dev Companion diagram: generated with `sitepackage:llm:generate-image --model=gpt-image-2.6` (fallback gpt-image-2.5-flare), TYPO3 CI: white/very light grey surfaces, ink #171717, TYPO3 orange #ff8700 / #ed6d05 for the read-only line and accents, Source Sans 3 look, rounded cards. Same boxes as the article plus a third dashed box under OUTSIDE: "typo3-skills · 61 agent skills for TYPO3 14 · github.com/dirnbauer/typo3-skills", highlighted ("New"). Text must be checked letter by letter; regenerate when wrong. |
| D8 | Copy rules: `docs/content/style-guide.md` and `packages/site_package/Resources/Private/Data/Content/canon.yaml` (British English, sentence case, headline ≤ 60 chars, eyebrow ≤ 24 chars, no hype words). Facts only from the repos (no invented numbers). The skills repo: https://github.com/dirnbauer/typo3-skills (61 skills: 41 webconsulting, 20 vendored; MIT code, CC BY-SA 4.0 content; no CONTRIBUTING.md yet → invite issues and pull requests). |
| D9 | Translation memories are keyed by English source text; new copy → `sitepackage:content:translate --missing=-` → Sol writes entries. XLF labels (Desiderio footer, Skillflow) also by Sol. |

## 3. Skill groups (from the inventory, 2026-11-08)

Skill names without the `typo3-` prefix where the repo uses it. SF = superfeature.

| # | Group (page title) | slug | Pitch | Skills |
|---|---|---|---|---|
| 1 | Proven site upgrade | proven-site-upgrade | Upgrade a whole site from 12/13 to 14.3 LTS in local DDEV and prove visitors see no difference. | upgrade-run SF, upgrade-intake, upgrade-baseline, upgrade-closure |
| 2 | Upgrade planning and migration | upgrade-planning | Estimate the effort, migrate step by step with a rollback point, learn from every run. | upgrade-effort-model, project-upgrade, upgrade-migration, upgrade-retrospective |
| 3 | Automated code migration | code-migration | Tools move extension code to v14: Rector for PHP, Fractor for the rest, in batches. | extension-upgrade, rector, fractor, batch |
| 4 | Content modelling and data | content-modelling | Content types in YAML, safe imports and translations, news tagged in bulk. | content-blocks SF, datahandler, translations, news-tags |
| 5 | Editor experience | editor-experience | Inline editing, staged publishing, card and board record views, a well-set rich-text editor. | visual-editor SF, workspaces, records-list-types SF, ckeditor5 |
| 6 | Design system and frontend | design-system | Design tokens become Fluid elements, a living style guide, a Vite build and v14 icons. | shadcn-content-elements SF, design-system-page SF, vite, icon14 |
| 7 | TYPO3 v14 development | v14-development | Correct v14 code, TypoScript and backend components on a local DDEV instance. | v14-reference, typoscript-ref, webcomponents, ddev |
| 8 | Accessibility | accessibility | WCAG 2.2 AA scans traced to templates, fixed markup, a drafted statement. | wcag22-aa-agentic SF, accessibility, a11y |
| 9 | Search, SEO and agents | search-seo-agents | Be found by Google, by your own site search and by AI agents. | seo, structured-data SF, solr, webmcp SF |
| 10 | Security and operations | security-operations | Harden the installation, exact editor rights, the right scheduled jobs. | security, backend-rights SF, scheduler-jobs, site-conformance |
| 11 | Testing and code quality | testing-quality | Bugs become failing tests, journeys run in a real browser, code is checked and cleaned. | testing, playwright, conformance, simplify |
| 12 | Release and open source | release-open-source | First public release, docs on docs.typo3.org, a hardened supply chain, Core contributions. | initial-release, docs, core-contributions, enterprise-readiness |

Also in the collection (hub strip, each pointing to a group): powermail, jev → 4; php-modernization → 3;
web-design-guidelines, web-platform-design → 6; security-audit, security-incident-reporting → 10;
thermo-nuclear-code-quality-review → 11; postgres-best-practices → 7; architecture-decision-records,
webconsulting-create-documentation, idea-extension-blog → 12. Not featured: webconsulting-branding, legal-impressum.

Old detail pages → redirect targets: upgrade-run → 1, wcag22-aa-agentic → 8, webmcp → 9, structured-data → 9,
backend-rights → 10, content-blocks → 4, shadcn-content-elements → 6, visual-editor → 5, rector → 3,
records-list-types → 5 (anchor = skill name).

Install and use (facts from the repo README and install.sh):
- Install script: `git clone https://github.com/dirnbauer/typo3-skills.git && cd typo3-skills && ./install.sh`
  (symlinks whole skill folders into ~/.claude/skills, ~/.cursor/skills, ~/.codex/skills, ~/.gemini/skills,
  ~/.codeium/windsurf/skills; inside a project with composer.json also into .claude/skills etc.; never overwrites;
  `--client`, `--user-only`, `--project-only`; `git pull` updates everything).
- Manual: copy or link the WHOLE folder `skills/<name>` (not only SKILL.md) into `~/.claude/skills/<name>` or
  `<project>/.claude/skills/<name>` (Codex/Cursor/Gemini/Windsurf equivalents).
- Call: describe the task (the agent picks the skill by its description); or call it by name: Claude Code
  `/typo3-upgrade-run`, Cursor `/` in agent chat, Codex `$typo3-upgrade-run`, Windsurf `@typo3-upgrade-run`.
- URL in a chat: pasting the raw SKILL.md URL gives the model only that text — no scripts, references or evals; fine
  for a quick try of text-only skills, breaks skills with scripts (upgrade-run, wcag22-aa-agentic, backend-rights).
  `/name` uses the installed folder with everything in it.

## 4. Search features (skills.sh-inspired, our own design)

Must have, in both looks (shadcn on 505, typo3.org on 740/1063):
- Instant search: `/` focuses, typing anywhere jumps into the field, Esc clears, query in the URL (`?tx_solr[q]=`),
  results while typing (debounced, from 2 characters), count line, empty state with suggestions.
- Result rows/cards: rank number, name, owner/source (webconsulting, Netresearch, Anthropic …), one-line
  description, badges (group/topic, superfeature, maintained vs vendored, capability vs preference, licence,
  reviewed evals), match highlighting, arrow-key navigation, Enter opens.
- Browse: topics = our 12 groups (chips/facets with counts), "Official" filter = owner, audience filter,
  sort: relevance, name, recently updated, superfeatures first.
- Packs: each group shows a one-line install for the whole group.
- Detail page: install tabs "Command" (install.sh / manual link line) and "Prompt" (a sentence to paste into a chat),
  rendered SKILL.md with "Show more", facts (version, owner, licence, evals, last update, source link), related skills
  (same group), group link, copy buttons.
- No CDN assets (fonts self-hosted, no Bootstrap/FontAwesome from jsDelivr); WCAG 2.2 AA; works without JS (plain
  Solr results), enhanced with JS.

## 5. Typewright: top 15 features (page section + film)

1 Pages and languages at a glance (tree, backend layout columns, language tabs with counts) · 2 Page assistant
(position, type, URL path, translations, first element; starts hidden) · 3 TYPO3's own element wizard (Content Blocks,
column rules) · 4 Edit every field of an element (TCA, Content Blocks collections) · 5 Rich text that keeps TYPO3's
markup (and opens HTML it cannot keep as HTML) · 6 Links the TYPO3 way (pages, content anchors, files, folders,
records; ⌘K) · 7 Media library on FAL (folders, search, metadata per language) · 8 Upload with progress, straight into
a field · 9 Alt text where the image is used, decorative images on purpose · 10 Translate next to the original ·
11 Switch languages and content: move, duplicate, hide, reorder elements · 12 Page properties per language (title,
navigation title, URL path, SEO) · 13 News with local drafts that survive an offline restart · 14 Workspaces:
save into a workspace, review the list, publish with explicit approval (TYPO3's own commands) · 15 A calm Mac app:
save feedback in place, ⌘S, preview, writing focus, dark mode, encrypted session.

## 6. Work packages

| WP | Owner | Repo / worktree | Writes to DDEV DB? |
|---|---|---|---|
| WP-D Desiderio: footer setting + XLF (en/de; zh/hu by Sol later), hub rewrite, 12 group pages, old pages → redirects, tests | Opus worker | `packages/desiderio` (own git worktree, branch `feature/skills-groups`) | no (coordinator seeds) |
| WP-S Skillflow + lab search config: skills.sh features, both looks, detail page, labels en/de, lab TypoScript/site config for 505 | Opus worker | `packages/skillflow` worktree + lab worktree files listed in its brief | read-only page views only |
| WP-A App rename to Typewright (UI, productName, docs; keep userData path), release 2.4.0 | Opus worker | `~/projects/desktop-news-typo3` (worktree) | no |
| WP-C Coordinator: plan, film "home" style, Dev Companion diagram, Typewright page + screenshots + film, group films, integration, seeds, translation via Sol, releases, deploy | coordinator | lab worktree | yes (only the coordinator) |

Rules for all workers: never run seeders, translate, Solr indexing, `composer install/update`, `cache:flush` or any DB
write in the shared DDEV project; never `git stash` in shared checkouts; commit only in your own worktree/branch; do
not push. Report: what changed (paths), tests run, open questions.

## 7. Added during the run (2026-11-08)

| What | Where | State |
|---|---|---|
| MTUG section on the skills hub (MTUG = Munich TYPO3 User Group, five community talks with verified materials; Kurt spoke at TUGA, not MTUG, so the copy credits the speakers) | WP-D (steered), facts in /tmp/redjs/research-mtug.md | in WP-D |
| Dev Companion diagram: gpt-image-2.6-flare (gpt-image-2.6 does not exist on the account) | /tmp/redjs/frontend-dev-companion-skills-8f5c759d.webp, handed to WP-D | done |
| Trademark notice for every site: Desiderio prints it in its footer (WP-D), every other theme gets one line from the site_package middleware `TrademarkNotice` (skips pages that carry `data-trademark-notice`); labels trademark.xlf en/de, zh/hu by Sol | lab worktree | done locally |
| Film "home" style (`style: 'home'`): Inter, violet mesh, gain 1.4 | Build/Film/film.html, films/feature-film.mjs | done |
| Group film helper `skillGroupFilm()` | Build/Film/films/skill-group-film.mjs | done, specs after WP-D |
| WP-N: full EXT:news in Typewright (languages like pages, TCA-driven form, media incl. video, related links/files, categories, tags, related news, SEO, content elements; workspace overlay fixes) | app worktree /Users/dirnbauer/projects/worktrees/typewright-news (branch feature/news-full) | running |
| Typewright page: feature 3 = adding an element (wizard → editor), feature 9 = everything EXT:news offers; capture script /tmp/redjs/capture-typewright.mjs | /tmp/redjs/build-typewright-payload.py | waits for WP-A + WP-N |
| MedAtlas: "Connected through an open API" + "Why AI agents answer better with it" (facts from MedAtlas docs/API.md; no MCP/llms.txt claims; not public, so placeholders) | Desiderio worktree /Users/dirnbauer/projects/worktrees/desiderio-medatlas, branch feature/medatlas-api, b70068bd | done, merge into the Desiderio release |
| Typewright settings menu: icon row in the sidebar footer (gear menu: Sprache, Berechtigungen, Verbindung, Arbeitsbereich, Lokale Entwürfe, Tastenkürzel, Über Typewright, Abmelden; theme Hell/Dunkel/System; connection status), connection as a modal on start, Berechtigungen/Verbindung/theme/logout out of nav and header | WP-A (steered after the rename), main app checkout | running |
| English interface (i18n de/en) with an interface-language setting under "Sprache" | after WP-A and WP-N are merged (touches every UI string) | planned |
| Typewright: original webconsulting logo in light and dark (T3 Ops logo-light.svg / logo-dark.svg, unchanged), app icon = original mark without text, connection modal in the T3 Ops login look (petrol brand panel + form card, German copy given to WP-A) | WP-A (steered), main app checkout | running |
