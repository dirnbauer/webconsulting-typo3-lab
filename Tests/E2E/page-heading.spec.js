import { expect, test } from "@playwright/test";

// Every page has exactly one h1. These are the pages where content renders
// the h1 itself and the theme's page-title h1 has to stand down
// (lib.pageHeadingOwnedByContent in Desiderio 4.4, registered by
// EXT:skillflow 1.8.1 for its detail plugin), plus the rich-text stress page
// whose fixture used to contain nine h1.
const pages = [
  { name: "skill detail", path: "/desiderio-corporate-starter/skill/typo3-testing/" },
  { name: "skill detail without a skill", path: "/desiderio-corporate-starter/skill/", status: 404 },
  { name: "news detail in a blog tree", path: "/blog/news/detail/article/basket-makers-see-early-easter-demand-from-local-markets" },
  { name: "news detail in a blog tree (en)", path: "/blog/en/news/detail/article/basket-makers-see-early-easter-demand-from-local-markets" },
  { name: "news detail", path: "/news/article/inline-editing-for-every-element/" },
  { name: "RTE combinations", path: "/content-types/rte-combinations/" },
  { name: "content page", path: "/desiderio-corporate-starter/advisory-services/" },
];

for (const target of pages) {
  test(`${target.name} has exactly one h1`, async ({ page }) => {
    const response = await page.goto(target.path, { waitUntil: "domcontentloaded" });
    expect(response, "navigation returned no response").not.toBeNull();
    expect(response.status()).toBe(target.status ?? 200);

    await expect(page.locator("h1")).toHaveCount(1);
  });
}
