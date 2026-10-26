/**
 * The films of /features/ and /ai/: one montage per group of the hub page,
 * each tool shown by the first screenshot of its own film.
 */

import ai from './ai.mjs';
import docx from './docx-editor.mjs';
import { HOST, SHOTS } from './feature-film.mjs';
import website from './features.mjs';

const features = { ...website, ...docx };

/** DOCX Editor's shots are clips; the hub shows its page tour screenshot instead. */
const STILL = { 'docx-editor': SHOTS + 'Backend/feature-docx-editor-page-in-word-dbfe56d4.webp' };

/** A tool of a hub group: its film's first shot, its name and the word that brings it up. */
function tool(films, id, cue) {
    const film = films[id];
    const shot = film.scenes.find((scene) => scene.type === 'shot');
    const title = film.scenes.find((scene) => scene.type === 'title');
    return { image: STILL[id] ?? shot.image, name: film.name, note: title.sub, cue };
}

function group(films, chapter, head, tools, say) {
    return { type: 'montage', chapter, eyebrow: chapter, head, items: tools.map(([id, cue]) => tool(films, id, cue)), say };
}

export default {
    features: {
        name: 'Website tools',
        page: 1068,
        slug: '/features',
        title: 'Website tools: overview film',
        url: `${HOST}/features/`,
        accent: '#9fb4ff',
        palette: ['#4a57ff', '#c7863f', '#2c5a52'],
        music: 'calm',
        alt: 'Overview film, {duration} seconds: 17 website tools for TYPO3 v14, in four groups: '
            + 'design systems, editing, search and forms, sign-in, payments and APIs. Narrated, with English captions.',
        scenes: [
            { type: 'hook', head: 'Every client site, <em>the same tools again.</em>', images: [tool(features, 'desiderio').image, tool(features, 'visual-editor').image, tool(features, 'solr').image], say: 'Every client site needs pages, editing, search, forms and sign-in, and each project builds them again.' },
            { type: 'title', eyebrow: 'For TYPO3 v14', name: 'Website tools', sub: '17 tools, each one running in this lab', key: `${HOST}/features/`, image: tool(features, 'desiderio').image, say: 'This lab runs 17 website tools for TYPO3 v14, each with live screenshots.' },
            group(features, 'Design systems', 'Design systems for <em>finished pages</em>', [['desiderio'], ['astryx', 'astryx'], ['innesto', 'innesto'], ['blog', 'blog'], ['news', 'news']],
                'Desiderio and Astryx bring complete design systems, and Innesto adds new elements. The Blog and News publish articles.'),
            group(features, 'Editing', 'Editing that saves <em>editors clicks</em>', [['visual-editor'], ['records-list', 'cards'], ['context-edit-records', 'side'], ['easy-workspace', 'publish'], ['docx-editor', 'word'], ['image-workbench', 'crop']],
                'Editors type on the page itself, see records as cards and edit them in a side panel. They publish in one click, edit pages in Word and crop images in the browser.'),
            group(features, 'Search and forms', 'Search and forms <em>that fit your site</em>', [['solr'], ['powermail', 'powermail']],
                'Solr finds the right page, and Powermail builds the forms.'),
            group(features, 'Sign-in and payments', 'Sign-in, payments and APIs <em>without custom code</em>', [['workos'], ['x402', 'x'], ['sg-apicore', 'api'], ['nr-vault', 'vault']],
                'WorkOS adds sign-in, x402 charges per page, sg_apicore opens a documented REST API, and nr-vault keeps API keys encrypted.'),
            { type: 'end', head: 'Start with <em>the free design system.</em>', cta: 'Get started free', url: `${HOST}/features/`, install: '<span>$</span> composer require webconsulting/desiderio', note: 'Every tool runs on TYPO3 v14', say: 'Every tool runs on TYPO3 v14. Start with the free design system.' },
        ],
    },
    ai: {
        name: 'AI tools',
        page: 2229,
        slug: '/ai',
        title: 'AI tools: overview film',
        url: `${HOST}/ai/`,
        accent: '#c4b5fd',
        palette: ['#7c5cff', '#2c5a52', '#c7863f'],
        music: 'focus',
        alt: 'Overview film, {duration} seconds: 10 AI tools for TYPO3 v14, in three groups: '
            + 'AI editors control, checked skills, and agents within limits. Narrated, with English captions.',
        scenes: [
            { type: 'hook', head: 'AI changes <em>nobody approved.</em>', images: [tool(ai, 'ai-assistant').image, tool(ai, 'mcp-server').image, tool(ai, 'agent-nexus').image], say: 'An AI tool that writes straight to live pages leaves nobody to approve the change.' },
            { type: 'title', eyebrow: 'AI for TYPO3 v14', name: 'AI tools', sub: '10 tools, with people in charge', key: `${HOST}/ai/`, image: tool(ai, 'ai-assistant').image, say: 'This lab runs 10 AI tools for TYPO3 v14, and people approve what they change.' },
            group(ai, 'Editors in control', 'AI your editors <em>can control</em>', [['ai-assistant'], ['nr-llm-manual', 'n'], ['jev', 'jev']],
                'The assistant asks before it writes, nr-llm keeps one model setup, and Jev sorts form messages.'),
            group(ai, 'Skills', 'Skills, checked <em>before they run</em>', [['skillflow'], ['skillspector', 'skillspector']],
                'Skillflow runs review skills on drafts, and Skillspector checks each skill first.'),
            group(ai, 'Agents', 'Open TYPO3 to agents, <em>within limits</em>', [['mcp-server'], ['typo3-abilities', 'abilities'], ['agent-nexus', 'nexus'], ['agentation', 'agentation'], ['llms-txt', 'l']],
                'The MCP Server and the Abilities Registry let agents work within your rules. Agent Nexus shows five protocols, Agentation points at elements, and llms.txt maps the site.'),
            { type: 'end', head: 'Try the AI tools <em>on your own copy.</em>', cta: 'Get started free', url: `${HOST}/ai/`, note: 'Each tool shown working in this lab', say: 'Try the AI tools on your own copy, with people in charge.' },
        ],
    },
};
