<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Translation;

/**
 * Which part of a site one translate run covers.
 *
 * - `pages`: only these page uids (empty = every page of the site).
 * - `recursive`: the listed pages and every page below them.
 * - `tables`: only these top-level record kinds: `pages` (the page rows
 *   themselves), `tt_content` (the content of the pages) and the scope's
 *   record tables (news, tags, categories, forms). Empty = all of them.
 *   Collection items and file references follow their parent record.
 *
 * Everything outside the filter is neither read for the report nor written.
 */
final readonly class TranslationFilter
{
    public const string PAGES = 'pages';
    public const string CONTENT = 'tt_content';

    /**
     * @param list<int> $pages
     * @param list<string> $tables
     */
    public function __construct(
        public array $pages = [],
        public bool $recursive = false,
        public array $tables = [],
    ) {}

    /**
     * @param string $pages comma-separated page uids
     * @param string $tables comma-separated table names
     */
    public static function fromOptions(string $pages, bool $recursive, string $tables): self
    {
        $uids = [];
        foreach (self::split($pages) as $page) {
            if (!ctype_digit($page) || (int)$page <= 0) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a page uid.', $page), 1791900001);
            }
            $uids[] = (int)$page;
        }
        if ($recursive && $uids === []) {
            throw new \InvalidArgumentException('--recursive needs --pages.', 1791900002);
        }

        return new self(array_values(array_unique($uids)), $recursive, array_values(array_unique(self::split($tables))));
    }

    /**
     * Tables the filter names that the run does not know.
     *
     * @param list<string> $recordTables the scope's record tables
     * @return list<string>
     */
    public function unknownTables(array $recordTables): array
    {
        return array_values(array_diff($this->tables, [self::PAGES, self::CONTENT, ...$recordTables]));
    }

    public function isActive(): bool
    {
        return $this->pages !== [] || $this->tables !== [];
    }

    /**
     * @param list<int> $rootline page uid first, then its parents up to the site root
     */
    public function includesPage(array $rootline): bool
    {
        if ($this->pages === []) {
            return true;
        }
        $page = $rootline[0] ?? 0;
        if (in_array($page, $this->pages, true)) {
            return true;
        }

        return $this->recursive && array_intersect($rootline, $this->pages) !== [];
    }

    public function includesTable(string $table): bool
    {
        return $this->tables === [] || in_array($table, $this->tables, true);
    }

    /**
     * One line for the command's report, e.g. "pages 1296 and below · tables tt_content".
     */
    public function describe(): string
    {
        if (!$this->isActive()) {
            return 'whole site';
        }
        $parts = [];
        if ($this->pages !== []) {
            $parts[] = sprintf('pages %s%s', implode(', ', $this->pages), $this->recursive ? ' and below' : '');
        }
        if ($this->tables !== []) {
            $parts[] = sprintf('tables %s', implode(', ', $this->tables));
        }

        return implode(' · ', $parts);
    }

    /**
     * @return list<string>
     */
    private static function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $part): bool => $part !== ''));
    }
}
