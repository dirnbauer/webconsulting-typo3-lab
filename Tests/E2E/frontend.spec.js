import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

const pages = [
  {
    name: "records list",
    path: "/features/records-list/",
    modernHeading: true,
    heroHeading: true,
    featureFilm: true,
  },
  {
    name: "Powermail support",
    path: "/desiderio-powermail/support/",
    modernHeading: true,
  },
  {
    name: "blog",
    path: "/features/blog",
    modernHeading: true,
    heroHeading: true,
    featureFilm: true,
  },
  {
    name: "Astryx",
    path: "/astryx-typo3/",
  },
  // New in the 2026-11-05 round (desiderio 4.29): the model page with its film
  // (how-we-earn) and the talk page with the long cut of the usability talk.
  // `pending`: the test skips while the page answers 404 (the round's
  // styleguide seed has not run yet) and runs in full once it exists.
  {
    name: "How we earn",
    path: "/pricing/how-we-earn/",
    featureFilm: true,
    pending: true,
  },
  {
    name: "Usability slogans",
    path: "/usability-slogans/",
    featureFilm: true,
    pending: true,
  },
];

/** The round's pages exist once the styleguide seed of 2026-11-05 has run. */
const ROUND_PAGE = "/pricing/how-we-earn/";
async function roundSeeded(request) {
  const response = await request.get(ROUND_PAGE, { ignoreHTTPSErrors: true, maxRedirects: 0 });
  return response.status() !== 404;
}

const videoExtensionPattern = /\.(?:m4v|mov|mp4|og[gv]|webm)(?:[?#"']|$)/i;

for (const target of pages) {
  test(`${target.name} renders its complete styled page`, async ({ page, request }) => {
    const browserErrors = [];
    const failedAssets = [];

    page.on("console", (message) => {
      if (message.type() === "error") {
        browserErrors.push(message.text());
      }
    });
    page.on("pageerror", (error) => browserErrors.push(error.message));
    page.on("response", (response) => {
      const url = new URL(response.url());
      if (
        url.hostname.endsWith(".ddev.site")
        && response.status() >= 400
        && response.request().resourceType() !== "document"
      ) {
        failedAssets.push(`${response.status()} ${response.url()}`);
      }
    });

    const response = await page.goto(target.path, { waitUntil: "domcontentloaded" });
    expect(response, "navigation returned no response").not.toBeNull();
    test.skip(
      Boolean(target.pending) && response.status() === 404,
      `${target.path} does not exist yet: run the styleguide seed of the 2026-11-05 round (desiderio 4.29).`,
    );
    expect(response.status()).toBeLessThan(400);

    const heading = page.locator("h1");
    await expect(heading).toHaveCount(1);
    // Since Desiderio 4.26.2 a page that opens with a hero keeps its page title as
    // a screen-reader-only h1 (tx_desiderio_h1_sronly); the hero headline is the
    // visible heading and carries the h1 typography.
    const visibleHeading = target.heroHeading
      ? page.locator('main [data-slot="typography"][data-variant="h1"]').first()
      : heading;
    if (target.heroHeading) {
      await expect(heading).toHaveClass(/(?:^|\s)sr-only(?:\s|$)/);
    }
    await expect(visibleHeading).toBeVisible();
    await expect(page.locator("body")).not.toHaveCSS("font-family", "Times");

    const stylesheetUrls = await page.locator('link[rel="stylesheet"]').evaluateAll(
      (links) => links.map((link) => link.href),
    );
    expect(stylesheetUrls.some((url) => url.includes("/_assets/vite/assets/Main-"))).toBe(true);
    expect(stylesheetUrls.some((url) => url.includes("vite-webconsulting-typo3-lab"))).toBe(false);

    for (const stylesheetUrl of stylesheetUrls) {
      const stylesheetResponse = await request.get(stylesheetUrl, { ignoreHTTPSErrors: true });
      expect(stylesheetResponse.status(), stylesheetUrl).toBeLessThan(400);
      expect(stylesheetResponse.headers()["content-type"] ?? "", stylesheetUrl).toContain("text/css");
    }

    if (target.modernHeading) {
      await expect(visibleHeading).toHaveAttribute("data-slot", "typography");
      await expect(visibleHeading).toHaveAttribute("data-variant", "h1");

      const headingStyle = await visibleHeading.evaluate((element) => {
        const style = getComputedStyle(element);
        return {
          fontSize: Number.parseFloat(style.fontSize),
          fontWeight: Number.parseInt(style.fontWeight, 10),
        };
      });
      const viewportWidth = page.viewportSize()?.width ?? 1280;
      expect(headingStyle.fontSize).toBeGreaterThanOrEqual(viewportWidth < 640 ? 30 : 40);
      expect(headingStyle.fontWeight).toBeGreaterThanOrEqual(600);
    }

    if (target.featureFilm) {
      // Feature pages carry their film (sitepackage:films:seed): one native player
      // with controls and a poster, nothing that plays as a background.
      const film = page.locator("main video");
      await expect(film).toHaveCount(1);
      await expect(film).toHaveAttribute("controls", "");
      await expect(film).toHaveAttribute("poster", /\S/);
      await expect(page.locator("video")).toHaveCount(1);
    } else {
      await expect(page.locator("video")).toHaveCount(0);
      await expect(page.locator('source[type^="video/"]')).toHaveCount(0);
      expect(videoExtensionPattern.test(await page.content())).toBe(false);
    }

    const horizontalOverflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    expect(horizontalOverflow).toBe(false);

    const accessibility = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"])
      .analyze();
    const seriousViolations = accessibility.violations.filter(
      (violation) => violation.impact === "serious" || violation.impact === "critical",
    );
    expect(seriousViolations).toEqual([]);
    expect(failedAssets).toEqual([]);
    expect(browserErrors).toEqual([]);
  });
}

// The home page sells Desiderio (plan 2026-11-05, D5–D7): of its films only the
// product film stays. The strategy film stays on /typo3-v14-strategy, the lab
// film moves to /resources and the usability talk to /usability-slogans.
test("the home page carries one film block, the product film", async ({ page, request }) => {
  test.skip(
    !(await roundSeeded(request)),
    `${ROUND_PAGE} does not exist yet, so the home page still has four films: run the styleguide seed of the 2026-11-05 round.`,
  );
  const response = await page.goto("/", { waitUntil: "domcontentloaded" });
  expect(response, "navigation returned no response").not.toBeNull();
  expect(response.status()).toBe(200);
  await expect(page.locator("h1")).toHaveCount(1);
  await expect(page.locator("main section.feature-video")).toHaveCount(1);
  await expect(page.locator('main video source[src*="/fileadmin/films/"]')).toHaveCount(0);
});

// Lab and resources (plan 2026-11-05, 5.5): the lab overview film after the hero.
test("the lab and resources page plays the lab overview film", async ({ page, request }) => {
  test.skip(
    !(await roundSeeded(request)),
    `${ROUND_PAGE} does not exist yet, so the lab film is still on the home page: run the styleguide seed and sitepackage:films:seed.`,
  );
  const response = await page.goto("/resources/", { waitUntil: "domcontentloaded" });
  expect(response, "navigation returned no response").not.toBeNull();
  expect(response.status()).toBe(200);
  await expect(page.locator("h1")).toHaveCount(1);
  await expect(page.locator("main video")).toHaveCount(1);
  await expect(page.locator('main video source[src*="/films/lab-overview/"]')).toHaveCount(1);
});
