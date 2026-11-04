#!/usr/bin/env node
/**
 * Renders the portrait talk films (1080 × 1920, 25 fps): the clean HeyGen
 * presenter render underneath, the overlay stage (talk.html) on top, Kurt's
 * voice with a ducked music bed, English WebVTT captions, a poster and
 * review sheets.
 *
 *   node render-talk.mjs --cut=short --timeline        # align script ↔ whisper, resolve cuts/short.mjs → <cache>/short.timeline.json
 *   node render-talk.mjs --cut=short --check           # face-safe + mobile-safe check (every frame, DOM) + alpha check (stills at every overlay's midpoint)
 *   node render-talk.mjs --cut=short --stills=3.2,12.5 # composited PNG stills → <out>/stills/
 *   node render-talk.mjs --cut=short --render          # picture + sound + captions + poster + sheet → <out>/usability-slogans-short.*
 *   node render-talk.mjs --cut=short --audio           # sound only, muxed onto the current picture
 *
 * Needs <cache>/<cut>.words.json (fal.mjs --transcribe), <cache>/<cut>.face.json
 * (face.py), <cache>/music-<cut>-1.mp3 (fal.mjs --music). Paths: paths.mjs.
 */

import { spawn, spawnSync } from 'node:child_process';
import { createReadStream, existsSync, mkdirSync, readFileSync, renameSync, statSync, unlinkSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, normalize } from 'node:path';
import { CACHE, FPS, LAB, OUT, RENDER, SIZE, TALK, argument, playwright } from './paths.mjs';
import { resolve, segments, words } from './timeline.mjs';

const cut = argument('cut') ?? 'short';
const spec = (await import(`./cuts/${cut}.mjs`)).default;
const presenter = join(RENDER, `${cut}-presenter.mp4`);
const NAME = `usability-slogans-${cut}`;
/** Video bitrate caps: CRF 20, capped so the files stay web-sized (long < 60 MB, short < 15 MB). */
const CAP = { long: '1400k', short: '1750k' }[cut] ?? '1800k';
const SAFE = { top: Math.round(SIZE.height * 0.08), bottom: Math.round(SIZE.height * 0.88), side: 48 };
mkdirSync(OUT, { recursive: true });
mkdirSync(CACHE, { recursive: true });

const run = (cmd, args) => {
    const result = spawnSync(cmd, args, { encoding: 'utf8', maxBuffer: 1 << 30 });
    if (result.status !== 0) throw new Error(`${cmd} ${args.join(' ').slice(0, 200)}: ${result.stderr?.slice(-600)}`);
    return result;
};
const ffmpeg = (args, input) => new Promise((done, fail) => {
    const child = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args], { stdio: [input ? 'pipe' : 'ignore', 'inherit', 'inherit'] });
    child.on('error', fail);
    child.on('close', (code) => (code === 0 ? done() : fail(new Error(`ffmpeg exited with ${code}`))));
    if (input) input(child.stdin);
});
const probe = (file, entries) => run('ffprobe', ['-v', 'error', '-show_entries', entries, '-of', 'csv=p=0', file]).stdout.trim();
const loudness = (file, extra = []) => {
    const report = run('ffmpeg', ['-hide_banner', '-nostats', ...extra, '-i', file, '-af', 'ebur128=peak=true:framelog=quiet', '-f', 'null', '-']).stderr;
    const pick = (re) => Number([...report.matchAll(re)].pop()?.[1]);
    return { I: pick(/I:\s+(-?[0-9.]+) LUFS/g), LRA: pick(/LRA:\s+([0-9.]+) LU/g), TP: pick(/Peak:\s+(-?[0-9.]+) dBFS/g) };
};

// ---------------------------------------------------------------- timeline
function buildTimeline() {
    const aligned = words(cut);
    const timeline = resolve(spec, aligned.words);
    timeline.words = aligned.words.map(({ text, start, end, paragraph, guessed }) => ({ text, start, end, paragraph, ...(guessed ? { guessed } : {}) }));
    timeline.presenter = Number(probe(presenter, 'format=duration'));
    timeline.poster = spec.poster ?? null;
    writeFileSync(join(CACHE, `${cut}.timeline.json`), JSON.stringify(timeline, null, 1) + '\n');
    console.log(`${cut}: ${aligned.matched}/${aligned.total} script words aligned (${aligned.heardTotal} heard), voice ends ${timeline.voiceEnd.toFixed(2)} s, film ${timeline.duration.toFixed(2)} s, presenter ${timeline.presenter.toFixed(2)} s`);
    return timeline;
}
const timeline = buildTimeline();
const frameCount = Math.round(timeline.duration * FPS);

// ---------------------------------------------------------------- stage
const TYPES = { '.html': 'text/html', '.mjs': 'text/javascript', '.json': 'application/json', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.png': 'image/png' };
const server = createServer((request, response) => {
    const path = normalize(decodeURIComponent(new URL(request.url, 'http://localhost').pathname));
    const [base, rest] = path.startsWith('/cache/') ? [CACHE, path.slice('/cache'.length)] : [LAB, path];
    const file = join(base, rest);
    if (!file.startsWith(base) || !existsSync(file) || statSync(file).isDirectory()) return void response.writeHead(404).end();
    response.writeHead(200, { 'Content-Type': TYPES[extname(file)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
    createReadStream(file).pipe(response);
});
await new Promise((done) => server.listen(0, '127.0.0.1', done));
const stageUrl = (extra = '') => `http://127.0.0.1:${server.address().port}${TALK.slice(LAB.length)}/talk.html?timeline=/cache/${cut}.timeline.json${extra}`;
const { chromium } = playwright();
const browser = await chromium.launch();

async function openStage(extra = '') {
    const page = await browser.newPage({ viewport: SIZE, deviceScaleFactor: 1 });
    page.on('pageerror', (error) => console.error(`talk.html: ${error.message}`));
    await page.goto(stageUrl(extra), { waitUntil: 'load' });
    await page.waitForFunction(() => window.ready !== undefined, null, { timeout: 30000 });
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Emulation.setDefaultBackgroundColorOverride', { color: { r: 0, g: 0, b: 0, a: 0 } });
    const capture = async () => Buffer.from((await cdp.send('Page.captureScreenshot', { format: 'png', optimizeForSpeed: true, fromSurface: true, captureBeyondViewport: false })).data, 'base64');
    const shot = async (seconds) => {
        await page.evaluate((ms) => window.seek(ms), seconds * 1000);
        return capture();
    };
    return { page, shot, capture };
}

/** A presenter frame at t (the last frame held past its end), as PNG. */
function presenterFrame(t, file) {
    const at = Math.min(t, timeline.presenter - 1 / FPS);
    run('ffmpeg', ['-v', 'error', '-y', '-ss', at.toFixed(3), '-i', presenter, '-frames:v', '1', file]);
}

async function still(stage, t, file) {
    const overlay = join(CACHE, 'still-overlay.png');
    const under = join(CACHE, 'still-under.png');
    writeFileSync(overlay, await stage.shot(t));
    presenterFrame(t, under);
    run('ffmpeg', ['-v', 'error', '-y', '-i', under, '-i', overlay, '-filter_complex', '[0:v][1:v]overlay=0:0:format=rgb', '-frames:v', '1', file]);
}

// ---------------------------------------------------------------- face-safe check
async function check() {
    const face = JSON.parse(readFileSync(join(CACHE, `${cut}.face.json`), 'utf8'));
    const [px0, py0, px1, py1] = face.protected;
    const stage = await openStage();
    const hits = (b) => b[0] < px1 && b[2] > px0 && b[1] < py1 && b[3] > py0;
    const problems = [];
    let frames = 0;
    let textFrames = 0;
    let lowestTop = Infinity;
    let maxBottom = 0;
    for (let f = 0; f < frameCount; f++) {
        const t = f / FPS;
        const items = await stage.page.evaluate((ms) => { window.seek(ms); return window.layout(); }, t * 1000);
        frames++;
        const covered = items.some((item) => item.kind === 'cover' && item.opacity >= 0.999 && item.box[0] <= 0 && item.box[1] <= 0 && item.box[2] >= SIZE.width && item.box[3] >= SIZE.height);
        const texts = items.filter((item) => item.kind === 'text');
        if (texts.length) textFrames++;
        for (const item of texts) {
            if (!covered) {
                lowestTop = Math.min(lowestTop, item.box[1]);
                if (hits(item.box)) problems.push(`${t.toFixed(2)} s: ${item.cls} ${item.box} over the protected box (opacity ${item.opacity.toFixed(3)})`);
            }
            maxBottom = Math.max(maxBottom, item.box[3]);
            if (item.box[1] < SAFE.top || item.box[3] > SAFE.bottom || item.box[0] < SAFE.side || item.box[2] > SIZE.width - SAFE.side) {
                problems.push(`${t.toFixed(2)} s: ${item.cls} ${item.box} outside the mobile-safe area`);
            }
        }
    }
    // Pixels: at every overlay's midpoint (and each line's), the overlay's alpha inside the protected box.
    const moments = [];
    for (const event of timeline.events) {
        if (event.type === 'card' || event.type === 'end') continue;
        if (event.type === 'deck') {
            for (const [i, slide] of event.slides.entries()) {
                moments.push([`deck ${slide.n} swap`, slide.at + 0.12], [`deck ${slide.n} headline`, slide.at + 0.6]);
                if (slide.phrase) moments.push([`deck ${slide.n} phrase`, Math.min(event.slides[i + 1]?.at ?? event.end, slide.phrase.at + 0.6) - 0.05]);
            }
            continue;
        }
        moments.push([event.type + (event.n ? ` ${event.n}` : ''), (event.start + event.end) / 2]);
        for (const item of event.items ?? []) moments.push([`${event.type} ${event.n ?? ''} "${item.text}"`, Math.min(event.end - 0.35, item.at + 0.6)]);
    }
    // Card and end-card transitions: the cover is translucent only in these windows (sampled at 15/40/65/90 % of each fade).
    const { FIN, FOUT } = await stage.page.evaluate(() => window.FADES);
    const share = [0.15, 0.4, 0.65, 0.9];
    for (const event of timeline.events.filter((e) => e.type === 'card' || e.type === 'end')) {
        const fadeIn = event.type === 'end' ? event.fade : FIN;
        for (const f of share) moments.push([`${event.type} ${event.n ?? ''} fade-in ${Math.round(f * 100)} %`, event.start + f * fadeIn]);
        if (event.type === 'card') for (const f of share) moments.push([`card ${event.n} fade-out ${Math.round(f * 100)} %`, event.end - f * FOUT]);
    }
    const pixels = [];
    for (const [label, t] of moments) {
        const png = await stage.shot(t);
        const raw = spawnSync('ffmpeg', ['-v', 'error', '-f', 'png_pipe', '-i', '-', '-vf', `crop=${px1 - px0}:${py1 - py0}:${px0}:${py0}`, '-f', 'rawvideo', '-pix_fmt', 'rgba', '-'], { input: png, maxBuffer: 1 << 28 }).stdout;
        let maxAlpha = 0;
        let count = 0;
        for (let i = 3; i < raw.length; i += 4) if (raw[i] > 2) { count++; maxAlpha = Math.max(maxAlpha, raw[i]); }
        // A translucent cover (no text) is allowed during a cross-fade; text pixels over a visible face are not.
        const layout = await stage.page.evaluate(() => window.layout());
        const fullyCovered = layout.some((item) => item.kind === 'cover' && item.opacity >= 0.999 && item.box[0] <= 0 && item.box[1] <= 0 && item.box[2] >= SIZE.width && item.box[3] >= SIZE.height);
        const textInBox = layout.some((item) => item.kind === 'text' && hits(item.box)) && !fullyCovered;
        const coverOnly = layout.every((item) => item.kind === 'cover' || !hits(item.box));
        // Pass: nothing in the box; or only a translucent cover (a cross-fade, no text); or Kurt fully hidden by an opaque card.
        const ok = !textInBox && (count === 0 || coverOnly || fullyCovered);
        pixels.push({ label, t: Number(t.toFixed(2)), alphaPixelsInBox: count, maxAlpha, kurtHidden: fullyCovered, ok });
        if (!ok) problems.push(`pixel check ${label} @ ${t.toFixed(2)} s: ${count} overlay pixels in the protected box`);
    }
    const report = {
        cut, protected: face.protected, headUnion: face.head_union, faceUnion: face.face_union, eyesY: face.eyes_y, faceSamples: face.samples, missed: face.missed.length,
        safeArea: SAFE, framesChecked: frames, framesWithText: textFrames, lowestOverlayTopOverKurt: lowestTop, clearanceToProtectedBox: lowestTop - py1, maxTextBottom: maxBottom,
        pixelMoments: pixels.length, pixelFailures: pixels.filter((p) => !p.ok).length, problems,
    };
    writeFileSync(join(OUT, `${NAME}.face-check.json`), JSON.stringify({ ...report, pixels }, null, 1) + '\n');
    console.log(JSON.stringify({ ...report, problems: problems.slice(0, 20), problemCount: problems.length }, null, 1));
    await stage.page.close();
    return problems.length === 0;
}

// ---------------------------------------------------------------- slogan card layout
/**
 * Every slogan card must share one layout: the headline's first line starts
 * at the same y on all ten. Measured on the delivered video: the first pixel
 * row (inside the text column) with ≥ 12 near-white pixels, below the eyebrow.
 * Long cut: full-screen cards; short cut: the slides of the lower-band deck.
 */
function cardPositions(file) {
    const shots = [];
    for (const event of timeline.events) {
        if (event.type === 'card') shots.push({ n: event.n, t: event.end - 0.25 - 0.3, x: [96, 984], y: [600, 1450] });
        if (event.type === 'deck') {
            event.slides.forEach((slide, i) => {
                const next = event.slides[i + 1]?.at ?? event.end;
                shots.push({ n: slide.n, t: Math.min(slide.at + 0.7, next - 0.05), x: [120, 960], y: [1150, 1700] });
            });
        }
    }
    const dir = join(OUT, 'stills', 'cards');
    mkdirSync(dir, { recursive: true });
    const rows = shots.map(({ n, t, x, y }) => {
        const png = join(dir, `${NAME}-card-${String(n).padStart(2, '0')}.png`);
        frameFrom(file, t, png);
        const w = x[1] - x[0];
        const h = y[1] - y[0];
        const raw = spawnSync('ffmpeg', ['-v', 'error', '-i', png, '-vf', `crop=${w}:${h}:${x[0]}:${y[0]}`, '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-'], { maxBuffer: 1 << 28 }).stdout;
        let top = null;
        for (let r = 0; r < h && top === null; r++) {
            let white = 0;
            for (let c = 0; c < w; c++) {
                const i = (r * w + c) * 3;
                if (raw[i] > 225 && raw[i + 1] > 225 && raw[i + 2] > 225) white++;
            }
            if (white >= 12) top = y[0] + r;
        }
        return { n, t: Number(t.toFixed(2)), headlineTop: top };
    });
    const tops = rows.map((row) => row.headlineTop).filter((v) => v !== null);
    const spread = tops.length ? Math.max(...tops) - Math.min(...tops) : Infinity;
    const result = { cards: rows.length, spread, ok: tops.length === rows.length && rows.length === 10 && spread <= 2, rows };
    writeFileSync(join(OUT, `${NAME}.card-check.json`), JSON.stringify(result, null, 1) + '\n');
    console.log(`slogan cards: headline top ${[...new Set(tops)].join(', ')} px over ${rows.length} cards, spread ${spread} px → ${result.ok ? 'OK' : 'FAIL'}`);
    if (!result.ok) process.exitCode = 1;
    return result;
}

// ---------------------------------------------------------------- picture
/**
 * The overlay as lossless RGBA parts (FFV1, one per worker), then one ffmpeg
 * pass puts them over the presenter (its last frame held past its end) and
 * encodes the delivery file. (Compositing inside each worker's live pipe
 * lost frames under load: the overlay is written to disk first. Both streams
 * are re-timed by frame index: the concatenated parts carry timestamp gaps
 * that would otherwise slide the overlay late.)
 */
async function picture(file) {
    const workers = Number(argument('workers') ?? 6);
    const per = Math.ceil(frameCount / workers);
    const parts = [];
    const started = Date.now();
    let shots = 0;
    let reused = 0;
    await Promise.all(Array.from({ length: workers }, async (_, k) => {
        const first = k * per;
        const last = Math.min(frameCount, first + per);
        if (first >= last) return;
        const stage = await openStage();
        const part = join(CACHE, `${cut}-overlay-${k}.mkv`);
        parts[k] = { part, frames: last - first };
        let previous = null;
        let previousKey = null;
        await ffmpeg(['-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'png', '-i', '-', '-c:v', 'ffv1', '-pix_fmt', 'bgra', '-r', String(FPS), part], async (stdin) => {
            for (let f = first; f < last; f++) {
                // Identical stage state → identical pixels: reuse the last screenshot.
                const key = await stage.page.evaluate((ms) => { window.seek(ms); return document.getElementById('stage').innerHTML; }, (f / FPS) * 1000);
                if (key !== previousKey) {
                    previous = await stage.capture();
                    previousKey = key;
                    shots++;
                } else reused++;
                if (!stdin.write(previous)) await new Promise((done) => stdin.once('drain', done));
            }
            stdin.end();
        });
        await stage.page.close();
    }));
    for (const { part, frames } of parts.filter(Boolean)) {
        const got = Number(probe(part, 'stream=nb_frames').split(',')[0]) || Number(run('ffprobe', ['-v', 'error', '-count_frames', '-show_entries', 'stream=nb_read_frames', '-of', 'csv=p=0', part]).stdout.trim());
        if (got !== frames) throw new Error(`${part}: ${got} frames, expected ${frames}`);
    }
    const list = join(CACHE, `${cut}-overlay.txt`);
    writeFileSync(list, parts.filter(Boolean).map(({ part }) => `file '${part}'\n`).join(''));
    console.log(`${cut}: overlay ${frameCount} frames (${shots} screenshots, ${reused} reused) in ${Math.round((Date.now() - started) / 1000)} s`);
    const graph = `[0:v]tpad=stop_mode=clone:stop_duration=30,setpts=N/(${FPS}*TB)[bg];[1:v]setpts=N/(${FPS}*TB)[ov];[bg][ov]overlay=0:0:format=rgb:eof_action=pass,format=yuv420p[v]`;
    await ffmpeg(['-i', presenter, '-f', 'concat', '-safe', '0', '-i', list, '-filter_complex', graph, '-map', '[v]', '-frames:v', String(frameCount), '-r', String(FPS),
        '-c:v', 'libx264', '-preset', 'slow', '-crf', '20', '-maxrate', CAP, '-bufsize', `${parseInt(CAP, 10) * 2}k`, '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p', '-g', String(FPS * 4), '-an', '-movflags', '+faststart', file]);
    const frames = Number(run('ffprobe', ['-v', 'error', '-count_frames', '-show_entries', 'stream=nb_read_frames', '-of', 'csv=p=0', file]).stdout.trim());
    if (frames !== frameCount) throw new Error(`${file}: ${frames} frames, expected ${frameCount}`);
    if (!process.env.KEEP_PARTS) parts.filter(Boolean).forEach(({ part }) => unlinkSync(part));
    console.log(`${cut}: composited ${frames} frames in ${Math.round((Date.now() - started) / 1000)} s`);
}

// ---------------------------------------------------------------- sound
/** Music gain envelope in dB relative to the bed's base level, per 10 ms. */
/** Short-term loudness (EBU R128 S, 3 s window) of a file every 0.1 s: [[t, S], …]. */
function profile(file) {
    const text = run('ffmpeg', ['-hide_banner', '-nostats', '-v', 'error', '-i', file, '-af', 'ebur128=metadata=1,ametadata=print:key=lavfi.r128.S:file=-', '-f', 'null', '-']).stdout;
    const points = [];
    let t = null;
    for (const line of text.split('\n')) {
        const time = line.match(/pts_time:([0-9.]+)/);
        if (time) t = Number(time[1]);
        const value = line.match(/lavfi\.r128\.S=(-?[0-9.]+)/);
        if (value && t !== null) points.push([t, Math.max(-70, Number(value[1]))]);
    }
    return points;
}

function envelope(duration, level = () => 0) {
    const step = 0.01;
    const n = Math.ceil(duration / step) + 1;
    const speech = segments(timeline.words, 0.45);
    const voiceEnd = timeline.voiceEnd;
    const LIFT = 3.5;         // in pauses of 0.7 s or more
    const SWELL = 9;          // after the last word, under the end card
    const target = new Float32Array(n);
    for (let i = 0; i < n; i++) {
        const t = i * step;
        const talking = speech.some(([a, b], k) => {
            const next = speech[k + 1];
            const gapAfter = next ? next[0] - b : Infinity;
            // Short pauses stay ducked: the bed only breathes in real pauses.
            return t >= a - 0.15 && t <= b + (gapAfter < 0.7 ? gapAfter : 0.3);
        });
        target[i] = talking ? 0 : LIFT;
        if (t > voiceEnd + 0.25) target[i] = SWELL * Math.min(1, (t - voiceEnd - 0.25) / 1.6);
    }
    // 250 ms moving average in dB: no steps, no pumping.
    const smooth = new Float32Array(n);
    const half = 12;
    for (let i = 0; i < n; i++) {
        let sum = 0;
        let count = 0;
        for (let k = Math.max(0, i - half); k <= Math.min(n - 1, i + half); k++) { sum += target[k]; count++; }
        smooth[i] = sum / count;
    }
    const gain = new Float32Array(n);
    for (let i = 0; i < n; i++) {
        const t = i * step;
        let g = 10 ** ((smooth[i] + level(t)) / 20);
        // Enters softly over 3 s; fades out over the last 3 s, to silence on the last frame.
        g *= Math.sin(Math.min(1, t / 3.0) * Math.PI / 2) ** 2;
        g *= Math.cos(Math.min(1, Math.max(0, (t - (duration - 3.0)) / 3.0)) * Math.PI / 2) ** 2;
        gain[i] = g;
    }
    return { step, gain };
}

async function sound(file) {
    const duration = timeline.duration;
    const rate = 48000;
    const voiceRaw = join(CACHE, `${cut}-voice.wav`);
    run('ffmpeg', ['-v', 'error', '-y', '-i', presenter, '-vn', '-ac', '2', '-ar', String(rate), '-af', `apad=whole_dur=${duration}`, '-t', duration.toFixed(3), voiceRaw]);
    const voice = loudness(voiceRaw);
    const voiceGain = -16 - voice.I;
    const musicFile = join(CACHE, argument('music') ?? `music-${cut}-1.mp3`);
    const musicLevel = loudness(musicFile);
    const UNDER = Number(argument('under') ?? 20);
    // Where the bed sits: its resolved ending (the last moment within 6 LU of its median, + 1.5 s) lands on the film's
    // last frames, under the end card. A longer bed starts later in the take; a shorter one is
    // slowed (≥ 0.965, pitch kept) and, if still short, enters a little later.
    const bedProfile = profile(musicFile);
    const loud = bedProfile.filter(([, S]) => S > -60).map(([, S]) => S).sort((x, y) => x - y);
    const median = loud[Math.floor(loud.length / 2)];
    const bedLength = Number(probe(musicFile, 'format=duration'));
    const musicEnd = Math.min(bedLength, Math.max(...bedProfile.filter(([, S]) => S >= median - 6).map(([t]) => t)) + 1.5);
    const aim = duration - 0.3;
    let tempo = 1;
    let musicStart = 0;
    let delay = 0;
    if (musicEnd >= aim) musicStart = musicEnd - aim;
    else {
        tempo = Math.max(0.965, musicEnd / aim);
        delay = Math.max(0, aim - musicEnd / tempo);
    }
    // Levelling: the take's own swells and dips are evened out (6 s average, −10…+6 dB) so the
    // bed sits UNDER dB below the voice all through; after the last word it is frozen, so the
    // take's own resolution plays as written.
    const S = (src) => bedProfile[Math.min(bedProfile.length - 1, Math.max(0, Math.round(src * 10)))]?.[1] ?? -70;
    const source = (t) => musicStart + (t - delay) * tempo;
    const levelAt = (t) => {
        let sum = 0;
        let count = 0;
        for (let x = t - 3; x <= t + 3; x += 0.25) { sum += S(source(x)); count++; }
        return Math.min(6, Math.max(-10, median - sum / count));
    };
    const frozen = levelAt(timeline.voiceEnd);
    const levels = new Float32Array(Math.ceil(duration * 10) + 2);
    for (let i = 0; i < levels.length; i++) levels[i] = i / 10 < delay ? 0 : i / 10 > timeline.voiceEnd ? frozen : levelAt(i / 10);
    const level = (t) => levels[Math.min(levels.length - 1, Math.round(t * 10))];
    // The bed's base level: its median short-term loudness UNDER dB below the voice's -16 LUFS.
    const base = 10 ** ((-16 - UNDER - median) / 20);
    const filters = [...(tempo < 1 ? [`atempo=${tempo.toFixed(4)}`] : []), ...(delay > 0 ? [`adelay=${Math.round(delay * 1000)}:all=1`] : []), 'apad'];
    const decoded = spawnSync('ffmpeg', ['-v', 'error', '-ss', musicStart.toFixed(3), '-i', musicFile, '-af', filters.join(','), '-t', duration.toFixed(3), '-ac', '2', '-ar', String(rate), '-f', 'f32le', '-'], { maxBuffer: 1 << 30 }).stdout;
    const pcm = new Float32Array(decoded.buffer.slice(decoded.byteOffset, decoded.byteOffset + decoded.byteLength));
    const total = Math.round(duration * rate);
    const shaped = new Float32Array(total * 2);
    const { step, gain } = envelope(duration, level);
    for (let s = 0; s < total; s++) {
        const pos = s / rate / step;
        const i = Math.floor(pos);
        const g = (gain[i] ?? 0) + ((gain[i + 1] ?? 0) - (gain[i] ?? 0)) * (pos - i);
        shaped[2 * s] = (pcm[2 * s] ?? 0) * g * base;
        shaped[2 * s + 1] = (pcm[2 * s + 1] ?? 0) * g * base;
    }
    const musicRaw = join(CACHE, `${cut}-music.f32`);
    writeFileSync(musicRaw, Buffer.from(shaped.buffer));
    const mix = join(CACHE, `${cut}-mix.wav`);
    run('ffmpeg', ['-v', 'error', '-y', '-i', voiceRaw, '-f', 'f32le', '-ar', String(rate), '-ac', '2', '-i', musicRaw,
        '-filter_complex', `[0:a]volume=${voiceGain.toFixed(2)}dB[v];[v][1:a]amix=inputs=2:normalize=0:duration=first[m]`, '-map', '[m]', '-c:a', 'pcm_f32le', mix]);
    const mixed = loudness(mix);
    const finalGain = -16 - mixed.I;
    const master = join(CACHE, `${cut}-master.wav`);
    run('ffmpeg', ['-v', 'error', '-y', '-i', mix, '-af', `volume=${finalGain.toFixed(2)}dB,alimiter=limit=0.84:attack=3:release=60:level=false`, '-c:a', 'pcm_s16le', master]);
    // Measured levels: the voice alone (speech only) and the bed alone, both after their gains.
    const musicOnly = join(CACHE, `${cut}-music-only.wav`);
    run('ffmpeg', ['-v', 'error', '-y', '-f', 'f32le', '-ar', String(rate), '-ac', '2', '-i', musicRaw, '-af', `volume=${finalGain.toFixed(2)}dB`, musicOnly]);
    const report = { bed: { file: musicFile.split('/').pop(), median, musicEnd, tempo: Number(tempo.toFixed(4)), musicStart: Number(musicStart.toFixed(2)), delay: Number(delay.toFixed(2)), frozenLevel: Number(frozen.toFixed(1)) }, voiceSource: voice, voiceGain: Number(voiceGain.toFixed(2)), musicSource: musicLevel, under: UNDER, finalGain: Number(finalGain.toFixed(2)), master: loudness(master), musicInMaster: loudness(musicOnly) };
    // The bed's level while Kurt speaks and after he stops (short-term, from the shaped bed).
    const speechWindows = segments(timeline.words, 0.45).filter(([a, b]) => b - a > 2.4 && a > 4);
    const spread = speechWindows.filter((_, i) => i % Math.max(1, Math.floor(speechWindows.length / 6)) === 0).slice(0, 6);
    report.musicDuringSpeech = spread.map(([a, b]) => ({ at: Number(a.toFixed(1)), lufs: loudness(musicOnly, ['-ss', String(a + 0.4), '-t', String(Math.min(4, b - a - 0.5))]).I }));
    report.voiceMinusMusicDuringSpeech = Number((report.musicDuringSpeech.map((w) => -16 - w.lufs).sort((x, y) => x - y)[Math.floor(report.musicDuringSpeech.length / 2)]).toFixed(1));
    report.musicAfterVoice = loudness(musicOnly, ["-ss", String(timeline.voiceEnd + 1.5), "-t", "1.4"]).I;
    report.voiceSpeech = loudness(voiceRaw, ['-ss', '1', '-t', String(Math.min(30, timeline.voiceEnd - 2))]).I + voiceGain;
    report.tailMaxVolume = run('ffmpeg', ['-hide_banner', '-nostats', '-ss', String(duration - 0.12), '-i', master, '-af', 'volumedetect', '-f', 'null', '-']).stderr.match(/max_volume: (-?[0-9.inf]+) dB/)?.[1];
    [voiceRaw, musicRaw, mix, musicOnly].forEach((f) => unlinkSync(f));
    await ffmpeg(['-i', file, '-i', master, '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '160k', '-ar', String(rate), '-movflags', '+faststart', '-t', duration.toFixed(3), `${file}.tmp.mp4`]);
    renameSync(`${file}.tmp.mp4`, file);
    console.log(JSON.stringify(report, null, 1));
    writeFileSync(join(OUT, `${NAME}.audio.json`), JSON.stringify(report, null, 1) + '\n');
    return report;
}

// ---------------------------------------------------------------- captions
function vtt() {
    const time = (s) => {
        const ms = Math.max(0, Math.round(s * 1000));
        const p = (v, w = 2) => String(v).padStart(w, '0');
        return `${p(Math.floor(ms / 3600000))}:${p(Math.floor(ms / 60000) % 60)}:${p(Math.floor(ms / 1000) % 60)}.${p(ms % 1000, 3)}`;
    };
    const MAX = 42;
    const list = timeline.words;
    const cues = [];
    let current = [];
    const width = (ws) => ws.map((w) => w.text).join(' ').length;
    // Fits in two lines of ≤ 42 characters?
    const wrap = (ws) => {
        const text = ws.map((w) => w.text);
        if (text.join(' ').length <= MAX) return [text.join(' ')];
        let best = null;
        for (let k = 1; k < text.length; k++) {
            const a = text.slice(0, k).join(' ');
            const b = text.slice(k).join(' ');
            if (a.length > MAX || b.length > MAX) continue;
            const score = Math.abs(a.length - b.length) - (/[,.:;!?]$/.test(text[k - 1]) ? 12 : 0);
            if (!best || score < best.score) best = { score, lines: [a, b] };
        }
        return best?.lines ?? null;
    };
    const flush = () => { if (current.length) cues.push(current); current = []; };
    for (let i = 0; i < list.length; i++) {
        const w = list[i];
        const next = [...current, w];
        const gap = current.length ? w.start - current.at(-1).end : 0;
        if (current.length && (!wrap(next) || gap > 0.9 || w.paragraph !== current[0].paragraph)) flush();
        current.push(w);
        const sentenceEnd = /[.!?]$/.test(w.text) || /:$/.test(w.text);
        // A cue ends at a sentence end once it holds a readable amount, or at a clause end when it is long.
        if (sentenceEnd && width(current) >= 18) flush();
        else if (/,$/.test(w.text) && width(current) >= 52) flush();
    }
    flush();
    const body = cues.map((ws, k) => {
        const start = ws[0].start;
        const nextStart = cues[k + 1]?.[0].start ?? timeline.voiceEnd + 1.2;
        const end = Math.min(nextStart - 0.04, Math.max(ws.at(-1).end + 0.35, start + 1.1));
        return `${k + 1}\n${time(start)} --> ${time(end)}\n${wrap(ws).join('\n')}\n`;
    });
    const text = `WEBVTT - Usability Slogans That Actually Matter (${cut} cut), Kurt Dirnbauer, English\n\n${body.join('\n')}`;
    writeFileSync(join(OUT, `${NAME}.en.vtt`), text);
    const longest = Math.max(...text.split('\n').filter((l) => !/-->|^\d+$|^WEBVTT/.test(l)).map((l) => l.length));
    console.log(`${NAME}.en.vtt: ${cues.length} cues, longest line ${longest} chars`);
}

// ---------------------------------------------------------------- review material
function sheets(file) {
    const every = cut === 'long' ? 5 : 2;
    const columns = cut === 'long' ? 12 : 10;
    const dir = join(CACHE, `${cut}-sheet`);
    run('rm', ['-rf', dir]);
    mkdirSync(dir, { recursive: true });
    // One frame every `every` s (the first at every/2), labelled with its time; tiled by Pillow (this ffmpeg has no drawtext).
    // Seek per frame: the fps filter's start_time put the labels a second or more off.
    for (let i = 0, t = every / 2; t < timeline.duration; i++, t += every) {
        run('ffmpeg', ['-v', 'error', '-y', '-ss', t.toFixed(3), '-i', file, '-frames:v', '1', '-vf', 'scale=216:384', join(dir, `${String(i).padStart(4, '0')}.png`)]);
    }
    const script = `
import glob, sys
from PIL import Image, ImageDraw
files = sorted(glob.glob(sys.argv[1] + '/*.png')); every = float(sys.argv[2]); columns = int(sys.argv[3])
rows = (len(files) + columns - 1) // columns
sheet = Image.new('RGB', (columns * 220, rows * 388), (34, 34, 34))
for i, f in enumerate(files):
    im = Image.open(f).convert('RGB'); d = ImageDraw.Draw(im)
    t = every / 2 + i * every; label = '%d:%04.1f' % (t // 60, t % 60)
    d.rectangle([0, 0, 64, 20], fill=(0, 0, 0)); d.text((4, 4), label, fill=(255, 255, 255))
    sheet.paste(im, ((i % columns) * 220 + 2, (i // columns) * 388 + 2))
sheet.save(sys.argv[4])`;
    run(join(process.env.HOME, '.cache/talk-venv/bin/python'), ['-I', '-c', script, dir, String(every), String(columns), join(OUT, `${NAME}-sheet.png`)]);
    run('rm', ['-rf', dir]);
    console.log(join(OUT, `${NAME}-sheet.png`));
}

function frameFrom(file, t, png) {
    // The last frames: decode the final half second and keep the last frame (a seek that close to the end yields nothing).
    if (t > timeline.duration - 0.5) run('ffmpeg', ['-v', 'error', '-y', '-sseof', '-0.5', '-i', file, '-update', '1', png]);
    else run('ffmpeg', ['-v', 'error', '-y', '-ss', t.toFixed(3), '-i', file, '-frames:v', '1', png]);
    if (!existsSync(png)) throw new Error(`no frame at ${t} s of ${file}`);
}

function reviewStills(file) {
    const dir = join(OUT, 'stills');
    mkdirSync(dir, { recursive: true });
    const ev = (type, n) => timeline.events.find((e) => e.type === type && (n === undefined || e.n === n));
    const lowerEvent = ev('lower', 1) ?? ev('lower');
    const deckEvent = ev('deck');
    const picks = {
        source: (ev('source').start + ev('source').end) / 2,
        // The short cut has no separate lower thirds or cutaways: its slogan deck stands in for both.
        lower: deckEvent ? Math.min(deckEvent.slides[1].at - 0.1, deckEvent.slides[0].phrase.at + 0.6) : Math.min(lowerEvent.end - 0.4, lowerEvent.items.at(-1).at + 0.8),
        card: deckEvent ? deckEvent.slides[3].at + 0.7 : ev('card', 1).start + 1.2,
        intro: timeline.poster ?? 2.0,
        end: timeline.duration - 1 / FPS,
        endCrossfade: ev('end').start + ev('end').fade / 2,
    };
    for (const [label, t] of Object.entries(picks)) frameFrom(file, t, join(dir, `${NAME}-${label}.png`));
    console.log(`stills: ${Object.entries(picks).map(([k, v]) => `${k}@${v.toFixed(2)}`).join(', ')}`);
}

function poster(file) {
    const t = timeline.poster ?? 2.0;
    const png = join(CACHE, `${cut}-poster.png`);
    frameFrom(file, t, png);
    run('cwebp', ['-quiet', '-q', '84', '-m', '6', png, '-o', join(OUT, `${NAME}-poster.webp`)]);
    console.log(`poster @ ${t} s → ${NAME}-poster.webp`);
}

// ---------------------------------------------------------------- main
try {
    if (argument('check') !== undefined) {
        const ok = await check();
        if (!ok) process.exitCode = 1;
    }
    if (argument('stills')) {
        const stage = await openStage();
        const dir = join(OUT, 'stills');
        mkdirSync(dir, { recursive: true });
        for (const t of argument('stills').split(',').map(Number)) {
            const file = join(dir, `${cut}-${t.toFixed(2)}.png`);
            await still(stage, t, file);
            console.log(file);
        }
        await stage.page.close();
    }
    const final = join(OUT, `${NAME}.mp4`);
    if (argument('render') !== undefined) {
        await picture(final);
        await sound(final);
        vtt();
        poster(final);
        sheets(final);
        reviewStills(final);
        cardPositions(final);
        const size = statSync(final).size;
        console.log(`${final}: ${(size / 1024 / 1024).toFixed(2)} MB, ${probe(final, 'format=duration')} s, ${probe(final, 'stream=width,height,r_frame_rate,codec_name,profile').replace(/\n/g, ' | ')}`);
    } else {
        if (argument('picture') !== undefined) await picture(final);
        if (argument('audio') !== undefined) await sound(final);
        if (argument('captions') !== undefined) vtt();
        if (argument('review') !== undefined) { poster(final); sheets(final); reviewStills(final); }
        if (argument('cards') !== undefined) cardPositions(final);
    }
} finally {
    await browser.close();
    server.close();
}
