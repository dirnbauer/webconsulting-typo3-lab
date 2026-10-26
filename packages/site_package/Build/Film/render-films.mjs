#!/usr/bin/env node
/**
 * Renders the overview films (film.html?film=<id>) to video.
 *
 *   node render-films.mjs --film=docx-editor                 # MP4, WebP poster, WebVTT, film.json
 *   node render-films.mjs --film=all                         # every film in films/index.mjs
 *   node render-films.mjs --film=docx-editor --stills=1500,9000   # PNG stills into <cache>/stills/
 *   node render-films.mjs --film=docx-editor --range=0,8000  # a quick test render into <cache>/out/
 *   node render-films.mjs --film=docx-editor --remix         # new soundtrack on the current picture
 *   node render-films.mjs --list                             # ids, pages and lengths
 *
 * Every motion in film.html is a paused animation or a function of the time,
 * so a frame is rendered by seeking, never by waiting: the output is the same
 * on every run. Frames go straight into ffmpeg through a pipe.
 *
 * Deliverables land in Resources/Private/Fixtures/Films/<id>/ with the film's
 * hash in their names (the seeder imports by file name and never re-imports a
 * name it has), next to film.json, which names them for the seeder.
 *
 * The soundtrack: each narration line (<cache>/audio/<id>/, from
 * generate-film-audio.mjs) at the time film.html gives it, the music bed
 * (<cache>/music/<bed>.mp3) 15 LU under the voice and 10 % louder in the
 * pauses, faded in and out, then one measured gain to -16 LUFS and a limiter.
 * The start card is silent: the music starts with the scenes and fades out
 * where they end, while the closing card holds (lib/timeline.mjs LEAD, TAIL).
 */

import { spawn, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { createReadStream, existsSync, mkdirSync, readFileSync, readdirSync, renameSync, statSync, unlinkSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, normalize } from 'node:path';
import { CACHE, FILM, LAB, argument, chromium } from './lib/capture.mjs';
import { films } from './films/index.mjs';
import { header } from './lib/timeline.mjs';

const OUT = join(CACHE, 'out');
const STILLS = join(CACHE, 'stills');
const VOICE = join(CACHE, 'audio');
const MUSIC = join(CACHE, 'music');
const TARGET = join(FILM, '../../Resources/Private/Fixtures/Films');
const MUSIC_DB = 15;
const BREAK_LIFT = 1.1;
const FPS = 30;
const SIZE = { width: 1920, height: 1080 };
/** x264 veryslow; mostly stills and slow camera moves, so 30 keeps UI text clean at a small size. */
const CRF = Number(argument('crf') ?? 30);

const TYPES = { '.html': 'text/html', '.mjs': 'text/javascript', '.js': 'text/javascript', '.json': 'application/json', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.woff2': 'font/woff2', '.css': 'text/css' };
// The lab at /, the film cache at /cache/.
const server = createServer((request, response) => {
    const path = normalize(decodeURIComponent(new URL(request.url, 'http://localhost').pathname));
    const [base, rest] = path.startsWith('/cache/') ? [CACHE, path.slice('/cache'.length)] : [LAB, path];
    const file = join(base, rest);
    if (!file.startsWith(base) || !existsSync(file) || statSync(file).isDirectory()) {
        response.writeHead(404).end();
        return;
    }
    response.writeHead(200, { 'Content-Type': TYPES[extname(file)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
    createReadStream(file).pipe(response);
});
await new Promise((done) => server.listen(Number(process.env.FILM_PORT ?? 0), '127.0.0.1', done));
const base = `http://127.0.0.1:${server.address().port}`;
const filmUrl = (id) => `${base}/packages/site_package/Build/Film/film.html?film=${id}${argument('query') ? `&${argument('query')}` : ''}`;

function lufs(file) {
    const report = spawnSync('ffmpeg', ['-hide_banner', '-nostats', '-i', file, '-af', 'ebur128=framelog=quiet', '-f', 'null', '-'], { encoding: 'utf8' }).stderr;
    const match = [...report.matchAll(/I:\s+(-?[0-9.]+) LUFS/g)].pop();
    if (!match) throw new Error(`No loudness for ${file}`);
    return Number(match[1]);
}

function ffmpeg(args, input) {
    return new Promise((done, fail) => {
        const child = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args], { stdio: [input ? 'pipe' : 'ignore', 'inherit', 'inherit'] });
        child.on('error', fail);
        child.on('close', (code) => (code === 0 ? done() : fail(new Error(`ffmpeg exited with ${code}`))));
        if (input) input(child.stdin);
    });
}

const vttTime = (ms) => {
    const h = Math.floor(ms / 3600000);
    const m = Math.floor((ms % 3600000) / 60000);
    const s = Math.floor((ms % 60000) / 1000);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}.${String(Math.round(ms) % 1000).padStart(3, '0')}`;
};

/** m:ss, as the transcript prints it. */
const clock = (ms) => `${Math.floor(ms / 60000)}:${String(Math.floor(ms / 1000) % 60).padStart(2, '0')}`;

function captions(spec, lines, duration) {
    const cues = lines.map((line, i) => {
        const start = Math.max(0, line.at - 60);
        const end = Math.min(duration, line.at + line.duration + 240);
        return `${i + 1}\n${vttTime(start)} --> ${vttTime(end)}\n${line.show}\n`;
    });
    return `WEBVTT - ${spec.name} (narration, English)\n\n${cues.join('\n')}`;
}

/** The soundtrack under the picture, when the voice lines are there. */
async function score(id, spec, mp4, timeline) {
    const { lines, duration, lead = 0, content = duration } = timeline;
    const end = duration / 1000;
    // The music runs with the scenes: from the end of the start card to the
    // end of the last scene, as if the cards were not there.
    const from = lead / 1000;
    const until = (lead + content) / 1000;
    const missing = lines.filter((line) => !line.measured || !existsSync(join(VOICE, id, line.file)));
    if (missing.length) {
        console.warn(`${id}: ${missing.length} narration lines missing, rendering without sound`);
        return false;
    }
    const music = join(MUSIC, `${spec.music ?? 'calm'}.mp3`);
    const voiceLufs = lines.reduce((sum, line) => sum + lufs(join(VOICE, id, line.file)), 0) / lines.length;
    const inputs = lines.flatMap((line) => ['-i', join(VOICE, id, line.file)]);
    // A take that ends on a stray fragment (narration.json `end`) is used up
    // to the end of its speech, with a 30 ms fade.
    const cut = (line, cuts) => (cuts && line.end ? `atrim=0:${line.end / 1000},afade=t=out:st=${(line.end - 30) / 1000}:d=0.03,` : '');
    const musicGain = existsSync(music) ? 10 ** ((voiceLufs - MUSIC_DB - lufs(music)) / 20) : 0;
    if (existsSync(music)) inputs.push('-i', music);
    else console.warn(`${id}: no music bed ${music}, voice only`);
    const graph = (cuts) => {
        const voices = lines.map((line, i) => `[${i}:a]aformat=sample_rates=44100:channel_layouts=stereo,${cut(line, cuts)}adelay=${line.at}|${line.at}[v${i}]`);
        const steps = [...voices, `${lines.map((_, i) => `[v${i}]`).join('')}amix=inputs=${lines.length}:normalize=0,apad[voice]`];
        if (!existsSync(music)) {
            steps[steps.length - 1] = steps[steps.length - 1].replace('[voice]', ',atrim=0:' + end + '[mix]');
            return steps.join(';');
        }
        const speaking = lines.map((line) => `between(t,${(line.at - 100) / 1000},${(line.at + line.duration + 150) / 1000})`).join('+');
        const volume = `${(musicGain * BREAK_LIFT).toFixed(5)}-${(musicGain * (BREAK_LIFT - 1)).toFixed(5)}*(${speaking})`;
        steps.push(`[${lines.length}:a]aformat=sample_rates=44100:channel_layouts=stereo,atrim=0:${until - from},adelay=${lead}|${lead},volume='${volume}':eval=frame,afade=t=in:st=${from}:d=1.2,afade=t=out:st=${Math.max(from, until - 2.4)}:d=2.4,apad=whole_dur=${end}[music]`);
        steps.push(`[music][voice]amix=inputs=2:normalize=0:duration=first,atrim=0:${end}[mix]`);
        return steps.join(';');
    };
    const raw = join(OUT, `${id}-soundtrack.wav`);
    // The level is measured on the takes as recorded: cutting a fragment off
    // never moves the gain of the whole film by a rounding step.
    await ffmpeg([...inputs, '-filter_complex', graph(false), '-map', '[mix]', '-ar', '44100', raw]);
    const gain = (-16 - lufs(raw)).toFixed(2);
    if (lines.some((line) => line.end)) await ffmpeg([...inputs, '-filter_complex', graph(true), '-map', '[mix]', '-ar', '44100', raw]);
    const scored = join(OUT, `${id}-scored.mp4`);
    await ffmpeg(['-i', mp4, '-i', raw, '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-af', `volume=${gain}dB,alimiter=limit=0.84:level=false`, '-c:a', 'aac', '-b:a', '112k', '-ar', '44100', '-movflags', '+faststart', '-t', String(end), scored]);
    unlinkSync(raw);
    renameSync(scored, mp4);
    console.log(`${id}: ${lines.length} lines + ${existsSync(music) ? spec.music : 'no music'}, ${gain} dB to -16 LUFS`);
    return true;
}

async function finish(id, spec, mp4, posterPng, timeline) {
    const sound = await score(id, spec, mp4, timeline);
    const target = join(TARGET, id);
    mkdirSync(target, { recursive: true });
    const hash = createHash('sha256').update(readFileSync(mp4)).digest('hex').slice(0, 8);
    for (const old of readdirSync(target).filter((name) => name.startsWith(`${id}-`))) unlinkSync(join(target, old));
    const names = { mp4: `${id}-${hash}.mp4`, poster: `${id}-poster-${hash}.webp`, vtt: `${id}-${hash}.en.vtt` };
    renameSync(mp4, join(target, names.mp4));
    const webp = spawnSync('cwebp', ['-quiet', '-q', '82', '-m', '6', '-resize', '1600', '0', posterPng, '-o', join(target, names.poster)]);
    if (webp.status !== 0) throw new Error(`cwebp failed: ${webp.stderr}`);
    writeFileSync(join(target, names.vtt), captions(spec, timeline.lines, timeline.duration));
    const size = (name) => statSync(join(target, name)).size;
    const seconds = Math.round(timeline.duration / 1000);
    const sub = spec.scenes.find((scene) => scene.type === 'title')?.sub ?? '';
    const manifest = {
        id,
        page: spec.page,
        slug: spec.slug,
        title: spec.title ?? spec.name,
        header: header(spec, timeline.duration),
        description: spec.video?.description
            ?? `<p>${sub.replace(/[.\s]*$/, '.')} A narrated tour, recorded in this TYPO3 v14 lab, with English captions.</p>`,
        mp4: names.mp4,
        poster: names.poster,
        vtt: names.vtt,
        duration: Math.round(timeline.duration) / 1000,
        width: SIZE.width,
        height: SIZE.height,
        fps: FPS,
        sound,
        alt: spec.alt.replace('{duration}', String(seconds)),
        transcript: timeline.lines.map((line) => ({ at: clock(line.at), text: line.show })),
        captions: { language: 'en', label: 'English', burnedIn: true },
        bytes: { mp4: size(names.mp4), poster: size(names.poster), vtt: size(names.vtt) },
        generator: 'packages/site_package/Build/Film/render-films.mjs',
    };
    writeFileSync(join(target, 'film.json'), JSON.stringify(manifest, null, 2) + '\n');
    console.log(`${id}: ${names.mp4} ${(size(names.mp4) / 1024 / 1024).toFixed(2)} MB, ${manifest.duration} s`);
}

async function open(browser, id) {
    const page = await browser.newPage({ viewport: SIZE, deviceScaleFactor: 1 });
    page.on('pageerror', (error) => console.error(`film.html (${id}): ${error.message}`));
    page.on('console', (message) => { if (message.type() === 'error') console.error(`film.html (${id}): ${message.text()}`); });
    await page.goto(filmUrl(id), { waitUntil: 'load' });
    const duration = await page.evaluate(() => window.ready);
    return { page, duration };
}

async function frames(pages, from, to, file, crf = 8, preset = 'veryfast') {
    const total = Math.round(((to - from) / 1000) * FPS);
    const per = Math.ceil(total / pages.length);
    const parts = [];
    const started = Date.now();
    await Promise.all(pages.map((worker, k) => {
        const first = k * per;
        const last = Math.min(total, first + per);
        if (first >= last) return null;
        const part = join(OUT, `part-${k}.mp4`);
        parts[k] = part;
        // JPEG at 95: several times faster to grab than PNG, and invisible after the CRF 30 web encode.
        return ffmpeg(['-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-', '-c:v', 'libx264', '-preset', preset, '-crf', String(crf), '-pix_fmt', 'yuv420p', part], async (stdin) => {
            for (let frame = first; frame < last; frame++) {
                await worker.evaluate((time) => window.seek(time), from + (frame * 1000) / FPS);
                const image = await worker.screenshot({ type: 'jpeg', quality: 95 });
                if (!stdin.write(image)) await new Promise((done) => stdin.once('drain', done));
            }
            stdin.end();
        });
    }));
    const list = join(OUT, 'parts.txt');
    writeFileSync(list, parts.filter(Boolean).map((part) => `file '${part}'\n`).join(''));
    await ffmpeg(['-f', 'concat', '-safe', '0', '-i', list, '-c', 'copy', file]);
    parts.filter(Boolean).forEach((part) => unlinkSync(part));
    unlinkSync(list);
    console.log(`${total} frames in ${Math.round((Date.now() - started) / 1000)} s`);
}

mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch();
const which = argument('film') ?? 'docx-editor';
const ids = which === 'all' ? Object.keys(films) : which.split(',');
const workers = Number(argument('workers') ?? 4);

try {
    if (process.argv.includes('--list')) {
        for (const id of Object.keys(films)) {
            const { page } = await open(browser, id);
            const timeline = await page.evaluate(() => window.TIMELINE);
            console.log(`${id.padEnd(28)} page ${String(films[id].page).padEnd(6)} ${(timeline.duration / 1000).toFixed(1)} s, ${timeline.lines.length} lines, ${timeline.lines.filter((l) => l.measured).length} recorded`);
            await page.close();
        }
    }
    for (const id of process.argv.includes('--list') ? [] : ids) {
        const spec = films[id];
        if (!spec) throw new Error(`No film ${id}`);
        const { page, duration } = await open(browser, id);
        const timeline = await page.evaluate(() => window.TIMELINE);
        const stills = argument('stills');
        const range = argument('range')?.split(',').map(Number);
        if (stills) {
            mkdirSync(STILLS, { recursive: true });
            const times = stills === 'scenes' ? timeline.scenes.map((scene) => Math.round(scene.start + (scene.end - scene.start) * 0.62)) : stills.split(',').map(Number);
            for (const t of times) {
                await page.evaluate((time) => window.seek(time), t);
                const file = join(STILLS, `${id}-${t}.png`);
                await page.screenshot({ path: file });
                console.log(file);
            }
        } else if (range) {
            const pages = [page, ...(await Promise.all(Array.from({ length: workers - 1 }, () => open(browser, id).then((o) => o.page))))];
            const file = join(OUT, `${id}-range.mp4`);
            await frames(pages, range[0], Math.min(range[1], duration), file, 23, 'veryfast');
            await Promise.all(pages.slice(1).map((p) => p.close()));
            console.log(file);
        } else if (process.argv.includes('--remix')) {
            const target = join(TARGET, id);
            const current = readdirSync(target).find((name) => new RegExp(`^${id}-[0-9a-f]{8}\\.mp4$`).test(name));
            if (!current) throw new Error(`No rendered ${id} to remix.`);
            const mp4 = join(OUT, `${id}.mp4`);
            await ffmpeg(['-i', join(target, current), '-map', '0:v', '-c:v', 'copy', '-an', mp4]);
            const poster = join(OUT, `${id}-poster.png`);
            await page.evaluate((time) => window.seek(time), await page.evaluate(() => window.POSTER_AT));
            await page.screenshot({ path: poster });
            await finish(id, spec, mp4, poster, timeline);
        } else {
            console.log(`${id}: ${(duration / 1000).toFixed(1)} s, ${timeline.lines.length} lines`);
            const pages = [page, ...(await Promise.all(Array.from({ length: workers - 1 }, () => open(browser, id).then((o) => o.page))))];
            const master = join(OUT, `${id}-master.mp4`);
            await frames(pages, 0, duration, master);
            await Promise.all(pages.slice(1).map((p) => p.close()));
            const mp4 = join(OUT, `${id}.mp4`);
            await ffmpeg(['-i', master, '-c:v', 'libx264', '-preset', 'veryslow', '-crf', String(CRF), '-profile:v', 'high', '-level', '4.1', '-refs', '4', '-bf', '5', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an', mp4]);
            const poster = join(OUT, `${id}-poster.png`);
            await page.evaluate((time) => window.seek(time), await page.evaluate(() => window.POSTER_AT));
            await page.screenshot({ path: poster });
            await finish(id, spec, mp4, poster, timeline);
        }
        await page.close();
    }
} finally {
    await browser.close();
    server.close();
}
