<?php

declare(strict_types=1);

use Webconsulting\Flue\Controller\FlueModuleController;

return [
    'web_flue' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/web/flue',
        'labels' => 'LLL:EXT:flue/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'Flue',
        'iconIdentifier' => 'flue-module',
        'controllerActions' => [
            FlueModuleController::class => [
                'list',
                'run',
                'show',
            ],
        ],
    ],
];
