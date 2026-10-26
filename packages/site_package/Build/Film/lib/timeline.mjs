/**
 * When everything in a film happens, from its spec and the measured narration.
 * film.html places the scenes with it, generate-film-audio.mjs and
 * render-films.mjs place the voice lines and the captions with it, so picture,
 * sound and captions share one clock.
 *
 * A scene lasts from its first line to the end of its last line plus a hold,
 * never shorter than its `min`. Before the voice exists, a line's length is
 * estimated from its words (George at speed 0.9: about 2.3 words a second).
 */

/** How the voice should read what the captions print. */
const SPOKEN = [
    [/TYPO3 v14/g, 'typo three version fourteen'],
    [/TYPO3/g, 'typo three'],
    [/\bv14\b/g, 'version fourteen'],
    [/\.docx\b/g, 'dot doc x'],
    [/\bDOCX\b/g, 'doc x'],
    [/\bSolr\b/g, 'Solar'],
    [/\bWorkOS\b/g, 'Work O S'],
    [/\bMCP\b/g, 'M C P'],
    [/\bx402\b/gi, 'x four oh two'],
    [/\bHTTP 402\b/g, 'H T T P four oh two'],
    [/\bllms\.txt\b/g, 'L L M S dot text'],
    [/\bagents\.md\b/g, 'agents dot M D'],
    [/\bnr-llm\b/g, 'N R L L M'],
    [/\bnr-vault\b/g, 'N R vault'],
    [/\bUSDC\b/g, 'U S D C'],
    [/\bSSO\b/g, 'S S O'],
    [/\bA2UI\b/g, 'A two U I'],
    [/\bAG-UI\b/g, 'A G U I'],
    [/\bA2A\b/g, 'A two A'],
    [/\bUCP\b/g, 'U C P'],
    [/\bAP2\b/g, 'A P two'],
    [/\bsg_apicore\b/g, 'S G API core'],
    [/\bsgalinski\b/g, 'S Galinski'],
    [/\bSEO\b/g, 'S E O'],
    [/\bKPI\b/g, 'K P I'],
    [/\bCTA\b/g, 'C T A'],
    [/ & /g, ' and '],
    [/\bREST\b/g, 'rest'],
    [/\bJSON\b/g, 'Jason'],
    [/\bCLI\b/g, 'C L I'],
    [/\bTSconfig\b/g, 'T S config'],
    [/\bURIs?\b/g, (match) => (match.endsWith('s') ? 'U R Is' : 'U R I')],
    [/\bURLs\b/g, 'U R Ls'],
    [/\bUI\b/g, 'U I'],
    [/\bshadcn\b/g, 'shad C N'],
    [/\bJev\b/g, 'Jev'],
    [/\b(\d+) %/g, '$1 percent'],
];

export function spoken(text) {
    return SPOKEN.reduce((out, [pattern, replacement]) => out.replace(pattern, replacement), text)
        .replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
}

/** The narration lines of a spec, in order: [{show, say, scene}]. */
export function linesOf(spec) {
    const lines = [];
    spec.scenes.forEach((scene, index) => {
        const said = scene.say == null ? [] : Array.isArray(scene.say) ? scene.say : [scene.say];
        for (const line of said) {
            const show = typeof line === 'string' ? line : line.show;
            const say = typeof line === 'string' ? spoken(line) : (line.say ?? spoken(line.show));
            // `after`: the line starts no earlier than this many ms into its scene.
            lines.push({ show, say, scene: index, after: typeof line === 'string' ? 0 : (line.after ?? 0) });
        }
    });
    return lines;
}

const estimate = (say) => Math.round((say.split(/\s+/).length / 2.3) * 1000 + 250);

/** Words of a line that were not measured: spread evenly over its length. */
function guessWords(say, duration) {
    const words = say.split(/\s+/);
    const step = duration / words.length;
    return words.map((word, i) => ({ word, start: Math.round(i * step), end: Math.round((i + 1) * step) }));
}

const DEFAULTS = {
    hook: { lead: 600, hold: 900, min: 3800 },
    title: { lead: 500, hold: 1100, min: 4200 },
    shot: { lead: 450, hold: 900, min: 5200 },
    points: { lead: 450, hold: 1200, min: 5000 },
    grid: { lead: 500, hold: 1200, min: 5500 },
    montage: { lead: 400, hold: 800, min: 5000 },
    split: { lead: 500, hold: 1200, min: 6000 },
    end: { lead: 700, hold: 3600, min: 7000 },
};
const GAP = 380;

/**
 * Scenes with start and end (ms), lines with `at` (scene time) and words, the
 * scenes' duration. `narration` is audio/<id>/narration.json or null.
 */
export function timeline(spec, narration) {
    const recorded = narration?.lines ?? [];
    const lines = linesOf(spec).map((line, index) => {
        const take = recorded[index];
        const measured = Boolean(take && take.say === line.say && take.duration > 0);
        const duration = measured ? take.duration : estimate(line.say);
        return {
            ...line,
            index,
            duration,
            measured,
            file: measured ? take.file : null,
            // Where the take's speech ends when a fragment follows it (generate-film-audio.mjs).
            end: measured && take.end ? take.end : null,
            words: measured && take.words?.length ? take.words : guessWords(line.say, duration),
        };
    });
    let t = 0;
    const scenes = spec.scenes.map((scene, index) => {
        const base = DEFAULTS[scene.type] ?? DEFAULTS.shot;
        const lead = scene.lead ?? base.lead;
        const hold = scene.hold ?? base.hold;
        const own = lines.filter((line) => line.scene === index);
        const start = t;
        let cursor = start + lead;
        for (const line of own) {
            line.at = Math.round(Math.max(cursor, start + line.after));
            cursor = line.at + line.duration + GAP;
        }
        const spoken = own.length ? cursor - GAP + hold : start + (scene.min ?? base.min);
        const end = Math.round(Math.max(spoken, start + (scene.min ?? base.min)));
        t = end;
        return { ...scene, index, start, end, lines: own };
    });
    return { duration: t, scenes, lines };
}

/**
 * The start card and the end hold, around the scenes. The film opens on its
 * title card, already complete in the first frame (muted autoplay and a
 * paused player show that frame), and the scenes' own clock starts LEAD ms
 * later; at the end the closing card stays on screen, TAIL ms past the last
 * scene, where it used to fade to black. Film time = LEAD + scene time.
 */
export const LEAD = 1500;
export const TAIL = 800;

/** The film's header, as the page and the start card show it. */
export function header(spec, duration) {
    return spec.video?.header ?? `${spec.name} in ${Math.round(duration / 1000)} seconds`;
}

/** Film time of the first spoken word of a scene after `after` that starts with `needle`. */
export function cue(scene, needle, fallback, after = -1) {
    const plain = (text) => text.toLowerCase().replace(/[^a-z0-9]/g, '');
    const pattern = plain(needle);
    for (const line of scene.lines) {
        const word = line.words.find((w) => line.at + w.start > after && plain(w.word).startsWith(pattern));
        if (word) return line.at + word.start;
    }
    return fallback;
}
