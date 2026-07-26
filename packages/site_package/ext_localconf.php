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

// Serve processed images as WebP. Measured on this install: a 640×800 portrait
// is 114 KB as the default quality-85 JPEG and 77 KB as WebP — and images are
// 95% of a chapter page's weight (1,287 KB of 1,354 KB), so nothing else on the
// page is worth optimising until this is.
//
// SVG stays SVG; it is not a raster format and converting it would rasterise
// icons. Everything else, including PNG, goes to WebP — alpha survives, so the
// signature artwork and the logo marks keep their transparency.
//
// Core's own default configuration recommends exactly this ("Ideally, use
// webp/avif here (future default?)"). It lives here rather than in
// config/system/settings.php because that file is git-ignored.
$GLOBALS['TYPO3_CONF_VARS']['GFX']['imageFileConversionFormats'] = [
    'svg' => 'svg',
    'default' => 'webp',
];

// StaticFileCache: let Apache serve the static files, never the PHP fallback.
//
// The fallback middleware is a convenience for setups whose webserver has no
// rewrite rules. It guards less than the rules do: it strips the query string
// before looking up the cached path, and it has no check for ADMCMD_prev — so a
// workspace preview link is answered with the live cached page instead of the
// workspace version. Measured here: 0.047s (from cache) instead of a render.
//
// public/.htaccess carries the full rule set, including the ADMCMD_prev and
// logged-in-cookie guards, and it is verified to serve a cache hit in ~0.011s
// without booting PHP. With one correct path there is no reason to keep a
// second, weaker one.
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['staticfilecache']['useFallbackMiddleware'] = '0';
