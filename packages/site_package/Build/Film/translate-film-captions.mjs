#!/usr/bin/env node
/**
 * Build selectable captions and timestamped transcript translations from the
 * site's translation memories, after render-films.mjs has produced the film.
 * node translate-film-captions.mjs --film=visual-editor --languages=de,zh,hu
 * Fails before writing when a narration line has no translation.
 */
import {readFileSync, writeFileSync, statSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const arg = name => process.argv.find(value => value.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const id = arg('film');
const languages = (arg('languages') ?? 'de,zh,hu').split(',');
if (!id || !/^[a-z0-9-]+$/.test(id) || languages.some(language => !/^[a-z]{2}$/.test(language) || language === 'en')) {
    throw new Error('Provide a film id and two-letter target languages, for example --film=visual-editor --languages=de,zh,hu.');
}
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const target = resolve(root, 'Resources/Private/Fixtures/Films', id);
const memoryRoot = resolve(root, 'Resources/Private/Data/Translations/desiderio');
const manifest = JSON.parse(readFileSync(resolve(target, 'film.json'), 'utf8'));
if (manifest.captions.burnedIn) throw new Error('Selectable translations require a render without burned-in captions.');
if (!manifest.vtt.endsWith('.en.vtt')) throw new Error('English captions must have the .en.vtt suffix.');
const english = readFileSync(resolve(target, manifest.vtt), 'utf8');
const blocks = english.trim().split(/\r?\n\r?\n/).slice(1).map(block => block.split(/\r?\n/));
if (blocks.length !== manifest.transcript.length) throw new Error('Caption cues and transcript do not match.');
for (const [index, block] of blocks.entries()) {
    if (block.slice(2).join(' ') !== manifest.transcript[index].text) throw new Error(`Caption ${index + 1} differs from its transcript.`);
}
const escape = text => text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
const transcript = texts => manifest.transcript.map((line, index) => `<p><strong>${escape(line.at)}</strong> ${escape(texts[index])}</p>`).join('');
const sourceTexts = manifest.transcript.map(line => line.text);
const sourceTranscript = transcript(sourceTexts);
const output = [];
for (const language of [...new Set(languages)]) {
    const path = resolve(memoryRoot, `${language}.json`);
    const raw = readFileSync(path, 'utf8');
    const memory = JSON.parse(raw);
    const texts = sourceTexts.map(source => {
        const translated = memory.strings[source];
        if (typeof translated !== 'string' || translated.trim() === '') throw new Error(`${language}: missing translation for ${source}`);
        return translated;
    });
    const translatedTranscript = transcript(texts);
    if (memory.strings[sourceTranscript] && memory.strings[sourceTranscript] !== translatedTranscript) {
        throw new Error(`${language}: transcript already has a different translation; reconcile the memory first.`);
    }
    const ending = raw.match(/\n[ \t]*}\s*\n}\s*$/);
    if (!ending) throw new Error(`${path}: unexpected memory layout.`);
    const updated = memory.strings[sourceTranscript] ? raw : raw.slice(0, ending.index)
        + `,\n  ${JSON.stringify(sourceTranscript)}: ${JSON.stringify(translatedTranscript)}` + ending[0];
    output.push({language, path, memory: updated, texts});
}
// Keep line breaks readable without changing cue timing or narration speed.
function wrap(text, language) {
    const width = language === 'zh' ? 24 : 48;
    const units = language === 'zh' ? Array.from(text) : text.split(/\s+/);
    const separator = language === 'zh' ? '' : ' ';
    const lines = [''];
    for (const unit of units) {
        const index = lines.length - 1;
        const candidate = lines[index] ? lines[index] + separator + unit : unit;
        if (candidate.length > width && lines[index]) lines.push(unit);
        else lines[index] = candidate;
    }
    return lines.join('\n');
}
const tracks = {en: manifest.vtt};
for (const item of [{language: 'en', texts: sourceTexts}, ...output]) {
    const name = manifest.vtt.replace(/\.en\.vtt$/, `.${item.language}.vtt`);
    const cues = blocks.map((block, index) => `${block[0]}\n${block[1]}\n${wrap(item.texts[index], item.language)}\n`);
    writeFileSync(resolve(target, name), `WEBVTT - ${manifest.title} (${item.language})\n\n${cues.join('\n')}`);
    tracks[item.language] = name;
}
for (const item of output) writeFileSync(item.path, item.memory);
manifest.captionTracks = tracks;
manifest.captions.languages = Object.keys(tracks);
manifest.bytes.vtt = statSync(resolve(target, manifest.vtt)).size;
manifest.bytes.captionTracks = Object.fromEntries(Object.entries(tracks).map(([language, name]) => [language, statSync(resolve(target, name)).size]));
writeFileSync(resolve(target, 'film.json'), JSON.stringify(manifest, null, 2) + '\n');
console.log(`${id}: ${Object.keys(tracks).join(', ')} captions and translated transcripts, ${blocks.length} cues each.`);
