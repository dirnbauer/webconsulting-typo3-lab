import { featureFilm, SHOTS } from './feature-film.mjs';
// Screenshots of the packaged macOS app (2.2) against the lab's Desiderio
// demo content, the same files as the product page.
const pictures = {
    pages: 'redaktion-pages-685b1995.webp',
    assistant: 'redaktion-assistant-c4e069cb.webp',
    wizard: 'redaktion-wizard-e3fd916b.webp',
    translate: 'redaktion-translate-db40c6ec.webp',
    links: 'redaktion-links-b56892f1.webp',
    media: 'redaktion-media-c3967a47.webp',
    editor: 'redaktion-native-5e39c7c2.webp',
};
const [id, spec] = featureFilm({
    id: 'redaktion', alt: "Overview film, {duration} seconds: webconsulting Redaktion. Pages and content in every language, a page assistant, TYPO3's element wizard, translation next to the original, TYPO3 links, a media library with alt text per use and calm news writing with local drafts. Narrated, with selectable English captions.", page: 8447, name: 'webconsulting Redaktion', section: 'software',
    eyebrow: 'A calm Mac workspace for TYPO3', background: 'grid', music: 'calm',
    accent: '#66c4e1', palette: ['#1b7a95', '#0f4555', '#66c4e1'],
    video: { header: 'Redaktion in ninety seconds', description: '<p>The actual macOS app with demo content from the TYPO3 lab: pages and languages, the page assistant, the element wizard, translation, links, media and news. Narrated, with selectable English captions.</p>' },
    hook: { head: 'One page. Four languages. <em>A deadline.</em>', say: 'One page, four languages, a dozen images, and a deadline.' },
    title: { sub: 'Write, build and translate. Publish when you are ready.', key: 'macOS · TYPO3 content · webconsulting', say: 'Redaktion is a calm Mac workspace for TYPO3. Your drafts stay on your Mac. TYPO3 stays in charge of publishing.' },
    shots: [
        { image: pictures.pages, eyebrow: 'Pages and languages', head: 'Every page. <em>Every language.</em>', text: 'Columns from the backend layout · language tabs', focus: [0.4, 0.3, 0.57, 0.52], say: 'Pages look the way TYPO3 shows them. Language tabs count what is already translated.' },
        { image: pictures.assistant, eyebrow: 'A new page', head: 'Five steps. <em>Starts hidden.</em>', text: 'Position, type, URL path, translations, first element', focus: [0.17, 0.08, 0.66, 0.84], say: 'The page assistant places a new page, sets its URL path and translations, and keeps it hidden until you publish.' },
        { image: pictures.wizard, eyebrow: 'Content elements', head: 'TYPO3’s own <em>element wizard.</em>', text: 'Content Blocks included · column rules respected', focus: [0.17, 0.12, 0.66, 0.8], say: 'Elements come from TYPO3’s own wizard, Content Blocks included, filtered by the rules of each column.' },
        { image: pictures.translate, eyebrow: 'Translation', head: 'Translate next to <em>the original.</em>', text: 'TYPO3’s localize command · the original under each field', focus: [0.4, 0.26, 0.57, 0.54], say: 'Translations use TYPO3’s own command, and the original text sits right under each field.' },
        { image: pictures.links, eyebrow: 'Links', head: 'Links the way <em>TYPO3 stores them.</em>', text: 'Pages, content anchors, files, folders and records', focus: [0.17, 0.08, 0.66, 0.84], say: 'Link pages, their content, files and records. Links keep TYPO3’s format and show their titles.' },
        { image: pictures.media, eyebrow: 'Media and accessibility', head: 'The file list. <em>Alt text where it is used.</em>', text: 'Metadata in every language · alt text per use', focus: [0.18, 0.02, 0.8, 0.94], say: 'The media library works on TYPO3’s file list, and every image gets its own alternative text where it is used.' },
        { image: pictures.editor, eyebrow: 'News', head: 'Room for the story. <em>Saved on your Mac.</em>', text: 'Local drafts · offline restarts · ⌘S', focus: [0.4, 0.27, 0.57, 0.6], say: 'News keeps its calm writing surface, with local drafts that survive an offline restart.' },
    ],
    end: { head: 'Write now. <em>Publish when you are ready.</em>', cta: 'Ask for a demonstration', url: 'typo3-lab.webconsulting.at/software/redaktion/', note: 'macOS Apple Silicon · beta · independent webconsulting application', say: 'Redaktion, by webconsulting. Ask us for a demonstration.' },
});
const resolvePicture = value => value.replace(SHOTS, 'packages/site_package/Resources/Public/Images/Redaktion/');
for (const scene of spec.scenes) {
    if (scene.image) scene.image = resolvePicture(scene.image);
    if (scene.images) scene.images = scene.images.map(resolvePicture);
    if (scene.type === 'shot') scene.url = 'webconsulting Redaktion · macOS · TYPO3 lab demo content';
}
// The page's hero is a header page element, which the seeder does not
// recognise as an intro: place the film right after it explicitly.
export default { [id]: { ...spec, placements: [{ slug: '/software/redaktion', after: { header: 'Write now. Publish when you are ready.' } }] } };
