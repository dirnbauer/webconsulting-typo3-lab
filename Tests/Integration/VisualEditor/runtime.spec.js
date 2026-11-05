import {test, expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
const main = process.env.VEE_RUNTIME_ROOT;
if (!main) throw new Error('Set VEE_RUNTIME_ROOT to the regular local DDEV project root.');
const fixture = JSON.parse(readFileSync(process.env.VEE_RUNTIME_FIXTURE || main + '/var/ve-integration/fixture.json'));
const base = 'https://webconsulting-typo3-lab.ddev.site';
function sql(query) { return execFileSync('ddev', ['mysql', '-N', '-e', query], {cwd: main, encoding: 'utf8'}).trim(); }
async function open(page) {
 await page.goto(`${base}/typo3/module/web/edit?id=${fixture.page}`, {waitUntil:'domcontentloaded'});
 await expect.poll(()=>page.frames().some(f=>f.url().includes('editMode=1')),{timeout:60000}).toBe(true);
 const frame=page.frames().find(f=>f.url().includes('editMode=1'));
 await frame.waitForFunction(()=>window.visualEditorEnhancements?.inlineRecordsEnabled);
 return frame;
}
async function chooser(frame, uid) {

 await frame.locator(`ve-content-element[uid="${uid}"] [data-ve-enhancement="inline-records"]`).evaluate(b=>b.click());
 const c=frame.locator('ve-field-chooser'); await expect.poll(()=>c.evaluate(x=>x.open && !x.loading && x.fields.some(f=>f.type==='inline'))).toBe(true); return c;
}
async function create(page, c, label='Add entry') {
 const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('visual-editor/save')&&r.request().postDataJSON()?.inlineRecord?.action==='create');
 const button=c.getByRole('button',{name:label,exact:true});await expect(button).toBeEnabled();await button.press('Enter');
 const r=await response; const data=await r.json();expect(data.success,JSON.stringify(data)).toBe(true);return data.uid;
}
test('real live create keeps hidden through child text save; Core modal cancellation and confirmed delete', async ({page})=>{
 const frame=await open(page);const c=await chooser(frame,fixture.records.NEWcheck);
 const uid=await create(page,c);expect(sql(`SELECT hidden FROM feature_checklist_items WHERE uid=${uid}`)).toBe('1');
 await expect(c.locator(`[data-child-settings="${uid}"]`)).toBeFocused();
 // The schema visibility policy intentionally keeps new entries out of anonymous HTML.
 const publicResponse=await page.request.get(`${base}/content-types/visual-editor-inline/integration-20261105/`);
 expect(await publicResponse.text()).not.toContain(`uid="${uid}"`);
 // Normal existing-child text save uses the unmodified generic persistence path.
 const result=await frame.evaluate(async ({uid})=>{const r=await fetch(window.TYPO3.settings.ajaxUrls.visual_editor_save,{method:'POST',headers:{'Content-Type':'application/json','X-Request-Token':window.veInfo.token},body:JSON.stringify({data:{feature_checklist_items:{[uid]:{title:'Edited hidden integration entry'}}},cmdArray:[]})});return {status:r.status,data:await r.json()};},{uid});
 expect(result.data.success).toBe(true);expect(sql(`SELECT CONCAT(hidden,':',title) FROM feature_checklist_items WHERE uid=${uid}`)).toBe('1:Edited hidden integration entry');
 await c.evaluate(x=>x.refresh());
 const row=c.locator('.field').filter({has:frame.locator(`[data-child-settings="${uid}"]`)}).last();
 const remove=row.getByRole('button',{name:'Remove',exact:true});
 let posts=0;page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('visual-editor/save'))posts++;});
 for(const cancel of ['Cancel','Escape','dismiss']){
  await expect(remove).toBeEnabled();await remove.press('Enter');const modal=page.locator('typo3-backend-modal').last();await expect(modal.locator('dialog')).toBeVisible();
  await expect(modal.getByRole('button',{name:'Cancel',exact:true})).toBeFocused();
  if(cancel==='Escape')await page.keyboard.press('Escape');else if(cancel==='dismiss')await modal.locator('.t3js-modal-close').click();else await modal.getByRole('button',{name:'Cancel',exact:true}).click();
  await expect(modal).toHaveCount(0);expect(sql(`SELECT deleted FROM feature_checklist_items WHERE uid=${uid}`)).toBe('0');expect(posts).toBe(0);await expect(remove).toBeFocused();
 }
 await expect(remove).toBeEnabled();await remove.press('Enter');await page.locator('typo3-backend-modal').last().getByRole('button',{name:'Remove',exact:true}).click();
 await expect.poll(()=>sql(`SELECT deleted FROM feature_checklist_items WHERE uid=${uid}`)).toBe('1');expect(posts).toBe(1);
});
test('real nested settings and leaf create/delete retain their owner',async({page})=>{
 const frame=await open(page);const c=await chooser(frame,fixture.records.NEWtable);
 await c.locator(`[data-child-settings="${fixture.records.NEWrow}"]`).press('Enter');await expect(c.getByRole('button',{name:'Back',exact:true})).toBeVisible();
 const uid=await create(page,c);expect(sql(`SELECT foreign_table_parent_uid FROM data_table_cells WHERE uid=${uid}`)).toBe(String(fixture.records.NEWrow));
 const row=c.locator('.field').filter({has:frame.locator(`[data-child-settings="${uid}"]`)}).last();const remove=row.getByRole('button',{name:'Remove',exact:true});await expect(remove).toBeEnabled();await expect(remove).toBeEnabled();await remove.press('Enter');await page.locator('typo3-backend-modal').last().getByRole('button',{name:'Remove',exact:true}).click();
 await expect.poll(()=>sql(`SELECT deleted FROM data_table_cells WHERE uid=${uid}`)).toBe('1');await c.getByRole('button',{name:'Back',exact:true}).press('Enter');await expect(c.locator(`[data-child-settings="${fixture.records.NEWrow}"]`)).toBeVisible();
});

function settings(role, mode, workspace=0) { execFileSync('ddev',['exec','php','/tmp/ve-runtime.php','settings',role,mode,String(workspace)],{cwd:main}); }
async function loginEditor(page) {
 await page.context().clearCookies();await page.goto(base+'/typo3/login?loginProvider=1433416747');
 await page.locator('#t3-username').fill('vee_integration_editor_20261105');await page.locator('#t3-password').fill(fixture.password);await page.locator('#t3-login-submit').click();
 await page.locator('[data-modulemenu-identifier],typo3-backend-module-menu').first().waitFor();
}
test('restricted German editor uses independent collection preference and is refused outside its mount',async({page})=>{
 settings('editor','disabled');
 try {
  await loginEditor(page);const f=await open(page);expect(await f.evaluate(()=>window.visualEditorEnhancements.fieldChooserMode)).toBe('disabled');
  const e=f.locator(`ve-content-element[uid="${fixture.records.NEWcheck}"]`);await expect(e.locator('[data-ve-enhancement="field-chooser"]')).toHaveCount(0);
  await e.locator('[data-ve-enhancement="inline-records"]').evaluate(b=>b.click());const c=f.locator('ve-field-chooser');await expect(c.getByRole('button',{name:'Eintrag hinzufügen',exact:true})).toBeVisible();
  const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('visual-editor/save'));
  await c.getByRole('button',{name:'Eintrag hinzufügen',exact:true}).press('Enter');const result=await (await response).json();expect(result.success).toBe(true);expect(sql(`SELECT hidden FROM feature_checklist_items WHERE uid=${result.uid}`)).toBe('1');
  const denied=await f.evaluate(async()=>{const url=new URL(location.href);url.searchParams.set('veFieldOptions','1');url.searchParams.set('table','tt_content');url.searchParams.set('uid','200210');const r=await fetch(url,{headers:{'X-Request-Token':window.veInfo.token}});return r.status;});expect(denied).toBe(403);
 } finally {settings('editor','tabs');}
});
test('existing Core workspace drafts support repeated create/discard without live changes; first-version fallback remains',async({page})=>{
 execFileSync('ddev',['exec','php','/tmp/ve-runtime.php','version'],{cwd:main});
 const before=sql(`SELECT * FROM tt_content WHERE pid=${fixture.page} AND t3ver_wsid=0 ORDER BY uid`)+sql(`SELECT * FROM feature_checklist_items WHERE pid=${fixture.page} AND t3ver_wsid=0 ORDER BY uid`);
 for(const role of ['admin','editor']) {
 settings(role,'sections',fixture.workspace);
 try {
  if(role==='editor') await loginEditor(page);
  const f=await open(page);expect(await f.evaluate(()=>window.visualEditorEnhancements.workspaceId)).toBe(fixture.workspace);
  const wrapper=f.locator('ve-content-element[ctype="desiderio_featurechecklist"]');await wrapper.locator('[data-ve-enhancement="inline-records"]').evaluate(b=>b.click());const c=f.locator('ve-field-chooser');
  await expect(c.getByRole('button',{name:role==='editor'?'Eintrag hinzufügen':'Add entry',exact:true})).toBeVisible();
  for(let i=0;i<2;i++){
   const uid=await create(page,c,role==='editor'?'Eintrag hinzufügen':'Add entry');expect(sql(`SELECT t3ver_wsid FROM feature_checklist_items WHERE uid=${uid}`)).toBe(String(fixture.workspace));
   const row=c.locator('.field').filter({has:f.locator(`[data-child-settings="${uid}"]`)}).last();const remove=row.getByRole('button',{name:role==='editor'?'Entfernen':'Remove',exact:true});await expect(remove).toBeEnabled();await expect(remove).toBeEnabled();await remove.press('Enter');const modal=page.locator('typo3-backend-modal').last();await expect(modal.locator('dialog')).toBeVisible();await modal.getByRole('button',{name:role==='editor'?'Entfernen':'Remove',exact:true}).click();await expect.poll(()=>sql(`SELECT COUNT(*) FROM feature_checklist_items WHERE uid=${uid} AND deleted=0`)).toBe('0');
  }
  expect(sql(`SELECT * FROM tt_content WHERE pid=${fixture.page} AND t3ver_wsid=0 ORDER BY uid`)+sql(`SELECT * FROM feature_checklist_items WHERE pid=${fixture.page} AND t3ver_wsid=0 ORDER BY uid`)).toBe(before);
  const other=f.locator(`ve-content-element[uid="${fixture.records.NEWtabs}"]`);await other.locator('[data-ve-enhancement="inline-records"]').evaluate(b=>b.click());await expect(c.getByText(role==='editor'?/vollständig versionierten/:/fully versioned parent/)).toBeVisible();
 }finally{settings(role,'tabs');}
 }
});
test('real tabs and sections preserve staged text and block structural writes until save',async({page})=>{
 try {
  for (const mode of ['tabs','sections']) {
   settings('admin',mode);const f=await open(page);const element=f.locator(`ve-content-element[uid="${fixture.records.NEWtabs}"]`);
   await element.locator('[data-ve-enhancement="field-chooser"]').evaluate(b=>b.click());const c=f.locator('ve-field-chooser');
   if(mode==='tabs')await expect(c.getByRole('tab').first()).toBeVisible();else await expect(c.getByRole('tab')).toHaveCount(0);
  }
  const f=page.frames().find(f=>f.url().includes('editMode=1'));const header=f.locator(`ve-editable-text[uid="${fixture.records.NEWcheck}"][field="header"]`).getByRole('textbox');
  await header.fill('Pending integration header');
  const c=await chooser(f,fixture.records.NEWcheck);await expect(c.getByRole('button',{name:'Add entry',exact:true})).toBeDisabled();
  expect(sql(`SELECT header FROM tt_content WHERE uid=${fixture.records.NEWcheck}`)).toBe('VEE disposable checklist');
  const save=f.parentFrame().locator('ve-backend-save-button');await save.click();
  await expect.poll(()=>sql(`SELECT header FROM tt_content WHERE uid=${fixture.records.NEWcheck}`)).toBe('Pending integration header');
  await expect(header).toContainText('Pending integration header');
  await header.fill('VEE disposable checklist');await save.click();await expect.poll(()=>sql(`SELECT header FROM tt_content WHERE uid=${fixture.records.NEWcheck}`)).toBe('VEE disposable checklist');
 }finally{settings('admin','tabs');}
});
test('actual showcase exposes translation, media and fixed-three-plan fallbacks',async({page})=>{
 await page.goto(base+'/typo3/module/web/edit?id=8430',{waitUntil:'domcontentloaded'});await expect.poll(()=>page.frames().some(f=>f.url().includes('editMode=1')),{timeout:60000}).toBe(true);const f=page.frames().find(f=>f.url().includes('editMode=1'));
 const rows=await f.evaluate(async()=>{const {fetchFieldOptions}=await import('@webconsulting/visual-editor-enhancements/Shared/field-options-cache.js');const result={};for(const uid of [200210,200225,200228]){const data=await fetchFieldOptions('tt_content',uid);result[uid]=data.fields.filter(x=>x.type==='inline').map(x=>({name:x.name,min:x.minItems,max:x.maxItems,canCreate:x.canCreate,reason:x.createReason,deleteReasons:x.children?.map(c=>c.deleteReason)}));}return result;});
 expect(rows[200210][0].deleteReasons).toContain('translations');expect(rows[200225][0]).toMatchObject({min:3,max:3,canCreate:false});expect(rows[200228][0]).toMatchObject({canCreate:false,reason:'required'});
});
test('normal library persistence supports live new/copy/paste move and renders the saved text',async({page})=>{
 const f=await open(page);const title='VEE library compatibility disposable';
 const save=payload=>f.evaluate(async(payload)=>{const r=await fetch(window.TYPO3.settings.ajaxUrls.visual_editor_save,{method:'POST',headers:{'Content-Type':'application/json','X-Request-Token':window.veInfo.token},body:JSON.stringify(payload)});return {status:r.status,data:await r.json()};},payload);
 const created=await save({data:{tt_content:{NEWcompat:{pid:fixture.page,CType:'text',header:title,bodytext:'<p>Library compatibility body</p>',hidden:0}}},cmdArray:[]});expect(created.data.success).toBe(true);
 const uid=Number(sql(`SELECT MAX(uid) FROM tt_content WHERE pid=${fixture.page} AND header='${title}' AND deleted=0`));expect(uid).toBeGreaterThan(0);
 expect((await save({cmdArray:[{tt_content:{[uid]:{copy:fixture.page}}}]})).data.success).toBe(true);
 const copy=Number(sql(`SELECT MAX(uid) FROM tt_content WHERE pid=${fixture.page} AND header LIKE '${title}%' AND deleted=0`));expect(copy).toBeGreaterThan(uid);
 expect((await save({cmdArray:[{tt_content:{[copy]:{move:{action:'paste',target:-uid,update:{colPos:0}}}}}]})).data.success).toBe(true);
 const result=await page.request.get(base+'/content-types/visual-editor-inline/integration-20261105/');expect(await result.text()).toContain('Library compatibility body');
 for(const id of [uid,copy])expect((await save({cmdArray:[{tt_content:{[id]:{delete:1}}}]})).data.success).toBe(true);
});
test('hidden new entry can be edited and saved in real Core FormEngine without publication',async({page})=>{
 const f=await open(page);const c=await chooser(f,fixture.records.NEWcheck);const uid=await create(page,c);
 await expect(c.locator(`[data-child-settings="${uid}"]`)).toBeVisible();
 const url=await c.evaluate((x,uid)=>x.fields.flatMap(f=>f.children||[]).find(c=>c.uid===uid).backendUrl,uid);
 const form=await page.context().newPage();await form.goto(url,{waitUntil:'domcontentloaded'});
 const title=form.locator(`textarea[name="data[feature_checklist_items][${uid}][title]"]`);await title.fill('Edited in actual FormEngine');
 await form.locator('button[name="_savedok"]').click();await expect.poll(()=>sql(`SELECT CONCAT(hidden,':',title) FROM feature_checklist_items WHERE uid=${uid}`)).toBe('1:Edited in actual FormEngine');await form.close();
});
