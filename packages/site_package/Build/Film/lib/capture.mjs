/**
 * Shared helpers of the film captures: paths, the footage manifest, a backend
 * browser context that saves nothing, and a screencast recorder.
 *
 * Footage lands in ~/.cache/lab-films/footage/<clip>/ (outside the lab tree,
 * which Mutagen syncs into the container; FILM_CACHE overrides): numbered
 * JPEG frames at 30 fps plus footage.json, which film.html reads.
 */

import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const FILM = resolve(dirname(fileURLToPath(import.meta.url)), '..');
export const LAB = resolve(FILM, '../../../..');
export const CACHE = process.env.FILM_CACHE ?? join(homedir(), '.cache/lab-films');
export const FOOTAGE = join(CACHE, 'footage');
export const BASE = process.env.LAB_BASE ?? 'https://webconsulting-typo3-lab.ddev.site';
export const FPS = 30;
/** 1536 × 864 CSS px at 1.25 device pixels: 1920 × 1080 frames. */
export const VIEW = { width: 1536, height: 864 };
export const DSF = 1.25;

export const { chromium } = createRequire(join(LAB, 'packages/desiderio/package.json'))('playwright');
export const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
export const ease = (p) => (p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2);
export const argument = (name) => process.argv.find((arg) => arg === `--${name}` || arg.startsWith(`--${name}=`))?.split('=')[1] ?? (process.argv.includes(`--${name}`) ? '' : undefined);

mkdirSync(FOOTAGE, { recursive: true });
const manifestFile = join(FOOTAGE, 'footage.json');

export function remember(name, entry) {
    const manifest = existsSync(manifestFile) ? JSON.parse(readFileSync(manifestFile, 'utf8')) : {};
    manifest[name] = { ...entry, fps: FPS, captured: new Date().toISOString() };
    writeFileSync(manifestFile, JSON.stringify(manifest, null, 2) + '\n');
}

/**
 * A browser context logged into the backend with the session cookie from
 * backend-session.php (FILM_SESSION, default var/film/backend-session.txt).
 * Nothing it does is saved: GET requests pass, so does the page-sync
 * preview (it computes a plan and writes nothing); every other request gets
 * the answer `fake` describes, so the UI behaves as after a real save.
 * `fake` stays mutable: a clip changes it before it starts.
 */
export async function backendContext(browser, fake) {
    const sessionFile = process.env.FILM_SESSION ?? join(LAB, 'var/film/backend-session.txt');
    const jwt = readFileSync(sessionFile, 'utf8').trim();
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: VIEW, deviceScaleFactor: DSF, locale: 'en-GB', acceptDownloads: true });
    const host = new URL(BASE).hostname;
    await context.addCookies([{ name: 'be_typo_user', value: jwt, domain: host, path: '/', secure: true, httpOnly: true, sameSite: 'Strict' }]);
    const json = (route, status, body) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
    const held = [];
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.hostname !== host) {
            held.push(`abort ${request.method()} ${url.href.slice(0, 100)}`);
            return route.abort();
        }
        const path = url.pathname;
        if (request.method() === 'GET' || request.method() === 'HEAD') {
            if (/collab\/presence/.test(path)) return json(route, 200, { ok: true, participants: fake.participants ?? [] });
            return route.continue();
        }
        if (/docx-editor\/page\/preview/.test(path)) return route.continue();
        held.push(`hold ${request.method()} ${path}`);
        if (/page\/apply/.test(path)) return json(route, 200, { ok: true, succeeded: true, results: fake.results ?? [] });
        if (/document\/save/.test(path) && fake.conflict) return json(route, 409, { ok: false, error: fake.conflict });
        return json(route, 200, { ok: true, participants: fake.participants ?? [], revision: 2, contentHash: 'film', sessionUid: 1 });
    });
    return { context, held };
}

/** Rectangles of a text in the page (shadow roots included), in CSS px. */
export function textBoxes(target, needle) {
    return target.evaluate((needle) => {
        const roots = [document];
        const boxes = [];
        while (roots.length) {
            const root = roots.shift();
            root.querySelectorAll('*').forEach((element) => { if (element.shadowRoot) roots.push(element.shadowRoot); });
            const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            for (let node = walker.nextNode(); node; node = walker.nextNode()) {
                const index = node.data.indexOf(needle);
                if (index < 0 || node.parentElement?.closest('title, script, style')) continue;
                const range = document.createRange();
                range.setStart(node, index);
                range.setEnd(node, index + needle.length);
                const rect = range.getBoundingClientRect();
                if (rect.width > 0) boxes.push({ x: rect.x, y: rect.y, width: rect.width, height: rect.height, cls: String(node.parentElement?.className ?? '') });
            }
        }
        return boxes;
    }, needle);
}

/**
 * Records the screen while `script` runs: a CDP screencast of JPEG frames
 * with their timestamps, turned into a constant 30 fps clip (each film frame
 * shows the newest screencast frame at that time). The script gets a
 * recorder: `mark(label)` notes a moment; `glide(x, y, ms)` and `click(x, y)`
 * drive the mouse and log the pointer path (the screencast has no cursor;
 * film.html draws one along this path); `type(text)` types like a person.
 */
export async function screencast(page, name, script, extra = {}) {
    const cdp = await page.context().newCDPSession(page);
    const dir = join(FOOTAGE, name);
    rmSync(dir, { recursive: true, force: true });
    const raw = join(dir, 'raw');
    mkdirSync(raw, { recursive: true });
    const shots = [];
    let stopped = false;
    cdp.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
        if (shots.length > 9000 || stopped) return;
        writeFileSync(join(raw, `${String(shots.length).padStart(5, '0')}.jpg`), Buffer.from(data, 'base64'));
        shots.push(metadata.timestamp * 1000);
        await cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
    });
    await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: VIEW.width * DSF, maxHeight: VIEW.height * DSF, everyNthFrame: 1 });
    await sleep(400);
    const start = Date.now();
    const at = () => Date.now() - start;
    const marks = {};
    const pointer = [];
    const clicks = [];
    let cursor = extra.cursor ?? [VIEW.width / 2, VIEW.height / 2];
    pointer.push([0, ...cursor]);
    const recorder = {
        sleep,
        mark: (label) => { marks[label] = at(); },
        glide: async (x, y, ms = 700) => {
            const [fromX, fromY] = cursor;
            const steps = Math.max(2, Math.round(ms / 25));
            for (let step = 1; step <= steps; step++) {
                const p = ease(step / steps);
                const px = fromX + (x - fromX) * p;
                const py = fromY + (y - fromY) * p;
                await page.mouse.move(px, py);
                pointer.push([at(), Math.round(px), Math.round(py)]);
                await sleep(ms / steps);
            }
            cursor = [x, y];
        },
        click: async (x, y, ms = 700) => {
            await recorder.glide(x, y, ms);
            await sleep(120);
            clicks.push(at());
            await page.mouse.down();
            await sleep(80);
            await page.mouse.up();
        },
        type: async (text) => {
            for (const char of text) {
                await page.keyboard.type(char);
                await sleep(45 + Math.random() * 55 + (char === ' ' ? 40 : 0));
            }
        },
        wheel: async (delta, ms) => {
            const steps = Math.max(1, Math.round(ms / 40));
            for (let step = 0; step < steps; step++) {
                await page.mouse.wheel(0, delta / steps);
                await sleep(ms / steps);
            }
        },
    };
    await script(recorder);
    await sleep(300);
    await cdp.send('Page.stopScreencast');
    stopped = true;
    const end = Date.now();
    // One ffmpeg pass: every screencast shot lasts until the next one.
    const list = shots.map((t, index) => {
        const next = index + 1 < shots.length ? shots[index + 1] : end;
        return `file '${join(raw, `${String(index).padStart(5, '0')}.jpg`)}'\nduration ${Math.max(0.001, (next - Math.max(t, start)) / 1000).toFixed(4)}`;
    });
    const before = shots.filter((t) => t < start).length;
    // The concat demuxer ignores the last duration: name the last shot twice, and pad.
    const last = `file '${join(raw, `${String(shots.length - 1).padStart(5, '0')}.jpg`)}'`;
    writeFileSync(join(raw, 'list.txt'), 'ffconcat version 1.0\n' + list.slice(Math.max(0, before - 1)).join('\n') + `\n${last}\n`);
    const frames = Math.floor(((end - start) / 1000) * FPS);
    spawnSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', join(raw, 'list.txt'),
        '-vf', `fps=${FPS},scale=${VIEW.width * DSF}:${VIEW.height * DSF},tpad=stop_mode=clone:stop_duration=3`, '-frames:v', String(frames), '-q:v', '2', '-start_number', '0', join(dir, '%04d.jpg')], { stdio: 'inherit' });
    rmSync(raw, { recursive: true, force: true });
    const scale = DSF;
    remember(name, {
        kind: 'screencast', frames, width: VIEW.width * DSF, height: VIEW.height * DSF, marks,
        pointer: pointer.map(([t, x, y]) => [t, Math.round(x * scale), Math.round(y * scale)]), clicks, ...extra,
    });
    console.log(`${name}: ${frames} frames (${(shots.length / ((end - start) / 1000)).toFixed(1)} shots/s), marks ${JSON.stringify(marks)}`);
    return marks;
}

/** Waits until the DOCX editor has laid out its pages. */
export async function editorReady(target) {
    await target.waitForSelector('.layout-run', { timeout: 90000 });
    await sleep(2500);
}
