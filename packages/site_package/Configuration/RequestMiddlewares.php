<?php

declare(strict_types=1);

use Webconsulting\SitePackage\Middleware\CowriterPreloadMiddleware;

return [
    'frontend' => [
        'webconsulting/site-package/cowriter-preload' => [
            'target' => CowriterPreloadMiddleware::class,
            // Queue Cowriter before Visual Editor assembles its import map.
            'before' => [
                'typo3/cms-visual-editor/persistence-middleware',
            ],
            'after' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
