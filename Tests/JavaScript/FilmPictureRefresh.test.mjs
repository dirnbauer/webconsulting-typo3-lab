import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { films } from '../../packages/site_package/Build/Film/films/index.mjs';
import { pictureRefreshSource } from '../../packages/site_package/Build/Film/lib/picture-refresh.mjs';

test('website films leave the caption layer to the native player', () => {
    for (const [id, spec] of Object.entries(films)) {
        assert.notEqual(spec.burnedInCaptions, true, `${id} would duplicate the player captions`);
    }
});

test('picture refresh preserves multilingual metadata and refuses changed speech timing', (t) => {
    const directory = mkdtempSync(join(tmpdir(), 'film-picture-test-'));
    t.after(() => rmSync(directory, { recursive: true }));
    const vtt = 'WEBVTT\n\n00:00:02.000 --> 00:00:04.000\nSearch in your design.\n';
    const manifest = {
        id: 'example', duration: 8, width: 1920, height: 1080, fps: 30, sound: true,
        mp4: 'old.mp4', poster: 'old.webp', vtt: 'old.en.vtt',
        captions: { burnedIn: true, languages: ['en', 'de'] },
        captionTracks: { en: 'old.en.vtt', de: 'old.de.vtt' },
        transcript: [{ at: '0:02', text: 'Search in your design.' }],
        transcriptTranslations: { de: [{ at: '0:02', text: 'Suche in Ihrem Design.' }] },
    };
    writeFileSync(join(directory, 'film.json'), JSON.stringify(manifest));
    for (const file of [manifest.mp4, manifest.poster, ...Object.values(manifest.captionTracks)]) {
        writeFileSync(join(directory, file), file === manifest.vtt ? vtt : 'fixture');
    }
    const timeline = { duration: 8000, lines: [{ at: 2000, show: 'Search in your design.' }] };
    assert.deepEqual(pictureRefreshSource(directory, {}, timeline, vtt), manifest);
    assert.throws(() => pictureRefreshSource(directory, {}, { ...timeline, duration: 8100 }, vtt), /duration changed/);
    assert.throws(() => pictureRefreshSource(directory, {}, timeline, vtt.replace('02.000', '02.250')), /caption timing or wording changed/);
    assert.throws(() => pictureRefreshSource(directory, {}, { ...timeline, lines: [{ at: 2000, show: 'Changed speech.' }] }, vtt), /transcript changed/);
    assert.throws(() => pictureRefreshSource(directory, { burnedInCaptions: true }, timeline, vtt), /burned-in captions are still enabled/);
    rmSync(join(directory, 'old.de.vtt'));
    assert.throws(() => pictureRefreshSource(directory, {}, timeline, vtt), /source asset is missing/);
});
