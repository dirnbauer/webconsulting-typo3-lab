/**
 * The overview film of /ai/agentic-skills (id agentic-skills; featureFilm,
 * section 'ai', in the look of the home page product film: style 'home').
 *
 * Plan 2026-11-08: the hub around twelve skill groups (Desiderio 4.35.0). Shots:
 * the install terminal (the page's hero), the TYPO3 Dev Companion diagram with
 * the typo3-skills card (frontend-dev-companion-skills: Benjamin Kott's original,
 * redrawn in TYPO3 CI; the credit is in the image and on screen),
 * the twelve groups, the community talks the skills build on (MTUG, TYPO3camp
 * München), the upgrade graph and the skill search (/ai/agentic-skills/find).
 * Hub sections and the search are stills from the lab (~/.cache/lab-films/stills).
 * Figures follow the repository: 61 skills, 12 groups, 72 nodes and 206 edges.
 *
 * Placed after the superfeature box, the hub's second element.
 */

import { featureFilm } from './feature-film.mjs';

const REPO = 'github.com/dirnbauer/typo3-skills';
const LAB = 'typo3-lab.webconsulting.at/ai/agentic-skills/';
const SHOT = (name) => `Frontend/${name}.webp`;

/** featureFilm() plus the frame bar of each shot (repository or lab) and film.json's placement. */
function skillsFilm({ urls, ...definition }) {
    const [id, spec] = featureFilm(definition);
    spec.scenes.filter((scene) => scene.type === 'shot').forEach((scene, index) => { scene.url = urls[index]; });
    // After the superfeature box, the hub's second element (Desiderio 4.35.0).
    return [id, { ...spec, placements: [{ slug: spec.slug, after: { header: 'Start with the ten superfeature skills' } }] }];
}

export default Object.fromEntries([
    skillsFilm({
        id: 'agentic-skills', name: 'Agentic skills', section: 'ai', eyebrow: 'AI for TYPO3 v14', style: 'home', music: 'focus',
        urls: [REPO, `${LAB} · TYPO3 Dev Companion and typo3-skills`, LAB, LAB, `${REPO} · typo3-upgrade-run`, `${LAB}find/`],
        video: {
            header: 'Agentic skills in a minute',
            description: '<p>What the 61 open skills teach a coding agent, how they work with the TYPO3 Dev Companion, the twelve groups, the community talks they build on and the skill search. Narrated, with selectable captions.</p>',
        },
        alt: 'Overview film, {duration} seconds: typo3-skills, 61 open-source skills that teach coding agents TYPO3 v14. '
            + 'The install script, the TYPO3 Dev Companion with the typo3-skills collection, twelve skill groups, the community talks at MTUG and TYPO3camp München, '
            + 'the upgrade graph with 72 nodes and 206 edges, and the skill search. Narrated, with selectable captions.',
        hook: {
            head: 'Knows TYPO3 <em>in general.</em>',
            say: 'Your agent knows TYPO3, but not how good TYPO3 work is done.',
        },
        title: {
            sub: '61 skills in 12 groups, for every coding agent',
            key: REPO,
            say: {
                show: 'typo3-skills: 61 skills for coding agents, in 12 groups.',
                say: 'typo three skills: 61 skills for coding agents, in twelve groups.',
            },
        },
        shots: [
            { image: SHOT('frontend-feature-skills-install-64d548d2'), eyebrow: 'Install', head: 'One script, <em>every client</em>', focus: [0.02, 0.04, 0.62, 0.7], say: 'One clone, one install script: Claude Code, Codex, Cursor and Gemini.' },
            { image: SHOT('frontend-dev-companion-skills-34483469'), eyebrow: 'TYPO3 Dev Companion', head: 'TYPO3 knowledge, <em>plus our skills</em>', text: 'Original diagram © Benjamin Kott · news.typo3.com', focus: [0.0, 0.0, 1.0, 1.0], say: 'The TYPO3 Dev Companion gives your agent version-aware TYPO3 knowledge. Our skills add whole jobs on top.' },
            { image: 'stills/skills-hub-groups.png', eyebrow: 'Twelve groups', head: 'Three or four skills <em>per group</em>', focus: [0.03, 0.03, 0.94, 0.94], say: 'Twelve groups: upgrades, content, editing, design, accessibility, search and security.' },
            { image: 'stills/skills-hub-mtug.png', eyebrow: 'Community', head: 'Built on <em>community talks</em>', focus: [0.3, 0.03, 0.66, 0.94], say: 'They build on what the community teaches, at MTUG and TYPO3camp München.' },
            { image: SHOT('frontend-feature-skills-upgrade-graph-8578eafb'), eyebrow: 'Superfeature', head: 'An upgrade <em>you can prove</em>', focus: [0.14, 0.08, 0.7, 0.46], say: 'The upgrade run follows a sealed graph of 72 nodes and 206 edges.' },
            { image: 'stills/skills-find.png', eyebrow: 'Find a skill', head: 'Search, filter, <em>install</em>', focus: [0.03, 0.03, 0.94, 0.94], say: 'Find a skill in seconds: search, filter by group, copy the install line.' },
        ],
        end: {
            head: 'Open source. <em>Help us improve them.</em>', cta: 'Get the skills', url: REPO,
            install: 'git clone https://github.com/dirnbauer/typo3-skills.git && cd typo3-skills && ./install.sh',
            note: 'MIT code, CC BY-SA 4.0 content',
            say: 'Open source. Clone the skills, and help us improve them.',
        },
    }),
]);
