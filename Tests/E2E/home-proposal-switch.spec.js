import { expect, test } from "@playwright/test";

/*
 * The start page proposal switch of the Desiderio site
 * (packages/site_package/Classes/Middleware/HomeProposalSwitch.php):
 * #home=a|b|c picks a presentation-only overlay, localStorage "lab-home"
 * remembers it, #home=off forgets it. Without a choice the page is unchanged.
 */

const homeFile = /\/Home\/[abc]\/lab-home\.(css|js)(\?|$)/;

function watch(page) {
  const seen = { home: [], csp: [] };
  page.on("response", (response) => {
    if (homeFile.test(new URL(response.url()).pathname + "?")) {
      seen.home.push({ url: response.url(), status: response.status() });
    }
  });
  // Violations the switch could cause: its inline script, or the proposal
  // files. (The lab's report-only policy also reports a data: font and, on
  // Astryx, an inline script and style of the theme; those are older.)
  page.on("console", (message) => {
    const text = message.text();
    if (/Content Security Policy/i.test(text) && /inline script|lab-home|\/Home\//i.test(text)) {
      seen.csp.push(text);
    }
  });
  return seen;
}

async function open(page, path) {
  // From about:blank, so a fragment change is always a full page load.
  await page.goto("about:blank");
  const response = await page.goto(path, { waitUntil: "load" });
  expect(response?.status()).toBe(200);
}

const proposal = (page) => page.evaluate(() => document.documentElement.getAttribute("data-lab-home"));

test("without a choice the start page has no attribute and loads no proposal", async ({ page }) => {
  const seen = watch(page);
  await open(page, "/");
  expect(await proposal(page)).toBeNull();
  expect(await page.locator("script[data-lab-home-switch]").count()).toBe(1);
  expect(seen.home).toEqual([]);
  expect(seen.csp).toEqual([]);
});

test("#home=a loads proposal a, survives a reload and #home=off clears it", async ({ page }) => {
  const seen = watch(page);
  await open(page, "/#home=a");
  expect(await proposal(page)).toBe("a");
  await expect.poll(() => seen.home.length).toBe(2);
  expect(seen.home.map((r) => r.status)).toEqual([200, 200]);
  expect(seen.home.map((r) => new URL(r.url).pathname).sort()).toEqual([
    expect.stringMatching(/\/Home\/a\/lab-home\.css$/),
    expect.stringMatching(/\/Home\/a\/lab-home\.js$/),
  ]);
  expect(await page.evaluate(() => localStorage.getItem("lab-home"))).toBe("a");

  seen.home.length = 0;
  await open(page, "/");
  expect(await proposal(page)).toBe("a");
  await expect.poll(() => seen.home.length).toBe(2);

  seen.home.length = 0;
  await open(page, "/#home=off");
  expect(await proposal(page)).toBeNull();
  expect(await page.evaluate(() => localStorage.getItem("lab-home"))).toBeNull();
  await open(page, "/");
  expect(await proposal(page)).toBeNull();
  expect(seen.home).toEqual([]);
  expect(seen.csp).toEqual([]);
});

test("a translated start page takes the choice too", async ({ page }) => {
  const seen = watch(page);
  await open(page, "/de/#home=b");
  expect(await proposal(page)).toBe("b");
  await expect.poll(() => seen.home.length).toBe(2);
  expect(seen.home.every((r) => /\/Home\/b\//.test(r.url) && r.status === 200)).toBe(true);
  await open(page, "/de/#home=off");
});

for (const path of ["/resources/", "/astryx-typo3/"]) {
  test(`${path} stays untouched even with a stored choice`, async ({ page }) => {
    const seen = watch(page);
    await open(page, "/#home=c");
    expect(await proposal(page)).toBe("c");
    expect(seen.csp).toEqual([]);
    seen.home.length = 0;
    await open(page, path + "#home=a");
    expect(await proposal(page)).toBeNull();
    expect(await page.locator("script[data-lab-home-switch]").count()).toBe(0);
    expect(await page.evaluate(() => localStorage.getItem("lab-home"))).toBe("c");
    expect(seen.home).toEqual([]);
  });
}
