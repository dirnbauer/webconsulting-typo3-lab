import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

const pages = [
  {
    name: "records list",
    path: "/features/records-list/",
    modernHeading: true,
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
  },
  {
    name: "Astryx",
    path: "/astryx-typo3/",
  },
];

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
    expect(response.status()).toBeLessThan(400);

    const heading = page.locator("h1");
    await expect(heading).toHaveCount(1);
    await expect(heading).toBeVisible();
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
      await expect(heading).toHaveAttribute("data-slot", "typography");
      await expect(heading).toHaveAttribute("data-variant", "h1");

      const headingStyle = await heading.evaluate((element) => {
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

    await expect(page.locator("video")).toHaveCount(0);
    await expect(page.locator('source[type^="video/"]')).toHaveCount(0);
    expect(videoExtensionPattern.test(await page.content())).toBe(false);

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
