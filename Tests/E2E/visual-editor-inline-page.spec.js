import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const slug = '/content-types/visual-editor-inline/';
const counts = [
  ['.feature-cards__item', 3], ['.accordion__item', 3], ['.faq details', 4],
  ['.pricing-faq__item', 3], ['.tabs-section [role="tab"]', 3], ['.timeline__item', 4],
  ['.stats__item', 4], ['.stats-inline__item', 3], ['.product-specs tbody tr', 5],
  ['.definition-list__term', 4], ['.feature-checklist__item', 5], ['.feature-list__item', 3],
  ['.changelog__date', 3], ['.data-table-section tbody tr', 3],
  ['.comparison-table-section tbody tr', 3], ['.pricing-three-tier__grid > li', 3],
  ['.team__grid > *', 3], ['.testimonial-grid__items > *', 3],
  ['.gallery [data-d-gallery-thumb]', 3], ['.logo-cloud__grid > *', 4],
  ['.legal-links__item', 3], ['.office-hours__row', 5],
];

for (const [lang, title] of [
  ['en', 'Inline records in the Visual Editor'], ['de', 'Inline-Einträge im Visual Editor'],
  ['zh', 'Visual Editor 中的内联记录'], ['hu', 'Beágyazott rekordok a Visual Editorban'],
]) {
  test(`anonymous ${lang} showcase renders all collection shapes without editor data`, async ({ page }) => {
    const response = await page.goto(lang === 'en' ? slug : `/${lang}${slug}`, { waitUntil: 'networkidle' });
    expect(response.status()).toBe(200);
    await expect(page.locator('h1')).toHaveText(title);
    for (const [selector, count] of counts) {
      await expect(page.locator(`main ${selector}`), selector).toHaveCount(count);
    }
    await expect(page.locator('.data-table-section tbody td')).toHaveCount(9);
    await expect(page.locator('.comparison-table-section tbody td')).toHaveCount(6);
    await expect(page.locator('ve-content-element, ve-editable-text, ve-field-chooser, [data-ve-table]')).toHaveCount(0);
    expect(await page.evaluate(() => Boolean(window.veInfo || window.visualEditorEnhancements))).toBe(false);
    await expect(page.locator(`[data-d-primary-nav] a[href*="visual-editor-inline"]`)).toHaveCount(0);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
  });
}

for (const lang of ['en', 'de']) {
  test(`login ${lang} preserves the accessible logo and working GPL disclosure`, async ({ browser }, testInfo) => {
    const context = await browser.newContext({ locale: lang, baseURL: testInfo.project.use.baseURL, ignoreHTTPSErrors: true, viewport: testInfo.project.use.viewport });
    const page = await context.newPage();
    try {
      const response = await page.goto('/typo3/login', { waitUntil: 'networkidle' });
      expect(response.status()).toBe(200);
      const credits = page.locator('.webcon-login-credits');
      await credits.scrollIntoViewIfNeeded();
      await expect(credits.getByRole('img', { name: 'TYPO3', exact: true })).toHaveCount(1);
      const tagline = credits.locator('.webcon-login-builton > span');
      await expect(tagline).toBeVisible();
      expect((await tagline.textContent()).trim().length).toBeGreaterThan(5);
      expect(await tagline.textContent()).not.toContain('TYPO3');
      const bounds = await credits.locator('.webcon-login-builton').boundingBox();
      expect(bounds.x).toBeGreaterThanOrEqual(0);
      expect(bounds.x + bounds.width).toBeLessThanOrEqual(page.viewportSize().width);
      await expect(credits).toContainText('GNU GPL');
      const disclosure = credits.locator('[aria-controls="loginCopyright"]');
      await disclosure.click();
      await expect(disclosure).toHaveAttribute('aria-expanded', 'true');
      await expect(credits.locator('#loginCopyright a[href="https://typo3.org"]')).toBeVisible();
      await expect(credits.locator('#loginCopyright a[href*="donate"]')).toBeVisible();
      await page.screenshot({ path: testInfo.outputPath(`login-${lang}.png`), fullPage: true });
      expect((await new AxeBuilder({ page }).include('.webcon-login-credits').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
    } finally {
      await context.close();
    }
  });
}
