/**
 * The lab overview film lab-overview: on the home page after the section
 * "Everything in this lab" (film.json placements, plan 4.8 item 2 and 6.3;
 * background mesh, bed bright).
 *
 * Shape: the hub films' montage (films/hubs.mjs) over the five groups of the
 * lab. Website tools and AI show each tool by the first screenshot of its own
 * film, as the /features and /ai films do; Software shows the four product
 * pages' hero shots, Templates the contact sheet of the /templates page
 * (webcon-templates' overview fixtures) and three template home pages, and
 * Strategy the five points of the strategy page. Narration: plan 6.4.
 */

import ai from './ai.mjs';
import { HOST, SHOTS } from './feature-film.mjs';
import website from './features.mjs';

/** The /templates page's own screenshots (webcon-templates, Resources/Private/Fixtures/Overview). */
const TEMPLATES = 'vendor/webconsulting/webcon-templates/Resources/Private/Fixtures/Overview/';
const frontend = (name) => `${SHOTS}Frontend/frontend-feature-${name}.webp`;
/** The lab's home page, for hook and title. */
const HOME = frontend('desiderio-home-hero-b85b6c67');
/**
 * Desiderio's film opens on a backend wizard shot whose name 4.27 replaced; the
 * montage shows its theme presets screenshot instead (a frontend page, like its neighbours).
 */
const STILL = { desiderio: frontend('desiderio-theme-presets-f63d31b0') };

/** A tool of a group: its film's first shot, its name and the word that brings it up. */
function tool(films, id, cue) {
    const film = films[id];
    const shot = film.scenes.find((scene) => scene.type === 'shot');
    const title = film.scenes.find((scene) => scene.type === 'title');
    return { image: STILL[id] ?? shot.image, name: film.name, note: title.sub, cue };
}

function group(chapter, head, path, items, say) {
    return { type: 'montage', chapter, eyebrow: chapter, head, url: `${HOST}${path}`, items, say };
}

const SOFTWARE = [
    { image: frontend('t3ops-overview-94f125f6'), name: 'T3 Ops', note: 'Runs the TYPO3 fleet' },
    { image: frontend('ledger-overview-bac84103'), name: 'Ledger', note: 'Closes the month', cue: 'ledger' },
    { image: frontend('medatlas-statistics-dark-5dad0c48'), name: 'MedAtlas Austria', note: 'Tracks medicine shortages', cue: 'med' },
    { image: frontend('capexone-value-portfolio-451057e9'), name: 'CapexOne Plus', note: 'Governs capital projects', cue: 'capex' },
];

const TEMPLATE_SITES = [
    { image: `${TEMPLATES}templates-contact-sheet-b7e184bb.webp`, name: 'Ten website templates', note: 'Ten kinds of business, each a live site' },
    { image: `${TEMPLATES}templates-home-odera-2258707f.webp`, name: 'Odera', note: 'Dental studio' },
    { image: `${TEMPLATES}templates-home-halftone-1f97432e.webp`, name: 'Halftone', note: 'News magazine' },
];

export default {
    'lab-overview': {
        name: 'The webconsulting TYPO3 lab',
        page: 505,
        slug: '/',
        placements: [{ slug: '/', after: { header: 'Everything in this lab' } }],
        title: 'The webconsulting TYPO3 lab: overview film',
        video: { description: '<p>Everything in this lab in one film: website tools, AI tools, four software products, ten website templates and our strategy for agents on the web. Narrated, with English captions.</p>' },
        url: `${HOST}/`,
        accent: '#9fb4ff',
        palette: ['#4a57ff', '#7c5cff', '#2c5a52', '#c7863f'],
        music: 'bright',
        background: 'mesh',
        alt: 'Overview film, {duration} seconds: everything in the webconsulting TYPO3 v14 lab. '
            + '17 website tools, 11 AI tools with people in charge, four software products (T3 Ops, Ledger, MedAtlas Austria, CapexOne Plus), '
            + 'ten website templates and the strategy for agents on the web. Narrated, with English captions.',
        scenes: [
            {
                type: 'hook', head: 'One installation, <em>everything running.</em>',
                images: [HOME, SOFTWARE[0].image, TEMPLATE_SITES[0].image],
                say: 'One TYPO3 installation shows everything we build.',
            },
            {
                type: 'title', eyebrow: 'TYPO3 v14 lab', name: 'The TYPO3 lab', sub: 'Everything we build, in one installation',
                key: `${HOST}/`, image: HOME,
                say: 'The webconsulting lab in one film.',
            },
            group('Website tools', '17 website tools <em>for TYPO3 v14</em>', '/features/', [
                tool(website, 'desiderio'), tool(website, 'astryx', 'design'), tool(website, 'blog', 'blog'),
                tool(website, 'visual-editor', 'editing'), tool(website, 'solr', 'search'), tool(website, 'powermail', 'forms'),
                tool(website, 'workos', 'sign'),
            ], 'Seventeen website tools: design systems, blog and news, editing on the page, search, forms and sign-in.'),
            group('AI and agents', '11 AI tools, <em>people in charge</em>', '/ai/', [
                tool(ai, 'ai-assistant'), tool(ai, 'skillflow', 'skills'), tool(ai, 'mcp-server', 'm'), tool(ai, 'agent-nexus', 'agent'),
            ], 'Eleven AI tools, with people in charge: an assistant, skills, MCP and agent protocols.'),
            group('Software', 'Four products <em>we build and run</em>', '/software/', SOFTWARE,
                'T3 Ops runs the fleet, Ledger closes the month, MedAtlas tracks medicine shortages, CapexOne Plus governs capital projects.'),
            group('Templates', 'Ten website templates, <em>each a live site</em>', '/templates/', TEMPLATE_SITES,
                'Ten website templates, each a live demo site.'),
            {
                type: 'shot', layout: 'wide', chapter: 'Strategy', image: frontend('strategy-points-aa57149b'), focus: [0.04, 0.05, 0.92, 0.62], max: 1.3,
                url: `${HOST}/typo3-v14-strategy/`, tag: '', eyebrow: 'Strategy 2026–2030', head: '',
                say: 'And a strategy for agents on the web.',
            },
            {
                type: 'end', head: 'Run the whole lab <em>on your laptop.</em>', cta: 'Download the lab', url: `${HOST}/downloads/`,
                note: 'One TYPO3 v14 installation with every site',
                say: 'Download the whole lab and run it on your laptop.',
            },
        ],
    },
};
