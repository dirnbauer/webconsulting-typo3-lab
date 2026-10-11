import { expect, test } from "@playwright/test";

test("the first keyboard stop skips the shared page header", async ({ page }) => {
  await page.goto("/", { waitUntil: "networkidle" });

  await page.keyboard.press("Tab");
  await expect(page.locator(".d-skip-link")).toBeFocused();
  await page.keyboard.press("Enter");
  await expect(page.locator("main#main-content")).toBeFocused();
  await expect(page.locator("main")).toHaveCount(1);
});

test("keyboard users enter the mobile navigation and dismiss one level at a time", async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 1280) > 1024, "The menu toggle is only shown below the desktop breakpoint.");
  await page.goto("/", { waitUntil: "networkidle" });

  const menuToggle = page.locator("[data-d-menu-toggle]");
  const nav = page.locator("[data-d-primary-nav]");
  await menuToggle.focus();
  await menuToggle.press("Enter");
  await expect(menuToggle).toHaveAttribute("aria-expanded", "true");
  await expect(nav.locator("a[href]").first()).toBeFocused();

  const submenuToggle = nav.locator("[data-d-subnav-toggle]:visible").first();
  await submenuToggle.focus();
  await submenuToggle.press("Enter");
  await expect(submenuToggle).toHaveAttribute("aria-expanded", "true");
  await page.keyboard.press("Tab");
  await page.keyboard.press("Escape");
  await expect(submenuToggle).toHaveAttribute("aria-expanded", "false");
  await expect(submenuToggle).toBeFocused();
  await expect(menuToggle).toHaveAttribute("aria-expanded", "true");

  await page.keyboard.press("Escape");
  await expect(menuToggle).toHaveAttribute("aria-expanded", "false");
  await expect(menuToggle).toBeFocused();
  await expect(nav).toBeHidden();
});

test("the legal navigation announces the current page", async ({ page }) => {
  await page.goto("/", { waitUntil: "networkidle" });
  const legalLink = page.locator(".desiderio-footer__legal-link").first();
  const href = await legalLink.getAttribute("href");
  expect(href).toBeTruthy();
  await page.goto(href, { waitUntil: "networkidle" });
  await expect(page.locator('.desiderio-footer__legal-link[aria-current="page"]')).toHaveCount(1);
});

test("selecting a colour scheme restores focus to its disclosure", async ({ page }) => {
  await page.goto("/", { waitUntil: "networkidle" });
  const switcher = page.locator("[data-d-theme-switch]");
  const summary = switcher.locator("summary");
  await summary.focus();
  await summary.press("Enter");
  const darkOption = switcher.locator('[data-d-theme-option="dark"]');
  await darkOption.focus();
  await darkOption.press("Enter");
  await expect(darkOption).toHaveAttribute("aria-pressed", "true");
  await expect(switcher).not.toHaveAttribute("open");
  await expect(summary).toBeFocused();
});
