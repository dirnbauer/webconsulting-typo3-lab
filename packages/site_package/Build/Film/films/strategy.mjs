/**
 * The strategy film typo3-v14-strategy: on /typo3-v14-strategy after its hero
 * (film.json placements; the home placement after "Five points for all our
 * software" was removed on 2026-11-05, plan 2026-11-05 6.1; background orbits,
 * bed focus).
 *
 * Shape: hook, title, the five points in two scenes (3 + 2, the same texts as
 * the page's checklist), a grid of lab screens that run parts of it today,
 * the strategy page itself for decision D13 (the five points are the standard
 * for all our software: communicated, not an audit), end. The stills of the
 * strategy page come from desiderio's Build/Data/feature-screenshots.strategy.json.
 */

import { HOST, SHOTS } from './feature-film.mjs';

const page = (name) => `${SHOTS}Frontend/frontend-feature-strategy-${name}.webp`;
const STILL = {
    hero: page('hero-1c4f86e8'),
    points: page('points-aa57149b'),
    layers: page('layers-a6aee41c'),
    today: page('today-73b674a8'),
    roadmap: page('roadmap-087f36b1'),
    readiness: page('readiness-97123af4'),
};

export default {
    'typo3-v14-strategy': {
        name: 'Our TYPO3 v14 strategy',
        page: 1121,
        slug: '/typo3-v14-strategy',
        placements: [{ slug: '/typo3-v14-strategy' }],
        title: 'TYPO3 v14 and AI agents: strategy film',
        video: { description: '<p>Our strategy for TYPO3 v14 and AI agents, 2026 to 2030: five points that are the standard for everything we build. Narrated, with English captions.</p>' },
        url: `${HOST}/typo3-v14-strategy/`,
        accent: '#c4b5fd',
        palette: ['#7c5cff', '#2c5a52', '#c7863f'],
        music: 'focus',
        background: 'orbits',
        alt: 'Strategy film, {duration} seconds: our strategy for TYPO3 v14 and AI agents, 2026 to 2030. '
            + 'Five points: governed tools, people approve, one registry, your choice of model, governance. '
            + 'What runs in the lab today, and why the five points are the standard for all our software. Narrated, with English captions.',
        scenes: [
            {
                type: 'hook', head: 'Agents read, edit <em>and buy.</em>',
                images: [STILL.layers, STILL.today, STILL.roadmap],
                say: 'Agents now read, edit and buy on websites. Tenders ask how a CMS handles that.',
            },
            {
                type: 'title', eyebrow: 'Strategy 2026–2030', name: 'TYPO3 v14 and AI agents', sub: 'Our strategy, 2026 to 2030',
                key: `${HOST}/typo3-v14-strategy/`, image: STILL.hero,
                say: 'Our strategy for TYPO3 v14 and AI agents.',
            },
            {
                type: 'points', chapter: 'Five points', head: 'Agents work <em>within the rules</em>',
                items: [
                    ['Agents use governed tools', 'MCP, CLI and APIs, never a shared admin login', 'one'],
                    ['People approve what goes live', 'Workspaces and approval steps apply to agents too', 'two'],
                    ['One registry defines each action', 'With its scopes and a risk level', 'three'],
                ],
                say: [
                    'One: agents use governed tools, never a shared admin login.',
                    'Two: people approve what goes live.',
                    'Three: one registry defines each action and its risk.',
                ],
            },
            {
                type: 'points', chapter: 'Five points', head: 'Your choice, <em>your governance</em>',
                items: [
                    ['You choose the AI model', 'Price, location and data policy stay your decision', 'four'],
                    ['Governance is what clients buy', 'Audit trails, approvals, EU hosting', 'five'],
                ],
                say: [
                    'Four: you choose the model and where it runs.',
                    'Five: governance is what clients buy.',
                ],
            },
            {
                type: 'grid', chapter: 'In the lab', head: 'Running in this lab <em>today</em>',
                items: [
                    { image: `${SHOTS}Backend/feature-mcp-server-tools-6e2c532a.webp`, name: 'MCP Server', note: 'Tools with their rights', cue: 'server' },
                    { image: `${SHOTS}Backend/feature-typo3-abilities-catalogue-6541f127.webp`, name: 'Abilities Registry', note: 'Each action defined once', cue: 'abilities' },
                    { image: `${SHOTS}Frontend/frontend-feature-agent-nexus-agui-approval-c2465592.webp`, name: 'Agent Nexus', note: 'Five agent protocols', cue: 'agent' },
                ],
                say: 'Much of it runs here today: an MCP server, an abilities registry, agent protocols.',
            },
            {
                type: 'shot', layout: 'wide', chapter: 'For all our software', image: STILL.points, focus: [0.04, 0.05, 0.92, 0.62], max: 1.3,
                url: `${HOST}/typo3-v14-strategy/`, tag: '', eyebrow: 'Not only TYPO3', head: '',
                say: 'These five points are our standard for everything we build. Not only TYPO3.',
            },
            {
                type: 'end', head: 'Start with <em>one workflow.</em>', cta: 'Read the strategy', url: `${HOST}/typo3-v14-strategy/`,
                note: 'The standard for every product we build',
                say: 'Read the strategy, then start with one workflow.',
            },
        ],
    },
};
