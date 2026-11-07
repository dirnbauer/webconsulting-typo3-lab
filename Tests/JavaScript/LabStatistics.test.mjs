import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../../packages/site_package/Resources/Public/JavaScript/lab-statistics.js', import.meta.url), 'utf8');
function run({ url = 'https://typo3-lab.webconsulting.at/blog/', referrer = '', navigator = {}, frame = false } = {}) {
    const scripts = [];
    const window = { location: { href: url } };
    window.self = window;
    window.top = frame ? {} : window;
    const denied = () => { throw new Error('Persistent browser storage must not be accessed'); };
    Object.defineProperties(window, { localStorage: { get: denied }, sessionStorage: { get: denied } });
    const document = { referrer, head: { appendChild: (script) => scripts.push(script) }, createElement: () => ({}) };
    Object.defineProperty(document, 'cookie', { get: denied, set: denied });
    const context = vm.createContext({ window, document, navigator, URL });
    vm.runInContext(source, context);
    return { commands: JSON.parse(JSON.stringify(window._paq || [])), scripts, context };
}
test('anonymous public pages use the separate cookieless Matomo site without consent or browser storage', () => {
    const { commands, scripts } = run();
    for (const expected of [['disableCookies'], ['setDoNotTrack', true], ['disableBrowserFeatureDetection'], ['setSiteId', '11'], ['setRequestMethod', 'POST']]) {
        assert.ok(commands.some((command) => JSON.stringify(command) === JSON.stringify(expected)));
    }
    assert.deepEqual(commands.at(-1), ['trackPageView']);
    assert.equal(scripts.length, 1);
    assert.equal(scripts[0].src, 'https://statistics.webconsulting.at/matomo.js');
    assert.equal(scripts[0].referrerPolicy, 'no-referrer');
    assert.ok(!commands.some(([name]) => /Consent|enableLinkTracking|UserId/.test(name)));
});
test('query, fragment, document title and full referrer cannot leak personal values', () => {
    const { commands } = run({ url: 'https://typo3-lab.webconsulting.at/blog/?utm_source=private#secret', referrer: 'https://source.invalid/person/name?email=private#secret' });
    assert.deepEqual(commands.find(([name]) => name === 'setCustomUrl'), ['setCustomUrl', 'https://typo3-lab.webconsulting.at/blog/']);
    assert.deepEqual(commands.find(([name]) => name === 'setReferrerUrl'), ['setReferrerUrl', 'https://source.invalid']);
    assert.deepEqual(commands.find(([name]) => name === 'setDocumentTitle'), ['setDocumentTitle', '/blog/']);
    assert.ok(!JSON.stringify(commands).includes('private'));
});
test('privacy preferences, frames and nonproduction origins prevent even loading Matomo', () => {
    for (const options of [{ navigator: { doNotTrack: '1' } }, { navigator: { globalPrivacyControl: true } }, { frame: true }, { url: 'http://typo3-lab.webconsulting.at/' }, { url: 'https://webconsulting-typo3-lab.ddev.site/' }]) {
        const result = run(options); assert.equal(result.scripts.length, 0); assert.deepEqual(result.commands, []);
    }
});
test('editor previews, login routes, searches and extension submissions are excluded', () => {
    for (const suffix of ['/typo3/', '/de/login/', '/workos/callback', '/?ADMCMD_prev=1', '/?tx_solr[q]=private', '/?tx_powermail_pi1[field]=private', '/?q=private', '/?eID=monitoring', '/?token=private', '/?email=private', '/?preview=1']) {
        const result = run({ url: 'https://typo3-lab.webconsulting.at' + suffix }); assert.equal(result.scripts.length, 0, suffix);
    }
});
test('duplicate execution records exactly one page view', () => {
    const result = run(); vm.runInContext(source, result.context);
    assert.equal(result.context.window._paq.filter(([name]) => name === 'trackPageView').length, 1);
    assert.equal(result.scripts.length, 1);
});
