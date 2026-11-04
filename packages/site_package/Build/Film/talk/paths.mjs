/**
 * Shared paths of the talk films (portrait 1080 × 1920, 25 fps).
 *
 * Inputs: var/heygen-usability/render/<cut>-presenter.mp4 (clean HeyGen
 * presenter renders, no overlays). Work files: ~/.cache/lab-films/talk/
 * (TALK_CACHE overrides). Outputs: var/heygen-usability/out/.
 */

import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const TALK = dirname(fileURLToPath(import.meta.url));
export const LAB = resolve(TALK, '../../../../..');
export const RENDER = process.env.TALK_RENDER ?? join(LAB, 'var/heygen-usability/render');
export const OUT = process.env.TALK_OUT ?? join(LAB, 'var/heygen-usability/out');
export const CACHE = process.env.TALK_CACHE ?? join(homedir(), '.cache/lab-films/talk');
export const FPS = 25;
export const SIZE = { width: 1080, height: 1920 };

export const argument = (name) => process.argv.find((arg) => arg === `--${name}` || arg.startsWith(`--${name}=`))?.split('=').slice(1).join('=') || (process.argv.includes(`--${name}`) ? '' : undefined);

/**
 * Playwright from the lab's node_modules; a worktree without them falls back
 * to the main checkout (PLAYWRIGHT_FROM overrides).
 */
export function playwright() {
    const candidates = [process.env.PLAYWRIGHT_FROM, join(LAB, 'node_modules'), join(LAB, 'packages/desiderio/node_modules'), join(homedir(), 'projects/webconsulting-typo3-lab/node_modules')].filter(Boolean);
    const from = candidates.find((dir) => existsSync(join(dir, 'playwright/package.json')));
    if (!from) throw new Error(`Playwright not found in ${candidates.join(', ')}`);
    return createRequire(join(from, 'noop.js'))('playwright');
}
