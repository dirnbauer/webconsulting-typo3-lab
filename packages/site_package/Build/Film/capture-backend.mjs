#!/usr/bin/env node
/**
 * Backend stills for the overview films, where a tour screenshot from
 * Desiderio's Styleguide folder does not fit: the Desiderio tour shows the
 * page module in German, the film is English.
 *
 *   ddev exec php packages/site_package/Build/Film/backend-session.php 9 > var/film/backend-session.txt
 *   node capture-backend.mjs
 *
 * Writes JPEG stills to <cache>/footage/stills/backend/ and their list to
 * footage.json under "stills-backend". Each still is one content element's
 * card in the page module, in the default language, with the backend in dark.
 */

import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { BASE, DSF, FOOTAGE, VIEW, backendContext, chromium, remember, sleep } from './lib/capture.mjs';

const STILLS = [
    // The 3-column feature grid of the Desiderio feature page, as in the tour shot.
    { name: 'desiderio-page-module', page: 667, element: 167616 },
];

const browser = await chromium.launch();
try {
    const { context } = await backendContext(browser, {});
    const page = await context.newPage();
    await page.emulateMedia({ colorScheme: 'dark' });
    const dir = join(FOOTAGE, 'stills/backend');
    mkdirSync(dir, { recursive: true });
    const stills = [];
    for (const still of STILLS) {
        await page.goto(`${BASE}/typo3/module/web/layout?id=${still.page}&language=0`, { waitUntil: 'load', timeout: 240000 });
        // The module runs in the backend's content frame.
        const card = page.frameLocator('#typo3-contentIframe').locator(`#element-tt_content-${still.element} > .t3-page-ce-element`);
        await card.waitFor({ timeout: 120000 });
        await card.scrollIntoViewIfNeeded();
        await sleep(800);
        const box = await card.boundingBox();
        if (!box) throw new Error(`No card for tt_content ${still.element}`);
        const margin = 12;
        const clip = {
            x: Math.max(0, box.x - margin),
            y: Math.max(0, box.y - margin),
            width: Math.min(VIEW.width, box.width + 2 * margin),
            height: box.height + 2 * margin,
        };
        const file = join(dir, `${still.name}.jpg`);
        await page.screenshot({ path: file, type: 'jpeg', quality: 92, clip });
        stills.push({ name: still.name, file: `stills/backend/${still.name}.jpg`, width: Math.round(clip.width * DSF), height: Math.round(clip.height * DSF) });
        console.log(`${still.name}: ${file}`);
    }
    remember('stills-backend', { kind: 'stills', stills });
} finally {
    await browser.close();
}
