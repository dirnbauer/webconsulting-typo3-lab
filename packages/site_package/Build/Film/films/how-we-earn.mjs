/**
 * The model film how-we-earn ("Free software, paid care"): on
 * /pricing/how-we-earn after its hero (film.json placements, plan 2026-11-05
 * 6.2; background aurora, bed calm).
 *
 * Shape: hook, the four ways to work with us (use it, support it, ask for
 * features, let us take care), what is always free, the three support plans
 * as counting prices, Care over the T3 Ops overview (the only screenshot: the
 * pricing page and the model page change in the same round, so the film does
 * not show them), what we never do, end. Typographic scenes otherwise.
 *
 * Every price is the one in Desiderio's ShowcasePricing (plans(), care(),
 * supporter(), sponsoredFeature()); the narration names only the plan prices
 * and Care, the Supporter and the sponsored feature stay on the cards. The
 * voice reads prices from `say` in words, the captions show the figures.
 */

import { HOST, SHOTS } from './feature-film.mjs';

const T3OPS = `${SHOTS}Frontend/frontend-feature-t3ops-overview-94f125f6.webp`;
const PAGE = `${HOST}/pricing/how-we-earn/`;

export default {
    'how-we-earn': {
        name: 'How we earn with free software',
        slug: '/pricing/how-we-earn',
        placements: [{ slug: '/pricing/how-we-earn' }],
        title: 'Free software, paid care: how we earn',
        video: {
            header: 'How we earn with free software, in 58 seconds',
            description: '<p>Desiderio is free with every release; we earn when you want our time. The four ways to work with us, what stays free, what the plans and Care cost, and what we never do. Narrated, with English captions.</p>',
        },
        card: { eyebrow: 'Free software, paid care' },
        url: PAGE,
        accent: '#a7d8c9',
        palette: ['#2c5a52', '#c7863f', '#4a57ff'],
        music: 'calm',
        background: 'aurora',
        /** The poster: the four ways, all cards in (scene time, ms). */
        poster: 10000,
        alt: 'Film, {duration} seconds: how webconsulting earns with free software. Four ways to work with us: use it, support it, ask for features, let us take care. '
            + 'Always free: every release, every fix, the demo and commercial use. Support plans: Studio €590, Agency €1,990, Partner €4,900 a year. '
            + 'Care over the T3 Ops overview: we run your site for €390 a month per live site. What we never do: paid early access, licence keys, discount codes, countdown timers. '
            + 'Narrated, with English captions.',
        scenes: [
            {
                type: 'hook', head: 'Everything here is free. <em>Here is how we pay the rent.</em>',
                say: 'Everything here is free. Here is how we pay the rent.',
            },
            {
                type: 'points', chapter: 'Four ways', head: 'Four ways <em>to work with us</em>',
                items: [
                    ['Use it', 'Every release and every fix, free under GPL-2.0', 'use'],
                    ['Support it', 'Stars and bug reports, or a Supporter for €190 a year', 'support'],
                    ['Ask for features', 'Open an issue, or sponsor one from €1,290', 'ask'],
                    ['Let us take care', 'Our time: answers, fixes, or your site run for you', 'let'],
                ],
                say: 'Use it. Support it. Ask for features. Or let us take care of it.',
            },
            {
                type: 'points', chapter: 'Always free', head: 'Always <em>free</em>',
                items: [
                    ['Every release, the day it ships', '', 'release'],
                    ['Every bug and security fix', '', 'fix'],
                    ['The demo site and its download', '', 'demo'],
                    ['Commercial use on unlimited sites', '', 'commercial'],
                ],
                say: 'Every release, every fix, the demo and commercial use stay free.',
            },
            {
                type: 'stats', chapter: 'Plans', eyebrow: 'Named developers and the maintenance promise', head: 'Plans add <em>our time</em>',
                tiles: [
                    { value: 590, prefix: '€', label: 'Studio, a year', note: 'Up to 5 live sites, 1 named developer', cue: 'studio' },
                    { value: 1990, prefix: '€', label: 'Agency, a year', note: 'Up to 25 live sites, 3 named developers', cue: 'agency' },
                    { value: 4900, prefix: '€', label: 'Partner, a year', note: 'Unlimited sites, 6 named developers', cue: 'partner' },
                ],
                say: {
                    show: 'Plans pay for our time. Studio €590 a year, Agency €1,990, Partner €4,900.',
                    say: 'Plans pay for our time. Studio, five hundred and ninety euros a year. Agency, one thousand nine hundred and ninety. Partner, four thousand nine hundred.',
                },
            },
            {
                type: 'shot', chapter: 'Care', image: T3OPS, url: `${HOST}/software/t3-ops/`, tag: '',
                eyebrow: 'Care', head: 'We run <em>your site</em>', text: '€390 a month per live site',
                say: {
                    show: 'Or Care: we run your site, with updates and backups. €390 a month per live site.',
                    say: 'Or Care: we run your site, with updates and backups. Three hundred and ninety euros a month per live site.',
                },
            },
            {
                type: 'points', chapter: 'Never', head: 'What we <em>never do</em>',
                items: [
                    ['No paid early access', 'A release that waits for payment is a fix that waits', 'early'],
                    ['No licence keys', 'One public codebase for every customer', 'licence'],
                    ['No discount codes', 'The price is the same for everyone', 'discount'],
                    ['No countdown timers', 'Take the time you need', 'countdown'],
                ],
                say: 'What we never do: paid early access, licence keys, discount codes or countdown timers.',
            },
            {
                type: 'end', head: 'Use it today. <em>Call us when you want help.</em>', cta: 'How we earn', url: PAGE,
                note: 'Free software, paid care',
                say: 'Use it today. Call us when you want help.',
            },
        ],
    },
};
