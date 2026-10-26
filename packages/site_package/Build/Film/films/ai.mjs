/**
 * The films of the AI pages below /ai/. Facts come from the feature
 * definitions (data/features.json); the screenshots are each page's tour.
 */

import { featureFilm } from './feature-film.mjs';

const film = (definition) => featureFilm({ section: 'ai', music: 'focus', ...definition });

export default Object.fromEntries([
    film({
        id: 'ai-assistant', page: 2084, name: 'AI Assistant',
        accent: '#c4b5fd', palette: ['#7c5cff', '#2c5a52', '#c7863f'],
        hook: { head: 'Answers hide <em>behind many clicks.</em>', say: 'Finding which pages sit below Features, or what the log recorded last night, takes a dozen clicks.' },
        title: { sub: 'Ask TYPO3. Approve every change.', key: 'webconsulting/typo3-ai-assistant', say: 'The AI Assistant answers questions about your site, inside the TYPO3 backend.' },
        shots: [
            { image: 'Backend/feature-ai-assistant-answer-21458ce7.webp', eyebrow: 'Answer', head: 'Answers from the site’s own tools', say: 'It calls the site’s own tools with your permissions, shows the result, then answers in plain words.' },
            { image: 'Backend/feature-ai-assistant-approval-d8e9abaf.webp', eyebrow: 'Approval', head: 'Every write waits for you', say: 'Every write stops and waits. Nothing changes until you approve it.' },
            { image: 'Backend/feature-ai-assistant-changes-9a912e08.webp', eyebrow: 'Changes', head: 'Drafts, linked to the editor', say: 'Approved changes land as drafts in a workspace, listed with the conversation.' },
            { image: 'Backend/feature-ai-assistant-toolbar-5b4526e6.webp', eyebrow: 'Toolbar', head: 'The chat follows you', say: 'The chat stays open across modules, and knows the page you are on.' },
        ],
        end: { head: 'An assistant that <em>asks first.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-ai-assistant', install: 'composer require webconsulting/typo3-ai-assistant', note: 'Built on the MCP Server and the Abilities Registry', say: 'AI Assistant. You approve every change.' },
    }),
    film({
        id: 'nr-llm-manual', page: 1309, name: 'nr-llm',
        accent: '#86efac', palette: ['#2c5a52', '#7c5cff', '#c7863f'],
        hook: { head: 'Every AI feature <em>brings its own setup.</em>', say: 'Each AI extension wants its own provider settings, its own API key and its own bill.' },
        title: { sub: 'One AI setup for every TYPO3 extension', key: 'netresearch/nr-llm', say: 'nr-llm keeps AI providers, models and configurations in one place.' },
        shots: [
            { image: 'Backend/feature-nr-llm-manual-providers-cb3762ff.webp', eyebrow: 'Providers', head: 'Keys stay in the vault', say: 'Keys sit encrypted in nr-vault. The backend shows Configured, never the key.' },
            { image: 'Backend/feature-nr-llm-manual-models-e7876a31.webp', eyebrow: 'Models', head: 'Capabilities and usage', say: 'Each model lists its capabilities, its context size and its usage over 30 days.' },
            { image: 'Backend/feature-nr-llm-manual-configurations-0c860476.webp', eyebrow: 'Configurations', head: 'Named configurations', say: 'Features ask for a named configuration. Change its model, and every feature follows.' },
            { image: 'Backend/feature-nr-llm-manual-test-3e843393.webp', eyebrow: 'Test run', head: 'Test before you assign', say: 'One click tests a configuration before any task uses it.' },
        ],
        end: { head: 'Set up AI <em>once.</em>', cta: 'Read the docs', url: 'typo3-lab.webconsulting.at/ai/nr-llm-manual/', install: 'composer require netresearch/nr-llm', note: 'OpenAI, Anthropic, Gemini, Mistral and Ollama behind one interface', say: 'nr-llm by Netresearch. Usage and cost in one view.' },
    }),
    film({
        id: 'jev', page: 2083, name: 'Jev',
        accent: '#fcd34d', palette: ['#c7863f', '#7c5cff', '#2c5a52'],
        hook: { head: 'One inbox, <em>sorted by hand.</em>', say: 'Every form message lands in one inbox, and a person forwards it to sales, support or accounting.' },
        title: { sub: 'Forms that understand what people write', key: 'webconsulting/webcon-jev', say: 'Jev brings TypeSafe AI’s typed decisions into TYPO3 forms.' },
        shots: [
            { image: 'Frontend/frontend-feature-jev-quality-gate-99e40dbb.webp', eyebrow: 'Spam gate', head: 'Empty messages stay unsent', say: 'Messages with nothing to answer cannot be sent. Borderline ones go through.' },
            { image: 'Backend/feature-jev-routing-tab-a497efed.webp', eyebrow: 'Routing', head: 'Mail finds its team', say: 'A decision picks the receiver, so each message reaches the right team.' },
            { image: 'Backend/feature-jev-playground-2cb22dfe.webp', eyebrow: 'Playground', head: 'Try a decision first', say: 'Try a decision on sample text, and see each answer with its confidence.' },
            { image: 'Backend/feature-jev-run-log-f94168e6.webp', eyebrow: 'Run log', head: 'Every call on record', say: 'The run log records every call, with its latency and its cost.' },
        ],
        end: { head: 'Mail that reaches <em>the right team.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-webcon-jev', install: 'composer require webconsulting/webcon-jev', note: 'Unsure answers fall back to the form’s own receiver', say: 'Jev. When the AI is unsure, the form still works.' },
    }),
    film({
        id: 'skillflow', page: 1081, name: 'Skillflow',
        accent: '#86efac', palette: ['#2c5a52', '#c7863f', '#7c5cff'],
        hook: { head: 'Every reviewer checks <em>something else.</em>', say: 'SEO, accessibility and tone rules live in one person’s head, so every draft gets a different check.' },
        title: { sub: 'Agent skills that review your pages', key: 'webconsulting/skillflow', say: 'Skillflow runs agent skills on pages and workspace stages, and keeps every report.' },
        shots: [
            { image: 'Backend/feature-skillflow-stage-review-35e8369c.webp', eyebrow: 'Stage review', head: 'Reviews in your workflow', say: 'Assign skills to a workspace stage, and every record sent there gets reviewed.' },
            { image: 'Backend/feature-skillflow-run-report-87bdaa2e.webp', eyebrow: 'Report', head: 'A report for every run', say: 'Each run keeps its status, its engine and a Markdown report.' },
            { image: 'Frontend/frontend-feature-skillflow-skill-detail-e6bc304e.webp', eyebrow: 'Skill page', head: 'A page for every skill', say: 'Active skills become a searchable catalogue, with a page for each skill.' },
        ],
        end: { head: 'The same review <em>for every draft.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/skillflow', install: 'composer require webconsulting/skillflow', note: 'Skills sync from git and arrive switched off', say: 'Skillflow. Reports your team can check.' },
    }),
    film({
        id: 'skillspector', page: 2085, name: 'Skillspector',
        accent: '#fca5a5', palette: ['#ef3138', '#2c5a52', '#c7863f'],
        hook: { head: 'Skills are instructions <em>from strangers.</em>', say: 'A skill is text your AI agent follows. One hidden line can leak a key or pipe a script into a shell.' },
        title: { sub: 'Check skills before agents use them', key: 'webconsulting/skillspector', say: 'Skillspector reads every agent skill before your AI does.' },
        shots: [
            { image: 'Backend/feature-skillspector-findings-179ea892.webp', eyebrow: 'Findings', head: 'Evidence, line by line', say: 'Each finding shows its severity, its location, the matched text and what to check.' },
            { image: 'Backend/feature-skillspector-hide-confirm-78f023f9.webp', eyebrow: 'You decide', head: 'Hiding a skill is your call', say: 'Checks never change a skill. An administrator decides what to hide.' },
        ],
        points: {
            head: 'Fifteen rules, <em>built in</em>',
            items: [['Prompt injection', 'Hidden instructions in the skill text.', 'prompt'], ['Exposed keys', 'Secrets and exfiltration endpoints.', 'keys'], ['Risky commands', 'Pipe-to-shell and fork bombs.', 'risky'], ['Licences', 'Shipped code against GPL-2.0-or-later.', 'licen']],
            say: 'Fifteen built-in rules look for prompt injection, exposed keys, risky commands and licence problems.',
        },
        end: { head: 'Read the skill <em>before your agent does.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-skillspector', install: 'composer require webconsulting/skillspector', note: 'Adds NVIDIA SkillSpector’s findings when it is installed', say: 'Skillspector. An administrator always decides.' },
    }),
    film({
        id: 'mcp-server', page: 1070, name: 'MCP Server',
        accent: '#fdba74', palette: ['#d97757', '#2c5a52', '#7c5cff'],
        hook: { head: 'AI answers, <em>retyped by hand.</em>', say: 'Your team works in Claude or Cursor, but TYPO3 content reaches them by copy and paste.' },
        title: { sub: 'Connect your AI assistant to TYPO3', key: 'hn/typo3-mcp-server', say: 'The MCP Server gives Claude, Cursor and Codex structured tools for TYPO3.' },
        shots: [
            { image: 'Backend/feature-mcp-server-tools-6e2c532a.webp', eyebrow: 'Tools', head: 'Every tool, with its rights', say: 'Every tool is listed, with badges for reading, writing and administrator-only.' },
            { image: 'Backend/feature-mcp-server-check-4dc2eef3.webp', eyebrow: 'Checks', head: 'Checks that say what to fix', say: 'Connection checks test the endpoint, OAuth and workspaces, and say what to do next.' },
            { image: 'Backend/feature-mcp-server-draft-00b87879.webp', eyebrow: 'Drafts', head: 'Agent edits wait in a workspace', say: 'On production, every write lands in a workspace, until an editor publishes it.' },
        ],
        end: { head: 'Let AI work <em>safely in TYPO3.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-mcp-server', install: 'composer require hn/typo3-mcp-server', note: 'Claude signs in over OAuth with your backend login', say: 'MCP Server for TYPO3. Drafts before live content.' },
    }),
    film({
        id: 'typo3-abilities', page: 1240, name: 'Abilities Registry',
        accent: '#c4b5fd', palette: ['#7c5cff', '#c7863f', '#2c5a52'],
        hook: { head: 'Every protocol <em>opens another door.</em>', say: 'MCP, REST, webhooks and the CLI each get their own checks, and nobody can say which agent did what.' },
        title: { sub: 'Decide what AI agents may do in TYPO3', key: 'webconsulting/typo3-abilities', say: 'The Abilities Registry describes each action once, with its scopes and its risk.' },
        shots: [
            { image: 'Backend/feature-typo3-abilities-catalogue-6541f127.webp', eyebrow: 'Catalogue', head: 'Everything the site can do', say: 'One catalogue lists everything the site can do, and how to call it on every surface.' },
            { image: 'Backend/feature-typo3-abilities-review-acc94ad6.webp', eyebrow: 'Approval', head: 'People approve risky actions', say: 'High-risk abilities need a person’s approval. Tokens and agents can never approve.' },
            { image: 'Backend/feature-typo3-abilities-traces-878eacc5.webp', eyebrow: 'Traces', head: 'Every attempt on record', say: 'Every attempt is traced, denials included.' },
        ],
        end: { head: 'One rulebook <em>for every surface.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-abilities', install: 'composer require webconsulting/typo3-abilities', note: 'MCP, CLI, REST, webhooks and the backend share one executor', say: 'Abilities Registry. A trace for every attempt.' },
    }),
    film({
        id: 'agent-nexus', page: 2086, name: 'Agent Nexus',
        accent: '#a5b4fc', palette: ['#4a57ff', '#c7863f', '#ef3138'],
        hook: { head: 'Agent protocols, <em>on slides.</em>', say: 'Clients ask what A2A, AG-UI or UCP would mean for their site, and a slide cannot show a real request.' },
        title: { sub: 'Five agent protocols on your own TYPO3', key: 'webconsulting/agent-nexus', say: 'Agent Nexus runs five agent protocols on TYPO3, with endpoints, widgets and a traffic log.' },
        shots: [
            { image: 'Frontend/frontend-feature-agent-nexus-a2ui-form-5edb828e.webp', eyebrow: 'A2UI', head: 'One sentence, a working form', say: 'With A2UI, one sentence becomes a form, drawn from TYPO3’s trusted components.' },
            { image: 'Frontend/frontend-feature-agent-nexus-agui-approval-c2465592.webp', eyebrow: 'AG-UI', head: 'Watch the run, then approve', say: 'With AG-UI, the run streams as typed events and stops for your approval.' },
            { image: 'Frontend/frontend-feature-agent-nexus-ucp-checkout-399edaa8.webp', eyebrow: 'UCP', head: 'A cart that waits for your OK', say: 'A shopping agent builds a priced cart. Nothing is ordered until a person approves.' },
            { image: 'Backend/feature-agent-nexus-traffic-4e27c05e.webp', eyebrow: 'Traffic log', head: 'Every request on record', say: 'The traffic log keeps every request and event, with personal data masked.' },
        ],
        end: { head: 'Agent protocols <em>you can inspect.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-agent-nexus', install: 'composer require webconsulting/agent-nexus', note: 'A2A, AG-UI, A2UI, UCP and AP2 · payments are simulated', say: 'Agent Nexus. Every write waits for a person.' },
    }),
    film({
        id: 'agentation', page: 1079, name: 'Agentation',
        accent: '#fca5a5', palette: ['#ef3138', '#4a57ff', '#c7863f'],
        hook: { head: 'The button <em>on the right.</em>', say: 'Clients describe problems in words, like the button on the right, and your coding agent has to guess the element.' },
        title: { sub: 'Show coding agents what to fix', key: 'webconsulting/agentation', say: 'With Agentation, you click an element and write a note for your coding agent.' },
        shots: [
            { image: 'Backend/feature-agentation-backend-note-30e21524.webp', eyebrow: 'Backend', head: 'Notes on backend screens', say: 'It works on backend screens too, so editors can mark up any module.' },
            { image: 'Backend/feature-agentation-module-e37c0aeb.webp', eyebrow: 'Setup', head: 'Connect your agent in one step', say: 'Copy the MCP configuration, check the status and manage the notes in one module.' },
        ],
        points: {
            head: 'Point, <em>instead of describing</em>',
            items: [['Selector and styles', 'Every note carries the element’s selector and styles.', 'selector'], ['Any MCP agent', 'Claude Code, Cursor, Windsurf or Zed.', 'claude'], ['Invisible to visitors', 'Only backend users who switch it on see it.', 'visitors'], ['Keys on the server', 'Same-origin proxies keep the API key private.', 'keys']],
            say: 'Each note carries the selector, the page and the computed styles to Claude Code or Cursor. Visitors never see the toolbar.',
        },
        end: { head: 'Show your agent <em>what to fix.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-agentation', install: 'composer require webconsulting/agentation', note: 'Claude Code, Cursor or any other MCP agent', say: 'Agentation for TYPO3.' },
    }),
    film({
        id: 'llms-txt', page: 2087, name: 'llms.txt',
        accent: '#86efac', palette: ['#2c5a52', '#c7863f', '#4a57ff'],
        hook: { head: 'AI agents read your site <em>without a map.</em>', say: 'AI agents read your site without a map, and a hand-written llms.txt goes stale with the next new page.' },
        title: { sub: 'Your site, explained to AI agents', key: 'webconsulting/typo3-llms-txt', say: 'Every TYPO3 site and language serves an llms.txt and an agents.md, built from the page tree.' },
        shots: [
            { image: 'Frontend/frontend-feature-llms-txt-agents-md-5e603f90.webp', eyebrow: 'agents.md', head: 'An operating guide for agents', say: 'agents.md lists the machine interfaces your site really has.' },
            { image: 'Frontend/frontend-feature-llms-txt-per-language-052280a8.webp', eyebrow: 'Per language', head: 'One file per language', say: 'Each language gets its own file, with translated titles and URLs.' },
            { image: 'Backend/feature-llms-txt-source-fields-d15e6c67.webp', eyebrow: 'Page fields', head: 'No extra fields to fill', say: 'Page titles and descriptions become the links, so there is nothing extra to maintain.' },
        ],
        end: { head: 'A map <em>for AI agents.</em>', cta: 'View on GitHub', url: 'github.com/dirnbauer/typo3-llms-txt', install: 'composer require webconsulting/typo3-llms-txt', note: 'Every page sends a Link header to its llms.txt', say: 'llms.txt for TYPO3. Always up to date.' },
    }),
]);
