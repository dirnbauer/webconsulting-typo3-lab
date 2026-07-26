<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Webconsulting\SitePackage\Bootstrap\McpTableConfiguration;

McpTableConfiguration::register();

// Enrich Desiderio's field-specific RTE preset with the Cowriter plugin.
// Desiderio Content Blocks explicitly select `richtextConfiguration:
// desiderio`, so `RTE.default.preset = cowriter` alone only reaches generic
// TYPO3 fields. Re-registering the preset under the same identifier keeps the
// complete Desiderio editing vocabulary and adds the four Cowriter controls to
// every Desiderio RTE field in this lab.
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['desiderio']
    = 'EXT:site_package/Configuration/RTE/DesiderioCowriter.yaml';

// Force EXT:news manual sorting on. Without it, tx_news_domain_model_news has
// no TCA `sortby`, so the Records module (and the EXT:records_list_types grid
// view) cannot drag-and-drop reorder news. This lives here — in version control —
// because config/system/settings.php is git-ignored, so the setting would
// otherwise be lost whenever that instance config is regenerated.
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['news']['manualSorting'] = '1';

// Hide EXT:news' Web > News Administration backend module. Editors use the
// regular TYPO3 record/page modules and News API Studio instead. This also
// keeps /typo3/module/web/NewsAdministration/ from being registered.
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['news']['showAdministrationModule'] = '0';
