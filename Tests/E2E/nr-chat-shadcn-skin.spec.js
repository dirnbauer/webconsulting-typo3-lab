import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

/*
 * The shadcn/ui skin of the Netresearch AI Chat (EXT:nr_mcp_agent), set by
 * packages/site_package/Resources/Public/Css/Backend/nr-chat-shadcn.css.
 *
 * The chat renders in shadow DOM, so the checks read computed styles through
 * the shadow roots: the stylesheet is loaded in the module iframe and in the
 * top frame, the font and colours resolve to Desiderio's preset b0 in light
 * and in dark, and the main pairs keep WCAG AA. The colour probes are elements
 * with the chat's own class names, appended to its shadow root and removed
 * again. Nothing is sent to the chat: the test never creates a conversation and
 * fails if a request to send one leaves the browser.
 *
 * Needs a backend login with access to the chat module:
 *   PLAYWRIGHT_BACKEND_CREDENTIALS_FILE=<json with username and password>
 * (the lab uses ~/.config/typewright/lab-e2e-login.json). Without it the test
 * is skipped. The login uses the password provider, because the lab's default
 * provider is WorkOS.
 */

const credentialsFile = process.env.PLAYWRIGHT_BACKEND_CREDENTIALS_FILE;
const credentials = credentialsFile ? JSON.parse(readFileSync(credentialsFile, 'utf8')) : null;
const moduleUrl = '/typo3/module/nr/mcp/agent/chat/conversation';

// Desiderio preset b0 (packages/desiderio Resources/Public/Css/shadcn-theme.css),
// as sRGB after Chrome's oklch conversion.
const expected = {
  light: {
    surface: '#ffffff', popover: '#ffffff', foreground: '#0a0a0a', primary: '#171717',
    primaryForeground: '#fafafa', muted: '#f5f5f5', ring: '#555555',
  },
  dark: {
    surface: '#0a0a0a', popover: '#171717', foreground: '#fafafa', primary: '#e5e5e5',
    primaryForeground: '#171717', muted: '#262626', ring: '#aeaeae',
  },
};

test.describe('Netresearch AI Chat in the shadcn/ui look', () => {
  test.skip(!credentials, 'PLAYWRIGHT_BACKEND_CREDENTIALS_FILE is not set');

  test('the skin reaches the module and the toolbar panel in light and dark', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium-desktop', 'backend check, desktop only');
    test.setTimeout(120_000);

    const sendRequests = [];
    page.on('request', (request) => {
      if (/\/ai-chat\/conversations\/(create|send|edit|approve|input|resume)\b/.test(request.url())) {
        sendRequests.push(request.url());
      }
    });

    await page.goto('/typo3/login?loginProvider=1433416747');
    await page.fill('#t3-username', credentials.username);
    await page.fill('#t3-password', credentials.password);
    await Promise.all([
      page.waitForURL(/\/typo3\/(main|module)/, { timeout: 90_000 }),
      page.click('#t3-login-submit'),
    ]);

    await page.goto(moduleUrl);
    const chatFrame = page.frameLocator('#typo3-contentIframe');
    await expect(chatFrame.locator('nr-chat-app')).toHaveCount(1, { timeout: 30_000 });
    const frame = page.frames().find((candidate) => candidate !== page.mainFrame() && candidate.url().includes('/nr/mcp/agent/chat'));
    expect(frame, 'module iframe').toBeTruthy();

    const stylesheet = 'link[rel="stylesheet"][href*="Css/Backend/nr-chat-shadcn.css"]';
    await expect(chatFrame.locator(stylesheet)).toHaveCount(1);
    await expect(page.locator(stylesheet)).toHaveCount(1);
    await expect(page.locator('ai-chat-panel')).toHaveCount(1);

    const font = await frame.evaluate(async () => {
      await document.fonts.ready;
      return getComputedStyle(document.querySelector('nr-chat-app')).fontFamily;
    });
    expect(font).toContain('Inter Variable');

    for (const scheme of ['light', 'dark']) {
      for (const target of [page.mainFrame(), frame]) {
        await target.evaluate((value) => { document.documentElement.dataset.colorScheme = value; }, scheme);
      }
      const module = await frame.evaluate(probe, 'nr-chat-app');
      const panel = await page.evaluate(probe, 'ai-chat-panel');
      const want = expected[scheme];

      expect(module.host, `${scheme}: module surface`).toBe(want.surface);
      expect(module.userBubble, `${scheme}: user bubble`).toEqual({ bg: want.primary, fg: want.primaryForeground });
      expect(module.assistantBubble, `${scheme}: assistant bubble`).toEqual({ bg: want.muted, fg: want.foreground });
      expect(module.focusRing, `${scheme}: focus ring`).toBe(want.ring);
      expect(module.ratios.userBubble, `${scheme}: user bubble contrast`).toBeGreaterThanOrEqual(4.5);
      expect(module.ratios.mutedOnSidebar, `${scheme}: muted text on the sidebar`).toBeGreaterThanOrEqual(4.5);
      expect(module.ratios.mutedOnMuted, `${scheme}: muted text in a tool trace`).toBeGreaterThanOrEqual(4.5);
      expect(module.ratios.inputEdge, `${scheme}: composer edge`).toBeGreaterThanOrEqual(3);

      expect(panel.host, `${scheme}: panel surface`).toBe(want.popover);
      expect(panel.userBubble, `${scheme}: panel user bubble`).toEqual({ bg: want.primary, fg: want.primaryForeground });
    }

    expect(sendRequests, 'no chat request was sent').toEqual([]);
  });
});

/**
 * Runs in the page: resolves the chat's colours inside the host's shadow root
 * and returns them as #rrggbb, plus the contrast of the main pairs.
 */
function probe(hostSelector) {
  const host = document.querySelector(hostSelector);
  const root = host.shadowRoot;
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = 1;
  const context = canvas.getContext('2d', { willReadFrequently: true });
  const rgba = (color) => {
    context.clearRect(0, 0, 1, 1);
    context.fillStyle = color;
    context.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = context.getImageData(0, 0, 1, 1).data;
    return [r, g, b, a / 255];
  };
  const over = (top, under) => [0, 1, 2].map((i) => top[i] * top[3] + under[i] * (1 - top[3]));
  const hex = (rgb) => '#' + rgb.slice(0, 3).map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
  const luminance = (rgb) => {
    const channel = (v) => { const s = v / 255; return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * channel(rgb[0]) + 0.7152 * channel(rgb[1]) + 0.0722 * channel(rgb[2]);
  };
  const ratio = (a, b) => {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return Math.round(((hi + 0.05) / (lo + 0.05)) * 100) / 100;
  };
  const hostBackground = rgba(getComputedStyle(host).backgroundColor);
  const background = (element) => {
    const layers = [];
    for (let node = element; node; node = node.parentNode instanceof ShadowRoot ? node.parentNode.host : node.parentElement) {
      const color = rgba(getComputedStyle(node).backgroundColor);
      if (color[3] > 0) layers.push(color);
      if (color[3] >= 1) break;
    }
    return layers.reverse().reduce((under, layer) => over(layer, under), [255, 255, 255]);
  };
  const measure = (markup, read) => {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = markup;
    root.appendChild(wrapper);
    try {
      return read(wrapper.querySelector('#probe'));
    } finally {
      wrapper.remove();
    }
  };
  const pair = (element) => {
    const bg = background(element);
    return { bg, fg: over(rgba(getComputedStyle(element).color), bg) };
  };

  const user = measure('<div class="messages"><div class="message user" id="probe">x</div></div>', pair);
  const assistant = measure('<div class="messages"><div class="message assistant" id="probe">x</div></div>', pair);
  const mutedOnSidebar = measure('<div class="sidebar"><div class="conversation-item"><span class="conv-summary-meta" id="probe">x</span></div></div>', pair);
  const mutedOnMuted = measure('<div class="messages"><div class="message tool expanded" id="probe">x</div></div>', pair);
  const ring = measure('<div id="probe" style="color: var(--nr-chat-focus-ring)">x</div>', (element) => rgba(getComputedStyle(element).color));
  const inputEdge = measure('<div class="input-area"><div class="input-wrap" id="probe">x</div></div>', (element) => {
    const bg = background(element.parentElement);
    return ratio(over(rgba(getComputedStyle(element).borderTopColor), bg), bg);
  });

  return {
    host: hex(hostBackground),
    userBubble: { bg: hex(user.bg), fg: hex(user.fg) },
    assistantBubble: { bg: hex(assistant.bg), fg: hex(assistant.fg) },
    focusRing: hex(ring),
    ratios: {
      userBubble: ratio(user.fg, user.bg),
      mutedOnSidebar: ratio(mutedOnSidebar.fg, mutedOnSidebar.bg),
      mutedOnMuted: ratio(mutedOnMuted.fg, mutedOnMuted.bg),
      inputEdge,
    },
  };
}
