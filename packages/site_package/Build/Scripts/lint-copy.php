<?php

declare(strict_types=1);

/**
 * Checks seed files against the content canon before anything is seeded.
 *
 *   php packages/site_package/Build/Scripts/lint-copy.php [options] <file|dir>...
 *
 *   --site=<key>        canon site key (desiderio, astryx, camp ...); sets language and address rules
 *   --lang=en|de        language, when neither the site nor the file name says it
 *   --php=<Class::method>  lint the array a static seed data method returns (repeatable)
 *   --against=<git ref> also compare each JSON file with that revision: keys must not
 *                       change and no field that had text may become empty
 *   --warnings          print warnings as well as errors
 *
 * Works on fixture.json, library.json, library.de.json and content payloads
 * (`records[].set`). File names decide the language (`*.de.json` is German).
 * `library*.json` must never name the product: that file is demo content an
 * editor keeps. Run it inside the container (host PHP is too old):
 *
 *   ddev exec php packages/site_package/Build/Scripts/lint-copy.php --site=desiderio \
 *     packages/desiderio/ContentBlocks/ContentElements/hero-parallax
 *
 * Exit code 1 if any error remains.
 */

use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\ContentAudit\CanonChecker;
use Webconsulting\SitePackage\ContentAudit\CopyMetrics;
use Webconsulting\SitePackage\ContentAudit\FieldRole;
use Webconsulting\SitePackage\ContentAudit\Finding;

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

$options = ['site' => '', 'lang' => '', 'against' => '', 'warnings' => false, 'php' => []];
$paths = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--(site|lang|against|php)=(.*)$/', $argument, $match) === 1) {
        if ($match[1] === 'php') {
            $options['php'][] = $match[2];
        } else {
            $options[$match[1]] = $match[2];
        }
    } elseif ($argument === '--warnings') {
        $options['warnings'] = true;
    } else {
        $paths[] = $argument;
    }
}
if ($paths === [] && $options['php'] === []) {
    fwrite(STDERR, "usage: lint-copy.php [--site=key] [--lang=en|de] [--against=ref] [--php=Class::method] [--warnings] <file|dir>...\n");
    exit(2);
}

$canon = Canon::fromFile();
$checker = new CanonChecker($canon);
$siteLanguage = $options['site'] !== '' ? ($canon->site($options['site'])['language'] ?? '') : '';

$files = [];
foreach ($paths as $path) {
    if (is_dir($path)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && preg_match('/^(fixture|library(\.[a-z]{2})?)\.json$|\.payload\.json$/', $file->getFilename()) === 1) {
                $files[] = $file->getPathname();
            }
        }
    } elseif (is_file($path)) {
        $files[] = $path;
    } else {
        fwrite(STDERR, "not found: {$path}\n");
        exit(2);
    }
}
sort($files);

$errors = 0;
$warnings = 0;
$report = static function (string $source, string $key, Finding $finding) use (&$errors, &$warnings, $options): void {
    if ($finding->isError()) {
        $errors++;
    } else {
        $warnings++;
        if (!$options['warnings']) {
            return;
        }
    }
    printf("%s %s [%s] %s: %s — \"%s\"\n", $finding->isError() ? 'ERROR' : 'warn ', $source, $key, $finding->rule, $finding->message, mb_strimwidth($finding->excerpt, 0, 100, '…'));
};

/**
 * Walks an array and checks every string leaf, keyed by its nearest field name.
 *
 * @param array<mixed> $data
 */
$walk = static function (array $data, string $source, string $table, string $language, bool $library, string $path = '', string $field = '') use (&$walk, $checker, $options, $report): void {
    foreach ($data as $key => $value) {
        $isField = is_string($key);
        $name = $isField ? $key : $field;
        $location = $path === '' ? (string)$key : $path . '.' . $key;
        if ($isField && (str_starts_with($key, '_') || in_array($key, ['file', 'source', 'identifier', 'ctype', 'CType', 'colPos', 'slug', 'table', 'expect'], true))) {
            continue;
        }
        if (is_array($value)) {
            $walk($value, $source, $table, $language, $library, $location, $name);
            continue;
        }
        if (!is_string($value) || trim($value) === '' || $name === '') {
            continue;
        }
        // Identifiers, paths, links and placeholders are not copy.
        if (preg_match('#^(\S+://|t3://|/|EXT:|\{\{|[a-z0-9_-]+:[a-z0-9_-]+$|[\w.-]+\.(png|jpe?g|webp|svg|gif|mp4|pdf|wav)$|\#)#i', trim($value)) === 1) {
            continue;
        }
        $role = FieldRole::fromField($name, $table);
        $text = CopyMetrics::plainText($value);
        foreach ($checker->check($text, $role, $language, $options['site']) as $finding) {
            $report($source, $location, $finding);
        }
        if ($library && preg_match('/\b(Desiderio|TYPO3|shadcn|Content Blocks?)\b/i', $text, $match) === 1) {
            $report($source, $location, new Finding('error', 'library', 'library demo content must not name the product', $match[0]));
        }
    }
};

foreach ($files as $file) {
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) {
        $report($file, '-', new Finding('error', 'json', 'not valid JSON'));
        continue;
    }
    $base = basename($file);
    $language = preg_match('/\.de\.json$/', $base) === 1 ? 'de' : ($options['lang'] !== '' ? $options['lang'] : ($siteLanguage !== '' ? $siteLanguage : 'en'));
    $library = str_starts_with($base, 'library');

    if (isset($data['records']) && is_array($data['records'])) {
        // A content payload: check what it sets, per table.
        $language = is_string($data['languageCode'] ?? null) ? $data['languageCode'] : $language;
        foreach ($data['records'] as $index => $record) {
            if (is_array($record) && is_array($record['set'] ?? null)) {
                $table = is_string($record['table'] ?? null) ? $record['table'] : 'tt_content';
                $label = sprintf('records.%s(%s:%s)', (string)$index, $table, is_scalar($record['uid'] ?? null) ? (string)$record['uid'] : (is_scalar($record['key'] ?? null) ? (string)$record['key'] : 'new'));
                $walk($record['set'], $file, $table, $language, false, $label);
            }
        }
    } else {
        $walk($data, $file, 'tt_content', $language, $library);
    }

    if ($options['against'] !== '') {
        $old = gitShow($file, $options['against']);
        if (is_array($old)) {
            foreach (compareShape($old, $data) as $message) {
                $report($file, '-', new Finding('error', 'shape', $message));
            }
        }
    }
}

foreach ($options['php'] as $callable) {
    if (!is_callable($callable)) {
        $report($callable, '-', new Finding('error', 'php', 'not a callable static method'));
        continue;
    }
    $data = $callable();
    if (is_array($data)) {
        $walk($data, $callable, 'tt_content', $options['lang'] !== '' ? $options['lang'] : ($siteLanguage !== '' ? $siteLanguage : 'en'), false);
    }
}

printf("\n%d file(s)%s: %d error(s), %d warning(s)\n", count($files), $options['php'] !== [] ? ' + ' . count($options['php']) . ' seed method(s)' : '', $errors, $warnings);
exit($errors > 0 ? 1 : 0);

/**
 * @return array<mixed>|null
 */
function gitShow(string $file, string $ref): ?array
{
    $real = realpath($file);
    if ($real === false) {
        return null;
    }
    $directory = dirname($real);
    $top = trim((string)shell_exec('git -C ' . escapeshellarg($directory) . ' rev-parse --show-toplevel 2>/dev/null'));
    if ($top === '') {
        return null;
    }
    $relative = ltrim(substr($real, strlen($top)), '/');
    $content = shell_exec('git -C ' . escapeshellarg($top) . ' show ' . escapeshellarg($ref . ':' . $relative) . ' 2>/dev/null');
    $decoded = is_string($content) ? json_decode($content, true) : null;

    return is_array($decoded) ? $decoded : null;
}

/**
 * Keys must stay the same and a field that had text must keep some.
 *
 * @param array<mixed> $old
 * @param array<mixed> $new
 * @return list<string>
 */
function compareShape(array $old, array $new, string $path = ''): array
{
    $messages = [];
    foreach ($old as $key => $value) {
        $location = $path === '' ? (string)$key : $path . '.' . $key;
        if (!array_key_exists($key, $new)) {
            if (is_string($key)) {
                $messages[] = "key removed: {$location}";
            } elseif (count($new) < 3) {
                $messages[] = "collection {$path} has fewer than 3 items";
            }
            continue;
        }
        if (is_array($value) && is_array($new[$key])) {
            array_push($messages, ...compareShape($value, $new[$key], $location));
        } elseif (is_string($value) && trim($value) !== '' && is_string($new[$key]) && trim($new[$key]) === '') {
            $messages[] = "field blanked: {$location} (the seeder would fill it with generated text)";
        } elseif (gettype($value) !== gettype($new[$key])) {
            $messages[] = "type changed: {$location}";
        }
    }
    foreach ($new as $key => $value) {
        if (is_string($key) && !array_key_exists($key, $old)) {
            $location = $path === '' ? $key : $path . '.' . $key;
            $messages[] = "key added: {$location}";
        }
    }

    return $messages;
}
