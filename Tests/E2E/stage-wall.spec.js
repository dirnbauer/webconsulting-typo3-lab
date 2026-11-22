import { expect, test } from "@playwright/test";

/*
 * The Desiderio start page "Stage and Wall" (site_package Templates/Pages/
 * StageWall, Classes/StartPage/StageWallPlan, Resources/Public/StageWall).
 * Presentation only: the elements, their order, the reading and tab order
 * stay the ones of the plain start page; the plan places them by the
 * editor's widths.
 */

const wall = ".desiderio-stage-wall";

async function open(page, path = "/") {
  const response = await page.goto(path, { waitUntil: "load" });
  expect(response?.status()).toBe(200);
}

for (const path of ["/", "/de/", "/zh/", "/hu/"]) {
  test(`${path} is Stage and Wall in the English look`, async ({ page }) => {
    await open(page, path);
    await expect(page.locator(wall)).toHaveAttribute("data-sw-stage", "film");
    expect(await page.evaluate(() => document.body.dataset.shadcnPreset)).toBe("b0");
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("h1")).toHaveClass(/sr-only/);
    await expect(page.locator(`${wall} > [data-sw="hero"]`)).toHaveCount(1);
    await expect(page.locator(`${wall} > [data-sw="film"]`)).toHaveCount(1);
    // Every element is placed, and every element keeps its anchor.
    const counts = await page.evaluate((selector) => {
      const root = document.querySelector(selector);
      const children = [...root.children].filter((el) => el.tagName !== "A");
      return {
        elements: children.length,
        placed: children.filter((el) => el.hasAttribute("data-sw")).length,
        anchors: root.querySelectorAll(":scope > a[id^='c'][data-sw-anchor]").length,
      };
    }, wall);
    expect(counts.elements).toBeGreaterThan(2);
    expect(counts.placed).toBe(counts.elements);
    expect(counts.anchors).toBe(counts.elements);
  });
}

for (const width of [390, 820, 1440, 1920]) {
  test(`no horizontal scroll at ${width} px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await open(page);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });
}

test("desktop: tiles share rows and connectors dock under their tile", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await open(page);
  const boxes = await page.evaluate((selector) => [...document.querySelectorAll(`${selector} > [data-sw]`)].map((el) => {
    const r = el.getBoundingClientRect();
    return { sw: el.dataset.sw, row: el.dataset.swRow, col: el.dataset.swCol, x: Math.round(r.left), y: Math.round(r.top + scrollY), w: Math.round(r.width), bottom: Math.round(r.bottom + scrollY) };
  }), wall);
  const wallItems = boxes.filter((box) => box.sw === "tile" || box.sw === "connector");
  // Tiles of one row start at the same height.
  const byRow = Object.groupBy(wallItems.filter((box) => box.sw === "tile" && box.row), (box) => box.row);
  for (const tiles of Object.values(byRow)) {
    expect(new Set(tiles.map((tile) => tile.y)).size).toBe(1);
  }
  expect(Object.values(byRow).some((tiles) => tiles.length > 1)).toBe(true);
  // A connector sits right under the tile before it, in the same columns.
  const connectors = boxes.map((box, index) => [box, boxes[index - 1]]).filter(([box]) => box.sw === "connector");
  expect(connectors.length).toBeGreaterThan(0);
  for (const [connector] of connectors) {
    const tile = wallItems.find((box) => box.sw === "tile" && box.col === connector.col && box.bottom <= connector.y + 1 && box.bottom >= connector.y - 2);
    expect(tile, JSON.stringify(connector)).toBeTruthy();
    expect(tile.w).toBe(connector.w);
  }
  // No tile overlaps another.
  for (const a of wallItems) {
    for (const b of wallItems) {
      if (a === b) continue;
      const overlap = a.x < b.x + b.w - 1 && b.x < a.x + a.w - 1 && a.y < b.bottom - 1 && b.y < a.bottom - 1;
      expect(overlap, `${JSON.stringify(a)} / ${JSON.stringify(b)}`).toBe(false);
    }
  }
});

test("the tab order follows the page order", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await open(page);
  await page.locator("main").evaluate((main) => main.setAttribute("tabindex", "-1"));
  await page.locator("main").focus();
  const order = [];
  for (let i = 0; i < 40; i++) {
    await page.keyboard.press("Tab");
    const index = await page.evaluate((selector) => {
      const active = document.activeElement;
      const root = document.querySelector(selector);
      if (!root || !root.contains(active)) return null;
      return [...root.querySelectorAll("*")].indexOf(active);
    }, wall);
    if (index === null) break;
    order.push(index);
  }
  expect(order.length).toBeGreaterThan(20);
  expect(order).toEqual([...order].sort((a, b) => a - b));
});

test("the film plays, the gallery switches and its lightbox opens and closes", async ({ page }, testInfo) => {
  await open(page);
  const film = page.locator(`${wall} [data-sw="film"] video`);
  await expect(film).toHaveCount(1);
  await expect(film).toHaveAttribute("controls", "");
  if (testInfo.project.name === "chromium-desktop") {
    // It starts muted once it is in view (Desiderio's data-d-autoplay).
    await film.scrollIntoViewIfNeeded();
    await expect.poll(() => film.evaluate((video) => video.currentTime), { timeout: 15_000 }).toBeGreaterThan(0);
  }
  const thumbs = page.locator(".gallery__thumb");
  await thumbs.nth(2).scrollIntoViewIfNeeded();
  await thumbs.nth(2).click();
  await expect(thumbs.nth(2)).toHaveAttribute("aria-current", "true");
  await page.locator(".gallery__featured .d-lightbox").click();
  await expect(page.locator("dialog[open]")).toHaveCount(1);
  await page.keyboard.press("Escape");
  await expect(page.locator("dialog[open]")).toHaveCount(0);
});

test("reduced motion: the film stands still on the stage", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await open(page);
  const animation = await page.locator(`${wall} [data-sw="film"] .feature-video__player`).evaluate((el) => getComputedStyle(el).animationName);
  expect(animation).toBe("none");
});

test("the layout does not shift while the page loads", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.addInitScript(() => {
    window.__shift = 0;
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (!entry.hadRecentInput) window.__shift += entry.value;
      }
    }).observe({ type: "layout-shift", buffered: true });
  });
  await open(page);
  await page.waitForTimeout(2500);
  expect(await page.evaluate(() => window.__shift)).toBeLessThan(0.1);
});
