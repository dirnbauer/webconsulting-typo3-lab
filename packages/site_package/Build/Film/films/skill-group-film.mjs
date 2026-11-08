/**
 * Films for the agentic-skills group pages (plan 2026-11-08, sections 3 and D6):
 * one film per group of three or four skills, in the look of the home page
 * product film (`style: 'home'`), about a minute long.
 *
 *   1. hook            the problem the group solves, one line
 *   2. title           the group name, its pitch, the repository
 *   3. per skill:
 *      terminal        "You say": the skill's own trigger phrase (from its evals), typed
 *      shot|points     a real result (footage of the earlier skill films) or what the skill does
 *   4. end             "Open source. Help us improve them.", with the install line
 *
 * Real-demo rule as in skill-film.mjs: prompts are the skills' own eval
 * prompts, shots are recorded footage, points only state what SKILL.md says.
 * No invented terminal output. The film runs on /ai/agentic-skills/<slug>
 * (sitepackage:films:seed puts it after the page's header section).
 */

import { HOST, SHOTS } from './feature-film.mjs';
import { SKILLS_INSTALL, SKILLS_REPO } from './skill-film.mjs';

const CLIENT = 'Claude Code · ~/projects/acme';
const source = (image) => (image && !image.startsWith('stills/') && !/^(vendor|packages|public)\//.test(image) ? SHOTS + image : image);

/**
 * @param {object} film
 * @param {string} film.slug group page slug under /ai/agentic-skills
 * @param {string} film.name group name (word mark on the title card)
 * @param {string} film.sub the pitch under the word mark
 * @param {{head: string, say: string, images?: string[]}} film.hook
 * @param {string} film.titleSay narration on the title card
 * @param {Array<{name: string, prompt: {text: string, say: string}, shot?: {image?: string, clip?: string, head: string, text?: string, say: string}, does?: {head?: string, items: Array<string|string[]>, say: string}}>} film.skills
 *        points items: 'text' or [title, small, needle] (needle = the spoken word that cues the card)
 * @param {string} [film.music] music bed, default 'focus'
 * @returns {[string, object]} [`skills-<slug>`, spec]
 */
export function skillGroupFilm({ slug, name, sub, hook, titleSay, skills, music = 'focus', alt }) {
    const path = `/ai/agentic-skills/${slug}/`;
    const scenes = [
        { type: 'hook', head: hook.head, images: (hook.images ?? []).map(source), say: hook.say },
        { type: 'title', eyebrow: 'Agentic skills for TYPO3 v14', name, sub, key: SKILLS_REPO, say: titleSay },
    ];
    skills.forEach((skill, index) => {
        const step = String(index + 1).padStart(2, '0');
        scenes.push({ type: 'terminal', chapter: skill.name, eyebrow: `${step} · You say`, title: CLIENT, prompt: skill.prompt.text, say: skill.prompt.say });
        if (skill.shot) {
            const { image, clip, head, text, say, ...rest } = skill.shot;
            scenes.push({ type: 'shot', layout: 'wide', chapter: skill.name, eyebrow: skill.name, step, ...rest, ...(image ? { image: source(image) } : {}), ...(clip ? { clip } : {}), head, text, say });
        } else {
            scenes.push({ type: 'points', chapter: skill.name, head: skill.does.head ?? `<em>${skill.name}</em>`, items: skill.does.items.map((item) => (Array.isArray(item) ? item : [item])), say: skill.does.say });
        }
    });
    scenes.push({ type: 'end', head: 'Open source. <em>Help us improve them.</em>', cta: 'Clone the skills', url: SKILLS_REPO, install: `<span>$</span> ${SKILLS_INSTALL}`, min: 5600, hold: 2400, say: 'Open source. Clone the skills, and help us improve them.' });
    return [`skills-${slug}`, {
        name,
        slug: path.slice(0, -1),
        title: `${name}: agent skills for TYPO3`,
        url: `${HOST}${path}`,
        style: 'home',
        music,
        // The header stays "<name> in N seconds"; the description names the captions in four languages.
        video: { description: `<p>${sub} For each skill: the prompt you would type and what it does. Narrated, with selectable captions.</p>` },
        card: { eyebrow: 'Agentic skills for TYPO3 v14' },
        placements: [{ slug: path.slice(0, -1) }],
        alt: alt ?? `Film, {duration} seconds: ${name}, ${skills.length} agent skills for TYPO3 v14 (${skills.map((skill) => skill.name).join(', ')}). `
            + 'Each skill with its own prompt and what it delivers. Narrated, with selectable captions.',
        scenes,
    }];
}
