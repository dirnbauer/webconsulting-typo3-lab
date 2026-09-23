<?php
declare(strict_types=1);

/**
 * Create a zip archive. Used by both snapshot paths.
 *
 *   php make-zip.php <output.zip> <file>          one file, stored at its basename
 *   php make-zip.php <output.zip> --dir <dir>     the directory's CONTENTS at the archive root
 *   php make-zip.php <output.zip> --dir <dir> --exclude <name|pattern> [--exclude ...]
 *
 * An exclusion without a slash or wildcard names a top-level entry. Anything
 * else is an fnmatch() pattern over the relative path, and a directory that
 * matches takes its whole subtree with it: `ai-chat`, `mcp/workspaces/ws-1/x.pdf`
 * and `*Lebenslauf*` all work.
 *
 * Zip rather than tar.gz because Apache serves any .gz with
 * "Content-Encoding: gzip". HTTP clients then transparently decompress it, so
 * a browser saves a file called db-public.tar.gz that is really a plain tar,
 * and `ddev import-db` rejects it. A .zip carries no Content-Encoding and
 * arrives byte-for-byte.
 *
 * PHP rather than the zip command: the production image is php:8.4-apache and
 * ships unzip and the PHP zip extension, but no zip binary.
 *
 * A directory's contents go in at the archive root, without a wrapping
 * directory, because that is what `ddev import-files` expects.
 */

$argv = $_SERVER['argv'];
$out = $argv[1] ?? null;
if ($out === null) {
    fwrite(STDERR, "usage: make-zip.php <output.zip> [--dir <dir> [--exclude <name>]] | <file>\n");
    exit(2);
}

$dir = null;
$file = null;
$exclude = [];
for ($i = 2; $i < count($argv); $i++) {
    switch ($argv[$i]) {
        case '--dir':     $dir = $argv[++$i] ?? null; break;
        case '--exclude': $exclude[] = $argv[++$i] ?? ''; break;
        default:          $file = $argv[$i];
    }
}

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "the PHP zip extension is not available\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "could not create {$out}\n");
    exit(1);
}

$added = 0;
if ($dir !== null) {
    $root = realpath($dir);
    if ($root === false) {
        fwrite(STDERR, "no such directory: {$dir}\n");
        exit(1);
    }
    $walker = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($walker as $path => $info) {
        $relative = ltrim(substr((string)$path, strlen($root)), DIRECTORY_SEPARATOR);
        if ($relative === '') {
            continue;
        }
        // A plain name matches the first path segment, which is how the
        // download directory keeps itself out of the fileadmin archive it
        // lives in; patterns keep private uploads out (see public-snapshot.sh).
        if (isExcluded($relative, $exclude)) {
            continue;
        }
        if ($info->isDir()) {
            $zip->addEmptyDir($relative);
            continue;
        }
        if (!$info->isFile() || !$info->isReadable()) {
            continue;
        }
        $zip->addFile((string)$path, $relative);
        $added++;
    }
} elseif ($file !== null) {
    if (!is_file($file)) {
        fwrite(STDERR, "no such file: {$file}\n");
        exit(1);
    }
    $zip->addFile($file, basename($file));
    $added++;
} else {
    fwrite(STDERR, "nothing to add\n");
    exit(2);
}

if (!$zip->close()) {
    fwrite(STDERR, "could not finalise {$out}\n");
    exit(1);
}

printf("%s: %d file(s)\n", $out, $added);

/**
 * @param list<string> $exclude
 */
function isExcluded(string $relative, array $exclude): bool
{
    $first = explode(DIRECTORY_SEPARATOR, $relative)[0];
    foreach ($exclude as $pattern) {
        if ($pattern === '') {
            continue;
        }
        if (strpbrk($pattern, '/*?[') === false) {
            if ($first === $pattern) {
                return true;
            }
            continue;
        }
        // Test the path and every parent directory, so a pattern naming a
        // directory also excludes what is inside it.
        $candidate = $relative;
        while (true) {
            if (fnmatch($pattern, $candidate)) {
                return true;
            }
            $slash = strrpos($candidate, '/');
            if ($slash === false) {
                break;
            }
            $candidate = substr($candidate, 0, $slash);
        }
    }
    return false;
}
