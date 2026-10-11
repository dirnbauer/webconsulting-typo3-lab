import { expect, test } from '@playwright/test';

test('section eyebrows use the theme font without a decorative marker', async ({ page }) => {
  await page.goto('/');
  const eyebrows = page.locator('.d-section-intro__eyebrow');
  expect(await eyebrows.count()).toBeGreaterThan(0);
  for (const eyebrow of await eyebrows.all()) {
    await expect(eyebrow).toHaveCSS('font-family', await page.locator('body').evaluate(el => getComputedStyle(el).fontFamily));
    expect(await eyebrow.evaluate(el => getComputedStyle(el, '::before').content)).toBe('none');
  }
});

test('editorial list markers align with paragraphs and only bullets use the theme colour', async ({ page }) => {
  await page.goto('/content-types/content-editorial/');
  // Exercise the actual rich-text wrapper alongside all catalog styles,
  // including wrapped text, CKEditor paragraphs, nesting and native counters.
  const sample = page.locator('.d-richtext').first();
  await sample.evaluate(el => {
    el.innerHTML = '<p>Paragraph edge</p><ul><li>One long item with enough text to wrap on a narrow mobile screen and remain readable.</li><li><p>Paragraph inside an item</p><ul><li>Nested bullet</li></ul></li></ul><ol start="9"><li>Nine</li><li value="12">Twelve</li></ol><ol reversed start="4"><li>Four</li><li>Three</li></ol>';
  });

  for (const mode of ['light', 'dark']) {
    await page.evaluate(mode => document.documentElement.classList.toggle('dark', mode === 'dark'), mode);
    for (const direction of ['ltr', 'rtl']) {
      await sample.evaluate((el, direction) => { el.dir = direction; }, direction);
      const result = await sample.evaluate(el => {
        const paragraph = el.querySelector('p').getBoundingClientRect();
        const lists = [...el.querySelectorAll(':scope > ul, :scope > ol')];
        const bullet = el.querySelector('ul > li');
        const number = el.querySelector('ol > li');
        const probe = document.createElement('span');
        probe.style.color = 'var(--primary)';
        el.append(probe);
        const primary = getComputedStyle(probe).color;
        probe.remove();
        return {
          listPositions: lists.map(list => getComputedStyle(list).listStylePosition),
          listPadding: lists.map(list => getComputedStyle(list).paddingInlineStart),
          edgeOffsets: lists.map(list => el.dir === 'rtl' ? list.getBoundingClientRect().right - paragraph.right : list.getBoundingClientRect().left - paragraph.left),
          bulletColor: getComputedStyle(bullet, '::marker').color,
          primary,
          numberColor: getComputedStyle(number, '::marker').color,
          textColor: getComputedStyle(number).color,
          firstParagraphDisplay: getComputedStyle(el.querySelector('li > p')).display,
          nativeValues: [el.querySelector('ol').start, el.querySelector('li[value]').value, el.querySelector('ol[reversed]').reversed],
        };
      });
      expect(result.listPositions).toEqual(['inside', 'inside', 'inside']);
      expect(result.listPadding).toEqual(['0px', '0px', '0px']);
      expect(result.edgeOffsets.every(offset => Math.abs(offset) < 1)).toBe(true);
      expect(result.bulletColor).toBe(result.primary);
      expect(result.numberColor).toBe(result.textColor);
      expect(result.firstParagraphDisplay).toBe('inline');
      expect(result.nativeValues).toEqual([9, 12, true]);
    }
  }
});

test('closed and open native accordions keep horizontal separators only', async ({ page }) => {
  await page.goto('/content-types/content-editorial/');
  for (const selector of ['.accordion__item', '.faq__item']) {
    const item = page.locator(selector).first();
    for (const open of [false, true]) {
      await item.evaluate((el, open) => { el.open = open; }, open);
      await expect(item).toHaveCSS('border-left-width', '0px');
      await expect(item).toHaveCSS('border-right-width', '0px');
      await expect(item).toHaveCSS('border-bottom-width', '1px');
    }
  }
});

test('feature FAQ hover follows the shadcn accordion without a side surface', async ({ page, isMobile }) => {
  await page.goto('/features/docx-editor/');
  const question = page.locator('.faq__question').first();
  await expect(page.locator('.faq h2')).toHaveText('Questions before you install');
  await question.scrollIntoViewIfNeeded();
  if (!isMobile) {
    await question.hover();
    await expect(question.locator('.faq__question-text')).toHaveCSS('text-decoration-line', 'underline');
  }
  await expect(question).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
  await expect(question).toHaveCSS('margin-left', '0px');
  await expect(question).toHaveCSS('margin-right', '0px');
  await expect(question).toHaveCSS('padding-left', '0px');
  await expect(question).toHaveCSS('padding-right', '0px');

  // A visible focus ring is still required for keyboard operation.
  await question.focus();
  await page.keyboard.press('Enter');
  await expect(question.locator('..')).toHaveAttribute('open', '');
  await expect(question).not.toHaveCSS('box-shadow', 'none');
  await page.keyboard.press('Enter');
  await expect(question.locator('..')).not.toHaveAttribute('open', '');
});
