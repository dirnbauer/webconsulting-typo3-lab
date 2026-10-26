/**
 * The film of /themes/: the same Desiderio pages in all 15 theme presets,
 * light and dark (capture-frontend.mjs --only=themes).
 */

import { HOST } from './feature-film.mjs';

const THEMES = 'stills/themes/';

const PRESETS = [
    ['aurora', 'Aurora'], ['marine', 'Marine'], ['forest', 'Forest'], ['ember', 'Ember'], ['bloom', 'Bloom'],
    ['lagoon', 'Lagoon'], ['gold', 'Gold'], ['midnight', 'Midnight'], ['blossom', 'Blossom'], ['citrus', 'Citrus'],
    ['b0', 'Default neutral'], ['b27GcrRo', 'Rhea modern neutral'], ['b4hb38Fyj', 'Olive product system'], ['b3IWPgRwnI', 'Mist dashboard'], ['b6G5977cw', 'Lyra mono olive'],
];
const label = (key) => PRESETS.find(([k]) => k === key)[1];
const still = (page, key, mode = 'light') => `${THEMES}${page}-${key}-${mode}.jpg`;

export default {
    themes: {
        name: 'Themes',
        page: 1311,
        slug: '/themes',
        title: 'Themes: overview film',
        url: `${HOST}/themes/`,
        accent: '#f0abfc',
        palette: ['#a21caf', '#0f766e', '#c7863f'],
        music: 'bright',
        alt: 'Overview film, {duration} seconds: the 15 theme presets of Desiderio for TYPO3 v14 on the same pages, '
            + 'in light and dark mode. Narrated, with English captions.',
        scenes: [
            { type: 'hook', head: 'A new look <em>means a rebuild.</em>', images: [still('home', 'ember'), still('pricing', 'midnight'), still('dashboard', 'lagoon')], say: 'On most sites, a new brand colour means new templates, new CSS and a round of testing.' },
            { type: 'title', eyebrow: 'Desiderio for TYPO3 v14', name: 'Themes', sub: '15 presets, switched in the site settings', key: 'webconsulting/desiderio', image: still('home', 'aurora'), say: 'Desiderio ships 15 theme presets. One setting changes the look of every page.' },
            {
                type: 'montage',
                chapter: 'Presets',
                eyebrow: 'Home page',
                items: ['aurora', 'marine', 'forest', 'ember', 'bloom', 'citrus', 'b6G5977cw', 'midnight'].map((key, i) => ({ image: still('home', key), name: label(key), cue: ['aurora', 'marine', 'forest', 'ember', 'bloom', null, null, null][i] })),
                say: [
                    'Aurora, Marine, Forest, Ember, Bloom.',
                    { show: 'Each preset sets colours, fonts and corner radius together, on the same content.', after: 0 },
                ],
            },
            {
                type: 'montage',
                chapter: 'Dark mode',
                eyebrow: 'Dark mode',
                items: ['aurora', 'lagoon', 'gold', 'blossom', 'b27GcrRo'].map((key) => ({ image: still('home', key, 'dark'), name: label(key), note: 'Dark' })),
                say: 'Every preset has a dark mode, and visitors switch it from the header.',
            },
            {
                type: 'montage',
                chapter: 'Every element',
                eyebrow: 'Pricing and dashboards',
                items: [['pricing', 'midnight'], ['pricing', 'ember'], ['dashboard', 'lagoon'], ['dashboard', 'b3IWPgRwnI'], ['pricing', 'forest']].map(([page, key]) => ({ image: still(page, key), name: label(key), note: page === 'pricing' ? 'Pricing' : 'Dashboard' })),
                say: 'Pricing tables, dashboards and forms follow the same design tokens, with no template change.',
            },
            { type: 'end', head: 'Design <em>your own preset.</em>', cta: 'Get started free', url: `${HOST}/themes/`, install: '<span>$</span> composer require webconsulting/desiderio', note: '15 presets in the free package · shadcn/ui tokens', say: 'Start with one of the 15 presets, or design your own.' },
        ],
    },
};
