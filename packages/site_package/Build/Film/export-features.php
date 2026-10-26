<?php

declare(strict_types=1);

/**
 * Writes the feature definitions the lab runs (the installed Desiderio's
 * ShowcaseFeatureDefinitions) to Build/Film/data/features.json: the facts the
 * film scripts start from, and the screenshots the films show, with their
 * paths relative to the lab root. Runs where the lab's PHP runs:
 *
 *   ddev exec php packages/site_package/Build/Film/export-features.php
 */

use Webconsulting\Desiderio\Data\Showcase\ShowcaseFeatureDefinitions;

$lab = dirname(__DIR__, 4);
require $lab . '/vendor/autoload.php';

$extension = 'vendor/webconsulting/desiderio/';
$withPath = static function (mixed $value) use (&$withPath, $extension): mixed {
    if (!is_array($value)) {
        return $value;
    }
    if (isset($value['file']) && is_string($value['file'])) {
        $value['path'] = $extension . $value['file'];
    }
    return array_map($withPath, $value);
};

$export = [
    'features' => [
        'hub' => $withPath(ShowcaseFeatureDefinitions::hub()),
        'categories' => $withPath(ShowcaseFeatureDefinitions::categories()),
    ],
    'ai' => [
        'hub' => $withPath(ShowcaseFeatureDefinitions::aiHub()),
        'categories' => $withPath(ShowcaseFeatureDefinitions::aiCategories()),
    ],
];

$target = __DIR__ . '/data/features.json';
@mkdir(dirname($target), 0777, true);
file_put_contents($target, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo $target . "\n";
