<?php

declare(strict_types=1);

// Explicit, local-only opt-in. The public lab manifest/lock are never modified.
$root = dirname(__DIR__, 2);
$source = json_decode((string)file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$source['repositories'] = array_merge([[
    'type' => 'git',
    'url' => 'git@gitlab.webconsulting.at:webcon/desktop-news-typo3.git',
    'only' => ['kdirnbauer/typo3-desktop-connector'],
]], $source['repositories']);
$source['require-dev']['kdirnbauer/typo3-desktop-connector'] = '^2.1.0';
$target = $root . '/.composer-redaktion-dev.json';
file_put_contents($target, json_encode($source, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
if (!is_file($root . '/.composer-redaktion-dev.lock')) {
    copy($root . '/composer.lock', $root . '/.composer-redaktion-dev.lock');
}
echo "Created the local Redaktion Composer profile. The project manifest and lock are unchanged.\n";
