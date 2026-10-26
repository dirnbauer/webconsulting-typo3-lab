#!/usr/bin/env node
/**
 * Frontend stills for the overview films: every content element of the ten
 * content-type pages, the homepage and a pricing section in all 15 theme
 * presets (light and dark), and the first screen of every page the hub films
 * show. A visitor's browser, without backend cookie, so no admin panel.
 *
 *   node capture-frontend.mjs [--only=elements|themes|pages] [--slug=plans-pricing]
 *
 * Writes JPEG stills to <cache>/footage/stills/<group>/ and their list to
 * footage.json under "stills-<group>".
 */

import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, readdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { BASE, DSF, FOOTAGE, LAB, VIEW, argument, chromium, remember, sleep } from './lib/capture.mjs';

const only = argument('only');
const HEADER = 77;
const HIDE = 'agentation-toolbar, .typo3-adminPanel-bar, #TSFE_ADMIN_PANEL_FORM { display: none !important; }'
    + ' *, *::before, *::after { scroll-behavior: auto !important; }';

const CATEGORIES = ['hero-landing-intros', 'navigation-wayfinding', 'content-editorial', 'features-benefits', 'plans-pricing',
    'trust-social-proof', 'people-team', 'data-dashboards', 'leads-conversion', 'footers-utility-areas'];
const PRESETS = ['b6G5977cw', 'b0', 'b27GcrRo', 'b4hb38Fyj', 'b3IWPgRwnI', 'aurora', 'marine', 'forest', 'ember', 'bloom',
    'lagoon', 'gold', 'midnight', 'blossom', 'citrus'];

function sql(query) {
    const result = spawnSync('ddev', ['mysql', '-N', '-e', query], { cwd: LAB, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr);
    return result.stdout.trim().split('\n').filter(Boolean).map((line) => line.split('\t'));
}

function elementTitles() {
    const root = join(LAB, 'vendor/webconsulting/desiderio/ContentBlocks/ContentElements');
    const titles = {};
    for (const name of readdirSync(root)) {
        const config = readFileSync(join(root, name, 'config.yaml'), 'utf8');
        const type = config.match(/^typeName:\s*(\S+)/m)?.[1];
        const title = config.match(/^title:\s*'?(.*?)'?\s*$/m)?.[1]?.replace(/''/g, "'");
        if (type && title) titles[type] = title;
    }
    return titles;
}

async function visitor(browser, mode = 'light') {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: VIEW, deviceScaleFactor: DSF, locale: 'en-GB' });
    await context.addInitScript(([css, theme]) => {
        try { window.localStorage.setItem('d-theme', theme); } catch {}
        document.addEventListener('DOMContentLoaded', () => {
            const style = document.createElement('style');
            style.textContent = css;
            document.head.append(style);
        });
    }, [HIDE, mode]);
    return context;
}

async function open(page, path) {
    await page.goto(BASE + path, { waitUntil: 'load', timeout: 120000 });
    await page.evaluate(() => document.fonts.ready);
    // Scroll once through the page so lazy images and reveal effects have run.
    const height = await page.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < height; y += 600) {
        await page.evaluate((top) => window.scrollTo(0, top), y);
        await sleep(90);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    await sleep(800);
}

async function shoot(page, file, top) {
    await page.evaluate((y) => window.scrollTo(0, y), Math.max(0, Math.round(top)));
    await sleep(700);
    await page.screenshot({ path: file, type: 'jpeg', quality: 90 });
}

function stillsDir(group) {
    const dir = join(FOOTAGE, 'stills', group);
    rmSync(dir, { recursive: true, force: true });
    mkdirSync(dir, { recursive: true });
    return dir;
}

async function elements(browser) {
    const titles = elementTitles();
    const pages = Object.fromEntries(sql(`SELECT slug, uid FROM pages WHERE pid=1093 AND sys_language_uid=0 AND deleted=0 AND t3ver_wsid=0`).map(([slug, uid]) => [slug.split('/').pop(), uid]));
    const slugs = argument('slug') ? [argument('slug')] : CATEGORIES;
    const context = await visitor(browser);
    const page = await context.newPage();
    for (const slug of slugs) {
        const types = Object.fromEntries(sql(`SELECT uid, CType, header FROM tt_content WHERE pid=${pages[slug]} AND deleted=0 AND hidden=0 AND sys_language_uid=0 AND t3ver_wsid=0`).map(([uid, type, header]) => [uid, { type, header }]));
        const dir = stillsDir(`elements/${slug}`);
        await open(page, `/content-types/${slug}/`);
        const preset = await page.evaluate(() => document.body.dataset.shadcnPreset ?? '');
        const anchors = await page.evaluate(() => {
            const list = [...document.querySelectorAll('a[id^="c"]')].filter((a) => /^c\d+$/.test(a.id))
                .map((a) => ({ uid: a.id.slice(1), top: a.getBoundingClientRect().top + window.scrollY }));
            const end = document.querySelector('footer')?.getBoundingClientRect().top + window.scrollY;
            return list.map((item, index) => ({ ...item, height: (list[index + 1]?.top ?? end) - item.top }));
        });
        const stills = [];
        await shoot(page, join(dir, '00-top.jpg'), 0);
        stills.push({ file: 'elements/' + slug + '/00-top.jpg', type: 'top' });
        for (const [index, anchor] of anchors.entries()) {
            const type = types[anchor.uid]?.type;
            if (!type || index === 0) continue;
            const free = VIEW.height - HEADER - 24;
            const top = anchor.height < free ? anchor.top - HEADER - 12 - (free - anchor.height) / 2 : anchor.top - HEADER - 12;
            const name = `${String(index).padStart(2, '0')}-${type.replace('desiderio_', '')}.jpg`;
            await shoot(page, join(dir, name), top);
            stills.push({ file: `elements/${slug}/${name}`, type, title: titles[type] ?? type, header: types[anchor.uid].header, height: Math.round(anchor.height) });
        }
        remember(`stills-elements-${slug}`, { kind: 'stills', preset, width: VIEW.width * DSF, height: VIEW.height * DSF, stills });
        console.log(`${slug}: ${stills.length} stills (${preset})`);
    }
    await context.close();
}

async function themes(browser) {
    const dir = stillsDir('themes');
    const stills = [];
    for (const mode of ['light', 'dark']) {
        const context = await visitor(browser, mode);
        const page = await context.newPage();
        for (const [path, name] of [['/', 'home'], ['/content-types/plans-pricing/', 'pricing'], ['/content-types/data-dashboards/', 'dashboard']]) {
            await open(page, path);
            const top = name === 'home' ? 0 : await page.evaluate(() => {
                const anchors = [...document.querySelectorAll('a[id^="c"]')].filter((a) => /^c\d+$/.test(a.id));
                return anchors[1].getBoundingClientRect().top + window.scrollY - 89;
            });
            await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation-duration: 0s !important; }' });
            for (const preset of PRESETS) {
                await page.evaluate((value) => { document.body.dataset.shadcnPreset = value; }, preset);
                await page.evaluate(async () => {
                    const family = getComputedStyle(document.body).getPropertyValue('--d-font-sans').trim();
                    if (family) { try { await document.fonts.load(`16px ${family}`); await document.fonts.load(`700 16px ${family}`); } catch {} }
                    await document.fonts.ready;
                });
                const file = `${name}-${preset}-${mode}.jpg`;
                await shoot(page, join(dir, file), top);
                stills.push({ file: `themes/${file}`, page: name, preset, mode });
            }
        }
        await context.close();
    }
    remember('stills-themes', { kind: 'stills', width: VIEW.width * DSF, height: VIEW.height * DSF, stills });
    console.log(`themes: ${stills.length} stills`);
}

async function pages(browser) {
    const dir = stillsDir('pages');
    const rows = sql(`SELECT uid, slug, title FROM pages WHERE (pid IN (1068, 2229) OR uid IN (1068, 2229, 1093, 1311)) AND sys_language_uid=0 AND deleted=0 AND hidden=0 AND t3ver_wsid=0 AND doktype=1 ORDER BY pid, sorting`);
    const context = await visitor(browser);
    const page = await context.newPage();
    const stills = [];
    for (const [uid, slug, title] of rows) {
        try {
            await open(page, slug + '/');
        } catch (error) {
            console.warn(`${slug}: ${error.message.split('\n')[0]}`);
            continue;
        }
        const name = slug.replace(/^\//, '').replace(/\//g, '--');
        // The first screen, and the one below it.
        for (const [index, top] of [[0, 0], [1, 760]]) {
            const file = `${name}-${index}.jpg`;
            await shoot(page, join(dir, file), top);
            stills.push({ file: `pages/${file}`, uid: Number(uid), slug, title, index });
        }
    }
    await context.close();
    remember('stills-pages', { kind: 'stills', width: VIEW.width * DSF, height: VIEW.height * DSF, stills });
    console.log(`pages: ${stills.length} stills`);
}

const browser = await chromium.launch();
try {
    if (!only || only === 'elements') await elements(browser);
    if (!only || only === 'themes') await themes(browser);
    if (!only || only === 'pages') await pages(browser);
} finally {
    await browser.close();
}
