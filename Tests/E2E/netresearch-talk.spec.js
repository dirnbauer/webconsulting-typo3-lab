import { expect, test } from "@playwright/test";

// The Netresearch talk below /ai (sitepackage:seed-netresearch-talk) and the AI
// chat page, which presents Netresearch's nr-mcp-agent since 2026-11-21. A
// smoke check per page and language: it renders, keeps one h1, resolves every
// {{page:}} link the seeder writes, loads its images and fits a phone.
// `pending`: the talk pages exist once the production data run seeded them, so
// a 404 skips instead of failing.
const TALK = [
  "",
  "possibilities/",
  "questions-before-you-start/",
  "answers-and-limits/",
  "agent-paths/",
  "context-and-exchange/",
  "technical-details/",
  "sources-and-links/",
  "modular-ai-stack/",
];
const LANGUAGES = ["", "/de"];

const targets = [
  ...LANGUAGES.flatMap((prefix) => TALK.map((slug) => ({
    path: `${prefix}/ai/netresearch-talk/${slug}`,
    pending: true,
  }))),
  { path: "/ai/ai-assistant/", pending: false },
  { path: "/de/ai/ai-assistant/", pending: false },
];

for (const target of targets) {
  test(`${target.path} renders`, async ({ page }) => {
    const browserErrors = [];
    const failedRequests = [];
    page.on("pageerror", (error) => browserErrors.push(error.message));
    page.on("console", (message) => {
      if (message.type() === "error") {
        browserErrors.push(message.text());
      }
    });
    page.on("response", (response) => {
      const url = new URL(response.url());
      if (url.hostname.endsWith(".ddev.site") && response.status() >= 400) {
        failedRequests.push(`${response.status()} ${response.url()}`);
      }
    });

    const response = await page.goto(target.path, { waitUntil: "domcontentloaded" });
    expect(response, "navigation returned no response").not.toBeNull();
    test.skip(
      target.pending && response.status() === 404,
      `${target.path} does not exist yet: run sitepackage:seed-netresearch-talk.`,
    );
    expect(response.status()).toBe(200);

    await expect(page.locator("h1")).toHaveCount(1);
    const html = await page.content();
    expect(html).not.toContain("{{page:");
    expect(html).not.toContain("{{site:");

    // Lazy images load once they are near the viewport: scroll through, then
    // every content image must have decoded.
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += window.innerHeight / 2) {
        window.scrollTo(0, y);
        await new Promise((resolve) => setTimeout(resolve, 50));
      }
    });
    const broken = await page.locator("main img").evaluateAll((images) => images
      .filter((image) => image.checkVisibility() && image.complete && image.naturalWidth === 0)
      .map((image) => image.currentSrc || image.src));
    expect(broken).toEqual([]);

    const horizontalOverflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    expect(horizontalOverflow).toBe(false);
    expect(failedRequests).toEqual([]);
    expect(browserErrors).toEqual([]);
  });
}
