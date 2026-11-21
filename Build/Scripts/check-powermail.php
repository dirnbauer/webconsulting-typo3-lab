<?php

declare(strict_types=1);

/*
 * The PHP half of Build/Scripts/check-powermail.sh (ddev check-powermail), the
 * local proof that the two licensed in2code packages (Powermail 14 and
 * Powermail Conditions 14 from in2code's Early Access Packagist) are installed
 * exactly as composer.lock says and carry the lab's patches. GitHub CI cannot
 * check this: it has no copy of the token (docs/coolify-deployment.md).
 *
 * Subcommands (all read-only, plain PHP 8.3+, no vendor/ needed):
 *
 *   installed    the installed version and dist/source reference of both
 *                packages equal composer.lock, they are real directories (not
 *                lab-link symlinks), and their lock URLs carry no credentials
 *   patches      every lab patch of both packages has the sha256
 *                patches.lock.json records, and the installed code contains
 *                all of them: a scratch copy of each package is un-patched
 *                with GNU patch in reverse order, without fuzz, the way the
 *                patches were applied
 *   jev-source   prints "<url> <commit>" of the locked webconsulting/webcon-jev
 *                source, for running its unit tests against this vendor/
 *   scan-token   reads ONE secret from stdin (never from an argument) and lists
 *                every regular file below the given paths that contains it;
 *                exit 1 when one does. --git-files adds the git working tree
 *                files (tracked and untracked, not ignored) of --root.
 *
 * Usage: php Build/Scripts/check-powermail.php <subcommand> [--root=<dir>] [paths…]
 */

namespace Webconsulting\Lab\Build;

final class PowermailCheck
{
    /** The packages only in2code's Early Access Packagist serves (CiDropPrivatePackages::PRIVATE_PACKAGES). */
    public const PACKAGES = ['in2code/powermail', 'in2code/powermail_cond'];

    /** Directories the token scan never descends into: large, binary, or not ours to judge. */
    public const SCAN_SKIP_DIRECTORIES = ['.git', 'node_modules', 'browsers', 'db_snapshots'];

    /**
     * @return list<string> problems
     */
    public static function installedProblems(string $composerLock, string $installedJson): array
    {
        $lock = json_decode($composerLock, true, 512, \JSON_THROW_ON_ERROR);
        $installed = json_decode($installedJson, true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($lock) || !is_array($installed)) {
            return ['composer.lock or vendor/composer/installed.json is not a JSON object.'];
        }
        $locked = self::indexByName(array_merge(self::listOf($lock['packages'] ?? []), self::listOf($lock['packages-dev'] ?? [])));
        $present = self::indexByName(self::listOf($installed['packages'] ?? $installed));

        $problems = [];
        foreach (self::PACKAGES as $name) {
            $expected = $locked[$name] ?? null;
            $actual = $present[$name] ?? null;
            if ($expected === null) {
                $problems[] = sprintf('%s is not in composer.lock.', $name);
                continue;
            }
            foreach (['dist', 'source'] as $kind) {
                $url = is_array($expected[$kind] ?? null) && is_string($expected[$kind]['url'] ?? null) ? $expected[$kind]['url'] : '';
                if ($url !== '' && self::urlHasCredentials($url)) {
                    $problems[] = sprintf('composer.lock: the %s URL of %s carries credentials; the lock must hold credential-free URLs only.', $kind, $name);
                }
            }
            if ($actual === null) {
                $problems[] = sprintf('%s %s is locked but not installed (run composer install).', $name, self::text($expected['version'] ?? null));
                continue;
            }
            if (self::text($expected['version'] ?? null) !== self::text($actual['version'] ?? null)) {
                $problems[] = sprintf('%s: installed %s, composer.lock says %s (run composer install).', $name, self::text($actual['version'] ?? null), self::text($expected['version'] ?? null));
            }
            $expectedReference = self::reference($expected);
            $actualReference = self::reference($actual);
            if ($expectedReference !== $actualReference) {
                $problems[] = sprintf('%s: installed reference %s, composer.lock says %s (run composer install).', $name, $actualReference, $expectedReference);
            }
            if (self::text($actual['installation-source'] ?? 'dist') !== 'dist') {
                $problems[] = sprintf('%s is installed from %s; the image installs the dist the lock names.', $name, self::text($actual['installation-source'] ?? null));
            }
        }

        return $problems;
    }

    /**
     * Install directory of a package, from installed.json's "install-path"
     * (relative to vendor/composer), or vendor/<name>.
     */
    public static function installPath(string $root, string $installedJson, string $name): string
    {
        $installed = json_decode($installedJson, true, 512, \JSON_THROW_ON_ERROR);
        foreach (self::listOf(is_array($installed) ? ($installed['packages'] ?? $installed) : []) as $package) {
            if (($package['name'] ?? null) === $name && is_string($package['install-path'] ?? null)) {
                return self::normalize($root . '/vendor/composer/' . $package['install-path']);
            }
        }

        return $root . '/vendor/' . $name;
    }

    /**
     * @return list<array{package: string, url: string, sha256: ?string, depth: int}>
     */
    public static function lockedPatches(string $patchesLock): array
    {
        $lock = json_decode($patchesLock, true, 512, \JSON_THROW_ON_ERROR);
        $patches = [];
        foreach (self::PACKAGES as $name) {
            foreach (self::listOf(is_array($lock) && is_array($lock['patches'] ?? null) ? ($lock['patches'][$name] ?? []) : []) as $entry) {
                if (!is_string($entry['url'] ?? null)) {
                    continue;
                }
                $patches[] = [
                    'package' => $name,
                    'url' => $entry['url'],
                    'sha256' => is_string($entry['sha256'] ?? null) ? $entry['sha256'] : null,
                    'depth' => is_int($entry['depth'] ?? null) ? $entry['depth'] : 1,
                ];
            }
        }

        return $patches;
    }

    /**
     * Proves that every locked patch is contained in the installed package:
     * a scratch copy is un-patched in reverse order (GNU patch -R, no fuzz).
     *
     * @return array{problems: list<string>, applied: list<string>}
     */
    public static function patchProblems(string $root, string $patchBinary = 'patch'): array
    {
        $problems = [];
        $applied = [];
        $patchesLock = self::read($root . '/patches.lock.json');
        $installedJson = self::read($root . '/vendor/composer/installed.json');
        if ($patchesLock === null || $installedJson === null) {
            return ['problems' => ['patches.lock.json or vendor/composer/installed.json is missing.'], 'applied' => []];
        }
        $byPackage = [];
        foreach (self::lockedPatches($patchesLock) as $patch) {
            $byPackage[$patch['package']][] = $patch;
        }
        foreach (self::PACKAGES as $name) {
            if (!isset($byPackage[$name])) {
                continue;
            }
            $directory = self::installPath($root, $installedJson, $name);
            if (!is_dir($directory)) {
                $problems[] = sprintf('%s is not installed at %s.', $name, $directory);
                continue;
            }
            if (is_link(rtrim($directory, '/'))) {
                $problems[] = sprintf('%s is a symlink (lab-link?); the check needs the installed dist.', $directory);
                continue;
            }
            $scratch = self::scratchCopy($directory);
            $packageApplied = [];
            try {
                foreach (array_reverse($byPackage[$name]) as $patch) {
                    $file = $root . '/' . $patch['url'];
                    if (!is_file($file)) {
                        $problems[] = sprintf('%s: %s does not exist.', $name, $patch['url']);
                        continue;
                    }
                    $hash = hash_file('sha256', $file);
                    if ($patch['sha256'] !== null && $hash !== $patch['sha256']) {
                        $problems[] = sprintf('%s: %s has sha256 %s, patches.lock.json says %s; composer-patches refuses such a file.', $name, $patch['url'], (string)$hash, $patch['sha256']);
                        continue;
                    }
                    [$exitCode, $output] = self::run([
                        $patchBinary, '--reverse', '--forward', '--batch', '--silent', '--fuzz=0',
                        '--no-backup-if-mismatch', '--reject-file=-', '-p' . $patch['depth'],
                        '--directory=' . $scratch, '--input=' . $file,
                    ]);
                    if ($exitCode !== 0) {
                        $problems[] = sprintf('%s: %s is NOT applied to the installed code (patch -R: %s).', $name, $patch['url'], trim($output) === '' ? 'exit ' . $exitCode : trim($output));
                        continue;
                    }
                    $packageApplied[] = sprintf('%s: %s', $name, $patch['url']);
                }
            } finally {
                self::remove($scratch);
            }
            array_push($applied, ...array_reverse($packageApplied));
        }

        return ['problems' => $problems, 'applied' => $applied];
    }

    /** @return array{url: string, reference: string}|null */
    public static function jevSource(string $composerLock): ?array
    {
        $lock = json_decode($composerLock, true, 512, \JSON_THROW_ON_ERROR);
        foreach (self::listOf(is_array($lock) ? array_merge(self::listOf($lock['packages'] ?? []), self::listOf($lock['packages-dev'] ?? [])) : []) as $package) {
            if (($package['name'] ?? null) !== 'webconsulting/webcon-jev') {
                continue;
            }
            $source = is_array($package['source'] ?? null) ? $package['source'] : [];
            if (is_string($source['url'] ?? null) && is_string($source['reference'] ?? null)) {
                return ['url' => $source['url'], 'reference' => $source['reference']];
            }
        }

        return null;
    }

    /**
     * Files below $paths that contain $secret. Symlinks are not followed;
     * the directories in SCAN_SKIP_DIRECTORIES are skipped.
     *
     * @param list<string> $paths
     * @return list<string>
     */
    public static function filesContaining(string $secret, array $paths): array
    {
        if (strlen($secret) < 8) {
            throw new \InvalidArgumentException('Refusing to scan for a secret shorter than 8 characters.', 1790300001);
        }
        $found = [];
        foreach ($paths as $path) {
            if (is_link($path)) {
                continue;
            }
            if (is_file($path)) {
                if (self::fileContains($path, $secret)) {
                    $found[] = $path;
                }
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO),
                    static fn(\SplFileInfo $file): bool => !$file->isLink()
                        && !($file->isDir() && in_array($file->getFilename(), self::SCAN_SKIP_DIRECTORIES, true)),
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
                \RecursiveIteratorIterator::CATCH_GET_CHILD,
            );
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->isReadable() && self::fileContains($file->getPathname(), $secret)) {
                    $found[] = $file->getPathname();
                }
            }
        }
        sort($found);

        return array_values(array_unique($found));
    }

    public static function fileContains(string $file, string $secret): bool
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return false;
        }
        $overlap = strlen($secret) - 1;
        $tail = '';
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 1 << 20);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $buffer = $tail . $chunk;
                if (str_contains($buffer, $secret)) {
                    return true;
                }
                $tail = substr($buffer, -$overlap);
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    public static function urlHasCredentials(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return true;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return true;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/token|auth|key|password|secret/i', (string)$key) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $arguments
     */
    public static function main(array $arguments, string $defaultRoot): int
    {
        $subcommand = array_shift($arguments) ?? '';
        $root = $defaultRoot;
        $paths = [];
        $gitFiles = false;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--root=')) {
                $root = substr($argument, 7);
            } elseif ($argument === '--git-files') {
                $gitFiles = true;
            } elseif (!str_starts_with($argument, '--')) {
                $paths[] = $argument;
            } else {
                return self::usage();
            }
        }

        try {
            switch ($subcommand) {
                case 'installed':
                    $problems = self::installedProblems(
                        (string)self::read($root . '/composer.lock'),
                        self::read($root . '/vendor/composer/installed.json') ?? '{"packages":[]}',
                    );

                    return self::report($problems, sprintf('%s are installed exactly as composer.lock says.', implode(' and ', self::PACKAGES)));
                case 'patches':
                    $result = self::patchProblems($root);
                    foreach ($result['applied'] as $line) {
                        fwrite(\STDOUT, '  applied  ' . $line . "\n");
                    }

                    return self::report($result['problems'], sprintf('All %d lab patches of %s are applied to the installed code.', count($result['applied']), implode(' and ', self::PACKAGES)));
                case 'jev-source':
                    $source = self::jevSource((string)self::read($root . '/composer.lock'));
                    if ($source === null) {
                        fwrite(\STDERR, "webconsulting/webcon-jev has no source URL and reference in composer.lock.\n");

                        return 1;
                    }
                    fwrite(\STDOUT, $source['url'] . ' ' . $source['reference'] . "\n");

                    return 0;
                case 'scan-token':
                    $secret = trim((string)stream_get_contents(\STDIN));
                    if ($gitFiles) {
                        [$exitCode, $output] = self::run(['git', '-C', $root, 'ls-files', '-z', '--cached', '--others', '--exclude-standard']);
                        if ($exitCode !== 0) {
                            fwrite(\STDERR, "git ls-files failed in {$root}.\n");

                            return 2;
                        }
                        foreach (array_filter(explode("\0", $output)) as $file) {
                            $paths[] = $root . '/' . $file;
                        }
                    }
                    $found = self::filesContaining($secret, $paths);
                    unset($secret);
                    foreach ($found as $file) {
                        fwrite(\STDOUT, $file . "\n");
                    }

                    return $found === [] ? 0 : 1;
                default:
                    return self::usage();
            }
        } catch (\Throwable $exception) {
            fwrite(\STDERR, $exception->getMessage() . "\n");

            return 2;
        }
    }

    private static function usage(): int
    {
        fwrite(\STDERR, "Usage: php Build/Scripts/check-powermail.php installed|patches|jev-source [--root=<dir>]\n"
            . "       php Build/Scripts/check-powermail.php scan-token [--root=<dir>] [--git-files] <path>… < secret\n");

        return 2;
    }

    /** @param list<string> $problems */
    private static function report(array $problems, string $success): int
    {
        if ($problems === []) {
            fwrite(\STDOUT, $success . "\n");

            return 0;
        }
        foreach ($problems as $problem) {
            fwrite(\STDERR, '  ' . $problem . "\n");
        }

        return 1;
    }

    /**
     * @param list<string> $command
     * @return array{0: int, 1: string}
     */
    private static function run(array $command): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return [127, sprintf('cannot start %s', $command[0])];
        }
        // The commands run here print little on stderr, so reading stdout first cannot block.
        $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    private static function scratchCopy(string $directory): string
    {
        $scratch = sys_get_temp_dir() . '/check-powermail-' . bin2hex(random_bytes(6));
        [$exitCode, $output] = self::run(['cp', '-R', rtrim($directory, '/'), $scratch]);
        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf('Cannot copy %s to %s: %s', $directory, $scratch, $output), 1790300002);
        }

        return $scratch;
    }

    private static function remove(string $directory): void
    {
        if (str_starts_with($directory, sys_get_temp_dir() . '/check-powermail-')) {
            self::run(['rm', '-rf', $directory]);
        }
    }

    private static function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..' && $parts !== [] && end($parts) !== '') {
                array_pop($parts);
            } elseif ($part !== '.') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    /** @param array<mixed> $package */
    private static function reference(array $package): string
    {
        foreach (['dist', 'source'] as $kind) {
            if (is_array($package[$kind] ?? null) && is_string($package[$kind]['reference'] ?? null) && $package[$kind]['reference'] !== '') {
                return $package[$kind]['reference'];
            }
        }

        return '(none)';
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '(none)';
    }

    /**
     * @return list<array<mixed>>
     */
    private static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /**
     * @param list<array<mixed>> $packages
     * @return array<string, array<mixed>>
     */
    private static function indexByName(array $packages): array
    {
        $index = [];
        foreach ($packages as $package) {
            if (is_string($package['name'] ?? null)) {
                $index[$package['name']] = $package;
            }
        }

        return $index;
    }

    private static function read(string $file): ?string
    {
        return is_file($file) ? (string)file_get_contents($file) : null;
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    /** @var list<string> $arguments */
    $arguments = array_values(array_slice($_SERVER['argv'] ?? [], 1));
    exit(PowermailCheck::main($arguments, dirname(__DIR__, 2)));
}
