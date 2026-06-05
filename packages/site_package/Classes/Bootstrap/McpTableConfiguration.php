<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Bootstrap;

/**
 * Registers MCP dynamic-tool table metadata for lab extensions.
 */
final class McpTableConfiguration
{
    public static function register(): void
    {
        $tables = self::tables();
        $tables['tt_address'] = [
            'label' => 'Addresses',
            'prefix' => 'tt_address',
        ];
        self::writeTables($tables);
    }

    /**
     * @return array<string, mixed>
     */
    private static function tables(): array
    {
        $configuration = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        if (!is_array($configuration)) {
            return [];
        }

        $extensionConfiguration = $configuration['EXTCONF'] ?? null;
        if (!is_array($extensionConfiguration)) {
            return [];
        }

        $mcpServerConfiguration = $extensionConfiguration['ms_mcp_server'] ?? null;
        if (!is_array($mcpServerConfiguration)) {
            return [];
        }

        $tables = $mcpServerConfiguration['tables'] ?? null;
        if (!is_array($tables)) {
            return [];
        }

        $normalizedTables = [];
        foreach ($tables as $tableName => $tableConfiguration) {
            if (!is_string($tableName)) {
                continue;
            }
            $normalizedTables[$tableName] = $tableConfiguration;
        }

        return $normalizedTables;
    }

    /**
     * @param array<string, mixed> $tables
     */
    private static function writeTables(array $tables): void
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS'] ?? null)) {
            $GLOBALS['TYPO3_CONF_VARS'] = [];
        }

        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'] ?? null)) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'] = [];
        }

        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ms_mcp_server'] ?? null)) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ms_mcp_server'] = [];
        }

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ms_mcp_server']['tables'] = $tables;
    }
}
