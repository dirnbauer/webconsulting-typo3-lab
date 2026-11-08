/**
 * The twelve group films of /ai/agentic-skills (plan 2026-11-08). Generated from
 * Desiderio's ShowcaseSkillGroups::groups() (4.35.0) so prompts and summaries match
 * the pages word for word: each skill's "Say" prompt is typed, its one-line summary
 * is spoken, then its real result footage (superfeatures, skill-footage.mjs) or what
 * is cool about it and why to use it. Hooks are written per group.
 */
import { skillGroupFilm } from './skill-group-film.mjs';
import { FOOTAGE } from './skill-footage.mjs';

const GROUPS = [
    {
        "slug": "proven-site-upgrade",
        "name": "Proven site upgrade",
        "sub": "Upgrade a whole site from TYPO3 v12 or v13 to 14.3 LTS in a local DDEV copy, and prove that visitors see no difference.",
        "hook": {
            "head": "An upgrade is done when nothing changed. <em>Can you prove it?</em>",
            "say": "An upgrade is done when visitors see no difference. Can you prove it?"
        },
        "titleSay": "Proven site upgrade: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-upgrade-run",
                "prompt": {
                    "text": "Update this TYPO3 12 project to 14.3 in DDEV and make sure the site looks exactly the same afterwards.",
                    "say": "Upgrades a site from v12 or v13 to 14.3 LTS and proves that visitors see no difference."
                },
                "headline": "typo3-upgrade-run: <em>the whole upgrade, proven</em>",
                "cool": "A sealed graph of 72 nodes and 206 edges decides each next step, and every retry has a limit.",
                "why": "It keeps two promises apart: first the same site on the new version, then improvements such as structured data and WebMCP."
            },
            {
                "name": "typo3-upgrade-intake",
                "prompt": {
                    "text": "Start the discovery for our v12-to-v14 site update and tell me which local extensions, sites and missing sitemaps change the plan.",
                    "say": "A read-only preflight of repository, runtime, database, files, extensions and risks."
                },
                "headline": "typo3-upgrade-intake: <em>know what you upgrade</em>",
                "cool": "It never changes the site.",
                "why": "An upgrade that starts from the wrong database proves nothing."
            },
            {
                "name": "typo3-upgrade-baseline",
                "prompt": {
                    "text": "Intake passed for the TYPO3 update. Build the untouched before-state proof, including our cookie banner and homepage carousel.",
                    "say": "Captures responses, HTML and screenshots of the untouched site and seals them as Baseline A."
                },
                "headline": "typo3-upgrade-baseline: <em>the site as it was</em>",
                "cool": "Fresh browsers capture the untouched site twice, and both captures must match at zero differences.",
                "why": "A comparison is only as good as its reference."
            },
            {
                "name": "typo3-upgrade-closure",
                "prompt": {
                    "text": "The TYPO3 v14 target is stable. Prove HTTP, markup, pixels, backend operations and our key interactions against the frozen source.",
                    "say": "Compares the upgraded site with the baseline and closes only when nothing unexplained changed."
                },
                "headline": "typo3-upgrade-closure: <em>proof before sign-off</em>",
                "cool": "13 closure checks, from pixels at strict zero to Lighthouse and axe.",
                "why": "It never accepts an unexplained difference and never refreshes the baseline to make one disappear."
            }
        ]
    },
    {
        "slug": "upgrade-planning",
        "name": "Upgrade planning and migration",
        "sub": "Estimate the effort before you start, migrate one cause at a time with a rollback point, and learn from every run.",
        "hook": {
            "head": "How long will it take? <em>And what if a step fails?</em>",
            "say": "How long will the upgrade take, and what happens when a step goes wrong?"
        },
        "titleSay": "Upgrade planning and migration: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-upgrade-effort-model",
                "prompt": {
                    "text": "Estimate the effort to upgrade this project from TYPO3 v13 to 14.3 LTS, extension by extension.",
                    "say": "Estimates an LTS upgrade per extension, with risk multipliers for the v14 breaking changes."
                },
                "headline": "typo3-upgrade-effort-model: <em>estimate first</em>",
                "cool": "It names what makes v14 hard: the removed TypoScriptFrontendController counts × 1.3, the stricter ViewHelpers of Fluid 5 × 1.2.",
                "why": "A number per extension is easier to plan with than a gut feeling, and it shows how much Rector saves: 15 to 20 per cent."
            },
            {
                "name": "typo3-project-upgrade",
                "prompt": {
                    "text": "After migrating to site sets in TYPO3 v13, I still get “No page configured for type=0”. What’s wrong?",
                    "say": "Moves a deployed project to a new LTS: site configuration, TypoScript, templates, Docker and database."
                },
                "headline": "typo3-project-upgrade: <em>the project around the code</em>",
                "cool": "It knows the traps after the switch: a leftover sys_template record that overrides site sets, or a container without ImageMagick serving full-size images.",
                "why": "It covers the project; typo3-extension-upgrade covers extension code."
            },
            {
                "name": "typo3-upgrade-migration",
                "prompt": {
                    "text": "Handle the next authorised data-migration node for the old list_type records before the new CType registration lands.",
                    "say": "Carries out one migration step behind a rollback point: Composer, Rector, Fractor, database or data."
                },
                "headline": "typo3-upgrade-migration: <em>one cause per step</em>",
                "cool": "Each step changes one cause and starts from a rollback anchor or a snapshot.",
                "why": "Small, reversible steps make a failed attempt cheap, and the graph decides how often a step may be retried."
            },
            {
                "name": "typo3-upgrade-retrospective",
                "prompt": {
                    "text": "Review our last six TYPO3 upgrade tasks: what was requested, what took too long, and which failures belong in the process.",
                    "say": "Reviews past or unfinished upgrade runs: what was done, what is open and what went wrong."
                },
                "headline": "typo3-upgrade-retrospective: <em>lessons from real runs</em>",
                "cool": "Each finding becomes a row of problem, cause, fix and control, with proposed skill and eval changes.",
                "why": "Git may say a branch is done while the run says otherwise."
            }
        ]
    },
    {
        "slug": "code-migration",
        "name": "Automated code migration",
        "sub": "Rector rewrites the PHP, Fractor the TypoScript, Fluid and FlexForms, and one change rolls out to many extensions in reviewable batches.",
        "hook": {
            "head": "Forty extensions. <em>One deprecated API.</em>",
            "say": "Forty extensions, one deprecated API, and nobody wants to fix it by hand."
        },
        "titleSay": "Automated code migration: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-extension-upgrade",
                "prompt": {
                    "text": "Make this extension work with TYPO3 v14.3 and keep it running on v13.4.",
                    "say": "Makes one extension work with a newer TYPO3 LTS, with Extension Scanner, Rector, Fractor and PHPStan."
                },
                "headline": "typo3-extension-upgrade: <em>one extension, done properly</em>",
                "cool": "Done means exit code 0 on every version line the constraint names.",
                "why": "It treats a failing commit hook as a check to fix, not an obstacle to skip."
            },
            {
                "name": "typo3-rector",
                "prompt": {
                    "text": "Automatically fix the deprecated PHP calls in Classes/ for TYPO3 v14.",
                    "say": "Fixes deprecated PHP calls with Rector: dry run, review the diff, apply, dry run again."
                },
                "headline": "typo3-rector: <em>deprecated PHP, fixed by rules</em>",
                "cool": "Dry run, review the diff, apply, dry run again.",
                "why": "Rules catch namespace moves and signature changes that hand edits miss, and the second pass catches code the first one generated."
            },
            {
                "name": "typo3-fractor",
                "prompt": {
                    "text": "I ran Rector and it left every .yaml and .typoscript file untouched. What handles those?",
                    "say": "Rewrites TypoScript, FlexForms, Fluid, YAML and XLIFF towards v14 with Fractor."
                },
                "headline": "typo3-fractor: <em>the files Rector leaves alone</em>",
                "cool": "The cumulative UP_TO_TYPO3_14 set applies every older rule set on the way to v14.",
                "why": "FlexForm, TypoScript and Fluid patterns are easy to miss in a review."
            },
            {
                "name": "typo3-batch",
                "prompt": {
                    "text": "Inventory which of our extensions still use the removed HashService and fix them together.",
                    "say": "Applies one repeated change to many extensions at once, in reviewable batches."
                },
                "headline": "typo3-batch: <em>one change, many extensions</em>",
                "cool": "Adapted from Boris Cherny’s /batch skill for Claude Code, it splits the work by root cause into units you can test on their own.",
                "why": "A 500-file commit cannot be reviewed."
            }
        ]
    },
    {
        "slug": "content-modelling",
        "name": "Content modelling and data",
        "sub": "Define content types in one YAML file each, import and translate records the way TYPO3 expects, and tag news in bulk.",
        "hook": {
            "head": "New content types. <em>Not one broken relation.</em>",
            "say": "New content types, imported records and translations, without breaking a single relation."
        },
        "titleSay": "Content modelling and data: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-content-blocks",
                "prompt": {
                    "text": "I need a teaser element with a headline, an image and a link. Set up the definition for it.",
                    "say": "Defines content elements, records and page types in one config.yaml each."
                },
                "headline": "typo3-content-blocks: <em>one file per content type</em>",
                "cool": "Content Blocks generates TCA, SQL, TypoScript, backend forms and labels from that one file.",
                "why": "Identifiers stay stable through a migration, because a renamed identifier leaves records that look empty."
            },
            {
                "name": "typo3-datahandler",
                "prompt": {
                    "text": "I need to create 500 pages programmatically from a JSON file with the parent references intact.",
                    "say": "Creates, changes, translates and deletes records from PHP through DataHandler."
                },
                "headline": "typo3-datahandler: <em>imports that keep references intact</em>",
                "cool": "Everything goes through DataHandler, never raw SQL, so the reference index, history, workspace versions and caches stay right.",
                "why": "Raw INSERTs look fine until the reference index is stale or imported records belong to nobody."
            },
            {
                "name": "typo3-translations",
                "prompt": {
                    "text": "The backend shows the raw label key instead of the actual text.",
                    "say": "Fixes labels and XLIFF files, including plural forms with ICU MessageFormat."
                },
                "headline": "typo3-translations: <em>labels that show the right text</em>",
                "cool": "It starts from a layout that works on v13 and v14, and moves to XLIFF 2.0 only when the extension is v14-only.",
                "why": "A raw label key on screen or duplicate ids after a merge are small bugs that editors see first."
            },
            {
                "name": "typo3-news-tags",
                "prompt": {
                    "text": "We have 8,000 news articles with no tags at all. Can we generate and assign them automatically?",
                    "say": "Generates thematic tags for EXT:news and assigns them to existing articles by keyword."
                },
                "headline": "typo3-news-tags: <em>a tag catalogue for your archive</em>",
                "cool": "It builds the console command for the assignment, and can add b13/tag as a separate tag system for the whole site.",
                "why": "It is made for archives too large to tag by hand, on TYPO3 v14.3 with georgringer/news 14."
            }
        ]
    },
    {
        "slug": "editor-experience",
        "name": "Editor experience",
        "sub": "Editors type on the page, publish when they are ready, see records as cards and boards, and get a well-configured rich-text editor.",
        "hook": {
            "head": "Editors want to <em>click and type.</em>",
            "say": "Editors want to click into the page and type, not fill in forms."
        },
        "titleSay": "Editor experience: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-visual-editor",
                "prompt": {
                    "text": "Editors want to click into the page and type, instead of opening backend forms.",
                    "say": "Prepares templates so editors click into the page and type, with every output traceable to its record."
                },
                "headline": "typo3-visual-editor: <em>editing on the page</em>",
                "cool": "It uses the core ViewHelpers f:render.text and f:render.contentArea, which stay useful even without the extension.",
                "why": "Readiness is tested as a restricted editor, with translations and a workspace, never as an administrator."
            },
            {
                "name": "typo3-workspaces",
                "prompt": {
                    "text": "Editors need to prepare content for a launch date without it going live early.",
                    "say": "Prepares content in a workspace, then reviews and publishes it, with the file traps named."
                },
                "headline": "typo3-workspaces: <em>publish when you are ready</em>",
                "cool": "It says plainly what workspaces do not cover: files are not versioned, so an overwritten file is live everywhere at once.",
                "why": "A preview that shows the live page, or images that lose their references on publishing, are the usual surprises."
            },
            {
                "name": "typo3-records-list-types",
                "prompt": {
                    "text": "The Records module shows a plain table. Can I get cards with thumbnails instead?",
                    "say": "Shows records as grid, teaser, timeline or board views in the Records module, set in TSconfig."
                },
                "headline": "typo3-records-list-types: <em>cards instead of rows</em>",
                "cool": "Views, fields and templates live in the Page TSconfig, Fluid and CSS of your sitepackage.",
                "why": "Editors find records by their thumbnail, not by a row of text."
            },
            {
                "name": "typo3-ckeditor5",
                "prompt": {
                    "text": "Migrate our CKEditor 4 plugin to CKEditor 5 and add it to the toolbar of our RTE preset.",
                    "say": "Configures RTE presets and builds CKEditor 5 plugins for TYPO3."
                },
                "headline": "typo3-ckeditor5: <em>the rich-text editor, set up well</em>",
                "cool": "It knows the versions: TYPO3 v14.3 bundles CKEditor 5 v47, which follows light and dark mode by default.",
                "why": "CKEditor 5 is a complete rewrite with no compatibility layer for version 4, so old plugins need a plan."
            }
        ]
    },
    {
        "slug": "design-system",
        "name": "Design system and frontend",
        "sub": "Design tokens become Fluid content elements, the site gets a living style guide, and assets and icons are built the v14 way.",
        "hook": {
            "head": "Tokens are only useful <em>in the elements.</em>",
            "say": "A design system only helps when it reaches the content elements."
        },
        "titleSay": "Design system and frontend: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-shadcn-content-elements",
                "prompt": {
                    "text": "These elements have hardcoded hex colours everywhere. Move them to semantic tokens.",
                    "say": "Turns shadcn/ui presets into Fluid content elements with semantic tokens, previews and seed data."
                },
                "headline": "typo3-shadcn-content-elements: <em>shadcn/ui as Fluid</em>",
                "cool": "No React at runtime: tokens and class contracts are ported to Fluid, and small vanilla controllers handle the interaction.",
                "why": "Elements without raw colour values follow every preset and dark mode, and editors recognise them by their previews."
            },
            {
                "name": "typo3-design-system-page",
                "prompt": {
                    "text": "Build a living style-guide page in our TYPO3 site with the real colours, ramps, fonts, spacing and component examples.",
                    "say": "Builds an accessible design-system page in your site, with design.md, tokens.json and a one-page PDF."
                },
                "headline": "typo3-design-system-page: <em>the style guide, live</em>",
                "cool": "The values come from the live site, with evidence for every contrast claim.",
                "why": "It asks which installation and which page to use before it writes, so nothing lands on a site you did not choose."
            },
            {
                "name": "typo3-vite",
                "prompt": {
                    "text": "Set up Vite for our TYPO3 v14 sitepackage with one entrypoint per content element and local fonts.",
                    "say": "Sets up Vite 7 for TYPO3 sitepackages, with an entry per content element."
                },
                "headline": "typo3-vite: <em>a frontend build for v14</em>",
                "cool": "Each content element gets its own entrypoint, so a page loads only the CSS and JavaScript of the elements it shows.",
                "why": "TYPO3 v14 removed the core’s own concatenation and compression of frontend assets, so a build tool now does that work."
            },
            {
                "name": "typo3-icon14",
                "prompt": {
                    "text": "Our extension icons look heavy and filled next to the v14 core ones. Can you redraw them?",
                    "say": "Redraws and migrates extension icons to the v14 line style, for light and dark mode."
                },
                "headline": "typo3-icon14: <em>icons that fit the v14 backend</em>",
                "cool": "It starts from meaning, not shapes, and keeps module, plugin, record and action icons distinct.",
                "why": "Heavy, filled icons look foreign next to the v14 core, and a module icon that vanishes in dark mode is a bug."
            }
        ]
    },
    {
        "slug": "v14-development",
        "name": "TYPO3 v14 development",
        "sub": "Look up the v14 way before writing it, check TypoScript against its reference, build backend components with Lit, and run it all in DDEV.",
        "hook": {
            "head": "TYPO3 v14, <em>written the v14 way.</em>",
            "say": "Agents still write TYPO3 code from old memory. These skills teach them version fourteen."
        },
        "titleSay": "TYPO3 v14 development: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-v14-reference",
                "prompt": {
                    "text": "Which PSR-14 event replaces the old post-processing hook in v14, and what is its namespace?",
                    "say": "Shows how v14 code is written: controllers, ViewFactory, PSR-14 events, TCA, QueryBuilder and CLI."
                },
                "headline": "typo3-v14-reference: <em>the API as v14 has it</em>",
                "cool": "It checks every class, method, event and configuration key against the installed v14 source, because memory is often wrong after the v14 removals.",
                "why": "Agents often mix up versions."
            },
            {
                "name": "typo3-typoscript-ref",
                "prompt": {
                    "text": "How do I create a navigation menu in TypoScript? Show me the recipe.",
                    "say": "A version-aware reference for TypoScript, TSconfig and Fluid, with a local lookup."
                },
                "headline": "typo3-typoscript-ref: <em>TypoScript, looked up locally</em>",
                "cool": "A local lookup script answers from the reference and adds deprecation warnings in review mode.",
                "why": "TypoScript keys are easy to misremember."
            },
            {
                "name": "typo3-webcomponents",
                "prompt": {
                    "text": "Build a reusable widget for the backend with Lit.",
                    "say": "Builds v14 backend Web Components with Lit, import maps and FormEngine integration."
                },
                "headline": "typo3-webcomponents: <em>backend JavaScript, the v14 way</em>",
                "cool": "The v14 APIs it names were checked against TYPO3 v14.3.9, and it adds fallbacks for v12 or v13 only when you ask.",
                "why": "It turns jQuery-era backend code into standard Web Components."
            },
            {
                "name": "typo3-ddev",
                "prompt": {
                    "text": "I need a TYPO3 v14 instance for this extension. Set it up with DDEV.",
                    "say": "Starts and reaches a local TYPO3 instance with DDEV, for one version or several side by side."
                },
                "headline": "typo3-ddev: <em>a running TYPO3, locally</em>",
                "cool": "It never guesses a URL: it reads the project name from .ddev/config.yaml and builds the address from it.",
                "why": "Most other skills need a running instance to check their work."
            }
        ]
    },
    {
        "slug": "accessibility",
        "name": "Accessibility",
        "sub": "Scan against WCAG 2.2 AA with findings traced to their templates, fix markup at the source, and draft a statement for review.",
        "hook": {
            "head": "Every barrier, <em>traced to its template.</em>",
            "say": "An accessibility report helps when every finding points to the template that causes it."
        },
        "titleSay": "Accessibility: three skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-wcag22-aa-agentic",
                "prompt": {
                    "text": "Scan our DDEV site at https://acme.ddev.site and tell me everything that fails WCAG 2.2 AA, page by page.",
                    "say": "Scans with Playwright and axe-core and names the Fluid template behind each finding."
                },
                "headline": "typo3-wcag22-aa-agentic: <em>scans that name the template</em>",
                "cool": "Each violation is mapped to the Fluid template that produced it, and a CI baseline fails pull requests that add new ones.",
                "why": "Automated scans are evidence, not conformance."
            },
            {
                "name": "typo3-accessibility",
                "prompt": {
                    "text": "Our accordion content element is just nested divs with a click handler. NVDA does not announce it as expandable. What markup should it be?",
                    "say": "Accessible patterns for Fluid, PHP and JavaScript, with a checklist to verify by hand."
                },
                "headline": "typo3-accessibility: <em>the markup, fixed at the source</em>",
                "cool": "It prefers native HTML over ARIA and uses TYPO3’s own ViewHelpers first.",
                "why": "When a screen reader does not announce an accordion as expandable, this skill gives you the markup that fixes it."
            },
            {
                "name": "typo3-a11y",
                "prompt": {
                    "text": "Review the main navigation and the filter form of our sitepackage for accessibility.",
                    "say": "Accessible patterns for navigation, filters, tables, sticky headers and language switchers."
                },
                "headline": "typo3-a11y: <em>patterns for common components</em>",
                "cool": "It names what v14 already does better, such as the backend modal on the native dialog element, with a focus trap and Escape to close.",
                "why": "It works to WCAG 2.2 AA for sitepackage frontends, with a checklist for every content element."
            }
        ]
    },
    {
        "slug": "search-seo-agents",
        "name": "Search, SEO and agents",
        "sub": "Be found by Google, by your own site search and by AI agents, with every statement in the markup taken from the visible page.",
        "hook": {
            "head": "Found three ways: <em>search, site search, agents.</em>",
            "say": "A site now has to be found three ways: by search engines, by its own search and by AI agents."
        },
        "titleSay": "Search, SEO and agents: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-seo",
                "prompt": {
                    "text": "Google is mixing up our German and Italian pages in the results. Is hreflang set up right?",
                    "say": "Sitemaps, canonical URLs, hreflang, meta tags, Open Graph and robots rules with EXT:seo."
                },
                "headline": "typo3-seo: <em>the right page in the right language</em>",
                "cool": "It uses EXT:seo and core features before custom output, checked against typo3/cms-seo 14.3.9.",
                "why": "It starts where the symptoms show: language versions mixed up in Google, or a sitemap with only the home page in it."
            },
            {
                "name": "typo3-structured-data",
                "prompt": {
                    "text": "Add LocalBusiness and opening-hours structured data to this TYPO3 company website.",
                    "say": "Builds schema.org JSON-LD from the records that render the page, and keeps it through an upgrade."
                },
                "headline": "typo3-structured-data: <em>JSON-LD from visible content</em>",
                "cool": "One connected graph per page with stable @id values, built only from visible, current content.",
                "why": "Valid markup makes a page eligible for search features; whether they appear is the search engine’s decision."
            },
            {
                "name": "typo3-solr",
                "prompt": {
                    "text": "The indexing queue has been stuck at 12,000 items since yesterday.",
                    "say": "Runs and debugs Apache Solr search: indexing, facets, suggest, PDFs and stuck queues."
                },
                "headline": "typo3-solr: <em>site search that finds things</em>",
                "cool": "It treats EXT:solr, the Solr server, the configset and Tika as one stack.",
                "why": "A Solr server that accepts documents can still drop unknown fields."
            },
            {
                "name": "typo3-webmcp",
                "prompt": {
                    "text": "Make the search and the contact form on this TYPO3 v14 website available to browser agents through WebMCP.",
                    "say": "Makes search, navigation and forms usable as typed WebMCP tools for browser agents."
                },
                "headline": "typo3-webmcp: <em>typed browser tools for agents</em>",
                "cool": "A browser without WebMCP gets the same working site.",
                "why": "WebMCP is a proposal, not yet a standard."
            }
        ]
    },
    {
        "slug": "security-operations",
        "name": "Security and operations",
        "sub": "Harden the installation, give every editor exactly the rights they need, run the right scheduled jobs and check the project repository.",
        "hook": {
            "head": "Exact rights. <em>A hardened site.</em>",
            "say": "Editors with exactly the rights they need, and an installation that is hardened and runs the right jobs."
        },
        "titleSay": "Security and operations: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-security",
                "prompt": {
                    "text": "We go live next week. Harden the installation: what are the must-dos?",
                    "say": "Hardens a v14 installation: trusted hosts, the Install Tool, two-factor sign-in, CSP and safe queries."
                },
                "headline": "typo3-security: <em>the must-dos before go-live</em>",
                "cool": "For an assessment it inspects and reports without changing anything.",
                "why": "It fixes the classic findings the TYPO3 way: user input concatenated into SQL becomes a QueryBuilder query with bound values."
            },
            {
                "name": "typo3-backend-rights",
                "prompt": {
                    "text": "Create one main backend editor role with simple subgroups that can manage all normal content, news records, and files.",
                    "say": "Builds and audits editor groups, page and language rights, MFA and login branding."
                },
                "headline": "typo3-backend-rights: <em>editor roles you can verify</em>",
                "cool": "A separate, working administrator is kept before anything changes, and group structure, membership and page ownership are three separate approvals.",
                "why": "An empty allow-list for content types is a defect, not “allow everything”."
            },
            {
                "name": "typo3-scheduler-jobs",
                "prompt": {
                    "text": "Audit this TYPO3 v14 installation and add the maintenance jobs it actually needs to the backend Scheduler.",
                    "say": "Plans and checks the Scheduler tasks an installation needs, with groups and safe intervals."
                },
                "headline": "typo3-scheduler-jobs: <em>the jobs your site needs</em>",
                "cool": "It derives the smallest complete set of tasks from the installation itself, never from another site’s rows.",
                "why": "It finds jobs that are missing, late or ungrouped, and repairs task rows left without a type after a v14 update."
            },
            {
                "name": "typo3-site-conformance",
                "prompt": {
                    "text": "Check this TYPO3 project repository with its compose.yaml against the site conformance gold standard.",
                    "say": "Scores a deployable project with Docker or Compose against a gold standard."
                },
                "headline": "typo3-site-conformance: <em>the project, scored</em>",
                "cool": "Seven rule families, from images pinned by digest and an SBOM in CI to settings files without secrets.",
                "why": "It is the project counterpart to typo3-conformance, which checks single extensions."
            }
        ]
    },
    {
        "slug": "testing-quality",
        "name": "Testing and code quality",
        "sub": "A bug report becomes a failing test, journeys run in a real browser, and extension code is checked and cleaned without changing what it does.",
        "hook": {
            "head": "Every bug <em>becomes a test.</em>",
            "say": "A bug that comes back should have been a test the first time."
        },
        "titleSay": "Testing and code quality: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-testing",
                "prompt": {
                    "text": "Reproduce this reported bug as a failing functional test before we fix it.",
                    "say": "Unit, functional and end-to-end tests, and a reported bug reproduced as a failing test first."
                },
                "headline": "typo3-testing: <em>the failing test comes first</em>",
                "cool": "A bug report is a test that does not exist yet: the skill writes it from the report and watches it fail for that reason.",
                "why": "A test written after the fix proves only that the code does what it does."
            },
            {
                "name": "typo3-playwright",
                "prompt": {
                    "text": "Our TYPO3 editors cannot insert record links. Add a browser test that opens the dialog and saves the edited text.",
                    "say": "Builds and repairs Playwright tests for visitor and editor journeys, with isolated fixtures."
                },
                "headline": "typo3-playwright: <em>journeys in a real browser</em>",
                "cool": "It reuses the project’s Playwright setup instead of installing a second framework, and counts which components are covered.",
                "why": "A unit test cannot click a filter or save a link dialog."
            },
            {
                "name": "typo3-conformance",
                "prompt": {
                    "text": "Review this TYPO3 extension and tell me what needs attention before it supports v14.3.",
                    "say": "Checks an extension against TYPO3 standards: versions, coding standards, deprecations and TER readiness."
                },
                "headline": "typo3-conformance: <em>an extension, scored</em>",
                "cool": "A score out of 100 in five areas of 20 points each, and critical issues block a release whatever the score.",
                "why": "A version bump that leaves half the files behind is easy to miss."
            },
            {
                "name": "typo3-simplify",
                "prompt": {
                    "text": "Simplify the code I changed in this branch before we merge it.",
                    "say": "Cleans up extension code for clarity and v14 patterns without changing what it does."
                },
                "headline": "typo3-simplify: <em>clearer code, same behaviour</em>",
                "cool": "Three review passes run in parallel: reuse of TYPO3 APIs, code quality and efficiency.",
                "why": "It replaces custom code with what TYPO3 already provides, and checks that the behaviour stays the same."
            }
        ]
    },
    {
        "slug": "release-open-source",
        "name": "Release and open source",
        "sub": "Ship a first release that keeps its promises, write docs that render on docs.typo3.org, harden the supply chain and contribute to the Core.",
        "hook": {
            "head": "From the first release <em>to Core patches.</em>",
            "say": "Shipping an extension takes more than code: a release, documentation and a safe supply chain."
        },
        "titleSay": "Release and open source: four skills for TYPO3 v14.",
        "skills": [
            {
                "name": "typo3-initial-release",
                "prompt": {
                    "text": "We are ready to ship. What has to be in place before I tag 1.0.0?",
                    "say": "Prepares the first public 1.0.0 release of an extension for TER or Packagist."
                },
                "headline": "typo3-initial-release: <em>a 1.0.0 that keeps its promise</em>",
                "cool": "The tag comes last, only after metadata, CI, documentation, labels and a final review all agree.",
                "why": "Updating version strings is the smallest part of a release."
            },
            {
                "name": "typo3-docs",
                "prompt": {
                    "text": "Create documentation for this TYPO3 extension from scratch. It has PHP classes, TCA configuration, and a backend module but no Documentation/ directory yet.",
                    "say": "Writes extension documentation that renders on docs.typo3.org, from guides.xml to screenshots."
                },
                "headline": "typo3-docs: <em>documentation that renders</em>",
                "cool": "It follows the rules of docs.typo3.org: guides.xml instead of Settings.cfg, a permalink anchor before every heading, sentence case.",
                "why": "A wrong namespace in guides.xml stops a manual from rendering, so the skill generates that file instead of typing it."
            },
            {
                "name": "typo3-core-contributions",
                "prompt": {
                    "text": "Prepare a patch for this TYPO3 Core bug from Forge and push it to Gerrit as work in progress.",
                    "say": "Helps contribute to TYPO3 Core: Forge issues, Gerrit patches, cherry-picks and CI logs."
                },
                "headline": "typo3-core-contributions: <em>patches for the Core</em>",
                "cool": "It proves each test fails without the fix, and reads the actual CI logs before the next push.",
                "why": "Gerrit has its own rules, from work-in-progress pushes to keeping the Change-Id."
            },
            {
                "name": "enterprise-readiness",
                "prompt": {
                    "text": "Assess this project for enterprise readiness and identify gaps.",
                    "say": "Assesses production readiness: supply-chain security, CI/CD hardening and quality gates."
                },
                "headline": "enterprise-readiness: <em>a supply chain you can show</em>",
                "cool": "It scores, lists the gaps by severity, applies the fixes and scores again, so the difference is visible.",
                "why": "It makes the build and signing of a release traceable, from SHA-pinned actions to signed artefacts."
            }
        ]
    }
];

export default Object.fromEntries(GROUPS.map((group) => skillGroupFilm({
    ...group,
    skills: group.skills.map(({ name, prompt, headline, cool, why }) => (FOOTAGE[name]
        ? { name, prompt, shot: { ...FOOTAGE[name], head: headline } }
        : { name, prompt, does: { head: headline, items: [[cool], [why]], say: why } })),
})));
