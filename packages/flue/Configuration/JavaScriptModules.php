<?php

declare(strict_types=1);

/**
 * Registers the extension's ES modules with the backend import map so templates
 * can load them via "@webconsulting/flue/<file>.js".
 */
return [
    'dependencies' => ['backend'],
    'imports' => [
        '@webconsulting/flue/' => 'EXT:flue/Resources/Public/JavaScript/',
    ],
];
