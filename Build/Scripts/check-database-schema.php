<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;

// Read-only verification using the same TYPO3 14 schema comparison as
// extension:setup. Its exit status alone does not expose failed SQL statements.
$classLoader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
$container = Bootstrap::init($classLoader);
$reader = $container->get(SqlReader::class);
$statements = $reader->getCreateTableStatementArray($reader->getTablesDefinitionString());
$suggestions = $container->get(SchemaMigrator::class)->getUpdateSuggestions($statements);
$pending = [];
foreach ($suggestions as $connection => $actions) {
    foreach (['add', 'change', 'create_table', 'change_table'] as $action) {
        if (($actions[$action] ?? []) !== []) {
            $pending[$connection][$action] = array_values($actions[$action]);
        }
    }
}
echo json_encode(['pending' => $pending], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($pending === [] ? 0 : 1);
