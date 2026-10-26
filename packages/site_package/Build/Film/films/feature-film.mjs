/**
 * The shape of a feature or AI film (35 to 50 s): the problem, the product,
 * three or four tour screenshots with a slow push towards their content, an
 * optional list of benefits, and the end card. The screenshots are the
 * feature page's own (Desiderio's Styleguide folder), so the film shows
 * what the page shows.
 */

export const SHOTS = 'vendor/webconsulting/desiderio/Resources/Public/Styleguide/';
export const HOST = 'typo3-lab.webconsulting.at';

/**
 * @param {object} film
 * @param {string} film.section 'features' or 'ai': the lab path of the page.
 * @param {{head: string, say: string}} film.hook
 * @param {{sub: string, key: string, say: string}} film.title
 * @param {Array<{image: string, eyebrow: string, head: string, text?: string, say: string, focus?: number[]}>} film.shots
 * @param {{head: string, items: string[][], say: string}} [film.points]
 * @param {{head: string, cta: string, url: string, install?: string, note?: string, say: string}} film.end
 */
export function featureFilm({ id, page, name, section, slug = id, accent, palette, music, hook, title, shots, points, end }) {
    const path = `/${section}/${slug}/`;
    return [id, {
        name,
        page,
        slug: path.slice(0, -1),
        title: `${name}: overview film`,
        url: `${HOST}${path}`,
        accent,
        palette,
        music,
        alt: `Overview film, {duration} seconds: ${name} for TYPO3 v14. `
            + `${shots.map((shot) => shot.head).join('; ')}. Narrated, with English captions.`,
        scenes: [
            { type: 'hook', head: hook.head, images: shots.slice(0, 3).map((shot) => SHOTS + shot.image), say: hook.say },
            { type: 'title', eyebrow: section === 'ai' ? 'AI for TYPO3 v14' : 'For TYPO3 v14', name, sub: title.sub, key: title.key, image: SHOTS + shots[0].image, say: title.say },
            ...shots.map((shot, index) => ({
                type: 'shot',
                chapter: shot.eyebrow,
                image: SHOTS + shot.image,
                url: shot.image.startsWith('Backend/') ? `${HOST}/typo3/` : HOST + (shot.path ?? ''),
                tag: shot.image.startsWith('Backend/') ? 'TYPO3 backend' : '',
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
