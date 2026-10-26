/**
 * DOCX Editor, the long film (about 100 s): real footage from the lab, recorded
 * by capture-docx.mjs, and Microsoft Word's own render of a page before and
 * after two edits made in Word. Facts: packages/docx_editor/README.md and the
 * feature page (/features/docx-editor/).
 *
 * Clip segments are [clipFrom, clipTo, speed] in clip ms; camera keys are
 * [clip ms, rect] with rects as fractions of the 1920 × 1080 footage. The
 * `after` of a line is where its action shows in the scene (film ms).
 */

const length = (segments) => Math.round(segments.reduce((sum, [from, to, speed]) => sum + (to - from) / speed, 0));
const WORD = 1819 / 2573;
const EDITED = [0.1, 0.27, 0.82, 0.29];
const MENU = [0.0, 0.0, 0.42, 0.3];
const DIALOG = [0.17, 0.0, 0.66, 0.42];
const TOAST = [0.5, 0.5, 0.5, 0.5];

const filelist = [[2200, 4800, 1], [4800, 10000, 4], [10000, 12900, 1]];
const fileEdit = [[6700, 11900, 1.3], [11900, 15500, 1]];
const conflict = [[300, 3000, 1], [3000, 4600, 2], [4600, 11000, 1]];
const pageMenu = [[1800, 7600, 1.2], [7600, 12600, 5], [12600, 15000, 1]];
const pageEdit = [[2000, 5200, 1], [5200, 8000, 2], [8000, 9800, 1], [9800, 12000, 3], [12000, 13700, 1]];
const download = [[1600, 6600, 1]];
const upload = [[1800, 7600, 1], [7600, 11000, 2.5], [11000, 13500, 1]];
const newPages = [[1500, 6400, 1.3], [6400, 9400, 4], [9400, 17800, 1.5], [17800, 20500, 1]];

export default {
    'docx-editor': {
        name: 'DOCX Editor',
        page: 2081,
        slug: '/features/docx-editor',
        title: 'DOCX Editor: edit Word files inside TYPO3',
        video: {
            header: 'DOCX Editor in two minutes',
            description: '<p>Edit a Word file where it is stored, open any page as a Word document, or keep your own word processor: download the page, '
                + 'change it in Word or LibreOffice, upload it and review every change before the import. Recorded in this TYPO3 v14 lab, with English captions.</p>',
        },
        url: 'typo3-lab.webconsulting.at/typo3/',
        accent: '#9fb4ff',
        palette: ['#2b579a', '#c7863f', '#3a2f6a'],
        music: 'docx',
        alt: 'Product film, {duration} seconds: DOCX Editor for TYPO3 v14. A Word file opens from the file list, is edited and saved back; '
            + 'a second editor causes a warning instead of an overwrite; a page opens as a Word document, is edited and reviewed before import; '
            + 'the page is downloaded, edited in Microsoft Word and uploaded again with a review of the Word changes; a long document becomes new pages. '
            + 'Narrated, with English captions.',
        scenes: [
            {
                type: 'hook',
                head: 'Download. Edit. <em>Upload again.</em>',
                images: ['word-before.png', 'docx-filelist/0300.jpg', 'docx-page-menu/0420.jpg'],
                say: 'One fix in a Word file means download, edit, upload, and hoping nobody changed it.',
            },
            {
                type: 'hook',
                head: 'Copy, paste, <em>fix the formatting.</em>',
                say: 'Between Word and TYPO3, copy and paste breaks the formatting.',
            },
            {
                type: 'title',
                eyebrow: 'For TYPO3 v14',
                name: 'DOCX Editor',
                sub: 'Word documents, edited inside TYPO3',
                key: 'webconsulting/docx-editor',
                image: 'docx-file-edit/0060.jpg',
                say: 'DOCX Editor brings Word into TYPO3, in both directions.',
            },
            {
                type: 'shot', layout: 'wide', chapter: 'Word files', clip: 'docx-filelist', segments: filelist, min: length(filelist) + 400,
                tag: 'Media › example-studio',
                eyebrow: 'From the file list', head: 'Click Edit DOCX',
                camera: [[2200, [0.17, 0.12, 0.6, 0.38]], [4500, [0.17, 0.12, 0.6, 0.38]], [6000, 'full'], [10300, 'full'], [11400, [0.34, 0.25, 0.6, 0.75]]],
                say: [
                    'Click Edit DOCX next to any Word file in the file list.',
                    { show: 'It opens full page, in Word’s own layout.', after: 4200 },
                ],
            },
            {
                type: 'shot', layout: 'wide', chapter: 'Word files', clip: 'docx-file-edit', segments: fileEdit, min: length(fileEdit) + 300,
                tag: 'website-relaunch-brief.docx',
                eyebrow: 'Save to the same file', head: 'Type, then save',
                camera: [[6700, [0.12, 0.38, 0.62, 0.3]], [12600, [0.12, 0.38, 0.62, 0.3]], [13500, TOAST], [15500, TOAST]],
                say: [
                    'Type, save, and the change lands in the same file.',
                    { show: 'Tracked changes and comments survive the save.', after: 5400 },
                ],
            },
            {
                type: 'shot', layout: 'wide', chapter: 'Teamwork', clip: 'docx-conflict', segments: conflict, min: length(conflict) + 300,
                tag: 'website-relaunch-brief.docx',
                eyebrow: 'No overwritten work', head: 'Two editors, one file',
                camera: [[300, [0.6, 0.08, 0.4, 0.22]], [3000, [0.6, 0.08, 0.4, 0.22]], [4400, [0.25, 0.4, 0.5, 0.25]], [7600, [0.25, 0.4, 0.5, 0.25]], [8500, [0.0, 0.1, 0.8, 0.25]], [11000, [0.0, 0.1, 0.8, 0.25]]],
                say: [
                    'A badge shows how many editors have the file open.',
                    { show: 'If someone saved first, your save stops and offers a reload.', after: 7000 },
                ],
            },
            {
                type: 'shot', layout: 'wide', chapter: 'Pages in Word', clip: 'docx-page-menu', segments: pageMenu, min: length(pageMenu) + 300,
                tag: 'Layout › For agencies and integrators',
                eyebrow: 'Any page', head: 'Open a page in Word',
                camera: [[1800, 'full'], [2600, [0.5, 0.1, 0.42, 0.28]], [7300, [0.5, 0.1, 0.42, 0.28]], [7700, 'full'], [12700, [0.4, 0.45, 0.55, 0.55]]],
                say: [
                    'Any page opens as a Word document.',
                    { show: 'Each element becomes a framed block you can edit.', after: 5800 },
                ],
            },
            {
                type: 'shot', layout: 'wide', chapter: 'Pages in Word', clip: 'docx-page-edit', segments: pageEdit, min: length(pageEdit) + 300,
                tag: 'For agencies and integrators',
                eyebrow: 'Review before import', head: 'Nothing changes until you confirm',
                camera: [[2000, [0.25, 0.55, 0.5, 0.35]], [4900, [0.25, 0.55, 0.5, 0.35]], [7700, DIALOG], [12000, DIALOG], [12400, TOAST]],
                say: [
                    'Edit the text in the browser.',
                    { show: 'A review lists every change before anything reaches the page.', after: 4400 },
                ],
            },
            {
                type: 'shot', layout: 'wide', chapter: 'Your word processor', clip: 'docx-download', segments: download, min: length(download) + 400,
                tag: 'For agencies and integrators',
                eyebrow: 'Keep your word processor', head: 'Download the page as .docx',
                camera: [[1600, 'full'], [2300, MENU], [6600, MENU]],
                say: 'Or download the page, and keep your own word processor.',
            },
            {
                type: 'split', chapter: 'Your word processor',
                head: 'Edited in <em>Microsoft Word</em>',
                panes: [
                    {
                        label: 'Before', image: 'word-before.png', aspect: WORD, focus: EDITED, max: 1.2, tag: 'Word', url: 'for-agencies-and-integrators.docx',
                        marks: [[0.415, 0.283, 0.18, 0.03], [0.73, 0.511, 0.18, 0.017], [0.112, 0.525, 0.095, 0.017]], marksAt: 2200,
                    },
                    {
                        label: 'After two edits in Word', image: 'word-after.png', aspect: WORD, focus: EDITED, max: 1.2, tag: 'Word', url: 'for-agencies-and-integrators.docx',
                        marks: [[0.415, 0.283, 0.15, 0.03], [0.685, 0.511, 0.19, 0.017], [0.112, 0.525, 0.075, 0.017]], marksAt: 2600,
                    },
                ],
                say: 'Edit it in Word, LibreOffice or any other editor. Here, two sentences changed in Microsoft Word.',
            },
            {
                type: 'shot', layout: 'wide', chapter: 'Your word processor', clip: 'docx-upload', segments: upload, min: length(upload) + 300,
                tag: 'For agencies and integrators',
                eyebrow: 'Upload and review', head: 'Word’s edits, ready to import',
                camera: [[1800, MENU], [5200, MENU], [6200, DIALOG], [11000, DIALOG], [11500, TOAST]],
                say: [
                    'Upload it again, and the review shows what changed in Word.',
                    { show: 'Import, and the page is up to date.', after: 7300 },
                ],
            },
            {
                type: 'shot', layout: 'wide', chapter: 'New pages', clip: 'docx-new-pages', segments: newPages, min: length(newPages) + 300,
                tag: 'Resources › Import a Word document',
                eyebrow: 'Import as subpages', head: 'One document, several pages',
                camera: [[1500, [0.0, 0.05, 0.55, 0.6]], [6400, [0.0, 0.05, 0.55, 0.6]], [9600, [0.17, 0.0, 0.66, 0.6]], [17700, [0.17, 0.0, 0.66, 0.6]], [18300, 'full']],
                say: [
                    'A long document can become new pages, one per Heading 1.',
                    { show: 'Each part gets a suggested content type, and new pages start hidden.', after: 4800 },
                ],
            },
            {
                type: 'end',
                head: 'Word files, <em>edited in TYPO3.</em>',
                cta: 'View on GitHub',
                url: 'github.com/dirnbauer/typo3-docx',
                install: '<span>$</span> composer require webconsulting/docx-editor',
                note: 'Open source, GPL-2.0 · TYPO3 v14',
                say: 'DOCX Editor. Open source, for TYPO3 v14.',
            },
        ],
    },
};
