import { expect, test as base } from '@playwright/test';

const test = base.extend({
  externalLinkGuard: [async ({ context }, use) => {
    // Register before the page fixture: mail and telephone links must never open native apps.
    await context.addInitScript(() => {
      window.__blockedExternal = 0;
      const external = value => {
        if (value == null) return false;
        try {
          return !['http:', 'https:', 'about:', 'blob:', 'data:', 'javascript:', 'file:']
            .includes(new URL(value, location.href).protocol.toLowerCase());
        } catch { return false; }
      };
      for (const type of ['click', 'auxclick']) {
        window.addEventListener(type, event => {
          const link = event.composedPath().find(node => node?.localName === 'a' || node?.localName === 'area');
          if (link && (link.hasAttribute('data-mailto-token') || external(link.getAttribute('href')))) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.__blockedExternal++;
          }
        }, true);
      }
      window.addEventListener('submit', event => {
        if (external(event.submitter?.getAttribute('formaction') ?? event.target.getAttribute('action'))) {
          event.preventDefault();
          event.stopImmediatePropagation();
          window.__blockedExternal++;
        }
      }, true);
    });
    await use();
    for (const page of context.pages()) {
      expect(await page.evaluate(() => window.__blockedExternal)).toBe(0);
    }
  }, { auto: true }],
});

test('Powermail exposes required labels and validates the minimum team size without submitting', async ({ page }) => {
  await page.goto('/desiderio-powermail/contact/', { waitUntil: 'domcontentloaded' });
  const form = page.locator('form.desiderio-powermail-contact');
  await expect(form).toBeVisible();
  const attemptedSubmissions = [];
  // A broken client validator must fail this test without sending a message on the live site.
  const action = await form.getAttribute('action');
  const actionUrl = new URL(action, page.url());
  await page.route(url => url.origin === actionUrl.origin && url.pathname === actionUrl.pathname, route => {
    attemptedSubmissions.push(route.request().method());
    return route.abort();
  });

  for (const marker of ['name', 'email', 'phone', 'team_size', 'topic', 'message']) {
    await expect(form.locator(`#powermail_field_${marker}`)).toHaveAttribute('required', 'required');
    await expect(form.locator(`label[for="powermail_field_${marker}"]`)).toContainText('*');
  }
  await form.getByRole('button', { name: 'Send request', exact: true }).click();
  await expect(form.locator('#powermail_field_name')).toBeFocused();
  await expect(form.locator('#powermail_field_name')).toHaveAttribute('aria-invalid', 'true');
  await expect(form.locator('#powermail_field_error_name')).toHaveText('Please complete this field.');

  const teamSize = form.getByLabel('Team size', { exact: false });
  await teamSize.fill('4');
  await teamSize.blur();
  await expect(teamSize).toHaveAttribute('aria-invalid', 'true');
  await expect(form.locator('#powermail_field_error_team_size')).toHaveText('Enter a value of at least 5.');
  await teamSize.fill('5');
  await teamSize.blur();
  await expect(teamSize).not.toHaveAttribute('aria-invalid', 'true');
  await expect(form.locator('#powermail_field_error_team_size')).toBeEmpty();
  expect(await teamSize.evaluate(field => field.validity.valid)).toBe(true);
  expect(attemptedSubmissions).toEqual([]);
});

test('Powermail Conditions shows outage follow-up fields and removes them again for a usage question', async ({ page }) => {
  test.setTimeout(60_000);
  await page.goto('/desiderio-powermail-jev/support-triage/', { waitUntil: 'domcontentloaded' });
  const form = page.locator('form.webcon-jev-support-triage');
  const reproduction = form.getByLabel('How can we reproduce it?', { exact: true });
  const escalation = form.locator('.powermail_fieldwrap_escalation');
  const message = form.getByLabel('What is the problem?', { exact: false });
  const endpoint = new URL(await page.locator('[data-condition-uri]').getAttribute('data-condition-uri'), page.url());
  await expect(reproduction).toBeHidden();
  await expect(reproduction).toBeDisabled();
  await expect(escalation).toBeHidden();

  for (const [text, action] of [
    ['The whole online shop is down after the update. Every checkout fails and we lose orders every hour.', 'un_hide'],
    ['How can I add a new page in TYPO3? Please point me to the documentation. Nothing is broken.', 'hide'],
  ]) {
    const result = page.waitForResponse(response => {
      const request = response.request();
      const url = new URL(response.url());
      return request.method() === 'POST'
        && url.origin === endpoint.origin && url.pathname === endpoint.pathname
        && request.postDataBuffer()?.includes(Buffer.from(text));
    });
    await message.fill(text);
    await message.blur();
    const response = await result;
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/json');
    const decisions = await response.json();
    expect(decisions.loops).toBeLessThan(decisions.loopLimit);
    const actions = Object.values(decisions.todo).flatMap(pages => Object.values(pages));
    expect(actions.some(fields => fields.reproduction?.['#action'] === action
      && fields.escalation?.['#action'] === action)).toBe(true);
    if (action === 'un_hide') {
      await expect(reproduction).toBeVisible();
      await expect(reproduction).toBeEnabled();
      await expect(escalation).toBeVisible();
      await expect(escalation).toContainText('This looks urgent.');
      await reproduction.fill('Local synthetic test: open the checkout after the update.');
    } else {
      await expect(reproduction).toBeHidden();
      await expect(reproduction).toBeDisabled();
      await expect(escalation).toBeHidden();
      expect(await form.evaluate(element => new FormData(element).has('tx_powermail_pi1[field][reproduction]'))).toBe(false);
    }
  }
  // This existing demo intentionally makes the conditional follow-up optional.
  await expect(reproduction).not.toHaveAttribute('required');
});
