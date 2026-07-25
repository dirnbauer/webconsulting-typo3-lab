#!/usr/bin/env node
/**
 * Generates the element library's demo media from desiderio's prompt manifest.
 *
 * Lives in the lab rather than in desiderio on purpose: desiderio must not gain
 * a dependency on nr-llm to stay installable on its own. The OpenAI credential
 * is never read here either - we shell out to `typo3 sitepackage:llm:generate-image`,
 * which resolves it from the nr-vault inside the container.
 *
 * Output filenames carry a content hash (lib-<role>-<slug>-<hash8>.webp) because
 * ExtensionFalSeeder imports assets by basename into one flat fileadmin folder
 * and short-circuits on a name it has already imported. Without the hash a
 * regenerated image would never reach an existing installation.
 *
 * Usage:
 *   node generate-library-images.mjs [--only <slug|role>] [--dry-run] [--force]
 *     --manifest <path>   default: ../desiderio/Build/Data/library-image-prompts.json
 *     --out <path>        default: ../desiderio/Resources/Public/Styleguide/Library
 */

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, unlinkSync, writeFileSync } from 'node:fs';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const LAB_ROOT = resolve(HERE, '../../../..');
const HOME = process.env.HOME ?? '';

const argv = process.argv.slice(2);
const flag = (name, fallback = null) => {
    const i = argv.indexOf(`--${name}`);
    return i === -1 ? fallback : argv[i + 1];
};
const has = (name) => argv.includes(`--${name}`);

const MANIFEST = resolve(flag('manifest', join(HOME, 'projects/desiderio/Build/Data/library-image-prompts.json')));
const OUT_DIR = resolve(flag('out', join(HOME, 'projects/desiderio/Resources/Public/Styleguide/Library')));
const ONLY = flag('only');
const DRY_RUN = has('dry-run');
const FORCE = has('force');

// Relative to the lab root, because the TYPO3 command resolves a relative
// --output against the project path inside the container.
const STAGING_REL = 'var/generated-images';
const STAGING_ABS = join(LAB_ROOT, STAGING_REL);

const manifest = JSON.parse(readFileSync(MANIFEST, 'utf8'));
const styles = manifest.styles ?? {};
const images = (manifest.images ?? []).filter(
    (image) => !ONLY || image.slug === ONLY || image.role === ONLY,
);

if (images.length === 0) {
    console.error(ONLY ? `No manifest entry matches "${ONLY}".` : 'Manifest contains no images.');
    process.exit(1);
}

mkdirSync(OUT_DIR, { recursive: true });

const sh = (cmd, args, opts = {}) =>
    execFileSync(cmd, args, { cwd: LAB_ROOT, encoding: 'utf8', stdio: 'pipe', ...opts });

/** Existing output for a slug, regardless of its content hash. */
const existingFor = (role, slug) =>
    readdirSync(OUT_DIR).filter((name) => name.startsWith(`lib-${role}-${slug}-`));

let generated = 0;
let skipped = 0;
const failures = [];

for (const image of images) {
    const { slug, role, style, size, subject, alt } = image;
    const stem = `lib-${role}-${slug}`;

    const already = existingFor(role, slug);
    if (already.length > 0 && !FORCE) {
        console.log(`· skip   ${stem} (have ${already[0]})`);
        skipped++;
        continue;
    }

    const prompt = [styles[style], subject].filter(Boolean).join(' ');
    if (DRY_RUN) {
        console.log(`· dry    ${stem} [${size}]\n         ${prompt}`);
        continue;
    }

    // The TYPO3 command refuses to overwrite, so clear the staging file first.
    const stagingRel = `${STAGING_REL}/${stem}.png`;
    const stagingAbs = join(STAGING_ABS, `${stem}.png`);
    if (existsSync(stagingAbs)) {
        unlinkSync(stagingAbs);
    }

    // The API drops the occasional request with a transient error; one retry
    // costs about two cents and saves a manual re-run of the whole batch.
    let lastError = null;
    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            sh('ddev', [
                'typo3',
                'sitepackage:llm:generate-image',
                prompt,
                `--size=${size}`,
                '--model=gpt-image-2',
                '--configuration=image-generation',
                `--output=${stagingRel}`,
            ]);
            lastError = null;
            break;
        } catch (error) {
            lastError = error;
            sh('ddev', ['exec', 'rm', '-f', `/var/www/html/${stagingRel}`]);
            if (attempt < 3) {
                console.log(`  retry  ${stem} (attempt ${attempt + 1})`);
            }
        }
    }
    if (lastError) {
        const detail = `${lastError.stdout ?? ''}${lastError.stderr ?? ''}`.trim().split('\n').slice(-2).join(' ');
        console.error(`✗ fail   ${stem}: ${detail || lastError.message}`);
        failures.push(slug);
        continue;
    }

    // var/ is outside the mutagen sync, so the generated file exists only in
    // the container. Stream it out rather than waiting for a sync that never
    // comes.
    mkdirSync(STAGING_ABS, { recursive: true });
    const bytes = execFileSync('ddev', ['exec', 'cat', `/var/www/html/${stagingRel}`], {
        cwd: LAB_ROOT,
        maxBuffer: 64 * 1024 * 1024,
    });
    if (bytes.length === 0) {
        console.error(`✗ fail   ${stem}: generated file was empty`);
        failures.push(slug);
        continue;
    }
    writeFileSync(stagingAbs, bytes);
    sh('ddev', ['exec', 'rm', '-f', `/var/www/html/${stagingRel}`]);

    // Logos come back as a lockup floating in a square of white. A logo strip
    // constrains height, so all that margin renders the wordmark tiny. Trim to
    // the ink and re-pad to a small even border, which is what a real logo file
    // looks like. Only logos: badges are circular seals and illustrations are
    // composed for a square frame, so both keep their bleed.
    if (role === 'logo') {
        try {
            sh('magick', [stagingAbs, '-fuzz', '4%', '-trim', '+repage', '-bordercolor', 'white', '-border', '24', stagingAbs]);
        } catch {
            // Trimming is cosmetic; an untrimmed logo still renders correctly.
        }
    }

    const finalBytes = readFileSync(stagingAbs);
    const hash = createHash('sha256').update(finalBytes).digest('hex').slice(0, 8);
    const target = join(OUT_DIR, `${stem}-${hash}.webp`);

    // Ship WebP: 5-8x smaller than PNG for photographic content, and already
    // the format used by the video posters in Styleguide/Video.
    try {
        sh('cwebp', ['-quiet', '-q', role === 'portrait' || role === 'logo' || role === 'badge' || role === 'illustration' ? '85' : '82', '-metadata', 'none', stagingAbs, '-o', target]);
    } catch {
        // macOS ships sips; no cwebp needed for a one-off run.
        sh('sips', ['-s', 'format', 'webp', stagingAbs, '--out', target], { stdio: 'ignore' });
    }

    for (const stale of already) {
        rmSync(join(OUT_DIR, stale));
    }
    unlinkSync(stagingAbs);

    const kb = Math.round(readFileSync(target).length / 1024);
    console.log(`✓ ${String(++generated).padStart(3)}    ${basename(target)} (${kb} KB)`);
}

console.log(`\n${generated} generated, ${skipped} skipped, ${failures.length} failed`);
if (failures.length > 0) {
    console.error(`failed slugs: ${failures.join(', ')}`);
    process.exit(1);
}
