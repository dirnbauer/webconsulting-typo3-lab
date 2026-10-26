#!/usr/bin/env node
/**
 * Captures the footage of the DOCX Editor film from the lab backend:
 *
 *   node packages/site_package/Build/Film/capture-docx.mjs            # everything
 *   node packages/site_package/Build/Film/capture-docx.mjs --only=page-edit,new-pages
 * node packages/site_package/Build/Film/capture-docx.mjs --word     # first let Microsoft Word edit the page again
 *
 * Needs:
 * - the sample documents in fileadmin/example-studio/ (docx/make-samples.py);
 * - a backend session of an English admin in the live workspace in
 *   var/film/backend-session.txt (backend-session.php);
 * - Microsoft Word on this Mac, driven by AppleScript, for the round trip:
 *   the page "For agencies and integrators" (983) is downloaded as a Word
 *   document, edited and saved by Word, and uploaded again in the film. That
 *   page has JPEG pictures only: Word saves WebP pictures as PNG, which the
 *   review then lists as a changed picture.
 *
 * Nothing is saved in TYPO3: lib/capture.mjs answers every write itself.
 */

import { execFileSync, spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { BASE, FOOTAGE, LAB, argument, backendContext, chromium, editorReady, remember, screencast, sleep, textBoxes } from './lib/capture.mjs';

const PAGE = 983;
const PARENT = 2232;
const FOLDER = '1:/example-studio/';
const BRIEF = 'website-relaunch-brief.docx';
const HANDBOOK = join(LAB, 'public/fileadmin/example-studio/team-handbook.docx');
/** Word is sandboxed: it writes only into folders it was once granted in a dialog. */
const WORD = process.env.FILM_WORD_DIR ?? join(LAB, 'var/film-scout/word');
/** The page as Word saved it after the edit; --word makes it again. */
const EDITED = join(WORD, 'word-edited.docx');
const only = argument('only')?.split(',');
const want = (name) => !only || only.includes(name);

const fake = {};
const browser = await chromium.launch({ headless: true });
const { context, held } = await backendContext(browser, fake);
const page = await context.newPage();

/** The module frame of the backend shell. */
const moduleFrame = () => page.frame({ name: 'list_frame' });
async function inShell(path, selector) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    for (let i = 0; i < 360; i++) {
        const frame = moduleFrame();
        if (frame && await frame.locator(selector).count().catch(() => 0)) {
            await sleep(2000);
            return frame;
        }
        await sleep(500);
    }
    throw new Error(`${path}: no "${selector}" in the module frame (${page.frames().map((f) => f.url().slice(0, 80)).join(', ')})`);
}
async function box(locator) {
    const rect = await locator.boundingBox();
    return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2, rect };
}
/** End of the layout run holding `word` (the n-th, or the one `pick` selects). */
async function runEnd(target, word, pick = (boxes) => boxes[0]) {
    const boxes = (await textBoxes(target, word)).filter((b) => b.cls.includes('layout-run'));
    const b = pick(boxes);
    if (!b) throw new Error(`no layout run "${word}"`);
    return { x: b.x + b.width - 1, y: b.y + b.height / 2 };
}

// The tokenized URLs come from the backend's own links.
const list = await inShell(`/typo3/module/file/list?id=${encodeURIComponent(FOLDER)}`, 'a[href*="docx-editor/edit"]');
const editUrl = BASE + await list.locator(`a[href*="docx-editor/edit"][href*="${BRIEF}"]`).first().getAttribute('href');
const layout = await inShell(`/typo3/module/web/layout?id=${PAGE}`, 'a[href*="docx-editor/page"]');
const pageUrl = BASE + (await layout.locator('a[href*="docx-editor/page?"]').first().getAttribute('href')).replace(/&returnUrl=.*/, '');
const downloadUrl = BASE + await layout.locator('a[href*="docx-editor/page/download"]').first().getAttribute('href');
const newUrl = BASE + (await layout.locator('a[href*="docx-editor/page/new"]').first().getAttribute('href')).replace(/([?&])id=\d+/, `$1id=${PARENT}`).replace(/&returnUrl=.*/, '');

// ---------------------------------------------------------------------------
// Microsoft Word: download the page, edit it in Word, save, PDF stills.
// ---------------------------------------------------------------------------
function word(script) {
    return execFileSync('osascript', ['-e', `with timeout of 60 seconds\ntell application "Microsoft Word"\n${script}\nend tell\nend timeout`], { encoding: 'utf8' }).trim();
}
if (argument('word') !== undefined) {
    mkdirSync(WORD, { recursive: true });
    const exported = join(WORD, 'for-agencies-and-integrators-en-us.docx');
    writeFileSync(exported, await (await context.request.get(downloadUrl)).body());
    execFileSync('osascript', ['-e', `tell application "Microsoft Word" to open POSIX file "${exported}"`]);
    await sleep(8000);
    word(`save as active document file name "${join(WORD, 'word-before.pdf')}" file format format PDF`);
    word([
        'set d to active document',
        'execute find (find object of text object of d) find text "in the kickoff meeting" replace with "in the first meeting" replace replace one',
        'execute find (find object of text object of d) find text "and you need no preparation." replace with "so there is nothing to prepare." replace replace one',
        'save d',
        `save as d file name "${join(WORD, 'word-after.pdf')}" file format format PDF`,
        'close d saving no',
    ].join('\n'));
    copyFileSync(exported, EDITED);
    console.log('word: edited and saved by Microsoft Word');
}
if (!existsSync(EDITED)) throw new Error(`${EDITED} is missing: run with --word once (Microsoft Word edits the page)`);
for (const state of ['before', 'after']) {
    spawnSync('pdftoppm', ['-r', '220', '-f', '1', '-l', '1', '-singlefile', '-png', join(WORD, `word-${state}.pdf`), join(FOOTAGE, `word-${state}`)]);
}
remember('word', { kind: 'stills', files: ['word-before.png', 'word-after.png'], document: 'for-agencies-and-integrators-en-us.docx', edits: ['in the kickoff meeting → in the first meeting', 'and you need no preparation. → so there is nothing to prepare.'] });

// ---------------------------------------------------------------------------
// The clips.
// ---------------------------------------------------------------------------
if (want('filelist')) {
    const frame = await inShell(`/typo3/module/file/list?id=${encodeURIComponent(FOLDER)}`, 'a[href*="docx-editor/edit"]');
    const icon = await box(frame.locator(`a[href*="docx-editor/edit"][href*="${BRIEF}"]`).filter({ visible: true }).first());
    await screencast(page, 'docx-filelist', async (rec) => {
        await rec.sleep(1000);
        await rec.glide(icon.x - 260, icon.y, 900);
        await rec.sleep(300);
        await rec.glide(icon.x, icon.y, 700);
        await rec.sleep(500);
        rec.mark('click');
        await rec.click(icon.x, icon.y, 100);
        await editorReady(moduleFrame());
        rec.mark('loaded');
        await rec.sleep(2500);
    }, { title: 'File list → Edit DOCX', cursor: [900, 520] });
}

if (want('file-edit')) {
    await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
    await editorReady(page);
    await screencast(page, 'docx-file-edit', async (rec) => {
        await rec.sleep(1200);
        rec.mark('scroll');
        await rec.glide(980, 620, 600);
        await rec.wheel(640, 2600);
        await rec.sleep(900);
        const end = await runEnd(page, 'German');
        await rec.click(end.x, end.y, 900);
        await page.keyboard.press('End');
        rec.mark('type');
        await page.keyboard.press('Enter');
        await rec.type('Keep the Word files the team already uses.');
        await rec.sleep(700);
        const save = await box(page.getByRole('button', { name: 'Save', exact: true }).first());
        rec.mark('save');
        await rec.click(save.x, save.y, 900);
        await rec.sleep(3200);
    });
}

if (want('conflict')) {
    fake.participants = [{ userId: 9, userName: 'Jonas Brandt', sessionUid: 1 }, { userId: 12, userName: 'Mara Lindqvist', sessionUid: 2 }];
    fake.conflict = 'Another editor saved a newer version. Reload the document to continue.';
    await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
    await editorReady(page);
    await sleep(2000);
    await screencast(page, 'docx-conflict', async (rec) => {
        await rec.sleep(800);
        const badge = await box(page.locator('[data-docx-presence]'));
        rec.mark('presence');
        await rec.glide(badge.x, badge.y + 4, 900);
        await rec.sleep(1300);
        const end = await runEnd(page, '2027');
        await rec.click(end.x, end.y, 900);
        await page.keyboard.press('End');
        await rec.type(': draft 2');
        await rec.sleep(500);
        const save = await box(page.getByRole('button', { name: 'Save', exact: true }).first());
        rec.mark('save');
        await rec.click(save.x, save.y, 900);
        await rec.sleep(800);
        rec.mark('conflict');
        const reload = await box(page.locator('[data-docx-reload]'));
        await rec.glide(reload.x, reload.y, 1100);
        await rec.sleep(2600);
    });
    delete fake.participants;
    delete fake.conflict;
}

if (want('page-menu')) {
    const frame = await inShell(`/typo3/module/web/layout?id=${PAGE}`, 'a[href*="docx-editor/page"]');
    const menu = await box(frame.locator('button.dropdown-toggle', { hasText: 'Word' }).first());
    await screencast(page, 'docx-page-menu', async (rec) => {
        await rec.sleep(1000);
        await rec.click(menu.x, menu.y, 1000);
        rec.mark('menu');
        await rec.sleep(600);
        const item = await box(frame.getByText('Edit in Word', { exact: true }).first());
        const download = await box(frame.getByText('Download as Word document').first());
        const subpages = await box(frame.getByText('Import Word document as subpages').first());
        await rec.glide(subpages.x - 40, subpages.y, 700);
        await rec.sleep(500);
        await rec.glide(download.x - 40, download.y, 500);
        await rec.sleep(500);
        await rec.glide(item.x - 40, item.y, 500);
        await rec.sleep(700);
        rec.mark('click');
        await rec.click(item.x - 40, item.y, 80);
        await editorReady(moduleFrame());
        rec.mark('loaded');
        await rec.sleep(2200);
    }, { cursor: [1000, 400] });
}

if (want('page-edit')) {
    fake.results = [{ created: [], updated: { 158258: 158258 }, deleted: [], moved: [], translated: [], errors: [], workspace: 0, page: PAGE, pageTitle: 'For agencies and integrators', pageUrl: '#' }];
    await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });
    await editorReady(page);
    await screencast(page, 'docx-page-edit', async (rec) => {
        await rec.sleep(1500);
        // The eyebrow "For agencies": the smallest run of that word.
        const end = await runEnd(page, 'agencies', (boxes) => boxes.filter((b) => b.y > 300).sort((a, b) => a.height - b.height)[0]);
        await rec.click(end.x, end.y, 1000);
        await page.keyboard.press('End');
        rec.mark('type');
        await rec.type(' and integrators');
        await rec.sleep(800);
        const review = await box(page.getByRole('button', { name: 'Review and save' }));
        rec.mark('review');
        await rec.click(review.x, review.y, 1000);
        await page.waitForSelector('.modal .btn-primary', { timeout: 30000 });
        rec.mark('dialog');
        await rec.sleep(2800);
        const apply = await box(page.locator('.modal button[name="apply"]'));
        await rec.click(apply.x, apply.y, 1000);
        rec.mark('imported');
        await rec.sleep(3200);
    });
}

if (want('download')) {
    await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });
    await editorReady(page);
    page.on('download', (download) => download.cancel().catch(() => {}));
    await screencast(page, 'docx-download', async (rec) => {
        await rec.sleep(1000);
        const menu = await box(page.locator('button.dropdown-toggle', { hasText: 'Word' }).first());
        await rec.click(menu.x, menu.y, 900);
        await rec.sleep(700);
        const item = await box(page.getByText('Download as Word document').first());
        await rec.glide(item.x - 30, item.y, 700);
        await rec.sleep(600);
        rec.mark('click');
        await rec.click(item.x - 30, item.y, 80);
        await rec.sleep(1800);
    }, { cursor: [700, 300] });
}

if (want('upload')) {
    fake.results = [{ created: [], updated: { 158260: 158260 }, deleted: [], moved: [], translated: [], errors: [], workspace: 0, page: PAGE, pageTitle: 'For agencies and integrators', pageUrl: '#' }];
    await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });
    await editorReady(page);
    await screencast(page, 'docx-upload', async (rec) => {
        await rec.sleep(1000);
        const menu = await box(page.locator('button.dropdown-toggle', { hasText: 'Word' }).first());
        await rec.click(menu.x, menu.y, 900);
        await rec.sleep(700);
        const item = await box(page.getByText('Upload Word document').first());
        await rec.glide(item.x - 30, item.y, 700);
        await rec.sleep(600);
        const chooser = page.waitForEvent('filechooser');
        rec.mark('click');
        await rec.click(item.x - 30, item.y, 80);
        await (await chooser).setFiles(EDITED);
        await page.waitForSelector('.modal .btn-primary', { timeout: 60000 });
        rec.mark('dialog');
        await rec.sleep(3800);
        const apply = await box(page.locator('.modal button[name="apply"]'));
        await rec.click(apply.x, apply.y, 1000);
        rec.mark('imported');
        await rec.sleep(3000);
    }, { cursor: [700, 300] });
}

if (want('new-pages')) {
    const titles = ['Welcome to Example Studio', 'How we work', 'Tools we use'];
    fake.results = titles.map((title, index) => ({ created: index === 0 ? [1, 2, 3] : [1, 2, 3, 4], updated: [], deleted: [], moved: [], translated: [], errors: [], workspace: 0, page: 9900 + index, pageTitle: title, pageUrl: '#' }));
    await page.goto(newUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('input[type=file]');
    await sleep(1500);
    await screencast(page, 'docx-new-pages', async (rec) => {
        await rec.sleep(1000);
        const input = await box(page.locator('input[type=file]'));
        const chooser = page.waitForEvent('filechooser');
        await rec.click(input.rect.x + 50, input.y, 900);
        await (await chooser).setFiles(HANDBOOK);
        rec.mark('file');
        await rec.sleep(900);
        const split = await box(page.locator('input[type=radio]').nth(1));
        await rec.click(split.x, split.y, 800);
        await rec.sleep(700);
        const submit = await box(page.getByRole('button', { name: 'Review import' }));
        rec.mark('review');
        await rec.click(submit.x, submit.y, 800);
        await page.waitForSelector('.modal .btn-primary', { timeout: 60000 });
        rec.mark('dialog');
        await rec.sleep(2200);
        const body = await box(page.locator('.modal-body'));
        await rec.glide(body.x, body.y + 60, 600);
        await rec.wheel(700, 3000);
        await rec.sleep(1000);
        const apply = await box(page.locator('.modal button[name="apply"]'));
        await rec.click(apply.x, apply.y, 1000);
        rec.mark('imported');
        await rec.sleep(3200);
    }, { cursor: [700, 400] });
}

console.log(`held ${held.length} requests:\n  ${[...new Set(held)].join('\n  ')}`);
await browser.close();
