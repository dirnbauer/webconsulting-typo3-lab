<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\NetresearchTalk;

/**
 * The Netresearch talk pages as one content payload, read from the files of
 * Resources/Private/Data/Content/netresearch-talk/ and made ready for
 * sitepackage:content:apply.
 *
 * Pure: no database, no TYPO3 bootstrap. The command passes what only the
 * installation knows (page uids by slug, which image files exist), so the
 * same payload resolves on the lab and on production, where uids differ.
 *
 * - `*.payload.json` are read in file-name order (00-hub first, it creates
 *   the page the others refer to as `@NEW_nrtalk_page`) and merged.
 * - `pid` / `match.pid` 0 is the parent of the talk (/ai).
 * - `{{page:/slug|/fallback}}` becomes `t3://page?uid=N` of the first slug
 *   that exists; as a whole `pid` value it becomes the uid itself.
 * - `{{labstatus:<id>}}` becomes the label lab-status.json gives that module.
 * - an image file that does not exist yet is left out (and reported), so
 *   the pages can be seeded before the screenshots are captured.
 */
final class TalkPayload
{
    public const DIRECTORY = __DIR__ . '/../../Resources/Private/Data/Content/netresearch-talk';
    public const MARKER_PREFIX = 'nrtalk:';
    private const PAGE_TOKEN = '/\{\{page:([^{}]+)\}\}/';
    private const LAB_STATUS_TOKEN = '/\{\{labstatus:([a-z0-9-]+)\}\}/';

    /**
     * @param list<array<string, mixed>> $records
     * @param list<string> $files payload files in the order they were merged
     */
    private function __construct(public readonly array $records, public readonly array $files) {}

    public static function fromDirectory(string $directory = self::DIRECTORY): self
    {
        $files = glob(rtrim($directory, '/') . '/*.payload.json') ?: [];
        sort($files);
        if ($files === []) {
            throw new \RuntimeException(sprintf('No *.payload.json in %s.', $directory), 1794000001);
        }
        $records = [];
        foreach ($files as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || !is_array($data['records'] ?? null)) {
                throw new \RuntimeException(sprintf('%s is not a content payload.', basename($file)), 1794000002);
            }
            if (($data['site'] ?? null) !== 'desiderio' || ($data['languageCode'] ?? null) !== 'en') {
                throw new \RuntimeException(sprintf('%s must be an English payload for the desiderio site.', basename($file)), 1794000003);
            }
            foreach ($data['records'] as $record) {
                if (!is_array($record)) {
                    throw new \RuntimeException(sprintf('%s holds a record that is not an object.', basename($file)), 1794000004);
                }
                $records[] = self::stringKeys($record) + ['_file' => basename($file)];
            }
        }
        // Pages first: elements link pages of later files (the pager, the hub's
        // cards), and an `@<key>` only resolves to a record created before it.
        $pages = array_values(array_filter($records, static fn (array $record): bool => ($record['table'] ?? '') === 'pages'));
        $others = array_values(array_filter($records, static fn (array $record): bool => ($record['table'] ?? '') !== 'pages'));

        return new self([...$pages, ...$others], array_map('basename', $files));
    }

    /**
     * The payload content:apply reads, with every installation-specific value resolved.
     *
     * @param int $parent uid of the page the talk hub sits below (/ai)
     * @param callable(string): ?int $pageUid uid of a default-language page of the site by slug, null when there is none
     * @param callable(string): bool $fileExists whether an EXT: file source exists
     * @param array<string, string> $labStatus module id => label (lab-status.json, already resolved)
     * @param list<string> $notices what was left out, for the command to report
     * @return array{version: int, site: string, language: int, languageCode: string, records: list<array<string, mixed>>}
     */
    public function resolve(int $parent, callable $pageUid, callable $fileExists, array $labStatus, array &$notices = []): array
    {
        $records = [];
        foreach ($this->records as $record) {
            // `_collections` stays: content:apply ignores it, the command links the items with it.
            unset($record['_file']);
            if (($record['pid'] ?? null) === 0) {
                $record['pid'] = $parent;
            }
            $match = is_array($record['match'] ?? null) ? $record['match'] : null;
            if ($match !== null && ($match['pid'] ?? null) === 0) {
                $record['match']['pid'] = $parent;
            }
            $record = self::resolveTokens($record, $pageUid, $labStatus);
            if (is_array($record['files'] ?? null)) {
                foreach ($record['files'] as $field => $references) {
                    $kept = [];
                    foreach (is_array($references) ? $references : [] as $reference) {
                        $source = is_array($reference) && is_string($reference['source'] ?? null) ? $reference['source'] : '';
                        if ($source !== '' && $fileExists($source)) {
                            $kept[] = $reference;
                        } else {
                            $notices[] = sprintf('%s.%s: image %s does not exist yet; seeded without it.', self::text($record['key'] ?? '?'), (string)$field, $source);
                        }
                    }
                    if ($kept === []) {
                        unset($record['files'][$field]);
                    } else {
                        $record['files'][$field] = $kept;
                    }
                }
                if ($record['files'] === []) {
                    unset($record['files']);
                }
            }
            $records[] = $record;
        }

        return ['version' => 1, 'site' => 'desiderio', 'language' => 0, 'languageCode' => 'en', 'records' => $records];
    }

    /**
     * Problems that would make a run fail or leave the pages inconsistent.
     * The command refuses to seed while this list is not empty.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        $keys = [];
        $markers = [];
        foreach ($this->records as $index => $record) {
            $where = sprintf('%s record %d', self::text($record['_file'] ?? '?'), $index);
            $key = self::text($record['key'] ?? '');
            if (($record['action'] ?? '') !== 'create' || !str_starts_with($key, 'NEW_nrtalk_')) {
                $problems[] = $where . ': every record is a create record with a NEW_nrtalk_ key';
            }
            if (isset($keys[$key])) {
                $problems[] = $where . ': duplicate key ' . $key;
            }
            foreach (self::references($record) as $reference) {
                if (!isset($keys[$reference]) && $reference !== $key) {
                    $problems[] = sprintf('%s: @%s is used before a record creates it', $where, $reference);
                }
            }
            $keys[$key] = true;
            $set = is_array($record['set'] ?? null) ? $record['set'] : [];
            if (($record['table'] ?? '') === 'tt_content') {
                $marker = self::text($set['rowDescription'] ?? '');
                $match = is_array($record['match'] ?? null) ? $record['match'] : [];
                if (!str_starts_with($marker, self::MARKER_PREFIX) || ($match['rowDescription'] ?? null) !== $marker) {
                    $problems[] = $where . ': a content element carries its nrtalk: marker in set and match';
                }
                if (isset($markers[$marker])) {
                    $problems[] = $where . ': duplicate marker ' . $marker;
                }
                $markers[$marker] = true;
            }
            foreach (self::collections($record) as $collection) {
                foreach ($collection['keys'] as $child) {
                    if (!in_array($child, array_column(array_slice($this->records, $index + 1), 'key'), true)) {
                        $problems[] = sprintf('%s: collection item %s is not defined after its parent', $where, $child);
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * Collections of a record: `_collections` (list) or the older single `_collection`.
     *
     * @param array<string, mixed> $record
     * @return list<array{field: string, keys: list<string>}>
     */
    public static function collections(array $record): array
    {
        $raw = is_array($record['_collections'] ?? null) ? $record['_collections'] : (is_array($record['_collection'] ?? null) ? [$record['_collection']] : []);
        $collections = [];
        foreach ($raw as $collection) {
            if (is_array($collection) && is_string($collection['field'] ?? null) && is_array($collection['keys'] ?? null)) {
                $collections[] = ['field' => $collection['field'], 'keys' => array_values(array_map(self::text(...), $collection['keys']))];
            }
        }

        return $collections;
    }

    /**
     * Every `@<key>` reference in pid, match and set.
     *
     * @param array<string, mixed> $record
     * @return list<string>
     */
    public static function references(array $record): array
    {
        $values = [$record['pid'] ?? null];
        foreach (['match', 'set'] as $part) {
            if (is_array($record[$part] ?? null)) {
                array_push($values, ...array_values($record[$part]));
            }
        }
        $references = [];
        foreach ($values as $value) {
            if (is_string($value) && preg_match('/^@([\w-]+)$/', $value, $match) === 1) {
                $references[] = $match[1];
            }
        }

        return $references;
    }

    /**
     * Every link target the payload writes: link fields and href attributes.
     *
     * @return list<array{record: string, field: string, target: string}>
     */
    public function links(): array
    {
        $links = [];
        foreach ($this->records as $record) {
            $set = is_array($record['set'] ?? null) ? $record['set'] : [];
            foreach ($set as $field => $value) {
                if (!is_string($value)) {
                    continue;
                }
                if (preg_match('/(^|_)link$/', (string)$field) === 1 && $value !== '') {
                    $links[] = ['record' => self::text($record['key'] ?? ''), 'field' => (string)$field, 'target' => $value];
                }
                preg_match_all('/href="([^"]*)"/', $value, $matches);
                foreach ($matches[1] as $href) {
                    $links[] = ['record' => self::text($record['key'] ?? ''), 'field' => (string)$field, 'target' => html_entity_decode($href)];
                }
            }
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $record
     * @param callable(string): ?int $pageUid
     * @param array<string, string> $labStatus
     * @return array<string, mixed>
     */
    private static function resolveTokens(array $record, callable $pageUid, array $labStatus): array
    {
        foreach (['pid'] as $field) {
            if (is_string($record[$field] ?? null) && preg_match('/^' . trim(self::PAGE_TOKEN, '/') . '$/', $record[$field], $match) === 1) {
                $record[$field] = self::pageFor($match[1], $pageUid);
            }
        }
        foreach (['match', 'set'] as $part) {
            if (!is_array($record[$part] ?? null)) {
                continue;
            }
            foreach ($record[$part] as $field => $value) {
                if (!is_string($value)) {
                    continue;
                }
                if ($field === 'pid' && preg_match('/^' . trim(self::PAGE_TOKEN, '/') . '$/', $value, $match) === 1) {
                    $record[$part][$field] = self::pageFor($match[1], $pageUid);
                    continue;
                }
                $value = (string)preg_replace_callback(self::PAGE_TOKEN, static fn (array $match): string => 't3://page?uid=' . self::pageFor($match[1], $pageUid), $value);
                $record[$part][$field] = (string)preg_replace_callback(self::LAB_STATUS_TOKEN, static function (array $match) use ($labStatus): string {
                    if (!isset($labStatus[$match[1]])) {
                        throw new \RuntimeException(sprintf('lab-status.json has no status for %s.', $match[1]), 1794000005);
                    }
                    return $labStatus[$match[1]];
                }, $value);
            }
        }

        return $record;
    }

    /**
     * @param callable(string): ?int $pageUid
     */
    private static function pageFor(string $slugs, callable $pageUid): int
    {
        foreach (explode('|', $slugs) as $slug) {
            $uid = $pageUid(trim($slug));
            if ($uid !== null && $uid > 0) {
                return $uid;
            }
        }
        throw new \RuntimeException(sprintf('None of the pages %s exists in the Desiderio site.', $slugs), 1794000006);
    }

    /**
     * Module id => label, from lab-status.json (`modules` names a status, `labels` words it).
     *
     * @return array<string, string>
     */
    public static function labStatus(string $directory = self::DIRECTORY): array
    {
        $data = json_decode((string)file_get_contents(rtrim($directory, '/') . '/lab-status.json'), true);
        if (!is_array($data) || !is_array($data['modules'] ?? null) || !is_array($data['labels'] ?? null)) {
            throw new \RuntimeException('lab-status.json needs "modules" and "labels".', 1794000007);
        }
        $labels = [];
        foreach ($data['modules'] as $module => $status) {
            $label = $data['labels'][self::text($status)] ?? null;
            if (!is_string($label) || $label === '') {
                throw new \RuntimeException(sprintf('lab-status.json: no label for status "%s" of %s.', self::text($status), (string)$module), 1794000008);
            }
            $labels[(string)$module] = $label;
        }

        return $labels;
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    private static function stringKeys(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            $result[(string)$key] = $item;
        }

        return $result;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
