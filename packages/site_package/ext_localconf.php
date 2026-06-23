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

// Hide EXT:news' Web > News Administration backend module. Editors use the
// regular TYPO3 record/page modules and News API Studio instead. This also
// keeps /typo3/module/web/NewsAdministration/ from being registered.
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['news']['showAdministrationModule'] = '0';

// Enable the Visual Editor element-library FAB (the circular "+" button in the
// frontend edit mode). The element-library-and-links patch gates the button on
// this flag (EditModeService::isElementLibraryEnabled), but neither the patch
// nor desiderio ever sets it. Same reasoning as above: keep it in version
// control so it survives config/system/settings.php being regenerated.
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['visual_editor']['elementLibraryEnabled'] = true;
