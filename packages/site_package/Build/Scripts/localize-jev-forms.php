<?php

declare(strict_types=1);

/**
 * Translate only the five Jev lab forms and their inline records.
 *
 * Run from the project root. The default is a read-only preflight:
 *   php packages/site_package/Build/Scripts/localize-jev-forms.php
 *   php packages/site_package/Build/Scripts/localize-jev-forms.php --apply
 *
 * Page copy is handled separately by sitepackage:content:translate. The
 * generic translator also selects non-Jev forms when given their shared
 * storage page; this script deliberately selects only the Jev demo marker.
 */

use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\SitePackage\Translation\TranslationMemory;

$root = dirname(__DIR__, 4);
$classLoader = require $root . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
$container = Bootstrap::init($classLoader);
$pool = $container->get(ConnectionPool::class);
$apply = in_array('--apply', $argv, true);

const FORM_TABLE = 'tx_powermail_domain_model_form';
const PAGE_TABLE = 'tx_powermail_domain_model_page';
const FIELD_TABLE = 'tx_powermail_domain_model_field';

/** @return list<array<string, mixed>> */
function rows(ConnectionPool $pool, string $table, array $where): array
{
    $query = $pool->getQueryBuilderForTable($table);
    $query->getRestrictions()->removeAll();
    $conditions = [$query->expr()->eq('deleted', 0), $query->expr()->eq('t3ver_wsid', 0)];
    foreach ($where as $field => $value) {
        $conditions[] = is_array($value)
            ? $query->expr()->in($field, $query->createNamedParameter($value, Connection::PARAM_INT_ARRAY))
            : $query->expr()->eq($field, $query->createNamedParameter($value));
    }
    return $query->select('*')->from($table)->where(...$conditions)->orderBy('uid')->executeQuery()->fetchAllAssociative();
}

/** @return array<string, mixed>|null */
function translation(ConnectionPool $pool, string $table, int $source, int $language): ?array
{
    $matches = rows($pool, $table, ['l10n_parent' => $source, 'sys_language_uid' => $language]);
    if (count($matches) > 1) {
        throw new RuntimeException("Duplicate translations for $table:$source in language $language.");
    }
    return $matches[0] ?? null;
}

function write(array $data, array $commands = []): void
{
    if ($data === [] && $commands === []) {
        return;
    }
    $handler = GeneralUtility::makeInstance(DataHandler::class);
    $handler->dontProcessTransformations = true;
    $handler->start($data, $commands);
    $handler->process_datamap();
    $handler->process_cmdmap();
    if ($handler->errorLog !== []) {
        throw new RuntimeException(implode("\n", $handler->errorLog));
    }
}

$forms = array_values(array_filter(
    rows($pool, FORM_TABLE, ['sys_language_uid' => 0]),
    static fn (array $row): bool => str_contains(' ' . $row['css'] . ' ', ' webcon-jev-demo '),
));
if (count($forms) !== 5) {
    throw new RuntimeException('Expected exactly five active Jev demo forms; found ' . count($forms) . '.');
}
$pages = rows($pool, PAGE_TABLE, ['sys_language_uid' => 0, 'form' => array_column($forms, 'uid')]);
$fields = rows($pool, FIELD_TABLE, ['sys_language_uid' => 0, 'page' => array_column($pages, 'uid')]);
$sources = [FORM_TABLE => $forms, PAGE_TABLE => $pages, FIELD_TABLE => $fields];
$fieldsToTranslate = [FORM_TABLE => ['title'], PAGE_TABLE => ['title'], FIELD_TABLE => ['title', 'placeholder', 'text', 'mandatory_text']];
$plans = [];

// Resolve every label before creating anything. Missing text aborts the run.
foreach (['zh' => 2, 'hu' => 3] as $code => $language) {
    $memory = TranslationMemory::fromFile($root . '/packages/site_package/Resources/Private/Data/Translations/desiderio/' . $code . '.json', $code);
    foreach ($sources as $table => $records) {
        foreach ($records as $source) {
            $changes = [];
            foreach ($fieldsToTranslate[$table] as $field) {
                $sourceText = (string)$source[$field];
                $changes[$field] = $memory->translate($sourceText)
                    ?? throw new RuntimeException("Missing $code translation for $table:{$source['uid']}.$field: $sourceText");
            }
            if ($table === FIELD_TABLE) {
                $options = [];
                foreach (explode("\n", (string)$source['settings']) as $line) {
                    $parts = explode('|', $line, 2);
                    $label = $memory->translate($parts[0])
                        ?? throw new RuntimeException("Missing $code option translation: {$parts[0]}");
                    $options[] = $label . (isset($parts[1]) ? '|' . $parts[1] : '');
                }
                $changes['settings'] = implode("\n", $options);
            }
            $plans[$language][$table][(int)$source['uid']] = $changes;
        }
    }
}

if ($apply) {
    Bootstrap::initializeBackendUser(CommandLineUserAuthentication::class);
    $GLOBALS['LANG'] = $container->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
    Bootstrap::initializeBackendAuthentication();
    $user = $GLOBALS['BE_USER'] ?? null;
    if (!$user instanceof CommandLineUserAuthentication) {
        throw new RuntimeException('A CLI backend user is required.');
    }
    $user->setWorkspace(0);
}

foreach ($plans as $language => $tables) {
    $missing = 0;
    $changed = 0;
    // Form localization normally includes its inline children. Check each
    // level again so this also repairs an incomplete previous localization.
    foreach ($tables as $table => $records) {
        $commands = [];
        foreach ($records as $source => $values) {
            if (translation($pool, $table, $source, $language) === null) {
                $commands[$table][$source] = ['localize' => $language];
                $missing++;
            }
        }
        if ($apply) {
            write([], $commands);
        }
    }
    $data = [];
    foreach ($tables as $table => $records) {
        foreach ($records as $source => $values) {
            $target = translation($pool, $table, $source, $language);
            if ($target === null) {
                if ($apply) {
                    throw new RuntimeException("Localization missing after DataHandler: $table:$source, language $language.");
                }
                continue;
            }
            foreach ($values as $field => $value) {
                if ((string)$target[$field] !== $value) {
                    $data[$table][(int)$target['uid']][$field] = $value;
                    $changed++;
                }
            }
        }
    }
    if ($apply) {
        write($data);
    }
    printf("%s language %d: %d missing records, %d changed text fields; %d forms, %d pages, %d fields checked.\n", $apply ? 'Applied' : 'Dry run', $language, $missing, $changed, count($forms), count($pages), count($fields));
}
