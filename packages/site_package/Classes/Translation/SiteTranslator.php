<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Translation;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\TableColumnType;
use TYPO3\CMS\Core\Schema\Capability\FieldCapability;
use TYPO3\CMS\Core\Schema\Capability\LanguageAwareSchemaCapability;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\SitePackage\ContentAudit\ContentCollector;
use Webconsulting\SitePackage\ContentAudit\FieldRole;

/**
 * Translates a site's pages, content elements and records into one language
 * from a translation memory.
 *
 * Every translation is created the way an editor's "Translate" button creates
 * it: DataHandler `localize` in connected mode, which also localizes Content
 * Blocks collections and file references with the wiring the frontend overlay
 * needs. Then the text fields are filled from the memory. Fields that hold
 * identifiers (icons, variants, anchors) keep the source value.
 *
 * Re-running is idempotent: existing translations are updated in place and
 * only fields whose value differs are written. Only live rows are read or
 * written (workspace 0); drafts in a workspace are never touched.
 */
final class SiteTranslator
{
    private const CONTENT_DOKTYPES_WITHOUT_CONTENT = [199, 254, 255];

    private TranslationMemory $memory;
    private TranslationScope $scope;
    private int $language = 0;

    /** @var array<string, array{source: string, role: string, where: array<string, true>}> normalised source => report */
    private array $missing = [];

    /** @var array<string, array<string, string>> table.field => text => where */
    private array $copied = [];

    /** @var array<string, array<int|string, array<string, mixed>>> */
    private array $dataMap = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $syncCommands = [];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, list<string>> */
    private array $columnNames = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly TcaSchemaFactory $tcaSchemaFactory,
        private readonly ContentCollector $collector,
    ) {
        // Replaced at the start of every translate() call.
        $this->memory = new TranslationMemory('');
        $this->scope = new TranslationScope();
    }

    /**
     * @param list<int> $otherRoots roots of other sites inside this tree, left out
     * @param list<int> $onlyPages restrict the run to these pages (and the records stored on them)
     */
    public function translate(
        int $root,
        array $otherRoots,
        int $language,
        string $languageCode,
        TranslationMemory $memory,
        TranslationScope $scope,
        bool $dryRun,
        array $onlyPages = [],
    ): TranslationResult {
        $this->memory = $memory;
        $this->scope = $scope;
        $this->language = $language;
        $this->missing = [];
        $this->copied = [];
        $this->dataMap = [];
        $this->syncCommands = [];
        $this->counts = ['pagesLocalized' => 0, 'recordsLocalized' => 0, 'childrenSynchronized' => 0, 'fieldsUpdated' => 0, 'recordsUpdated' => 0];

        [$pages, $records] = $this->units($root, $otherRoots, $languageCode, $onlyPages);

        // 1. Create what does not exist yet: pages first, their content second.
        $newPages = [];
        $localize = [];
        foreach ($pages as $page) {
            if ($this->translationUid('pages', $page['uid']) === null) {
                $newPages[] = $page['uid'];
                $localize['pages'][$page['uid']] = ['localize' => $language];
            }
        }
        $this->counts['pagesLocalized'] = count($newPages);
        if (!$dryRun) {
            $this->processCommands($localize);
        }
        $localize = [];
        foreach ($records as [$table, $uid]) {
            if ($this->translationUid($table, $uid) === null) {
                $localize[$table][$uid] = ['localize' => $language];
                $this->counts['recordsLocalized']++;
            }
        }
        if (!$dryRun) {
            $this->processCommands($localize);
        }

        // 2. Children added to a source after its translation was made.
        foreach ($records as [$table, $uid]) {
            $this->planRecord($table, $uid, true);
        }
        foreach ($pages as $page) {
            $this->planPage($page['uid'], in_array($page['uid'], $newPages, true), true);
        }
        if (!$dryRun && $this->syncCommands !== []) {
            $this->processCommands($this->syncCommands);
        }

        // 3. Fill every translation from the memory.
        $this->dataMap = [];
        $this->missing = [];
        $this->copied = [];
        foreach ($pages as $page) {
            $this->planPage($page['uid'], in_array($page['uid'], $newPages, true), false);
        }
        foreach ($records as [$table, $uid]) {
            $this->planRecord($table, $uid, false);
        }
        foreach ($this->dataMap as $rows) {
            $this->counts['recordsUpdated'] += count($rows);
            foreach ($rows as $fields) {
                $this->counts['fieldsUpdated'] += count($fields);
            }
        }
        if (!$dryRun && $this->dataMap !== []) {
            $dataHandler = $this->dataHandler();
            // Translations mirror the stored source HTML; running the RTE
            // transformation again would change it on every run.
            $dataHandler->dontProcessTransformations = true;
            $dataHandler->start($this->dataMap, []);
            $dataHandler->process_datamap();
            $this->assertNoErrors($dataHandler, 'write translations');
        }

        $missing = [];
        foreach ($this->missing as $info) {
            $missing[] = ['source' => $info['source'], 'role' => $info['role'], 'where' => array_keys($info['where'])];
        }

        return new TranslationResult($languageCode, $this->counts, $missing, $memory->unused(), $this->copied);
    }

    /**
     * Pages and records in scope, in tree order.
     *
     * @param list<int> $otherRoots
     * @param list<int> $onlyPages
     * @return array{0: list<array{uid: int}>, 1: list<array{0: string, 1: int}>}
     */
    private function units(int $root, array $otherRoots, string $languageCode, array $onlyPages): array
    {
        $tree = $this->collector->pageTree($root, $otherRoots);
        $parents = [];
        foreach ($tree as $page) {
            $parents[$page['uid']] = $page['pid'];
        }

        $pages = [];
        $records = [];
        foreach ($tree as $page) {
            if ($page['hidden'] || ($onlyPages !== [] && !in_array($page['uid'], $onlyPages, true))
                || $this->scope->skips($this->rootline($page['uid'], $parents, $root), $languageCode)) {
                continue;
            }
            $hasContent = !in_array($page['doktype'], self::CONTENT_DOKTYPES_WITHOUT_CONTENT, true);
            if ($hasContent) {
                $pages[] = ['uid' => $page['uid']];
                foreach ($this->defaultRows('tt_content', ['pid' => $page['uid']], ['colPos', 'sorting']) as $row) {
                    $records[] = ['tt_content', $this->int($row['uid'] ?? 0)];
                }
            }
            foreach ($this->scope->recordTables as $table) {
                if (!$this->tcaSchemaFactory->has($table)) {
                    continue;
                }
                foreach ($this->defaultRows($table, ['pid' => $page['uid']], ['sorting', 'uid']) as $row) {
                    $records[] = [$table, $this->int($row['uid'] ?? 0)];
                }
            }
        }

        return [$pages, $records];
    }

    /**
     * @param array<int, int> $parents
     * @return list<int>
     */
    private function rootline(int $uid, array $parents, int $root): array
    {
        $rootline = [$uid];
        while ($uid !== $root && isset($parents[$uid])) {
            $uid = $parents[$uid];
            $rootline[] = $uid;
        }

        return $rootline;
    }

    private function planPage(int $uid, bool $isNew, bool $syncOnly): void
    {
        $source = $this->row('pages', $uid);
        $translationUid = $this->translationUid('pages', $uid);
        if ($source === null) {
            return;
        }
        $translation = $translationUid !== null ? $this->row('pages', $translationUid) : null;
        $where = sprintf('page %d', $uid);

        if (!$syncOnly) {
            $changes = [];
            foreach ([...ContentCollector::PAGE_TEXT_COLUMNS, ...($this->scope->translateFields['pages'] ?? [])] as $field) {
                if (!array_key_exists($field, $source)) {
                    continue;
                }
                $wanted = $this->translatedValue('pages', $field, $this->string($source[$field]), $this->role('pages', $field), $where);
                $this->addChange($changes, $translation, $field, $wanted);
            }
            // Visibility and menu visibility follow the source: a seeder that hides
            // and shows its pages again would otherwise leave translations hidden.
            $this->addChange($changes, $translation, 'hidden', $this->int($source['hidden'] ?? 0));
            $this->addChange($changes, $translation, 'nav_hide', $this->int($source['nav_hide'] ?? 0));
            // A new translation gets the address of its source under the
            // language prefix (/de/features/…); existing addresses stay.
            if ($isNew) {
                $this->addChange($changes, $translation, 'slug', $this->string($source['slug'] ?? ''));
            }
            if ($changes !== [] && $translationUid !== null) {
                $this->dataMap['pages'][$translationUid] = $changes;
            }
        }

        $this->planFiles('pages', $source, $translationUid, $where, $syncOnly);
    }

    private function planRecord(string $table, int $uid, bool $syncOnly, int $depth = 0): void
    {
        $source = $this->row($table, $uid);
        if ($source === null || $depth > 4) {
            return;
        }
        $translationUid = $this->translationUid($table, $uid);
        $translation = $translationUid !== null ? $this->row($table, $translationUid) : null;
        $schema = $this->recordSchema($table, $source);
        $where = sprintf('page %d %s:%d (%s)', $this->int($source['pid'] ?? 0), $table, $uid, $this->recordType($table, $source));

        if (!$syncOnly) {
            $changes = [];
            foreach ($this->textFields($table, $schema) as $field) {
                $name = $field->getName();
                if (!array_key_exists($name, $source)) {
                    continue;
                }
                $value = $this->string($source[$name]);
                $role = $this->role($table, $name);
                $separator = $this->scope->lineSeparator($table, $name);
                if ($separator !== null) {
                    $wanted = $this->translatedLines($table, $name, $value, $separator, $where);
                } elseif ($role === FieldRole::Skip || str_starts_with(ltrim($value), '{') || str_starts_with(ltrim($value), '[')) {
                    $this->reportCopied($table, $name, $value, $where);
                    $wanted = $value;
                } else {
                    $wanted = $this->translatedValue($table, $name, $value, $role, $where);
                }
                $this->addChange($changes, $translation, $name, $wanted);
            }
            $hiddenField = $this->hiddenField($table);
            if ($hiddenField !== null) {
                $this->addChange($changes, $translation, $hiddenField, $this->int($source[$hiddenField] ?? 0));
            }
            // Record slugs (news, tags, categories) of a new translation are
            // the source's, not one built from the "[Translate to …]" title
            // that localize starts from, so detail URLs match across languages.
            if ($translation !== null) {
                foreach ($schema->getFields() as $field) {
                    $slug = $this->string($translation[$field->getName()] ?? '');
                    if ($field->isType(TableColumnType::SLUG) && ($slug === '' || str_contains($slug, 'translate-to-'))) {
                        $this->addChange($changes, $translation, $field->getName(), $this->string($source[$field->getName()] ?? ''));
                    }
                }
            }
            if ($changes !== [] && $translationUid !== null) {
                $this->dataMap[$table][$translationUid] = $changes;
            }
        }

        $this->planFiles($table, $source, $translationUid, $where, $syncOnly, $schema);

        foreach ($schema->getFields() as $field) {
            if (!$field->isType(TableColumnType::INLINE)) {
                continue;
            }
            $children = $this->inlineChildren($field, $table, $uid);
            if ($children === null) {
                continue;
            }
            [$childTable, $childRows] = $children;
            $unsynchronized = false;
            foreach ($childRows as $childRow) {
                $childUid = $this->int($childRow['uid'] ?? 0);
                if ($this->translationUid($childTable, $childUid) === null) {
                    $unsynchronized = true;
                    continue;
                }
                $this->planRecord($childTable, $childUid, $syncOnly, $depth + 1);
            }
            if ($unsynchronized && $syncOnly && $translationUid !== null) {
                $this->syncCommands[$table][$uid]['inlineLocalizeSynchronize'] = ['field' => $field->getName(), 'language' => $this->language, 'action' => 'synchronize'];
                $this->counts['childrenSynchronized']++;
            }
            if (!$syncOnly && $translationUid === null) {
                // Dry run or a failed localize: still report what the children need.
                foreach ($childRows as $childRow) {
                    $this->planRecord($childTable, $this->int($childRow['uid'] ?? 0), false, $depth + 1);
                }
            }
        }
    }

    /**
     * Alt texts, titles and captions of a record's images. An empty alt text
     * on the reference falls back to the file's own, which is English, so the
     * translation gets the translated fallback written into the reference.
     *
     * @param array<string, mixed> $source
     */
    private function planFiles(string $table, array $source, ?int $translationUid, string $where, bool $syncOnly, ?TcaSchema $schema = null): void
    {
        $schema ??= $this->recordSchema($table, $source);
        $sourceUid = $this->int($source['uid'] ?? 0);
        foreach ($schema->getFields() as $field) {
            if (!$field->isType(TableColumnType::FILE)) {
                continue;
            }
            $references = $this->fileReferences($table, $sourceUid, $field->getName());
            if ($references === []) {
                continue;
            }
            $unsynchronized = false;
            foreach ($references as $reference) {
                $referenceUid = $this->int($reference['uid'] ?? 0);
                $translatedUid = $this->translationUid('sys_file_reference', $referenceUid);
                if ($translatedUid === null) {
                    $unsynchronized = true;
                }
                if ($syncOnly) {
                    continue;
                }
                $translated = $translatedUid !== null ? $this->row('sys_file_reference', $translatedUid) : null;
                $alternative = $this->string($reference['alternative'] ?? '');
                if ($alternative === '') {
                    $alternative = $this->string($reference['file_alternative'] ?? '');
                }
                $changes = [];
                $this->addChange($changes, $translated, 'alternative', $this->translatedValue('sys_file_reference', 'alternative', $alternative, FieldRole::Alt, $where));
                foreach (['title' => FieldRole::Headline, 'description' => FieldRole::Other] as $column => $role) {
                    $this->addChange($changes, $translated, $column, $this->translatedValue('sys_file_reference', $column, $this->string($reference[$column] ?? ''), $role, $where));
                }
                if ($changes !== [] && $translatedUid !== null) {
                    $this->dataMap['sys_file_reference'][$translatedUid] = $changes;
                }
            }
            if ($unsynchronized && $syncOnly && $translationUid !== null) {
                $this->syncCommands[$table][$sourceUid]['inlineLocalizeSynchronize'] = ['field' => $field->getName(), 'language' => $this->language, 'action' => 'synchronize'];
                $this->counts['childrenSynchronized']++;
            }
        }
    }

    /**
     * What a field is for; a field the scope marks as copy is never an identifier.
     */
    private function role(string $table, string $field): FieldRole
    {
        $role = FieldRole::fromField($field, $table);

        return $role === FieldRole::Skip && $this->scope->translates($table, $field) ? FieldRole::Other : $role;
    }

    /**
     * A value kept as it is because its field name marks an identifier, but
     * which reads like copy: a candidate for `translateFields` in the scope.
     */
    private function reportCopied(string $table, string $field, string $value, string $where): void
    {
        $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/\p{L}{2,}[ ,]+\p{L}{2,}/u', $plain) !== 1 || preg_match('#^(https?:|mailto:|t3:|/|\{|\[)#', $plain) === 1) {
            return;
        }
        $this->copied[$table . '.' . $field][$plain] = $where;
    }

    private function translatedValue(string $table, string $field, string $source, FieldRole $role, string $where): string
    {
        if (trim($source) === '') {
            return $source;
        }
        $translation = $this->memory->translate($source);
        if ($translation !== null) {
            return $translation;
        }
        // Reported as stored: line breaks matter in fields that hold one
        // entry per line (chart data, addresses), even though the lookup
        // ignores whitespace.
        $key = TranslationMemory::key($source);
        $this->missing[$key] ??= ['source' => trim($source), 'role' => $role->value, 'where' => []];
        $this->missing[$key]['where'][$where . ' ' . $table . '.' . $field] = true;

        return $source;
    }

    /**
     * `Label|value` per line: the label is translated, the value is what
     * the form submits and stays as it is.
     *
     * @param non-empty-string $separator
     */
    private function translatedLines(string $table, string $field, string $source, string $separator, string $where): string
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', $source) ?: [] as $line) {
            $parts = explode($separator, $line, 2);
            $label = $this->translatedValue($table, $field, $parts[0], FieldRole::Other, $where);
            $lines[] = isset($parts[1]) ? $label . $separator . $parts[1] : $label;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<string, mixed>|null $current
     */
    private function addChange(array &$changes, ?array $current, string $field, string|int $wanted): void
    {
        if ($current === null) {
            return;
        }
        $now = $current[$field] ?? null;
        if (is_int($wanted) ? $this->int($now) !== $wanted : TranslationMemory::normalise($this->string($now)) !== TranslationMemory::normalise($wanted)) {
            $changes[$field] = $wanted;
        }
    }

    /**
     * Text columns of a record type, the same the content audit reads.
     *
     * @return list<FieldTypeInterface>
     */
    private function textFields(string $table, TcaSchema $schema): array
    {
        $fields = [];
        foreach ($schema->getFields() as $field) {
            $name = $field->getName();
            if (!$field->isType(TableColumnType::INPUT, TableColumnType::TEXT)
                || $this->scope->ignores($table, $name)
                || in_array($name, ContentCollector::IGNORED_COLUMNS, true) && !$this->scope->translates($table, $name)
            ) {
                continue;
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @return array{0: string, 1: list<array<string, mixed>>}|null
     */
    private function inlineChildren(FieldTypeInterface $field, string $parentTable, int $parentUid): ?array
    {
        $config = $field->getConfiguration();
        $childTable = $this->string($config['foreign_table'] ?? '');
        $foreignField = $this->string($config['foreign_field'] ?? '');
        if ($childTable === '' || $foreignField === '' || $childTable === 'sys_file_reference'
            || !$this->tcaSchemaFactory->has($childTable) || !$this->tcaSchemaFactory->get($childTable)->isLanguageAware()) {
            return null;
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

        return [$childTable, $this->defaultRows($childTable, $conditions, $sorting !== '' ? [$sorting] : ['uid'], true)];
    }

    /**
     * Visible references of one field, with the file's own alt text.
     *
     * @return list<array<string, mixed>>
     */
    private function fileReferences(string $table, int $uid, string $field): array
    {
        $queryBuilder = $this->queryBuilder('sys_file_reference');
        $rows = $queryBuilder
            ->select('r.uid', 'r.alternative', 'r.title', 'r.description', 'm.alternative AS file_alternative')
            ->from('sys_file_reference', 'r')
            ->leftJoin('r', 'sys_file_metadata', 'm', 'm.file = r.uid_local AND m.sys_language_uid = 0')
            ->where(
                $queryBuilder->expr()->eq('r.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('r.uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.fieldname', $queryBuilder->createNamedParameter($field)),
                $queryBuilder->expr()->eq('r.sys_language_uid', 0),
                $queryBuilder->expr()->eq('r.deleted', 0),
                $queryBuilder->expr()->eq('r.hidden', 0),
                $queryBuilder->expr()->eq('r.t3ver_wsid', 0),
            )
            ->orderBy('r.sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values($rows);
    }

    private function translationUid(string $table, int $uid): ?int
    {
        $language = $this->languageCapability($table);
        if ($language === null) {
            return null;
        }
        $queryBuilder = $this->queryBuilder($table);
        $queryBuilder->select('uid')->from($table)->where(
            $queryBuilder->expr()->eq($language->getTranslationOriginPointerField()->getName(), $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq($language->getLanguageField()->getName(), $queryBuilder->createNamedParameter($this->language, Connection::PARAM_INT)),
        );
        $this->liveOnly($queryBuilder, $table);
        $translation = $queryBuilder->orderBy('uid')->setMaxResults(1)->executeQuery()->fetchOne();

        return is_numeric($translation) ? (int)$translation : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $table, int $uid): ?array
    {
        $queryBuilder = $this->queryBuilder($table);
        $queryBuilder->select('*')->from($table)->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
        );
        $this->liveOnly($queryBuilder, $table);
        $row = $queryBuilder->executeQuery()->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * Live default-language rows. Hidden rows are left out unless asked for:
     * a hidden element needs no translation, a hidden collection item is
     * localized with its element anyway.
     *
     * @param array<string, scalar> $conditions
     * @param list<string> $orderBy
     * @return list<array<string, mixed>>
     */
    private function defaultRows(string $table, array $conditions, array $orderBy, bool $includeHidden = false): array
    {
        $queryBuilder = $this->queryBuilder($table);
        $queryBuilder->select('*')->from($table);
        foreach ($conditions as $column => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq(
                $column,
                $queryBuilder->createNamedParameter($value, is_int($value) ? Connection::PARAM_INT : Connection::PARAM_STR)
            ));
        }
        $language = $this->languageCapability($table);
        if ($language !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($language->getLanguageField()->getName(), 0));
        }
        $this->liveOnly($queryBuilder, $table);
        $hiddenField = $this->hiddenField($table);
        if (!$includeHidden && $hiddenField !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($hiddenField, 0));
        }
        foreach ($orderBy as $column) {
            if (in_array(strtolower($column), $this->columnNames($table), true)) {
                $queryBuilder->addOrderBy($column);
            }
        }

        return array_values($queryBuilder->executeQuery()->fetchAllAssociative());
    }

    private function liveOnly(QueryBuilder $queryBuilder, string $table): void
    {
        $columns = $this->columnNames($table);
        if (in_array('deleted', $columns, true)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('deleted', 0));
        }
        if (in_array('t3ver_wsid', $columns, true)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('t3ver_wsid', 0));
        }
    }

    private function languageCapability(string $table): ?LanguageAwareSchemaCapability
    {
        if (!$this->tcaSchemaFactory->has($table) || !$this->tcaSchemaFactory->get($table)->isLanguageAware()) {
            return null;
        }
        $capability = $this->tcaSchemaFactory->get($table)->getCapability(TcaSchemaCapability::Language);

        return $capability instanceof LanguageAwareSchemaCapability ? $capability : null;
    }

    private function hiddenField(string $table): ?string
    {
        if (!$this->tcaSchemaFactory->has($table)) {
            return null;
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->hasCapability(TcaSchemaCapability::RestrictionDisabledField)) {
            return null;
        }
        $capability = $schema->getCapability(TcaSchemaCapability::RestrictionDisabledField);

        return $capability instanceof FieldCapability ? $capability->getFieldName() : null;
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
        $type = $this->string($row[$schema->getSubSchemaTypeInformation()->getFieldName()] ?? '');

        return $type !== '' && $schema->hasSubSchema($type) ? $schema->getSubSchema($type) : $schema;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function recordType(string $table, array $row): string
    {
        $schema = $this->tcaSchemaFactory->get($table);

        return $schema->supportsSubSchema() ? $this->string($row[$schema->getSubSchemaTypeInformation()->getFieldName()] ?? $table) : $table;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $commands
     */
    private function processCommands(array $commands): void
    {
        if ($commands === []) {
            return;
        }
        $dataHandler = $this->dataHandler();
        $dataHandler->start([], $commands);
        $dataHandler->process_cmdmap();
        $this->assertNoErrors($dataHandler, 'localize');
    }

    private function dataHandler(): DataHandler
    {
        // Logging stays on: DataHandler only collects errors in errorLog
        // when it logs, and a localize that fails must stop the run.
        return GeneralUtility::makeInstance(DataHandler::class);
    }

    private function assertNoErrors(DataHandler $dataHandler, string $step): void
    {
        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException(sprintf('DataHandler (%s): %s', $step, implode(' | ', $dataHandler->errorLog)), 1790100021);
        }
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
