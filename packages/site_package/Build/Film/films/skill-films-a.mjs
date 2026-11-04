/**
 * Skill films 1–5 (skillFilm from ./skill-film.mjs): typo3-upgrade-run, typo3-wcag22-aa-agentic,
 * typo3-webmcp, typo3-structured-data, typo3-backend-rights. Plan 2026-11-04, sections 6.4 and 6.5.
 *
 * Real runs only (WP6d, 2026-11-04). Every terminal line below is copied from a run whose output
 * was kept in ~/.cache/lab-films/skills/<skill>/; the "You say" prompts are the skills' own eval
 * prompts (skills/<name>/evals/evals.json in github.com/dirnbauer/typo3-skills). Sources:
 *
 *   upgrade-run       `t3u init/graph-init/graph-next/node-brief` (harness at typo3-skills f31694e) in a
 *                     scratch folder against the fakeshop DDEV project; the result is the SUMMARY.md the
 *                     harness (`t3u report --loop 620`, pinned harness-39f22d8) regenerates from the JSON
 *                     reports of the archived fakeshop run 3 (12.4 → 14.3, final proof epoch 3).
 *   wcag22-aa-agentic `./Skills.sh scaffold`, then `npm run a11y:all` against nine pages of the lab's Odera
 *                     template site (49 s); the result is the generated .a11y/report.md.
 *   webmcp            fakeshop DDEV (TYPO3 14.3) in Chrome 156 with --enable-features=WebMCPTesting:
 *                     document.modelContext.getTools() and executeTool(prepare_error_report); the clip
 *                     wp6d-webmcp-form records the tool filling the visible form, every POST aborted.
 *   structured-data   the skill's evidence gate (fetch, parse without recovery, compare with the visible
 *                     page) on seven English fakeshop pages; the result is the Schema.org Validator.
 *   backend-rights    the skill's read-only audit-backend-rights.php on the lab plus two jq views of
 *                     its JSON; the result is the lab's Backend Users module, groups view.
 *
 * Stills live in <cache>/footage/stills/wp6d/, the clip in <cache>/footage/wp6d-webmcp-form/.
 */

import { skillFilm } from './skill-film.mjs';

const ok = (text) => ['success', text];
const dim = (text) => ['muted', text];

/**
 * skillFilm() plus typing speeds for the "You say" and "It works" terminals (scenes 2 and 3), the
 * page placement (plan 6.3, right after the detail page's header section) and a description that
 * says where the demo was recorded (render-films.mjs would say "recorded in this TYPO3 v14 lab").
 */
function film({ promptMs, workMs, recorded, ...options }) {
    const [id, spec] = skillFilm(options);
    if (promptMs) spec.scenes[2].typeMs = promptMs;
    if (workMs) spec.scenes[3].typeMs = workMs;
    spec.placements = [{ slug: spec.slug }];
    spec.video = { description: `<p>${options.sub}. ${recorded}. Narrated, with English captions.</p>` };
    return [id, spec];
}

export default Object.fromEntries([
    film({
        slug: 'typo3-upgrade-run',
        recorded: 'Recorded from real runs: the t3u harness on a scratch run and the final proof of a real TYPO3 12.4 to 14.3 update',
        workMs: 30,
        promptMs: 30,
        sub: 'The upgrade as a sealed evidence graph',
        hook: 'A TYPO3 update succeeds when nobody notices. Proving that by hand is slow.',
        prompt: {
            text: 'Update this TYPO3 12 project to 14.3 in DDEV and make sure the site looks exactly the same afterwards.',
            say: 'You ask for the update, with nothing visibly changed.',
        },
        work: {
            title: 'Claude Code · scratch run for fakeshop',
            prompt: 't3u init --base-url https://fakeshop.ddev.site --ddev-project fakeshop && t3u graph-init && t3u graph-next',
            lines: [
                ok('[t3u] ✓ Initialised /Users/dirnbauer/.cache/lab-films/skills/upgrade-run/acme/.typo3-update (run 2026-11-04-fakeshop, origin https://fakeshop.ddev.site; runtime unclassified)'),
                dim('[t3u] · Project layout: Composer root . (default), web dir public (default), sites config/sites, CLI vendor/bin/typo3 (not found)'),
                dim('[t3u] · Next: graph-init, complete the read-only P00 branches, write runtime-size.json, runtime-seal, then close intake-join.'),
                ok('[t3u] ✓ run 2026-11-04-fakeshop initialised'),
                ok('[t3u] ✓ Upgrade graph initialised with 72 nodes and 206 edges.'),
                ok('[t3u] ✓ graph initialised'),
                '[t3u] · intake -> typo3-upgrade-intake [machine-load]',
                ok('[t3u] ✓ 1 node(s) ready'),
                dim('$ t3u node-brief --node intake'),
                '# Node brief: intake (P00)',
                '',
                'Owner: typo3-upgrade-intake — load this skill for the procedure',
                'Status: ready — openable',
                'Objective: Confirm the single project, its local DDEV clone and the authorized dataset source before any branch starts.',
                'Done when: Project root, remote, branch, HEAD, DDEV name and the dataset decision are recorded and nothing was mutated.',
                'Evidence: nodes/intake/evidence.md',
                'Mutation: none',
            ],
            highlight: ['72 nodes and 206 edges', '1 node(s) ready', 'Mutation: none'],
            say: 't3u seals a 72-node graph and hands out one node at a time.',
        },
        result: {
            image: 'stills/wp6d/upgrade-run-summary.png',
            url: 'fakeshop run 3 · loops/620-invariance-final-proof-e3/SUMMARY.md',
            tag: 'Final proof',
            focus: [0.04, 0.12, 0.92, 0.46],
            max: 1.3,
            marks: [[0.195, 0.466, 0.265, 0.048]],
            marksAt: 2200,
            say: 'The final proof of a real run: 360 screenshots, no undeclared difference.',
        },
        guardrail: {
            items: [['A person accepts the proof', 'closure-verify records it and leaves acceptance to a person'], ['Nothing deploys to live', 'local DDEV only']],
            say: 'A person accepts the result. Nothing deploys to live.',
        },
        alt: 'Film, {duration} seconds: the typo3-upgrade-run agent skill. The t3u harness seals a 72-node upgrade graph for a DDEV project '
            + 'and hands out the first node; the final proof of a real 12.4 to 14.3 run compares 60 URLs and 360 screenshots. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-wcag22-aa-agentic',
        recorded: 'Recorded from a real scan of nine pages of this lab’s Odera template site',
        promptMs: 30,
        sub: 'WCAG 2.2 AA scans, a report and a statement draft',
        hook: 'The European Accessibility Act is law. Checking every page by hand is slow.',
        prompt: {
            text: 'Scan our DDEV site at https://acme.ddev.site and tell me everything that fails WCAG 2.2 AA, page by page.',
            say: 'You ask for every WCAG failure, page by page.',
        },
        work: {
            title: 'Claude Code · acme (the lab’s Odera template site)',
            prompt: 'npm run a11y:all',
            lines: [
                'Discovered 9 URLs -> .a11y/url-inventory.json',
                'Template/static findings: 2 -> .a11y/template-lint.md',
                '',
                'Running 9 tests using 1 worker',
                '',
                ok('  ✓  1 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/ (9.6s)'),
                ok('  ✓  2 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/faq/ (7.3s)'),
                ok('  ✓  3 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/book/ (7.4s)'),
                ok('  ✓  4 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/team/ (2.0s)'),
                ok('  ✓  5 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/prices/ (2.6s)'),
                ok('  ✓  6 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/journal/ (2.4s)'),
                ok('  ✓  7 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/treatments/ (8.3s)'),
                ok('  ✓  8 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/your-visit/ (3.1s)'),
                ok('  ✓  9 [chromium-desktop] › tests/accessibility.spec.js:42:3 › WCAG 2.2 AA automated scan: /templates/odera/new-patients/ (2.9s)'),
                '',
                ok('  9 passed (46.7s)'),
                'Report written: .a11y/report.md (1 axe issue types)',
                'Statement draft written: .a11y/accessibility-statement-draft.de.md',
            ],
            highlight: ['9 passed (46.7s)', 'Report written: .a11y/report.md', 'Statement draft written'],
            say: 'Playwright and axe-core scan nine pages in twenty-nine states, in under a minute.',
        },
        result: {
            image: 'stills/wp6d/wcag-report.png',
            url: 'acme/.a11y/report.md',
            tag: 'Report',
            focus: [0.03, 0.05, 0.8, 0.52],
            max: 1.4,
            say: 'The report lists each violation by page and drafts the accessibility statement.',
        },
        guardrail: {
            items: [['Evidence, not a conformance claim', 'never complete WCAG conformance from scans alone'], ['A person reviews the rest', 'alt text, keyboard, screen reader, legal wording']],
            say: 'Scans are evidence, not a conformance claim. A person reviews the rest.',
        },
        alt: 'Film, {duration} seconds: the typo3-wcag22-aa-agentic agent skill. Playwright and axe-core scan nine pages of a TYPO3 template site '
            + 'in 29 states; the generated report lists each violation by page and the theme files, with a draft accessibility statement. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-webmcp',
        recorded: 'Recorded on a TYPO3 14.3 site in DDEV, in Chrome with WebMCP testing switched on. Nothing was sent',
        workMs: 30,
        promptMs: 30,
        sub: 'Search, navigation and forms as typed browser tools',
        hook: 'Browser agents now shop and book. Most sites give them nothing to hold.',
        prompt: {
            text: 'Make the search and contact journey on this TYPO3 14 website available to browser agents through WebMCP.',
            say: 'You ask for search and contact as browser tools.',
        },
        work: {
            title: 'Claude Code · fakeshop (TYPO3 14.3, DDEV)',
            prompt: 'node webmcp-tools.mjs https://fakeshop.ddev.site/en/contact-us/report-an-error/',
            lines: [
                dim('Chrome 156.0.8078.80 · --enable-features=WebMCPTesting'),
                'document.modelContext.getTools(): 4 tools on /en/contact-us/report-an-error/',
                '  navigate_site          read-only   in: page',
                '  prepare_error_report   fills form  in: name, email, description',
                '  search_faq             read-only   in: query',
                '  switch_language        read-only   in: language',
            ],
            highlight: ['4 tools', [3, 2, 22]],
            say: 'Chrome now sees four tools: navigation, FAQ search, language and a form helper.',
        },
        result: {
            clip: 'wp6d-webmcp-form',
            url: 'fakeshop.ddev.site/en/contact-us/report-an-error/',
            tag: 'Chrome',
            segments: [[700, 11000, 1.15]],
            camera: [[700, [0.1, 0.0, 0.62, 0.9]], [2200, [0.1, 0.0, 0.62, 0.9]], [3600, [0.1, 0.42, 0.6, 0.55]], [6900, [0.1, 0.42, 0.6, 0.55]], [8200, [0.1, 0.3, 0.62, 0.7]]],
            say: 'An agent fills the form. The consent box stays empty; a person sends it.',
        },
        guardrail: {
            items: [['A schema is not consent', 'the visitor ticks the box and sends the form'], ['The tool sends nothing', 'prepare_error_report answers submitted: false']],
            say: 'A schema is not consent. People stay in charge.',
        },
        alt: 'Film, {duration} seconds: the typo3-webmcp agent skill. Chrome with WebMCP testing lists four browser tools on a TYPO3 14 site, '
            + 'and the form tool fills an error report while the consent box stays empty and nothing is sent. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-structured-data',
        recorded: 'Recorded on a TYPO3 14.3 site in DDEV and in the Schema.org Validator',
        workMs: 30,
        promptMs: 30,
        sub: 'One JSON-LD graph from the records that render the page',
        hook: 'AI answers need markup that matches the page. Many sites keep a hidden second truth.',
        prompt: {
            text: 'The old TYPO3 site has no JSON-LD. Add Organization, breadcrumbs and article schema before capturing the upgrade baseline so the final comparison is green.',
            say: 'You ask for markup before the upgrade baseline.',
        },
        work: {
            title: 'Claude Code · fakeshop (TYPO3 14.3, DDEV)',
            prompt: 'node check-jsonld.mjs https://fakeshop.ddev.site/ /en/ /en/faq/faq-how-to/ /en/tips/marketplaces/ /en/contact-us/report-an-error/',
            lines: [
                ok('✓ /en/                            Organization WebSite WebPage               en-GB'),
                ok('✓ /en/faq/faq-how-to/             Organization WebSite WebPage Breadcrumb(3) en-GB'),
                ok('✓ /en/tips/marketplaces/          Organization WebSite WebPage Breadcrumb(3) en-GB'),
                ok('✓ /en/contact-us/report-an-error/ Organization WebSite WebPage Breadcrumb(2) en-GB'),
                '',
                '4 pages, 15 nodes, 1 Organization @id, 0 with problems',
            ],
            highlight: ['Breadcrumb(3)', '1 Organization @id', '0 with problems'],
            say: 'The skill keeps the upgrade identical, adds the graph afterwards, and checks every page.',
        },
        result: {
            image: 'stills/wp6d/structured-data-validator.png',
            url: 'validator.schema.org',
            tag: 'Validator',
            camera: [[0.06, 'full'], [0.42, [0.5, 0.15, 0.5, 0.42]], [1, [0.5, 0.15, 0.5, 0.42]]],
            max: 1.9,
            marks: [[0.832, 0.296, 0.128, 0.04]],
            marksAt: 3200,
            say: 'The validator finds no errors. The graph says what the page shows.',
        },
        guardrail: {
            items: [['No invented facts', 'every value comes from the visible page'], ['Eligible, never promised', 'valid markup earns eligibility, not a rich result']],
            say: 'No invented facts. Valid markup earns eligibility, not a promise.',
        },
        alt: 'Film, {duration} seconds: the typo3-structured-data agent skill. It keeps an upgrade identical, then checks one JSON-LD graph per page '
            + 'against the visible page on seven pages of a TYPO3 14 site; the Schema.org Validator reports no errors. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-backend-rights',
        recorded: 'Recorded from a read-only audit of this TYPO3 v14 lab and its Backend Users module',
        promptMs: 30,
        workMs: 30,
        sub: 'Editor groups you can verify',
        hook: 'Editor rights grow for years. One wrong click, and the admin is locked out.',
        prompt: {
            text: 'Create one main backend editor role with simple subgroups that can manage all normal content, news records, and files.',
            say: 'You ask for one main editor role with subgroups.',
        },
        work: {
            title: 'Claude Code · webconsulting-typo3-lab (DDEV)',
            prompt: 'ddev exec php /dev/stdin --pretty < audit-backend-rights.php > audit.json',
            lines: [
                dim('$ jq -c \'{groups: [.groups[].title], admins: (.enabled_administrators|length), findings: (.findings|length)}\' audit.json'),
                '{"groups":["main","Flue Agents (sandboxed)","Vault provisioning"],"admins":4,"findings":56}',
                dim('$ jq -r \'.groups[0].findings[].code\' audit.json | head -10'),
                'empty-explicit-ctype-allow-list',
                'used-editorial-ctypes-not-allowed',
                'required-leaf-groups-missing',
                'main-group-has-direct-permissions',
                'workspace-live-access-missing',
                'editor-group-not-in-custom-workspace',
                'installed-editor-modules-missing',
                'installed-editor-tables-missing',
                'used-page-types-missing',
                'site-root-mounts-missing',
            ],
            highlight: ['"findings":56', 'required-leaf-groups-missing', 'site-root-mounts-missing'],
            say: 'A read-only audit reads every group, module and page permission, and prints JSON.',
        },
        result: {
            image: 'stills/wp6d/backend-rights-groups.png',
            aspect: 16 / 9,
            url: 'webconsulting-typo3-lab.ddev.site/typo3/module/users/management',
            tag: 'Backend',
            focus: [0.16, 0.2, 0.6, 0.52],
            max: 1.6,
            say: 'The lab fails: three groups, no leaves, 56 findings.',
        },
        guardrail: {
            items: [['A working administrator always stays', 'never demote the current working administrator'], ['Verified, not copied', 'the audit runs again, --strict, after the change']],
            say: 'A working administrator always stays. A lockout costs more than any permission gain.',
        },
        alt: 'Film, {duration} seconds: the typo3-backend-rights agent skill. A read-only audit of the TYPO3 lab prints its editor model as JSON: '
            + 'three groups, four administrators and 56 findings, and the Backend Users module shows the groups. Narrated, with English captions.',
    }),
]);
