/**
 * The timeline of a talk film: the script's words with the times whisper
 * heard them at, and the cut's overlay plan resolved against those words.
 *
 * The script (<render>/<cut>.txt) is what HeyGen spoke, so it is the text of
 * record (casing, punctuation, "One:" where whisper writes "1."); whisper
 * (<cache>/<cut>.words.json) only supplies the times. The two are aligned
 * by a longest-common-subsequence over normalised tokens; script words
 * whisper missed get times interpolated between their aligned neighbours.
 *
 * A cut spec (cuts/<cut>.mjs) names moments by phrases of the script:
 * { say: 'opinions are not evidence' } is the start of the first match after
 * the previous anchor; `edge: 'end'` its end; `offset` seconds added.
 */

import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { CACHE, RENDER } from './paths.mjs';

const NUMBERS = { 1: 'one', 2: 'two', 3: 'three', 4: 'four', 5: 'five', 6: 'six', 7: 'seven', 8: 'eight', 9: 'nine', 10: 'ten' };
export const norm = (token) => {
    const bare = token.toLowerCase().replace(/[’']/g, '').replace(/[^a-z0-9]/g, '');
    return NUMBERS[bare] ?? bare;
};

/** Script tokens with start/end seconds. */
export function words(cut) {
    const script = readFileSync(join(RENDER, `${cut}.txt`), 'utf8');
    const heard = JSON.parse(readFileSync(join(CACHE, `${cut}.words.json`), 'utf8')).words.filter((w) => norm(w.text));
    const said = [];
    script.split('\n').forEach((line, paragraph) => {
        line.split(/\s+/).filter(Boolean).forEach((text) => said.push({ text, key: norm(text), paragraph }));
    });
    const a = said.map((w) => w.key);
    const b = heard.map((w) => norm(w.text));
    // LCS table (≈ 700 × 700 for the long cut: fine).
    const n = a.length;
    const m = b.length;
    const table = Array.from({ length: n + 1 }, () => new Uint16Array(m + 1));
    for (let i = n - 1; i >= 0; i--) {
        for (let j = m - 1; j >= 0; j--) table[i][j] = a[i] === b[j] ? table[i + 1][j + 1] + 1 : Math.max(table[i + 1][j], table[i][j + 1]);
    }
    for (let i = 0, j = 0; i < n && j < m;) {
        if (a[i] === b[j]) {
            said[i].start = heard[j].start;
            said[i].end = heard[j].end;
            i++;
            j++;
        } else if (table[i + 1][j] >= table[i][j + 1]) i++;
        else j++;
    }
    const matched = said.filter((w) => w.start !== undefined).length;
    // Unmatched script words: spread between the neighbours that matched.
    for (let i = 0; i < n; i++) {
        if (said[i].start !== undefined) continue;
        let k = i;
        while (k < n && said[k].start === undefined) k++;
        const from = i > 0 ? said[i - 1].end : 0;
        const to = k < n ? said[k].start : heard.at(-1).end;
        const span = (to - from) / (k - i);
        for (let x = i; x < k; x++) {
            said[x].start = from + span * (x - i);
            said[x].end = from + span * (x - i + 1);
            said[x].guessed = true;
        }
    }
    return { words: said, matched, total: n, heardTotal: m };
}

/** Speech segments (words closer than `gap` s merged), for the music ducking. */
export function segments(list, gap = 0.45) {
    const out = [];
    for (const w of list) {
        const last = out.at(-1);
        if (last && w.start - last[1] <= gap) last[1] = Math.max(last[1], w.end);
        else out.push([w.start, w.end]);
    }
    return out;
}

/** Resolves the anchors of a cut spec to seconds. */
export function resolve(spec, list) {
    let cursor = 0;
    const find = (anchor, advance = true) => {
        if (typeof anchor === 'number') return anchor;
        const keys = anchor.say.split(/\s+/).map(norm).filter(Boolean);
        const from = anchor.anywhere ? 0 : cursor;
        for (let i = from; i <= list.length - keys.length; i++) {
            if (keys.every((key, k) => list[i + k].key === key)) {
                if (!anchor.anywhere && advance) cursor = i;
                const word = anchor.edge === 'end' ? list[i + keys.length - 1].end : list[i].start;
                return Math.round((word + (anchor.offset ?? 0)) * 1000) / 1000;
            }
        }
        throw new Error(`${spec.id}: phrase "${anchor.say}" not found after word ${cursor}`);
    };
    const events = spec.events.map((event) => {
        const resolved = { ...event, start: find(event.start) };
        if (event.items) resolved.items = event.items.map((item) => ({ ...item, at: find(item.at) }));
        // The short cut's slogan deck: each slide, its key phrase and its steps, in speaking order.
        if (event.slides) {
            resolved.slides = event.slides.map((slide) => {
                const at = find(slide.at);
                if (!slide.phrase) return { ...slide, at };
                const phrase = { ...slide.phrase };
                if (phrase.steps) {
                    phrase.steps = phrase.steps.map((step) => ({ ...step, at: find(step.at) }));
                    phrase.at = phrase.steps[0].at;
                } else phrase.at = find(phrase.at);
                return { ...slide, at, phrase };
            });
        }
        if (event.end !== undefined) resolved.end = find(event.end, false);
        else if (event.dur !== undefined) resolved.end = resolved.start + event.dur;
        return resolved;
    });
    // A panel that would start under a card's cover starts as the cover lifts.
    events.forEach((event, i) => {
        if (!['lower', 'steps', 'closing'].includes(event.type)) return;
        const cover = events.slice(0, i).reverse().find((other) => other.type === 'card');
        if (cover && cover.end > event.start - 0.05 && cover.start < event.start) event.start = Math.round((cover.end - 0.12) * 1000) / 1000;
    });
    events.forEach((event) => { event.end = Math.round(event.end * 1000) / 1000; });
    const voiceEnd = list.at(-1).end;
    const closing = events.find((event) => event.type === 'end');
    const duration = closing.start + closing.fade + closing.hold;
    closing.end = duration;
    return { id: spec.id, events, voiceEnd, duration };
}
