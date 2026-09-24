<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Translation;

/**
 * What the translate command may write for one site, read from the site's
 * `scope.json` next to its translation memories.
 *
 * - `skip`: pages (or whole subtrees) whose translations another command owns,
 *   per language or for all of them. Their rows are never touched.
 * - `recordTables`: tables whose records are translated wherever they are
 *   stored in the tree, sysfolders included (news, tags, categories, forms).
 * - `ignoreFields`: text columns that hold identifiers, not copy.
 * - `translateFields`: text columns whose name reads like an identifier
 *   (`mode`, `author`, `position` …) but which hold copy the reader sees.
 * - `lineFields`: text columns with one `label|value` pair per line, where
 *   only the label is translated.
 */
final readonly class TranslationScope
{
    /**
     * @param list<array{page: int, tree: bool, languages: list<string>}> $skip
     * @param list<string> $recordTables
     * @param array<string, list<string>> $ignoreFields
     * @param array<string, array<string, non-empty-string>> $lineFields table => field => separator
     * @param array<string, list<string>> $translateFields
     */
    public function __construct(
        public array $skip = [],
        public array $recordTables = [],
        public array $ignoreFields = [],
        public array $lineFields = [],
        public array $translateFields = [],
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return new self();
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf('%s is not valid JSON.', $path), 1790100011);
        }

        return self::fromArray($data);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $skip = [];
        foreach (self::list($data['skip'] ?? []) as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $tree = isset($rule['tree']);
            $page = self::int($tree ? $rule['tree'] : ($rule['page'] ?? 0));
            if ($page <= 0) {
                continue;
            }
            $skip[] = [
                'page' => $page,
                'tree' => $tree,
                'languages' => array_values(array_map(self::string(...), self::list($rule['languages'] ?? []))),
            ];
        }

        $ignoreFields = [];
        foreach (self::map($data['ignoreFields'] ?? []) as $table => $fields) {
            $ignoreFields[$table] = array_values(array_map(self::string(...), self::list($fields)));
        }
        $translateFields = [];
        foreach (self::map($data['translateFields'] ?? []) as $table => $fields) {
            $translateFields[$table] = array_values(array_map(self::string(...), self::list($fields)));
        }
        $lineFields = [];
        foreach (self::map($data['lineFields'] ?? []) as $table => $fields) {
            foreach (self::map($fields) as $field => $separator) {
                $separator = self::string($separator);
                $lineFields[$table][$field] = $separator !== '' ? $separator : '|';
            }
        }

        return new self(
            $skip,
            array_values(array_map(self::string(...), self::list($data['recordTables'] ?? []))),
            $ignoreFields,
            $lineFields,
            $translateFields,
        );
    }

    /**
     * @param list<int> $rootline page uid first, then its parents up to the root
     */
    public function skips(array $rootline, string $language): bool
    {
        $page = $rootline[0] ?? 0;
        foreach ($this->skip as $rule) {
            if ($rule['languages'] !== [] && !in_array($language, $rule['languages'], true)) {
                continue;
            }
            if ($rule['page'] === $page || $rule['tree'] && in_array($rule['page'], $rootline, true)) {
                return true;
            }
        }

        return false;
    }

    public function ignores(string $table, string $field): bool
    {
        return in_array($field, $this->ignoreFields[$table] ?? [], true);
    }

    public function translates(string $table, string $field): bool
    {
        return in_array($field, $this->translateFields[$table] ?? [], true);
    }

    /**
     * @return non-empty-string|null
     */
    public function lineSeparator(string $table, string $field): ?string
    {
        return $this->lineFields[$table][$field] ?? null;
    }

    /** @return list<mixed> */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /** @return array<string, mixed> */
    private static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $map = [];
        foreach ($value as $key => $item) {
            $map[(string)$key] = $item;
        }

        return $map;
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
