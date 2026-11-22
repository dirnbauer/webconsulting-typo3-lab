import { expect, test } from "@playwright/test";

/*
 * The start page switch of the Desiderio site
 * (packages/site_package/Classes/Middleware/HomeProposalSwitch.php).
 * The start page is "Stage and Wall", rendered on the server. #home=classic
 * shows the plain Desiderio start page, #home=b|c the earlier proposals on
 * it; the choice lives in the tab's sessionStorage ("lab-home"), #home=a or
 * #home=off returns to the start page.
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
    if (/Content Security Policy/i.test(text) && /inline script|lab-home|\/Home\/|stage-wall/i.test(text)) {
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

const choice = (page) => page.evaluate(() => document.documentElement.getAttribute("data-lab-home"));
const wallDisplay = (page) => page.evaluate(() => getComputedStyle(document.querySelector(".desiderio-stage-wall")).display);
const stored = (page) => page.evaluate(() => sessionStorage.getItem("lab-home"));

test("without a choice the start page is Stage and Wall, and nothing else loads", async ({ page }) => {
  const seen = watch(page);
  await open(page, "/");
  expect(await choice(page)).toBeNull();
  expect(await page.locator("script[data-lab-home-switch]").count()).toBe(1);
  // The stylesheet is in the server's head, before the first paint.
  expect(await page.locator('head link[rel="stylesheet"][href*="StageWall/stage-wall.css"]').count()).toBe(1);
  expect(await wallDisplay(page)).toBe("grid");
  expect(seen.home).toEqual([]);
  expect(seen.csp).toEqual([]);
});

test("#home=classic shows the plain start page in this tab, #home=a brings Stage and Wall back", async ({ page }) => {
  const seen = watch(page);
  await open(page, "/#home=classic");
  expect(await choice(page)).toBe("classic");
  expect(await wallDisplay(page)).not.toBe("grid");
  expect(await stored(page)).toBe("classic");
  expect(seen.home).toEqual([]);

  await open(page, "/");
  expect(await choice(page)).toBe("classic");

  await open(page, "/#home=a");
  expect(await choice(page)).toBeNull();
  expect(await stored(page)).toBeNull();
  expect(await wallDisplay(page)).toBe("grid");
  expect(seen.csp).toEqual([]);
});

test("#home=b loads proposal b for this tab only; #home=off clears it", async ({ page, context }) => {
  const seen = watch(page);
  await open(page, "/#home=b");
  expect(await choice(page)).toBe("b");
  await expect.poll(() => seen.home.length).toBe(2);
  expect(seen.home.map((r) => r.status)).toEqual([200, 200]);
  expect(seen.home.every((r) => /\/Home\/b\//.test(r.url))).toBe(true);
  expect(await stored(page)).toBe("b");

  seen.home.length = 0;
  await open(page, "/");
  expect(await choice(page)).toBe("b");
  await expect.poll(() => seen.home.length).toBe(2);

  // A new tab starts without the choice.
  const other = await context.newPage();
  const otherSeen = watch(other);
  await open(other, "/");
  expect(await choice(other)).toBeNull();
  expect(otherSeen.home).toEqual([]);
  await other.close();

  seen.home.length = 0;
  await open(page, "/#home=off");
  expect(await choice(page)).toBeNull();
  expect(await stored(page)).toBeNull();
  await open(page, "/");
  expect(await choice(page)).toBeNull();
  expect(seen.home).toEqual([]);
  expect(seen.csp).toEqual([]);
});

test("a choice the earlier switch kept in localStorage is forgotten", async ({ page }) => {
  await open(page, "/resources/");
  await page.evaluate(() => localStorage.setItem("lab-home", "c"));
  const seen = watch(page);
  await open(page, "/");
  expect(await choice(page)).toBeNull();
  expect(await page.evaluate(() => localStorage.getItem("lab-home"))).toBeNull();
  expect(seen.home).toEqual([]);
});

test("a translated start page takes the choice too", async ({ page }) => {
  const seen = watch(page);
  await open(page, "/de/#home=c");
  expect(await choice(page)).toBe("c");
  await expect.poll(() => seen.home.length).toBe(2);
  expect(seen.home.every((r) => /\/Home\/c\//.test(r.url) && r.status === 200)).toBe(true);
  await open(page, "/de/#home=off");
  expect(await choice(page)).toBeNull();
  expect(await wallDisplay(page)).toBe("grid");
});

for (const path of ["/resources/", "/astryx-typo3/"]) {
  test(`${path} stays untouched even with a stored choice`, async ({ page }) => {
    const seen = watch(page);
    await open(page, "/#home=c");
    expect(await choice(page)).toBe("c");
    expect(seen.csp).toEqual([]);
    seen.home.length = 0;
    await open(page, path + "#home=b");
    expect(await choice(page)).toBeNull();
    expect(await page.locator("script[data-lab-home-switch]").count()).toBe(0);
    expect(await page.locator('link[href*="stage-wall.css"]').count()).toBe(0);
    expect(await stored(page)).toBe("c");
    expect(seen.home).toEqual([]);
  });
}

test("a stored choice stays out of frames (Visual Editor, backend preview)", async ({ page }) => {
  const seen = watch(page);
  await open(page, "/#home=c");
  expect(await choice(page)).toBe("c");
  seen.home.length = 0;
  // A same-origin page that frames the start page, like the Visual Editor does.
  await open(page, "/resources/");
  await page.evaluate(() => {
    const frame = document.createElement("iframe");
    frame.src = "/";
    frame.id = "home-frame";
    document.body.appendChild(frame);
  });
  const frame = page.frameLocator("#home-frame");
  await expect(frame.locator("script[data-lab-home-switch]")).toHaveCount(1);
  const handle = await page.locator("#home-frame").elementHandle();
  const inner = await handle.contentFrame();
  await inner.waitForLoadState("load");
  expect(await inner.evaluate(() => document.documentElement.getAttribute("data-lab-home"))).toBeNull();
  expect(await inner.evaluate(() => getComputedStyle(document.querySelector(".desiderio-stage-wall")).display)).toBe("grid");
  expect(seen.home).toEqual([]);
  await open(page, "/#home=off");
});

test("the switch script follows the charset declaration", async ({ request }) => {
  const html = await (await request.get("/")).text();
  const charset = html.search(/<meta charset=/i);
  expect(charset).toBeGreaterThan(-1);
  expect(charset).toBeLessThan(1024);
  expect(html.indexOf("<script data-lab-home-switch")).toBeGreaterThan(charset);
});
