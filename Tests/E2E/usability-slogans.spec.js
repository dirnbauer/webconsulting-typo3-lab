import { expect, test } from "@playwright/test";

// Kurt's usability talk (plan 2026-11-04, D7, 4.8 item 3 and WP4e): the long
// cut (about five minutes), placed by sitepackage:films:seed as a portrait
// Video Feature Section on the home page after the lab overview film, from
// packages/site_package/Resources/Private/Fixtures/Films/usability-slogans/long/
// (film id usability-slogans). The short cut is not on the site any more, and
// the block links to no separate talk page. It does not start by itself:
// poster, controls and captions, no autoplay, and a click starts it.
//
// The test skips while the film is not on the page yet (run
// sitepackage:films:seed --film=usability-slogans). USABILITY_TALK_PATH points
// it at another page that carries the block.
const path = process.env.USABILITY_TALK_PATH ?? "/";

test("the usability talk on the home page is the long cut as a click-to-play portrait video", async ({ page }) => {
  test.setTimeout(240_000);
  const response = await page.goto(path, { waitUntil: "load", timeout: 120_000 });
  expect(response, "navigation returned no response").not.toBeNull();
  expect(response.status()).toBe(200);

  const block = page.locator(".feature-video--portrait").filter({
    has: page.locator('video source[src*="/films/usability-slogans/"]'),
  });
  test.skip(
    (await block.count()) === 0,
    `The usability talk is not on ${path} yet: run sitepackage:films:seed --film=usability-slogans.`,
  );
  await expect(block).toHaveCount(1);

  const video = block.locator("video");
  const source = await video.locator("source").first().getAttribute("src");
  expect(source, "the home block plays the wrong cut").toMatch(/\/usability-slogans-long-[0-9a-f]{8}\.mp4$/);

  // The text column: eyebrow, heading, the ten slogans and the Nielsen credit.
  await expect(block.locator(".feature-video__text h2")).toBeVisible();
  await expect(block.locator(".feature-video__text ol > li")).toHaveCount(10);
  await expect(
    block.locator('.feature-video__text a[href="https://www.nngroup.com/books/usability-engineering/"]'),
    "Nielsen is credited with a link to the book",
  ).toHaveCount(1);
  // The full talk is this block now: no link to the old talk page.
  // (The article URL …/blog/usability-slogans-nielsen is fine: only the page path itself is wrong.)
  const hrefs = await block.locator("a[href]").evaluateAll((links) => links.map((link) => link.href));
  expect(
    hrefs.filter((href) => /\/usability-slogans\/?(?:[?#].*)?$/.test(href)),
    "the block must not link to the old talk page",
  ).toEqual([]);
  await expect(block.locator(".feature-video__text a", { hasText: "Watch the full talk" })).toHaveCount(0);

  await expect(video).toHaveAttribute("poster", /\S/);
  await expect(video).toHaveAttribute("controls", "");
  await expect(video).toHaveAttribute("preload", "metadata");
  await expect(video.locator('track[kind="captions"][srclang="en"]')).toHaveCount(1);
  for (const name of ["autoplay", "muted", "loop", "data-d-autoplay"]) {
    expect(await video.getAttribute(name), `the talk must not carry ${name}`).toBeNull();
  }
  await expect(block.locator("[data-d-video-sound]")).toHaveCount(0);

  // Portrait: taller than wide.
  await video.scrollIntoViewIfNeeded();
  const box = await video.boundingBox();
  expect(box).not.toBeNull();
  expect(box.height).toBeGreaterThan(box.width);

  // The long cut: its length, read from the file's metadata.
  const duration = await video.evaluate(
    (element) =>
      element.readyState >= 1
        ? element.duration
        : new Promise((resolve) => {
            element.addEventListener("loadedmetadata", () => resolve(element.duration), { once: true });
          }),
  );
  expect(duration).toBeGreaterThan(290);
  expect(duration).toBeLessThan(320);

  // Nothing plays on load; a click on the player starts it.
  expect(await video.evaluate((element) => element.paused)).toBe(true);
  await video.click();
  await expect.poll(() => video.evaluate((element) => element.paused), { timeout: 20_000 }).toBe(false);
});
