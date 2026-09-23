<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ContentAudit;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\TableColumnType;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Reads the text a site shows, straight from the live database.
 *
 * Which columns hold text is taken from the TCA schema of each record type, so
 * every Content Block is covered without a list of element names: input and
 * text fields are read, inline fields are followed into their collection
 * tables, and file fields yield the alt text of each reference. Only live,
 * visible rows count (workspace 0, not deleted, not hidden).
 */
final class ContentCollector
{
    /** @var array<string, list<string>> */
    private array $columnNames = [];

    /**
     * Columns that are input/text in TCA but are not reader-facing copy.
     */
    private const IGNORED_COLUMNS = ['rowDescription', 'l18n_diffsource', 'l10n_diffsource', 't3ver_label', 'tx_impexp_origuid', 'editlock', 'fe_group', 'tsconfig_includes', 'TSconfig', 'backend_layout', 'backend_layout_next_level', 'content_from_pid', 'cache_tags', 'target', 'url', 'mount_pid', 'shortcut', 'canonical_link', 'sitemap_changefreq', 'sitemap_priority', 'no_index', 'no_follow', 'module', 'media', 'categories', 'layout', 'l18n_cfg', 'author', 'author_email', 'keywords', 'lastUpdated', 'newUntil'];

    private const PAGE_TEXT_COLUMNS = ['title', 'nav_title', 'subtitle', 'abstract', 'description', 'seo_title', 'og_title', 'og_description', 'twitter_title', 'twitter_description'];

    private const LEGAL_PATTERN = '/imprint|impressum|privacy|datenschutz|accessibility|barrierefrei|legal|terms|agb|cookie/i';

    /**
     * Record tables read from any page of the tree, sysfolders included.
     */
    public const RECORD_TABLES = ['tx_news_domain_model_news'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * Pages of a site in tree order. Subtrees that are sites of their own
     * (another root page) are left out.
     *
     * @param list<int> $otherRoots
     * @return list<array{uid: int, pid: int, doktype: int, title: string, slug: string, hidden: bool, legal: bool, depth: int}>
     */
    public function pageTree(int $root, array $otherRoots = []): array
    {
        $pages = [];
        $walk = function (int $uid, int $depth) use (&$walk, &$pages, $otherRoots): void {
            $row = $this->pageRow($uid);
            if ($row === null) {
                return;
            }
            $title = $this->string($row['title'] ?? '');
            $slug = $this->string($row['slug'] ?? '');
            $pages[] = [
                'uid' => $uid,
                'pid' => (int)$this->scalar($row['pid'] ?? 0),
                'doktype' => (int)$this->scalar($row['doktype'] ?? 1),
                'title' => $title,
                'slug' => $slug,
                'hidden' => (bool)$this->scalar($row['hidden'] ?? 0),
                'legal' => preg_match(self::LEGAL_PATTERN, $title . ' ' . $slug) === 1,
                'depth' => $depth,
            ];
            foreach ($this->childPageUids($uid) as $child) {
                if (!in_array($child, $otherRoots, true)) {
                    $walk($child, $depth + 1);
                }
            }
        };
        $walk($root, 0);

        return $pages;
    }

    /**
     * Every text item on one page in one language.
     *
     * @param bool $withContent read tt_content (false for sysfolders that only hold records)
     * @return list<TextItem>
     */
    public function collectPage(int $pageUid, int $language, bool $withContent = true): array
    {
        $items = [];

        $pageRow = $language === 0 ? $this->pageRow($pageUid) : $this->pageTranslation($pageUid, $language);
        if ($pageRow !== null) {
            $pageRecordUid = (int)$this->scalar($pageRow['uid'] ?? $pageUid);
            foreach (self::PAGE_TEXT_COLUMNS as $column) {
                if (array_key_exists($column, $pageRow)) {
                    $this->addItem($items, 'pages', $pageRecordUid, $column, $pageRow[$column], $pageUid, $pageUid, 'pages');
                }
            }
            $this->collectFiles($items, 'pages', $pageRecordUid, $pageUid, $pageUid, ['featured_image', 'og_image', 'twitter_image', 'media']);
        }

        if ($withContent) {
            foreach ($this->rows('tt_content', ['pid' => $pageUid, 'sys_language_uid' => $language], ['colPos', 'sorting']) as $row) {
                $this->collectRecord($items, 'tt_content', $row, $pageUid, (int)$this->scalar($row['uid'] ?? 0), $this->string($row['CType'] ?? ''), 0);
            }
        }

        foreach (self::RECORD_TABLES as $table) {
            if (!$this->tcaSchemaFactory->has($table)) {
                continue;
            }
            foreach ($this->rows($table, ['pid' => $pageUid, 'sys_language_uid' => $language], ['uid']) as $row) {
                $this->collectRecord($items, $table, $row, $pageUid, (int)$this->scalar($row['uid'] ?? 0), $table, 0);
            }
        }

        return $items;
    }

    /**
     * @param list<TextItem> $items
     * @param array<string, mixed> $row
     */
    private function collectRecord(array &$items, string $table, array $row, int $pageUid, int $elementUid, string $elementType, int $depth): void
    {
        if ($depth > 3 || !$this->tcaSchemaFactory->has($table)) {
            return;
        }
        $uid = (int)$this->scalar($row['uid'] ?? 0);
        $schema = $this->recordSchema($table, $row);

        foreach ($schema->getFields() as $field) {
            $name = $field->getName();
            if (in_array($name, self::IGNORED_COLUMNS, true) || !array_key_exists($name, $row) && !$field->isType(TableColumnType::INLINE, TableColumnType::FILE)) {
                continue;
            }
            if ($field->isType(TableColumnType::INPUT, TableColumnType::TEXT)) {
                $this->addItem($items, $table, $uid, $name, $row[$name] ?? '', $pageUid, $elementUid, $elementType);
                continue;
            }
            if ($field->isType(TableColumnType::FILE)) {
                $this->collectFiles($items, $table, $uid, $pageUid, $elementUid, [$name], $elementType);
                continue;
            }
            if ($field->isType(TableColumnType::INLINE)) {
                foreach ($this->inlineChildren($field, $table, $uid) as [$childTable, $childRow]) {
                    $this->collectRecord($items, $childTable, $childRow, $pageUid, $elementUid, $elementType, $depth + 1);
                }
            }
        }
    }

    /**
     * @param list<TextItem> $items
     * @param list<string> $fields
     */
    private function collectFiles(array &$items, string $table, int $uid, int $pageUid, int $elementUid, array $fields, string $elementType = ''): void
    {
        $queryBuilder = $this->queryBuilder('sys_file_reference');
        $rows = $queryBuilder
            ->select('r.uid', 'r.fieldname', 'r.alternative', 'r.title', 'r.uid_local', 'm.alternative AS file_alternative', 'f.identifier')
            ->from('sys_file_reference', 'r')
            ->leftJoin('r', 'sys_file_metadata', 'm', 'm.file = r.uid_local AND m.sys_language_uid = 0')
            ->leftJoin('r', 'sys_file', 'f', 'f.uid = r.uid_local')
            ->where(
                $queryBuilder->expr()->eq('r.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('r.uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('r.fieldname', $queryBuilder->createNamedParameter($fields, Connection::PARAM_STR_ARRAY)),
                $queryBuilder->expr()->eq('r.deleted', 0),
                $queryBuilder->expr()->eq('r.hidden', 0),
                $queryBuilder->expr()->eq('r.t3ver_wsid', 0),
            )
            ->orderBy('r.sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $stored = $this->string($row['alternative'] ?? '');
            $alternative = $stored !== '' ? $stored : $this->string($row['file_alternative'] ?? '');
            $items[] = new TextItem(
                table: 'sys_file_reference',
                uid: (int)$this->scalar($row['uid'] ?? 0),
                field: 'alternative',
                value: $alternative,
                pageUid: $pageUid,
                elementUid: $elementUid,
                elementType: $elementType,
                role: FieldRole::Alt,
                file: $this->string($row['identifier'] ?? ''),
                rawValue: $stored,
            );
        }
    }

    /**
     * @return iterable<array{0: string, 1: array<string, mixed>}>
     */
    private function inlineChildren(FieldTypeInterface $field, string $parentTable, int $parentUid): iterable
    {
        $config = $field->getConfiguration();
        $childTable = $this->string($config['foreign_table'] ?? '');
        $foreignField = $this->string($config['foreign_field'] ?? '');
        if ($childTable === '' || $foreignField === '' || $childTable === 'sys_file_reference' || !$this->tcaSchemaFactory->has($childTable)) {
            return;
        }
        $conditions = [$foreignField => $parentUid];
        $tableField = $this->string($config['foreign_table_field'] ?? '');
        if ($tableField !== '') {
            $conditions[$tableField] = $parentTable;
        }
        $matchFields = $config['foreign_match_fields'] ?? [];
        if (is_array($matchFields)) {
            foreach ($matchFields as $matchField => $matchValue) {
                if (is_scalar($matchValue)) {
                    $conditions[(string)$matchField] = $matchValue;
                }
            }
        }
        $sorting = $this->string($config['foreign_sortby'] ?? '');
        foreach ($this->rows($childTable, $conditions, $sorting !== '' ? [$sorting] : ['uid']) as $row) {
            yield [$childTable, $row];
        }
    }

    /**
     * @param list<TextItem> $items
     */
    private function addItem(array &$items, string $table, int $uid, string $field, mixed $value, int $pageUid, int $elementUid, string $elementType): void
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        if ($value === '' || str_starts_with($value, '{') || str_starts_with($value, '[')) {
            return;
        }
        $role = FieldRole::fromField($field, $table);
        if ($role === FieldRole::Skip) {
            return;
        }
        $items[] = new TextItem($table, $uid, $field, $value, $pageUid, $elementUid, $elementType, $role);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function recordSchema(string $table, array $row): TcaSchema
    {
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->supportsSubSchema()) {
            return $schema;
        }
        $typeField = $schema->getSubSchemaTypeInformation()->getFieldName();
        $type = $this->string($row[$typeField] ?? '');

        return $type !== '' && $schema->hasSubSchema($type) ? $schema->getSubSchema($type) : $schema;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pageRow(int $uid): ?array
    {
        $queryBuilder = $this->queryBuilder('pages');
        $row = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('t3ver_wsid', 0),
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pageTranslation(int $uid, int $language): ?array
    {
        $rows = $this->rows('pages', ['l10n_parent' => $uid, 'sys_language_uid' => $language], ['uid']);

        return $rows[0] ?? null;
    }

    /**
     * @return list<int>
     */
    private function childPageUids(int $uid): array
    {
        return array_map(
            fn (array $row): int => (int)$this->scalar($row['uid'] ?? 0),
            $this->rows('pages', ['pid' => $uid, 'sys_language_uid' => 0], ['sorting'], true)
        );
    }

    /**
     * Live rows matching the conditions. Hidden rows are left out unless
     * asked for, because hidden pages still have visible children.
     *
     * @param array<string, scalar> $conditions
     * @param list<string> $orderBy
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, array $conditions, array $orderBy, bool $includeHidden = false): array
    {
        $queryBuilder = $this->queryBuilder($table);
        $queryBuilder->select('*')->from($table);
        foreach ($conditions as $column => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq(
                $column,
                $queryBuilder->createNamedParameter($value, is_int($value) ? Connection::PARAM_INT : Connection::PARAM_STR)
            ));
        }
        $columnNames = $this->columnNames($table);
        foreach (['deleted' => 0, 't3ver_wsid' => 0] as $column => $value) {
            if (in_array($column, $columnNames, true)) {
                $queryBuilder->andWhere($queryBuilder->expr()->eq($column, $value));
            }
        }
        if (!$includeHidden && in_array('hidden', $columnNames, true)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('hidden', 0));
        }
        foreach ($orderBy as $column) {
            if (in_array(strtolower($column), $columnNames, true)) {
                $queryBuilder->addOrderBy($column);
            }
        }

        return array_values($queryBuilder->executeQuery()->fetchAllAssociative());
    }

    /**
     * @return list<string>
     */
    private function columnNames(string $table): array
    {
        if (!isset($this->columnNames[$table])) {
            $columns = $this->connectionPool->getConnectionForTable($table)->createSchemaManager()->listTableColumns($table);
            $this->columnNames[$table] = array_values(array_map('strtolower', array_keys($columns)));
        }

        return $this->columnNames[$table];
    }

    private function queryBuilder(string $table): QueryBuilder
    {
        // Restrictions are applied explicitly: the default ones would also
        // hide rows by start/end time and language, which the audit reports on.
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private function scalar(mixed $value): int|float|string|bool
    {
        return is_scalar($value) ? $value : 0;
    }
}
