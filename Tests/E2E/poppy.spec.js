import { expect, test } from '@playwright/test';

// Exercise the accessible motion preference; freeze decorative motion for captures.
test.use({ reducedMotion: 'reduce' });

for (const [path, heading, image] of [
  ['/agent-nexus/poppy/', 'Poppy: personal agents meet TYPO3', 'Four steps: discover'],
  ['/agent-nexus/poppy/pi-durable/', 'Pi Durable + TYPO3: a personal agent example', 'Pi Durable on Cloudflare'],
  ['/agent-nexus/poppy/help/', 'Poppy help: install, connect and test', null],
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
  await expect(page.locator('#source')).toHaveAttribute('href', /\/agent-nexus\/poppy\/pi-durable\/$/);
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
  // Override the suite's lab login; new request contexts inherit its defaults.
  const anonymous = await playwright.request.newContext({ baseURL, httpCredentials: { username: '', password: '' } });
  try {
    expect((await anonymous.get('/.well-known/poppy.json')).status()).toBe(200);
    for (const path of ['/poppy/demo', '/poppy/demo/assertions', '/agent-nexus/poppy/', '/.well-known/poppy.json?eID=unknown', '/poppy/knowledge?topic=poppy&eID=unknown']) {
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

test('Agent Nexus presents six protocols and links the complete Poppy field guide', async ({ page }) => {
  await page.goto('/agent-nexus/');
  await expect(page.getByRole('heading', { name: 'Six agent protocols running on TYPO3' })).toBeVisible();
  await expect(page.locator('.anx-hub__grid > li')).toHaveCount(6);
  await expect(page.locator('.anx-hub__card[data-accent="poppy"]')).toContainText('guest knowledge profile');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
  await page.getByRole('link', { name: 'Open the Poppy demo', exact: false }).click();
  await expect(page).toHaveURL(/\/agent-nexus\/poppy\/$/);
  await expect(page.getByRole('heading', { name: 'How Poppy works', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Where Poppy fits in Agent Nexus', exact: true })).toBeVisible();
  for (const path of ['/agent-nexus/poppy/pi-durable/', '/agent-nexus/poppy/help/', '/agent-nexus/']) {
    expect(await page.locator(`a[href="${path}"]`).count()).toBeGreaterThan(0);
  }
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
  await page.goto('/agent-nexus/playground/');
  await expect(page.getByRole('heading', { name: 'Poppy — identify the agent and read published knowledge', exact: true })).toBeVisible();
  await page.goto('/agent-nexus/docs/');
  await expect(page.locator('a[href="/agent-nexus/poppy/help/"]').first()).toBeVisible();
});

test('Former Poppy content URLs redirect to the Agent Nexus canonical pages', async ({ request, baseURL }) => {
  for (const [oldPath, newPath] of [
    ['/poppy/', '/agent-nexus/poppy/'],
    ['/features/poppy/', '/agent-nexus/poppy/'],
    ['/features/poppy/pi-durable/', '/agent-nexus/poppy/pi-durable/'],
    ['/resources/poppy-help/', '/agent-nexus/poppy/help/'],
  ]) {
    const redirect = await request.get(oldPath, { maxRedirects: 0 });
    expect(redirect.status(), oldPath).toBe(301);
    expect(new URL(redirect.headers().location, baseURL).pathname.replace(/\/$/, '')).toBe(newPath.replace(/\/$/, ''));
    expect((await request.get(oldPath)).status()).toBe(200);
  }
});
