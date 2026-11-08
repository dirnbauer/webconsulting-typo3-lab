import { featureFilm, SHOTS } from './feature-film.mjs';

/**
 * The Typewright film (formerly Redaktion; the film id, the fixture folder and
 * the audio folder keep the old name, like the seed command's markers). One
 * shot per feature of the product page, in the page's order: 15 screenshots of
 * the packaged macOS app (2.4) with the lab's demo content, the same files as
 * the page's three feature tab groups (typewright-fNN-<hash>.webp).
 */
// The captures of the 15 features (Resources/Public/Images/Redaktion/), the
// same files as the page's tabs. This module also runs in film.html (the
// browser), so the names are listed here, not read from the folder.
const CAPTURES = {
    f01: 'typewright-f01-400d1845.webp',
    f02: 'typewright-f02-d8310f25.webp',
    f03: 'typewright-f03-1f6e07a7.webp',
    f04: 'typewright-f04-6ae2026b.webp',
    f05: 'typewright-f05-6546df1c.webp',
    f06: 'typewright-f06-1caa4061.webp',
    f07: 'typewright-f07-ab188d61.webp',
    f08: 'typewright-f08-4364ecd4.webp',
    f09: 'typewright-f09-15a37282.webp',
    f10: 'typewright-f10-07891631.webp',
    f11: 'typewright-f11-3a746b71.webp',
    f12: 'typewright-f12-13936c98.webp',
    f13: 'typewright-f13-5c4bf08e.webp',
    f14: 'typewright-f14-8755aed8.webp',
    f15: 'typewright-f15-fffcf204.webp',
};
/** The capture of feature NN; before the capture exists, its future name (the render fails loudly on it). */
const picture = (number) => CAPTURES[number] ?? `typewright-${number}.webp`;

const [id, spec] = featureFilm({
    id: 'redaktion', page: 8447, name: 'Typewright', section: 'software', slug: 'typewright',
    alt: 'Overview film, {duration} seconds: Typewright, a calm Mac workspace for TYPO3 content, in fifteen features. '
        + 'The page tree in every language, the page assistant, TYPO3’s element wizard, moving and switching elements, page properties, '
        + 'every field of an element, rich text that keeps TYPO3’s markup, links, news with images and videos, local drafts and saving, '
        + 'the media library, uploads, alt text per use, translation next to the original and workspace publishing. Narrated, with selectable captions.',
    // The look of the home page product film, like the skills films (plan D6).
    eyebrow: 'A calm Mac workspace for TYPO3', style: 'home', music: 'calm',
    video: {
        header: 'Typewright in fifteen features',
        description: '<p>The actual macOS app with demo content from the TYPO3 lab: pages and languages, adding and moving elements, editing, links, news with media, saving, uploads, alt text, translation and publishing. Narrated, with selectable captions.</p>',
    },
    hook: { head: 'One page. Four languages. <em>A deadline.</em>', say: 'One page, four languages, a dozen images, and a deadline.' },
    title: {
        sub: 'Fifteen features for TYPO3 content, on your Mac',
        key: 'macOS · TYPO3 content · webconsulting',
        say: 'Typewright is a calm Mac workspace for TYPO3. Your drafts stay on your Mac. TYPO3 stays in charge of publishing.',
    },
    shots: [
        // Build pages the TYPO3 way (features 1 to 5)
        { image: picture('f01'), eyebrow: 'Pages and languages', head: 'Every page. <em>Every language.</em>', text: 'Columns from the backend layout · language tabs', focus: [0.4, 0.3, 0.57, 0.52], say: 'Pages look the way TYPO3 shows them, and language tabs count what is translated.' },
        { image: picture('f02'), eyebrow: 'A new page', head: 'Five steps. <em>Starts hidden.</em>', text: 'Position, type, URL path, translations, first element', focus: [0.17, 0.08, 0.66, 0.84], say: 'The page assistant places a new page in five steps and keeps it hidden until it is ready.' },
        { image: picture('f03'), eyebrow: 'Add content', head: 'TYPO3’s own <em>element wizard.</em>', text: 'Content Blocks included · column rules respected', focus: [0.17, 0.12, 0.66, 0.8], say: 'Add an element from TYPO3’s own wizard, Content Blocks included, and fill it in right away.' },
        { image: picture('f04'), eyebrow: 'Move and switch', head: 'Move, duplicate, <em>hide, switch.</em>', text: 'TYPO3’s own move and copy commands', focus: [0.42, 0.5, 0.56, 0.5], say: 'Move elements between columns, duplicate or hide them, and switch languages with one tab.' },
        { image: picture('f05'), eyebrow: 'Page properties', head: 'Title, URL, SEO. <em>Per language.</em>', text: 'Visibility and dates next to the content', focus: [0.17, 0.08, 0.66, 0.84], say: 'Page properties, from the URL path to the search description, in every language.' },
        // Write, edit and link (features 6 to 10)
        { image: picture('f06'), eyebrow: 'Editing', head: 'Every field <em>of an element.</em>', text: 'Headers, text, links, files and collections', focus: [0.3, 0.1, 0.65, 0.8], say: 'Edit every field TYPO3 defines, including tabs, questions and other collections.' },
        { image: picture('f07'), eyebrow: 'Rich text', head: 'Keeps TYPO3’s <em>markup.</em>', text: 'Abbreviations, language spans, classes, images', focus: [0.3, 0.1, 0.65, 0.8], say: 'Rich text keeps TYPO3’s markup: abbreviations, language spans, classes and images.' },
        { image: picture('f08'), eyebrow: 'Links', head: 'Links the way <em>TYPO3 stores them.</em>', text: 'Pages, content anchors, files, folders, records · ⌘K', focus: [0.17, 0.08, 0.66, 0.84], say: { show: 'Press ⌘K to link a page, a content anchor, a file or a record.', say: 'Press Command K to link a page, a content anchor, a file or a record.' } },
        { image: picture('f09'), eyebrow: 'News', head: 'Everything <em>EXT:news offers.</em>', text: 'Images, videos, related links, categories, tags', focus: [0.3, 0.1, 0.65, 0.8], say: 'News articles get their images and videos, related links, categories and tags.' },
        { image: picture('f10'), eyebrow: 'Saving', head: 'Kept on your Mac. <em>Sent with ⌘S.</em>', text: 'Local drafts · a check and the time in the save bar', focus: [0.42, 0.5, 0.56, 0.5], say: { show: 'Drafts stay on your Mac. ⌘S sends them to TYPO3, and the save bar confirms it.', say: 'Drafts stay on your Mac. Command S sends them to TYPO3, and the save bar confirms it.' } },
        // Media, languages and publishing (features 11 to 15)
        { image: picture('f11'), eyebrow: 'Media library', head: 'TYPO3’s file list, <em>in every language.</em>', text: 'Folders, search, metadata per language', focus: [0.18, 0.02, 0.8, 0.94], say: 'The media library browses TYPO3’s folders and edits metadata in every language.' },
        { image: picture('f12'), eyebrow: 'Upload', head: 'Upload it. <em>Watch it land.</em>', text: 'Progress · cancel · TYPO3’s file storage', focus: [0.34, 0.38, 0.64, 0.6], say: 'Uploads show their progress and land in TYPO3’s file storage.' },
        { image: picture('f13'), eyebrow: 'Accessibility', head: 'Alt text <em>where the image is used.</em>', text: 'Title, description and link per use', focus: [0.3, 0.2, 0.65, 0.7], say: 'Each use of an image gets its own alternative text.' },
        { image: picture('f14'), eyebrow: 'Translation', head: 'Translate next to <em>the original.</em>', text: 'TYPO3’s localize command · the original under each field', focus: [0.4, 0.26, 0.57, 0.54], say: 'Translate with TYPO3’s own command, with the original right under each field.' },
        { image: picture('f15'), eyebrow: 'Workspaces', head: 'Review every change. <em>Then publish.</em>', text: 'TYPO3’s workspace commands · explicit approval', focus: [0.2, 0.05, 0.75, 0.9], say: 'Review every change in a workspace, and publish only after you confirm.' },
    ],
    end: {
        head: 'Write now. <em>Publish when you are ready.</em>', cta: 'Ask for a demonstration', url: 'typo3-lab.webconsulting.at/software/typewright/',
        note: 'macOS Apple Silicon · beta · independent webconsulting application',
        say: 'Typewright, by webconsulting. Ask us for a demonstration.',
    },
});
const resolvePicture = (value) => value.replace(SHOTS, 'packages/site_package/Resources/Public/Images/Redaktion/');
// The element menu, the save bar and the upload panel sit at the bottom of their screenshots:
// those shots start on them and pull back to the whole window.
const PULL = ['f04', 'f10', 'f12'].map((number) => picture(number));
for (const scene of spec.scenes) {
    if (scene.type === 'shot' && PULL.some((name) => scene.image?.endsWith(name))) scene.pull = true;
    if (scene.image) scene.image = resolvePicture(scene.image);
    if (scene.images) scene.images = scene.images.map(resolvePicture);
    if (scene.type === 'shot') scene.url = 'Typewright · macOS · TYPO3 lab demo content';
}
// The page's hero is a header page element, which the seeder does not
// recognise as an intro: place the film right after it explicitly.
export default { [id]: { ...spec, placements: [{ slug: '/software/typewright', after: { header: 'Write now. Publish when you are ready.' } }] } };
