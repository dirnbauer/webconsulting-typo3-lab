/**
 * Skill films 6–10 (skillFilm from ./skill-film.mjs): typo3-content-blocks, typo3-shadcn-content-elements,
 * typo3-visual-editor, typo3-rector, typo3-records-list-types. Plan 2026-11-04, sections 6.4 and 6.5.
 *
 * Real runs only (WP6e, 2026-11-04). Every terminal line below is copied from a run whose output was
 * kept in ~/.cache/lab-films/wp6e/<skill>/; the "You say" prompts are the skills' own eval prompts
 * (skills/<name>/evals/evals.json in github.com/dirnbauer/typo3-skills at f31694e). Sources:
 *
 *   content-blocks    `make:content-block` (Content Blocks 2.4) in the lab's DDEV web container into the site
 *                     package, then config.yaml, template and preview written by the skill (existing fields
 *                     only, so no schema change); the clip wp6e-cb-wizard records the content wizard on a
 *                     scratch page of the lab's "TYPO3 v14 blog" site and a real save. Block, page and
 *                     element were removed afterwards.
 *   shadcn            the skill's read-only audit-content-elements.php on packages/desiderio (`jq .summary`)
 *                     and its raw-colour scan from references/styling-token-contract.md; the clip
 *                     wp6e-shadcn-presets switches body[data-shadcn-preset] on the pricing examples page and
 *                     turns on dark mode with the header's own colour-scheme switch (visitor only).
 *   visual-editor     a still of the real header-section template (f:render.text on three fields); the clip
 *                     wp6e-ve-inline types into a headline in the Visual Editor on a scratch page of the
 *                     Desiderio site and saves it (page and element removed afterwards).
 *   rector            Rector and Fractor (TYPO3 14 sets) on a `git archive` copy of powermail_cond at 197bead,
 *                     its TYPO3 12 revision, outside every repository: dry run (13 files), apply, dry run
 *                     (1 file: the upgrade wizard Rector generated), apply, dry run ("Rector is done!");
 *                     Fractor only reformatted two XLIFF files. The result still shows the output of
 *                     the later runs, with the remaining diff.
 *   records-list      a still of records_list_examples/Configuration/page.tsconfig; the clip
 *                     wp6e-records-views switches the Records module on the lab's News Data folder (page 44)
 *                     from the table to Grid, Timeline and Catalog (the user setting was restored).
 *
 * Stills live in <cache>/footage/stills/wp6e/, the clips in <cache>/footage/wp6e-*.
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
        slug: 'typo3-content-blocks',
        music: 'bright',
        recorded: 'Recorded in this TYPO3 v14 lab: make:content-block in DDEV, then the new element in the content wizard of a test page, saved for real and removed afterwards',
        promptMs: 30,
        workMs: 30,
        sub: 'An element in one YAML file',
        hook: 'A new element once meant TCA, SQL, TypoScript and a template.',
        prompt: {
            text: 'I need a teaser element with a headline, an image and a link. Set up the definition for it.',
            say: 'You ask for a teaser: headline, image, link.',
        },
        work: {
            title: 'Claude Code · webconsulting-typo3-lab (DDEV)',
            prompt: 'vendor/bin/typo3 make:content-block --content-type=content-element --vendor=acme --name=teaser --title=Teaser --extension=site_package -n',
            lines: [
                ok('Successfully created new Content Block "acme/teaser" inside site_package.'),
                dim('$ tree ContentBlocks/ContentElements/teaser'),
                'ContentBlocks/ContentElements/teaser',
                '├── assets',
                '│   └── icon.svg',
                '├── config.yaml',
                '├── language',
                '│   └── labels.xlf',
                '└── templates',
                '    ├── backend-preview.fluid.html',
                '    └── frontend.fluid.html',
                dim('$ cat ContentBlocks/ContentElements/teaser/config.yaml'),
                'name: acme/teaser',
                'typeName: acme_teaser',
                'title: Teaser',
                'fields:',
                '  - identifier: header',
                '    useExistingField: true',
                '  - identifier: image',
                '    useExistingField: true',
                '    maxitems: 1',
                '  - identifier: header_link',
                '    useExistingField: true',
                '    label: Link',
            ],
            highlight: ['Successfully created new Content Block "acme/teaser"', '├── config.yaml', 'identifier: image', 'identifier: header_link'],
            say: { show: 'make:content-block creates the block; the skill writes fields, template and preview.', say: 'make content block creates the block; the skill writes fields, template and preview.' },
        },
        result: {
            clip: 'wp6e-cb-wizard',
            url: 'webconsulting-typo3-lab.ddev.site/typo3/module/web/layout',
            tag: 'Backend',
            // wizard search and result, the form typed and saved (fast), the save message, the page module preview
            segments: [[6400, 9900, 2], [13700, 23800, 4], [61000, 61900, 1], [68300, 71000, 1.1]],
            camera: [[6400, [0.17, 0.0, 0.66, 0.5]], [9800, [0.17, 0.0, 0.66, 0.5]], [13700, 'full'], [68300, [0.42, 0.3, 0.58, 0.62]]],
            min: 7600,
            say: 'The teaser shows up in the wizard. An editor fills it in and saves.',
        },
        guardrail: {
            items: [['Identifiers stay stable', 'a renamed field leaves records that look empty'], ['One config.yaml per block', 'TCA, SQL and forms are generated from it']],
            say: 'A renamed field leaves records that look empty.',
        },
        alt: 'Film, {duration} seconds: the typo3-content-blocks agent skill. make:content-block creates a teaser block in a TYPO3 v14 project, '
            + 'config.yaml lists its headline, image and link fields, and the new element appears in the content wizard and is saved. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-shadcn-content-elements',
        background: 'mesh',
        music: 'bright',
        recorded: 'Recorded in this TYPO3 v14 lab: the skill’s read-only audit of the Desiderio elements, then a pricing page with the preset switched in the browser and dark mode turned on with the header switch',
        promptMs: 30,
        workMs: 30,
        sub: 'shadcn presets as Fluid elements with semantic tokens',
        hook: { head: 'shadcn/ui ships as React. TYPO3 renders Fluid.', say: 'shad C N ships as React. TYPO3 renders Fluid.' },
        prompt: {
            text: 'These elements have hardcoded hex colours everywhere. Move them to semantic tokens.',
            say: 'You ask to move hardcoded colours to semantic tokens.',
        },
        work: {
            title: 'Claude Code · webconsulting-typo3-lab',
            prompt: 'php ~/projects/typo3-skills/skills/typo3-shadcn-content-elements/scripts/audit-content-elements.php packages/desiderio | jq .summary',
            lines: [
                '{',
                '  "elements": 244,',
                '  "fieldsMissingDefaults": 0,',
                '  "linkModelIssues": 0,',
                '  "missingConfig": 0,',
                '  "missingFrontend": 0,',
                '  "missingIcon": 0,',
                '  "missingLabels": 0,',
                '  "missingPreview": 0,',
                '  "templatesWithCollectionIssues": 27,',
                '  "templatesWithUndeclaredTopFields": 227,',
                '  "templatesWithUnusedTopFields": 0',
                '}',
                dim('$ cd packages/desiderio && rg -n "#[0-9a-fA-F]{3,8}\\b|rgba?\\(|hsla?\\(|hsl\\(|oklch\\(" ContentBlocks/ContentElements -g \'*.css\' -g \'*.html\' -g \'*.js\' -g \'*.svg\' | grep -vc \'&#\''),
                ok('0'),
            ],
            highlight: ['"elements": 244', '"missingIcon": 0', '"missingPreview": 0', [14, 0, 1]],
            say: 'The audit checks all 244 elements, and the colour scan finds no raw value left.',
        },
        result: {
            clip: 'wp6e-shadcn-presets',
            url: 'webconsulting-typo3-lab.ddev.site/content-types/plans-pricing/',
            tag: 'Frontend',
            // the site preset, three preset switches, then the header's colour-scheme switch to dark
            segments: [[900, 6900, 1.3], [8200, 12600, 1.5]],
            min: 7600,
            say: 'Switch the preset, switch to dark mode: every element follows.',
        },
        guardrail: {
            items: [['No React at runtime', 'tokens and class contracts are ported into Fluid'], ['Tokens, not colours', 'presets and dark mode reach every element']],
            say: 'No React at runtime. Tokens, not colours, in every template.',
        },
        alt: 'Film, {duration} seconds: the typo3-shadcn-content-elements agent skill. Its audit checks 244 TYPO3 content elements, a colour scan '
            + 'finds no raw value in their templates, and a pricing page follows a preset switch and dark mode. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-visual-editor',
        music: 'bright',
        recorded: 'Recorded in this TYPO3 v14 lab: a real Desiderio template, then the Visual Editor on a test page, saved for real and removed afterwards',
        promptMs: 30,
        sub: 'Templates ready for editing on the page',
        hook: 'A typo on the page meant finding the record in the backend, opening its form and saving.',
        prompt: {
            text: 'Editors want to click into the page and type, instead of opening backend forms.',
            say: 'You ask for editing on the page.',
        },
        work: {
            image: 'stills/wp6e/ve-header-section.png',
            url: 'packages/desiderio/ContentBlocks/ContentElements/header-section/templates/frontend.html',
            tag: 'Fluid',
            focus: [0.04, 0.16, 0.96, 0.52],
            max: 1.25,
            say: { show: 'The skill traces each output to its record and field; f:render.text makes it editable.', say: 'The skill traces each output to its record and field; render text makes it editable.' },
        },
        result: {
            clip: 'wp6e-ve-inline',
            url: 'webconsulting-typo3-lab.ddev.site/typo3/module/web/edit',
            tag: 'Visual Editor',
            // pointer to the headline, click, type, Save, saved
            segments: [[1600, 13000, 1.45]],
            camera: [[1600, 'full'], [3600, [0.06, 0.1, 0.88, 0.42]], [13000, [0.06, 0.1, 0.88, 0.42]]],
            say: 'In the Visual Editor: click the headline, type, save. The record is updated.',
        },
        guardrail: {
            items: [['Tested as an editor', 'never judge readiness from admin-only testing'], ['Traceable output', 'every editable text maps to a record and a field']],
            say: 'Readiness is tested as an editor, never only as admin.',
        },
        alt: 'Film, {duration} seconds: the typo3-visual-editor agent skill. A Desiderio Fluid template renders three fields with f:render.text, '
            + 'and in the TYPO3 Visual Editor a headline is typed into on the page and saved. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-rector',
        recorded: 'Recorded from real Rector and Fractor runs on a throwaway copy of a TYPO3 12 revision of powermail_cond; nothing was applied to any repository',
        promptMs: 30,
        workMs: 30,
        sub: 'Deprecated calls fixed by tools, twice checked',
        hook: 'TYPO3 12 code calls APIs that version 14 removed.',
        prompt: {
            text: 'Automatically fix the deprecated PHP calls in Classes/ for TYPO3 v14.',
            say: 'You ask for the deprecated PHP calls to be fixed.',
        },
        work: {
            title: 'Claude Code · powermail_cond 197bead (scratch copy)',
            prompt: 'vendor/bin/rector process --dry-run',
            lines: [
                '13 files with changes',
                '=====================',
                '1) ext_localconf.php:1',
                ' * MigrateAddPageTSConfigToPageTsConfigFileRector',
                ' * MigratePluginContentElementAndPluginSubtypesRector',
                ' * DropFifthParameterForExtensionUtilityConfigurePluginRector',
                '3) Classes/Controller/ConditionController.php:92',
                ' * MigrateTypoScriptFrontendControllerFeUserMethodsRector',
                '11) Configuration/TCA/tx_powermailcond_domain_model_condition.php:27',
                ' * MigrateCoreTcaAndUserSettingsShowitemStringsToShortFormReferencesRector',
                ok(' [OK] 13 files would have been changed (dry-run) by Rector'),
                '',
                dim('$ vendor/bin/fractor process --dry-run'),
                '2 files with changes',
                '1) Resources/Private/Language/de.locallang_db.xlf',
                '    ~ reformatted / normalized',
                '2) Resources/Private/Language/locallang_db.xlf',
                '    ~ reformatted / normalized',
                ok(' [OK] 2 files would have been changed (dry-run) by Fractor'),
            ],
            highlight: ['13 files with changes', 'MigrateTypoScriptFrontendControllerFeUserMethodsRector', 'DropFifthParameterForExtensionUtilityConfigurePluginRector', '2 files would have been changed'],
            say: 'The dry run lists 13 files and the rule for each change. Fractor tidies two label files.',
        },
        result: {
            image: 'stills/wp6e/rector-second-run.png',
            url: 'powermail_cond 197bead · scratch copy',
            tag: 'Dry run',
            focus: [0.04, 0.3, 0.92, 0.66],
            max: 1.3,
            marks: [[0.06, 0.885, 0.3, 0.05]],
            marksAt: 3600,
            say: 'Apply, dry-run again: one generated file left. Once more: Rector is done.',
        },
        guardrail: {
            items: [['One applied pass is not completion', 'dry run, review, apply, dry run again'], ['Nothing applied to a repository', 'the runs used a throwaway copy']],
            say: 'One applied pass is not completion. The clean dry run is.',
        },
        alt: 'Film, {duration} seconds: the typo3-rector agent skill with typo3-fractor. A dry run on a TYPO3 12 extension lists 13 files with their Rector rules, '
            + 'Fractor reformats two label files, and after applying, a second dry run finds one generated file and a third reports Rector is done. Narrated, with English captions.',
    }),
    film({
        slug: 'typo3-records-list-types',
        music: 'bright',
        recorded: 'Recorded in this TYPO3 v14 lab: the TSconfig of the Records List Examples package, then the Records module on the news folder',
        promptMs: 30,
        sub: 'Cards, timelines and catalogues through TSconfig',
        hook: 'The Records module shows a plain table, even for news with photos.',
        prompt: {
            text: 'The Records module shows a plain table. Can I get cards with thumbnails instead?',
            say: 'You ask for cards with thumbnails instead of the table.',
        },
        work: {
            image: 'stills/wp6e/records-tsconfig.png',
            url: 'packages/records_list_examples/Configuration/page.tsconfig',
            tag: 'TSconfig',
            focus: [0.04, 0.06, 0.96, 0.46],
            max: 1.25,
            say: 'The extension adds the views. TSconfig decides which ones each page allows.',
        },
        result: {
            clip: 'wp6e-records-views',
            url: 'webconsulting-typo3-lab.ddev.site/typo3/module/content/records',
            tag: 'Records',
            // table, then the View menu's Grid, Timeline and Catalog
            segments: [[900, 2500, 1], [7900, 10700, 1.4], [16800, 19600, 1.4], [25900, 29000, 1.4]],
            camera: [[900, [0.38, 0.12, 0.6, 0.62]], [29000, [0.38, 0.12, 0.6, 0.62]]],
            max: 1.6,
            min: 7800,
            say: 'Grid, timeline, catalogue: editors switch views, and nothing in core is patched.',
        },
        guardrail: {
            items: [['Configure, do not patch', 'views come from TSconfig and Fluid templates'], ['Accessibility is evidenced', 'not assumed']],
            say: 'Configure, do not patch. Accessibility is evidenced, not assumed.',
        },
        alt: 'Film, {duration} seconds: the typo3-records-list-types agent skill. Page TSconfig adds view types to the TYPO3 v14 Records module, '
            + 'and editors switch a news folder from the table to grid, timeline and catalogue views. Narrated, with English captions.',
    }),
]);
