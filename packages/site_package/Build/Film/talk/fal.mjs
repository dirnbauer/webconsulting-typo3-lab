#!/usr/bin/env node
/**
 * fal.ai steps of the talk films: word timings and the music bed.
 *
 *   node fal.mjs --transcribe=short     # whisper word chunks of <var>/render/short-presenter.mp4 → <cache>/short.words.json
 *   node fal.mjs --music=long --length=300000 [--take=2]   # music bed take → <cache>/music-long-<take>.mp3
 *
 * FAL_KEY comes from the environment (the caller sources ~/.zshenv); it is
 * never printed. The voice is Kurt's own (the presenter render); fal only
 * listens to it. The music is described in words, never by an artist.
 */

import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { CACHE, RENDER, argument } from './paths.mjs';

const KEY = process.env.FAL_KEY;
if (!KEY) {
    console.error('FAL_KEY is not set (source ~/.zshenv first).');
    process.exit(1);
}

async function fal(endpoint, input) {
    // The queue API: long transcriptions and music takes outlive a plain fal.run request.
    const submit = await fetch(`https://queue.fal.run/${endpoint}`, {
        method: 'POST',
        headers: { Authorization: `Key ${KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify(input),
    });
    if (!submit.ok) throw new Error(`${endpoint}: ${submit.status} ${(await submit.text()).slice(0, 300)}`);
    const { status_url: statusUrl, response_url: responseUrl } = await submit.json();
    for (;;) {
        await new Promise((done) => setTimeout(done, 4000));
        const status = await (await fetch(statusUrl, { headers: { Authorization: `Key ${KEY}` } })).json();
        if (status.status === 'COMPLETED') break;
        if (status.status === 'FAILED' || status.error) throw new Error(`${endpoint}: ${JSON.stringify(status).slice(0, 300)}`);
    }
    const response = await fetch(responseUrl, { headers: { Authorization: `Key ${KEY}` } });
    if (!response.ok) throw new Error(`${endpoint} result: ${response.status} ${(await response.text()).slice(0, 300)}`);
    return response.json();
}

mkdirSync(CACHE, { recursive: true });

const cut = argument('transcribe');
if (cut) {
    // 16 kHz mono MP3 as a data URI: small enough to post inline.
    const mp3 = join(CACHE, `${cut}-voice16k.mp3`);
    execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', join(RENDER, `${cut}-presenter.mp4`), '-vn', '-ac', '1', '-ar', '16000', '-b:a', '48k', mp3]);
    const uri = `data:audio/mpeg;base64,${readFileSync(mp3).toString('base64')}`;
    const result = await fal('fal-ai/whisper', { audio_url: uri, task: 'transcribe', language: 'en', chunk_level: 'word', version: '3' });
    const words = (result.chunks ?? []).map((chunk) => ({ text: chunk.text.trim(), start: chunk.timestamp[0], end: chunk.timestamp[1] }));
    writeFileSync(join(CACHE, `${cut}.words.json`), JSON.stringify({ text: result.text, words }, null, 1) + '\n');
    console.log(`${cut}: ${words.length} words, ${words.at(-1)?.end ?? 0} s`);
}

const bed = argument('music');
if (bed) {
    const length = Number(argument('length') ?? 300000);
    const take = argument('take') ?? '1';
    const prompt = 'Calm, warm, minimal instrumental documentary underscore for a thoughtful talk. Soft felt piano with sparse, patient phrases, '
        + 'warm sustained analogue pads, a gentle low string drone, slow tempo around 68 bpm. Even, quiet dynamics that sit under a speaking voice, '
        + 'no drums, no percussion hits, no build-ups, no drops. Reflective, confident, human. Ends on a long, resolved, warm final chord. '
        + 'Instrumental only, no vocals.';
    const result = await fal('fal-ai/elevenlabs/music', { prompt, music_length_ms: length, force_instrumental: true, output_format: 'mp3_44100_192' });
    const file = join(CACHE, `music-${bed}-${take}.mp3`);
    const audio = await fetch(result.audio.url);
    writeFileSync(file, Buffer.from(await audio.arrayBuffer()));
    console.log(`${file} ${execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file]).toString().trim()} s`);
}
