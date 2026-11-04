/**
 * The overview film of /ai/agentic-skills (id agentic-skills; featureFilm,
 * section 'ai', background orbits, bed focus; plan 6.2 and 6.4).
 *
 * The four tour shots are the page's own (desiderio's ShowcaseFeatureDefinitions::agenticSkills(),
 * plan 5.6): the install terminal (the page's hero), the lab's skill catalogue,
 * the upgrade graph in its Safety contract view and the rules terminal. The
 * figures follow the page: 61 skills, 72 nodes and 206 edges, 22 rules (the
 * repository's count on 4 November 2026; the plan's draft said 23).
 *
 * `page` stays unset until the coordinated reseed creates /ai/agentic-skills
 * (render-films.mjs --list prints `page -`); the seeder finds the page by its
 * slug through `placements`.
 */

import { featureFilm } from './feature-film.mjs';

const REPO = 'github.com/dirnbauer/typo3-skills';
const SHOT = (name) => `Frontend/frontend-feature-skills-${name}.webp`;

/** featureFilm() plus the frame bar of each shot (repository or lab) and film.json's placement. */
function skillsFilm({ urls, ...definition }) {
    const [id, spec] = featureFilm(definition);
    spec.scenes.filter((scene) => scene.type === 'shot').forEach((scene, index) => { scene.url = urls[index]; });
    return [id, { ...spec, placements: [{ slug: spec.slug }] }];
}

export default Object.fromEntries([
    skillsFilm({
        id: 'agentic-skills', name: 'Agentic skills', section: 'ai', eyebrow: 'AI for TYPO3 v14', background: 'orbits', music: 'focus',
        accent: '#c4b5fd', palette: ['#7c5cff', '#2c5a52', '#c7863f'],
        urls: [REPO, 'typo3-lab.webconsulting.at/desiderio-corporate-starter/skills/', `${REPO} · typo3-upgrade-run`, REPO],
        alt: 'Overview film, {duration} seconds: typo3-skills, 61 open-source skills that teach coding agents how we work with TYPO3. '
            + 'The install script, the skill catalogue in this lab, the upgrade graph with 72 nodes and 206 edges, and 22 always-on rules. '
            + 'Narrated, with English captions.',
        hook: {
            head: 'Knows TYPO3 <em>in general.</em>',
            say: 'Your agent knows TYPO3, but not your project.',
        },
        title: {
            sub: '61 skills for coding agents',
            key: REPO,
            say: {
                show: 'typo3-skills: 61 skills for coding agents.',
                say: 'typo three skills: 61 skills for coding agents.',
            },
        },
        shots: [
            { image: SHOT('install-64d548d2'), eyebrow: 'Install', head: 'One script, every client', focus: [0.02, 0.04, 0.62, 0.7], say: 'One clone, one install script, every client.' },
            { image: SHOT('catalogue-ce0b02d1'), eyebrow: 'Catalogue', head: 'Searchable in TYPO3 with Skillflow', focus: [0.08, 0.02, 0.84, 0.62], say: 'Upgrades, accessibility, Solr, structured data.' },
            { image: SHOT('upgrade-graph-8578eafb'), eyebrow: 'Upgrade graph', head: '72 nodes, 206 edges', focus: [0.14, 0.08, 0.7, 0.46], say: 'Upgrades follow a sealed graph: 72 nodes, 206 edges.' },
            { image: SHOT('rules-c7be1a89'), eyebrow: 'Rules', head: '22 always-on rules', focus: [0.02, 0.04, 0.96, 0.62], say: 'Twenty-two rules, such as: no claim without evidence.' },
        ],
        end: {
            head: 'Open source. <em>Clone it today.</em>', cta: 'Get the skills', url: REPO,
            install: 'git clone https://github.com/dirnbauer/typo3-skills.git && cd typo3-skills && ./install.sh',
            note: 'MIT code, CC BY-SA 4.0 content',
            say: 'Open source. Clone it today.',
        },
    }),
]);
