/**
 * The films of the feature pages below /features/ (DOCX Editor has its own,
 * docx-editor.mjs). Facts come from the feature definitions
 * (data/features.json, from ShowcaseFeatureDefinitions); the screenshots are
 * the tour screenshots of each page.
 */

import { featureFilm } from './feature-film.mjs';

const film = (definition) => featureFilm({ section: 'features', music: 'calm', ...definition });

export default Object.fromEntries([
    film({
        id: 'desiderio', page: 1073, name: 'Desiderio', music: 'bright',
        accent: '#c4b5fd', palette: ['#7c5cff', '#e08a36', '#2c5a52'],
        hook: { head: 'Every relaunch, <em>the same sections.</em>', say: 'Every relaunch, you build the same heroes, pricing tables and footers again. A new brand colour means another deployment.' },
        title: { sub: '244 finished content elements for TYPO3', key: 'webconsulting/desiderio', say: 'Desiderio gives editors 244 finished content elements, in ten groups.' },
        shots: [
            { image: 'Backend/feature-desiderio-content-wizard-f80cbefc.webp', eyebrow: 'Content wizard', head: 'Ten groups, one wizard', say: 'Editors pick heroes, pricing tables, charts and forms from the content wizard.' },
            { image: 'Backend/feature-desiderio-page-module-9d43bf8e.webp', eyebrow: 'Page module', head: 'A preview card for every element', say: 'In the page module, every element shows a preview card, so nobody opens forms to find content.' },
            { image: 'Frontend/frontend-feature-desiderio-theme-presets-f63d31b0.webp', eyebrow: 'Theme presets', head: 'One setting changes the look', say: 'Pick one of 15 theme presets in the site settings, and every page follows without a rebuild.' },
            { image: 'Frontend/frontend-feature-desiderio-chapter-pricing-c1cd90b1.webp', eyebrow: 'Per page tree', head: 'A preset for each page tree', say: 'Each page tree can have its own preset. This pricing chapter runs in Midnight.' },
        ],
        end: { head: 'Finished pages, <em>without template work.</em>', cta: 'See all elements', url: 'typo3-lab.webconsulting.at/content-types/', install: 'composer require webconsulting/desiderio', note: 'Innesto turns shadcn components into new elements in one command', say: 'Desiderio. Browse all 244 elements in the lab.' },
    }),
    film({
        id: 'astryx', page: 2079, name: 'Astryx', music: 'bright',
        accent: '#93c5fd', palette: ['#0866ff', '#c7863f', '#5a2b6a'],
        hook: { head: 'Design systems <em>ship as React.</em>', say: 'Most design systems ship as React components, with a Node build and JavaScript on every page.' },
        title: { sub: 'Meta’s open-source design system, rendered by Fluid', key: 'webconsulting/astryx-typo3', say: 'Astryx brings Meta’s open-source design system to TYPO3, with 250 elements and 25 themes.' },
        shots: [
            { image: 'Frontend/frontend-feature-astryx-themes-ec012da4.webp', eyebrow: 'Themes', head: 'Six of 25 themes', say: 'Each theme changes fonts, colours and corners. Matcha even sets headings in handwriting.' },
            { image: 'Backend/feature-astryx-page-theme-field-f07df322.webp', eyebrow: 'Page theme', head: 'A theme for a page and its subpages', say: 'A field in the page properties sets the theme for a page and everything below it.' },
            { image: 'Frontend/frontend-feature-astryx-pricing-matcha-7e6d0d04.webp', eyebrow: 'Chapter page', head: 'Pricing in Matcha', say: 'Here, the Plans and Pricing chapter runs in Matcha.' },
            { image: 'Frontend/frontend-feature-astryx-dark-e5307387.webp', eyebrow: 'Dark mode', head: 'Every theme, light and dark', say: 'Colour scheme and theme switch independently, so every theme also works in dark mode.' },
        ],
        end: { head: 'Meta’s design system, <em>on TYPO3.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/astryx-typo3', install: 'composer require webconsulting/astryx-typo3', note: 'Fluid renders on the server · contrast checked against WCAG 2.2 AA', say: 'Astryx for TYPO3. Fluid on the server, with no build step.' },
    }),
    film({
        id: 'blog', page: 1072, name: 'TYPO3 Blog',
        accent: '#f0c38e', palette: ['#c7863f', '#2c5a52', '#3a2f6a'],
        hook: { head: 'A blog that feels <em>like another tool.</em>', say: 'Many blog add-ons keep posts in their own tables, with their own editor and their own rules.' },
        title: { sub: 'Posts are pages, styled by Desiderio', key: 't3g/blog', say: 'The TYPO3 Blog extension turns pages into posts, and Desiderio gives them your theme.' },
        shots: [
            { image: 'Backend/feature-blog-page-module-7d57906f.webp', eyebrow: 'Page module', head: 'Posts are pages', say: 'Editors write posts in the page module, with the content elements they know.' },
            { image: 'Backend/feature-blog-posts-module-62a80ef2.webp', eyebrow: 'Posts module', head: 'All posts in one list', say: 'The Posts module lists every post with author, categories, tags and date.' },
            { image: 'Frontend/frontend-feature-blog-post-ce13ec7b.webp', eyebrow: 'Post', head: 'Metadata and a featured image', say: 'Posts open with the date, the title, a teaser and the reading time.' },
            { image: 'Frontend/frontend-feature-blog-comments-41108afd.webp', eyebrow: 'Comments', head: 'Comments you approve', say: 'Readers comment under the post, and editors can approve each comment first.' },
        ],
        end: { head: 'Blogging with <em>the pages you know.</em>', cta: 'Read the docs', url: 'typo3-lab.webconsulting.at/features/blog/', install: 'composer require t3g/blog', note: 'Two templates, Classic and Modern, in light and dark mode', say: 'TYPO3 Blog, styled by Desiderio.' },
    }),
    film({
        id: 'visual-editor', page: 2080, name: 'Visual Editor Enhancements',
        accent: '#a5b4fc', palette: ['#4a57ff', '#c7863f', '#2c5a52'],
        hook: { head: 'Editing on the page <em>stops at the text.</em>', say: 'The Visual Editor lets editors type on the page. Layout options, links and new elements still mean the backend form.' },
        title: { sub: 'An element library, a popover and link editing', key: 'webconsulting/visual-editor-enhancements', say: 'Visual Editor Enhancements adds what editors miss on the page.' },
        shots: [
            { image: 'Backend/feature-visual-editor-search-cf0a8d71.webp', eyebrow: 'Element library', head: 'Search that forgives typos', say: 'The element library shows live previews, and a typo like testimonal still finds testimonials.' },
            { image: 'Backend/feature-visual-editor-field-chooser-a068f1c8.webp', eyebrow: 'Field settings', head: 'Layout options in a popover', say: 'Layout options open in a popover, grouped like the backend form.' },
            { image: 'Backend/feature-visual-editor-rte-toolbar-3a2e263b.webp', eyebrow: 'Rich text', head: 'A toolbar that stays on screen', say: 'The rich-text toolbar sits in one row above the text.' },
            { image: 'Backend/feature-visual-editor-link-helper-0579b9b7.webp', eyebrow: 'Links', head: 'Edit a link where it sits', say: 'Hover a button, and a chain icon opens the link browser.' },
        ],
        end: { head: 'Edit more <em>on the page.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-visual-editor-enhancements', install: 'composer require webconsulting/visual-editor-enhancements', note: 'Saving, permissions and workspaces stay with the Visual Editor', say: 'Everything saves through the Visual Editor.' },
    }),
    film({
        id: 'records-list', page: 1069, name: 'Records List Types',
        accent: '#86efac', palette: ['#2c5a52', '#4a57ff', '#c7863f'],
        hook: { head: 'One table <em>for every record.</em>', say: 'The Records module shows news, contacts and products as the same rows of text.' },
        title: { sub: 'Cards, tables and teasers in the Records module', key: 'webconsulting/records-list-types', say: 'Records List Types lets editors pick the view that suits each table.' },
        shots: [
            { image: 'Backend/feature-records-list-list-e4a0e6b9.webp', eyebrow: 'List', head: 'The core list, one click away', say: 'The standard list stays one click away, with the columns you choose.' },
            { image: 'Backend/feature-records-list-compact-9beea1fe.webp', eyebrow: 'Compact', head: 'Long lists on one screen', say: 'Compact view keeps long lists on one screen, with sortable columns.' },
            { image: 'Backend/feature-records-list-teaser-9508caa6.webp', eyebrow: 'Teaser', head: 'Title, date and summary', say: 'Teaser view reads like a news list, with title, date and a short excerpt.' },
            { image: 'Backend/feature-records-list-catalog-9c116153.webp', eyebrow: 'Catalogue', head: 'Your own layout from TSconfig', say: 'Catalogue is a custom view, built from TSconfig, a Fluid template and a stylesheet.' },
        ],
        end: { head: 'Find any record <em>faster.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-records-list-types', install: 'composer require webconsulting/records-list-types', note: 'New views in Page TSconfig, without PHP', say: 'Records List Types. New views without PHP.' },
    }),
    film({
        id: 'easy-workspace', page: 1071, name: 'Easy Workspace',
        accent: '#86efac', palette: ['#2c5a52', '#c7863f', '#4a57ff'],
        hook: { head: 'Publishing <em>waits for an admin.</em>', say: 'The Workspaces module lists changes for whole page trees, so editors leave publishing to an admin.' },
        title: { sub: 'Review and publish a page in one click', key: 'webconsulting/webcon-easy-workspace', say: 'Easy Workspace adds a publishing dropdown to the TYPO3 toolbar.' },
        shots: [
            { image: 'Backend/feature-easy-workspace-toolbar-badge-d0e3a646.webp', eyebrow: 'Badge', head: 'Pending changes, per page', say: 'The toolbar counts the pending changes of the page you are on.' },
            { image: 'Backend/feature-easy-workspace-selection-440f2fe1.webp', eyebrow: 'Selection', head: 'Publish what is ready', say: 'Every change starts selected. Untick what is not ready yet.' },
            { image: 'Backend/feature-easy-workspace-languages-23de7971.webp', eyebrow: 'Languages', head: 'No translation left behind', say: 'Changes in other languages get their own section, and publish with the rest.' },
            { image: 'Backend/feature-easy-workspace-published-f5f8a356.webp', eyebrow: 'Published', head: 'The rest waits for later', say: 'Publish, and the unselected changes stay in the workspace.' },
        ],
        end: { head: 'Review and publish <em>in one click.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-webcon-easy-workspace', install: 'composer require webconsulting/webcon-easy-workspace', note: 'Built on the TYPO3 core workspace code', say: 'Easy Workspace, built on TYPO3’s own workspaces.' },
    }),
    film({
        id: 'image-workbench', page: 2082, name: 'Image Workbench',
        accent: '#fca5a5', palette: ['#c7863f', '#ef3138', '#3a2f6a'],
        hook: { head: 'A small crop <em>becomes a detour.</em>', say: 'Editors download an image, crop it in a desktop tool and upload it again.' },
        title: { sub: 'Crop, adjust and generate images inside TYPO3', key: 'webconsulting/image-workbench', say: 'Image Workbench opens images from the file list in a full-page editor.' },
        shots: [
            { image: 'Backend/feature-image-workbench-editor-dfb370fb.webp', eyebrow: 'Editor', head: 'Filters, crop and resize', say: 'Adjust, filter, annotate, crop and resize, right next to the photo.' },
            { image: 'Backend/feature-image-workbench-ai-panel-c9e705fb.webp', eyebrow: 'AI panel', head: 'Describe an image, get a PNG', say: 'Describe a new image, and a PNG arrives in the same folder, through nr-llm.' },
            { image: 'Backend/feature-image-workbench-save-copy-6396bd39.webp', eyebrow: 'Save a copy', head: 'The original stays safe', say: 'Save as copy is the default, so the original is never replaced.' },
        ],
        end: { head: 'Edit images <em>where they live.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-image-workbench', install: 'composer require webconsulting/image-workbench', note: 'Model, budget and costs managed centrally in nr-llm', say: 'Image Workbench for TYPO3.' },
    }),
    film({
        id: 'solr', page: 1074, name: 'Apache Solr',
        accent: '#fdba74', palette: ['#d9411e', '#2c5a52', '#4a57ff'],
        hook: { head: 'Good search, <em>off-brand results.</em>', say: 'Solr finds your content, but its default templates look like another site.' },
        title: { sub: 'Search results in your design, with Desiderio', key: 'apache-solr-for-typo3/solr', say: 'Desiderio’s templates give Solr’s results, filters and page numbers your theme.' },
        shots: [
            { image: 'Frontend/frontend-feature-solr-suggest-85633349.webp', eyebrow: 'Suggestions', head: 'Top results while you type', say: 'After three letters, suggestions and top results appear, usable with the keyboard.' },
            { image: 'Frontend/frontend-feature-solr-active-filter-4d0c9899.webp', eyebrow: 'Filters', head: 'One click narrows the results', say: 'Active filters show as chips, and every option keeps its own count.' },
            { image: 'Frontend/frontend-feature-solr-dark-mode-bfe145bf.webp', eyebrow: 'Dark mode', head: 'The same page in dark mode', say: 'Switch the preset or dark mode, and search follows, with no CSS of its own.' },
        ],
        end: { head: 'Search that <em>looks like your site.</em>', cta: 'Read the docs', url: 'typo3-lab.webconsulting.at/features/solr/', install: 'composer require apache-solr-for-typo3/solr', note: 'Labelled page numbers and filters, ready for screen readers', say: 'Apache Solr, styled by Desiderio.' },
    }),
    film({
        id: 'powermail', page: 1076, name: 'Powermail',
        accent: '#fdba74', palette: ['#e08a36', '#2c5a52', '#4a57ff'],
        hook: { head: 'Forms are where <em>leads get lost.</em>', say: 'Long forms put people off, spam fills the inbox, and many captchas make visitors solve puzzles.' },
        title: { sub: 'Forms your editors build, in your theme', key: 'in2code/powermail', say: 'Powermail lets editors build forms in the backend, and Desiderio styles every field.' },
        shots: [
            { image: 'Backend/feature-powermail-form-overview-7d67b20d.webp', eyebrow: 'Backend', head: 'Every form in one overview', say: 'Editors manage every form, and see the pages that use it.' },
            { image: 'Frontend/frontend-feature-powermail-validation-6627fca3.webp', eyebrow: 'Validation', head: 'Each error at its field', say: 'Each error shows at its field, and the server checks everything again.' },
            { image: 'Frontend/frontend-feature-powermail-final-step-25b84154.webp', eyebrow: 'Final step', head: 'Consent and spam protection', say: 'Long forms split into steps, and Friendly Captcha guards the last one.' },
            { image: 'Frontend/frontend-feature-powermail-dark-mode-ae44bd50.webp', eyebrow: 'Dark mode', head: 'Every field in your theme', say: 'Every field follows the theme preset, in light and dark mode.' },
        ],
        end: { head: 'Forms without <em>a developer.</em>', cta: 'Read the docs', url: 'typo3-lab.webconsulting.at/features/powermail/', install: 'composer require in2code/powermail', note: '19 field partials, one per Powermail field type', say: 'Powermail, styled by Desiderio.' },
    }),
    film({
        id: 'workos', page: 1075, name: 'WorkOS Auth',
        accent: '#a5b4fc', palette: ['#6363f1', '#c7863f', '#2c5a52'],
        hook: { head: 'Every login is <em>another project.</em>', say: 'Customers expect to sign in with their company identity. Building single sign-on yourself takes months.' },
        title: { sub: 'One login for your site and the TYPO3 backend', key: 'webconsulting/workos-auth', say: 'WorkOS Auth adds WorkOS sign-in to the TYPO3 frontend and backend.' },
        shots: [
            { image: 'Frontend/frontend-feature-workos-sign-up-957d9c8f.webp', eyebrow: 'Sign up', head: 'Self-service registration', say: 'New customers create an account with a password, an email code, Google or Microsoft.' },
            { image: 'Backend/feature-workos-backend-login-24a68a62.webp', eyebrow: 'Backend', head: 'The same identity for editors', say: 'Editors use the same identity in the backend. The classic form stays one click away.' },
            { image: 'Backend/feature-workos-setup-assistant-42474903.webp', eyebrow: 'Setup', head: 'Redirect URIs, ready to copy', say: 'The setup assistant lists every redirect URI, each with a copy button.' },
        ],
        end: { head: 'One login, <em>two screens.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/workos', install: 'composer require webconsulting/workos-auth', note: 'Organisation admins invite their own teams', say: 'WorkOS Auth for TYPO3.' },
    }),
    film({
        id: 'x402', page: 1077, slug: 'x402-paywall', name: 'x402 Paywall',
        accent: '#93c5fd', palette: ['#0052ff', '#c7863f', '#2c5a52'],
        hook: { head: 'Small payments <em>cost more than they earn.</em>', say: 'Selling one article or one API call means accounts, card forms and fees. AI agents cannot fill in a checkout at all.' },
        title: { sub: 'Paid content for people and AI agents', key: 'webconsulting/typo3-x402-paywall', say: 'x402 Paywall sells TYPO3 pages and API routes for small USDC amounts.' },
        shots: [
            { image: 'Backend/feature-x402-paywall-page-properties-c15e604b.webp', eyebrow: 'Settings', head: 'A price in the page properties', say: 'Switch the paywall on in the page properties, and set a price.' },
            { image: 'Backend/feature-x402-paywall-simulator-ed8c6250.webp', eyebrow: 'Simulator', head: 'Test without spending money', say: 'The simulator requests a paid page and decodes the HTTP 402 answer.' },
            { image: 'Backend/feature-x402-paywall-dashboard-455adf1b.webp', eyebrow: 'Dashboard', head: 'Revenue and a setup check', say: 'The dashboard shows settled revenue and checks the setup of each site.' },
        ],
        end: { head: 'Get paid by <em>people and agents.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-x402-paywall', install: 'composer require webconsulting/typo3-x402-paywall', note: 'A wallet paywall for browsers, HTTP 402 for agents', say: 'x402 Paywall. People pay from their wallet, agents through HTTP headers.' },
    }),
    film({
        id: 'sg-apicore', page: 1080, name: 'sg_apicore',
        accent: '#86efac', palette: ['#2c5a52', '#4a57ff', '#c7863f'],
        hook: { head: 'Apps want <em>clean JSON.</em>', say: 'A mobile app, a partner portal or an AI agent needs clean JSON. Every hand-built endpoint needs login, limits and docs.' },
        title: { sub: 'A documented REST API for TYPO3', key: 'sgalinski/sg-apicore', say: 'sg_apicore turns TYPO3 content into a REST API, with live Swagger docs.' },
        shots: [
            { image: 'Frontend/frontend-feature-sg-apicore-try-it-7cb535e5.webp', eyebrow: 'Try it', head: 'A request in the browser', say: 'Try a request in the browser, and get JSON with pagination and rate-limit headers.' },
            { image: 'Frontend/frontend-feature-sg-apicore-crud-a65b3316.webp', eyebrow: 'CRUD', head: 'A table, behind a token', say: 'One registration exposes a table for list, read, create, update and delete, behind a token.' },
            { image: 'Backend/feature-sg-apicore-endpoints-7a010ef7.webp', eyebrow: 'Endpoints', head: 'Every endpoint, also as an MCP tool', say: 'The backend lists each endpoint with its login mode, its scopes and its MCP tool.' },
        ],
        end: { head: 'A TYPO3 API <em>partners can read.</em>', cta: 'Read the docs', url: 'typo3-lab.webconsulting.at/features/sg-apicore/', install: 'composer require sgalinski/sg-apicore', note: 'OpenAPI 3.0 docs, generated from PHP attributes', say: 'sg_apicore by sgalinski. Docs that stay in sync with the code.' },
    }),
]);
