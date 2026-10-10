<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Category fields offer the categories of the record's own site: the ones
// stored in the folders that site lists in page TSconfig as
// TCEFORM.<table>.<field>.PAGE_TSCONFIG_IDLIST (config/sites/<site>/page.tsconfig).
// Without that setting - file metadata, backend user permissions, sites
// without categories of their own - core replaces the marker with 0 and the
// field keeps offering every category. Core's own clause (default and
// all-languages categories only) is kept; a field that defines its own
// foreign_table_where is left alone. Core applies its default only after
// the overrides ran, so this one wins.
$defaultLanguageOnly = ' AND {#sys_category}.{#sys_language_uid} IN (-1, 0)';
foreach ($GLOBALS['TCA'] as $table => $tableConfiguration) {
    foreach ($tableConfiguration['columns'] ?? [] as $field => $column) {
        if (($column['config']['type'] ?? '') !== 'category') {
            continue;
        }
        if (!isset($column['config']['foreign_table_where'])) {
            $GLOBALS['TCA'][$table]['columns'][$field]['config']['foreign_table_where'] = $defaultLanguageOnly
                . ' AND ({#sys_category}.{#pid} IN (###PAGE_TSCONFIG_IDLIST###) OR \'###PAGE_TSCONFIG_IDLIST###\' = \'0\')';
        }
        // A record type's own where-clause replaces the column's. EXT:blog
        // builds the one for a blog category's parent from pages.categories
        // before core has added its language condition, so the picker listed
        // every translation; such clauses get the condition in front.
        foreach ($tableConfiguration['types'] ?? [] as $type => $typeConfiguration) {
            $typeClause = $typeConfiguration['columnsOverrides'][$field]['config']['foreign_table_where'] ?? null;
            if (is_string($typeClause) && stripos($typeClause, 'sys_language_uid') === false) {
                $GLOBALS['TCA'][$table]['types'][$type]['columnsOverrides'][$field]['config']['foreign_table_where'] = $defaultLanguageOnly . ' ' . $typeClause;
            }
        }
    }
}
