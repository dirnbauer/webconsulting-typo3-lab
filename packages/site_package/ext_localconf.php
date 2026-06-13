<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Webconsulting\SitePackage\Bootstrap\McpTableConfiguration;

McpTableConfiguration::register();

// Force EXT:news manual sorting on. Without it, tx_news_domain_model_news has
// no TCA `sortby`, so the Records module (and the EXT:records_list_types grid
// view) cannot drag-and-drop reorder news. This lives here — in version control —
// because config/system/settings.php is git-ignored, so the setting would
// otherwise be lost whenever that instance config is regenerated.
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['news']['manualSorting'] = '1';
