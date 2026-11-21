<?php

declare(strict_types=1);

/*
 * Static guard for the Composer patches, run by GitHub Actions BEFORE
 * ci-drop-private-packages.php and by Build/Scripts/check-powermail.sh. It needs
 * neither vendor/ nor the patched packages' code, so it also covers the two
 * licensed in2code packages CI cannot install:
 *
 * - every patch composer.json declares (extra.patches) exists under patches/;
 * - patches.lock.json lists exactly these patches (package, description, url),
 *   each with the sha256 of the file and a patch depth, and its "_hash" is the
 *   one cweagans/composer-patches 2 computes;
 * - every top-level patches/*.patch file is declared (no forgotten patch);
 * - every patch file is a well-formed unified diff: file headers name a
 *   relative path inside the package (after stripping "depth" components),
 *   old and new names agree, every hunk's line counts match its header, and
 *   new or deleted files have the empty side the header claims.
 *
 * What it cannot see: whether the hunks still match the package's code. That
 * needs the package: the Coolify image build and check-powermail.sh prove it.
 *
 * Usage: php Build/Scripts/check-patch-declarations.php [--root=<dir>]
 * Plain PHP 8.3+, no vendor/ needed.
 */

namespace Webconsulting\Lab\Build;

require_once __DIR__ . '/ci-drop-private-packages.php';

final class PatchDeclarations
{
    /**
     * @return list<string> problems; empty when everything is consistent
     */
    public static function check(string $root): array
    {
        $problems = [];
        $composerJson = self::readFile($root . '/composer.json');
        if ($composerJson === null) {
            return ['composer.json is missing.'];
        }
        $composer = json_decode($composerJson, true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($composer)) {
            return ['composer.json is not a JSON object.'];
        }
        $declared = self::declaredPatches($composer, $problems);

        $lockJson = self::readFile($root . '/patches.lock.json');
        if ($lockJson === null) {
            if ($declared !== []) {
                $problems[] = 'patches.lock.json is missing although composer.json declares patches (run "composer patches-relock").';
            }

            return $problems;
        }
        $lock = json_decode($lockJson, true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($lock)) {
            return [...$problems, 'patches.lock.json is not a JSON object.'];
        }
        $expectedHash = CiDropPrivatePackages::patchesLockHash($lockJson);
        if (($lock['_hash'] ?? null) !== $expectedHash) {
            $problems[] = sprintf('patches.lock.json: "_hash" is %s, but its content hashes to %s (edited by hand? run "composer patches-relock").', is_string($lock['_hash'] ?? null) ? $lock['_hash'] : 'missing', $expectedHash);
        }
        $locked = self::lockedPatches($lock, $problems);

        foreach ($declared as $key => $patch) {
            if (!isset($locked[$key])) {
                $problems[] = sprintf('%s: patch "%s" (%s) is declared in composer.json but not in patches.lock.json (run "composer patches-relock").', $patch['package'], $patch['description'], $patch['url']);
            }
        }
        foreach ($locked as $key => $patch) {
            if (!isset($declared[$key])) {
                $problems[] = sprintf('%s: patches.lock.json lists "%s" (%s), which composer.json does not declare (run "composer patches-relock").', $patch['package'], $patch['description'], $patch['url']);
                continue;
            }
            $file = $root . '/' . $patch['url'];
            if (!self::isLocalPatchPath($patch['url'])) {
                $problems[] = sprintf('%s: patch path "%s" must be a relative path below patches/.', $patch['package'], $patch['url']);
                continue;
            }
            if (!is_file($file)) {
                $problems[] = sprintf('%s: the patch file %s does not exist.', $patch['package'], $patch['url']);
                continue;
            }
            $hash = hash_file('sha256', $file);
            if ($patch['sha256'] !== $hash) {
                $problems[] = sprintf('%s: %s has sha256 %s, patches.lock.json expects %s (changed without "composer patches-relock").', $patch['package'], $patch['url'], (string)$hash, $patch['sha256'] ?? 'none');
            }
            if ($patch['depth'] === null) {
                $problems[] = sprintf('%s: %s has no patch depth in patches.lock.json.', $patch['package'], $patch['url']);
                continue;
            }
            foreach (self::diffProblems((string)file_get_contents($file), $patch['depth']) as $problem) {
                $problems[] = sprintf('%s: %s: %s', $patch['package'], $patch['url'], $problem);
            }
        }

        $declaredUrls = array_column($declared, 'url');
        foreach (glob($root . '/patches/*.patch') ?: [] as $file) {
            $relative = 'patches/' . basename($file);
            if (!in_array($relative, $declaredUrls, true)) {
                $problems[] = sprintf('%s is not declared in composer.json extra.patches (declare it or move it out of patches/).', $relative);
            }
        }

        return $problems;
    }

    /**
     * @param array<mixed> $composer
     * @param list<string> $problems
     * @return array<string, array{package: string, description: string, url: string}>
     */
    private static function declaredPatches(array $composer, array &$problems): array
    {
        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $patches = $extra['patches'] ?? [];
        if (!is_array($patches)) {
            $problems[] = 'composer.json: extra.patches must be an object.';

            return [];
        }
        $declared = [];
        foreach ($patches as $package => $list) {
            if (!is_array($list)) {
                $problems[] = sprintf('composer.json: extra.patches["%s"] must be an object of "description": "path".', $package);
                continue;
            }
            foreach ($list as $description => $definition) {
                // Both forms cweagans 2 accepts: "description": "path" and {"description": …, "url": …}.
                if (is_string($definition)) {
                    $url = $definition;
                    $text = (string)$description;
                } elseif (is_array($definition) && is_string($definition['url'] ?? null)) {
                    $url = $definition['url'];
                    $text = is_string($definition['description'] ?? null) ? $definition['description'] : (string)$description;
                } else {
                    $problems[] = sprintf('composer.json: a patch of %s has no path.', $package);
                    continue;
                }
                $declared[self::key((string)$package, $url)] = ['package' => (string)$package, 'description' => $text, 'url' => $url];
            }
        }

        return $declared;
    }

    /**
     * @param array<mixed> $lock
     * @param list<string> $problems
     * @return array<string, array{package: string, description: string, url: string, sha256: ?string, depth: ?int}>
     */
    private static function lockedPatches(array $lock, array &$problems): array
    {
        $locked = [];
        foreach (is_array($lock['patches'] ?? null) ? $lock['patches'] : [] as $package => $list) {
            foreach (is_array($list) ? $list : [] as $entry) {
                if (!is_array($entry) || !is_string($entry['url'] ?? null)) {
                    $problems[] = sprintf('patches.lock.json: an entry of %s has no url.', $package);
                    continue;
                }
                if (($entry['package'] ?? $package) !== $package) {
                    $problems[] = sprintf('patches.lock.json: %s is filed under %s but names the package %s.', $entry['url'], $package, is_string($entry['package'] ?? null) ? $entry['package'] : '?');
                }
                $key = self::key((string)$package, $entry['url']);
                $locked[$key] = [
                    'package' => (string)$package,
                    'description' => is_string($entry['description'] ?? null) ? $entry['description'] : '',
                    'url' => $entry['url'],
                    'sha256' => is_string($entry['sha256'] ?? null) ? $entry['sha256'] : null,
                    'depth' => is_int($entry['depth'] ?? null) ? $entry['depth'] : null,
                ];
            }
        }

        return $locked;
    }

    private static function key(string $package, string $url): string
    {
        return $package . "\0" . $url;
    }

    private static function isLocalPatchPath(string $url): bool
    {
        return str_starts_with($url, 'patches/') && !str_contains($url, '..') && !str_contains($url, '://');
    }

    /**
     * Checks one unified diff without the code it applies to.
     *
     * @return list<string>
     */
    public static function diffProblems(string $diff, int $depth): array
    {
        $problems = [];
        $lines = preg_split('/\r?\n/', $diff) ?: [];
        // A trailing newline leaves one empty element that is not a diff line.
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }
        $count = count($lines);
        $files = 0;
        $gitOld = null;
        $gitNew = null;
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match('#^diff --git (\S+) (\S+)$#', $line, $match) === 1) {
                [$gitOld, $gitNew] = [$match[1], $match[2]];
                continue;
            }
            if (!str_starts_with($line, '--- ') || !str_starts_with($lines[$i + 1] ?? '', '+++ ')) {
                // Preamble, extended git headers (index, new file mode …) or a signature.
                continue;
            }
            $files++;
            $old = self::headerPath(substr($line, 4));
            $new = self::headerPath(substr($lines[$i + 1], 4));
            $label = $new === '/dev/null' ? $old : $new;
            $i++;
            foreach (['old' => $old, 'new' => $new] as $side => $path) {
                if ($path === '/dev/null') {
                    continue;
                }
                $stripped = self::strip($path, $depth);
                if ($stripped === null) {
                    $problems[] = sprintf('the %s path "%s" has fewer than %d leading components to strip (patch depth).', $side, $path, $depth + 1);
                } elseif (str_starts_with($stripped, '/') || in_array('..', explode('/', $stripped), true)) {
                    $problems[] = sprintf('the %s path "%s" leaves the package.', $side, $path);
                }
            }
            if ($old === '/dev/null' && $new === '/dev/null') {
                $problems[] = 'a file header has /dev/null on both sides.';
            }
            if ($old !== '/dev/null' && $new !== '/dev/null' && self::strip($old, $depth) !== self::strip($new, $depth)) {
                $problems[] = sprintf('"%s" and "%s" name different files (renames are not supported by every patcher).', $old, $new);
            }
            if ($gitOld !== null && $gitNew !== null) {
                $expectedOld = $old === '/dev/null' ? $gitOld : $old;
                $expectedNew = $new === '/dev/null' ? $gitNew : $new;
                if ($gitOld !== $expectedOld || $gitNew !== $expectedNew) {
                    $problems[] = sprintf('the "diff --git" line names %s and %s, the file header %s and %s.', $gitOld, $gitNew, $old, $new);
                }
            }
            $gitOld = $gitNew = null;

            $hunks = 0;
            while (preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $lines[$i + 1] ?? '', $hunk) === 1) {
                $i++;
                $hunks++;
                $oldStart = (int)$hunk[1];
                $oldLeft = $hunk[2] === '' ? 1 : (int)$hunk[2];
                $newLeft = ($hunk[4] ?? '') === '' ? 1 : (int)$hunk[4];
                if ($old === '/dev/null' && ($oldStart !== 0 || $oldLeft !== 0)) {
                    $problems[] = sprintf('%s is a new file, but hunk %d expects %d existing lines.', $label, $hunks, $oldLeft);
                }
                if ($new === '/dev/null' && $newLeft !== 0) {
                    $problems[] = sprintf('%s is deleted, but hunk %d leaves %d lines.', $label, $hunks, $newLeft);
                }
                while ($oldLeft > 0 || $newLeft > 0) {
                    $i++;
                    if ($i >= $count) {
                        $problems[] = sprintf('%s: hunk %d ends early (%d old and %d new lines missing).', $label, $hunks, $oldLeft, $newLeft);
                        break 2;
                    }
                    $body = $lines[$i];
                    $marker = $body === '' ? ' ' : $body[0];
                    if ($marker === '\\') {
                        continue;
                    }
                    if ($marker === ' ') {
                        $oldLeft--;
                        $newLeft--;
                    } elseif ($marker === '-') {
                        $oldLeft--;
                    } elseif ($marker === '+') {
                        $newLeft--;
                    } else {
                        $problems[] = sprintf('%s: hunk %d has a line that is neither context, removal nor addition: "%s".', $label, $hunks, substr($body, 0, 60));
                        break 2;
                    }
                    if ($oldLeft < 0 || $newLeft < 0) {
                        $problems[] = sprintf('%s: hunk %d has more lines than its header counts.', $label, $hunks);
                        break 2;
                    }
                }
                // "\ No newline at end of file" after the last counted line.
                while (str_starts_with($lines[$i + 1] ?? '', '\\')) {
                    $i++;
                }
            }
            if ($hunks === 0) {
                $problems[] = sprintf('%s has a file header but no hunk.', $label);
            }
        }
        if ($files === 0) {
            $problems[] = 'it contains no unified diff ("--- " and "+++ " file headers).';
        }

        return $problems;
    }

    /** "a/Classes/X.php\t2026-01-01 …" → "a/Classes/X.php" */
    private static function headerPath(string $value): string
    {
        $path = explode("\t", $value, 2)[0];

        return rtrim($path);
    }

    private static function strip(string $path, int $depth): ?string
    {
        $parts = explode('/', $path);
        if (count($parts) <= $depth) {
            return null;
        }

        return implode('/', array_slice($parts, $depth));
    }

    private static function readFile(string $file): ?string
    {
        return is_file($file) ? (string)file_get_contents($file) : null;
    }

    /** @param list<string> $arguments */
    public static function main(array $arguments, string $defaultRoot): int
    {
        $root = $defaultRoot;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--root=')) {
                $root = substr($argument, 7);
            } else {
                fwrite(\STDERR, "Usage: php Build/Scripts/check-patch-declarations.php [--root=<dir>]\n");

                return 2;
            }
        }
        try {
            $problems = self::check($root);
        } catch (\JsonException $exception) {
            $problems = ['Invalid JSON: ' . $exception->getMessage()];
        }
        if ($problems !== []) {
            foreach ($problems as $problem) {
                fwrite(\STDERR, '::error::' . $problem . "\n");
            }
            fwrite(\STDERR, "Patch declarations: FAIL. Whether the patches still apply to the packages is checked by Build/Scripts/check-powermail.sh (ddev check-powermail) and the Coolify build.\n");

            return 1;
        }
        fwrite(\STDOUT, "Patch declarations: PASS (every declared patch exists, is locked with its sha256 and is a well-formed diff).\n");

        return 0;
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    /** @var list<string> $arguments */
    $arguments = array_values(array_slice($_SERVER['argv'] ?? [], 1));
    exit(PatchDeclarations::main($arguments, dirname(__DIR__, 2)));
}
