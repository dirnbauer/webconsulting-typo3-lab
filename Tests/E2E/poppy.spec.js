import { expect, test } from '@playwright/test';

// Exercise the accessible motion preference; freeze decorative motion for captures.
test.use({ reducedMotion: 'reduce' });

for (const [path, heading, image] of [
  ['/features/poppy/', 'Poppy: personal agents meet TYPO3', 'Four steps: discover'],
  ['/features/poppy/pi-durable/', 'Pi Durable + TYPO3: a personal agent example', 'Pi Durable on Cloudflare'],
  ['/resources/poppy-help/', 'Poppy help: install, connect and test', null],
]) {
  test(`Poppy CMS page ${path}`, async ({ page }, testInfo) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto(path, { waitUntil: 'networkidle' });
    expect(response.status()).toBe(200);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.getByRole('heading', { name: heading, exact: true, level: 1 })).toBeVisible();
    if (image) {
      const img = page.getByRole('img', { name: new RegExp(image) });
      await img.scrollIntoViewIfNeeded();
      await expect(img).toBeVisible();
      expect(await img.evaluate(element => element.complete && element.naturalWidth > 0)).toBeTruthy();
    }
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
    expect(errors).toEqual([]);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: `var/playwright/poppy-${testInfo.project.name}-${path.includes('pi-durable') ? 'pi' : path.includes('help') ? 'help' : 'feature'}.png`, fullPage: true, animations: 'disabled' });
  });
}

test('Poppy discovery advertises the working guest profile', async ({ request, baseURL }) => {
  const response = await request.get('/.well-known/poppy.json');
  expect(response.status()).toBe(200);
  const d = await response.json();
  expect(d.protocol_version).toBe('0.1'); expect(d.auth).toEqual({ issuer: new URL(baseURL).origin + '/poppy' });
  expect(d.web).toEqual({}); expect(d.agent).toBeUndefined();
  const api = await request.get('/poppy/knowledge?topic=poppy'); expect(api.status()).toBe(401);
});

test('Poppy playground reads both CMS answers, renews and rejects replay', async ({ page }) => {
  await page.goto('/poppy/demo');
  await expect(page.getByRole('heading', { name: 'Ask the lab through Poppy.' })).toBeVisible();
  await page.getByRole('button', { name: 'Ask TYPO3 Lab' }).click();
  await expect(page.locator('#answer')).toContainText('signed-out profile');
  await expect(page.locator('#status')).toContainText('Guest session verified');
  await page.getByRole('button', { name: 'Renew session', exact: true }).click();
  await expect(page.locator('#check-result')).toContainText('same guest session');
  await page.getByRole('button', { name: 'Test replay protection' }).click();
  await expect(page.locator('#check-result')).toContainText('HTTP 401 invalid_dpop_proof');
  await page.getByLabel('What does TYPO3 Lab say about…').selectOption('pi-durable');
  await page.getByRole('button', { name: 'Ask TYPO3 Lab' }).click();
  await expect(page.locator('#answer-heading')).toHaveText('Pi Durable + TYPO3: a personal agent example');
  await expect(page.locator('#answer')).toContainText('Pi Durable provides the runtime');
  await expect(page.locator('#source')).toHaveAttribute('href', /\/features\/poppy\/pi-durable\/$/);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
});

test('Poppy playground cannot mint assertions without its browser CSRF context', async ({ request }) => {
  const response = await request.post('/poppy/demo/assertions');
  expect(response.status()).toBe(403);
  expect(await response.json()).toEqual({ error: 'invalid_request' });
  const jwks = await (await request.get('/poppy/demo/jwks.json')).json();
  expect(jwks.keys[0].kty).toBe('EC');
  expect(jwks.keys[0].d).toBeUndefined();
});

test('Poppy public routes cannot bypass the deployed lab login with other TYPO3 handlers', async ({ playwright, baseURL }) => {
  test.skip(new URL(baseURL).hostname.endsWith('.ddev.site'), 'DDEV has no Apache Basic Auth; this boundary is tested on the deployed lab.');
  const anonymous = await playwright.request.newContext({ baseURL });
  try {
    expect((await anonymous.get('/.well-known/poppy.json')).status()).toBe(200);
    for (const path of ['/poppy/demo', '/poppy/demo/assertions', '/features/poppy/', '/.well-known/poppy.json?eID=unknown', '/poppy/knowledge?topic=poppy&eID=unknown']) {
      const response = await anonymous.get(path);
      expect(response.status(), path).toBe(401);
      expect(response.headers()['www-authenticate'], path).toContain('Basic');
    }
    const api = await anonymous.get('/poppy/knowledge?topic=poppy');
    expect(api.status()).toBe(401);
    expect((await api.json()).error).toBeDefined();
  } finally {
    await anonymous.dispose();
  }
});
