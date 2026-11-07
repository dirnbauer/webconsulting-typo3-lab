import { featureFilm, SHOTS } from './feature-film.mjs';
const pictures = {
    editor: 'redaktion-native-1449743c.webp',
    draft: 'redaktion-local-draft-c16dc500.webp',
    preview: 'redaktion-preview-f2f09d39.webp',
    dark: 'redaktion-dark-b5e037ff.webp',
};
const [id, spec] = featureFilm({
    id: 'redaktion', alt: "Overview film, {duration} seconds: webconsulting Redaktion. Rich-text news editing, local drafts, offline continuation, content preview and native Mac shortcuts. Narrated, with selectable English captions.", page: 8447, name: 'webconsulting Redaktion', section: 'software',
    eyebrow: 'A focused Mac app for TYPO3 news', background: 'grid', music: 'calm',
    accent: '#66c4e1', palette: ['#1b7a95', '#0f4555', '#66c4e1'],
    video: { header: 'Redaktion in one minute', description: '<p>The actual macOS app with synthetic demo articles: focused writing, local drafts, offline continuation and deliberate TYPO3 publishing. Narrated, with selectable English captions.</p>' },
    hook: { head: 'Halfway through a story. <em>The connection drops.</em>', say: 'You are halfway through a story when the connection drops.' },
    title: { sub: 'Write now. Publish when you are ready.', key: 'macOS · TYPO3 news · webconsulting', say: 'Redaktion keeps your writing on your Mac. TYPO3 stays in charge of publishing.' },
    shots: [
        { image: pictures.editor, eyebrow: 'Your writing workspace', head: 'One story. <em>Room to think.</em>', text: 'Actual app · synthetic demo articles', say: 'Pick a news article, write in rich text, and keep the rest of the website out of your way.' },
        { image: pictures.draft, eyebrow: 'Local drafts', head: 'Saved here. <em>Sent when you decide.</em>', text: 'Drafts stay separate from TYPO3 saves', say: 'Drafts save locally after a short pause. Previously loaded articles and drafts stay available after an offline restart.' },
        { image: pictures.preview, eyebrow: 'Read it before you send it', head: 'A clean <em>content preview.</em>', text: 'Preview the wording, then save deliberately', say: 'Check the wording in preview. Saving sends it to your chosen TYPO3 workspace. Publication needs your explicit approval.' },
        { image: pictures.dark, eyebrow: 'Made for your Mac', head: 'Your focus. <em>Day or night.</em>', text: 'Light and dark mode · native shortcuts', say: 'Native shortcuts, encrypted sessions, and light or dark mode. The same workspace also runs in the browser.' },
    ],
    end: { head: 'Write now. <em>Publish when you are ready.</em>', cta: 'Ask for a demonstration', url: 'typo3-lab.webconsulting.at/software/redaktion/', note: 'macOS Apple Silicon · beta · independent webconsulting application', say: 'Redaktion, by webconsulting. Ask us for a demonstration.' },
});
const resolvePicture = value => value.replace(SHOTS, 'packages/site_package/Resources/Public/Images/Redaktion/');
for (const scene of spec.scenes) {
    if (scene.image) scene.image = resolvePicture(scene.image);
    if (scene.images) scene.images = scene.images.map(resolvePicture);
    if (scene.type === 'shot') scene.url = 'webconsulting Redaktion · macOS · demo articles';
}
export default { [id]: { ...spec, placements: [{ slug: '/software/redaktion' }] } };
