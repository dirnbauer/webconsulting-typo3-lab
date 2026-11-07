/**
 * The shape of a feature or AI film (35 to 50 s): the problem, the product,
 * three or four tour screenshots with a slow push towards their content, an
 * optional list of benefits, and the end card. The screenshots are the
 * feature page's own (Desiderio's Styleguide folder), so the film shows
 * what the page shows. A shot under stills/ is a still from the footage cache
 * instead (capture-backend.mjs), for a tour screenshot that does not fit.
 */

export const SHOTS = 'vendor/webconsulting/desiderio/Resources/Public/Styleguide/';
export const HOST = 'typo3-lab.webconsulting.at';

const source = (image) => (image.startsWith('stills/') ? image : SHOTS + image);
const backend = (image) => image.startsWith('Backend/') || image.startsWith('stills/backend/');

/**
 * @param {object} film
 * @param {string} film.section 'features', 'ai' or 'software': the lab path of the page.
 * @param {string} [film.eyebrow] the title's (and start card's) eyebrow; default 'For TYPO3 v14',
 *   'AI for TYPO3 v14' for section 'ai'. Software films use 'Software by webconsulting'.
 * @param {string} [film.background] lib/backgrounds.mjs style ('aurora' when absent).
 * @param {string} [film.alt] the video's alt text ({duration} is replaced), when the default does not fit.
 * @param {{head: string, say: string}} film.hook
 * @param {{sub: string, key: string, say: string}} film.title
 * @param {Array<{image: string, eyebrow: string, head: string, text?: string, say: string, focus?: number[]}>} film.shots
 * @param {{head: string, items: string[][], say: string}} [film.points]
 * @param {{head: string, cta: string, url: string, install?: string, note?: string, say: string}} film.end
 */
export function featureFilm({ id, page, name, section, slug = id, eyebrow, background, alt, accent, palette, music, hook, title, shots, points, end, burnedInCaptions = false, video }) {
    const path = `/${section}/${slug}/`;
    const forTypo3 = section !== 'software';
    return [id, {
        name,
        page,
        slug: path.slice(0, -1),
        title: `${name}: overview film`,
        url: `${HOST}${path}`,
        accent,
        palette,
        music,
        burnedInCaptions,
        ...(video ? {video} : {}),
        ...(background ? { background } : {}),
        alt: alt ?? `Overview film, {duration} seconds: ${name}${forTypo3 ? ' for TYPO3 v14' : ''}. `
            + `${shots.map((shot) => shot.head).join('; ')}. Narrated, with English captions.`,
        scenes: [
            { type: 'hook', head: hook.head, images: shots.slice(0, 3).map((shot) => source(shot.image)), say: hook.say },
            { type: 'title', eyebrow: eyebrow ?? (section === 'ai' ? 'AI for TYPO3 v14' : 'For TYPO3 v14'), name, sub: title.sub, key: title.key, image: source(shots[0].image), say: title.say },
            ...shots.map((shot, index) => ({
                type: 'shot',
                chapter: shot.eyebrow,
                image: source(shot.image),
                url: backend(shot.image) ? `${HOST}/typo3/` : HOST + (shot.path ?? ''),
                tag: backend(shot.image) ? 'TYPO3 backend' : '',
                step: String(index + 1).padStart(2, '0'),
                eyebrow: shot.eyebrow,
                head: shot.head,
                text: shot.text,
                focus: shot.focus,
                say: shot.say,
            })),
            ...(points ? [{ type: 'points', head: points.head, items: points.items, say: points.say }] : []),
            { type: 'end', head: end.head, cta: end.cta, url: end.url, install: end.install ? `<span>$</span> ${end.install}` : null, note: end.note, say: end.say },
        ],
    }];
}
