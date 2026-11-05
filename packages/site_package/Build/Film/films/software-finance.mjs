/**
 * Films of the software pages /software/medatlas and /software/capex-one
 * (featureFilm, section 'software', eyebrow 'Software by webconsulting';
 * backgrounds pulse and grid, bed focus). Each adds one `stats` scene to the
 * featureFilm shape, where docs/plans/2026-11-04-software-ai-features-plan.md
 * 6.4 places it.
 *
 * MedAtlas: the statistics page is the showpiece (the user, 2026-11-04). The
 * three dark statistics screenshots open the film behind hook and title; the
 * data report runs as tiles, then a wide frame pushes into the 24-month chart
 * and one into the reasons and median panels. Figures: the import of
 * 4 November 2026, 10:10 (var/research/medatlas-figures-2026-11-04.md, the
 * same numbers as the page's statistics section); keep tiles and narration in
 * step when the page's figures change.
 *
 * CapexOne Plus: positioning from capex-one.com; the stats scene is the
 * shared-savings model of decision D14, a target model and pricing approach,
 * never a measured result or a guarantee.
 */

import { SHOTS, featureFilm } from './feature-film.mjs';

const film = (definition) => featureFilm({ section: 'software', eyebrow: 'Software by webconsulting', music: 'focus', ...definition });

/** Inserts `scene` into a featureFilm spec before the shot whose eyebrow is `before`. */
function insert([id, spec], scene, before) {
    const at = spec.scenes.findIndex((s) => s.type === 'shot' && s.eyebrow === before);
    spec.scenes.splice(at < 0 ? spec.scenes.length - 1 : at, 0, scene);
    return [id, spec];
}

/** Applies `extra` to the shot whose eyebrow is `eyebrow`. */
function tune([id, spec], eyebrow, extra) {
    Object.assign(spec.scenes.find((s) => s.type === 'shot' && s.eyebrow === eyebrow), extra);
    return [id, spec];
}

const MED = {
    statistics: 'Frontend/frontend-feature-medatlas-statistics-dark-5dad0c48.webp',
    chart: 'Frontend/frontend-feature-medatlas-statistics-chart-dark-10b6c4c7.webp',
    reasons: 'Frontend/frontend-feature-medatlas-statistics-reasons-dark-4e772802.webp',
};
/** The 24-month column chart and the reasons / median panels, as fractions of the screenshot. */
const CHART = [0.0, 0.43, 0.62, 0.5];
const REASONS = [0.0, 0.0, 0.5, 0.52];
const MEDIAN = [0.515, 0.19, 0.485, 0.4];
const AS_OF = '4 Nov 2026';

let medatlas = film({
    id: 'medatlas', page: 8231, name: 'MedAtlas Austria', background: 'pulse',
    // MedAtlas design tokens (src/design/tokens.ts): the live-cell green of the mark, the fallback blue, the restricted amber.
    accent: '#6ad895', palette: ['#35c177', '#9fb6dc', '#e9ab2b'],
    alt: 'Overview film, {duration} seconds: MedAtlas Austria, independent research on medicine shortages in Austria. '
        + 'Substance finder, next steps, the nightly data report with 622 active shortages as of 4 November 2026, '
        + 'the 24-month chart, the top reasons and alerts. Narrated, with English captions.',
    hook: { head: 'The pharmacy says: <em>not available.</em>', say: 'A child has a fever. The pharmacy says: not available.' },
    title: { sub: 'Medicines and shortages, from the public registers', key: 'Independent research project', say: 'MedAtlas shows what is short, and what has the same substance.' },
    shots: [
        { image: 'Frontend/frontend-feature-medatlas-finder-0be95a29.webp', eyebrow: 'Substance finder', head: 'Grouped by availability', focus: [0.08, 0.02, 0.84, 0.6], say: 'Search a substance, grouped by availability.' },
        { image: 'Frontend/frontend-feature-medatlas-next-steps-1d250fdd.webp', eyebrow: 'Next steps', head: 'Call, check, decide with the pharmacist', focus: [0.08, 0.62, 0.84, 0.36], say: 'Then call a pharmacy, and let doctor or pharmacist decide.' },
        { image: MED.statistics, eyebrow: 'Data report', head: 'Counted every night', say: { show: 'Counted every night: 133 new reports in October alone.', say: 'Counted every night: one hundred thirty-three new reports in October alone.' } },
        { image: MED.reasons, eyebrow: 'Reasons', head: 'Why medicines run short', say: { show: 'Top reason: manufacturing delays. Median planned outage: 272 days.', say: 'Top reason: manufacturing delays. Median planned outage: two hundred seventy-two days.' } },
        { image: 'Frontend/frontend-feature-medatlas-alerts-cb4974fb.webp', eyebrow: 'Alerts', head: 'A mail when it is back', focus: [0.08, 0.12, 0.6, 0.32], say: 'Alerts tell you when it is back.' },
    ],
    end: { head: 'Independent, <em>open data.</em>', cta: 'Book a demo', url: 'typo3-lab.webconsulting.at/software/medatlas/', note: 'Not medical advice: doctor or pharmacist decide', say: 'MedAtlas. Open data, no medical advice.' },
});
medatlas[1].placements = [{ slug: '/software/medatlas' }];
medatlas[1].video = { description: '<p>Medicines and shortages, from the public registers. A narrated tour of the MedAtlas Austria demo, figures as of 4 November 2026, with English captions. Not medical advice.</p>' };
// The screens are the product's own (local demo of medikamente.webconsulting.at), not the lab's.
for (const scene of medatlas[1].scenes) if (scene.type === 'shot') scene.url = 'medikamente.webconsulting.at';
// The film opens on the dark statistics screens, behind hook and title.
medatlas[1].scenes[0].images = [MED.chart, MED.statistics, MED.reasons].map((image) => SHOTS + image);
medatlas[1].scenes[1].image = SHOTS + MED.statistics;
// The statistics run as wide frames: first the whole report, then a slow push into the chart.
medatlas = tune(medatlas, 'Data report', { layout: 'wide', head: '', max: 1.7, camera: [[0, 'full'], [0.3, 'full'], [1, CHART]] });
medatlas = tune(medatlas, 'Reasons', { layout: 'wide', head: '', max: 2, camera: [[0, 'full'], [0.18, REASONS], [0.5, REASONS], [0.72, MEDIAN], [1, MEDIAN]] });
medatlas = insert(medatlas, {
    type: 'stats',
    chapter: 'Data report',
    eyebrow: 'The data report, every night',
    head: 'Shortages in Austria, <em>today</em>',
    tiles: [
        { value: 622, label: 'active shortages', note: `BASG register, ${AS_OF}`, cue: 'six' },
        { value: 287, label: 'substances affected', note: `BASG register, ${AS_OF}`, cue: 'two' },
        { value: 63, unit: '%', label: 'packages deliverable', note: `ELGA list, ${AS_OF}`, cue: 'one' },
        { value: 140, label: 'reports in 30 days', note: `BASG register, ${AS_OF}`, cue: 'deliverable' },
    ],
    say: {
        show: '622 shortages, 287 substances, one package in three not deliverable.',
        say: 'Six hundred twenty-two shortages, two hundred eighty-seven substances, one package in three not deliverable.',
    },
}, 'Data report');

let capexOne = film({
    id: 'capex-one', page: 8232, name: 'CapexOne Plus', background: 'grid',
    // CapexOne Plus brand (globals.css, dark): tan accent, slate, and a deep ink blue for the floor.
    accent: '#e2c2a4', palette: ['#c49a7a', '#7f9fb5', '#46557a'],
    alt: 'Overview film, {duration} seconds: CapexOne Plus checks capital projects against their business case. It shows the planner, gate '
        + 'pack, project value, invoice approval and the CFO portfolio view, and an API built for agents. The shared-savings model sets a 3 '
        + '% saving goal: one third is our fee, two thirds stay with the client, a target, not a guarantee. Narrated, with English captions.',
    hook: { head: 'Last month’s slides, <em>forty hours a gate.</em>', say: 'Last month’s slides and forty hours per gate review.' },
    title: { sub: 'Capital projects, checked live', key: 'capex-one.com', say: 'CapexOne Plus checks every capital project against its business case, live.' },
    shots: [
        { image: 'Frontend/frontend-feature-capexone-planner-1004c895.webp', eyebrow: 'Planner', head: 'Plan in minutes', focus: [0.18, 0.1, 0.56, 0.62], say: 'Describe the investment; a rule engine builds the stage-gate plan.' },
        { image: 'Frontend/frontend-feature-capexone-gate-pack-c6e1c51d.webp', eyebrow: 'Gate pack', head: 'The gate pack builds itself', focus: [0.18, 0.6, 0.53, 0.3], say: 'Before every review, the gate pack builds itself.' },
        { image: 'Frontend/frontend-feature-capexone-project-value-62b8b80a.webp', eyebrow: 'Project value', head: 'Checked against the business case', focus: [0.18, 0.2, 0.8, 0.58], say: 'NPV and IRR, against the approved case.' },
        { image: 'Frontend/frontend-feature-capexone-ledger-invoices-abe5cbcc.webp', eyebrow: 'Invoices', head: 'Technical check, arithmetic check, four eyes', focus: [0.18, 0.43, 0.8, 0.4], say: 'Every invoice passes the approval matrix before it reaches the ERP.' },
        { image: 'Frontend/frontend-feature-capexone-value-portfolio-451057e9.webp', eyebrow: 'Portfolio', head: 'The CFO sees the portfolio', focus: [0.18, 0.1, 0.8, 0.46], say: 'The CFO sees value at risk across the portfolio.' },
    ],
    points: {
        head: '“Do it <em>for the agents.</em>”',
        items: [
            ['“If you’re starting now, focus on the API.”', 'Guillermo Rauch, on X, 12 February 2026'],
            ['173 documented endpoints', 'REST API v1, API keys with roles'],
            ['The in-app agent only reads', 'Gates still need two human votes'],
        ],
        say: 'Guillermo Rauch: focus on the API. So we did: 173 documented endpoints.',
    },
    end: { head: 'Decide on <em>live numbers.</em>', cta: 'Book a demo', url: 'typo3-lab.webconsulting.at/software/capex-one/', note: 'capex-one.com', say: 'CapexOne Plus. Decide on live numbers.' },
});
capexOne[1].placements = [{ slug: '/software/capex-one' }];
capexOne[1].video = { description: '<p>Capital projects, checked live. A narrated tour of the CapexOne Plus demo with fictional data, with English captions.</p>' };
for (const scene of capexOne[1].scenes) if (scene.type === 'shot') scene.url = 'capex-one.com';
// The shared-savings model (D14), after the portfolio and before the agents.
const [, capexSpec] = capexOne;
capexSpec.scenes.splice(capexSpec.scenes.findIndex((scene) => scene.type === 'points'), 0, {
    type: 'stats',
    chapter: 'Our approach',
    eyebrow: 'Our approach: shared savings',
    head: 'You keep <em>two thirds</em>',
    tiles: [
        { value: 3, unit: '%', label: 'saving we aim for', note: 'A target, not a guarantee', cue: 'three' },
        { value: 1, unit: '%', label: 'our share, a third', note: 'paid from the saving', cue: 'third' },
        { value: 2, unit: '%', label: 'stays with you', note: 'two thirds of the saving', cue: 'two' },
    ],
    say: 'We aim to save you three percent: a third is our fee, two thirds stay with you. A goal, not a guarantee.',
});
// The Rauch scene carries its own chapter (the HUD), like the stats scene before it.
capexSpec.scenes.find((scene) => scene.type === 'points').chapter = 'For the agents';

export default Object.fromEntries([medatlas, capexOne]);
