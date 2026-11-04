/**
 * The shape of the ten agentic-skill films (plan 2026-11-04, section 6.4): at
 * most 50 seconds, the skill actually working, recorded from real runs.
 *
 *   1. hook            the problem, one line
 *   2. title           the skill's name as the word mark, its purpose, the repository
 *   3. terminal        "You say": the trigger phrase typed into Claude Code
 *   4. terminal|shot   "It works": real command output (`work.lines`) or footage
 *   5. shot            "The result": the real screen or artefact
 *   6. points          "The guardrail": what the skill refuses or proves
 *   7. end             "Open source. Clone the skills.", with the install line
 *
 * Real-demo rule: every output line comes from a real run; the prompt is the
 * skill's own trigger phrase from its evals; footage comes from capture-*.mjs.
 * The film runs on page /ai/agentic-skills/<slug> (sitepackage:films:seed puts
 * it after the page's header section). `cap: 50` makes render-films.mjs --list
 * warn when the measured film (start card and end hold included) is longer.
 *
 *   export default Object.fromEntries([skillFilm({ slug: 'typo3-rector', ... }), ...]);
 */

import { HOST, SHOTS } from './feature-film.mjs';

export const SKILLS_REPO = 'github.com/dirnbauer/typo3-skills';
export const SKILLS_INSTALL = 'git clone https://github.com/dirnbauer/typo3-skills.git && ./install.sh';
const CLIENT = 'Claude Code · ~/projects/acme';

/** A Styleguide name or a stills/ path, as feature-film.mjs resolves them. */
const source = (image) => (image && !image.startsWith('stills/') && !/^(vendor|packages|public)\//.test(image) ? SHOTS + image : image);
const line = (value) => (typeof value === 'string' ? { head: value, say: value } : value);

/** A shot scene from {image|clip, ...}: the image is resolved, the rest passes through. */
function shotOf(part, chapter, eyebrow, step) {
    const { say, head, text, image, ...rest } = part;
    return { type: 'shot', layout: 'wide', chapter, eyebrow, step, ...rest, ...(image ? { image: source(image) } : {}), head, text, say };
}

/**
 * @param {object} film
 * @param {string} film.slug the skill's folder name, e.g. 'typo3-upgrade-run'
 * @param {string} film.name the word mark, usually the slug
 * @param {string} film.sub the one-line purpose under the word mark
 * @param {string|{head: string, say: string, images?: string[]}} film.hook
 * @param {{text: string, say: string, title?: string}} film.prompt the trigger phrase, typed
 * @param {{say: string, lines?: Array, prompt?: string, title?: string, highlight?: Array, then?: object}
 *        | {say: string, image?: string, clip?: string, head?: string}} film.work
 *        with `lines`: a terminal (prompt = the command that ran), else a shot
 * @param {{say: string, image?: string, clip?: string, head?: string, text?: string}} film.result
 * @param {string|{head?: string, items?: string[][], say: string}} film.guardrail
 * @param {string} [film.background] lib/backgrounds.mjs style, default 'orbits'
 * @param {string} [film.titleSay] narration on the title card (none by default)
 * @param {number} [film.page] page uid in the lab, once seeded
 * @returns {[string, object]} [`skill-<slug>`, spec]
 */
export function skillFilm({ slug, name = slug, sub, hook, prompt, work, result, guardrail, background = 'orbits', titleSay, page, music = 'focus', accent, palette, alt }) {
    const path = `/ai/agentic-skills/${slug}/`;
    const opener = line(hook);
    const guard = typeof guardrail === 'string' ? { say: guardrail } : guardrail;
    const working = work.lines
        ? {
            type: 'terminal',
            chapter: 'It works',
            eyebrow: 'It works',
            title: work.title ?? CLIENT,
            prompt: work.prompt ?? '',
            ps: work.ps ?? '$',
            lines: work.lines,
            highlight: work.highlight,
            then: work.then ? { ...work.then, ...(work.then.image ? { image: source(work.then.image) } : {}) } : undefined,
            say: work.say,
        }
        : shotOf(work, 'It works', 'It works', '02');
    return [`skill-${slug}`, {
        name,
        page,
        slug: path.slice(0, -1),
        title: `${name}: the skill at work`,
        url: `${HOST}${path}`,
        accent,
        palette,
        music,
        background,
        cap: 50,
        card: { eyebrow: 'Agentic skills for TYPO3 v14' },
        alt: alt ?? `Film, {duration} seconds: the ${name} agent skill at work in a real TYPO3 v14 project. `
            + 'The prompt, the real output, the result and its guardrail. Narrated, with English captions.',
        scenes: [
            { type: 'hook', head: opener.head, images: (opener.images ?? []).map(source), say: opener.say },
            // Shorter title and end cards than the overview films: the 50 s cap is tight.
            { type: 'title', eyebrow: 'AI for TYPO3 v14', name, sub, key: SKILLS_REPO, min: 3600, ...(titleSay ? { say: titleSay } : {}) },
            { type: 'terminal', chapter: 'You say', eyebrow: 'You say', title: prompt.title ?? CLIENT, prompt: prompt.text, say: prompt.say },
            working,
            shotOf(result, 'The result', 'The result', '03'),
            { type: 'points', chapter: 'The guardrail', head: guard.head ?? 'The <em>guardrail</em>', items: guard.items ?? [[guard.say]], say: guard.say },
            { type: 'end', head: 'Open source. <em>Clone the skills.</em>', cta: 'Clone the skills', url: SKILLS_REPO, install: `<span>$</span> ${SKILLS_INSTALL}`, min: 5600, hold: 2400, say: 'Open source. Clone the skills.' },
        ],
    }];
}
