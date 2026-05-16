<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Expose tt_address to the MCP dynamic tools with the plural content type label.
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ms_mcp_server']['tables']['tt_address'] = [
    'label' => 'Addresses',
    'prefix' => 'tt_address',
];
