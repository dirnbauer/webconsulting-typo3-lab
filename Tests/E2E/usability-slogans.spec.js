import { expect, test } from "@playwright/test";

// Kurt's usability talk (plan 2026-11-05, D6 and 6.1): the long cut (about
// five minutes) on its own page /usability-slogans (a child of Lab and
// resources), placed by sitepackage:films:seed as a portrait Video Feature
// Section after the page's header section, from
// packages/site_package/Resources/Private/Fixtures/Films/usability-slogans/long/
// (film id usability-slogans). It does not start by itself: poster, controls
// and captions, no autoplay, and a click starts it. Until 2026-11-05 the
// block sat on the home page; the home page must not play it any more.
//
// The tests skip while the talk page does not exist yet (404: the styleguide
// reseed of the round has not run) or carries no block yet (run
// sitepackage:films:seed --film=usability-slogans). USABILITY_TALK_PATH points
// them at another page that carries the block.
const path = process.env.USABILITY_TALK_PATH ?? "/usability-slogans/";

test("the usability talk on its page is the long cut as a click-to-play portrait video", async ({ page }) => {
  test.setTimeout(240_000);
  const response = await page.goto(path, { waitUntil: "load", timeout: 120_000 });
  expect(response, "navigation returned no response").not.toBeNull();
  test.skip(
    response.status() === 404,
    `${path} does not exist yet: run the styleguide seed of the 2026-11-05 round (desiderio 4.29).`,
  );
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
  expect(source, "the block plays the wrong cut").toMatch(/\/usability-slogans-long-[0-9a-f]{8}\.mp4$/);

  // The text column has its own heading: the page's header section names the
  // talk, the slogan block lists the ten slogans and the editors block credits
  // Nielsen, so the film block repeats none of them.
  await expect(block.locator(".feature-video__text h2")).toHaveText("Watch the five-minute talk");
  await expect(block.locator(".feature-video__text ol > li")).toHaveCount(0);
  await expect(
    page.locator('a[href="https://www.nngroup.com/books/usability-engineering/"]'),
    "Nielsen is credited on the page with a link to the book",
  ).not.toHaveCount(0);
  // The block sits on the talk page: no link to the page itself.
  // (The article URL …/blog/usability-slogans-nielsen is fine: only the page path itself is wrong.)
  const hrefs = await block.locator("a[href]").evaluateAll((links) => links.map((link) => link.href));
  expect(
    hrefs.filter((href) => /\/usability-slogans\/?(?:[?#].*)?$/.test(href)),
    "the block must not link to its own page",
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

test("the home page does not play the usability talk any more", async ({ page, request }) => {
  test.setTimeout(120_000);
  const talkPage = await request.get("/usability-slogans/", { ignoreHTTPSErrors: true, maxRedirects: 0 });
  test.skip(
    talkPage.status() === 404,
    "The talk page does not exist yet, so the home page still has the talk: run the styleguide seed of the 2026-11-05 round.",
  );
  const response = await page.goto("/", { waitUntil: "domcontentloaded", timeout: 90_000 });
  expect(response, "navigation returned no response").not.toBeNull();
  expect(response.status()).toBe(200);
  await expect(page.locator('video source[src*="/films/usability-slogans/"]')).toHaveCount(0);
});
