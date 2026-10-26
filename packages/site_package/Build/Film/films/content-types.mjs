/**
 * The films of /content-types/ and its ten group pages. The footage is each
 * group page as a visitor sees it, one still per element, in the group's own
 * theme preset (capture-frontend.mjs --only=elements). Counts are the hub's.
 */

import { HOST } from './feature-film.mjs';

const ELEMENTS = 'stills/elements/';

const GROUPS = [
    {
        slug: 'hero-landing-intros', page: 669, name: 'Hero & Landing Intros', count: 21, accent: '#2dd4bf', palette: ['#0f766e', '#c7863f', '#4a57ff'],
        hook: { head: 'The first screen <em>decides the visit.</em>', say: 'Visitors decide on the first screen whether a page is for them.' },
        sub: 'Heroes for the first screen of a page',
        title: 'Hero and Landing Intros has 21 heroes for the first screen of a page.',
        items: [['08-hero', 'Hero'], ['14-heroproduct', 'Product hero', 'product'], ['19-herostats', 'Hero with stats', 'numbers'], ['20-herotestimonial', 'Hero with testimonial', 'quote'], ['07-heroform', 'Hero with form', 'form'], ['05-herocountdown', 'Countdown hero', 'countdown']],
        tour: 'Pick a hero with a product shot, key numbers, a quote, a sign-up form or a countdown.',
        end: { head: 'Twenty-one heroes, <em>ready to fill.</em>', say: 'Editors fill in the fields, and the layout holds.' },
    },
    {
        slug: 'navigation-wayfinding', page: 670, name: 'Navigation & Wayfinding', count: 24, accent: '#e0b44c', palette: ['#8a6a1f', '#2c5a52', '#4a57ff'],
        hook: { head: 'Visitors get lost <em>three clicks deep.</em>', say: 'In a deep page tree, visitors get lost when menus and breadcrumbs disagree.' },
        sub: 'Menus, breadcrumbs and pagination',
        title: 'Navigation and Wayfinding has 24 elements for menus, headers and paths through a site.',
        items: [['04-navbardropdown', 'Navbar with dropdowns'], ['02-breadcrumb', 'Breadcrumb', 'breadcrumbs'], ['07-mapembed', 'Map embed', 'maps'], ['13-headerprofile', 'Profile header', 'profile'], ['14-searchheader', 'Search header', 'search'], ['22-navtabs', 'Tabs', 'tabs']],
        tour: 'Dropdown menus, breadcrumbs, maps, profile headers, a search header and tabs.',
        end: { head: 'A clear path <em>through every page tree.</em>', say: 'Every element uses the same design tokens as the rest of the site.' },
    },
    {
        slug: 'content-editorial', page: 664, name: 'Content & Editorial', count: 24, accent: '#a78bfa', palette: ['#5b3fd1', '#c7863f', '#2c5a52'],
        hook: { head: 'Long pages turn into <em>walls of text.</em>', say: 'Articles and guides turn into walls of text when every block looks the same.' },
        sub: 'Layouts for articles and text pages',
        title: 'Content and Editorial has 24 layouts for articles and text pages.',
        items: [['03-articlehero', 'Article hero'], ['18-gallery', 'Gallery', 'galleries'], ['23-timeline', 'Timeline', 'timelines'], ['22-tabs', 'Tabs', 'tabs'], ['05-blogteasers', 'Blog teasers', 'blog'], ['19-quote', 'Quote', 'quotes']],
        tour: 'Article heroes, galleries, timelines, tabs, blog teasers and quotes break a long page into parts.',
        end: { head: 'Structure for <em>every text page.</em>', say: 'Editors pick a layout, and the text stays readable.' },
    },
    {
        slug: 'features-benefits', page: 667, name: 'Features & Benefits', count: 25, accent: '#fb923c', palette: ['#c2410c', '#4a57ff', '#2c5a52'],
        hook: { head: 'Feature lists <em>all look alike.</em>', say: 'A feature list in plain bullet points makes every product sound the same.' },
        sub: 'Ways to explain what you offer',
        title: 'Features and Benefits has 25 ways to explain what you offer.',
        items: [['06-featurebento', 'Bento grid'], ['04-featurealternating', 'Alternating rows', 'alternating'], ['22-featuretabs', 'Feature tabs', 'tabs'], ['21-featurestats', 'Feature stats', 'numbers'], ['08-featurecards', 'Feature cards', 'cards'], ['12-featurecomparison', 'Comparison', 'comparisons']],
        tour: 'Bento grids, alternating rows, tabs, numbers, cards and comparisons.',
        end: { head: 'Explain your offer <em>at a glance.</em>', say: 'Every layout has a backend preview, so editors see the result before they save.' },
    },
    {
        slug: 'plans-pricing', page: 671, name: 'Plans & Pricing', count: 25, accent: '#818cf8', palette: ['#3730a3', '#c7863f', '#2c5a52'],
        hook: { head: 'Pricing pages <em>built by hand.</em>', say: 'On a hand-built pricing table, every price change means a ticket for a developer.' },
        sub: 'Pricing tables, toggles and calculators',
        title: 'Plans and Pricing has 25 elements for prices, products and orders.',
        items: [['22-pricingthreetier', 'Three tiers'], ['11-pricingcalculator', 'Calculator', 'calculator'], ['10-pricingslider', 'Slider', 'slider'], ['14-productgallery', 'Product gallery', 'product'], ['06-ordersummary', 'Order summary', 'order'], ['07-pricingcomparison', 'Comparison', 'comparison']],
        tour: 'Three-tier tables, a calculator, a slider, product galleries, an order summary and a full comparison.',
        end: { head: 'Prices editors <em>can change.</em>', say: 'Editors change prices in the backend, without a developer.' },
    },
    {
        slug: 'trust-social-proof', page: 672, name: 'Trust & Social Proof', count: 25, accent: '#f472b6', palette: ['#be185d', '#4a57ff', '#c7863f'],
        hook: { head: 'Claims need <em>proof.</em>', say: 'Visitors trust what other customers say more than what a site says about itself.' },
        sub: 'Testimonials, logos and case studies',
        title: 'Trust and Social Proof has 25 elements for testimonials, logos and results.',
        items: [['23-testimonialwall', 'Testimonial wall'], ['10-casestudyfeatured', 'Featured case study', 'case'], ['15-logocloud', 'Logo cloud', 'logo'], ['18-ratingdisplay', 'Ratings', 'ratings'], ['09-statscounter', 'Stats counter', 'counters'], ['01-awards', 'Awards', 'awards']],
        tour: 'A testimonial wall, case studies, logo clouds, ratings, counters and awards.',
        end: { head: 'Let customers <em>make the case.</em>', say: 'Add the proof you have, and the layout does the rest.' },
    },
    {
        slug: 'people-team', page: 673, name: 'People & Team', count: 24, accent: '#a3e635', palette: ['#4d7c0f', '#c7863f', '#4a57ff'],
        hook: { head: 'An about page <em>without faces.</em>', say: 'An about page without faces or history tells visitors nothing about who does the work.' },
        sub: 'Introduce the people behind the work',
        title: 'People and Team has 24 elements for the people behind the work.',
        items: [['23-team', 'Team grid'], ['17-orgchart', 'Org chart', 'org'], ['07-companyhistory', 'Company history', 'history'], ['16-officelocations', 'Office locations', 'offices'], ['05-careerlisting', 'Career listing', 'roles'], ['11-founderquote', 'Founder quote', 'founder']],
        tour: 'Team grids, an org chart, company history, offices, open roles and a founder quote.',
        end: { head: 'Put faces <em>to the name.</em>', say: 'Show who does the work, and where to find them.' },
    },
    {
        slug: 'data-dashboards', page: 666, name: 'Data & Dashboards', count: 29, accent: '#d4d4d8', palette: ['#52525b', '#4a57ff', '#c7863f'],
        hook: { head: 'Charts pasted in <em>as screenshots.</em>', say: 'Charts pasted in as screenshots go stale, and screen readers skip them.' },
        sub: 'Charts that render on the server',
        title: 'Data and Dashboards has 29 elements, and the charts render on the server.',
        items: [['20-metricdashboard', 'Metric dashboard'], ['13-chartheatmap', 'Heatmap', 'heatmaps'], ['18-leaderboard', 'Leaderboard', 'leaderboards'], ['17-kpicards', 'KPI cards', 'k'], ['05-chartbar', 'Bar chart', 'bar'], ['12-chartdonut', 'Donut chart', 'donuts']],
        tour: 'Metric dashboards, heatmaps, leaderboards, KPI cards, bar charts and donuts, from numbers editors type in.',
        end: { head: 'Charts that <em>stay current.</em>', say: 'Change a number in the backend, and the chart follows on the next page load.' },
    },
    {
        slug: 'leads-conversion', page: 665, name: 'Leads & Conversion', count: 24, accent: '#60a5fa', palette: ['#1d4ed8', '#c7863f', '#2c5a52'],
        hook: { head: 'A contact page <em>with only an email.</em>', say: 'A bare email address gives visitors no reason to write, and gives sales nothing to sort.' },
        sub: 'Turn visits into leads without a developer',
        title: 'Leads and Conversion has 24 elements that turn visits into enquiries. Every form is a real TYPO3 form.',
        items: [['06-contactsplit', 'Contact split'], ['01-bookingform', 'Booking form', 'bookings'], ['08-demorequest', 'Demo request', 'demo'], ['20-newslettersignup', 'Newsletter sign-up', 'newsletter'], ['16-ctawithimage', 'CTA with image', 'calls'], ['21-officehours', 'Office hours', 'office']],
        tour: 'Contact forms, bookings, demo requests, newsletter sign-ups, calls to action and office hours.',
        end: { head: 'Give visitors <em>a next step.</em>', say: 'Every page can end with a clear next step.' },
    },
    {
        slug: 'footers-utility-areas', page: 668, name: 'Footers & Utility Areas', count: 23, accent: '#fb7185', palette: ['#be123c', '#2c5a52', '#c7863f'],
        hook: { head: 'The legal pages <em>come last.</em>', say: 'Footers, cookie notices and legal pages get built last, in a hurry, before launch.' },
        sub: 'The parts every site needs',
        title: 'Footers and Utility Areas has 23 elements for the parts every site needs.',
        items: [['14-footermega', 'Mega footer'], ['08-footerdark', 'Dark footer', 'dark'], ['07-cookiebanner', 'Cookie banner', 'cookie'], ['17-footernewsletter', 'Newsletter footer', 'newsletter'], ['05-compliancebadges', 'Compliance badges', 'compliance'], ['20-sitemapgrid', 'Sitemap grid', 'sitemap']],
        tour: 'Mega and dark footers, a cookie banner, a newsletter footer, compliance badges and a sitemap.',
        end: { head: 'Launch with <em>the last parts done.</em>', say: 'The parts every site needs, ready from the first day.' },
    },
];

const still = (group, file) => `${ELEMENTS}${group.slug}/${file}.jpg`;
const install = '<span>$</span> composer require webconsulting/desiderio';

function groupFilm(group) {
    const id = `content-types-${group.slug}`;
    const items = group.items.map(([file, name, cue]) => ({ image: still(group, file), name, cue }));
    return [id, {
        name: group.name,
        page: group.page,
        slug: `/content-types/${group.slug}`,
        title: `${group.name}: overview film`,
        url: `${HOST}/content-types/${group.slug}/`,
        accent: group.accent,
        palette: group.palette,
        music: 'bright',
        alt: `Overview film, {duration} seconds: the ${group.count} ${group.name} elements of Desiderio for TYPO3 v14, `
            + `including ${items.map((item) => item.name.toLowerCase()).join(', ')}. Narrated, with English captions.`,
        scenes: [
            { type: 'hook', head: group.hook.head, images: items.slice(0, 3).map((item) => item.image), say: group.hook.say },
            { type: 'title', eyebrow: 'Desiderio for TYPO3 v14', name: group.name, sub: group.sub, key: `${group.count} elements`, image: items[0].image, say: group.title },
            { type: 'montage', chapter: 'Elements', eyebrow: group.name, items, say: group.tour },
            { type: 'end', head: group.end.head, cta: 'See all elements', url: `${HOST}/content-types/${group.slug}/`, install, note: 'Part of Desiderio · 244 elements, free under GPL-2.0', say: group.end.say },
        ],
    }];
}

const hub = ['content-types', {
    name: 'Content types',
    page: 1093,
    slug: '/content-types',
    title: 'Content types: overview film',
    url: `${HOST}/content-types/`,
    accent: '#9fb4ff',
    palette: ['#4a57ff', '#c7863f', '#2c5a52'],
    music: 'bright',
    alt: 'Overview film, {duration} seconds: the 244 content elements of Desiderio for TYPO3 v14 in 10 groups, '
        + 'from heroes and navigation to dashboards, forms and footers. Narrated, with English captions.',
    scenes: [
        { type: 'hook', head: 'Every new page <em>starts from scratch.</em>', images: [still(GROUPS[0], '14-heroproduct'), still(GROUPS[4], '22-pricingthreetier'), still(GROUPS[7], '20-metricdashboard')], say: 'A new client page often starts as a design file, and someone rebuilds it as templates.' },
        { type: 'title', eyebrow: 'Desiderio for TYPO3 v14', name: 'Content types', sub: 'All 244 elements, sorted into 10 groups', key: 'webconsulting/desiderio', image: still(GROUPS[0], '08-hero'), say: 'Desiderio ships 244 content elements for TYPO3 v14, sorted into 10 groups by what a page needs.' },
        {
            type: 'grid',
            chapter: 'Groups',
            head: '244 elements, <em>10 groups</em>',
            items: GROUPS.map((group, i) => ({ image: still(group, group.items[0][0]), name: group.name, note: `${group.count} elements`, cue: ['heroes', 'navigation', 'editorial', 'features', 'pricing', 'trust', 'people', 'dashboards', 'leads', 'footers'][i] })),
            say: 'Heroes, navigation, editorial layouts, features, pricing, trust, people, dashboards, leads and footers.',
        },
        {
            type: 'montage',
            chapter: 'Presets',
            eyebrow: 'Content types',
            items: [[0, '14-heroproduct'], [4, '22-pricingthreetier'], [6, '23-team'], [7, '20-metricdashboard'], [5, '23-testimonialwall'], [8, '06-contactsplit']]
                .map(([g, file]) => ({ image: still(GROUPS[g], file), name: GROUPS[g].items.find(([f]) => f === file)[1], note: GROUPS[g].name })),
            say: [
                'Each group page shows its elements live, in its own theme preset.',
                'Every element has a backend preview, a dark mode and the colours of the chosen preset.',
            ],
        },
        { type: 'end', head: 'All 244 elements, <em>in the free package.</em>', cta: 'See all elements', url: `${HOST}/content-types/`, install, note: 'Open source, GPL-2.0 · TYPO3 v14', say: 'All 244 elements are in the free package.' },
    ],
}];

export default Object.fromEntries([hub, ...GROUPS.map(groupFilm)]);
