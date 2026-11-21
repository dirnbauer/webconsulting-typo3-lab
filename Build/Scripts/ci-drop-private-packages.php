<?php

declare(strict_types=1);

/*
 * Removes the two licensed in2code packages (Powermail 14 and Powermail
 * Conditions 14 from in2code's Early Access Packagist) from a THROW-AWAY CI
 * checkout, so GitHub Actions can install and check everything else without
 * the in2code token. The token lives only in Coolify (build-only variable
 * IN2CODE_PACKAGIST_TOKEN) and on the developer's machine; GitHub has no copy.
 *
 * It changes, in the checkout it runs in:
 * - composer.json: the two packages leave "require"/"require-dev", the
 *   "in2code" repository and their extra.patches entries go;
 * - composer.lock: the two packages, their stability flags and aliases go,
 *   and "content-hash" is recomputed exactly as Composer does
 *   (Composer\Package\Locker::getContentHash());
 * - patches.lock.json: their blocks go and "_hash" is recomputed exactly as
 *   cweagans/composer-patches 2 does (cweagans\Composer\Locker::getCollectionHash()).
 *
 * It refuses to run outside GitHub Actions unless --force is given, and it
 * fails loudly when another locked package requires one of the two, or when
 * composer.json lists a "composer"-type repository it does not know (a new
 * private repository must be decided on, not silently kept or dropped).
 *
 * Never run it on a developer checkout (it rewrites the lock) and never in the
 * Docker build: the image must carry Powermail with its five patches.
 * .dockerignore keeps this file out of the build context.
 *
 * Usage: php Build/Scripts/ci-drop-private-packages.php [--force] [--root=<dir>]
 * Plain PHP, no vendor/ needed (it runs before "composer install").
 */

namespace Webconsulting\Lab\Build;

final class CiDropPrivatePackages
{
    /** The packages only in2code's Early Access Packagist serves. */
    public const PRIVATE_PACKAGES = ['in2code/powermail', 'in2code/powermail_cond'];

    /** Private "composer" repositories this script knows how to drop. */
    public const DROPPED_REPOSITORY_URLS = ['https://in2code.repo.packagist.com/webconsulting/'];

    /** Public "composer" repositories that may stay. */
    public const PUBLIC_REPOSITORY_URLS = ['https://repo.packagist.org', 'https://packagist.org'];

    private const JSON_FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE;

    /**
     * Composer\Package\Locker::getContentHash(): the md5 of the relevant
     * composer.json keys, decoded as arrays, sorted, encoded with flags 0.
     */
    public static function composerContentHash(string $composerJson): string
    {
        $content = json_decode($composerJson, true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($content)) {
            throw new \RuntimeException('composer.json is not a JSON object.', 1790200001);
        }
        $relevantKeys = [
            'name', 'version', 'require', 'require-dev', 'conflict', 'replace', 'provide',
            'minimum-stability', 'prefer-stable', 'repositories', 'extra',
        ];
        $relevant = [];
        foreach (array_intersect($relevantKeys, array_keys($content)) as $key) {
            $relevant[$key] = $content[$key];
        }
        if (is_array($content['config'] ?? null) && isset($content['config']['platform'])) {
            $relevant['config']['platform'] = $content['config']['platform'];
        }
        if (is_array($content['config'] ?? null) && isset($content['config']['policy'])) {
            $relevant['config']['policy'] = $content['config']['policy'];
        }
        ksort($relevant);

        return hash('md5', (string)json_encode($relevant, 0));
    }

    /**
     * cweagans\Composer\Locker::getCollectionHash(): the sha256 of the lock
     * data without "_hash", decoded as arrays, key-sorted, encoded with flags 0.
     */
    public static function patchesLockHash(string $patchesLockJson): string
    {
        $data = json_decode($patchesLockJson, true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('patches.lock.json is not a JSON object.', 1790200002);
        }
        unset($data['_hash']);
        ksort($data);

        return hash('sha256', (string)json_encode($data, 0));
    }

    /**
     * @return array{composerJson: string, composerLock: string, patchesLock: ?string, messages: list<string>}
     */
    public static function drop(string $composerJson, string $composerLock, ?string $patchesLock): array
    {
        $messages = [];
        $private = self::PRIVATE_PACKAGES;

        // Objects, not arrays: re-encoding must keep {} as {} in the lock.
        $json = json_decode($composerJson, false, 512, \JSON_THROW_ON_ERROR);
        $lock = json_decode($composerLock, false, 512, \JSON_THROW_ON_ERROR);
        if (!$json instanceof \stdClass || !$lock instanceof \stdClass) {
            throw new \RuntimeException('composer.json and composer.lock must be JSON objects.', 1790200003);
        }

        // 1. Repositories: drop the known private one, refuse unknown private ones.
        if (isset($json->repositories)) {
            $repositories = $json->repositories;
            $kept = [];
            $isList = is_array($repositories);
            foreach ((array)$repositories as $key => $repository) {
                if (!$repository instanceof \stdClass || ($repository->type ?? '') !== 'composer') {
                    $kept[$key] = $repository;
                    continue;
                }
                $url = is_string($repository->url ?? null) ? $repository->url : '';
                if (in_array(rtrim($url, '/'), self::PUBLIC_REPOSITORY_URLS, true)) {
                    $kept[$key] = $repository;
                    continue;
                }
                if (!in_array($url, self::DROPPED_REPOSITORY_URLS, true)) {
                    throw new \RuntimeException(sprintf(
                        'composer.json lists the composer repository "%s" this script does not know. Decide whether CI can reach it, then add it to %s::PUBLIC_REPOSITORY_URLS or DROPPED_REPOSITORY_URLS.',
                        $url,
                        self::class,
                    ), 1790200004);
                }
                $only = is_array($repository->only ?? null) ? $repository->only : null;
                if ($only === null || array_diff($only, $private) !== []) {
                    throw new \RuntimeException(sprintf(
                        'The repository "%s" must be limited ("only") to %s; dropping it would also drop %s.',
                        $url,
                        implode(', ', $private),
                        $only === null ? 'everything it serves' : implode(', ', array_diff($only, $private)),
                    ), 1790200005);
                }
                $messages[] = sprintf('composer.json: removed the repository %s', $url);
            }
            $json->repositories = $isList ? array_values($kept) : (object)$kept;
        }

        // 2. Root requirements and patches.
        foreach (['require', 'require-dev'] as $section) {
            if (!isset($json->{$section}) || !$json->{$section} instanceof \stdClass) {
                continue;
            }
            foreach ($private as $name) {
                if (property_exists($json->{$section}, $name)) {
                    unset($json->{$section}->{$name});
                    $messages[] = sprintf('composer.json: removed %s from "%s"', $name, $section);
                }
            }
        }
        if (isset($json->extra->patches) && $json->extra->patches instanceof \stdClass) {
            foreach ($private as $name) {
                if (property_exists($json->extra->patches, $name)) {
                    unset($json->extra->patches->{$name});
                    $messages[] = sprintf('composer.json: removed the patches for %s', $name);
                }
            }
        }

        // 3. The lock: refuse when anything else still needs them.
        $dependents = [];
        $removed = [];
        // Names the private packages also answer to ("replace", "provide").
        $privateNames = $private;
        foreach (['packages', 'packages-dev'] as $section) {
            foreach (is_array($lock->{$section} ?? null) ? $lock->{$section} : [] as $package) {
                if (in_array($package->name ?? null, $private, true)) {
                    foreach (['replace', 'provide'] as $key) {
                        foreach (array_keys((array)($package->{$key} ?? [])) as $alias) {
                            $privateNames[] = (string)$alias;
                        }
                    }
                }
            }
        }
        foreach (['packages', 'packages-dev'] as $section) {
            if (!isset($lock->{$section}) || !is_array($lock->{$section})) {
                continue;
            }
            $kept = [];
            foreach ($lock->{$section} as $package) {
                $name = is_string($package->name ?? null) ? $package->name : '';
                if (in_array($name, $private, true)) {
                    $removed[] = $name;
                    continue;
                }
                $kept[] = $package;
                foreach ((array)($package->require ?? []) as $required => $constraint) {
                    if (in_array((string)$required, $privateNames, true)) {
                        $dependents[] = sprintf('%s requires %s', $name, $required);
                    }
                }
            }
            $lock->{$section} = $kept;
        }
        if ($dependents !== []) {
            throw new \RuntimeException(
                'Other locked packages require the private packages, so CI cannot drop them: ' . implode('; ', $dependents),
                1790200006,
            );
        }
        foreach ($removed as $name) {
            $messages[] = sprintf('composer.lock: removed %s', $name);
        }
        if (isset($lock->{'stability-flags'}) && $lock->{'stability-flags'} instanceof \stdClass) {
            foreach ($private as $name) {
                unset($lock->{'stability-flags'}->{$name});
            }
        }
        if (isset($lock->aliases) && is_array($lock->aliases)) {
            $lock->aliases = array_values(array_filter(
                $lock->aliases,
                static fn(mixed $alias): bool => !($alias instanceof \stdClass && in_array($alias->package ?? null, $private, true)),
            ));
        }

        $newComposerJson = self::encode($json, self::indentationOf($composerJson));
        $lock->{'content-hash'} = self::composerContentHash($newComposerJson);
        $newComposerLock = self::encode($lock, '    ');

        // 4. patches.lock.json (cweagans/composer-patches 2).
        $newPatchesLock = null;
        if ($patchesLock !== null) {
            $patches = json_decode($patchesLock, false, 512, \JSON_THROW_ON_ERROR);
            if (!$patches instanceof \stdClass) {
                throw new \RuntimeException('patches.lock.json is not a JSON object.', 1790200007);
            }
            if (isset($patches->patches) && $patches->patches instanceof \stdClass) {
                foreach ($private as $name) {
                    if (property_exists($patches->patches, $name)) {
                        unset($patches->patches->{$name});
                        $messages[] = sprintf('patches.lock.json: removed the patches for %s', $name);
                    }
                }
            }
            $patches->_hash = '';
            $patches->_hash = self::patchesLockHash(self::encode($patches, '    '));
            $newPatchesLock = self::encode($patches, '    ');
        }

        return [
            'composerJson' => $newComposerJson,
            'composerLock' => $newComposerLock,
            'patchesLock' => $newPatchesLock,
            'messages' => $messages,
        ];
    }

    /** Composer's JsonFile::write(): pretty, unescaped, a trailing newline. */
    public static function encode(mixed $data, string $indent): string
    {
        $json = json_encode($data, self::JSON_FLAGS | \JSON_THROW_ON_ERROR);
        if ($indent !== '    ') {
            $json = (string)preg_replace_callback(
                '#^ {4,}#m',
                static fn(array $match): string => str_repeat($indent, intdiv(strlen($match[0]), 4)),
                $json,
            );
        }

        return $json . "\n";
    }

    private static function indentationOf(string $json): string
    {
        return preg_match('/^([ \t]+)"/m', $json, $match) === 1 ? $match[1] : '    ';
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     */
    public static function main(array $arguments, array $environment, string $defaultRoot): int
    {
        $force = false;
        $root = $defaultRoot;
        foreach ($arguments as $argument) {
            if ($argument === '--force') {
                $force = true;
            } elseif (str_starts_with($argument, '--root=')) {
                $root = substr($argument, 7);
            } else {
                fwrite(\STDERR, "Usage: php Build/Scripts/ci-drop-private-packages.php [--force] [--root=<dir>]\n");

                return 2;
            }
        }
        if (!$force && !self::isCi($environment)) {
            fwrite(\STDERR, "Refusing to run: this rewrites composer.json, composer.lock and patches.lock.json without Powermail.\n"
                . "It is meant for the throw-away GitHub Actions checkout only (CI=true, GITHUB_ACTIONS=true).\n"
                . "Pass --force only on a scratch copy you will delete.\n");

            return 1;
        }

        $read = static function (string $path, bool $required) use ($root): ?string {
            $file = $root . '/' . $path;
            if (!is_file($file)) {
                if ($required) {
                    throw new \RuntimeException(sprintf('%s is missing.', $file), 1790200008);
                }

                return null;
            }

            return (string)file_get_contents($file);
        };

        try {
            $result = self::drop(
                (string)$read('composer.json', true),
                (string)$read('composer.lock', true),
                $read('patches.lock.json', false),
            );
        } catch (\Throwable $exception) {
            fwrite(\STDERR, '::error::' . $exception->getMessage() . "\n");

            return 1;
        }

        file_put_contents($root . '/composer.json', $result['composerJson']);
        file_put_contents($root . '/composer.lock', $result['composerLock']);
        if ($result['patchesLock'] !== null) {
            file_put_contents($root . '/patches.lock.json', $result['patchesLock']);
        }
        foreach ($result['messages'] === [] ? ['Nothing to drop: the private packages are not in this checkout.'] : $result['messages'] as $message) {
            fwrite(\STDOUT, $message . "\n");
        }
        fwrite(\STDOUT, sprintf(
            "This checkout no longer contains %s. CI checks everything else; the Coolify image build and the local run before each merge check the full install.\n",
            implode(' and ', self::PRIVATE_PACKAGES),
        ));

        return 0;
    }

    /** @param array<string, string> $environment */
    public static function isCi(array $environment): bool
    {
        return ($environment['CI'] ?? '') === 'true' && ($environment['GITHUB_ACTIONS'] ?? '') === 'true';
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    /** @var list<string> $arguments */
    $arguments = array_values(array_slice($_SERVER['argv'] ?? [], 1));
    $environment = array_filter(getenv(), 'is_string');
    exit(CiDropPrivatePackages::main($arguments, $environment, dirname(__DIR__, 2)));
}
