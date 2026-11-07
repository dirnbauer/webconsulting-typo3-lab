import { existsSync, readFileSync } from 'node:fs';
import { basename, join } from 'node:path';

/** Refuse to reuse audio and translations when the authored timeline has changed. */
export function pictureRefreshSource(directory, spec, timeline, vtt) {
    const current = JSON.parse(readFileSync(join(directory, 'film.json'), 'utf8'));
    const fail = (reason) => { throw new Error(`${current.id}: cannot refresh picture: ${reason}`); };
    if (spec.burnedInCaptions === true) fail('burned-in captions are still enabled');
    if (!current.sound || current.width !== 1920 || current.height !== 1080 || current.fps !== 30) {
        fail('expected a narrated 1920 × 1080, 30 fps film');
    }
    if (Math.abs(current.duration * 1000 - timeline.duration) > 1) fail('duration changed');
    const transcript = timeline.lines.map((line) => ({
        at: `${Math.floor(line.at / 60000)}:${String(Math.floor(line.at / 1000) % 60).padStart(2, '0')}`,
        text: line.show,
    }));
    if (JSON.stringify(current.transcript) !== JSON.stringify(transcript)) fail('transcript changed');
    const files = [current.mp4, current.poster, current.vtt, ...Object.values(current.captionTracks ?? {})];
    if (files.some((file) => typeof file !== 'string' || file !== basename(file) || !existsSync(join(directory, file)))) {
        fail('a source asset is missing or outside its fixture directory');
    }
    if (readFileSync(join(directory, current.vtt), 'utf8') !== vtt) fail('caption timing or wording changed');
    return current;
}
