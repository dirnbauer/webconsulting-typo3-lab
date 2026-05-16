<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Expose tt_address to the MCP dynamic tools with the plural content type label.
$typo3Configuration = $GLOBALS['TYPO3_CONF_VARS'] ?? [];
if (!is_array($typo3Configuration)) {
    $typo3Configuration = [];
}

$extensionConfiguration = $typo3Configuration['EXTCONF'] ?? [];
if (!is_array($extensionConfiguration)) {
    $extensionConfiguration = [];
}

$mcpServerConfiguration = $extensionConfiguration['ms_mcp_server'] ?? [];
if (!is_array($mcpServerConfiguration)) {
    $mcpServerConfiguration = [];
}

$tables = $mcpServerConfiguration['tables'] ?? [];
if (!is_array($tables)) {
    $tables = [];
}

$tables['tt_address'] = [
    'label' => 'Addresses',
    'prefix' => 'tt_address',
];
$mcpServerConfiguration['tables'] = $tables;
$extensionConfiguration['ms_mcp_server'] = $mcpServerConfiguration;
$typo3Configuration['EXTCONF'] = $extensionConfiguration;
$GLOBALS['TYPO3_CONF_VARS'] = $typo3Configuration;
