#!/usr/bin/env node
/**
 * Narration and music for the overview films, through fal.ai (ElevenLabs).
 *
 *   FAL_KEY=… node generate-film-audio.mjs --film=docx-editor --voice   # lines whose text changed
 *   FAL_KEY=… node generate-film-audio.mjs --film=all --voice
 *   FAL_KEY=… node generate-film-audio.mjs --film=docx-editor --voice --line=4 --force
 *   FAL_KEY=… node generate-film-audio.mjs --music --bed=calm --takes=2  # takes → <cache>/music/takes/
 *   node generate-film-audio.mjs --pick=calm-2                           # the take that becomes <cache>/music/calm.mp3
 *   node generate-film-audio.mjs --film=all --check                      # what Whisper heard, where it differs
 *   node generate-film-audio.mjs --film=all --trim                       # where a take ends on a fragment, no API
 *
 * The lines come from the film specs (films/*.mjs) through lib/timeline.mjs:
 * `show` is the caption, `say` what the voice reads. Each line is a separate
 * take with word timestamps; Whisper transcribes it back (`heard`), so a
 * misread product name shows without listening. The takes stay in the film
 * cache (<cache>/audio/<id>/); audio/<id>/narration.json keeps the text,
 * lengths and word times, which film.html times the film by.
 *
 * The voice is an ElevenLabs library voice and imitates nobody; the music is
 * described by style, never by an artist.
 *
 * A take can end on a stray fragment: given the next line as context, the
 * voice starts that line's first sound ("s", "f") after a pause and the take
 * stops in the middle of it, which the film plays as a short hiss that
 * breaks off after the sentence. Every take is checked: when it ends that
 * way, narration.json gets `end`, where its speech ends, and the mix uses the
 * take up to there. `duration` stays the take's length, so the film's timing
 * and captions do not move.
 */

import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { CACHE, FILM, argument } from './lib/capture.mjs';
import { linesOf, timeline } from './lib/timeline.mjs';
import { films } from './films/index.mjs';

const KEY = process.env.FAL_KEY;
const VOICE = 'George';
/** Unhurried, close and calm: the voice of the templates and Desiderio films. */
const DELIVERY = { stability: 0.62, similarity_boost: 0.78, style: 0.18, speed: 0.9 };
const TAKES = join(CACHE, 'audio');
const MUSIC = join(CACHE, 'music');
const has = (name) => process.argv.includes(`--${name}`);

/** Shared beds: one per family of films, long enough for the longest of them. */
const BEDS = {
    calm: {
        prompt: 'Calm, modern, warm instrumental bed for a short product film about website tools. Soft felt piano, gentle plucked synth arpeggio, '
            + 'light brushed percussion entering after 8 seconds, warm sustained pads, steady unhurried pulse around 90 bpm. Confident and friendly, '
            + 'never busy, no big drops. A resolved, gentle ending chord. Instrumental only, no vocals.',
        length: 75000,
    },
    focus: {
        prompt: 'Thoughtful, precise, modern instrumental bed for a short film about AI tools that people stay in control of. Clean pulsing analogue synth, '
            + 'soft piano motif, subtle glitchy textures kept quiet, warm low strings from 10 seconds, steady pulse around 84 bpm. Trustworthy and calm, '
            + 'not dark, not dramatic. A resolved, gentle ending. Instrumental only, no vocals.',
        length: 75000,
    },
    bright: {
        prompt: 'Bright, light, design-led instrumental bed for a short film about website building blocks and colour themes. Marimba and plucked strings, '
            + 'soft claps and light percussion, airy synth pads, playful but refined, around 100 bpm. Lively forward momentum, never cheesy, no ukulele. '
            + 'A resolved, warm ending. Instrumental only, no vocals.',
        length: 75000,
    },
};

/** The DOCX film's score follows its acts; the plan is built from its timeline. */
function docxPlan() {
    const tl = timeline(films['docx-editor'], readNarration('docx-editor'));
    const span = (from, to) => Math.max(3000, Math.round(to - from));
    const scenes = tl.scenes;
    const at = (chapter) => scenes.find((scene) => scene.chapter === chapter)?.start;
    const marks = [0, scenes[2].start, at('Word files'), at('Teamwork'), at('Pages in Word'), at('Your word processor'), at('New pages'), scenes[scenes.length - 1].start, tl.duration];
    const sections = [
        ['The problem', ['intimate felt piano', 'soft sustained string pad', 'sparse', 'slightly unresolved'], ['drums', 'beat']],
        ['Title', ['gentle swell', 'warm low strings enter', 'shimmering synth', 'a sense of arrival'], ['drums']],
        ['Word files', ['light steady pulse', 'plucked strings', 'soft clicks', 'curious and precise'], ['heavy bass']],
        ['Teamwork', ['same pulse', 'a little tension', 'muted piano chords', 'then resolving'], ['dramatic hits']],
        ['Pages in Word', ['forward momentum', 'bright synth arpeggios', 'warm pads', 'confident'], ['aggressive']],
        ['Your word processor', ['playful', 'light percussion', 'piano melody returns', 'warm and human'], ['loud']],
        ['New pages', ['building', 'low strings and soft brass grow', 'rising towards a peak'], ['fade out']],
        ['Finale', ['bright hopeful peak at full warmth', 'then one long resolved final chord that rings to the very end'], ['fade out early', 'silence', 'abrupt stop']],
    ];
    return {
        positive_global_styles: ['cinematic', 'elegant modern score', 'felt piano', 'warm strings', 'soft analogue synth pads', 'refined', 'confident', 'instrumental'],
        negative_global_styles: ['vocals', 'choir', 'spoken word', 'dubstep', 'heavy drums', 'distorted guitar', 'cheesy corporate ukulele', 'abrupt ending'],
        sections: sections.map(([name, positive, negative], i) => ({
            section_name: name, duration_ms: span(marks[i], marks[i + 1]), positive_local_styles: positive, negative_local_styles: negative, lines: [],
        })),
    };
}

function requireKey() {
    if (!KEY) {
        console.error('FAL_KEY is not set.');
        process.exit(1);
    }
}

async function fal(endpoint, input) {
    const response = await fetch(`https://fal.run/${endpoint}`, {
        method: 'POST',
        headers: { Authorization: `Key ${KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify(input),
    });
    if (!response.ok) throw new Error(`${endpoint}: ${response.status} ${(await response.text()).slice(0, 300)}`);
    return response.json();
}

async function download(url, file) {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`download ${url}: ${response.status}`);
    writeFileSync(file, Buffer.from(await response.arrayBuffer()));
}

const seconds = (file) => Number(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file]).toString().trim());

/**
 * Where a take's speech ends when the take ends on a stray fragment, in ms,
 * or null. The take is read as 10 ms windows; sound is anything above
 * -60 dBFS, and pauses shorter than 120 ms belong to the word. A fragment is
 * a last stretch of sound shorter than 250 ms after a pause of 150 ms or
 * more: the speech ends 80 ms after the stretch before it. (The same check
 * as Desiderio's Build/Scripts/generate-film-audio.mjs.)
 */
function speechEnd(file) {
    const raw = execFileSync('ffmpeg', ['-v', 'error', '-i', file, '-f', 'f32le', '-ac', '1', '-ar', '44100', '-'], { maxBuffer: 1 << 28 });
    const samples = new Float32Array(raw.buffer, raw.byteOffset, raw.byteLength / 4);
    const step = 441;
    const sounding = [];
    for (let i = 0; i + step <= samples.length; i += step) {
        let sum = 0;
        for (let k = i; k < i + step; k++) sum += samples[k] * samples[k];
        sounding.push(10 * Math.log10(sum / step + 1e-18) > -60);
    }
    const stretches = [];
    sounding.forEach((on, i) => {
        const last = stretches[stretches.length - 1];
        if (!on) return;
        if (last && i - last[1] < 12) last[1] = i + 1;
        else stretches.push([i, i + 1]);
    });
    if (stretches.length < 2) return null;
    const [before, tail] = stretches.slice(-2);
    return tail[1] - tail[0] < 25 && tail[0] - before[1] >= 15 ? (before[1] + 8) * 10 : null;
}

/** Records `end` for every take of a film that ends on a fragment; touches nothing else. */
function trim(id) {
    const narration = readNarration(id);
    if (!narration) return console.log(`${id}: no narration`);
    for (const line of narration.lines) {
        delete line.end;
        if (!line.file || !line.duration || !existsSync(join(TAKES, id, line.file))) continue;
        const end = speechEnd(join(TAKES, id, line.file));
        if (end) {
            line.end = end;
            console.log(`${id} ${line.file}: speech to ${end} of ${line.duration} ms, then a fragment`);
        }
    }
    writeFileSync(narrationFile(id), JSON.stringify(narration, null, 2) + '\n');
}

/** ElevenLabs character alignment → [{word, start, end}] in ms from the line's start. */
function words(timestamps) {
    const out = [];
    for (const chunk of Array.isArray(timestamps) ? timestamps : [timestamps].filter(Boolean)) {
        const characters = chunk.characters ?? [];
        let current = null;
        characters.forEach((character, i) => {
            const start = Math.round((chunk.character_start_times_seconds?.[i] ?? 0) * 1000);
            const end = Math.round((chunk.character_end_times_seconds?.[i] ?? 0) * 1000);
            if (/\s/.test(character)) {
                if (current) out.push(current);
                current = null;
                return;
            }
            if (!current) current = { word: '', start, end };
            current.word += character;
            current.end = end;
        });
        if (current) out.push(current);
    }
    return out;
}

const narrationFile = (id) => join(FILM, 'audio', id, 'narration.json');
function readNarration(id) {
    return existsSync(narrationFile(id)) ? JSON.parse(readFileSync(narrationFile(id), 'utf8')) : null;
}

async function voice(id) {
    const spec = films[id];
    const lines = linesOf(spec);
    const before = readNarration(id)?.lines ?? [];
    const only = argument('line') ? Number(argument('line')) : null;
    const dir = join(TAKES, id);
    mkdirSync(dir, { recursive: true });
    mkdirSync(join(FILM, 'audio', id), { recursive: true });
    const out = [];
    let made = 0;
    for (const [index, line] of lines.entries()) {
        const file = `voice-${String(index + 1).padStart(2, '0')}.mp3`;
        const kept = before.find((take) => take.say === line.say && existsSync(join(dir, take.file)));
        const wanted = only ? only === index + 1 : true;
        if (kept && !(has('force') && wanted)) {
            if (kept.file !== file) copyFileSync(join(dir, kept.file), join(dir, file));
            out.push({ ...kept, show: line.show, file });
            continue;
        }
        if (!wanted) {
            out.push({ say: null, show: line.show, file, duration: 0, words: [], heard: '(not recorded)' });
            continue;
        }
        const result = await fal('fal-ai/elevenlabs/tts/multilingual-v2', {
            text: line.say,
            voice: VOICE,
            ...DELIVERY,
            language_code: 'en',
            timestamps: true,
            previous_text: lines[index - 1]?.say ?? null,
            next_text: lines[index + 1]?.say ?? null,
        });
        await download(result.audio.url, join(dir, file));
        const duration = Math.round(seconds(join(dir, file)) * 1000);
        const end = speechEnd(join(dir, file));
        let heard = '';
        try {
            heard = (await fal('fal-ai/whisper', { audio_url: result.audio.url, task: 'transcribe', language: 'en', chunk_level: 'none' })).text?.trim() ?? '';
        } catch (error) {
            heard = `(no transcript: ${error.message.slice(0, 80)})`;
        }
        out.push({ say: line.say, show: line.show, file, duration, ...(end ? { end } : {}), words: words(result.timestamps), heard });
        made++;
        console.log(`${id} ${file} ${duration} ms  ${line.say}\n${' '.repeat(id.length)}   heard: ${heard}`);
    }
    writeFileSync(narrationFile(id), JSON.stringify({ voice: VOICE, delivery: DELIVERY, lines: out }, null, 2) + '\n');
    const tl = timeline(spec, { lines: out });
    console.log(`${id}: ${made} new takes, ${lines.length} lines, film ${(tl.duration / 1000).toFixed(1)} s`);
}

/** Words Whisper heard differently from the script (case and punctuation aside). */
function check(id) {
    const narration = readNarration(id);
    if (!narration) return console.log(`${id}: no narration`);
    const plain = (text) => text.toLowerCase().replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim();
    for (const [i, line] of narration.lines.entries()) {
        if (plain(line.heard) !== plain(line.say)) console.log(`${id} ${i + 1}: said  ${line.say}\n${' '.repeat(id.length)}    heard ${line.heard}`);
    }
}

mkdirSync(join(MUSIC, 'takes'), { recursive: true });
const which = argument('film');
const ids = which === 'all' ? Object.keys(films) : which ? which.split(',') : [];

if (has('voice')) {
    requireKey();
    for (const id of ids) await voice(id);
}
if (has('check')) for (const id of ids) check(id);
if (has('trim')) for (const id of ids) trim(id);

if (has('music')) {
    requireKey();
    const bed = argument('bed');
    const takes = Number(argument('takes') ?? 2);
    for (let n = 1; n <= takes; n++) {
        const input = bed === 'docx'
            ? { composition_plan: docxPlan(), respect_sections_durations: true, output_format: 'mp3_44100_192' }
            : { prompt: BEDS[bed].prompt, music_length_ms: BEDS[bed].length, force_instrumental: true, output_format: 'mp3_44100_192' };
        const result = await fal('fal-ai/elevenlabs/music', input);
        const file = join(MUSIC, 'takes', `${bed}-${n}.mp3`);
        await download(result.audio.url, file);
        console.log(`${file}  ${Math.round(seconds(file) * 1000)} ms`);
    }
}

const pick = argument('pick');
if (pick) {
    const source = join(MUSIC, 'takes', `${pick}.mp3`);
    if (!existsSync(source)) throw new Error(`No take ${pick}`);
    copyFileSync(source, join(MUSIC, `${pick.replace(/-\d+$/, '')}.mp3`));
    console.log(`music/${pick.replace(/-\d+$/, '')}.mp3 ← ${pick}`);
}
