/**
 * Films of the software pages /software/t3-ops and /software/ledger
 * (featureFilm, section 'software', eyebrow 'Software by webconsulting';
 * backgrounds grid and bands, music bed calm).
 *
 * The screenshots are the pages' own captures (Desiderio's Styleguide/Frontend
 * folder, plan 5.2 and 5.3): demo data only. Ledger rule: no footage of the
 * running app and no draft PDF; the invoice-pdf still is the only invoice image
 * allowed, and only in the hook. Narration: plan 6.4.
 */

import { featureFilm } from './feature-film.mjs';

const SHOT = (name) => `Frontend/frontend-feature-${name}.webp`;
const SOURCE = 'vendor/webconsulting/desiderio/Resources/Public/Styleguide/';

/**
 * featureFilm() plus what the software films need on top: the frame bar shows
 * the product (not the lab host), the hook and title may use their own images,
 * and film.json gets its placements.
 */
function softwareFilm({ urls, hookImages, titleImage, description, ...definition }) {
    const [id, spec] = featureFilm({ section: 'software', eyebrow: 'Software by webconsulting', music: 'calm', ...definition });
    const shots = spec.scenes.filter((scene) => scene.type === 'shot');
    shots.forEach((scene, index) => { scene.url = urls[index]; });
    const hook = spec.scenes.find((scene) => scene.type === 'hook');
    const title = spec.scenes.find((scene) => scene.type === 'title');
    if (hookImages) hook.images = hookImages.map((image) => SOURCE + image);
    if (titleImage) title.image = SOURCE + titleImage;
    return [id, { ...spec, video: { description }, placements: [{ slug: spec.slug }] }];
}

export default Object.fromEntries([
    softwareFilm({
        id: 't3-ops', description: '<p>One screen for the whole fleet. A narrated tour of T3 Ops on demo data, with English captions.</p>',
        page: 8229, name: 'T3 Ops', background: 'grid',
        accent: '#5eead4', palette: ['#0e7490', '#2563eb', '#14b8a6'],
        hookImages: [SHOT('t3ops-sites-1bcbd6bd'), SHOT('t3ops-security-8841b7c3'), SHOT('t3ops-runs-8b9279bc')],
        titleImage: SHOT('t3ops-overview-94f125f6'),
        urls: ['t3-ops.webconsulting.at/security', 't3-ops.webconsulting.at/updates', 't3-ops.webconsulting.at/runs', 't3-ops.webconsulting.at/sites'],
        hook: { head: 'One advisory, <em>every update by hand.</em>', say: 'Twenty-two TYPO3 sites, one security advisory, and every update done by hand.' },
        title: { sub: 'One screen for the whole fleet', key: 't3-ops.webconsulting.at', say: 'T3 Ops runs the whole fleet from one screen.' },
        shots: [
            { image: SHOT('t3ops-security-8841b7c3'), eyebrow: 'Security', head: 'Core, extensions and advisories', text: 'Four checks a day, seven sources', say: 'Four times a day it checks core, extensions and advisories.' },
            { image: SHOT('t3ops-update-assistant-0c6c3071'), eyebrow: 'Update assistant', head: 'Staging first, release second', text: 'Backup, Composer, deploy, screenshots', say: 'An update runs on a staging copy first: backup, Composer, deploy, screenshots.' },
            { image: SHOT('t3ops-runs-8b9279bc'), eyebrow: 'Runs with evidence', head: 'Before and after, pixel by pixel', text: 'Every run with its report', say: 'Before and after shots of every sampled page, compared pixel by pixel.' },
            { image: SHOT('t3ops-hardening-d563196e'), eyebrow: 'Hardening', head: 'A checklist with 64 items', text: 'Seven groups, from Report only to Strict', say: 'A 64-point hardening checklist, from the Install Tool to security headers.' },
        ],
        points: {
            head: 'Watched <em>between updates</em>',
            items: [
                ['Metrics and dashboards', 'Prometheus exporters and a Grafana board.', 'Monitoring'],
                ['Uptime checks', 'Uptime Kuma every 60 seconds, per site.', 'Uptime'],
                ['Verified backups', 'Nightly, encrypted, read back every morning.', 'Nightly'],
                ['Cloudflare', 'Cache purged after each release.', 'Cloudflare'],
            ],
            say: 'Monitoring with Prometheus, Grafana and Uptime Kuma. Nightly encrypted backups, verified every morning. Cloudflare cache purged after each release.',
        },
        end: { head: 'Updates <em>you can prove.</em>', cta: 'Book a demo', url: 't3-ops.webconsulting.at', note: 'Deploys over SSH to the hosting you already have', say: 'T3 Ops. Updates you can prove.' },
    }),
    softwareFilm({
        id: 'ledger', description: '<p>Hours, receipts and bank in one place. A narrated tour of Ledger on synthetic demo data, with English captions.</p>',
        page: 8230, name: 'Ledger', background: 'bands',
        accent: '#86efac', palette: ['#a8876a', '#6f5f52', '#2f855a'],
        hookImages: [SHOT('ledger-time-invoice-0696abd3'), SHOT('ledger-invoice-pdf-9170dc2b'), SHOT('ledger-bank-5b0c5bd0')],
        urls: ['ledger · demo data · /time', 'ledger · demo data · /expenses', 'ledger · demo data · /bank', 'ledger · demo data · /closing'],
        hook: { head: 'Three places, <em>one month-end.</em>', say: 'Hours in Redmine, receipts in the inbox, bookings in the bank. Three places, one month-end.' },
        title: { sub: 'Hours, receipts and bank in one place', key: 'webconsulting · internal tool', say: 'Ledger turns them into invoices, checked receipts and a handover your accountant can use.' },
        shots: [
            { image: SHOT('ledger-time-invoice-0696abd3'), eyebrow: 'Time to invoice', head: 'One line per ticket', text: 'Billable hours from Redmine', say: 'It imports billable hours from Redmine and drafts the invoice, one line per ticket.' },
            { image: SHOT('ledger-receipt-review-7b7873c1'), eyebrow: 'Receipt review', head: 'Checks explained, you confirm', text: '§ 11 UStG checks beside the form', say: 'Receipts arrive from mail, Drive or upload. AI reads number, date and VAT; you confirm.' },
            { image: SHOT('ledger-bank-5b0c5bd0'), eyebrow: 'Bank payments', head: 'Booked when exactly one invoice fits', text: 'Bookings over PSD2, read-only', say: 'Bank bookings come in over PSD2. Clear matches are booked; the rest waits for you.' },
            { image: SHOT('ledger-closing-matching-6cfea2bd'), eyebrow: 'Monthly closing', head: 'Matched, suggested, missing', text: 'Five steps to the handover', say: 'The monthly statement shows every booking with its receipt, ready for the tax advisor.' },
        ],
        end: { head: 'Less bookkeeping, <em>more overview.</em>', cta: 'Book a call', url: 'typo3-lab.webconsulting.at/software/ledger/', note: 'Our own tool: the pattern is what we build for clients', say: 'Ledger. Less bookkeeping, more overview.' },
    }),
]);
