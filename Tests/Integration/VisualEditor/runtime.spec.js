// Regular-runtime Visual Editor suite (README.md). Real Core, FormEngine,
// MariaDB, backend sessions, Core modals and rendered templates; nothing is
// mocked. Run through Build/Scripts/ve-runtime.sh run.
import {test, expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';

const main = process.env.VEE_RUNTIME_ROOT;
if (!main) throw new Error('Set VEE_RUNTIME_ROOT (Build/Scripts/ve-runtime.sh run does).');
const stateDir = main + '/var/ve-integration';
const fixture = JSON.parse(readFileSync(process.env.VEE_RUNTIME_FIXTURE || stateDir + '/fixture.json'));
const base = 'https://webconsulting-typo3-lab.ddev.site';
const helperPath = process.env.VEE_FIXTURE_HELPER || '/tmp/ve-runtime.php';
const axeSource = readFileSync(process.env.VEE_AXE || main + '/node_modules/axe-core/axe.min.js', 'utf8');
const r = fixture.records;
const storage = (role) => `${stateDir}/state-${role}.json`;
const contextOptions = {ignoreHTTPSErrors: true, viewport: {width: 1700, height: 1000}};
const php = ['/opt/homebrew/opt/php@8.5/bin/php', 'php'].find((bin) => { try { execFileSync(bin, ['-v']); return true; } catch { return false; } });
const axeTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const evidence = [];

/** Read-only SQL. With VEE_DB_PORT (published DDEV port) host PHP talks to MariaDB directly. */
function sql(query) {
  if (process.env.VEE_DB_PORT && php) {
    return execFileSync(php, ['-r', '$p=new PDO("mysql:host=127.0.0.1;port=' + Number(process.env.VEE_DB_PORT)
      + ';dbname=db","db","db");foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r)echo implode("\\t",array_map(fn($v)=>$v??"NULL",$r)),"\\n";', '--', query], {encoding: 'utf8'}).trim();
  }
  return execFileSync('ddev', ['mysql', '-N', '-e', query], {cwd: main, encoding: 'utf8'}).trim();
}
function helper(...args) { return execFileSync('ddev', ['exec', 'php', helperPath, ...args.map(String)], {cwd: main, encoding: 'utf8'}); }
const fixturePath = sql(`SELECT slug FROM pages WHERE uid=${Number(fixture.page)}`) + '/';
/** settings <role> <chooser> <workspace> <labels> <layout> <scheme> — fixture identities only. */
function settings(role, {chooser = 'tabs', workspace = 0, labels = 'caption', layout = 'inspector', scheme = 'auto'} = {}) {
  helper('settings', role, chooser, workspace, labels, layout, scheme);
}
const order = (parent) => sql(`SELECT GROUP_CONCAT(title ORDER BY sorting SEPARATOR '|') FROM feature_checklist_items WHERE foreign_table_parent_uid=${parent} AND deleted=0 AND t3ver_wsid=0`);

async function rolePage(browser, role) {
  const context = await browser.newContext({...contextOptions, storageState: storage(role)});
  return context.newPage();
}
test.use({storageState: storage('admin')});

async function open(page, {id = fixture.page, language = 0} = {}) {
  await page.goto(`${base}/typo3/module/web/edit?id=${id}&languages[0]=${language}`, {waitUntil: 'domcontentloaded'});
  await expect(page).not.toHaveURL(/\/typo3\/login/);
  await expect.poll(() => page.frames().some((f) => f.url().includes('editMode=1')), {timeout: 90000}).toBe(true);
  const frame = page.frames().find((f) => f.url().includes('editMode=1'));
  await frame.waitForFunction(() => window.visualEditorEnhancements?.inlineRecordsEnabled, null, {timeout: 60000});
  return frame;
}
/** The wrapper of a record: live uid, or its draft's uid inside a workspace. */
function element(frame, uid) {
  const versions = sql(`SELECT uid FROM tt_content WHERE t3ver_oid=${Number(uid)} AND deleted=0`).split('\n').filter(Boolean);
  return frame.locator([uid, ...versions].map((id) => `ve-content-element[uid="${id}"]`).join(', ')).first();
}
async function revealBar(frame, uid) {
  const e = element(frame, uid);
  // The bar sits above the element; keep it clear of desiderio's sticky site header.
  await e.evaluate((el) => window.scrollBy(0, el.getBoundingClientRect().top - 300));
  await e.hover({position: {x: 40, y: 20}});
  await expect(e.locator('.action-bar')).toBeVisible();
  return e;
}
/** Opens the entries inspector of an element through its bar's Entries button. */
async function entries(frame, uid) {
  const e = await revealBar(frame, uid);
  await e.locator('[data-ve-enhancement="inline-records"]').click();
  const c = frame.locator('ve-field-chooser');
  await expect.poll(() => c.evaluate((x) => x.open && !x.loading && x.fields.some((f) => f.type === 'inline'))).toBe(true);
  return c;
}
/** The inspector re-renders after every operation (reload, hint lines): wait until it is idle. */
async function settle(c) {
  await expect.poll(() => c.evaluate(async (x) => { await x.updateComplete; return !x.loading && !x.busy; })).toBe(true);
  await c.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
}
const row = (c, uid) => c.locator(`li.entryItem[data-ve-row="${uid}"]`);
const control = (c, uid, name) => row(c, uid).locator(`[data-control="${name}"]`);
function nextIntent(page, action) {
  return page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('visual-editor/save')
    && res.request().postDataJSON()?.inlineRecord?.action === action);
}
/** Collection payload of a record as the edit frame sees it (no cache). */
function payload(frame, uid) {
  return frame.evaluate(async (uid) => {
    const cache = await import('@webconsulting/visual-editor-enhancements/Shared/field-options-cache.js');
    cache.clearFieldOptionsCache?.();
    const data = await cache.fetchFieldOptions('tt_content', uid);
    return data.fields.filter((f) => f.type === 'inline');
  }, uid);
}
/** A server intent through the shipped builder (identities only from the payload). */
function intent(frame, uid, fieldName, action, options = {}, revision = null) {
  return frame.evaluate(async ({uid, fieldName, action, options, revision}) => {
    const cache = await import('@webconsulting/visual-editor-enhancements/Shared/field-options-cache.js');
    const {collectionIntent} = await import('@webconsulting/visual-editor-enhancements/Shared/inline-record-actions.js');
    cache.clearFieldOptionsCache?.();
    const field = (await cache.fetchFieldOptions('tt_content', uid)).fields.find((f) => f.name === fieldName);
    const body = collectionIntent(revision ? {...field, revision} : field, action, options);
    const res = await fetch(window.TYPO3.settings.ajaxUrls.visual_editor_save, {method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Request-Token': window.veInfo.token}, body: JSON.stringify(body)});
    return {status: res.status, data: await res.json()};
  }, {uid, fieldName, action, options, revision});
}
const changes = (frame) => frame.evaluate(async () => (await import('@typo3/visual-editor/Frontend/stores/data-handler-store')).dataHandlerStore.changesCount);
async function axe(target, include, state) {
  await target.evaluate(axeSource);
  const violations = await target.evaluate(async ({include, tags}) => {
    const result = await window.axe.run({include}, {runOnly: {type: 'tag', values: tags}});
    return result.violations.map((v) => ({id: v.id, impact: v.impact, nodes: v.nodes.slice(0, 4).map((n) => n.target.flat().join(' > '))}));
  }, {include, tags: axeTags});
  evidence.push({axe: state, violations});
  expect(violations, `axe ${state}`).toEqual([]);
}
async function confirmModal(page) {
  const modal = page.locator('typo3-backend-modal').last();
  await expect(modal.locator('dialog')).toBeVisible();
  return modal;
}

test.afterAll(() => { console.log('EVIDENCE ' + JSON.stringify(evidence)); });

test('labels: caption reveals the current name on hover, focus and click; the bar width stays; vendor re-render keeps them', async ({page}) => {
  const frame = await open(page);
  const e = await revealBar(frame, r.NEWcheck);
  const bar = e.locator('.action-bar');
  await expect(bar).toHaveAttribute('data-ve-labels', 'caption');
  const after = (button) => button.evaluate((b) => { const s = getComputedStyle(b, '::after'); return {content: s.content, opacity: s.opacity, size: s.fontSize}; });
  const width = () => bar.evaluate((b) => b.getBoundingClientRect().width);
  const rest = await width();
  const add = bar.locator('[data-ve-enhancement="inline-add"]');
  expect((await after(add)).opacity).toBe('0');
  await add.hover();
  await expect(add).toHaveAttribute('data-ve-label-current', '');
  await expect.poll(() => after(add)).toMatchObject({content: '"Add entry"', opacity: '1', size: '11px'});
  expect(await width()).toBe(rest);
  // WCAG 1.4.13: moving onto the caption row (inside the bar) keeps the caption.
  const box = await bar.boundingBox();
  const addBox = await add.boundingBox();
  await page.mouse.move(addBox.x + 4, box.y + box.height - 4, {steps: 4});
  await expect(add).toHaveAttribute('data-ve-label-current', '');
  await axe(frame, [{fromShadowDom: [`ve-content-element[uid="${r.NEWcheck}"]`, '.action-bar']}], 'bar hovered (caption)');
  // Keyboard focus reveals like hover.
  const vendor = bar.locator('.button').first();
  await vendor.focus();
  await page.keyboard.press('Tab');
  const focused = bar.locator('.button:focus-visible');
  await expect(focused).toHaveCount(1);
  await expect.poll(async () => (await after(focused)).opacity).toBe('1');
  const vendorAfter = await after(bar.locator('button.button[role="switch"]'));
  expect(vendorAfter.content).not.toBe('none');
  // A click pins (Settings opens the field chooser; Escape closes it again).
  await bar.locator('[data-ve-enhancement="field-chooser"]').click();
  await expect(bar).toHaveAttribute('data-ve-labels-pinned', '');
  await page.keyboard.press('Escape');
  // The vendor hide toggle re-renders the wrapper: labels and injected buttons survive, toggling back leaves no change.
  const toggle = bar.locator('button.button[role="switch"]');
  await toggle.click();
  expect(await changes(frame)).toBe(1);
  for (const kind of ['field-chooser', 'inline-records', 'inline-add']) await expect(bar.locator(`[data-ve-enhancement="${kind}"]`)).toHaveCount(1);
  await expect(bar).toHaveAttribute('data-ve-labels', 'caption');
  await toggle.click();
  expect(await changes(frame)).toBe(0);
});

test('labels: inline, stacked and icons modes from the user setting', async ({page}) => {
  try {
    for (const labels of ['inline', 'stacked', 'icons']) {
      settings('admin', {labels});
      const frame = await open(page);
      const e = await revealBar(frame, r.NEWcheck);
      const bar = e.locator('.action-bar');
      await expect(bar).toHaveAttribute('data-ve-labels', labels);
      const add = bar.locator('[data-ve-enhancement="inline-add"]');
      await page.waitForTimeout(300);
      const style = await add.evaluate((b) => { const s = getComputedStyle(b, '::after'); return {content: s.content, opacity: s.opacity, size: s.fontSize}; });
      if (labels === 'icons') {
        expect(style.content === 'none' || style.content === 'normal' || style.opacity === '0').toBe(true);
        await expect(add).toHaveAttribute('title', /.+/);
      } else {
        expect(style).toMatchObject({content: '"Add entry"', opacity: '1', size: labels === 'stacked' ? '10px' : '11px'});
        await expect(add).not.toHaveAttribute('title', /.+/);
      }
      evidence.push({labels, style});
    }
  } finally { settings('admin'); }
});

test('Add entry: present from first render, creates hidden, focuses the new editable; eye + title staged, Save now publishes', async ({page}) => {
  const frame = await open(page);
  // No hover yet: the collection index rendered both buttons with the bar.
  const add = element(frame, r.NEWcheck).locator('[data-ve-enhancement="inline-add"]');
  await expect(add).toHaveCount(1);
  await expect(add).not.toHaveAttribute('hidden', '');
  await expect(element(frame, r.NEWcheck).locator('[data-ve-enhancement="inline-records"]')).not.toHaveAttribute('hidden', '');
  await expect(add).not.toHaveAttribute('aria-haspopup', /.*/);
  await revealBar(frame, r.NEWcheck);
  const response = nextIntent(page, 'create');
  await add.click();
  const data = await (await response).json();
  expect(data.success, JSON.stringify(data)).toBe(true);
  const uid = Number(data.created?.uid ?? data.uid);
  expect(sql(`SELECT hidden FROM feature_checklist_items WHERE uid=${uid}`)).toBe('1');
  const c = frame.locator('ve-field-chooser');
  await expect(row(c, uid)).toBeVisible();
  await expect(c.locator('.popover')).toHaveAttribute('data-layout', 'inspector');
  await expect(c.locator('.popover')).toContainText('New entries start hidden');
  await expect(row(c, uid).locator('.entryBadge.isHidden')).toHaveCount(1);
  // Focus sits in the new entry's own editable on the page (refreshed element).
  await expect.poll(() => frame.evaluate((uid) => !!document.querySelector(`ve-editable-text[uid="${uid}"], ve-editable-rich-text[uid="${uid}"]`)?.matches(':focus-within'), uid)).toBe(true);
  expect(await frame.evaluate((uid) => document.querySelector(`ve-editable-text[uid="${uid}"]`)?.hasAttribute('data-ve-entry-hidden'), uid)).toBe(true);
  // The panel stays open while the editor works on the page.
  await frame.locator(`ve-editable-text[uid="${r.NEWalpha}"]`).first().click();
  await expect(row(c, uid)).toBeVisible();
  // Eye twice = no change; then visible + title, staged.
  const eye = control(c, uid, 'eye');
  await expect(eye).toHaveAttribute('aria-checked', 'false');
  await eye.click(); await eye.click();
  expect(await changes(frame)).toBe(0);
  await eye.click();
  await expect(eye).toHaveAttribute('aria-checked', 'true');
  expect(await frame.evaluate((uid) => document.querySelector(`ve-editable-text[uid="${uid}"]`)?.hasAttribute('data-ve-entry-hidden'), uid)).toBe(false);
  await row(c, uid).locator('input.entryTitle').fill('Bar entry made visible');
  await expect(row(c, uid).locator('.isPending')).toHaveCount(1);
  // A staged row holds only its own Remove; Save now saves through the vendor.
  await expect(control(c, uid, 'remove')).toHaveAttribute('aria-disabled', 'true');
  await expect(control(c, uid, 'after')).not.toHaveAttribute('aria-disabled', 'true');
  await c.locator('.saveNow').click();
  await expect.poll(() => sql(`SELECT CONCAT(hidden,':',title) FROM feature_checklist_items WHERE uid=${uid}`)).toBe('0:Bar entry made visible');
  await expect.poll(async () => (await (await page.request.get(base + fixturePath)).text()).includes('Bar entry made visible')).toBe(true);
  evidence.push({addEntry: uid});
});

test('rows: add after inserts between rows, move up/down and Alt+Arrow reorder, the page follows; a stale move writes nothing', async ({page}) => {
  const frame = await open(page);
  const c = await entries(frame, r.NEWcheck);
  const response = nextIntent(page, 'create');
  await control(c, r.NEWalpha, 'after').click();
  const created = Number((await (await response).json()).uid);
  await settle(c);
  const titles = () => sql(`SELECT GROUP_CONCAT(uid ORDER BY sorting) FROM feature_checklist_items WHERE foreign_table_parent_uid=${r.NEWcheck} AND deleted=0 AND t3ver_wsid=0`).split(',').map(Number);
  let uids = titles();
  expect(uids.indexOf(created)).toBe(uids.indexOf(r.NEWalpha) + 1);
  expect(uids.indexOf(r.NEWbeta)).toBeGreaterThan(uids.indexOf(created));
  // First row cannot move up.
  await expect(control(c, uids[0], 'up')).toHaveAttribute('aria-disabled', 'true');
  // Gamma to the top: buttons, then Alt+ArrowUp on the row.
  const steps = titles().indexOf(r.NEWgamma);
  for (let i = 0; i < steps; i++) {
    const moved = nextIntent(page, 'move');
    if (i % 2 === 0) await control(c, r.NEWgamma, 'up').click();
    else { await control(c, r.NEWgamma, 'eye').focus(); await page.keyboard.press('Alt+ArrowUp'); }
    const movedResponse = await moved;
    const movedData = await movedResponse.json();
    expect(movedResponse.request().postDataJSON().inlineRecord.childUid).toBe(r.NEWgamma);
    expect(movedData.success).toBe(true);
    await expect.poll(() => titles().indexOf(r.NEWgamma)).toBe(steps - i - 1);
    await settle(c);
  }
  await expect(c.locator('.notice')).toContainText('Moved to position 1');
  const html = await (await page.request.get(base + fixturePath)).text();
  expect(html.indexOf('Gamma entry')).toBeGreaterThan(-1);
  expect(html.indexOf('Gamma entry')).toBeLessThan(html.indexOf('Alpha entry'));
  // Down again once.
  await settle(c);
  const down = nextIntent(page, 'move');
  await control(c, r.NEWgamma, 'down').click();
  expect((await (await down).json()).success).toBe(true);
  uids = titles();
  const before = sql(`SELECT GROUP_CONCAT(CONCAT(uid,':',sorting,':',tstamp)) FROM feature_checklist_items WHERE foreign_table_parent_uid=${r.NEWcheck}`);
  const stale = await intent(frame, r.NEWcheck, 'desiderio_featurechecklist_items', 'move', {childUid: r.NEWbeta, afterUid: null}, 'stale-revision');
  expect(stale.status).toBe(409);
  expect(sql(`SELECT GROUP_CONCAT(CONCAT(uid,':',sorting,':',tstamp)) FROM feature_checklist_items WHERE foreign_table_parent_uid=${r.NEWcheck}`)).toBe(before);
  evidence.push({order: order(r.NEWcheck)});
});

test('remove: counted confirm names the translation; Cancel, Escape and dismiss never write; confirm cascades like FormEngine', async ({page}) => {
  const frame = await open(page);
  const c = await entries(frame, r.NEWtrans);
  const victim = r.NEWtransthree;
  const translation = Number(sql(`SELECT uid FROM feature_checklist_items WHERE l10n_parent=${victim} AND sys_language_uid=1 AND deleted=0`));
  expect(translation).toBeGreaterThan(0);
  const remove = control(c, victim, 'remove');
  let posts = 0;
  page.on('request', (q) => { if (q.method() === 'POST' && q.url().includes('visual-editor/save')) posts++; });
  for (const way of ['Cancel', 'Escape', 'dismiss']) {
    await remove.click();
    const modal = await confirmModal(page);
    await expect(modal).toContainText(/Also removes 1 translations? \(/);
    await expect(modal.getByRole('button', {name: 'Cancel', exact: true})).toBeFocused();
    if (way === 'Cancel') {
      await axe(page, ['typo3-backend-modal'], 'Core modal open');
      await modal.getByRole('button', {name: 'Cancel', exact: true}).click();
    } else if (way === 'Escape') await page.keyboard.press('Escape');
    else await modal.locator('.t3js-modal-close').click();
    await expect(page.locator('typo3-backend-modal')).toHaveCount(0);
    expect(sql(`SELECT CONCAT(a.deleted, b.deleted) FROM feature_checklist_items a, feature_checklist_items b WHERE a.uid=${victim} AND b.uid=${translation}`)).toBe('00');
    expect(posts).toBe(0);
  }
  await remove.click();
  await (await confirmModal(page)).getByRole('button', {name: 'Remove', exact: true}).click();
  await expect.poll(() => sql(`SELECT CONCAT(a.deleted, b.deleted) FROM feature_checklist_items a, feature_checklist_items b WHERE a.uid=${victim} AND b.uid=${translation}`)).toBe('11');
  expect(posts).toBe(1);
  evidence.push({cascade: {victim, translation}});
});

test('translations: a new default entry is offered to Sync, localize adds it; the German frame adds a translation-only entry', async ({page}) => {
  const frame = await open(page);
  let c = await entries(frame, r.NEWtrans);
  const response = nextIntent(page, 'create');
  await c.locator('.addEntry').click();
  const created = Number((await (await response).json()).uid);
  await settle(c);
  const german = await payload(frame, r.NEWtrans);
  const language = german[0].translationLanguages.find((l) => Number(l.id) === 1);
  expect(language).toMatchObject({missing: 1, canSync: true});
  await expect(row(c, created).locator('.entryBadge.isMuted')).toContainText('Not translated here');
  const menu = c.locator('ve-row-menu.syncMenu');
  await menu.locator('.trigger').click();
  const sync = nextIntent(page, 'sync');
  await menu.getByRole('menuitem').filter({hasText: language.title}).first().click();
  const synced = await (await sync).json();
  expect(synced.success, JSON.stringify(synced)).toBe(true);
  expect(Number(sql(`SELECT COUNT(*) FROM feature_checklist_items WHERE l10n_parent=${created} AND sys_language_uid=1 AND deleted=0`))).toBe(1);
  expect((await payload(frame, r.NEWtrans))[0].translationLanguages.find((l) => Number(l.id) === 1).missing).toBe(0);
  // Connected translation (FormEngine parity): a German-only entry.
  const de = await open(page, {language: 1});
  c = await entries(de, fixture.translated.trans);
  await expect(c.locator('.contextStrip')).toContainText(/Connected translation|structure also lives/i);
  const own = nextIntent(page, 'create');
  await c.locator('.addEntry').click();
  const only = Number((await (await own).json()).uid);
  expect(sql(`SELECT CONCAT(sys_language_uid,':',l10n_parent,':',foreign_table_parent_uid) FROM feature_checklist_items WHERE uid=${only}`)).toBe(`1:0:${fixture.translated.trans}`);
  await expect(row(c, only)).toContainText('Only in this language');
  await open(page, {language: 0});
  evidence.push({sync: created, translationOnly: only});
});

test('layouts: inspector, tray and anchored render the same row actions at 1280 and 900 px in the real backend; dark backend shadow', async ({page}) => {
  try {
    for (const layout of ['inspector', 'tray', 'anchored']) {
      settings('admin', {layout});
      // 390 px: the component spec (the real backend puts its page-tree overlay over a phone-width frame).
      for (const width of [1280, 900]) {
        await page.setViewportSize({width, height: 900});
        const frame = await open(page);
        const c = await entries(frame, r.NEWcheck);
        await expect(c.locator('.popover')).toHaveAttribute('data-layout', layout);
        const first = c.locator('li.entryItem').first();
        await expect(first.locator('[data-control]')).toHaveCount(5);
        await expect(first.locator('ve-row-menu')).toHaveCount(1);
        const box = await c.locator('.popover').boundingBox();
        // boundingBox() is in top-page coordinates.
        expect(box.x).toBeGreaterThanOrEqual(-1);
        expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize().width + 1);
        if (layout === 'inspector' && width === 1280) await axe(frame, [{fromShadowDom: ['ve-field-chooser', '.popover']}], 'inspector open');
        await page.screenshot({path: `${stateDir}/layout-${layout}-${width}.png`});
        await page.keyboard.press('Escape');
      }
    }
    await page.setViewportSize({width: 1700, height: 1000});
    settings('admin', {scheme: 'dark'});
    const frame = await open(page);
    const c = await entries(frame, r.NEWcheck);
    const shadow = await c.evaluate((x) => {
      const pop = x.renderRoot.querySelector('.popover');
      return {boxShadow: getComputedStyle(pop).boxShadow, token: getComputedStyle(document.documentElement).getPropertyValue('--ve-t3-shadow-dialog').trim(),
        scheme: document.documentElement.style.colorScheme || getComputedStyle(document.documentElement).colorScheme};
    });
    evidence.push({darkShadow: shadow});
    expect(shadow.token).not.toBe('');
    await page.screenshot({path: `${stateDir}/inspector-dark.png`});
    await axe(frame, [{fromShadowDom: ['ve-field-chooser', '.popover']}], 'inspector open (dark)');
  } finally { settings('admin'); await page.setViewportSize({width: 1700, height: 1000}); }
});

test('restricted ve_editor: hidden right decides the eye, unmodifiable child tables stay private, 403 outside the mount', async ({browser}) => {
  const page = await rolePage(browser, 'veEditor');
  const frame = await open(page);
  // Checklist: full rights incl. hidden.
  let c = await entries(frame, r.NEWcheck);
  await expect(control(c, r.NEWalpha, 'eye')).not.toHaveAttribute('aria-disabled', 'true');
  const response = nextIntent(page, 'create');
  await c.locator('.addEntry').click();
  const created = Number((await (await response).json()).uid);
  expect(sql(`SELECT hidden FROM feature_checklist_items WHERE uid=${created}`)).toBe('1');
  await page.keyboard.press('Escape');
  // Tabs: no tabs_items:hidden right -> eye refused with the reason, never a silent drop.
  c = await entries(frame, r.NEWtabs);
  const eye = control(c, r.NEWone, 'eye');
  await expect(eye).toHaveAttribute('aria-disabled', 'true');
  await expect(row(c, r.NEWone).locator('.actionReason')).toContainText('restricted');
  const tabs = await payload(frame, r.NEWtabs);
  expect(tabs[0].children.every((child) => child.canHide === false && child.hideReason === 'rights')).toBe(true);
  await page.keyboard.press('Escape');
  // Legal links: child table not modifiable -> no entries, no counts.
  const legal = await payload(frame, r.NEWlegal);
  evidence.push({veEditorLegal: legal.map((f) => ({name: f.name, canCreate: f.canCreate, reason: f.createReason, children: f.children?.length ?? null, uids: f.uids ?? null}))});
  expect(legal.every((f) => !f.children?.length && !f.uids?.length && f.canCreate !== true)).toBe(true);
  // Not in the editor's collection index: neither Entries nor Add entry is offered.
  const e = await revealBar(frame, r.NEWlegal);
  await expect(e.locator('[data-ve-enhancement="inline-add"]')).toBeHidden();
  await expect(e.locator('[data-ve-enhancement="inline-records"]')).toBeHidden();
  expect(await frame.evaluate(() => Object.keys(window.visualEditorEnhancements.collectionTypes.tt_content ?? {}))).not.toContain('desiderio_legallinks');
  const denied = await frame.evaluate(async () => {
    const url = new URL(location.href);
    url.searchParams.set('veFieldOptions', '1'); url.searchParams.set('table', 'tt_content'); url.searchParams.set('uid', '200210');
    return (await fetch(url, {headers: {'X-Request-Token': window.veInfo.token}})).status;
  });
  expect(denied).toBe(403);
  await axe(frame, [{fromShadowDom: [`ve-content-element[uid="${r.NEWlegal}"]`, '.action-bar']}], 'restricted bar');
  await page.context().close();
});

test('workspace: existing drafts take create/remove without live changes for admin, editor and ve_editor', async ({browser}) => {
  helper('version');
  const live = () => sql(`SELECT * FROM tt_content WHERE pid=${fixture.page} AND t3ver_wsid=0 ORDER BY uid`) + sql(`SELECT * FROM feature_checklist_items WHERE pid=${fixture.page} AND t3ver_wsid=0 ORDER BY uid`);
  const before = live();
  for (const role of ['admin', 'editor', 'veEditor']) {
    settings(role, {chooser: 'sections', workspace: fixture.workspace});
    const page = await rolePage(browser, role);
    try {
      const f = await open(page);
      expect(await f.evaluate(() => window.visualEditorEnhancements.workspaceId)).toBe(fixture.workspace);
      const c = await entries(f, r.NEWcheck);
      await expect(c.locator('.contextChip.isWorkspace')).toBeVisible();
      for (let i = 0; i < 2; i++) {
        const response = nextIntent(page, 'create');
        await c.locator('.addEntry').click();
        const uid = Number((await (await response).json()).uid);
        expect(sql(`SELECT t3ver_wsid FROM feature_checklist_items WHERE uid=${uid}`)).toBe(String(fixture.workspace));
        await settle(c);
        await control(c, uid, 'remove').click();
        await (await confirmModal(page)).getByRole('button', {name: role === 'editor' ? 'Entfernen' : 'Remove', exact: true}).click();
        await expect.poll(() => sql(`SELECT COUNT(*) FROM feature_checklist_items WHERE uid=${uid} AND deleted=0`)).toBe('0');
        await settle(c);
      }
      expect(live()).toBe(before);
    } finally { settings(role); await page.context().close(); }
  }
});

test('workspace first version: refused under firstVersion = 0, created under 1 with only sorting/tstamp live deltas', async ({page}) => {
  const masked = (table) => sql(`SELECT GROUP_CONCAT(CONCAT(CHAR(96),COLUMN_NAME,CHAR(96)) ORDER BY ORDINAL_POSITION) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='db' AND TABLE_NAME='${table}' AND COLUMN_NAME NOT IN ('sorting','tstamp')`);
  const snapshot = () => ({
    parent: sql(`SELECT ${masked('tt_content')} FROM tt_content WHERE uid=${r.NEWfirst}`),
    children: sql(`SELECT ${masked('feature_checklist_items')} FROM feature_checklist_items WHERE foreign_table_parent_uid=${r.NEWfirst} AND t3ver_wsid=0 ORDER BY uid`),
    order: sql(`SELECT GROUP_CONCAT(uid ORDER BY sorting) FROM feature_checklist_items WHERE foreign_table_parent_uid=${r.NEWfirst} AND t3ver_wsid=0 AND deleted=0`),
    sorting: sql(`SELECT GROUP_CONCAT(CONCAT(uid,':',sorting,':',tstamp) ORDER BY uid) FROM feature_checklist_items WHERE foreign_table_parent_uid=${r.NEWfirst} AND t3ver_wsid=0`),
    parentFull: sql(`SELECT * FROM tt_content WHERE uid=${r.NEWfirst}`),
  });
  const drafts = () => Number(sql(`SELECT COUNT(*) FROM tt_content WHERE t3ver_oid=${r.NEWfirst} AND t3ver_wsid=${fixture.workspace}`));
  settings('admin', {workspace: fixture.workspace});
  try {
    helper('firstversion', 0);
    let f = await open(page);
    let c = await entries(f, r.NEWfirst);
    await expect(c.locator('.contextHint')).toContainText('first draft');
    const before = snapshot();
    // The payload already refuses it: Add stays focusable, aria-disabled, with the sentence.
    await expect(c.locator('.addEntry')).toHaveAttribute('aria-disabled', 'true');
    await expect(c.locator('.collectionNotice')).toContainText('this site has not enabled that here');
    expect((await payload(f, r.NEWfirst))[0]).toMatchObject({canCreate: false, createReason: 'firstVersion', workspace: {firstVersion: true}});
    // And the server refuses a forged intent with 409 firstVersion, writing nothing.
    const no = await intent(f, r.NEWfirst, 'desiderio_featurechecklist_items', 'create');
    expect([no.status, no.data.reason]).toEqual([409, 'firstVersion']);
    const noMove = await intent(f, r.NEWfirst, 'desiderio_featurechecklist_items', 'move', {childUid: r.NEWfirsttwo, afterUid: null});
    expect([noMove.status, noMove.data.reason]).toEqual([409, 'firstVersion']);
    expect(drafts()).toBe(0);
    expect(snapshot()).toEqual(before);
    helper('firstversion', 1);
    f = await open(page);
    c = await entries(f, r.NEWfirst);
    const allowed = nextIntent(page, 'create');
    await c.locator('.addEntry').click();
    const yes = await (await allowed).json();
    expect(yes.success, JSON.stringify(yes)).toBe(true);
    expect(yes.workspace).toMatchObject({id: fixture.workspace, firstVersion: true});
    await expect(c.locator('.notice')).toContainText('Draft created');
    const after = snapshot();
    expect(drafts()).toBe(1);
    expect(after.parentFull).toBe(before.parentFull);
    expect(after.parent).toBe(before.parent);
    expect(after.children).toBe(before.children);
    expect(after.order).toBe(before.order);
    evidence.push({firstVersion: {sortingBefore: before.sorting, sortingAfter: after.sorting, created: yes.uid}});
  } finally { helper('firstversion', 0); settings('admin'); }
});

test('staged parent text does not block structure; the page updates after Save', async ({page}) => {
  const frame = await open(page);
  const header = frame.locator(`ve-editable-text[uid="${r.NEWcheck}"][field="header"]`).getByRole('textbox');
  await header.fill('Pending integration header');
  const c = await entries(frame, r.NEWcheck);
  await expect(c.locator('.addEntry')).not.toHaveAttribute('aria-disabled', 'true');
  const response = nextIntent(page, 'create');
  await c.locator('.addEntry').click();
  const uid = Number((await (await response).json()).uid);
  await expect(row(c, uid)).toBeVisible();
  await expect(c.locator('.notice')).toContainText(/after you save|Refresh/i);
  expect(sql(`SELECT header FROM tt_content WHERE uid=${r.NEWcheck}`)).toBe('VEE disposable checklist');
  const save = frame.parentFrame().locator('ve-backend-save-button');
  await save.click();
  await expect.poll(() => sql(`SELECT header FROM tt_content WHERE uid=${r.NEWcheck}`)).toBe('Pending integration header');
  await header.fill('VEE disposable checklist');
  await save.click();
  await expect.poll(() => sql(`SELECT header FROM tt_content WHERE uid=${r.NEWcheck}`)).toBe('VEE disposable checklist');
});

test('1.5 kept: hidden entry edited in Core FormEngine stays hidden', async ({page}) => {
  const frame = await open(page);
  const c = await entries(frame, r.NEWcheck);
  const response = nextIntent(page, 'create');
  await c.locator('.addEntry').click();
  const uid = Number((await (await response).json()).uid);
  await settle(c);
  const menu = row(c, uid).locator('ve-row-menu');
  await menu.locator('.trigger').click();
  await menu.getByRole('menuitem', {name: 'Edit in form'}).click();
  const modal = page.locator('typo3-backend-modal').last();
  const form = modal.frameLocator('iframe');
  await form.locator(`textarea[name="data[feature_checklist_items][${uid}][title]"]`).fill('Edited in actual FormEngine');
  await form.locator('button[name="_savedok"]').click();
  await expect(form.locator('.contextual-record-edit-saved-indicator')).toBeVisible();
  expect(sql(`SELECT CONCAT(hidden,':',title) FROM feature_checklist_items WHERE uid=${uid}`)).toBe('1:Edited in actual FormEngine');
  await form.locator('.t3js-contextual-close').click();
  await expect(modal).toHaveCount(0);
});

test('1.5 kept: nested settings and leaf create/remove retain their owner', async ({page}) => {
  const frame = await open(page);
  const c = await entries(frame, r.NEWtable);
  const menu = row(c, r.NEWrow).locator('ve-row-menu');
  await menu.locator('.trigger').click();
  await menu.getByRole('menuitem', {name: 'Item settings'}).click();
  await expect(c.getByRole('button', {name: 'Back', exact: true})).toBeVisible();
  await c.locator('.collectionShortcut').first().click();
  const response = nextIntent(page, 'create');
  await c.locator('.addEntry').click();
  const uid = Number((await (await response).json()).uid);
  expect(sql(`SELECT foreign_table_parent_uid FROM data_table_cells WHERE uid=${uid}`)).toBe(String(r.NEWrow));
  await settle(c);
  await control(c, uid, 'remove').click();
  await (await confirmModal(page)).getByRole('button', {name: 'Remove', exact: true}).click();
  await expect.poll(() => sql(`SELECT deleted FROM data_table_cells WHERE uid=${uid}`)).toBe('1');
});

test('1.5 kept: the German broad editor with the chooser disabled creates hidden entries and is refused outside its mount', async ({browser}) => {
  settings('editor', {chooser: 'disabled'});
  const page = await rolePage(browser, 'editor');
  try {
    const f = await open(page);
    expect(await f.evaluate(() => window.visualEditorEnhancements.fieldChooserMode)).toBe('disabled');
    const e = element(f, r.NEWcheck);
    await expect(e.locator('[data-ve-enhancement="field-chooser"]')).toHaveCount(0);
    const c = await entries(f, r.NEWcheck);
    await expect(c.locator('.addEntry')).toContainText('hinzufügen');
    const response = nextIntent(page, 'create');
    await c.locator('.addEntry').click();
    const result = await (await response).json();
    expect(result.success).toBe(true);
    expect(sql(`SELECT hidden FROM feature_checklist_items WHERE uid=${result.uid}`)).toBe('1');
    const denied = await f.evaluate(async () => { const url = new URL(location.href); url.searchParams.set('veFieldOptions', '1'); url.searchParams.set('table', 'tt_content'); url.searchParams.set('uid', '200210'); return (await fetch(url, {headers: {'X-Request-Token': window.veInfo.token}})).status; });
    expect(denied).toBe(403);
  } finally { settings('editor'); await page.context().close(); }
});

test('showcase 8430: translated entries are removable with counts, fixed and media collections keep their reasons', async ({page}) => {
  const f = await open(page, {id: 8430});
  const ids = Number(sql("SELECT COUNT(*) FROM tt_content WHERE uid IN (200210,200225,200228) AND deleted=0"));
  test.skip(ids !== 3, 'showcase uids changed after a reseed');
  const fields = {};
  for (const uid of [200210, 200225, 200228]) fields[uid] = await payload(f, uid);
  const cards = fields[200210][0];
  expect(cards.children.every((child) => child.canDelete === true && Number(child.cascade?.translations ?? 0) > 0)).toBe(true);
  expect(fields[200225][0]).toMatchObject({minItems: 3, maxItems: 3, canCreate: false});
  expect(fields[200228][0]).toMatchObject({canCreate: false, createReason: 'required'});
  evidence.push({showcase: cards.children.map((child) => ({uid: child.uid, translations: child.cascade?.translations}))});
});

test('1.5 kept: library persistence (new, copy, paste-move) renders the saved text', async ({page}) => {
  const f = await open(page);
  const title = 'VEE library compatibility disposable';
  const save = (body) => f.evaluate(async (body) => { const res = await fetch(window.TYPO3.settings.ajaxUrls.visual_editor_save, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-Request-Token': window.veInfo.token}, body: JSON.stringify(body)}); return {status: res.status, data: await res.json()}; }, body);
  expect((await save({data: {tt_content: {NEWcompat: {pid: fixture.page, CType: 'text', header: title, bodytext: '<p>Library compatibility body</p>', hidden: 0}}}, cmdArray: []})).data.success).toBe(true);
  const uid = Number(sql(`SELECT MAX(uid) FROM tt_content WHERE pid=${fixture.page} AND header='${title}' AND deleted=0`));
  expect((await save({cmdArray: [{tt_content: {[uid]: {copy: fixture.page}}}]})).data.success).toBe(true);
  const copy = Number(sql(`SELECT MAX(uid) FROM tt_content WHERE pid=${fixture.page} AND header LIKE '${title}%' AND deleted=0`));
  expect(copy).toBeGreaterThan(uid);
  expect((await save({cmdArray: [{tt_content: {[copy]: {move: {action: 'paste', target: -uid, update: {colPos: 0}}}}}]})).data.success).toBe(true);
  expect(await (await page.request.get(base + fixturePath)).text()).toContain('Library compatibility body');
  for (const id of [uid, copy]) expect((await save({cmdArray: [{tt_content: {[id]: {delete: 1}}}]})).data.success).toBe(true);
});
